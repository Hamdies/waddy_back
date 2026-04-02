# Order Management Services

<cite>
**Referenced Files in This Document**
- [OrderStatusService.php](file://app/Services/OrderStatusService.php)
- [OrderTrackingService.php](file://app/Services/OrderTrackingService.php)
- [OrderSecurityService.php](file://app/Services/OrderSecurityService.php)
- [EstimatedDeliveryService.php](file://app/Services/EstimatedDeliveryService.php)
- [OrderNotificationService.php](file://app/Services/OrderNotificationService.php)
- [Order.php](file://app/Models/Order.php)
- [OrderStatusLog.php](file://app/Models/OrderStatusLog.php)
- [OrderTrackingLog.php](file://app/Models/OrderTrackingLog.php)
- [order.php](file://config/order.php)
- [DMLocationSocketHandler.php](file://app/WebSockets/Handler/DMLocationSocketHandler.php)
- [OrderController.php](file://app/Http/Controllers/Api/V1/OrderController.php)
- [OrderTrackingStreamController.php](file://app/Http/Controllers/Api/V1/OrderTrackingStreamController.php)
- [OrderTrackingHistoryController.php](file://app/Http/Controllers/Api/V1/OrderTrackingHistoryController.php)
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
This document provides comprehensive documentation for the order management services in the system. It focuses on four core services:
- OrderStatusService: centralizes order lifecycle management and status transitions with validation, atomic operations, notifications, and audit logging.
- OrderTrackingService: manages real-time order location updates, sub-status updates, and tracking history retrieval.
- OrderSecurityService: enforces idempotency, cooldowns, and HMAC signature verification to prevent duplicate orders and detect anomalies.
- EstimatedDeliveryService: calculates and recalculates estimated delivery times based on store processing time, distance, and order status.

Additionally, it documents integration points with WebSocket handlers for real-time location updates and with streaming endpoints for live order tracking.

## Project Structure
The order management system is organized around services, models, configuration, and controllers:
- Services encapsulate business logic for status transitions, tracking, security, and delivery estimation.
- Models define the domain entities and relationships (Order, OrderStatusLog, OrderTrackingLog).
- Configuration centralizes order-related settings such as valid status transitions and OTP limits.
- Controllers orchestrate requests and delegate to services for processing.

```mermaid
graph TB
subgraph "Controllers"
OC["OrderController"]
OTSC["OrderTrackingStreamController"]
OTHC["OrderTrackingHistoryController"]
end
subgraph "Services"
OSS["OrderStatusService"]
OTS["OrderTrackingService"]
OSSec["OrderSecurityService"]
EDS["EstimatedDeliveryService"]
ONS["OrderNotificationService"]
end
subgraph "Models"
OM["Order"]
OSL["OrderStatusLog"]
OTL["OrderTrackingLog"]
end
subgraph "Infrastructure"
WS["DMLocationSocketHandler"]
CFG["config/order.php"]
end
OC --> OSS
OC --> OSSec
OC --> ONS
OTSC --> OTS
OTHC --> OTL
OTS --> ONS
OSS --> EDS
OSS --> OSL
OTS --> OTL
WS --> OTL
OSS -.reads.-> CFG
```

**Diagram sources**
- [OrderController.php:1-791](file://app/Http/Controllers/Api/V1/OrderController.php#L1-L791)
- [OrderTrackingStreamController.php:1-200](file://app/Http/Controllers/Api/V1/OrderTrackingStreamController.php#L1-L200)
- [OrderTrackingHistoryController.php:1-87](file://app/Http/Controllers/Api/V1/OrderTrackingHistoryController.php#L1-L87)
- [OrderStatusService.php:1-348](file://app/Services/OrderStatusService.php#L1-L348)
- [OrderTrackingService.php:1-124](file://app/Services/OrderTrackingService.php#L1-L124)
- [OrderSecurityService.php:1-137](file://app/Services/OrderSecurityService.php#L1-L137)
- [EstimatedDeliveryService.php:1-172](file://app/Services/EstimatedDeliveryService.php#L1-L172)
- [OrderNotificationService.php:1-312](file://app/Services/OrderNotificationService.php#L1-L312)
- [Order.php:1-358](file://app/Models/Order.php#L1-L358)
- [OrderStatusLog.php:1-112](file://app/Models/OrderStatusLog.php#L1-L112)
- [OrderTrackingLog.php:1-56](file://app/Models/OrderTrackingLog.php#L1-L56)
- [DMLocationSocketHandler.php:1-83](file://app/WebSockets/Handler/DMLocationSocketHandler.php#L1-L83)
- [order.php:1-108](file://config/order.php#L1-L108)

**Section sources**
- [OrderStatusService.php:1-348](file://app/Services/OrderStatusService.php#L1-L348)
- [OrderTrackingService.php:1-124](file://app/Services/OrderTrackingService.php#L1-L124)
- [OrderSecurityService.php:1-137](file://app/Services/OrderSecurityService.php#L1-L137)
- [EstimatedDeliveryService.php:1-172](file://app/Services/EstimatedDeliveryService.php#L1-L172)
- [OrderNotificationService.php:1-312](file://app/Services/OrderNotificationService.php#L1-L312)
- [Order.php:1-358](file://app/Models/Order.php#L1-L358)
- [OrderStatusLog.php:1-112](file://app/Models/OrderStatusLog.php#L1-L112)
- [OrderTrackingLog.php:1-56](file://app/Models/OrderTrackingLog.php#L1-L56)
- [order.php:1-108](file://config/order.php#L1-L108)
- [DMLocationSocketHandler.php:1-83](file://app/WebSockets/Handler/DMLocationSocketHandler.php#L1-L83)
- [OrderController.php:1-791](file://app/Http/Controllers/Api/V1/OrderController.php#L1-L791)
- [OrderTrackingStreamController.php:1-200](file://app/Http/Controllers/Api/V1/OrderTrackingStreamController.php#L1-L200)
- [OrderTrackingHistoryController.php:1-87](file://app/Http/Controllers/Api/V1/OrderTrackingHistoryController.php#L1-L87)

## Core Components
This section outlines the primary responsibilities and methods for each service.

- OrderStatusService
  - Validates status transitions against configured rules.
  - Updates order status atomically, handles special transitions (delivered, canceled, refunded), recalculates estimated delivery time, logs status changes, and sends notifications.
  - Provides OTP verification with rate limiting and timeline retrieval.

- OrderTrackingService
  - Logs driver location updates with order context and triggers proximity notifications.
  - Retrieves tracking history and current tracking data for an order.
  - Updates sub-status and optionally notifies stakeholders.

- OrderSecurityService
  - Enforces idempotency via cache keys to prevent duplicate submissions.
  - Applies cooldown periods per user to avoid rapid successive orders.
  - Verifies HMAC-SHA256 signatures and timestamps for order requests.

- EstimatedDeliveryService
  - Computes initial and recalculation delivery estimates based on processing time, distance, and status-specific buffers.

**Section sources**
- [OrderStatusService.php:26-156](file://app/Services/OrderStatusService.php#L26-L156)
- [OrderTrackingService.php:28-122](file://app/Services/OrderTrackingService.php#L28-L122)
- [OrderSecurityService.php:22-135](file://app/Services/OrderSecurityService.php#L22-L135)
- [EstimatedDeliveryService.php:38-170](file://app/Services/EstimatedDeliveryService.php#L38-L170)

## Architecture Overview
The order management architecture integrates services with models, configuration, and infrastructure components to support robust order lifecycle management, real-time tracking, and security controls.

```mermaid
classDiagram
class OrderStatusService {
+validateTransition(currentStatus, newStatus) bool
+getAvailableTransitions(order) array
+updateStatus(order, newStatus, updatedBy, options) array
+verifyOTP(order, otp) array
+getOrderTimeline(orderId) array
}
class OrderTrackingService {
+logLocationUpdate(order, deliveryMan) OrderTrackingLog
+getTrackingHistory(orderId, limit) Collection
+getCurrentTrackingData(order) array
+updateSubStatus(order, subStatus, notify) bool
}
class OrderSecurityService {
+checkIdempotency(request) JsonResponse?
+checkOrderCooldown(request) JsonResponse?
+verifySignature(request) void
+storeSecurityFields(order, request) void
}
class EstimatedDeliveryService {
+calculateInitialEstimate(order, store) Carbon
+recalculateOnStatusChange(order, newStatus) Carbon?
}
class Order {
+order_status
+sub_status
+estimated_delivery_at
+distance
+processing_time
+delivery_man()
+tracking_logs()
}
class OrderStatusLog {
+order_id
+previous_status
+new_status
+updated_by_type
+logStatusChange(...)
}
class OrderTrackingLog {
+order_id
+status
+sub_status
+lat
+lng
+heading
+speed
}
class OrderNotificationService {
+notifyStatusChange(order) bool
+checkProximityNotification(order, lat, lng) bool
+buildExtendedPayload(order) array
}
OrderStatusService --> Order : "updates"
OrderStatusService --> OrderStatusLog : "logs"
OrderStatusService --> EstimatedDeliveryService : "recalculates"
OrderTrackingService --> Order : "reads"
OrderTrackingService --> OrderTrackingLog : "creates"
OrderTrackingService --> OrderNotificationService : "notifies"
OrderSecurityService --> Order : "stores fields"
```

**Diagram sources**
- [OrderStatusService.php:1-348](file://app/Services/OrderStatusService.php#L1-L348)
- [OrderTrackingService.php:1-124](file://app/Services/OrderTrackingService.php#L1-L124)
- [OrderSecurityService.php:1-137](file://app/Services/OrderSecurityService.php#L1-L137)
- [EstimatedDeliveryService.php:1-172](file://app/Services/EstimatedDeliveryService.php#L1-L172)
- [Order.php:1-358](file://app/Models/Order.php#L1-L358)
- [OrderStatusLog.php:1-112](file://app/Models/OrderStatusLog.php#L1-L112)
- [OrderTrackingLog.php:1-56](file://app/Models/OrderTrackingLog.php#L1-L56)
- [OrderNotificationService.php:1-312](file://app/Services/OrderNotificationService.php#L1-L312)

## Detailed Component Analysis

### OrderStatusService
- Purpose: Centralized order status management with validation, atomic updates, notifications, and audit trails.
- Key methods:
  - validateTransition: Checks if a new status follows a valid transition from the current status.
  - getAvailableTransitions: Returns allowed next statuses for a given order.
  - updateStatus: Performs atomic status update, handles special transitions (delivered, canceled, refunded), recalculates estimated delivery time, logs changes, and sends notifications.
  - verifyOTP: Rate-limited OTP verification using cache.
  - logStatusChange/getOrderTimeline: Auditing and timeline retrieval.

```mermaid
sequenceDiagram
participant Client as "Client"
participant Controller as "OrderController"
participant StatusSvc as "OrderStatusService"
participant EstSvc as "EstimatedDeliveryService"
participant DB as "Database"
Client->>Controller : "POST /api/v1/admin/order/update_status"
Controller->>StatusSvc : "updateStatus(order, newStatus, updatedBy, options)"
StatusSvc->>StatusSvc : "validateTransition()"
StatusSvc->>DB : "lockForUpdate()"
StatusSvc->>EstSvc : "recalculateOnStatusChange(order, newStatus)"
EstSvc-->>StatusSvc : "estimated_delivery_at"
StatusSvc->>DB : "save()"
StatusSvc->>DB : "logStatusChange()"
StatusSvc-->>Controller : "result"
Controller-->>Client : "JSON response"
```

**Diagram sources**
- [OrderStatusService.php:89-156](file://app/Services/OrderStatusService.php#L89-L156)
- [EstimatedDeliveryService.php:60-69](file://app/Services/EstimatedDeliveryService.php#L60-L69)
- [OrderController.php:1-791](file://app/Http/Controllers/Api/V1/OrderController.php#L1-L791)

**Section sources**
- [OrderStatusService.php:26-156](file://app/Services/OrderStatusService.php#L26-L156)
- [OrderStatusLog.php:71-90](file://app/Models/OrderStatusLog.php#L71-L90)

### OrderTrackingService
- Purpose: Real-time order tracking with location logging, proximity notifications, and history retrieval.
- Key methods:
  - logLocationUpdate: Records driver location with order status and sub-status, triggers proximity checks.
  - getTrackingHistory: Paginates recent tracking logs for an order.
  - getCurrentTrackingData: Builds current tracking payload including driver details.
  - updateSubStatus: Updates sub-status and optionally notifies.

```mermaid
sequenceDiagram
participant Driver as "Driver App"
participant Socket as "DMLocationSocketHandler"
participant Hist as "DeliveryHistory"
participant TrackSvc as "OrderTrackingService"
participant Notif as "OrderNotificationService"
Driver->>Socket : "WebSocket message {token, longitude, latitude, location}"
Socket->>Hist : "updateOrCreate(driver position)"
Socket-->>Driver : "acknowledgment"
TrackSvc->>TrackSvc : "logLocationUpdate(order, deliveryMan)"
TrackSvc->>Notif : "checkProximityNotification(order, lat, lng)"
Notif-->>TrackSvc : "optional sub_status update"
```

**Diagram sources**
- [DMLocationSocketHandler.php:19-43](file://app/WebSockets/Handler/DMLocationSocketHandler.php#L19-L43)
- [OrderTrackingService.php:28-50](file://app/Services/OrderTrackingService.php#L28-L50)
- [OrderNotificationService.php:252-283](file://app/Services/OrderNotificationService.php#L252-L283)

**Section sources**
- [OrderTrackingService.php:28-122](file://app/Services/OrderTrackingService.php#L28-L122)
- [OrderTrackingLog.php:43-54](file://app/Models/OrderTrackingLog.php#L43-L54)
- [OrderNotificationService.php:252-283](file://app/Services/OrderNotificationService.php#L252-L283)
- [DMLocationSocketHandler.php:19-43](file://app/WebSockets/Handler/DMLocationSocketHandler.php#L19-L43)

### OrderSecurityService
- Purpose: Fraud prevention and order integrity enforcement.
- Key methods:
  - checkIdempotency: Prevents duplicate orders using cache-based idempotency keys.
  - checkOrderCooldown: Enforces per-user cooldown to reduce spam.
  - verifySignature: Validates HMAC-SHA256 signatures and timestamps.
  - storeSecurityFields: Persists security-related fields on the order record.

```mermaid
flowchart TD
Start(["Incoming Order Request"]) --> IdempCheck["checkIdempotency(request)"]
IdempCheck --> Duplicate{"Duplicate Key?"}
Duplicate --> |Yes| Return409["Return 409 Conflict"]
Duplicate --> |No| Cooldown["checkOrderCooldown(request)"]
Cooldown --> TooSoon{"Within Cooldown?"}
TooSoon --> |Yes| Return429["Return 429 Too Many Requests"]
TooSoon --> |No| Signature["verifySignature(request)"]
Signature --> SignatureOK{"Signature Valid?"}
SignatureOK --> |No| LogWarn["Log warning (non-blocking)"]
SignatureOK --> |Yes| StoreFields["storeSecurityFields(order, request)"]
LogWarn --> StoreFields
StoreFields --> End(["Proceed to order placement"])
```

**Diagram sources**
- [OrderSecurityService.php:22-135](file://app/Services/OrderSecurityService.php#L22-L135)

**Section sources**
- [OrderSecurityService.php:22-135](file://app/Services/OrderSecurityService.php#L22-L135)

### EstimatedDeliveryService
- Purpose: Accurate delivery time estimation and recalculation during order lifecycle.
- Key methods:
  - calculateInitialEstimate: Computes initial ETA using schedule time, store processing time, distance, and buffer.
  - recalculateOnStatusChange: Recalculates ETA based on status transitions.
  - Helper methods: getProcessingMinutes, getStoreMinDeliveryTime, getTravelMinutes.

```mermaid
flowchart TD
Entry(["Status Change Detected"]) --> Match{"Match status"}
Match --> |confirmed| CalcConf["Recalculate from now<br/>processing + travel + buffer"]
Match --> |processing| CalcProc["Use processing_time or store min<br/>+ travel from now"]
Match --> |handover| CalcHand["Travel + small buffer"]
Match --> |picked_up| CalcPick["Only travel time"]
Match --> |default| NoRecalc["No recalculation"]
CalcConf --> SaveETA["Set estimated_delivery_at"]
CalcProc --> SaveETA
CalcHand --> SaveETA
CalcPick --> SaveETA
NoRecalc --> Exit(["Return null"])
SaveETA --> Exit
```

**Diagram sources**
- [EstimatedDeliveryService.php:60-113](file://app/Services/EstimatedDeliveryService.php#L60-L113)

**Section sources**
- [EstimatedDeliveryService.php:38-170](file://app/Services/EstimatedDeliveryService.php#L38-L170)
- [Order.php:48-50](file://app/Models/Order.php#L48-L50)

### Integration with WebSocket and Streaming
- WebSocket handler receives driver location updates, persists them, and acknowledges the client.
- Streaming endpoints provide real-time order tracking updates to clients via Server-Sent Events (SSE) with throttling and access control.

```mermaid
sequenceDiagram
participant Client as "Client App"
participant Stream as "OrderTrackingStreamController"
participant Order as "Order"
participant SSE as "SSE Transport"
Client->>Stream : "GET /api/v1/orders/{id}/stream"
loop Every 3 seconds
Stream->>Order : "Load order"
Stream->>Stream : "Build tracking data"
Stream-->>SSE : "Send event data"
alt Order completed
Stream-->>SSE : "completed event"
Stream-->>Client : "Close connection"
end
end
```

**Diagram sources**
- [OrderTrackingStreamController.php:80-154](file://app/Http/Controllers/Api/V1/OrderTrackingStreamController.php#L80-L154)
- [OrderTrackingHistoryController.php:20-60](file://app/Http/Controllers/Api/V1/OrderTrackingHistoryController.php#L20-L60)
- [Order.php:128-131](file://app/Models/Order.php#L128-L131)

**Section sources**
- [DMLocationSocketHandler.php:19-43](file://app/WebSockets/Handler/DMLocationSocketHandler.php#L19-L43)
- [OrderTrackingStreamController.php:80-154](file://app/Http/Controllers/Api/V1/OrderTrackingStreamController.php#L80-L154)
- [OrderTrackingHistoryController.php:20-60](file://app/Http/Controllers/Api/V1/OrderTrackingHistoryController.php#L20-L60)

## Dependency Analysis
- OrderStatusService depends on:
  - EstimatedDeliveryService for ETA recalculations.
  - OrderNotificationService indirectly via Helpers::send_order_notification.
  - OrderStatusLog for audit trails.
- OrderTrackingService depends on:
  - OrderTrackingLog for persistence.
  - OrderNotificationService for proximity and sub-status notifications.
- OrderSecurityService depends on:
  - Cache for idempotency and cooldown enforcement.
  - Configuration for HMAC secret and thresholds.
- EstimatedDeliveryService depends on:
  - Order model for distance and processing_time.
  - Store model for delivery_time parsing.

```mermaid
graph TB
OSS["OrderStatusService"] --> EDS["EstimatedDeliveryService"]
OSS --> OSL["OrderStatusLog"]
OTS["OrderTrackingService"] --> OTL["OrderTrackingLog"]
OTS --> ONS["OrderNotificationService"]
OSSec["OrderSecurityService"] --> Cache["Cache"]
EDS --> OM["Order"]
OM --> Store["Store"]
```

**Diagram sources**
- [OrderStatusService.php:10-12](file://app/Services/OrderStatusService.php#L10-L12)
- [EstimatedDeliveryService.php:5-7](file://app/Services/EstimatedDeliveryService.php#L5-L7)
- [OrderTrackingService.php:14-18](file://app/Services/OrderTrackingService.php#L14-L18)
- [OrderSecurityService.php:8-10](file://app/Services/OrderSecurityService.php#L8-L10)
- [Order.php:148-151](file://app/Models/Order.php#L148-L151)

**Section sources**
- [OrderStatusService.php:10-12](file://app/Services/OrderStatusService.php#L10-L12)
- [OrderTrackingService.php:14-18](file://app/Services/OrderTrackingService.php#L14-L18)
- [OrderSecurityService.php:8-10](file://app/Services/OrderSecurityService.php#L8-L10)
- [EstimatedDeliveryService.php:5-7](file://app/Services/EstimatedDeliveryService.php#L5-L7)

## Performance Considerations
- Atomic operations: OrderStatusService uses database transactions and row-level locking to prevent race conditions during status updates.
- Caching: OrderSecurityService leverages cache for idempotency and cooldown checks to minimize database load.
- Efficient queries: OrderTrackingService uses scopes for filtering and ordering tracking logs.
- ETA calculations: EstimatedDeliveryService uses simple arithmetic and minimal branching for fast recalculation.
- Streaming: OrderTrackingStreamController emits events at fixed intervals with throttling to balance responsiveness and resource usage.

[No sources needed since this section provides general guidance]

## Troubleshooting Guide
- Invalid status transition:
  - Symptom: Status update fails with an invalid transition message.
  - Cause: New status not permitted from current status according to configuration.
  - Resolution: Verify order status transitions in configuration and ensure the intended transition is allowed.

- OTP verification failures:
  - Symptom: OTP mismatch with remaining attempts displayed.
  - Cause: Incorrect OTP or exceeding maximum attempts within the decay window.
  - Resolution: Check OTP configuration limits and retry within the allowed timeframe.

- Duplicate order submission:
  - Symptom: 409 Conflict returned for idempotency key.
  - Cause: Same idempotency key submitted again.
  - Resolution: Ensure unique idempotency keys per order attempt.

- Too many requests:
  - Symptom: 429 Too Many Requests.
  - Cause: User placing orders within the cooldown period.
  - Resolution: Wait for the cooldown period to expire before placing another order.

- Tracking not updating:
  - Symptom: No tracking updates or proximity notifications.
  - Cause: Driver location not received via WebSocket or proximity threshold not met.
  - Resolution: Confirm WebSocket connectivity and driver location updates; verify proximity thresholds.

**Section sources**
- [OrderStatusService.php:94-98](file://app/Services/OrderStatusService.php#L94-L98)
- [OrderStatusService.php:275-308](file://app/Services/OrderStatusService.php#L275-L308)
- [OrderSecurityService.php:36-42](file://app/Services/OrderSecurityService.php#L36-L42)
- [OrderSecurityService.php:59-67](file://app/Services/OrderSecurityService.php#L59-L67)
- [OrderNotificationService.php:252-283](file://app/Services/OrderNotificationService.php#L252-L283)
- [DMLocationSocketHandler.php:19-43](file://app/WebSockets/Handler/DMLocationSocketHandler.php#L19-L43)

## Conclusion
The order management services provide a robust, secure, and real-time capable system for handling order lifecycles. OrderStatusService ensures controlled state transitions with auditing and notifications, OrderTrackingService enables precise location tracking and timely updates, OrderSecurityService protects against fraud and abuse, and EstimatedDeliveryService delivers accurate delivery expectations. Together with WebSocket and streaming integrations, the system supports responsive and reliable order experiences across all stakeholders.