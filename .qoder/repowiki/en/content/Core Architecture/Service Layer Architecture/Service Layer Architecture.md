# Service Layer Architecture

<cite>
**Referenced Files in This Document**
- [AdminService.php](file://app/Services/AdminService.php)
- [AdminServiceInterface.php](file://app/Contracts/AdminServiceInterface.php)
- [OrderStatusService.php](file://app/Services/OrderStatusService.php)
- [OrderSecurityService.php](file://app/Services/OrderSecurityService.php)
- [OrderNotificationService.php](file://app/Services/OrderNotificationService.php)
- [OrderTrackingService.php](file://app/Services/OrderTrackingService.php)
- [OrderTransactionRepository.php](file://app/Repositories/OrderTransactionRepository.php)
- [InterfaceServiceProvider.php](file://app/Providers/InterfaceServiceProvider.php)
- [AppServiceProvider.php](file://app/Providers/AppServiceProvider.php)
- [LeaderboardService.php](file://Modules/PlacesToVisit/Services/LeaderboardService.php)
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
This document explains the service layer architecture of Waddy Back, focusing on how services encapsulate business logic, coordinate between repositories and models, and provide clean interfaces for controllers. It documents service contracts, dependency injection usage, and demonstrates how services handle complex operations such as order processing, user management, and notification delivery. The analysis includes concrete examples from the codebase, error handling strategies, transaction management, and the relationship with the repository pattern and business rule enforcement.

## Project Structure
The service layer is organized under app/Services and app/Repositories, with contracts under app/Contracts. Providers manage binding interfaces to implementations. Module-specific services live under Modules/<ModuleName>/Services. The service layer sits between controllers and repositories/models, centralizing business logic and ensuring testability and separation of concerns.

```mermaid
graph TB
subgraph "Controllers"
C1["Controllers"]
end
subgraph "Services"
S1["OrderStatusService"]
S2["OrderSecurityService"]
S3["OrderNotificationService"]
S4["OrderTrackingService"]
S5["AdminService"]
S6["LeaderboardService"]
end
subgraph "Repositories"
R1["OrderTransactionRepository"]
end
subgraph "Models"
M1["Order"]
M2["OrderTransaction"]
M3["OrderStatusLog"]
M4["OrderTrackingLog"]
M5["LiveActivityToken"]
M6["User"]
end
C1 --> S1
C1 --> S2
C1 --> S3
C1 --> S4
C1 --> S5
C1 --> S6
S1 --> R1
S1 --> M1
S1 --> M3
S2 --> M1
S3 --> M1
S3 --> M5
S4 --> M1
S4 --> M4
S5 --> M6
S6 --> M1
```

**Diagram sources**
- [OrderStatusService.php:1-348](file://app/Services/OrderStatusService.php#L1-L348)
- [OrderSecurityService.php:1-137](file://app/Services/OrderSecurityService.php#L1-L137)
- [OrderNotificationService.php:1-312](file://app/Services/OrderNotificationService.php#L1-L312)
- [OrderTrackingService.php:1-124](file://app/Services/OrderTrackingService.php#L1-L124)
- [OrderTransactionRepository.php:1-76](file://app/Repositories/OrderTransactionRepository.php#L1-L76)
- [AdminService.php:1-23](file://app/Services/AdminService.php#L1-L23)
- [LeaderboardService.php:1-141](file://Modules/PlacesToVisit/Services/LeaderboardService.php#L1-L141)

**Section sources**
- [OrderStatusService.php:1-348](file://app/Services/OrderStatusService.php#L1-L348)
- [OrderSecurityService.php:1-137](file://app/Services/OrderSecurityService.php#L1-L137)
- [OrderNotificationService.php:1-312](file://app/Services/OrderNotificationService.php#L1-L312)
- [OrderTrackingService.php:1-124](file://app/Services/OrderTrackingService.php#L1-L124)
- [OrderTransactionRepository.php:1-76](file://app/Repositories/OrderTransactionRepository.php#L1-L76)
- [AdminService.php:1-23](file://app/Services/AdminService.php#L1-L23)
- [LeaderboardService.php:1-141](file://Modules/PlacesToVisit/Services/LeaderboardService.php#L1-L141)

## Core Components
- OrderStatusService: Centralized orchestration for order status transitions, validation, transaction management, notifications, and audit logging.
- OrderSecurityService: Idempotency checks, rate-limiting, and HMAC signature verification for order requests.
- OrderNotificationService: Push notification building and dispatching, Live Activity updates, and proximity-based triggers.
- OrderTrackingService: Location logging, tracking history retrieval, and sub-status updates with optional notifications.
- AdminService: Authentication and logout for administrative contexts.
- LeaderboardService (module): Aggregates and caches leaderboard data for place voting.

These services depend on repositories and models to persist and retrieve data, while exposing clean method signatures to controllers.

**Section sources**
- [OrderStatusService.php:1-348](file://app/Services/OrderStatusService.php#L1-L348)
- [OrderSecurityService.php:1-137](file://app/Services/OrderSecurityService.php#L1-L137)
- [OrderNotificationService.php:1-312](file://app/Services/OrderNotificationService.php#L1-L312)
- [OrderTrackingService.php:1-124](file://app/Services/OrderTrackingService.php#L1-L124)
- [AdminService.php:1-23](file://app/Services/AdminService.php#L1-L23)
- [LeaderboardService.php:1-141](file://Modules/PlacesToVisit/Services/LeaderboardService.php#L1-L141)

## Architecture Overview
The service layer follows a layered architecture:
- Controllers receive requests and delegate to services.
- Services validate inputs, enforce business rules, and coordinate with repositories/models.
- Repositories abstract persistence and encapsulate query logic.
- Models represent domain entities and relationships.
- Providers bind interfaces to implementations for dependency injection.

```mermaid
sequenceDiagram
participant Client as "Client"
participant Controller as "Controller"
participant OrderStatusSvc as "OrderStatusService"
participant Order as "Order Model"
participant EstDelSvc as "EstimatedDeliveryService"
participant Helpers as "Helpers"
participant DB as "Database"
Client->>Controller : "POST /orders/{id}/status"
Controller->>OrderStatusSvc : "updateStatus(order, newStatus, updatedBy, options)"
OrderStatusSvc->>OrderStatusSvc : "validateTransition()"
OrderStatusSvc->>DB : "DB : : transaction()"
DB-->>OrderStatusSvc : "transaction block"
OrderStatusSvc->>Order : "lockForUpdate()"
OrderStatusSvc->>EstDelSvc : "recalculateOnStatusChange()"
EstDelSvc-->>OrderStatusSvc : "new estimated delivery time"
OrderStatusSvc->>Order : "save()"
OrderStatusSvc->>Helpers : "send_order_notification()"
Helpers-->>OrderStatusSvc : "notification dispatched"
OrderStatusSvc-->>Controller : "{success, message}"
Controller-->>Client : "HTTP 200/4xx"
```

**Diagram sources**
- [OrderStatusService.php:89-156](file://app/Services/OrderStatusService.php#L89-L156)

**Section sources**
- [OrderStatusService.php:1-348](file://app/Services/OrderStatusService.php#L1-L348)

## Detailed Component Analysis

### OrderStatusService
Responsibilities:
- Validates allowed status transitions against configuration.
- Performs atomic updates using database transactions.
- Handles special transitions (delivered, canceled, refunded) with side effects.
- Updates estimated delivery time via EstimatedDeliveryService.
- Logs status changes for audit trails.
- Sends order notifications via Helpers.

Key implementation patterns:
- Static service methods for centralized logic.
- Match expressions for transition-specific handling.
- DB::transaction for atomicity.
- Eloquent lockForUpdate for concurrency safety.
- Integration with OrderStatusLog model for audit trail.

Error handling:
- Returns structured results with success flags and messages.
- Wraps transaction in try/catch to capture failures.
- Graceful logging for notification failures.

Transaction management:
- Uses database transactions to ensure consistency across order updates, delivery man adjustments, and inventory/order count increments.

Relationships:
- Depends on Order, DeliveryMan, OrderStatusLog, EstimatedDeliveryService, Helpers, and Eloquent models.

```mermaid
flowchart TD
Start(["updateStatus Entry"]) --> Validate["validateTransition(current, new)"]
Validate --> Valid{"Transition Allowed?"}
Valid --> |No| ReturnInvalid["Return invalid transition error"]
Valid --> |Yes| Txn["DB::transaction()"]
Txn --> Lock["Order::lockForUpdate()"]
Lock --> Handle{"Match newStatus"}
Handle --> |delivered| Delivered["handleDelivered()"]
Handle --> |canceled| Canceled["handleCanceled()"]
Handle --> |refunded| Refunded["handleRefunded()"]
Handle --> |default| Skip["No special handling"]
Delivered --> UpdateOrder["Set order_status, timestamps, payment_status"]
Canceled --> UpdateOrder
Refunded --> UpdateOrder
Skip --> UpdateOrder
UpdateOrder --> Recalc["EstimatedDeliveryService::recalculateOnStatusChange()"]
Recalc --> Save["Order->save()"]
Save --> Log["OrderStatusLog::logStatusChange()"]
Log --> Notify["Helpers::send_order_notification()"]
Notify --> Done(["Return success"])
ReturnInvalid --> Done
```

**Diagram sources**
- [OrderStatusService.php:89-156](file://app/Services/OrderStatusService.php#L89-L156)

**Section sources**
- [OrderStatusService.php:1-348](file://app/Services/OrderStatusService.php#L1-L348)

### OrderSecurityService
Responsibilities:
- Idempotency enforcement using cache keys to prevent duplicate submissions.
- Cooldown enforcement per user/guest to throttle order frequency.
- HMAC-SHA256 signature verification with timestamp window checking.
- Stores security fields on the order for audit.

Error handling:
- Returns structured HTTP 409/429 responses for violations.
- Logs warnings and informational events without failing the request.

```mermaid
flowchart TD
Start(["checkIdempotency Entry"]) --> Key["Read idempotency_key"]
Key --> HasKey{"Key present?"}
HasKey --> |No| LogWarn["Log info, allow pass"] --> End
HasKey --> |Yes| CacheKey["Build cache key"]
CacheKey --> Exists{"Cache key exists?"}
Exists --> |Yes| Conflict["Return 409 Duplicate Order"]
Exists --> |No| SetCache["Cache::add(key, true, TTL)"] --> End
subgraph "Cooldown"
CStart(["checkOrderCooldown Entry"]) --> UserKey["Build user/guest cache key"]
UserKey --> HasCooldown{"Cooldown active?"}
HasCooldown --> |Yes| TooSoon["Return 429 Too Many Requests"] --> CEnd
HasCooldown --> |No| SetCD["Cache::put(key, true, COOLDOWN)"] --> CEnd
end
subgraph "Signature Verification"
SStart(["verifySignature Entry"]) --> ReadSig["Read signature & timestamp"]
ReadSig --> ParamsOK{"Signature & timestamp present?"}
ParamsOK --> |No| LogNoSig["Log info, continue"] --> SEnd
ParamsOK --> |Yes| Window["Check timestamp window"]
Window --> Expired{"Expired?"}
Expired --> |Yes| Warn["Log warning"] --> Compare["Compute HMAC"]
Expired --> |No| Compare["Compute HMAC"]
Compare --> Match{"Hash equals?"}
Match --> |No| Warn2["Log warning"] --> SEnd
Match --> |Yes| Pass["Signature valid"] --> SEnd
end
```

**Diagram sources**
- [OrderSecurityService.php:22-125](file://app/Services/OrderSecurityService.php#L22-L125)

**Section sources**
- [OrderSecurityService.php:1-137](file://app/Services/OrderSecurityService.php#L1-L137)

### OrderNotificationService
Responsibilities:
- Builds localized, structured notification payloads for order status changes.
- Dispatches push notifications via shared trait methods.
- Manages Live Activity updates for iOS.
- Calculates ETA and constructs display-friendly titles/subtitles.
- Proximity-based triggers to notify customers when drivers are near.

Integration points:
- Uses Order model relationships (store, delivery_man).
- Integrates with LiveActivityService for iOS updates.

```mermaid
sequenceDiagram
participant Order as "Order"
participant Notifier as "OrderNotificationService"
participant FCM as "Push Gateway"
participant Live as "LiveActivityService"
Notifier->>Order : "Load store & delivery_man"
Notifier->>Notifier : "buildExtendedPayload()"
Notifier->>FCM : "sendPushNotificationToDevice(token, data)"
FCM-->>Notifier : "result"
Notifier->>Live : "pushUpdate(push_token, order, event)"
Live-->>Notifier : "result"
```

**Diagram sources**
- [OrderNotificationService.php:86-122](file://app/Services/OrderNotificationService.php#L86-L122)
- [OrderNotificationService.php:177-196](file://app/Services/OrderNotificationService.php#L177-L196)

**Section sources**
- [OrderNotificationService.php:1-312](file://app/Services/OrderNotificationService.php#L1-L312)

### OrderTrackingService
Responsibilities:
- Logs driver location updates with order status and sub-status.
- Retrieves tracking history and current tracking data.
- Updates sub-status and optionally notifies the customer.

Dependency injection:
- Accepts OrderNotificationService via constructor injection.

```mermaid
classDiagram
class OrderTrackingService {
-OrderNotificationService notificationService
+__construct(notificationService)
+logLocationUpdate(order, deliveryMan) OrderTrackingLog
+getTrackingHistory(orderId, limit) Collection
+getCurrentTrackingData(order) array
+updateSubStatus(order, subStatus, notify) bool
}
class OrderNotificationService {
+notifyStatusChange(order) bool
+checkProximityNotification(order, lat, lng) bool
}
OrderTrackingService --> OrderNotificationService : "uses"
```

**Diagram sources**
- [OrderTrackingService.php:12-19](file://app/Services/OrderTrackingService.php#L12-L19)
- [OrderTrackingService.php:28-50](file://app/Services/OrderTrackingService.php#L28-L50)
- [OrderTrackingService.php:110-122](file://app/Services/OrderTrackingService.php#L110-L122)

**Section sources**
- [OrderTrackingService.php:1-124](file://app/Services/OrderTrackingService.php#L1-L124)

### AdminService
Responsibilities:
- Handles admin login attempts with remember token support.
- Performs logout and session invalidation.

Contract:
- Implements AdminServiceInterface for type-safe contracts.

```mermaid
classDiagram
class AdminServiceInterface {
<<interface>>
+isLoginSuccessful(email, password, rememberToken) bool
+logout() void
}
class AdminService {
+isLoginSuccessful(email, password, rememberToken) bool
+logout() void
}
AdminService ..|> AdminServiceInterface
```

**Diagram sources**
- [AdminServiceInterface.php:5-10](file://app/Contracts/AdminServiceInterface.php#L5-L10)
- [AdminService.php:7-22](file://app/Services/AdminService.php#L7-L22)

**Section sources**
- [AdminServiceInterface.php:1-11](file://app/Contracts/AdminServiceInterface.php#L1-L11)
- [AdminService.php:1-23](file://app/Services/AdminService.php#L1-L23)

### LeaderboardService (Module)
Responsibilities:
- Computes top places and top voters with caching and configurable limits.
- Supports filtering by period, category, and zone.
- Clears cache for recomputation.

```mermaid
flowchart TD
Start(["getTopPlaces Entry"]) --> Period["Resolve period"]
Period --> CacheKey["Build cache key"]
CacheKey --> CacheGet{"Cache hit?"}
CacheGet --> |Yes| ReturnCache["Return cached collection"]
CacheGet --> |No| Query["Query Places with votes_count & avg_rating"]
Query --> Filter["having votes_count >= min_votes"]
Filter --> Sort["orderByDesc(votes_count), orderByDesc(avg_rating)"]
Sort --> Limit["limit(limit)"]
Limit --> Map["Map to simplified structure"]
Map --> StoreCache["Cache::remember(...)"]
StoreCache --> ReturnCache
```

**Diagram sources**
- [LeaderboardService.php:28-58](file://Modules/PlacesToVisit/Services/LeaderboardService.php#L28-L58)

**Section sources**
- [LeaderboardService.php:1-141](file://Modules/PlacesToVisit/Services/LeaderboardService.php#L1-L141)

## Dependency Analysis
Dependency injection and bindings:
- InterfaceServiceProvider scans repositories and binds matching interface-to-implementation pairs automatically.
- AppServiceProvider bootstraps global configurations and shares view data.

Repository pattern:
- OrderTransactionRepository implements a generic repository interface and encapsulates CRUD operations for OrderTransaction, enabling testable and reusable persistence logic.

```mermaid
graph TB
subgraph "Bindings"
ISP["InterfaceServiceProvider"]
ASP["AppServiceProvider"]
end
subgraph "Repositories"
OTR["OrderTransactionRepository"]
IR["Interfaces/Repositories/*"]
end
ISP --> IR
ISP --> OTR
ASP --> |"Config & Views"| App["Application"]
```

**Diagram sources**
- [InterfaceServiceProvider.php:20-36](file://app/Providers/InterfaceServiceProvider.php#L20-L36)
- [AppServiceProvider.php:29-45](file://app/Providers/AppServiceProvider.php#L29-L45)
- [OrderTransactionRepository.php:12-16](file://app/Repositories/OrderTransactionRepository.php#L12-L16)

**Section sources**
- [InterfaceServiceProvider.php:1-46](file://app/Providers/InterfaceServiceProvider.php#L1-L46)
- [AppServiceProvider.php:1-49](file://app/Providers/AppServiceProvider.php#L1-L49)
- [OrderTransactionRepository.php:1-76](file://app/Repositories/OrderTransactionRepository.php#L1-L76)

## Performance Considerations
- Use database transactions for atomic updates to avoid inconsistent states during order processing.
- Apply row-level locking (lockForUpdate) to prevent race conditions during concurrent updates.
- Cache heavy computations (e.g., leaderboard) with appropriate TTLs to reduce database load.
- Minimize N+1 queries by eager-loading relationships in services (e.g., loading store and delivery_man before sending notifications).
- Use pagination and filtered queries in repositories to limit result sets.

## Troubleshooting Guide
Common issues and resolutions:
- Order status update fails: Check transaction rollback causes and review validation logic in OrderStatusService.updateStatus.
- Duplicate order submission: Ensure idempotency_key is provided and cache TTL is configured correctly in OrderSecurityService.
- Too many requests: Verify cooldown cache keys and TTL values.
- Signature verification failures: Confirm HMAC secret and timestamp window configuration; inspect logs for warnings.
- Notification delivery problems: Validate device tokens and push gateway credentials; note that notification failures are logged but do not block the main flow.

**Section sources**
- [OrderStatusService.php:149-155](file://app/Services/OrderStatusService.php#L149-L155)
- [OrderSecurityService.php:36-44](file://app/Services/OrderSecurityService.php#L36-L44)
- [OrderSecurityService.php:59-69](file://app/Services/OrderSecurityService.php#L59-L69)
- [OrderSecurityService.php:117-124](file://app/Services/OrderSecurityService.php#L117-L124)
- [OrderNotificationService.php:139-142](file://app/Services/OrderNotificationService.php#L139-L142)

## Conclusion
Waddy Back’s service layer effectively encapsulates business logic, coordinates between repositories and models, and provides clean interfaces for controllers. Services enforce business rules, manage transactions, and integrate with external systems for notifications and security. The repository pattern and provider-based dependency injection further enhance modularity and testability. By following the patterns documented here, developers can extend and maintain the service layer reliably.