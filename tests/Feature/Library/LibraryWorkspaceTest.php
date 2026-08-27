<?php

use App\Enums\RoleName;
use App\Livewire\Library\BookInventory;
use App\Models\Academic\Student;
use App\Models\Library\BookCategory;
use App\Models\Library\BookIssue;
use App\Models\Library\Shelf;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('administrators can open library management pages', function (string $route, string $heading) {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $this->actingAs($admin)->get(route($route))->assertOk()->assertSee($heading);
})->with([
    ['library.admin.books', 'Book Inventory'],
    ['library.admin.issues', 'Issue & Return Books'],
]);

test('students cannot open library management pages', function () {
    $student = User::factory()->create();
    $student->assignRole(RoleName::Student->value);

    $this->actingAs($student)->get(route('library.admin.books'))->assertForbidden();
});

test('an administrator can create a title with physical copies', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $category = BookCategory::query()->create(['name' => 'Science', 'code' => 'SCI']);
    $shelf = Shelf::query()->create(['name' => 'Science Rack', 'code' => 'R-01']);

    Livewire::actingAs($admin)->test(BookInventory::class)
        ->set('bookCategoryId', $category->id)
        ->set('shelfId', $shelf->id)
        ->set('title', 'General Science')
        ->set('author', 'A. Author')
        ->set('isbn', '978000000001')
        ->set('totalCopies', 3)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('books', ['title' => 'General Science', 'book_category_id' => $category->id]);
    $this->assertDatabaseCount('book_copies', 3);
});

test('students with profiles can open their library history', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Student->value);
    Student::query()->create([
        'user_id' => $user->id, 'admission_no' => 'ADM-001', 'name_en' => 'Library Student',
        'date_of_birth' => '2014-01-01', 'gender' => 'male', 'admission_date' => '2026-01-01',
    ]);

    $this->actingAs($user)->get(route('library.my_books'))->assertOk()->assertSee('My Library Books');
});

test('late return fine uses snapshotted daily rate and grace period', function () {
    $issue = new BookIssue([
        'status' => 'issued', 'issued_on' => '2026-08-01', 'due_date' => '2026-08-10',
        'fine_per_day' => 10, 'grace_days' => 1, 'max_fine' => 100,
    ]);

    expect($issue->calculateFine(Carbon::parse('2026-08-15')))->toBe(40.0);
});
