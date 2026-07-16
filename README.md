# ShipBridge · FedEx


[![CI](https://github.com/mohamedhekal/shipbridge-fedex/actions/workflows/tests.yml/badge.svg)](https://github.com/mohamedhekal/shipbridge-fedex/actions)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Packagist](https://img.shields.io/packagist/v/mohamedhekal/shipbridge-fedex.svg)](https://packagist.org/packages/mohamedhekal/shipbridge-fedex)

**FedEx** shipping driver for [ShipBridge](https://github.com/mohamedhekal/shipbridge) · Region: **Global** / **عالمي**

---

## بالعربي — في ٣ خطوات

### ١) ثبّت الحزمتين
```bash
composer require mohamedhekal/shipbridge mohamedhekal/shipbridge-fedex
```

### ٢) حط مفاتيح FedEx في `.env`
```env
SHIPBRIDGE_DRIVER=fedex
FEDEX_API_KEY=your-key-here
FEDEX_BASE_URL=https://apis.fedex.com
```
> لو الشركة بتستخدم username/password أو OAuth، شوف ملف `config/fedex.php`.

### ٣) ابعت شحنة
```php
use Hekal\ShipBridge\Facades\ShipBridge;
use Hekal\ShipBridge\DTOs\Address;
use Hekal\ShipBridge\DTOs\CreateShipmentRequest;
use Hekal\ShipBridge\DTOs\Parcel;

$shipment = ShipBridge::driver('fedex')->createShipment(new CreateShipmentRequest(
    origin: new Address('المخزن', 'شارع ١', 'القاهرة', 'EG'),
    destination: new Address('العميل', 'شارع النيل', 'الجيزة', 'EG', phone: '01000000000'),
    parcels: [new Parcel(weightKg: 1.2)],
    reference: 'ORD-42',
));

echo $shipment->trackingNumber;
```

تتبع / ليبل / مرتجع:
```php
ShipBridge::driver('fedex')->track($shipment->trackingNumber);
ShipBridge::driver('fedex')->label($shipment->id);
```

---

## English — Quick start

```bash
composer require mohamedhekal/shipbridge mohamedhekal/shipbridge-fedex
```

```env
SHIPBRIDGE_DRIVER=fedex
FEDEX_API_KEY=your-key-here
```

```php
ShipBridge::driver('fedex')->createShipment(...);
ShipBridge::driver('fedex')->track('TRACKING');
ShipBridge::driver('fedex')->label('SHIPMENT_ID');
```

## How it fits

```
Your Laravel app
      │
      ▼
 ShipBridge  (one API for all carriers)
      │
      ▼
 shipbridge-fedex  ← this package (FedEx)
```

## Testing

```bash
composer install && composer test
```

## License

MIT © Mohamed Hekal
