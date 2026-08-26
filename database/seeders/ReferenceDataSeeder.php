<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BorrowerType;
use App\Enums\DocumentType;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $this->upsert('boards', [
            ['name' => 'Dhaka Education Board', 'name_bn' => 'ঢাকা শিক্ষা বোর্ড', 'code' => 'DHAKA'],
            ['name' => 'Cumilla Education Board', 'name_bn' => 'কুমিল্লা শিক্ষা বোর্ড', 'code' => 'CUMILLA'],
            ['name' => 'Rajshahi Education Board', 'name_bn' => 'রাজশাহী শিক্ষা বোর্ড', 'code' => 'RAJSHAHI'],
            ['name' => 'Jashore Education Board', 'name_bn' => 'যশোর শিক্ষা বোর্ড', 'code' => 'JASHORE'],
            ['name' => 'Chattogram Education Board', 'name_bn' => 'চট্টগ্রাম শিক্ষা বোর্ড', 'code' => 'CHATTOGRAM'],
            ['name' => 'Barishal Education Board', 'name_bn' => 'বরিশাল শিক্ষা বোর্ড', 'code' => 'BARISHAL'],
            ['name' => 'Sylhet Education Board', 'name_bn' => 'সিলেট শিক্ষা বোর্ড', 'code' => 'SYLHET'],
            ['name' => 'Dinajpur Education Board', 'name_bn' => 'দিনাজপুর শিক্ষা বোর্ড', 'code' => 'DINAJPUR'],
            ['name' => 'Mymensingh Education Board', 'name_bn' => 'ময়মনসিংহ শিক্ষা বোর্ড', 'code' => 'MYMENSINGH'],
            ['name' => 'Bangladesh Madrasah Education Board', 'name_bn' => 'বাংলাদেশ মাদ্রাসা শিক্ষা বোর্ড', 'code' => 'MADRASAH'],
            ['name' => 'Bangladesh Technical Education Board', 'name_bn' => 'বাংলাদেশ কারিগরি শিক্ষা বোর্ড', 'code' => 'TECHNICAL'],
        ], 'code', $now);

        $this->upsert('departments', [
            ['name' => 'Academic', 'code' => 'ACADEMIC'],
            ['name' => 'Administration', 'code' => 'ADMIN'],
            ['name' => 'Accounts', 'code' => 'ACCOUNTS'],
            ['name' => 'Library', 'code' => 'LIBRARY'],
        ], 'code', $now);

        $this->upsert('designations', [
            ['name' => 'Head Teacher', 'name_bn' => 'প্রধান শিক্ষক', 'code' => 'HEAD_TEACHER', 'rank' => 10, 'is_teaching' => true],
            ['name' => 'Assistant Teacher', 'name_bn' => 'সহকারী শিক্ষক', 'code' => 'ASSISTANT_TEACHER', 'rank' => 50, 'is_teaching' => true],
            ['name' => 'Accountant', 'name_bn' => 'হিসাবরক্ষক', 'code' => 'ACCOUNTANT', 'rank' => 60, 'is_teaching' => false],
            ['name' => 'Librarian', 'name_bn' => 'গ্রন্থাগারিক', 'code' => 'LIBRARIAN', 'rank' => 70, 'is_teaching' => false],
        ], 'code', $now);

        $this->upsert('leave_types', [
            ['name' => 'Casual Leave', 'code' => 'CL', 'annual_quota' => 20, 'is_paid' => true],
            ['name' => 'Sick Leave', 'code' => 'SL', 'annual_quota' => 14, 'is_paid' => true, 'requires_document' => true],
            ['name' => 'Unpaid Leave', 'code' => 'UL', 'annual_quota' => 0, 'is_paid' => false],
        ], 'code', $now);

        $this->upsert('salary_components', [
            ['name' => 'House Rent', 'code' => 'HOUSE_RENT', 'type' => 'earning', 'calculation' => 'percent_of_basic', 'default_value' => 50, 'applies_to_all' => true, 'sort_order' => 10],
            ['name' => 'Medical Allowance', 'code' => 'MEDICAL', 'type' => 'earning', 'calculation' => 'fixed', 'default_value' => 1500, 'applies_to_all' => true, 'sort_order' => 20],
            ['name' => 'Provident Fund', 'code' => 'PF', 'type' => 'deduction', 'calculation' => 'percent_of_basic', 'default_value' => 10, 'applies_to_all' => true, 'sort_order' => 30],
        ], 'code', $now);

        $this->upsert('fee_heads', [
            ['name' => 'Tuition Fee', 'name_bn' => 'বেতন', 'code' => 'TUITION', 'category' => 'tuition', 'frequency' => 'monthly', 'sort_order' => 10],
            ['name' => 'Exam Fee', 'name_bn' => 'পরীক্ষার ফি', 'code' => 'EXAM', 'category' => 'exam', 'frequency' => 'half_yearly', 'sort_order' => 20],
            ['name' => 'Admission Fee', 'name_bn' => 'ভর্তি ফি', 'code' => 'ADMISSION', 'category' => 'admission', 'frequency' => 'one_time', 'sort_order' => 30],
        ], 'code', $now);

        $this->upsert('expense_categories', [
            ['name' => 'Utilities', 'name_bn' => 'ইউটিলিটি', 'code' => 'UTILITIES'],
            ['name' => 'Stationery', 'name_bn' => 'স্টেশনারি', 'code' => 'STATIONERY'],
            ['name' => 'Maintenance', 'name_bn' => 'রক্ষণাবেক্ষণ', 'code' => 'MAINTENANCE'],
        ], 'code', $now);

        $this->upsert('financial_accounts', [
            ['name' => 'Cash Counter', 'code' => 'CASH', 'type' => 'cash', 'is_default' => true],
            ['name' => 'Bank Account', 'code' => 'BANK', 'type' => 'bank'],
            ['name' => 'Mobile Financial Service', 'code' => 'MFS', 'type' => 'mobile_wallet'],
        ], 'code', $now);

        foreach (BorrowerType::cases() as $borrowerType) {
            DB::table('library_rules')->updateOrInsert(
                ['borrower_type' => $borrowerType->value, 'effective_from' => '2026-01-01'],
                ['max_books' => $borrowerType === BorrowerType::Student ? 2 : 5, 'loan_days' => 14, 'grace_days' => 1, 'fine_per_day' => 5, 'max_fine' => 500, 'max_renewals' => 1, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        foreach (DocumentType::cases() as $documentType) {
            DB::table('document_templates')->updateOrInsert(
                ['name' => $documentType->label().' (System)', 'type' => $documentType->value],
                ['is_system' => true, 'is_default' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $this->seedGradeScale($now);
    }

    private function seedGradeScale(CarbonInterface $now): void
    {
        DB::table('grade_scales')->updateOrInsert(
            ['name' => 'Bangladesh National GPA 5.00'],
            ['name_bn' => 'বাংলাদেশ জাতীয় জিপিএ ৫.০০', 'max_gpa' => 5, 'optional_subject_deduction' => 2, 'is_default' => true, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now],
        );

        $gradeScaleId = DB::table('grade_scales')->where('name', 'Bangladesh National GPA 5.00')->value('id');
        $bands = [
            ['name' => 'A+', 'min_marks' => 80, 'max_marks' => 100, 'gpa' => 5, 'remarks' => 'Excellent'],
            ['name' => 'A', 'min_marks' => 70, 'max_marks' => 79.99, 'gpa' => 4, 'remarks' => 'Very Good'],
            ['name' => 'A-', 'min_marks' => 60, 'max_marks' => 69.99, 'gpa' => 3.5, 'remarks' => 'Good'],
            ['name' => 'B', 'min_marks' => 50, 'max_marks' => 59.99, 'gpa' => 3, 'remarks' => 'Satisfactory'],
            ['name' => 'C', 'min_marks' => 40, 'max_marks' => 49.99, 'gpa' => 2, 'remarks' => 'Average'],
            ['name' => 'D', 'min_marks' => 33, 'max_marks' => 39.99, 'gpa' => 1, 'remarks' => 'Pass'],
            ['name' => 'F', 'min_marks' => 0, 'max_marks' => 32.99, 'gpa' => 0, 'remarks' => 'Fail', 'is_failing' => true],
        ];

        foreach ($bands as $serial => $band) {
            DB::table('grade_scale_items')->updateOrInsert(
                ['grade_scale_id' => $gradeScaleId, 'name' => $band['name']],
                [...$band, 'grade_scale_id' => $gradeScaleId, 'serial' => $serial + 1, 'is_failing' => $band['is_failing'] ?? false, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function upsert(string $table, array $rows, string $uniqueBy, CarbonInterface $now): void
    {
        foreach ($rows as $row) {
            DB::table($table)->updateOrInsert(
                [$uniqueBy => $row[$uniqueBy]],
                [...$row, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }
}
