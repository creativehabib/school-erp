<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $year = (int) now()->format('Y');

        $dhakaBoardId = DB::table('boards')->where('code', 'DHAKA')->value('id');
        DB::table('school_profile')->updateOrInsert(['id' => 1], [
            'name_en' => 'Bangladesh Model School',
            'name_bn' => 'বাংলাদেশ মডেল স্কুল',
            'eiin' => '123456',
            'board_id' => $dhakaBoardId,
            'address_en' => 'Dhaka, Bangladesh',
            'phone' => '02-00000000',
            'email' => 'info@school.test',
            'head_teacher_name' => 'Md. Karim Uddin',
            'established_year' => 2000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('academic_sessions')->updateOrInsert(['name' => (string) $year], [
            'year' => $year,
            'starts_on' => "{$year}-01-01",
            'ends_on' => "{$year}-12-31",
            'is_current' => true,
            'is_locked' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sessionId = DB::table('academic_sessions')->where('name', (string) $year)->value('id');

        foreach (range(6, 10) as $level) {
            DB::table('school_classes')->updateOrInsert(['code' => "CLASS_{$level}"], [
                'name' => "Class {$level}",
                'name_bn' => 'শ্রেণি '.$level,
                'level' => $level,
                'has_groups' => $level >= 9,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('shifts')->updateOrInsert(['name' => 'Morning'], ['name_bn' => 'প্রভাতী', 'starts_at' => '08:00', 'ends_at' => '13:00', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $shiftId = DB::table('shifts')->where('name', 'Morning')->value('id');
        $classId = DB::table('school_classes')->where('code', 'CLASS_6')->value('id');
        DB::table('sections')->updateOrInsert(['school_class_id' => $classId, 'shift_id' => $shiftId, 'name' => 'A'], ['capacity' => 40, 'room_no' => '201', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $sectionId = DB::table('sections')->where(['school_class_id' => $classId, 'shift_id' => $shiftId, 'name' => 'A'])->value('id');

        foreach ([
            ['English', 'ইংরেজি', 'ENG'],
            ['Bangla', 'বাংলা', 'BAN'],
            ['Mathematics', 'গণিত', 'MATH'],
            ['Science', 'বিজ্ঞান', 'SCI'],
        ] as $serial => [$name, $nameBn, $code]) {
            DB::table('subjects')->updateOrInsert(['code' => $code], ['name' => $name, 'name_bn' => $nameBn, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            $subjectId = DB::table('subjects')->where('code', $code)->value('id');
            DB::table('class_subjects')->updateOrInsert(['school_class_id' => $classId, 'subject_id' => $subjectId, 'group' => null], ['full_marks' => 100, 'pass_marks' => 33, 'serial' => $serial + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $this->demoUser('Demo Admin', 'admin-demo@school.test', '01700000004', RoleName::Admin);
        $teacher = $this->demoUser('Demo Teacher', 'teacher@school.test', '01700000001', RoleName::Teacher);
        $student = $this->demoUser('Demo Student', 'student@school.test', '01700000002', RoleName::Student);
        $guardian = $this->demoUser('Demo Father', 'father@school.test', '01700000003', RoleName::Guardian);

        $departmentId = DB::table('departments')->where('code', 'ACADEMIC')->value('id');
        $designationId = DB::table('designations')->where('code', 'ASSISTANT_TEACHER')->value('id');
        DB::table('employees')->updateOrInsert(['employee_code' => 'T-0001'], ['user_id' => $teacher->id, 'name_en' => $teacher->name, 'department_id' => $departmentId, 'designation_id' => $designationId, 'type' => 'teaching', 'employment_type' => 'permanent', 'joining_date' => "{$year}-01-01", 'status' => 'active', 'gender' => 'male', 'phone' => $teacher->phone, 'email' => $teacher->email, 'basic_salary' => 30000, 'created_at' => $now, 'updated_at' => $now]);

        DB::table('students')->updateOrInsert(['admission_no' => 'ST-0001'], ['user_id' => $student->id, 'name_en' => $student->name, 'father_name' => $guardian->name, 'mother_name' => 'Mrs. Rahman', 'date_of_birth' => ($year - 12).'-01-01', 'gender' => 'male', 'nationality' => 'Bangladeshi', 'board_id' => $dhakaBoardId, 'admission_date' => "{$year}-01-01", 'admission_session_id' => $sessionId, 'admission_class_id' => $classId, 'phone' => $student->phone, 'email' => $student->email, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $studentId = DB::table('students')->where('admission_no', 'ST-0001')->value('id');
        DB::table('student_enrollments')->updateOrInsert(['student_id' => $studentId, 'academic_session_id' => $sessionId], ['school_class_id' => $classId, 'section_id' => $sectionId, 'shift_id' => $shiftId, 'class_roll' => '1', 'status' => 'running', 'is_current' => true, 'enrolled_on' => "{$year}-01-01", 'created_at' => $now, 'updated_at' => $now]);

        DB::table('guardians')->updateOrInsert(['user_id' => $guardian->id], ['name_en' => $guardian->name, 'relation' => 'father', 'phone' => $guardian->phone, 'email' => $guardian->email, 'occupation' => 'Business', 'address' => 'Dhaka, Bangladesh', 'created_at' => $now, 'updated_at' => $now]);
        $guardianId = DB::table('guardians')->where('user_id', $guardian->id)->value('id');
        DB::table('guardian_student')->updateOrInsert(['guardian_id' => $guardianId, 'student_id' => $studentId], ['is_primary' => true, 'receives_sms' => true, 'can_collect_student' => true, 'created_at' => $now, 'updated_at' => $now]);

        $this->seedLibrary($now);
    }

    private function demoUser(string $name, string $email, string $phone, RoleName $role): User
    {
        $user = User::updateOrCreate(['email' => $email], ['name' => $name, 'phone' => $phone, 'password' => 'password', 'status' => 'active', 'email_verified_at' => now(), 'must_change_password' => true]);
        $user->syncRoles($role->value);

        return $user;
    }

    private function seedLibrary(CarbonInterface $now): void
    {
        DB::table('book_categories')->updateOrInsert(['code' => 'TEXTBOOK'], ['name' => 'Textbook', 'name_bn' => 'পাঠ্যপুস্তক', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('shelves')->updateOrInsert(['code' => 'A-01'], ['name' => 'Shelf A-01', 'room' => 'Library', 'rack' => 'A', 'row' => '01', 'capacity' => 100, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $categoryId = DB::table('book_categories')->where('code', 'TEXTBOOK')->value('id');
        $shelfId = DB::table('shelves')->where('code', 'A-01')->value('id');
        DB::table('books')->updateOrInsert(['isbn' => '9789840000001'], ['book_category_id' => $categoryId, 'shelf_id' => $shelfId, 'title' => 'Bangla Literature', 'title_bn' => 'বাংলা সাহিত্য', 'author' => 'NCTB', 'publisher' => 'NCTB', 'language' => 'Bengali', 'price' => 250, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $bookId = DB::table('books')->where('isbn', '9789840000001')->value('id');
        DB::table('book_copies')->updateOrInsert(['accession_no' => 'ACC-0001'], ['book_id' => $bookId, 'shelf_id' => $shelfId, 'barcode' => 'BOOK-0001', 'status' => 'available', 'condition' => 'good', 'acquired_on' => now()->toDateString(), 'acquisition_source' => 'Purchase', 'purchase_price' => 250, 'created_at' => $now, 'updated_at' => $now]);
    }
}
