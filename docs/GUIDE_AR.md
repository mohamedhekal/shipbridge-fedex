# دليل FedEx — شرح بسيط ومفصّل

## إيه هي الحزمة دي؟

`mohamedhekal/shipbridge-fedex` تربط Laravel بـ **FedEx REST API** عن طريق ShipBridge.

```
تطبيقك → ShipBridge → shipbridge-fedex → apis.fedex.com
```

---

## قبل ما تبدأ

1. افتح حساب FedEx Developer واحصل على:
   - `client_id` (API Key)
   - `client_secret` (Secret Key)
   - `account_number` (رقم حساب الشحن)
2. للاختبار استخدم Sandbox: `https://apis-sandbox.fedex.com`

---

## التثبيت

```bash
composer require mohamedhekal/shipbridge mohamedhekal/shipbridge-fedex
```

`.env`:

```env
SHIPBRIDGE_DRIVER=fedex
FEDEX_BASE_URL=https://apis-sandbox.fedex.com
FEDEX_CLIENT_ID=your-api-key
FEDEX_CLIENT_SECRET=your-secret-key
FEDEX_ACCOUNT_NUMBER=123456789
FEDEX_SERVICE_TYPE=INTERNATIONAL_PRIORITY
```

---

## ابعت شحنة

```php
use Hekal\ShipBridge\Facades\ShipBridge;
use Hekal\ShipBridge\DTOs\Address;
use Hekal\ShipBridge\DTOs\CreateShipmentRequest;
use Hekal\ShipBridge\DTOs\Parcel;

$shipment = ShipBridge::driver('fedex')->createShipment(new CreateShipmentRequest(
    origin: new Address('المخزن', 'شارع الصناعة ١', 'القاهرة', 'EG', phone: '01011111111', postalCode: '11511'),
    destination: new Address('محمد أحمد', '١٢ شارع النيل', 'الجيزة', 'EG', phone: '01000000000', postalCode: '12613'),
    parcels: [new Parcel(weightKg: 1.5, description: 'ملابس')],
    reference: 'ORD-1001',
));

$shipment->trackingNumber;
```

**مهم — البوليصة (PDF):** FedEx بترجع ملف PDF مشفر base64 **مع إنشاء الشحنة** مش في استدعاء منفصل.

```php
$encoded = $shipment->raw['output']['transactionShipments'][0]['pieceResponses'][0]['packageDocuments'][0]['encodedLabel'] ?? null;
if ($encoded) {
    file_put_contents('label.pdf', base64_decode($encoded));
}
```

---

## تتبع / رابط تتبع

```php
ShipBridge::driver('fedex')->track($shipment->trackingNumber);

// label() بترجع رابط صفحة التتبع العامة (مش PDF)
ShipBridge::driver('fedex')->label($shipment->trackingNumber);
// https://www.fedex.com/fedextrack/?trknbr=...
```

---

## COD (اختياري)

```php
metadata: [
    'cod' => 250,
    'currency' => 'EGP',
]
```

---

## Troubleshooting

| رسالة | الحل |
|---|---|
| requires FEDEX_CLIENT_ID | حط مفاتيح OAuth في `.env` |
| requires FEDEX_ACCOUNT_NUMBER | `FEDEX_ACCOUNT_NUMBER` |
| requires recipient phone | `Address::$phone` على المستلم |
| OAuth failed | راجع Sandbox vs Production URL |

---

## English summary

See [`API.md`](API.md) for full REST contract, endpoints, and label behavior.
