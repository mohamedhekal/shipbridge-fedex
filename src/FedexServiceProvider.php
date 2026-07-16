<?php

declare(strict_types=1);

namespace Hekal\ShipBridge\Fedex;

use Hekal\ShipBridge\Facades\ShipBridge;
use Hekal\ShipBridge\Fedex\Support\PayloadFactory;
use Hekal\ShipBridge\Support\StatusNormalizer;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

final class FedexServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fedex.php', 'shipbridge.drivers.fedex');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/fedex.php' => config_path('shipbridge-fedex.php'),
        ], 'shipbridge-fedex-config');

        ShipBridge::extend('fedex', function ($app, array $config): FedexDriver {
            /** @var array<string, string> $aliases */
            $aliases = config('shipbridge.status_aliases', []);
            /** @var array<string, string> $driverMap */
            $driverMap = $config['status_map'] ?? [];

            $client = new FedexClient($app->make(HttpFactory::class), $config);

            return new FedexDriver(
                client: $client,
                payloads: new PayloadFactory($config),
                normalizer: new StatusNormalizer(array_merge($aliases, $driverMap)),
                config: $config,
            );
        });
    }
}
