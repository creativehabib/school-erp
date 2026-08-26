<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum OtpPurpose: string
{
    use HasOptions;

    case Login = 'login';
    case PasswordReset = 'password_reset';
    case PhoneVerify = 'phone_verify';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Login',
            self::PasswordReset => 'Password Reset',
            self::PhoneVerify => 'Phone Verification',
        };
    }
}
