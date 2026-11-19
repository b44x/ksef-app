<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

/** Runs only in the desktop app: opens the window that shows the same application a browser would. */
class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {
        Window::open()->title(config('app.name'))->width(1100)->height(800);
    }

    /**
     * @return array<string, string>
     */
    public function phpIni(): array
    {
        return [];
    }
}
