# Routing and Middleware Integration

<cite>
**Referenced Files in This Document**
- [routes/web.php](file://routes/web.php)
- [routes/admin.php](file://routes/admin.php)
- [app/Http/Kernel.php](file://app/Http/Kernel.php)
- [Modules/PlacesToVisit/Routes/web.php](file://Modules/PlacesToVisit/Routes/web.php)
- [Modules/PlacesToVisit/Routes/api/v1/api.php](file://Modules/PlacesToVisit/Routes/api/v1/api.php)
- [Modules/TaxModule/Routes/web.php](file://Modules/TaxModule/Routes/web.php)
- [Modules/TaxModule/Routes/api/v1/api.php](file://Modules/TaxModule/Routes/api/v1/api.php)
- [Modules/PlacesToVisit/Providers/RouteServiceProvider.php](file://Modules/PlacesToVisit/Providers/RouteServiceProvider.php)
- [Modules/TaxModule/Providers/RouteServiceProvider.php](file://Modules/TaxModule/Providers/RouteServiceProvider.php)
- [Modules/PlacesToVisit/Providers/PlacesToVisitServiceProvider.php](file://Modules/PlacesToVisit/Providers/PlacesToVisitServiceProvider.php)
- [Modules/TaxModule/Providers/TaxVatServiceProvider.php](file://Modules/TaxModule/Providers/TaxVatServiceProvider.php)
- [app/Providers/RouteServiceProvider.php](file://app/Providers/RouteServiceProvider.php)
- [app/Http/Middleware/ModulePermissionMiddleware.php](file://app/Http/Middleware/ModulePermissionMiddleware.php)
- [app/Http/Middleware/CurrentModule.php](file://app/Http/Middleware/CurrentModule.php)
- [app/Http/Middleware/AdminMiddleware.php](file://app/Http/Middleware/AdminMiddleware.php)
- [app/Http/Middleware/VendorMiddleware.php](file://app/Http/Middleware/VendorMiddleware.php)
- [config/modules.php](file://config/modules.php)
- [Modules/PlacesToVisit/module.json](file://Modules/PlacesToVisit/module.json)
- [Modules/TaxModule/module.json](file://Modules/TaxModule/module.json)
</cite>

## Update Summary
**Changes Made**
- Added comprehensive documentation for the new PlacesToVisit RouteServiceProvider implementation as a best practice example
- Updated module routing architecture patterns to reflect the new service provider-based approach
- Enhanced documentation with detailed analysis of module-specific route service providers
- Added new sections covering module routing service providers and their benefits
- Updated architectural diagrams to show the enhanced routing structure

## Table of Contents
1. [Introduction](#introduction)
2. [Project Structure](#project-structure)
3. [Core Components](#core-components)
4. [Architecture Overview](#architecture-overview)
5. [Enhanced Module Routing Patterns](#enhanced-module-routing-patterns)
6. [Detailed Component Analysis](#detailed-component-analysis)
7. [Dependency Analysis](#dependency-analysis)
8. [Performance Considerations](#performance-considerations)
9. [Troubleshooting Guide](#troubleshooting-guide)
10. [Conclusion](#conclusion)

## Introduction
This document explains how routing and middleware are integrated across the application and modules. It covers:
- How modules define their own route service providers for web and API endpoints
- Route registration, URL generation, and parameter binding within modules
- Middleware integration for authentication, authorization, and cross-cutting concerns
- Examples of admin routes, API versioning, and route model binding
- Route caching, performance considerations, and conflict resolution between modules
- Best practices demonstrated by the PlacesToVisit module's RouteServiceProvider implementation

## Project Structure
The routing system is organized around:
- Global web routes for public and payment integrations
- Admin routes grouped under a dedicated admin route file
- Module-specific route service providers that encapsulate routing logic
- Module-specific route files under each module's Routes directory
- HTTP kernel configured globally and per-route via the HTTP kernel

```mermaid
graph TB
subgraph "Global Routes"
GW["routes/web.php"]
GA["routes/admin.php"]
GRSP["app/Providers/RouteServiceProvider.php"]
end
subgraph "Module Route Service Providers"
MPTV_RouteSP["Modules/PlacesToVisit/Providers/RouteServiceProvider.php"]
MTAX_RouteSP["Modules/TaxModule/Providers/RouteServiceProvider.php"]
end
subgraph "Module Routes"
MPTV_WEB["Modules/PlacesToVisit/Routes/web.php"]
MPTV_API["Modules/PlacesToVisit/Routes/api/v1/api.php"]
MTAX_WEB["Modules/TaxModule/Routes/web.php"]
MTAX_API["Modules/TaxModule/Routes/api/v1/api.php"]
end
subgraph "HTTP Kernel"
HK["app/Http/Kernel.php"]
end
subgraph "Middleware"
AMW["AdminMiddleware"]
VMW["VendorMiddleware"]
CMP["ModulePermissionMiddleware"]
CMU["CurrentModule"]
end
GW --> HK
GA --> HK
GRSP --> MPTV_RouteSP
GRSP --> MTAX_RouteSP
MPTV_RouteSP --> MPTV_WEB
MPTV_RouteSP --> MPTV_API
MTAX_RouteSP --> MTAX_WEB
MTAX_RouteSP --> MTAX_API
HK --> AMW
HK --> VMW
HK --> CMP
HK --> CMU
```

**Diagram sources**
- [app/Providers/RouteServiceProvider.php:50-82](file://app/Providers/RouteServiceProvider.php#L50-L82)
- [Modules/PlacesToVisit/Providers/RouteServiceProvider.php:17-36](file://Modules/PlacesToVisit/Providers/RouteServiceProvider.php#L17-L36)
- [Modules/TaxModule/Providers/RouteServiceProvider.php:34-68](file://Modules/TaxModule/Providers/RouteServiceProvider.php#L34-L68)
- [Modules/PlacesToVisit/Routes/web.php:11-91](file://Modules/PlacesToVisit/Routes/web.php#L11-L91)
- [Modules/PlacesToVisit/Routes/api/v1/api.php:11-50](file://Modules/PlacesToVisit/Routes/api/v1/api.php#L11-L50)
- [Modules/TaxModule/Routes/web.php:16-26](file://Modules/TaxModule/Routes/web.php#L16-L26)
- [Modules/TaxModule/Routes/api/v1/api.php:18-21](file://Modules/TaxModule/Routes/api/v1/api.php#L18-L21)

**Section sources**
- [routes/web.php:1-260](file://routes/web.php#L1-L260)
- [routes/admin.php:1-827](file://routes/admin.php#L1-L827)
- [app/Providers/RouteServiceProvider.php:1-105](file://app/Providers/RouteServiceProvider.php#L1-L105)

## Core Components
- Global web routes: Define public pages, authentication, payments, and admin API endpoints.
- Admin routes: Centralized admin navigation and module-scoped admin routes.
- Module route service providers: Encapsulate routing logic with namespace management and route mapping.
- Module route files: Each module registers its own web and API routes with module-aware prefixes and middleware.
- HTTP kernel: Declares middleware groups and individual middleware aliases.
- Middleware: Enforce authentication, authorization, current module selection, and module permissions.

**Section sources**
- [routes/web.php:1-260](file://routes/web.php#L1-L260)
- [routes/admin.php:1-827](file://routes/admin.php#L1-L827)
- [Modules/PlacesToVisit/Routes/web.php:1-92](file://Modules/PlacesToVisit/Routes/web.php#L1-L92)
- [Modules/TaxModule/Routes/web.php:1-27](file://Modules/TaxModule/Routes/web.php#L1-L27)
- [app/Http/Kernel.php:1-88](file://app/Http/Kernel.php#L1-L88)

## Architecture Overview
The routing architecture combines global routes, admin routes, and module routes through service providers. Middleware ensures proper authentication and authorization at each layer. Modules encapsulate their admin UI and API endpoints under consistent namespaces and prefixes.

```mermaid
sequenceDiagram
participant Browser as "Browser"
participant GlobalRouteSP as "Global RouteServiceProvider"
participant ModuleRouteSP as "Module RouteServiceProvider"
participant MW_Admin as "AdminMiddleware"
participant MW_Module as "ModulePermissionMiddleware"
participant MW_Current as "CurrentModule"
participant ModuleRoutes as "Module Routes"
participant Controller as "Controller"
Browser->>GlobalRouteSP : HTTP Request
GlobalRouteSP->>MW_Admin : Apply admin middleware
MW_Admin-->>GlobalRouteSP : Authorized or redirect
GlobalRouteSP->>MW_Current : Resolve current module
MW_Current-->>GlobalRouteSP : Set module context
GlobalRouteSP->>MW_Module : Apply module permission (optional)
MW_Module-->>GlobalRouteSP : Allowed or deny
GlobalRouteSP->>ModuleRouteSP : Delegate to module routes
ModuleRouteSP->>ModuleRoutes : Match route within module scope
ModuleRoutes->>Controller : Dispatch to controller action
Controller-->>Browser : Response
```

**Diagram sources**
- [app/Providers/RouteServiceProvider.php:46-82](file://app/Providers/RouteServiceProvider.php#L46-L82)
- [Modules/PlacesToVisit/Providers/RouteServiceProvider.php:12-36](file://Modules/PlacesToVisit/Providers/RouteServiceProvider.php#L12-L36)
- [app/Http/Middleware/AdminMiddleware.php:20-45](file://app/Http/Middleware/AdminMiddleware.php#L20-L45)
- [app/Http/Middleware/CurrentModule.php:20-58](file://app/Http/Middleware/CurrentModule.php#L20-L58)
- [app/Http/Middleware/ModulePermissionMiddleware.php:18-32](file://app/Http/Middleware/ModulePermissionMiddleware.php#L18-L32)

## Enhanced Module Routing Patterns

### Module Route Service Providers
Both PlacesToVisit and TaxModule implement dedicated RouteServiceProvider classes that provide structured routing patterns:

**PlacesToVisit RouteServiceProvider Features:**
- Defines module namespace for controller resolution
- Implements map() method to register both web and API routes
- Uses module_path() helper for dynamic route file loading
- Supports both web and API route registration with proper middleware assignment

**TaxModule RouteServiceProvider Features:**
- Similar pattern with enhanced documentation and comments
- Demonstrates consistent API route prefixing with versioning
- Provides clear separation between web and API route registration

**Benefits of Service Provider Pattern:**
- Encapsulates routing logic within module boundaries
- Enables dynamic route file loading using module_path() helper
- Provides consistent namespace management across controllers
- Supports modular architecture with clear separation of concerns

**Section sources**
- [Modules/PlacesToVisit/Providers/RouteServiceProvider.php:1-38](file://Modules/PlacesToVisit/Providers/RouteServiceProvider.php#L1-L38)
- [Modules/TaxModule/Providers/RouteServiceProvider.php:1-70](file://Modules/TaxModule/Providers/RouteServiceProvider.php#L1-L70)

### Module Service Provider Registration
Each module's main service provider registers the route service provider:

**PlacesToVisitServiceProvider:**
- Registers RouteServiceProvider in the container
- Manages service bindings for leaderboard, trending, and voting services
- Handles configuration, view, and translation publishing

**TaxVatServiceProvider:**
- Registers RouteServiceProvider for module routing
- Manages configuration and view registration
- Handles database migration loading

**Section sources**
- [Modules/PlacesToVisit/Providers/PlacesToVisitServiceProvider.php:15-30](file://Modules/PlacesToVisit/Providers/PlacesToVisitServiceProvider.php#L15-L30)
- [Modules/TaxModule/Providers/TaxVatServiceProvider.php:38-41](file://Modules/TaxModule/Providers/TaxVatServiceProvider.php#L38-L41)

## Detailed Component Analysis

### Global Web Routes
- Public pages and authentication endpoints are registered here.
- Payment provider routes are grouped under prefixes and sometimes exclude CSRF verification for third-party callbacks.
- Admin API endpoints for order tracking are defined with throttling middleware.

Key observations:
- Route registration uses closures and controller method arrays.
- Parameter binding occurs implicitly via route model binding in controllers.
- Throttling middleware is applied to streaming endpoints.

**Section sources**
- [routes/web.php:32-260](file://routes/web.php#L32-L260)

### Admin Routes
- Admin routes are grouped under an admin namespace and prefixed route groups.
- Middleware stacks include admin authentication, current module detection, and activation checks.
- Module-scoped routes are guarded by a module middleware that checks permissions for specific modules.

Examples:
- Orders, stores, campaigns, flash sales, and promotional banners are grouped under module-specific middleware.
- URLs are generated with the admin namespace and module-specific suffixes.

**Section sources**
- [routes/admin.php:6-827](file://routes/admin.php#L6-L827)

### Module Routes: PlacesToVisit
- Module routes are prefixed under admin and scoped to the module namespace.
- Uses admin and current-module middleware to ensure access and context.
- Groups cover categories, zones, places, leaderboard, banners, offers, and submissions.

URL generation:
- Namespaced routes allow consistent URL generation using the admin.places.* pattern.

**Section sources**
- [Modules/PlacesToVisit/Routes/web.php:11-91](file://Modules/PlacesToVisit/Routes/web.php#L11-L91)

### Module Routes: TaxModule
- Module routes are prefixed under taxvat and namespaced similarly.
- Includes CRUD endpoints for tax/vat data and system-wide settings.

**Section sources**
- [Modules/TaxModule/Routes/web.php:16-26](file://Modules/TaxModule/Routes/web.php#L16-L26)

### API Routes Implementation
Both modules implement comprehensive API routing patterns:

**PlacesToVisit API Routes:**
- Public routes for categories, places, leaderboard, and trending data
- Protected routes requiring API authentication for voting, favorites, and submissions
- Clear separation between public and authenticated endpoints
- RESTful resource patterns with proper HTTP methods

**TaxModule API Routes:**
- Simple CRUD operations for tax/vat data retrieval and calculation
- Direct controller method references for API endpoints
- Consistent naming conventions with module-specific prefixes

**Section sources**
- [Modules/PlacesToVisit/Routes/api/v1/api.php:11-50](file://Modules/PlacesToVisit/Routes/api/v1/api.php#L11-L50)
- [Modules/TaxModule/Routes/api/v1/api.php:18-21](file://Modules/TaxModule/Routes/api/v1/api.php#L18-L21)

### Middleware Integration
- Authentication middleware: AdminMiddleware and VendorMiddleware enforce session validity and status checks.
- Authorization middleware: ModulePermissionMiddleware validates module access for both admins and vendors/employees.
- Context middleware: CurrentModule selects the active module and sets configuration for module-aware logic.

```mermaid
flowchart TD
Start(["Incoming Request"]) --> CheckAdmin["AdminMiddleware"]
CheckAdmin --> AdminOK{"Admin logged in<br/>and session valid?"}
AdminOK --> |No| RedirectHome["Redirect to home"]
AdminOK --> |Yes| SetContext["CurrentModule"]
SetContext --> ModulePerm["ModulePermissionMiddleware (optional)"]
ModulePerm --> PermOK{"Allowed by module permission?"}
PermOK --> |No| Deny["Deny access"]
PermOK --> |Yes| Dispatch["Dispatch to controller"]
RedirectHome --> End(["End"])
Deny --> End
Dispatch --> End
```

**Diagram sources**
- [app/Http/Middleware/AdminMiddleware.php:20-45](file://app/Http/Middleware/AdminMiddleware.php#L20-L45)
- [app/Http/Middleware/CurrentModule.php:20-58](file://app/Http/Middleware/CurrentModule.php#L20-L58)
- [app/Http/Middleware/ModulePermissionMiddleware.php:18-32](file://app/Http/Middleware/ModulePermissionMiddleware.php#L18-L32)

**Section sources**
- [app/Http/Middleware/AdminMiddleware.php:1-47](file://app/Http/Middleware/AdminMiddleware.php#L1-L47)
- [app/Http/Middleware/VendorMiddleware.php:1-60](file://app/Http/Middleware/VendorMiddleware.php#L1-L60)
- [app/Http/Middleware/ModulePermissionMiddleware.php:1-34](file://app/Http/Middleware/ModulePermissionMiddleware.php#L1-L34)
- [app/Http/Middleware/CurrentModule.php:1-61](file://app/Http/Middleware/CurrentModule.php#L1-L61)

### Route Model Binding
- Implicit binding is used in module routes where route parameters match controller method parameters.
- Example: Routes with placeholders like {place}, {category}, {zone}, and {taxVat} bind to controller method parameters automatically.

**Section sources**
- [Modules/PlacesToVisit/Routes/web.php:40-80](file://Modules/PlacesToVisit/Routes/web.php#L40-L80)
- [Modules/TaxModule/Routes/web.php:17-25](file://Modules/TaxModule/Routes/web.php#L17-L25)

### URL Generation and Naming Conventions
- Routes are named consistently within modules (e.g., admin.places.*, taxvat.*).
- Prefixes and namespaces ensure unique route names and predictable URL patterns.

**Section sources**
- [Modules/PlacesToVisit/Routes/web.php:11-91](file://Modules/PlacesToVisit/Routes/web.php#L11-L91)
- [Modules/TaxModule/Routes/web.php:16-26](file://Modules/TaxModule/Routes/web.php#L16-L26)

### API Versioning
- API routes are versioned under separate directories (e.g., api/v1, api/v2).
- Global web routes also include admin API endpoints for order tracking with throttling.

**Section sources**
- [routes/web.php:248-258](file://routes/web.php#L248-L258)

### Admin Routes and Module Permissions
- Admin routes apply middleware stacks including admin authentication and module permission checks.
- Module-scoped routes are gated by module middleware to restrict access to authorized users.

**Section sources**
- [routes/admin.php:8-496](file://routes/admin.php#L8-L496)
- [app/Http/Middleware/ModulePermissionMiddleware.php:18-32](file://app/Http/Middleware/ModulePermissionMiddleware.php#L18-L32)

## Dependency Analysis
The routing system depends on:
- Global RouteServiceProvider for middleware registration and grouping
- Module route service providers for encapsulating module-specific endpoints
- HTTP kernel for enforcing authentication, authorization, and module context
- Module route files for defining endpoint specifications

```mermaid
graph LR
GlobalRouteSP["Global RouteServiceProvider"] --> MW_Admin["AdminMiddleware"]
GlobalRouteSP --> MW_Vendor["VendorMiddleware"]
GlobalRouteSP --> MW_Module["ModulePermissionMiddleware"]
GlobalRouteSP --> MW_Current["CurrentModule"]
MW_Admin --> Routes_Admin["Admin Routes"]
MW_Current --> ModuleRouteSP["Module Route Service Providers"]
MW_Module --> ModuleRouteSP
ModuleRouteSP --> Routes_Modules["Module Routes"]
Routes_Admin --> Controllers["Controllers"]
Routes_Modules --> Controllers
```

**Diagram sources**
- [app/Providers/RouteServiceProvider.php:46-82](file://app/Providers/RouteServiceProvider.php#L46-L82)
- [app/Http/Middleware/AdminMiddleware.php:20-45](file://app/Http/Middleware/AdminMiddleware.php#L20-L45)
- [app/Http/Middleware/VendorMiddleware.php:19-57](file://app/Http/Middleware/VendorMiddleware.php#L19-L57)
- [app/Http/Middleware/ModulePermissionMiddleware.php:18-32](file://app/Http/Middleware/ModulePermissionMiddleware.php#L18-L32)
- [app/Http/Middleware/CurrentModule.php:20-58](file://app/Http/Middleware/CurrentModule.php#L20-L58)
- [routes/admin.php:6-827](file://routes/admin.php#L6-L827)
- [Modules/PlacesToVisit/Providers/RouteServiceProvider.php:17-36](file://Modules/PlacesToVisit/Providers/RouteServiceProvider.php#L17-L36)

**Section sources**
- [app/Providers/RouteServiceProvider.php:1-105](file://app/Providers/RouteServiceProvider.php#L1-L105)
- [routes/admin.php:6-827](file://routes/admin.php#L6-L827)
- [Modules/PlacesToVisit/Providers/RouteServiceProvider.php:1-38](file://Modules/PlacesToVisit/Providers/RouteServiceProvider.php#L1-L38)

## Performance Considerations
- Route caching: Laravel supports route caching to reduce bootstrapping overhead. Enable route caching in production environments to improve performance.
- Middleware ordering: Keep heavy middleware (e.g., module permission checks) after early authentication checks to minimize unnecessary work.
- Throttling: Use throttle middleware on endpoints that are sensitive to abuse (e.g., order tracking streaming).
- Static assets: Serve static assets efficiently and leverage browser caching.
- Module routing optimization: Service providers enable lazy loading of module routes only when needed.

## Troubleshooting Guide
Common issues and resolutions:
- Access denied errors: Ensure the user has the appropriate module permissions. The module permission middleware denies access when the user lacks permission.
- Session expiration: Admin and vendor middleware log out users with invalid or expired sessions and redirect them to login.
- Incorrect module context: The current module middleware sets module context based on query parameters or session; verify module_id and current module type.
- CSRF failures for third-party callbacks: Some payment routes exclude CSRF verification for external callbacks; confirm the route excludes CSRF middleware as intended.
- Module route loading issues: Verify that module service providers are properly registered in module.json configuration files.
- Route service provider conflicts: Ensure each module has a unique RouteServiceProvider implementation and proper namespace configuration.

**Section sources**
- [app/Http/Middleware/ModulePermissionMiddleware.php:18-32](file://app/Http/Middleware/ModulePermissionMiddleware.php#L18-L32)
- [app/Http/Middleware/AdminMiddleware.php:20-45](file://app/Http/Middleware/AdminMiddleware.php#L20-L45)
- [app/Http/Middleware/VendorMiddleware.php:19-57](file://app/Http/Middleware/VendorMiddleware.php#L19-L57)
- [app/Http/Middleware/CurrentModule.php:20-58](file://app/Http/Middleware/CurrentModule.php#L20-L58)
- [routes/web.php:88-196](file://routes/web.php#L88-L196)

## Conclusion
The routing and middleware integration pattern leverages:
- Clear separation of global, admin, and module routes through service providers
- Strong middleware enforcement for authentication, authorization, and module context
- Consistent naming and prefixing for predictable URL generation
- Practical examples of admin routes, API versioning, and route model binding
- Best practices demonstrated by the PlacesToVisit module's RouteServiceProvider implementation
- Modular architecture enabling scalable and maintainable routing patterns

This design enables scalable module development while maintaining centralized control over access and behavior, with the PlacesToVisit module serving as a comprehensive example of Laravel module routing best practices.