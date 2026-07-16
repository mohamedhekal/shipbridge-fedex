<?php

declare(strict_types=1);

use Hekal\ShipBridge\DTOs\Address;
use Hekal\ShipBridge\DTOs\CreateShipmentRequest;
use Hekal\ShipBridge\DTOs\Parcel;
use Hekal\ShipBridge\Enums\ShipmentStatus;
use Hekal\ShipBridge\Facades\ShipBridge;
use Illuminate\Support\Facades\Http;

it('creates a FedEx shipment through ShipBridge', function (): void {
    Http::fake([
        'https://fedex.test/v1/shipments' => Http::response([
            'id' => 'FEDEX-1',
            'tracking_number' => 'TRK-FEDEX-1',
            'status' => 'created',
            'carrier' => 'fedex',
            'label_url' => 'https://labels.test/fedex.pdf',
        ], 200),
    ]);

    $result = ShipBridge::driver('fedex')->createShipment(new CreateShipmentRequest(
        origin: new Address('Warehouse', '1 Industrial Rd', 'Cairo', 'EG'),
        destination: new Address('Customer', '12 Nile St', 'Giza', 'EG', phone: '01000000000'),
        parcels: [new Parcel(weightKg: 1.5)],
        reference: 'ORD-100',
    ));

    expect($result->id)->toBe('FEDEX-1')
        ->and($result->trackingNumber)->toBe('TRK-FEDEX-1')
        ->and($result->carrier)->toBe('fedex')
        ->and($result->status)->toBe(ShipmentStatus::Created);
});

it('tracks a FedEx shipment', function (): void {
    Http::fake([
        'https://fedex.test/v1/shipments/track/*' => Http::response([
            'tracking_number' => 'TRK-1',
            'status' => 'in_transit',
            'events' => [
                [
                    'status' => 'picked_up',
                    'description' => 'Picked up',
                    'occurred_at' => '2026-07-16T10:00:00Z',
                    'location' => 'Cairo',
                ],
            ],
        ], 200),
    ]);

    $tracking = ShipBridge::driver('fedex')->track('TRK-1');

    expect($tracking->status)->toBe(ShipmentStatus::InTransit)
        ->and($tracking->events)->toHaveCount(1);
});
