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
        $app['config']->set('shipbridge.drivers.fedex.base_url', 'https://apis-sandbox.fedex.com');
        $app['config']->set('shipbridge.drivers.fedex.client_id', 'test-client-id');
        $app['config']->set('shipbridge.drivers.fedex.client_secret', 'test-client-secret');
        $app['config']->set('shipbridge.drivers.fedex.account_number', '123456789');
    }
}
