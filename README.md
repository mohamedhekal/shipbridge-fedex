# ShipBridge · FedEx

[![CI](https://github.com/mohamedhekal/shipbridge-fedex/actions/workflows/tests.yml/badge.svg)](https://github.com/mohamedhekal/shipbridge-fedex/actions)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Packagist](https://img.shields.io/packagist/v/mohamedhekal/shipbridge-fedex.svg)](https://packagist.org/packages/mohamedhekal/shipbridge-fedex)

**FedEx** shipping driver for [ShipBridge](https://github.com/mohamedhekal/shipbridge) · Region: **Global** / **عالمي**

Real FedEx REST API: `https://apis.fedex.com` (sandbox: `https://apis-sandbox.fedex.com`)

---

## بالعربي — في ٣ خطوات

### ١) ثبّت الحزمتين
```bash
composer require mohamedhekal/shipbridge mohamedhekal/shipbridge-fedex
```

### ٢) حط مفاتيح FedEx في `.env`
```env
SHIPBRIDGE_DRIVER=fedex
FEDEX_BASE_URL=https://apis-sandbox.fedex.com
FEDEX_CLIENT_ID=your-api-key
FEDEX_CLIENT_SECRET=your-secret-key
FEDEX_ACCOUNT_NUMBER=123456789
```
> التفاصيل في `config/fedex.php` و [`docs/GUIDE_AR.md`](docs/GUIDE_AR.md).

### ٣) ابعت شحنة
```php
use Hekal\ShipBridge\Facades\ShipBridge;
use Hekal\ShipBridge\DTOs\Address;
use Hekal\ShipBridge\DTOs\CreateShipmentRequest;
use Hekal\ShipBridge\DTOs\Parcel;

$shipment = ShipBridge::driver('fedex')->createShipment(new CreateShipmentRequest(
    origin: new Address('المخزن', 'شارع ١', 'القاهرة', 'EG', phone: '01011111111', postalCode: '11511'),
    destination: new Address('العميل', 'شارع النيل', 'الجيزة', 'EG', phone: '01000000000', postalCode: '12613'),
    parcels: [new Parcel(weightKg: 1.2)],
    reference: 'ORD-42',
));

echo $shipment->trackingNumber;

// PDF label (base64) is in create response raw — persist it yourself:
$pdf = $shipment->raw['output']['transactionShipments'][0]['pieceResponses'][0]['packageDocuments'][0]['encodedLabel'] ?? null;
```

تتبع / رابط تتبع:
```php
ShipBridge::driver('fedex')->track($shipment->trackingNumber);
ShipBridge::driver('fedex')->label($shipment->trackingNumber); // FedEx public tracking URL
```

---

## English — Quick start

```bash
composer require mohamedhekal/shipbridge mohamedhekal/shipbridge-fedex
```

```env
SHIPBRIDGE_DRIVER=fedex
FEDEX_BASE_URL=https://apis-sandbox.fedex.com
FEDEX_CLIENT_ID=...
FEDEX_CLIENT_SECRET=...
FEDEX_ACCOUNT_NUMBER=...
FEDEX_TOKEN=          # optional pre-issued bearer token
```

```php
ShipBridge::driver('fedex')->createShipment(...);  // POST /ship/v1/shipments
ShipBridge::driver('fedex')->track('7946...');    // POST /track/v1/trackingnumbers
ShipBridge::driver('fedex')->label('7946...');   // public tracking URL (PDF from create raw)
```

See [`docs/API.md`](docs/API.md) for OAuth, payload shape, COD, and label behavior.

## How it fits

```
Your Laravel app
      │
      ▼
 ShipBridge  (one API for all carriers)
      │
      ▼
 shipbridge-fedex  ← this package (FedEx REST)
```

## Testing

```bash
composer install && composer test
```

---
## License

MIT © Mohamed Hekal

---

<p align="center">
  <img src="docs/assets/banner.png" alt="ShipBridge · fedex" width="100%">
</p>
