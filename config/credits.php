<?php

/*
| Who made the application, shown in the footer. A fork or a company running its own installation can change these
| in .env; the licence (MIT) asks only that the copyright notice stays in the source.
*/
return [
    'author' => env('APP_CREDITS_AUTHOR', 'Michell Hoduń'),
    'since' => (int) env('APP_CREDITS_SINCE', 2026),
    'url' => env('APP_CREDITS_URL', 'https://github.com/b44x/ksef-app'),
    'license' => 'MIT',
];
