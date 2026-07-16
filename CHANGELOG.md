# Changelog

## v0.2.0 — 2026-07-16

- Real FedEx REST API integration (OAuth, Ship, Track)
- `FedexClient` with token caching and optional `FEDEX_TOKEN`
- `PayloadFactory` mapping ShipBridge DTOs to FedEx Ship JSON
- Labels embedded in create response; `label()` returns public tracking URL
- COD via `metadata.cod` or raw `shipment_special_services`
- Pest tests with `Http::fake` for OAuth, create, track, label
- Arabic guide (`docs/GUIDE_AR.md`) and API reference (`docs/API.md`)

## v0.1.0 — 2026-07-16

- Initial FedEx driver scaffold for ShipBridge
- Create / track / label / return / exchange stubs
- Status map for common FedEx codes
- Pest + Pint + PHPStan CI
