<?php

declare(strict_types=1);

namespace Hekal\ShipBridge\Fedex;

use Hekal\ShipBridge\Exceptions\ShipBridgeException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * FedEx REST API client.
 *
 * Live: https://apis.fedex.com
 * Sandbox: https://apis-sandbox.fedex.com
 */
final class FedexClient
{
    private ?string $accessToken = null;

    private ?int $accessTokenExpiresAt = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createShipment(array $payload): array
    {
        return $this->postJson('/ship/v1/shipments', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function track(string $trackingNumber): array
    {
        return $this->postJson('/track/v1/trackingnumbers', [
            'includeDetailedScans' => true,
            'trackingInfo' => [
                [
                    'trackingNumberInfo' => [
                        'trackingNumber' => $trackingNumber,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postJson(string $path, array $payload): array
    {
        $response = $this->authorizedRequest()
            ->asJson()
            ->post($path, $payload);

        return $this->decode($response);
    }

    private function authorizedRequest(): PendingRequest
    {
        return $this->request()->withToken($this->token());
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl(rtrim((string) ($this->config['base_url'] ?? 'https://apis.fedex.com'), '/'))
            ->timeout((int) ($this->config['timeout'] ?? 60))
            ->acceptJson()
            ->withHeaders([
                'X-ShipBridge-Carrier' => 'fedex',
            ]);
    }

    private function token(): string
    {
        $configured = $this->config['token'] ?? null;
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if ($this->accessToken !== null && $this->accessTokenExpiresAt !== null && $this->accessTokenExpiresAt > time()) {
            return $this->accessToken;
        }

        $clientId = $this->clientId();
        $clientSecret = $this->clientSecret();

        $response = $this->request()
            ->asForm()
            ->post('/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        if (! $response->successful()) {
            $this->throwFromResponse($response, $json, 'FedEx OAuth token request failed.');
        }

        $accessToken = (string) ($json['access_token'] ?? '');
        if ($accessToken === '') {
            throw ShipBridgeException::carrierFailed('FedEx OAuth returned no access_token.', $response->status());
        }

        $expiresIn = (int) ($json['expires_in'] ?? 3600);
        $this->accessToken = $accessToken;
        $this->accessTokenExpiresAt = time() + max(60, $expiresIn - 30);

        return $accessToken;
    }

    private function clientId(): string
    {
        $value = $this->config['client_id'] ?? null;
        if (! is_string($value) || $value === '') {
            throw ShipBridgeException::carrierFailed('FedEx requires FEDEX_CLIENT_ID.');
        }

        return $value;
    }

    private function clientSecret(): string
    {
        $value = $this->config['client_secret'] ?? null;
        if (! is_string($value) || $value === '') {
            throw ShipBridgeException::carrierFailed('FedEx requires FEDEX_CLIENT_SECRET.');
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        if ($response->successful()) {
            return $json;
        }

        $this->throwFromResponse($response, $json, 'FedEx API request failed.');
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function throwFromResponse(Response $response, array $json, string $fallback): never
    {
        $message = $this->errorMessage($json, $response->body());

        throw ShipBridgeException::carrierFailed(
            $message !== '' ? $message : $fallback,
            $response->status(),
        );
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function errorMessage(array $json, string $body): string
    {
        $parts = [];

        if (isset($json['errors']) && is_array($json['errors'])) {
            foreach ($json['errors'] as $error) {
                if (! is_array($error)) {
                    continue;
                }
                $code = isset($error['code']) ? (string) $error['code'] : '';
                $msg = isset($error['message']) ? (string) $error['message'] : '';
                $parts[] = trim($code.($msg !== '' ? ': '.$msg : ''));
            }
        }

        if ($parts !== []) {
            return implode('; ', $parts);
        }

        foreach (['message', 'error_description', 'error'] as $key) {
            if (isset($json[$key]) && is_scalar($json[$key]) && (string) $json[$key] !== '') {
                return (string) $json[$key];
            }
        }

        return $body;
    }
}
