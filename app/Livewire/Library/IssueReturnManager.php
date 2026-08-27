<?php

declare(strict_types=1);

namespace App\Livewire\Library;

use App\Actions\Library\IssueBook;
use App\Actions\Library\ReturnBook;
use App\Models\Academic\Student;
use App\Models\Hrm\Employee;
use App\Models\Library\BookCopy;
use App\Models\Library\BookIssue;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Issue & Return Books')]
class IssueReturnManager extends Component
{
    public ?int $bookCopyId = null;
    public ?int $borrowerUserId = null;
    public ?int $loanDays = null;

    public function mount(): void
    {
        Gate::authorize('library.issue.view');
    }

    /** @return Collection<int, BookCopy> */
    #[Computed]
    public function availableCopies(): Collection
    {
        return BookCopy::query()->available()->whereHas('book', fn ($query) => $query->lendable())
            ->with('book:id,title')->orderBy('accession_no')->get();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function borrowers(): Collection
    {
        return User::query()->whereHas('roles', fn ($query) => $query->whereIn('name', ['student', 'teacher']))
            ->where(fn ($query) => $query->whereHas('student')->orWhereHas('employee'))
            ->orderBy('name')->get(['id', 'name', 'email']);
    }

    /** @return Collection<int, BookIssue> */
    #[Computed]
    public function openIssues(): Collection
    {
        return BookIssue::query()->open()->with(['bookCopy.book:id,title', 'borrower'])->orderBy('due_date')->get();
    }

    public function issue(IssueBook $issuer): void
    {
        Gate::authorize('library.issue.create');
        $validated = $this->validate([
            'bookCopyId' => ['required', 'integer', Rule::exists(BookCopy::class, 'id')],
            'borrowerUserId' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'loanDays' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        $user = User::query()->with(['student', 'employee'])->findOrFail($validated['borrowerUserId']);
        $borrower = $user->student ?? $user->employee;
        if (! $borrower instanceof Student && ! $borrower instanceof Employee) {
            throw ValidationException::withMessages(['borrowerUserId' => __('The selected user has no student or employee profile.')]);
        }
        $actor = Auth::user(); abort_unless($actor instanceof User, 401);
        $issuer->handle(BookCopy::query()->findOrFail($validated['bookCopyId']), $borrower, $actor, loanDays: $validated['loanDays']);
        $this->reset(['bookCopyId', 'borrowerUserId', 'loanDays']);
        unset($this->availableCopies, $this->openIssues);
        Flux::toast(variant: 'success', text: __('Book issued successfully.'));
    }

    public function returnBook(int $issueId, ReturnBook $returner): void
    {
        Gate::authorize('library.issue.return');
        $actor = Auth::user(); abort_unless($actor instanceof User, 401);
        $issue = $returner->handle(BookIssue::query()->open()->findOrFail($issueId), $actor);
        unset($this->availableCopies, $this->openIssues);
        Flux::toast(variant: 'success', text: __('Book returned. Fine: BDT :fine', ['fine' => number_format((float) $issue->fine_amount, 2)]));
    }
}
