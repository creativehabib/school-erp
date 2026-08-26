<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentGateway: string
{
    use HasOptions;

    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Rocket = 'rocket';
    case SslCommerz = 'sslcommerz';

    public function label(): string
    {
        return match ($this) {
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
            self::Rocket => 'Rocket',
            self::SslCommerz => 'SSLCommerz',
        };
    }
}
