<?php

declare(strict_types=1);

namespace Hekal\ShipBridge\Fedex\Support;

use Hekal\ShipBridge\DTOs\Address;
use Hekal\ShipBridge\DTOs\CreateShipmentRequest;
use Hekal\ShipBridge\DTOs\ExchangeShipmentRequest;
use Hekal\ShipBridge\DTOs\Parcel;
use Hekal\ShipBridge\DTOs\ReturnShipmentRequest;
use Hekal\ShipBridge\Exceptions\ShipBridgeException;

/**
 * Maps ShipBridge DTOs → FedEx Ship API JSON.
 *
 * Metadata keys:
 * - service_type, packaging_type, pickup_type, payment_type
 * - label_image_type, label_stock_type, weight_units
 * - cod / currency, ship_datestamp
 * - shipment_special_services (raw FedEx block)
 */
final class PayloadFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function create(CreateShipmentRequest $request): array
    {
        return $this->shipment(
            shipper: $request->origin,
            recipient: $request->destination,
            parcels: $request->parcels,
            metadata: $request->metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function returnShipment(ReturnShipmentRequest $request): array
    {
        $pickup = $request->pickupFrom ?? $request->returnTo;
        $meta = array_merge($request->metadata, [
            'notes' => $request->reason ?? 'Return shipment',
        ]);

        return $this->shipment(
            shipper: $pickup,
            recipient: $request->returnTo,
            parcels: $request->parcels ?? [new Parcel(weightKg: 1.0)],
            metadata: $meta,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function exchange(ExchangeShipmentRequest $request): array
    {
        $meta = array_merge($request->metadata, [
            'notes' => $request->reason ?? 'Exchange shipment',
        ]);

        return $this->shipment(
            shipper: $request->origin,
            recipient: $request->destination,
            parcels: $request->outboundParcels,
            metadata: $meta,
        );
    }

    /**
     * @param  list<Parcel>  $parcels
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function shipment(
        Address $shipper,
        Address $recipient,
        array $parcels,
        array $metadata,
    ): array {
        $this->requireAccountNumber();
        $this->requirePhone($recipient, $metadata);

        $requestedShipment = [
            'shipper' => $this->party($shipper, $metadata, 'shipper'),
            'recipients' => [$this->party($recipient, $metadata, 'recipient')],
            'shipDatestamp' => (string) ($metadata['ship_datestamp'] ?? date('Y-m-d')),
            'serviceType' => (string) ($metadata['service_type'] ?? $this->config['service_type'] ?? 'INTERNATIONAL_PRIORITY'),
            'packagingType' => (string) ($metadata['packaging_type'] ?? $this->config['packaging_type'] ?? 'YOUR_PACKAGING'),
            'pickupType' => (string) ($metadata['pickup_type'] ?? $this->config['pickup_type'] ?? 'DROPOFF_AT_FEDEX_LOCATION'),
            'blockInsightVisibility' => (bool) ($metadata['block_insight_visibility'] ?? false),
            'shippingChargesPayment' => [
                'paymentType' => (string) ($metadata['payment_type'] ?? $this->config['payment_type'] ?? 'SENDER'),
            ],
            'labelSpecification' => [
                'imageType' => (string) ($metadata['label_image_type'] ?? $this->config['label_image_type'] ?? 'PDF'),
                'labelStockType' => (string) ($metadata['label_stock_type'] ?? $this->config['label_stock_type'] ?? 'PAPER_85X11_TOP_HALF_LABEL'),
            ],
            'requestedPackageLineItems' => $this->packageLineItems($parcels, $metadata),
        ];

        $specialServices = $this->specialServices($metadata);
        if ($specialServices !== null) {
            $requestedShipment['shipmentSpecialServices'] = $specialServices;
        }

        return [
            'labelResponseOptions' => (string) ($metadata['label_response_options'] ?? 'LABEL'),
            'requestedShipment' => $requestedShipment,
            'accountNumber' => [
                'value' => (string) ($this->config['account_number'] ?? ''),
            ],
        ];
    }

    private function requireAccountNumber(): void
    {
        $account = $this->config['account_number'] ?? null;
        if (! is_string($account) || $account === '') {
            throw ShipBridgeException::carrierFailed('FedEx requires FEDEX_ACCOUNT_NUMBER.');
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function requirePhone(Address $recipient, array $metadata): void
    {
        $phone = $recipient->phone ?? (isset($metadata['phone']) ? (string) $metadata['phone'] : null);
        if ($phone === null || $phone === '') {
            throw ShipBridgeException::carrierFailed('FedEx requires recipient phone (Address::$phone).');
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function party(Address $address, array $metadata, string $role): array
    {
        $prefix = $role.'_';

        $phone = $address->phone
            ?? (isset($metadata[$prefix.'phone']) ? (string) $metadata[$prefix.'phone'] : null)
            ?? (isset($metadata['phone']) && $role === 'recipient' ? (string) $metadata['phone'] : null)
            ?? '';

        $line2 = isset($metadata[$prefix.'street2']) ? (string) $metadata[$prefix.'street2'] : ($address->line2 ?? '');
        $streetLines = [];
        foreach ([
            (string) ($metadata[$prefix.'street'] ?? $address->line1),
            $line2,
        ] as $line) {
            if ($line !== '') {
                $streetLines[] = $line;
            }
        }

        $party = [
            'contact' => array_filter([
                'personName' => (string) ($metadata[$prefix.'name'] ?? $address->name),
                'companyName' => (string) ($metadata[$prefix.'company'] ?? $address->name),
                'phoneNumber' => $phone,
                'emailAddress' => $address->email ?? (isset($metadata[$prefix.'email']) ? (string) $metadata[$prefix.'email'] : null),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'address' => array_filter([
                'streetLines' => $streetLines !== [] ? $streetLines : [(string) ($metadata[$prefix.'street'] ?? $address->line1)],
                'city' => (string) ($metadata[$prefix.'city'] ?? $address->city),
                'stateOrProvinceCode' => isset($metadata[$prefix.'state']) ? (string) $metadata[$prefix.'state'] : $address->state,
                'postalCode' => isset($metadata[$prefix.'postal_code']) ? (string) $metadata[$prefix.'postal_code'] : $address->postalCode,
                'countryCode' => (string) ($metadata[$prefix.'country_code'] ?? $address->countryCode),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ];

        return $party;
    }

    /**
     * @param  list<Parcel>  $parcels
     * @param  array<string, mixed>  $metadata
     * @return list<array<string, mixed>>
     */
    private function packageLineItems(array $parcels, array $metadata): array
    {
        $units = (string) ($metadata['weight_units'] ?? $this->config['weight_units'] ?? 'KG');
        $rows = [];

        foreach ($parcels as $parcel) {
            $weight = $parcel->weightKg > 0 ? $parcel->weightKg : 1.0;
            $item = [
                'weight' => [
                    'units' => $units,
                    'value' => round($weight, 3),
                ],
            ];

            if ($parcel->lengthCm !== null && $parcel->widthCm !== null && $parcel->heightCm !== null) {
                $item['dimensions'] = [
                    'length' => (int) round($parcel->lengthCm),
                    'width' => (int) round($parcel->widthCm),
                    'height' => (int) round($parcel->heightCm),
                    'units' => (string) ($metadata['dimension_units'] ?? 'CM'),
                ];
            }

            $rows[] = $item;
        }

        return $rows !== [] ? $rows : [[
            'weight' => [
                'units' => $units,
                'value' => 1.0,
            ],
        ]];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>|null
     */
    private function specialServices(array $metadata): ?array
    {
        if (isset($metadata['shipment_special_services']) && is_array($metadata['shipment_special_services'])) {
            /** @var array<string, mixed> $services */
            $services = $metadata['shipment_special_services'];

            return $services;
        }

        $cod = (float) ($metadata['cod'] ?? $metadata['cod_amount'] ?? 0);
        if ($cod <= 0) {
            return null;
        }

        return [
            'specialServiceTypes' => ['COD'],
            'codCollectionDetail' => [
                'codCollectionType' => (string) ($metadata['cod_collection_type'] ?? 'ANY'),
                'codCollectionAmount' => [
                    'amount' => $cod,
                    'currency' => (string) ($metadata['currency'] ?? $this->config['currency'] ?? 'USD'),
                ],
            ],
        ];
    }
}
