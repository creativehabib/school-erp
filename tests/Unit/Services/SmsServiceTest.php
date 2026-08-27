<?php

use App\Services\SmsService;
use Illuminate\Support\Facades\Http;

test('it sends a normalized Bangladeshi phone number to the configured gateway', function () {
    config()->set('services.sms', [
        'url' => 'https://sms.example.test/send', 'api_key' => 'secret',
        'client_id' => 'school', 'sender_id' => 'SCHOOL', 'method' => 'POST', 'timeout' => 10,
    ]);
    Http::fake(['sms.example.test/*' => Http::response(['status' => 'success'])]);

    (new SmsService)->sendSms('01712-345678', 'Attendance alert');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://sms.example.test/send'
        && $request['mobile'] === '8801712345678'
        && $request['message'] === 'Attendance alert'
        && $request['api_key'] === 'secret');
});

test('it rejects invalid Bangladeshi mobile numbers before contacting the gateway', function () {
    config()->set('services.sms.url', 'https://sms.example.test/send');
    Http::fake();

    (new SmsService)->sendSms('12345', 'Invalid number');
})->throws(InvalidArgumentException::class);
