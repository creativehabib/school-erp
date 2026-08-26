<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FinancialAccountType: string
{
    use HasOptions;

    case Cash = 'cash';
    case Bank = 'bank';
    case MobileWallet = 'mobile_wallet';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank',
            self::MobileWallet => 'Mobile Wallet',
        };
    }
}
