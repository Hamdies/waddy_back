# Prize Redemption System

<cite>
**Referenced Files in This Document**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)
- [2025_12_28_000001_add_xp_level_to_users_table.php](file://database/migrations/2025_12_28_000001_add_xp_level_to_users_table.php)
- [2025_12_28_000002_create_levels_table.php](file://database/migrations/2025_12_28_000002_create_levels_table.php)
- [2025_12_28_000003_create_level_prizes_table.php](file://database/migrations/2025_12_28_000003_create_level_prizes_table.php)
- [2025_12_28_000007_create_user_level_prizes_table.php](file://database/migrations/2025_12_28_000007_create_user_level_prizes_table.php)
- [2026_01_07_000001_add_prize_constraints_to_level_prizes.php](file://database/migrations/2026_01_07_000001_add_prize_constraints_to_level_prizes.php)
- [2025_12_28_000005_create_xp_challenges_table.php](file://database/migrations/2025_12_28_000005_create_xp_challenges_table.php)
- [2025_12_28_000006_create_user_challenges_table.php](file://database/migrations/2025_12_28_000006_create_user_challenges_table.php)
- [2025_12_28_000004_create_xp_transactions_table.php](file://database/migrations/2025_12_28_000004_create_xp_transactions_table.php)
- [2025_12_28_000008_create_xp_settings_table.php](file://database/migrations/2025_12_28_000008_create_xp_settings_table.php)
- [XpService.php](file://app/Services/XpService.php)
- [PlaceXpService.php](file://Modules/PlacesToVisit/Services/PlaceXpService.php)
- [XpServiceTest.php](file://tests/Unit/XpServiceTest.php)
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
This document provides comprehensive documentation for the prize redemption system, covering prize types, claiming mechanisms, status management, availability filtering, checkout integration, and reward items for free_item prizes. It consolidates the frontend API documentation and backend implementation details to explain how users unlock, claim, and redeem various prizes such as badges, free delivery, wallet credits, free items, discounts, and custom rewards.

## Project Structure
The prize redemption system spans frontend API endpoints, database migrations defining the data model, and backend services implementing the logic. Key areas include:
- API endpoints for prize management, checkout integration, and reward items
- Database schema for levels, level prizes, user-level prize instances, XP settings, transactions, and challenges
- Backend services orchestrating XP calculations, prize unlocking, and fulfillment

```mermaid
graph TB
subgraph "API Layer"
A["GET /xp/levels"]
B["GET /xp/prizes"]
C["POST /xp/prizes/{id}/claim"]
D["GET /xp/checkout-prizes"]
E["GET /xp/reward-items"]
F["POST /api/v1/customer/order/place"]
end
subgraph "Services"
S1["XpService"]
S2["PlaceXpService"]
end
subgraph "Database"
T1["levels"]
T2["level_prizes"]
T3["user_level_prizes"]
T4["xp_settings"]
T5["xp_transactions"]
T6["xp_challenges"]
T7["user_challenges"]
end
A --> S1
B --> S1
C --> S1
D --> S1
E --> S1
F --> S1
S1 --> T1
S1 --> T2
S1 --> T3
S1 --> T4
S1 --> T5
S1 --> T6
S1 --> T7
S2 --> T5
```

**Diagram sources**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)
- [XpService.php](file://app/Services/XpService.php)
- [PlaceXpService.php](file://Modules/PlacesToVisit/Services/PlaceXpService.php)
- [2025_12_28_000002_create_levels_table.php](file://database/migrations/2025_12_28_000002_create_levels_table.php)
- [2025_12_28_000003_create_level_prizes_table.php](file://database/migrations/2025_12_28_000003_create_level_prizes_table.php)
- [2025_12_28_000007_create_user_level_prizes_table.php](file://database/migrations/2025_12_28_000007_create_user_level_prizes_table.php)
- [2025_12_28_000008_create_xp_settings_table.php](file://database/migrations/2025_12_28_000008_create_xp_settings_table.php)
- [2025_12_28_000004_create_xp_transactions_table.php](file://database/migrations/2025_12_28_000004_create_xp_transactions_table.php)
- [2025_12_28_000005_create_xp_challenges_table.php](file://database/migrations/2025_12_28_000005_create_xp_challenges_table.php)
- [2025_12_28_000006_create_user_challenges_table.php](file://database/migrations/2025_12_28_000006_create_user_challenges_table.php)

**Section sources**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)
- [2025_12_28_000001_add_xp_level_to_users_table.php](file://database/migrations/2025_12_28_000001_add_xp_level_to_users_table.php)
- [2025_12_28_000002_create_levels_table.php](file://database/migrations/2025_12_28_000002_create_levels_table.php)
- [2025_12_28_000003_create_level_prizes_table.php](file://database/migrations/2025_12_28_000003_create_level_prizes_table.php)
- [2025_12_28_000007_create_user_level_prizes_table.php](file://database/migrations/2025_12_28_000007_create_user_level_prizes_table.php)
- [2026_01_07_000001_add_prize_constraints_to_level_prizes.php](file://database/migrations/2026_01_07_000001_add_prize_constraints_to_level_prizes.php)
- [2025_12_28_000005_create_xp_challenges_table.php](file://database/migrations/2025_12_28_000005_create_xp_challenges_table.php)
- [2025_12_28_000006_create_user_challenges_table.php](file://database/migrations/2025_12_28_000006_create_user_challenges_table.php)
- [2025_12_28_000004_create_xp_transactions_table.php](file://database/migrations/2025_12_28_000004_create_xp_transactions_table.php)
- [2025_12_28_000008_create_xp_settings_table.php](file://database/migrations/2025_12_28_000008_create_xp_settings_table.php)

## Core Components
This section outlines the primary components of the prize redemption system, including prize types, statuses, and the claiming mechanism.

- Prize Types
  - Badge: Auto-unlocked on level reach; display-only.
  - Free Delivery: Usable at checkout with minimum order requirements.
  - Wallet Credit: Immediate wallet credit upon claiming.
  - Free Item: Redeemable for specific store items.
  - Discount: Order discount vouchers.
  - Custom: Special rewards.

- Status Management
  - Unlocked: Available to claim.
  - Claimed: Claimed but not yet used (e.g., free delivery).
  - Used: Consumed.
  - Expired: Validity period ended.

- Claiming Mechanism
  - Endpoint: POST /xp/prizes/{id}/claim
  - Behavior varies by type:
    - Wallet Credit: Credits added immediately; status becomes used.
    - Free Delivery: Status becomes claimed; usable at checkout.
    - Free Item: Status becomes claimed.
    - Discount: Status becomes claimed.
    - Badge: Auto-claimed on unlock; no manual claim needed.

- Availability Filtering
  - Usable prizes include both unlocked and claimed prizes that haven't expired and pass period limits.
  - is_usable indicates whether a prize can be applied.

**Section sources**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)

## Architecture Overview
The system integrates frontend API endpoints with backend services and database models. The flow below illustrates the end-to-end process from claiming a prize to applying it at checkout.

```mermaid
sequenceDiagram
participant Client as "Client App"
participant API as "XP API"
participant Service as "XpService"
participant DB as "Database"
Client->>API : GET /xp/prizes
API->>Service : Fetch user prizes
Service->>DB : Query user_level_prizes + level_prizes
DB-->>Service : Prizes with status and constraints
Service-->>API : Grouped prizes (usable/used/expired)
API-->>Client : Prize list
Client->>API : POST /xp/prizes/{id}/claim
API->>Service : Claim prize by id
Service->>DB : Update status to claimed/used depending on type
DB-->>Service : Updated record
Service-->>API : Claim result
API-->>Client : Success with new status
Client->>API : GET /xp/checkout-prizes?order_amount={subtotal}
API->>Service : Filter eligible free delivery prizes
Service->>DB : Apply min_order_amount and expiry checks
DB-->>Service : Eligible prizes
Service-->>API : Eligible prize list
API-->>Client : Checkout options
Client->>API : POST /api/v1/customer/order/place { use_prize_id }
API->>Service : Validate and apply prize
Service->>DB : Verify prize validity and update order
DB-->>Service : Order updated with delivery charge reduction
Service-->>API : Order confirmation
API-->>Client : Order placed with prize applied
```

**Diagram sources**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)
- [XpService.php](file://app/Services/XpService.php)
- [2025_12_28_000003_create_level_prizes_table.php](file://database/migrations/2025_12_28_000003_create_level_prizes_table.php)
- [2025_12_28_000007_create_user_level_prizes_table.php](file://database/migrations/2025_12_28_000007_create_user_level_prizes_table.php)

## Detailed Component Analysis

### Prize Types and Behaviors
- Badge
  - Auto-unlocked on level reach; display-only.
  - No manual claim required.
- Free Delivery
  - Claimed status becomes usable at checkout.
  - Requires meeting minimum order amount and not being expired.
  - Applied by passing use_prize_id in order placement.
- Wallet Credit
  - On claim, credits are added immediately; status becomes used.
- Free Item
  - Status becomes claimed after claim; fulfillment handled separately.
- Discount
  - Status becomes claimed after claim; usage governed by constraints.
- Custom
  - Special rewards configured per level.

**Section sources**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)

### Prize Claiming Process
- Endpoint: POST /xp/prizes/{id}/claim
- Request: No body required.
- Response: Success message and updated prize with new status.
- Validation: Returns 404 if prize not found; 403 if already claimed/used/expired.

```mermaid
sequenceDiagram
participant Client as "Client App"
participant API as "XP API"
participant Service as "XpService"
participant DB as "Database"
Client->>API : POST /xp/prizes/{id}/claim
API->>Service : Validate and claim prize
Service->>DB : Check status and type
alt Wallet Credit
Service->>DB : Credit wallet and mark used
else Free Delivery
Service->>DB : Mark claimed for checkout
else Free Item
Service->>DB : Mark claimed pending fulfillment
else Discount
Service->>DB : Mark claimed pending usage
else Badge
Service->>DB : Auto-complete on unlock
end
DB-->>Service : Updated status
Service-->>API : Result
API-->>Client : Success with new status
```

**Diagram sources**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)
- [XpService.php](file://app/Services/XpService.php)

**Section sources**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)

### Checkout Integration and Validation
- Endpoint: GET /xp/checkout-prizes
  - Filters free delivery prizes by min_order_amount eligibility.
  - Returns eligible prizes with expiration and level name.
- Order Placement
  - Endpoint: POST /api/v1/customer/order/place
  - Pass use_prize_id from checkout-prizes or prizes endpoint.
  - Only free_delivery prizes are supported at checkout currently.
  - If valid, delivery charge becomes zero; otherwise, normal delivery charge applies.

```mermaid
sequenceDiagram
participant Client as "Client App"
participant API as "XP API"
participant OrderAPI as "Order API"
participant Service as "XpService"
participant DB as "Database"
Client->>API : GET /xp/checkout-prizes?order_amount={subtotal}
API->>Service : Filter eligible free delivery prizes
Service->>DB : Apply min_order_amount and expiry checks
DB-->>Service : Eligible prizes
Service-->>API : Eligible prize list
API-->>Client : Options
Client->>OrderAPI : POST /api/v1/customer/order/place { use_prize_id }
OrderAPI->>Service : Validate and apply prize
Service->>DB : Verify validity and update order
DB-->>Service : Order updated with delivery charge reduction
Service-->>OrderAPI : Confirmation
OrderAPI-->>Client : Order placed with prize applied
```

**Diagram sources**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)
- [XpService.php](file://app/Services/XpService.php)

**Section sources**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)

### Reward Items System for Free Item Prizes
- Endpoint: GET /xp/reward-items
  - Query parameters: store_id (required), reward_type (optional).
  - Returns reward items available for free_item, free_side, or birthday_gift types.
  - Includes item metadata and max_value constraints.

**Section sources**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)

### Database Model and Constraints
The database schema defines the core entities and constraints for the XP and prize system:

```mermaid
erDiagram
LEVELS {
bigint id PK
tinyint level_number UK
string name
bigint xp_required
text description
string badge_image
boolean status
timestamps timestamps
}
LEVEL_PRIZES {
bigint id PK
bigint level_id FK
string title
text description
enum prize_type
decimal value
decimal min_order_amount
integer usage_limit
integer validity_days
boolean status
timestamps timestamps
}
USER_LEVEL_PRIZES {
bigint id PK
bigint user_id FK
bigint level_prize_id FK
enum status
bigint order_id
timestamp unlocked_at
timestamp expires_at
timestamp claimed_at
timestamp used_at
timestamps timestamps
}
XP_SETTINGS {
bigint id PK
string key UK
string value
string description
timestamps timestamps
}
XP_TRANSACTIONS {
bigint id PK
bigint user_id FK
string reference_type
bigint reference_id
string xp_source
integer xp_amount
bigint balance_after
text description
boolean is_reversed
timestamps timestamps
}
XP_CHALLENGES {
bigint id PK
string title
text description
enum challenge_type
enum frequency
json conditions
integer xp_reward
integer time_limit_hours
boolean status
timestamps timestamps
}
USER_CHALLENGES {
bigint id PK
bigint user_id FK
bigint xp_challenge_id FK
enum status
json progress
timestamp started_at
timestamp expires_at
timestamp completed_at
timestamp claimed_at
timestamp next_available_at
timestamps timestamps
}
LEVELS ||--o{ LEVEL_PRIZES : "contains"
LEVEL_PRIZES ||--o{ USER_LEVEL_PRIZES : "awarded_as"
USERS ||--o{ USER_LEVEL_PRIZES : "owns"
XP_SETTINGS ||--|| XP_SETTINGS : "configuration"
USERS ||--o{ XP_TRANSACTIONS : "earns"
XP_CHALLENGES ||--o{ USER_CHALLENGES : "assigned_to"
USERS ||--o{ USER_CHALLENGES : "completes"
```

**Diagram sources**
- [2025_12_28_000002_create_levels_table.php](file://database/migrations/2025_12_28_000002_create_levels_table.php)
- [2025_12_28_000003_create_level_prizes_table.php](file://database/migrations/2025_12_28_000003_create_level_prizes_table.php)
- [2025_12_28_000007_create_user_level_prizes_table.php](file://database/migrations/2025_12_28_000007_create_user_level_prizes_table.php)
- [2025_12_28_000008_create_xp_settings_table.php](file://database/migrations/2025_12_28_000008_create_xp_settings_table.php)
- [2025_12_28_000004_create_xp_transactions_table.php](file://database/migrations/2025_12_28_000004_create_xp_transactions_table.php)
- [2025_12_28_000005_create_xp_challenges_table.php](file://database/migrations/2025_12_28_000005_create_xp_challenges_table.php)
- [2025_12_28_000006_create_user_challenges_table.php](file://database/migrations/2025_12_28_000006_create_user_challenges_table.php)

**Section sources**
- [2025_12_28_000002_create_levels_table.php](file://database/migrations/2025_12_28_000002_create_levels_table.php)
- [2025_12_28_000003_create_level_prizes_table.php](file://database/migrations/2025_12_28_000003_create_level_prizes_table.php)
- [2025_12_28_000007_create_user_level_prizes_table.php](file://database/migrations/2025_12_28_000007_create_user_level_prizes_table.php)
- [2026_01_07_000001_add_prize_constraints_to_level_prizes.php](file://database/migrations/2026_01_07_000001_add_prize_constraints_to_level_prizes.php)
- [2025_12_28_000008_create_xp_settings_table.php](file://database/migrations/2025_12_28_000008_create_xp_settings_table.php)
- [2025_12_28_000004_create_xp_transactions_table.php](file://database/migrations/2025_12_28_000004_create_xp_transactions_table.php)
- [2025_12_28_000005_create_xp_challenges_table.php](file://database/migrations/2025_12_28_000005_create_xp_challenges_table.php)
- [2025_12_28_000006_create_user_challenges_table.php](file://database/migrations/2025_12_28_000006_create_user_challenges_table.php)

### Backend Services
- XpService
  - Orchestrates XP calculations, challenge assignments, and prize management.
  - Handles prize unlocking, claiming, and validation for checkout.
- PlaceXpService
  - Integrates XP awarding with place order flow, ensuring proper transaction recording.

**Section sources**
- [XpService.php](file://app/Services/XpService.php)
- [PlaceXpService.php](file://Modules/PlacesToVisit/Services/PlaceXpService.php)

## Dependency Analysis
The system exhibits clear separation of concerns:
- API layer depends on services for business logic.
- Services depend on database models for persistence.
- Database models define referential integrity and constraints.

```mermaid
graph TB
API["XP API Endpoints"] --> Service["XpService"]
Service --> Models["Database Models"]
Models --> DB["Database"]
Service --> OrderAPI["Order Placement API"]
OrderAPI --> Service
Tests["XpServiceTest"] --> Service
```

**Diagram sources**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)
- [XpService.php](file://app/Services/XpService.php)
- [XpServiceTest.php](file://tests/Unit/XpServiceTest.php)

**Section sources**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)
- [XpService.php](file://app/Services/XpService.php)
- [XpServiceTest.php](file://tests/Unit/XpServiceTest.php)

## Performance Considerations
- Indexes on user_id and status in user_level_prizes facilitate fast filtering of usable prizes.
- Unique constraints on XP transactions prevent duplicate awards.
- Period-based usage limits and expiry checks should leverage indexed timestamps for efficient queries.
- Caching of XP configuration reduces repeated database reads for client-side calculations.

## Troubleshooting Guide
Common issues and resolutions:
- Prize Not Found (404)
  - Verify the prize id corresponds to the user's instance id.
- Already Claimed/Used/Expired (403)
  - Check prize status and expiry date; ensure min_order_amount eligibility.
- Free Delivery Not Applied at Checkout
  - Confirm the prize is claimed, not expired, and meets min_order_amount.
  - Ensure use_prize_id matches an eligible prize id from checkout-prizes.
- Wallet Credit Not Credited
  - For wallet_credit type, verify immediate credit and status change to used.

**Section sources**
- [XP_SYSTEM_API_DOCS.md](file://XP_SYSTEM_API_DOCS.md)

## Conclusion
The prize redemption system provides a robust framework for rewarding users through diverse prize types with clear status management and checkout integration. The backend services coordinate with database models to enforce constraints, while the frontend API ensures a seamless user experience across claiming, filtering, and redemption processes.