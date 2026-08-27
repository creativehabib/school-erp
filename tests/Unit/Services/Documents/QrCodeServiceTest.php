<?php

use App\Services\Documents\QrCodeService;

test('it renders an svg qr code without the simple qrcode facade', function () {
    $svg = (new QrCodeService)->raw('STU:ADM-001', 140, 'svg');

    expect($svg)
        ->toContain('<svg')
        ->toContain('</svg>');
});

test('it returns a correctly labelled data uri when png is unavailable', function () {
    $uri = (new QrCodeService)->dataUri('STU:ADM-001', 140, 'png');

    expect($uri)->toStartWith(
        extension_loaded('imagick') ? 'data:image/png;base64,' : 'data:image/svg+xml;base64,',
    );
});
