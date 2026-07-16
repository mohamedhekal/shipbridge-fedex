<?php

declare(strict_types=1);

use Hekal\ShipBridge\DTOs\Address;
use Hekal\ShipBridge\DTOs\CreateShipmentRequest;
use Hekal\ShipBridge\DTOs\Parcel;
use Hekal\ShipBridge\Enums\ShipmentStatus;
use Hekal\ShipBridge\Exceptions\ShipBridgeException;
use Hekal\ShipBridge\Facades\ShipBridge;
use Illuminate\Support\Facades\Http;

it('obtains OAuth token then creates a FedEx shipment', function (): void {
    Http::fake([
        'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
            'access_token' => 'fedex-test-token',
            'token_type' => 'bearer',
            'expires_in' => 3600,
        ], 200),
        'https://apis-sandbox.fedex.com/ship/v1/shipments' => Http::response([
            'transactionId' => 'txn-1',
            'output' => [
                'transactionShipments' => [[
                    'masterTrackingNumber' => '794612345678',
                    'pieceResponses' => [[
                        'trackingNumber' => '794612345678',
                        'packageDocuments' => [[
                            'encodedLabel' => base64_encode('%PDF-fake-label'),
                            'docType' => 'PDF',
                        ]],
                    ]],
                ]],
            ],
        ], 200),
    ]);

    $result = ShipBridge::driver('fedex')->createShipment(new CreateShipmentRequest(
        origin: new Address('Warehouse', '1 Industrial Rd', 'Cairo', 'EG', phone: '01011111111', postalCode: '11511'),
        destination: new Address('Customer', '12 Nile St', 'Giza', 'EG', phone: '01000000000', state: 'Giza', postalCode: '12613'),
        parcels: [new Parcel(weightKg: 1.5, description: 'Shoes')],
        reference: 'ORD-100',
    ));

    expect($result->trackingNumber)->toBe('794612345678')
        ->and($result->id)->toBe('794612345678')
        ->and($result->carrier)->toBe('fedex')
        ->and($result->status)->toBe(ShipmentStatus::Created)
        ->and($result->labelUrl)->toBeNull()
        ->and($result->raw['output']['transactionShipments'][0]['pieceResponses'][0]['packageDocuments'][0]['encodedLabel'])->not->toBeEmpty();

    Http::assertSent(function ($request): bool {
        if (str_ends_with($request->url(), '/oauth/token')) {
            return $request->method() === 'POST'
                && ($request->data()['grant_type'] ?? null) === 'client_credentials'
                && ($request->data()['client_id'] ?? null) === 'test-client-id'
                && ($request->data()['client_secret'] ?? null) === 'test-client-secret';
        }

        if (str_ends_with($request->url(), '/ship/v1/shipments')) {
            $body = $request->data();

            return $request->hasHeader('Authorization', 'Bearer fedex-test-token')
                && ($body['accountNumber']['value'] ?? null) === '123456789'
                && ($body['requestedShipment']['serviceType'] ?? null) === 'INTERNATIONAL_PRIORITY'
                && ($body['requestedShipment']['recipients'][0]['contact']['phoneNumber'] ?? null) === '01000000000'
                && ($body['requestedShipment']['requestedPackageLineItems'][0]['weight']['units'] ?? null) === 'KG';
        }

        return false;
    });
});

it('tracks via track/v1/trackingnumbers', function (): void {
    Http::fake([
        'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
            'access_token' => 'fedex-test-token',
            'expires_in' => 3600,
        ], 200),
        'https://apis-sandbox.fedex.com/track/v1/trackingnumbers' => Http::response([
            'output' => [
                'completeTrackResults' => [[
                    'trackResults' => [[
                        'trackingNumberInfo' => ['trackingNumber' => '794612345678'],
                        'latestStatusDetail' => [
                            'code' => 'IT',
                            'description' => 'In transit',
                        ],
                        'scanEvents' => [[
                            'date' => '2026-07-16T10:00:00+00:00',
                            'eventDescription' => 'Departed facility',
                            'derivedStatusCode' => 'IT',
                            'scanLocation' => ['city' => 'Cairo'],
                        ]],
                    ]],
                ]],
            ],
        ], 200),
    ]);

    $tracking = ShipBridge::driver('fedex')->track('794612345678');

    expect($tracking->status)->toBe(ShipmentStatus::InTransit)
        ->and($tracking->events)->toHaveCount(1)
        ->and($tracking->trackingNumber)->toBe('794612345678');
});

it('returns FedEx public tracking URL as label', function (): void {
    $label = ShipBridge::driver('fedex')->label('794612345678');

    expect($label->url)->toBe('https://www.fedex.com/fedextrack/?trknbr=794612345678')
        ->and($label->contents)->toBe('');
});

it('requires recipient phone and account number', function (): void {
    ShipBridge::driver('fedex')->createShipment(new CreateShipmentRequest(
        origin: new Address('Warehouse', '1 Industrial Rd', 'Cairo', 'EG', phone: '01011111111'),
        destination: new Address('Customer', '12 Nile St', 'Giza', 'EG'),
        parcels: [new Parcel(weightKg: 1.0)],
    ));
})->throws(ShipBridgeException::class);

it('uses pre-issued token when configured', function (): void {
    config()->set('shipbridge.drivers.fedex.token', 'static-token');

    Http::fake([
        'https://apis-sandbox.fedex.com/ship/v1/shipments' => Http::response([
            'output' => [
                'transactionShipments' => [[
                    'masterTrackingNumber' => '794600000001',
                    'pieceResponses' => [['trackingNumber' => '794600000001']],
                ]],
            ],
        ], 200),
    ]);

    ShipBridge::driver('fedex')->createShipment(new CreateShipmentRequest(
        origin: new Address('Warehouse', '1 Industrial Rd', 'Cairo', 'EG', phone: '01011111111'),
        destination: new Address('Customer', '12 Nile St', 'Giza', 'EG', phone: '01000000000'),
        parcels: [new Parcel(weightKg: 1.0)],
    ));

    Http::assertSent(function ($request): bool {
        return str_ends_with($request->url(), '/ship/v1/shipments')
            && $request->hasHeader('Authorization', 'Bearer static-token');
    });

    Http::assertNotSent(function ($request): bool {
        return str_ends_with($request->url(), '/oauth/token');
    });
});
