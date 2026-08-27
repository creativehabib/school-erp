<?php

declare(strict_types=1);

namespace App\Livewire\Library;

use App\Enums\BookCopyStatus;
use App\Models\Library\Book;
use App\Models\Library\BookCategory;
use App\Models\Library\BookCopy;
use App\Models\Library\Shelf;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Book Inventory')]
class BookInventory extends Component
{
    public ?int $editingBookId = null;
    public ?int $bookCategoryId = null;
    public ?int $shelfId = null;
    public string $title = '';
    public string $author = '';
    public string $publisher = '';
    public string $isbn = '';
    public int $totalCopies = 1;
    public bool $isActive = true;

    public function mount(): void
    {
        Gate::authorize('library.book.view');
    }

    /** @return Collection<int, Book> */
    #[Computed]
    public function books(): Collection
    {
        return Book::query()->with(['category:id,name', 'shelf:id,name,rack'])
            ->withCount(['copies', 'availableCopies'])->orderBy('title')->get();
    }

    #[Computed] public function categories(): Collection { return BookCategory::query()->active()->orderBy('name')->get(['id', 'name']); }
    #[Computed] public function shelves(): Collection { return Shelf::query()->active()->orderBy('name')->get(['id', 'name', 'rack']); }

    public function create(): void
    {
        Gate::authorize('library.book.create');
        $this->resetForm();
        Flux::modal('book-form')->show();
    }

    public function edit(int $bookId): void
    {
        Gate::authorize('library.book.update');
        $book = Book::query()->withCount('copies')->findOrFail($bookId);
        $this->editingBookId = $book->id;
        $this->bookCategoryId = $book->book_category_id;
        $this->shelfId = $book->shelf_id;
        $this->title = $book->title;
        $this->author = $book->author ?? '';
        $this->publisher = $book->publisher ?? '';
        $this->isbn = $book->isbn ?? '';
        $this->totalCopies = $book->copies_count;
        $this->isActive = $book->is_active;
        Flux::modal('book-form')->show();
    }

    public function save(): void
    {
        Gate::authorize($this->editingBookId === null ? 'library.book.create' : 'library.book.update');
        $validated = $this->validate([
            'bookCategoryId' => ['required', 'integer', Rule::exists(BookCategory::class, 'id')->where('is_active', true)],
            'shelfId' => ['nullable', 'integer', Rule::exists(Shelf::class, 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'], 'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:20', Rule::unique(Book::class, 'isbn')->ignore($this->editingBookId)],
            'totalCopies' => ['required', 'integer', 'min:1', 'max:1000'], 'isActive' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated): void {
            $book = Book::query()->updateOrCreate(['id' => $this->editingBookId], [
                'book_category_id' => $validated['bookCategoryId'], 'shelf_id' => $validated['shelfId'],
                'title' => $validated['title'], 'author' => $validated['author'] ?: null,
                'publisher' => $validated['publisher'] ?: null, 'isbn' => $validated['isbn'] ?: null,
                'is_active' => $validated['isActive'],
            ]);
            $existing = $book->copies()->count();
            if ($validated['totalCopies'] < $existing) {
                throw ValidationException::withMessages(['totalCopies' => __('Existing physical copies cannot be removed from inventory history.')]);
            }
            for ($number = $existing + 1; $number <= $validated['totalCopies']; $number++) {
                BookCopy::query()->create([
                    'book_id' => $book->id, 'shelf_id' => $validated['shelfId'],
                    'accession_no' => sprintf('BK-%06d-%03d', $book->id, $number),
                    'status' => BookCopyStatus::Available,
                ]);
            }
        });
        unset($this->books);
        Flux::modal('book-form')->close();
        Flux::toast(variant: 'success', text: __('Book inventory saved.'));
    }

    public function delete(int $bookId): void
    {
        Gate::authorize('library.book.delete');
        $book = Book::query()->withCount('copies')->findOrFail($bookId);
        if ($book->copies_count > 0) {
            throw ValidationException::withMessages(['bookDeletion' => __('A title with physical copies cannot be deleted; mark it inactive instead.')]);
        }
        $book->delete(); unset($this->books);
        Flux::toast(variant: 'success', text: __('Book deleted.'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingBookId', 'bookCategoryId', 'shelfId', 'title', 'author', 'publisher', 'isbn']);
        $this->totalCopies = 1; $this->isActive = true; $this->resetValidation();
    }
}
