<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The exam events a Bangladeshi school actually runs.
 *
 * Terminology varies by school, so this list is intentionally broad; a school
 * uses the three or four cases that match its calendar and ignores the rest.
 */
enum ExamType: string
{
    use HasOptions;

    case ClassTest = 'class_test';
    case Tutorial = 'tutorial';
    case FirstTerm = 'first_term';
    case HalfYearly = 'half_yearly';
    case SecondTerm = 'second_term';
    case PreTest = 'pre_test';
    case Test = 'test';
    case ModelTest = 'model_test';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::ClassTest => 'Class Test',
            self::Tutorial => 'Tutorial',
            self::FirstTerm => 'First Terminal',
            self::HalfYearly => 'Half Yearly',
            self::SecondTerm => 'Second Terminal',
            self::PreTest => 'Pre-Test',
            self::Test => 'Test Exam',
            self::ModelTest => 'Model Test',
            self::Annual => 'Annual / Final',
        };
    }

    /**
     * Whether this exam type normally contributes to the combined annual result.
     *
     * Class tests and tutorials are recorded for continuous assessment but a school
     * rarely wants them dragging the final GPA around, so they default to false and
     * the exam's own `weight` column can still override the behaviour.
     */
    public function countsTowardFinalResult(): bool
    {
        return match ($this) {
            self::ClassTest, self::Tutorial, self::ModelTest => false,
            default => true,
        };
    }
}
