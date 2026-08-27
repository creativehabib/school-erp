<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

final class SmsService
{
    public function sendSms(string $phone, string $message): Response
    {
        $url = (string) config('services.sms.url');
        if ($url === '') {
            throw new RuntimeException('SMS_API_URL is not configured.');
        }

        $phone = $this->normalizeBangladeshiPhone($phone);
        if (blank($message)) {
            throw new InvalidArgumentException('SMS message cannot be empty.');
        }

        $payload = [
            'api_key' => config('services.sms.api_key'),
            'client_id' => config('services.sms.client_id'),
            'sender_id' => config('services.sms.sender_id'),
            'mobile' => $phone,
            'message' => $message,
        ];
        $request = $this->request();
        $response = strtoupper((string) config('services.sms.method', 'POST')) === 'GET'
            ? $request->get($url, $payload)
            : $request->asForm()->post($url, $payload);

        return $response->throw();
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('services.sms.timeout', 10))
            ->connectTimeout(5)
            ->retry(2, 250, throw: false);
    }

    private function normalizeBangladeshiPhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($phone, '880')) {
            $phone = substr($phone, 3);
        }
        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }
        if (! preg_match('/^1[3-9]\d{8}$/', $phone)) {
            throw new InvalidArgumentException('A valid Bangladeshi mobile number is required.');
        }

        return '880'.$phone;
    }
}
