<?php

use App\Actions\Accounts\CollectFeePayment;
use App\Enums\PaymentMethod;
use App\Jobs\SendSmsJob;
use App\Models\Academic\Guardian;
use App\Models\Academic\Student;
use Illuminate\Support\Facades\Queue;

test('a completed fee payment queues an SMS for the primary guardian', function () {
    Queue::fake();
    $student = Student::query()->create([
        'admission_no' => 'ADM-001', 'name_en' => 'Payment Student',
        'date_of_birth' => '2014-01-01', 'gender' => 'male', 'admission_date' => '2026-01-01',
    ]);
    $guardian = Guardian::query()->create([
        'name_en' => 'Student Father', 'relation' => 'father', 'phone' => '01712345678',
    ]);
    $student->guardians()->attach($guardian->id, [
        'is_primary' => true, 'receives_sms' => true, 'can_collect_student' => true,
    ]);

    $payment = app(CollectFeePayment::class)->handle($student, 1500, PaymentMethod::Cash);

    expect((float) $payment->amount)->toBe(1500.0);
    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->phone === '01712345678'
        && str_contains($job->message, '1,500.00')
        && str_contains($job->message, 'Payment Student'));
});
