<?php

use App\Services\Exam\GradingService;

it('returns the Bangladesh national grade for each boundary', function (float $mark, string $grade, float $gpa) {
    expect((new GradingService)->grade($mark))->toBe(['grade' => $grade, 'gpa' => $gpa]);
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
    expect((new GradingService)->grade(40, 50))->toBe(['grade' => 'A+', 'gpa' => 5.0]);
});

it('rejects marks outside the paper range', function (float $obtained, float $full) {
    (new GradingService)->grade($obtained, $full);
})->with([
    'negative mark' => [-1, 100],
    'above full marks' => [101, 100],
    'invalid full marks' => [0, 0],
])->throws(InvalidArgumentException::class);
