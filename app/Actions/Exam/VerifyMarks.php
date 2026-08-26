<?php

declare(strict_types=1);

namespace App\Actions\Exam;

use App\Models\Exam\ExamSubject;
use App\Models\Exam\Mark;
use App\Models\User;

/**
 * A second signature on a paper's marks.
 *
 * Schools run a two-person rule on marks - the subject teacher enters, the head or
 * exam controller verifies - because a transposed digit found after publication is an
 * embarrassment that cannot be quietly fixed. Verification is a separate action from
 * entry so the permission can be granted separately.
 */
final class VerifyMarks
{
    public function handle(ExamSubject $examSubject, User $verifiedBy): int
    {
        return Mark::query()
            ->where('exam_subject_id', $examSubject->getKey())
            ->whereNull('verified_at')
            ->update([
                'verified_by' => $verifiedBy->getKey(),
                'verified_at' => now(),
            ]);
    }
}
