<?php

declare(strict_types=1);

namespace App\Services\Exam;

use InvalidArgumentException;

final class GradingService
{
    /**
     * Resolve the Bangladesh national letter grade and GPA.
     *
     * @return array{grade: string, gpa: float}
     */
    public function grade(float $obtainedMarks, float $fullMarks = 100.0): array
    {
        if ($fullMarks <= 0) {
            throw new InvalidArgumentException('Full marks must be greater than zero.');
        }

        if ($obtainedMarks < 0 || $obtainedMarks > $fullMarks) {
            throw new InvalidArgumentException('Obtained marks must be between zero and full marks.');
        }

        $percentage = ($obtainedMarks / $fullMarks) * 100;

        return match (true) {
            $percentage >= 80 => ['grade' => 'A+', 'gpa' => 5.00],
            $percentage >= 70 => ['grade' => 'A', 'gpa' => 4.00],
            $percentage >= 60 => ['grade' => 'A-', 'gpa' => 3.50],
            $percentage >= 50 => ['grade' => 'B', 'gpa' => 3.00],
            $percentage >= 40 => ['grade' => 'C', 'gpa' => 2.00],
            $percentage >= 33 => ['grade' => 'D', 'gpa' => 1.00],
            default => ['grade' => 'F', 'gpa' => 0.00],
        };
    }
}
