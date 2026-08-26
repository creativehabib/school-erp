<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class GradingService
{
    /**
     * Resolve the Bangladesh national letter grade and GPA for a percentage mark.
     *
     * @return array{grade: string, gpa: float}
     */
    public function calculateGradeAndGpa(float $totalMark): array
    {
        if ($totalMark < 0 || $totalMark > 100) {
            throw new InvalidArgumentException('Total marks must be between zero and one hundred.');
        }

        return match (true) {
            $totalMark >= 80 => ['grade' => 'A+', 'gpa' => 5.00],
            $totalMark >= 70 => ['grade' => 'A', 'gpa' => 4.00],
            $totalMark >= 60 => ['grade' => 'A-', 'gpa' => 3.50],
            $totalMark >= 50 => ['grade' => 'B', 'gpa' => 3.00],
            $totalMark >= 40 => ['grade' => 'C', 'gpa' => 2.00],
            $totalMark >= 33 => ['grade' => 'D', 'gpa' => 1.00],
            default => ['grade' => 'F', 'gpa' => 0.00],
        };
    }

    /**
     * Normalize a paper with arbitrary full marks before grading it.
     *
     * @return array{grade: string, gpa: float}
     */
    public function calculateForPaper(float $obtainedMarks, float $fullMarks): array
    {
        if ($fullMarks <= 0) {
            throw new InvalidArgumentException('Full marks must be greater than zero.');
        }

        if ($obtainedMarks < 0 || $obtainedMarks > $fullMarks) {
            throw new InvalidArgumentException('Obtained marks must be between zero and full marks.');
        }

        return $this->calculateGradeAndGpa(($obtainedMarks / $fullMarks) * 100);
    }
}
