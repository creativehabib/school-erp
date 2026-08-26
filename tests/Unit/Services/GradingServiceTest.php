<?php

use App\Services\GradingService;

it('returns the Bangladesh national grade for each boundary', function (float $mark, string $grade, float $gpa) {
    expect((new GradingService)->calculateGradeAndGpa($mark))->toBe(['grade' => $grade, 'gpa' => $gpa]);
})->with([
    'A plus' => [80, 'A+', 5.00],
    'A' => [70, 'A', 4.00],
    'A minus' => [60, 'A-', 3.50],
    'B' => [50, 'B', 3.00],
    'C' => [40, 'C', 2.00],
    'D' => [33, 'D', 1.00],
    'F' => [32, 'F', 0.00],
]);

it('normalizes papers that are not marked out of one hundred', function () {
    expect((new GradingService)->calculateForPaper(40, 50))->toBe(['grade' => 'A+', 'gpa' => 5.0]);
});

it('rejects percentage marks outside zero to one hundred', function (float $mark) {
    (new GradingService)->calculateGradeAndGpa($mark);
})->with([-1, 101])->throws(InvalidArgumentException::class);

it('rejects marks outside the paper range', function (float $obtained, float $full) {
    (new GradingService)->calculateForPaper($obtained, $full);
})->with([
    'negative mark' => [-1, 100],
    'above full marks' => [101, 100],
    'invalid full marks' => [0, 0],
])->throws(InvalidArgumentException::class);
