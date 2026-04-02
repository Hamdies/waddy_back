# Zone Configurations

<cite>
**Referenced Files in This Document**
- [Zone.php](file://app/Models/Zone.php)
- [ModuleZone.php](file://app/Models/ModuleZone.php)
- [ZoneService.php](file://app/Services/ZoneService.php)
- [ZoneRepository.php](file://app/Repositories/ZoneRepository.php)
- [ZoneRepositoryInterface.php](file://app/Contracts/Repositories/ZoneRepositoryInterface.php)
- [ZoneController.php](file://app/Http/Controllers/Admin/Zone/ZoneController.php)
- [ZoneController.php](file://app/Http/Controllers/Api/V1/ZoneController.php)
- [module-setup.blade.php](file://resources/views/admin-views/zone/module-setup.blade.php)
- [index.blade.php](file://resources/views/admin-views/zone/index.blade.php)
- [SurgePrice.php](file://app/Models/SurgePrice.php)
- [2025_07_13_185456_create_surge_prices_table.php](file://database/migrations/2025_07_13_185456_create_surge_prices_table.php)
- [2025_07_13_160717_add_charge_type_col_to_module_zone_table.php](file://database/migrations/2025_07_13_160717_add_charge_type_col_to_module_zone_table.php)
- [2023_10_08_103818_add_increased_delivery_fee_in_zones_table.php](file://database/migrations/2023_10_08_103818_add_increased_delivery_fee_in_zones_table.php)
- [2022_10_25_153214_add_payment_method_columns_to_zones_table.php](file://database/migrations/2022_10_25_153214_add_payment_method_columns_to_zones_table.php)
- [2022_10_31_165427_add_rename_delivery_charge_column_to_stores_table.php](file://database/migrations/2022_10_31_165427_add_rename_delivery_charge_column_to_stores_table.php)
- [2022_12_29_105321_add_maximum_cod_order_amount_column_to_module_zone_table.php](file://database/migrations/2022_12_29_105321_add_maximum_cod_order_amount_column_to_module_zone_table.php)
</cite>

## Table of Contents
1. [Introduction](#introduction)
2. [Project Structure](#project-structure)
3. [Core Components](#core-components)
4. [Architecture Overview](#architecture-overview)
5. [Detailed Component Analysis](#detailed-component-analysis)
6. [Dependency Analysis](#dependency-analysis)
7. [Performance Considerations](#performance-considerations)
8. [Troubleshooting Guide](#troubleshooting-guide)
9. [Conclusion](#conclusion)

## Introduction
This document explains the zone-specific configuration system and business rule enforcement for dynamic pricing, payment method controls, and surge pricing integration. It covers:
- Per-zone pricing structures: fixed and distance-based shipping charges, minimum and maximum caps
- COD limits per zone and module
- Payment method restrictions per zone
- Zone-specific messaging for increased delivery fees
- Surge pricing configuration and integration
- Workflows for configuration, validation, and dynamic pricing calculation

## Project Structure
The zone configuration system spans models, repositories, services, controllers, and admin views. Key areas:
- Models define spatial zones, pivot tables for module-zone linkage, and surge pricing
- Services handle coordinate parsing, validation, and formatting
- Repositories manage persistence and synchronization of zone-module relationships
- Controllers orchestrate admin workflows and API endpoints
- Views render configuration forms for delivery charges, payment methods, and surge pricing

```mermaid
graph TB
subgraph "Admin UI"
V1["module-setup.blade.php"]
V2["index.blade.php"]
end
subgraph "Controllers"
C1["Admin ZoneController"]
C2["API ZoneController"]
end
subgraph "Services"
S1["ZoneService"]
end
subgraph "Repositories"
R1["ZoneRepository"]
RI["ZoneRepositoryInterface"]
end
subgraph "Models"
M1["Zone"]
M2["ModuleZone (pivot)"]
M3["SurgePrice"]
end
subgraph "Migrations"
G1["add_charge_type_col_to_module_zone"]
G2["add_increased_delivery_fee_in_zones"]
G3["add_payment_method_columns_to_zones"]
G4["add_maximum_cod_order_amount_column_to_module_zone"]
G5["create_surge_prices"]
end
V1 --> C1
V2 --> C1
C1 --> S1
C1 --> R1
C2 --> R1
R1 --> M1
R1 --> M2
R1 --> M3
S1 --> M1
S1 --> M2
M1 --> M2
M1 --> M3
G1 --> M2
G2 --> M1
G3 --> M1
G4 --> M2
G5 --> M3
```

**Diagram sources**
- [module-setup.blade.php:1-403](file://resources/views/admin-views/zone/module-setup.blade.php#L1-L403)
- [index.blade.php:1-585](file://resources/views/admin-views/zone/index.blade.php#L1-L585)
- [ZoneController.php](file://app/Http/Controllers/Admin/Zone/ZoneController.php)
- [ZoneController.php](file://app/Http/Controllers/Api/V1/ZoneController.php)
- [ZoneService.php:1-126](file://app/Services/ZoneService.php#L1-L126)
- [ZoneRepository.php:1-129](file://app/Repositories/ZoneRepository.php#L1-L129)
- [ZoneRepositoryInterface.php:1-63](file://app/Contracts/Repositories/ZoneRepositoryInterface.php#L1-L63)
- [Zone.php:1-160](file://app/Models/Zone.php#L1-L160)
- [ModuleZone.php:1-24](file://app/Models/ModuleZone.php#L1-L24)
- [SurgePrice.php:1-73](file://app/Models/SurgePrice.php#L1-L73)
- [2025_07_13_160717_add_charge_type_col_to_module_zone_table.php:1-31](file://database/migrations/2025_07_13_160717_add_charge_type_col_to_module_zone_table.php#L1-L31)
- [2023_10_08_103818_add_increased_delivery_fee_in_zones_table.php:1-35](file://database/migrations/2023_10_08_103818_add_increased_delivery_fee_in_zones_table.php#L1-L35)
- [2022_10_25_153214_add_payment_method_columns_to_zones_table.php:1-35](file://database/migrations/2022_10_25_153214_add_payment_method_columns_to_zones_table.php#L1-L35)
- [2022_12_29_105321_add_maximum_cod_order_amount_column_to_module_zone_table.php:1-32](file://database/migrations/2022_12_29_105321_add_maximum_cod_order_amount_column_to_module_zone_table.php#L1-L32)
- [2025_07_13_185456_create_surge_prices_table.php:1-45](file://database/migrations/2025_07_13_185456_create_surge_prices_table.php#L1-L45)

**Section sources**
- [Zone.php:1-160](file://app/Models/Zone.php#L1-L160)
- [ModuleZone.php:1-24](file://app/Models/ModuleZone.php#L1-L24)
- [ZoneService.php:1-126](file://app/Services/ZoneService.php#L1-L126)
- [ZoneRepository.php:1-129](file://app/Repositories/ZoneRepository.php#L1-L129)
- [ZoneRepositoryInterface.php:1-63](file://app/Contracts/Repositories/ZoneRepositoryInterface.php#L1-L63)
- [module-setup.blade.php:1-403](file://resources/views/admin-views/zone/module-setup.blade.php#L1-L403)
- [index.blade.php:1-585](file://resources/views/admin-views/zone/index.blade.php#L1-L585)
- [SurgePrice.php:1-73](file://app/Models/SurgePrice.php#L1-L73)
- [2025_07_13_160717_add_charge_type_col_to_module_zone_table.php:1-31](file://database/migrations/2025_07_13_160717_add_charge_type_col_to_module_zone_table.php#L1-L31)
- [2023_10_08_103818_add_increased_delivery_fee_in_zones_table.php:1-35](file://database/migrations/2023_10_08_103818_add_increased_delivery_fee_in_zones_table.php#L1-L35)
- [2022_10_25_153214_add_payment_method_columns_to_zones_table.php:1-35](file://database/migrations/2022_10_25_153214_add_payment_method_columns_to_zones_table.php#L1-L35)
- [2022_12_29_105321_add_maximum_cod_order_amount_column_to_module_zone_table.php:1-32](file://database/migrations/2022_12_29_105321_add_maximum_cod_order_amount_column_to_module_zone_table.php#L1-L32)
- [2025_07_13_185456_create_surge_prices_table.php:1-45](file://database/migrations/2025_07_13_185456_create_surge_prices_table.php#L1-L45)

## Core Components
- Zone model: spatial polygon-based zones with payment method flags, increased delivery fee settings, and relationships to stores, delivery men, orders, campaigns, and surge prices.
- ModuleZone pivot: per-zone, per-module pricing and limits (fixed or distance-based).
- ZoneService: parses coordinates, formats spatial data, validates delivery charge setups, and prepares zone-module setup data.
- ZoneRepository: persists zones, synchronizes module assignments, and retrieves zones with counts and coordinates.
- Admin views: configure payment methods, delivery charges, COD limits, and surge pricing per zone.

**Section sources**
- [Zone.php:37-160](file://app/Models/Zone.php#L37-L160)
- [ModuleZone.php:10-24](file://app/Models/ModuleZone.php#L10-L24)
- [ZoneService.php:9-126](file://app/Services/ZoneService.php#L9-L126)
- [ZoneRepository.php:12-129](file://app/Repositories/ZoneRepository.php#L12-L129)
- [module-setup.blade.php:23-340](file://resources/views/admin-views/zone/module-setup.blade.php#L23-L340)

## Architecture Overview
The system integrates admin configuration with runtime enforcement:
- Admin UI posts zone and module settings to controllers
- Controllers delegate to services for validation/formatting and repositories for persistence
- Models define relationships and casts for spatial and numeric fields
- Surge pricing is linked to zones and modules for dynamic adjustments

```mermaid
sequenceDiagram
participant Admin as "Admin UI"
participant Ctrl as "ZoneController"
participant Svc as "ZoneService"
participant Repo as "ZoneRepository"
participant DB as "DB : zones, module_zone, surge_prices"
Admin->>Ctrl : Submit zone/module setup
Ctrl->>Svc : Validate and format delivery charge data
Svc-->>Ctrl : Validation result
Ctrl->>Repo : Persist zone + sync module assignments
Repo->>DB : INSERT/UPDATE zones, module_zone
Ctrl-->>Admin : Success response
```

**Diagram sources**
- [module-setup.blade.php:23-348](file://resources/views/admin-views/zone/module-setup.blade.php#L23-L348)
- [ZoneController.php](file://app/Http/Controllers/Admin/Zone/ZoneController.php)
- [ZoneService.php:94-123](file://app/Services/ZoneService.php#L94-L123)
- [ZoneRepository.php:108-117](file://app/Repositories/ZoneRepository.php#L108-L117)
- [2025_07_13_160717_add_charge_type_col_to_module_zone_table.php:14-17](file://database/migrations/2025_07_13_160717_add_charge_type_col_to_module_zone_table.php#L14-L17)

## Detailed Component Analysis

### Zone Model and Spatial Configuration
- Stores spatial polygon coordinates and translation-aware display names
- Flags for payment methods (cash on delivery, digital payment, offline payment)
- Increased delivery fee fields for messaging and enforcement
- Relationships to stores, deliverymen, orders, campaigns, and surge prices

```mermaid
classDiagram
class Zone {
+int id
+string name
+string display_name
+Polygon coordinates
+int status
+bool cash_on_delivery
+bool digital_payment
+bool offline_payment
+float increased_delivery_fee
+int increased_delivery_fee_status
+string increase_delivery_charge_message
+stores()
+deliverymen()
+orders()
+campaigns()
+surge_prices()
}
class ModuleZone {
+int id
+int module_id
+int zone_id
+float per_km_shipping_charge
+float minimum_shipping_charge
+float maximum_shipping_charge
+float maximum_cod_order_amount
+string delivery_charge_type
+float fixed_shipping_charge
}
class SurgePrice {
+int id
+string surge_price_name
+string customer_note
+int customer_note_status
+array module_ids
+int zone_id
+decimal price
+string price_type
+bool status
+bool is_permanent
+string duration_type
+array weekly_days
+array custom_days
+array custom_times
+date start_date
+date end_date
+time start_time
+time end_time
+zone()
+details()
}
Zone "1" -- "many" ModuleZone : "belongsToMany via pivot"
Zone "1" -- "many" SurgePrice : "has many"
```

**Diagram sources**
- [Zone.php:37-160](file://app/Models/Zone.php#L37-L160)
- [ModuleZone.php:10-24](file://app/Models/ModuleZone.php#L10-L24)
- [SurgePrice.php:9-73](file://app/Models/SurgePrice.php#L9-L73)

**Section sources**
- [Zone.php:37-160](file://app/Models/Zone.php#L37-L160)
- [SurgePrice.php:9-73](file://app/Models/SurgePrice.php#L9-L73)

### Per-Zone Pricing Structures
- Fixed amount: requires a positive fixed_shipping_charge when delivery_charge_type equals fixed
- Distance-based: requires per_km_shipping_charge and minimum_shipping_charge; optional maximum_shipping_charge
- Validation ensures maximum_shipping_charge is not less than minimum_shipping_charge when both are present

```mermaid
flowchart TD
Start(["Validate Module Delivery Charge"]) --> CheckType["Check delivery_charge_type"]
CheckType --> |fixed| FixedPath["Require fixed_shipping_charge"]
CheckType --> |distance| DistPath["Require per_km_shipping_charge<br/>and minimum_shipping_charge"]
FixedPath --> FixedOK{"fixed_shipping_charge present?"}
FixedOK --> |No| ErrFixed["Return flag: fixed_required"]
FixedOK --> |Yes| DistPath
DistPath --> MinMaxCheck["If maximum_shipping_charge exists,<br/>ensure >= minimum_shipping_charge"]
MinMaxCheck --> MinMaxOK{"Valid?"}
MinMaxOK --> |No| ErrMax["Return flag: max_delivery_charge"]
MinMaxOK --> |Yes| Done(["Success"])
CheckType --> |other| ErrUnknown["Return flag: unknown_type"]
```

**Diagram sources**
- [ZoneService.php:94-123](file://app/Services/ZoneService.php#L94-L123)

**Section sources**
- [ZoneService.php:94-123](file://app/Services/ZoneService.php#L94-L123)
- [2025_07_13_160717_add_charge_type_col_to_module_zone_table.php:14-17](file://database/migrations/2025_07_13_160717_add_charge_type_col_to_module_zone_table.php#L14-L17)

### COD Limits and Payment Method Restrictions
- Maximum COD order amount per module-zone: stored in module_zone table
- Payment method flags per zone: cash_on_delivery, digital_payment, offline_payment
- Admin UI enforces at least one payment method selection and validates COD limits per module

```mermaid
sequenceDiagram
participant Admin as "Admin UI"
participant Ctrl as "ZoneController"
participant Repo as "ZoneRepository"
participant DB as "DB : zones, module_zone"
Admin->>Ctrl : Select payment methods + set COD limits
Ctrl->>Repo : zoneModuleSetupUpdate(zone_id, zone_data, module_data)
Repo->>DB : UPDATE zones + sync module_zone
DB-->>Repo : OK
Repo-->>Ctrl : Updated zone
Ctrl-->>Admin : Success
```

**Diagram sources**
- [module-setup.blade.php:324-332](file://resources/views/admin-views/zone/module-setup.blade.php#L324-L332)
- [ZoneRepository.php:108-117](file://app/Repositories/ZoneRepository.php#L108-L117)
- [2022_12_29_105321_add_maximum_cod_order_amount_column_to_module_zone_table.php:16-18](file://database/migrations/2022_12_29_105321_add_maximum_cod_order_amount_column_to_module_zone_table.php#L16-L18)
- [2022_10_25_153214_add_payment_method_columns_to_zones_table.php:16-19](file://database/migrations/2022_10_25_153214_add_payment_method_columns_to_zones_table.php#L16-L19)

**Section sources**
- [module-setup.blade.php:32-88](file://resources/views/admin-views/zone/module-setup.blade.php#L32-L88)
- [ZoneRepository.php:108-117](file://app/Repositories/ZoneRepository.php#L108-L117)
- [2022_12_29_105321_add_maximum_cod_order_amount_column_to_module_zone_table.php:1-32](file://database/migrations/2022_12_29_105321_add_maximum_cod_order_amount_column_to_module_zone_table.php#L1-L32)
- [2022_10_25_153214_add_payment_method_columns_to_zones_table.php:1-35](file://database/migrations/2022_10_25_153214_add_payment_method_columns_to_zones_table.php#L1-L35)

### Zone-Specific Messaging for Increased Delivery Fees
- Zones support increased_delivery_fee, increased_delivery_fee_status, and increase_delivery_charge_message
- These fields enable targeted messaging to users when delivery fees rise in specific zones

**Section sources**
- [Zone.php:29-36](file://app/Models/Zone.php#L29-L36)
- [2023_10_08_103818_add_increased_delivery_fee_in_zones_table.php:14-19](file://database/migrations/2023_10_08_103818_add_increased_delivery_fee_in_zones_table.php#L14-L19)

### Surge Pricing Integration
- Surge prices are zone-bound with flexible duration types (daily, weekly, custom) and price types (amount or percent)
- Surge pricing can target specific modules and optionally include customer-facing notes

```mermaid
erDiagram
ZONE {
int id PK
string name
string display_name
polygon coordinates
bool cash_on_delivery
bool digital_payment
bool offline_payment
float increased_delivery_fee
int increased_delivery_fee_status
string increase_delivery_charge_message
}
SURGE_PRICE {
int id PK
string surge_price_name
text customer_note
int customer_note_status
json module_ids
int zone_id FK
decimal price
enum price_type
bool status
bool is_permanent
enum duration_type
json weekly_days
json custom_days
json custom_times
date start_date
date end_date
time start_time
time end_time
}
ZONE ||--o{ SURGE_PRICE : "has many"
```

**Diagram sources**
- [SurgePrice.php:9-73](file://app/Models/SurgePrice.php#L9-L73)
- [2025_07_13_185456_create_surge_prices_table.php:14-34](file://database/migrations/2025_07_13_185456_create_surge_prices_table.php#L14-L34)

**Section sources**
- [SurgePrice.php:9-73](file://app/Models/SurgePrice.php#L9-L73)
- [2025_07_13_185456_create_surge_prices_table.php:1-45](file://database/migrations/2025_07_13_185456_create_surge_prices_table.php#L1-L45)

### Configuration Workflows
- Zone creation: Admin UI collects translated names, coordinates, and initial flags; service converts coordinates to spatial polygons
- Module setup: Admin selects modules and sets delivery charge type, fixed amount, per-km, minimum, maximum, and COD limits
- Persistence: Repository persists zone and synchronizes module assignments with per-zone parameters

```mermaid
sequenceDiagram
participant Admin as "Admin UI"
participant Ctrl as "ZoneController"
participant Svc as "ZoneService"
participant Repo as "ZoneRepository"
participant DB as "DB : zones, module_zone"
Admin->>Ctrl : Create Zone
Ctrl->>Svc : getAddData(request, zoneId)
Svc-->>Ctrl : Spatial zone data
Ctrl->>Repo : add(zoneData)
Repo->>DB : INSERT zones
Ctrl-->>Admin : Zone created
Admin->>Ctrl : Connect Modules + Set Charges
Ctrl->>Repo : zoneModuleSetupUpdate(zone_id, zone_data, module_data)
Repo->>DB : UPDATE zones + sync module_zone
Ctrl-->>Admin : Saved
```

**Diagram sources**
- [index.blade.php:533-575](file://resources/views/admin-views/zone/index.blade.php#L533-L575)
- [module-setup.blade.php:23-348](file://resources/views/admin-views/zone/module-setup.blade.php#L23-L348)
- [ZoneService.php:12-37](file://app/Services/ZoneService.php#L12-L37)
- [ZoneRepository.php:18-26](file://app/Repositories/ZoneRepository.php#L18-L26)
- [ZoneRepository.php:108-117](file://app/Repositories/ZoneRepository.php#L108-L117)

**Section sources**
- [index.blade.php:533-575](file://resources/views/admin-views/zone/index.blade.php#L533-L575)
- [module-setup.blade.php:23-348](file://resources/views/admin-views/zone/module-setup.blade.php#L23-L348)
- [ZoneService.php:12-37](file://app/Services/ZoneService.php#L12-L37)
- [ZoneRepository.php:18-26](file://app/Repositories/ZoneRepository.php#L18-L26)
- [ZoneRepository.php:108-117](file://app/Repositories/ZoneRepository.php#L108-L117)

## Dependency Analysis
- Zone depends on spatial traits and relations to stores, deliverymen, orders, campaigns, and surge prices
- ModuleZone is a pivot with strong casting for numeric pricing fields
- ZoneService depends on spatial objects for coordinate parsing
- ZoneRepository depends on Zone model and performs synchronization with module assignments
- Admin views depend on controller actions and model relationships for rendering and validation

```mermaid
graph LR
VS["ZoneService"] --> Z["Zone"]
VS --> MZ["ModuleZone"]
ZR["ZoneRepository"] --> Z
ZR --> MZ
Z --> MZ
Z --> SP["SurgePrice"]
V1["module-setup.blade.php"] --> ZR
V2["index.blade.php"] --> ZR
```

**Diagram sources**
- [ZoneService.php:5-8](file://app/Services/ZoneService.php#L5-L8)
- [ZoneRepository.php:6-12](file://app/Repositories/ZoneRepository.php#L6-L12)
- [Zone.php:104-153](file://app/Models/Zone.php#L104-L153)
- [ModuleZone.php:10-24](file://app/Models/ModuleZone.php#L10-L24)
- [SurgePrice.php:26-34](file://app/Models/SurgePrice.php#L26-L34)
- [module-setup.blade.php:1-403](file://resources/views/admin-views/zone/module-setup.blade.php#L1-L403)
- [index.blade.php:1-585](file://resources/views/admin-views/zone/index.blade.php#L1-L585)

**Section sources**
- [Zone.php:104-153](file://app/Models/Zone.php#L104-L153)
- [ModuleZone.php:10-24](file://app/Models/ModuleZone.php#L10-L24)
- [ZoneService.php:5-8](file://app/Services/ZoneService.php#L5-L8)
- [ZoneRepository.php:6-12](file://app/Repositories/ZoneRepository.php#L6-L12)
- [SurgePrice.php:26-34](file://app/Models/SurgePrice.php#L26-L34)
- [module-setup.blade.php:1-403](file://resources/views/admin-views/zone/module-setup.blade.php#L1-L403)
- [index.blade.php:1-585](file://resources/views/admin-views/zone/index.blade.php#L1-L585)

## Performance Considerations
- Spatial queries: Zones use spatial polygons; ensure proper indexing on geographic fields for efficient containment checks
- Coordinate parsing: ZoneService constructs polygons from raw coordinate strings; keep input validation tight to avoid malformed geometry
- Module synchronization: zoneModuleSetupUpdate performs a sync operation; batch updates when connecting many modules to reduce overhead
- Surges: Duration-based surge schedules can be precomputed or cached to minimize runtime calculations during checkout

## Troubleshooting Guide
Common validation failures and remedies:
- Fixed delivery charge missing: When delivery_charge_type is fixed, ensure fixed_shipping_charge is set
- Distance-based charge missing required fields: Ensure per_km_shipping_charge and minimum_shipping_charge are both provided
- Maximum less than minimum: If maximum_shipping_charge is set, it must be greater than or equal to minimum_shipping_charge
- Unknown delivery charge type: delivery_charge_type must be either fixed or distance

Operational checks:
- Payment method flags: At least one of cash_on_delivery, digital_payment, or offline_payment must be enabled
- COD limit configuration: maximum_cod_order_amount should be set appropriately per module-zone
- Surge pricing: Verify zone association and duration settings for accurate customer messaging and price adjustments

**Section sources**
- [ZoneService.php:94-123](file://app/Services/ZoneService.php#L94-L123)
- [module-setup.blade.php:32-88](file://resources/views/admin-views/zone/module-setup.blade.php#L32-L88)
- [2022_12_29_105321_add_maximum_cod_order_amount_column_to_module_zone_table.php:16-18](file://database/migrations/2022_12_29_105321_add_maximum_cod_order_amount_column_to_module_zone_table.php#L16-L18)
- [2025_07_13_185456_create_surge_prices_table.php:19-33](file://database/migrations/2025_07_13_185456_create_surge_prices_table.php#L19-L33)

## Conclusion
The zone configuration system provides robust, per-zone control over pricing, COD limits, and payment methods, with optional surge pricing and messaging for increased delivery fees. Admin workflows enforce business rules through validation and synchronization, while models and services encapsulate spatial and pricing logic for reliable runtime behavior.