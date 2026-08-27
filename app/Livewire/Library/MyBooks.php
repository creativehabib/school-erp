<?php

declare(strict_types=1);

namespace App\Livewire\Library;

use App\Models\Academic\Student;
use App\Models\Hrm\Employee;
use App\Models\Library\BookIssue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My Library Books')]
class MyBooks extends Component
{
    public function mount(): void
    {
        Gate::authorize('library.issue.view');
        $this->borrower();
    }

    /** @return Collection<int, BookIssue> */
    #[Computed]
    public function issues(): Collection
    {
        return $this->borrower()->bookIssues()->with('bookCopy.book:id,title,author')
            ->latest('issued_on')->get();
    }

    private function borrower(): Student|Employee
    {
        $user = Auth::user();
        $borrower = $user?->student()->first() ?? $user?->employee()->first();
        abort_unless($borrower instanceof Student || $borrower instanceof Employee, 404);

        return $borrower;
    }
}
