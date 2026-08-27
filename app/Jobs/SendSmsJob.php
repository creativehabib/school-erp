<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly string $phone, public readonly string $message)
    {
        $this->onQueue('notifications');
    }

    public function handle(SmsService $sms): void
    {
        $sms->sendSms($this->phone, $this->message);
    }
}
