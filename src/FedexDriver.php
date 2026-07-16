<?php

declare(strict_types=1);

namespace Hekal\ShipBridge\Fedex;

use Hekal\ShipBridge\Contracts\CarrierDriver;
use Hekal\ShipBridge\DTOs\CreateShipmentRequest;
use Hekal\ShipBridge\DTOs\ExchangeShipmentRequest;
use Hekal\ShipBridge\DTOs\LabelResult;
use Hekal\ShipBridge\DTOs\ReturnShipmentRequest;
use Hekal\ShipBridge\DTOs\ShipmentResult;
use Hekal\ShipBridge\DTOs\TrackingEvent;
use Hekal\ShipBridge\DTOs\TrackingResult;
use Hekal\ShipBridge\Enums\LabelFormat;
use Hekal\ShipBridge\Enums\ShipmentStatus;
use Hekal\ShipBridge\Exceptions\ShipBridgeException;
use Hekal\ShipBridge\Fedex\Support\PayloadFactory;
use Hekal\ShipBridge\Support\StatusNormalizer;

/**
 * FedEx REST Ship + Track driver.
 */
final class FedexDriver implements CarrierDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly FedexClient $client,
        private readonly PayloadFactory $payloads,
        private readonly StatusNormalizer $normalizer,
        private readonly array $config = [],
    ) {}

    public function createShipment(CreateShipmentRequest $request): ShipmentResult
    {
        $response = $this->client->createShipment($this->payloads->create($request));

        return $this->shipmentFromCreate($response, ShipmentStatus::Created);
    }

    public function track(string $trackingNumber): TrackingResult
    {
        $response = $this->client->track($trackingNumber);

        /** @var array<string, mixed> $output */
        $output = is_array($response['output'] ?? null) ? $response['output'] : [];

        /** @var array<string, mixed> $complete */
        $complete = is_array($output['completeTrackResults'][0] ?? null) ? $output['completeTrackResults'][0] : [];

        /** @var array<string, mixed> $trackResult */
        $trackResult = is_array($complete['trackResults'][0] ?? null) ? $complete['trackResults'][0] : [];

        $statusRaw = $this->extractTrackStatus($trackResult);
        $status = $this->normalizer->normalize($statusRaw);

        /** @var list<TrackingEvent> $events */
        $events = [];
        $scans = $trackResult['scanEvents'] ?? [];
        if (is_array($scans)) {
            foreach ($scans as $scan) {
                if (! is_array($scan)) {
                    continue;
                }

                $eventStatus = (string) ($scan['derivedStatusCode'] ?? $scan['eventType'] ?? $scan['exceptionCode'] ?? $statusRaw);
                $location = null;
                if (isset($scan['scanLocation']) && is_array($scan['scanLocation'])) {
                    $location = (string) ($scan['scanLocation']['city'] ?? $scan['scanLocation']['countryName'] ?? '');
                }

                $events[] = new TrackingEvent(
                    status: $this->normalizer->normalize($eventStatus),
                    description: (string) ($scan['eventDescription'] ?? $scan['exceptionDescription'] ?? $eventStatus),
                    occurredAt: isset($scan['date']) ? (string) $scan['date'] : null,
                    location: $location !== '' ? $location : null,
                );
            }
        }

        if ($events === [] && $statusRaw !== '') {
            $latest = $trackResult['latestStatusDetail'] ?? null;
            $location = null;
            if (is_array($latest) && isset($latest['scanLocation']) && is_array($latest['scanLocation'])) {
                $location = (string) ($latest['scanLocation']['city'] ?? '');
            }

            $events[] = new TrackingEvent(
                status: $status,
                description: is_array($latest) ? (string) ($latest['description'] ?? $statusRaw) : $statusRaw,
                location: $location !== '' ? $location : null,
            );
        }

        $resolvedTracking = (string) (
            $trackResult['trackingNumberInfo']['trackingNumber']
            ?? $complete['trackingNumber']
            ?? $trackingNumber
        );

        return new TrackingResult(
            trackingNumber: $resolvedTracking,
            status: $status,
            events: $events,
            raw: $response,
        );
    }

    public function label(string $shipmentId, LabelFormat $format = LabelFormat::Pdf): LabelResult
    {
        // FedEx embeds PDF labels in createShipment response (encodedLabel).
        // Provide the public tracking page for convenience.
        $template = (string) ($this->config['tracking_url_template'] ?? 'https://www.fedex.com/fedextrack/?trknbr={tracking}');
        $url = str_replace('{tracking}', rawurlencode($shipmentId), $template);

        return new LabelResult(
            shipmentId: $shipmentId,
            format: $format,
            contents: '',
            base64Encoded: false,
            url: $url,
        );
    }

    public function createReturn(ReturnShipmentRequest $request): ShipmentResult
    {
        $response = $this->client->createShipment($this->payloads->returnShipment($request));

        return $this->shipmentFromCreate($response, ShipmentStatus::Returned);
    }

    public function createExchange(ExchangeShipmentRequest $request): ShipmentResult
    {
        $response = $this->client->createShipment($this->payloads->exchange($request));

        return $this->shipmentFromCreate($response, ShipmentStatus::Exchanged);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function shipmentFromCreate(array $response, ShipmentStatus $fallbackStatus): ShipmentResult
    {
        /** @var array<string, mixed> $output */
        $output = is_array($response['output'] ?? null) ? $response['output'] : [];

        /** @var array<string, mixed> $shipment */
        $shipment = is_array($output['transactionShipments'][0] ?? null) ? $output['transactionShipments'][0] : [];

        $masterTracking = (string) ($shipment['masterTrackingNumber'] ?? '');

        /** @var array<string, mixed> $piece */
        $piece = is_array($shipment['pieceResponses'][0] ?? null) ? $shipment['pieceResponses'][0] : [];

        $pieceTracking = (string) ($piece['trackingNumber'] ?? '');

        $trackingNumber = $masterTracking !== '' ? $masterTracking : $pieceTracking;
        if ($trackingNumber === '') {
            throw ShipBridgeException::carrierFailed('FedEx createShipment returned no tracking number.');
        }

        return new ShipmentResult(
            id: $trackingNumber,
            trackingNumber: $trackingNumber,
            status: $fallbackStatus,
            carrier: 'fedex',
            labelUrl: null,
            raw: $response,
        );
    }

    /**
     * @param  array<string, mixed>  $trackResult
     */
    private function extractTrackStatus(array $trackResult): string
    {
        if (isset($trackResult['latestStatusDetail']) && is_array($trackResult['latestStatusDetail'])) {
            $latest = $trackResult['latestStatusDetail'];
            foreach (['code', 'derivedCode', 'statusByLocale', 'description'] as $key) {
                if (isset($latest[$key]) && is_scalar($latest[$key]) && (string) $latest[$key] !== '') {
                    return (string) $latest[$key];
                }
            }
        }

        return 'exception';
    }
}
