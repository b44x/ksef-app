<?php

namespace App\Providers;

use App\Ksef\KsefGateway;
use App\Ksef\SdkKsefGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The HTTP client the KSeF SDK uses (any PSR-18 client works; tests replace it). Timeouts are set here and
        // TLS verification stays on.
        $this->app->singleton(ClientInterface::class, fn () => new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]));
        $this->app->singleton(HttpFactory::class);
        $this->app->bind(RequestFactoryInterface::class, HttpFactory::class);
        $this->app->bind(StreamFactoryInterface::class, HttpFactory::class);

        $this->app->bind(KsefGateway::class, SdkKsefGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
