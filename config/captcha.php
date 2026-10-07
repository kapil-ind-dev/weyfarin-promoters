<?php

return [
    'disable' => env('CAPTCHA_DISABLE', false),

    // Characters to use (no visually confusing letters/numbers)
    'characters' => ['2','3','4','6','7','8','9','A','B','C','D','E','F','G','H','J','K','M','N','P','Q','R','T','U','X','Y','Z'],

    // === Simple 4-digit white-background captcha ===
    'default' => [
        'length'     => 4,          // exactly 4 digits/letters
        'width'      => 120,
        'height'     => 40,
        'quality'    => 90,
        'math'       => false,
        'expire'     => 60,
        'encrypt'    => false,
        'bgImage'    => false,      // no noisy background
        'bgColor'    => '#ffffff',  // pure white background
        'fontColors' => ['#000000'],// black text for strong contrast
        'lines'      => 1,          // minimal lines for readability
        'contrast'   => 0,
    ],
];
