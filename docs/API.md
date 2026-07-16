# FedEx REST API reference

Contract aligned with FedEx Ship + Track REST APIs.

## Hosts

| Env | Base |
|---|---|
| Live | `https://apis.fedex.com` |
| Sandbox | `https://apis-sandbox.fedex.com` |

## Auth

`POST /oauth/token` (`application/x-www-form-urlencoded`):

```
grant_type=client_credentials
client_id=<api key>
client_secret=<secret key>
```

Response:

```json
{
  "access_token": "...",
  "token_type": "bearer",
  "expires_in": 3599
}
```

Set `FEDEX_TOKEN` to skip OAuth when you already have a bearer token.

## Endpoints used

| Action | Method | Path |
|---|---|---|
| OAuth | POST | `/oauth/token` |
| Create | POST | `/ship/v1/shipments` |
| Track | POST | `/track/v1/trackingnumbers` |

## Create shipment

Request body (simplified):

```json
{
  "labelResponseOptions": "LABEL",
  "requestedShipment": {
    "shipper": { "contact": {...}, "address": {...} },
    "recipients": [{ "contact": {...}, "address": {...} }],
    "shipDatestamp": "2026-07-16",
    "serviceType": "INTERNATIONAL_PRIORITY",
    "packagingType": "YOUR_PACKAGING",
    "pickupType": "DROPOFF_AT_FEDEX_LOCATION",
    "shippingChargesPayment": { "paymentType": "SENDER" },
    "labelSpecification": {
      "imageType": "PDF",
      "labelStockType": "PAPER_85X11_TOP_HALF_LABEL"
    },
    "requestedPackageLineItems": [
      { "weight": { "units": "KG", "value": 1.2 } }
    ]
  },
  "accountNumber": { "value": "123456789" }
}
```

Success (`output.transactionShipments[0]`):

- `masterTrackingNumber` → `ShipmentResult::trackingNumber`
- `pieceResponses[].packageDocuments[].encodedLabel` → base64 PDF in `ShipmentResult::raw`

Non-zero HTTP status or `errors[]` → `ShipBridgeException`.

## Track

```json
{
  "includeDetailedScans": true,
  "trackingInfo": [
    {
      "trackingNumberInfo": {
        "trackingNumber": "794612345678"
      }
    }
  ]
}
```

Events are read from `output.completeTrackResults[0].trackResults[0].scanEvents[]`.
Latest status from `latestStatusDetail.code`.

## Labels

FedEx returns PDF labels at **create** time only. There is no separate label download by tracking number in this driver.

- `ShipmentResult::labelUrl` is `null`
- PDF base64 path: `raw.output.transactionShipments.0.pieceResponses.0.packageDocuments.0.encodedLabel`
- `label($trackingNumber)` returns the public FedEx tracking URL (no PDF contents)

## COD (optional)

Pass `metadata.cod` and optional `metadata.currency`. The driver adds:

```json
"shipmentSpecialServices": {
  "specialServiceTypes": ["COD"],
  "codCollectionDetail": { ... }
}
```

Or pass a raw `metadata.shipment_special_services` block.

## Config keys

| Key | Env | Default |
|---|---|---|
| `base_url` | `FEDEX_BASE_URL` | `https://apis.fedex.com` |
| `client_id` | `FEDEX_CLIENT_ID` | — |
| `client_secret` | `FEDEX_CLIENT_SECRET` | — |
| `account_number` | `FEDEX_ACCOUNT_NUMBER` | — |
| `token` | `FEDEX_TOKEN` | optional |
| `service_type` | `FEDEX_SERVICE_TYPE` | `INTERNATIONAL_PRIORITY` |
| `packaging_type` | `FEDEX_PACKAGING_TYPE` | `YOUR_PACKAGING` |
| `pickup_type` | `FEDEX_PICKUP_TYPE` | `DROPOFF_AT_FEDEX_LOCATION` |
| `payment_type` | `FEDEX_PAYMENT_TYPE` | `SENDER` |
| `label_image_type` | `FEDEX_LABEL_IMAGE_TYPE` | `PDF` |
| `label_stock_type` | `FEDEX_LABEL_STOCK_TYPE` | `PAPER_85X11_TOP_HALF_LABEL` |
| `weight_units` | `FEDEX_WEIGHT_UNITS` | `KG` |
