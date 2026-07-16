<?php

declare(strict_types=1);

namespace Hekal\ShipBridge\Fedex\Tests;

use Hekal\ShipBridge\Fedex\FedexServiceProvider;
use Hekal\ShipBridge\ShipBridgeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ShipBridgeServiceProvider::class,
            FedexServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('shipbridge.default', 'fedex');
        $app['config']->set('shipbridge.drivers.fedex.base_url', 'https://fedex.test/v1');
        $app['config']->set('shipbridge.drivers.fedex.token', 'test-token');
    }
}
