<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\Academic\AcademicSession;
use Illuminate\Support\Facades\DB;

final class SaveAcademicSession
{
    /**
     * @param  array{name: string, year: int, starts_on: string, ends_on: string, is_current: bool}  $attributes
     */
    public function handle(?AcademicSession $academicSession, array $attributes): AcademicSession
    {
        return DB::transaction(function () use ($academicSession, $attributes): AcademicSession {
            AcademicSession::query()->lockForUpdate()->get(['id']);

            if ($attributes['is_current']) {
                $currentAcademicYears = AcademicSession::query();

                if ($academicSession !== null) {
                    $currentAcademicYears->where('id', '!=', $academicSession->id);
                }

                $currentAcademicYears->update(['is_current' => false]);
            }

            $academicSession ??= new AcademicSession;
            $academicSession->fill($attributes);
            $academicSession->save();

            return $academicSession->refresh();
        });
    }
}
