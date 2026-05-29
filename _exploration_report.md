# Project Exploration Report: co_crmv5

## Overview
This project is a Customer Relationship Management (CRM) system designed for a trading platform environment (likely Forex/MT4/MT5). It features a dual-interface system: an Admin panel for system management and a Front-end portal for agents and customers.

- **Framework**: Laravel
- **Authentication**: JWT-based with a custom Single Sign-On (SSO) implementation.
- **Database**: MySQL with complex hierarchy tracking.

---

## 1. File Structure & Purposes

### Route Files
- `routes/admin.php`: Defines API endpoints for the administration panel (User management, Agent levels, Commissions, System config, etc.).
- `routes/front.php`: Defines API endpoints for agents and customers (Dashboard, Profile, My Agents, Deposits/Withdrawals).
- `routes/web.php`: Serves as the entry point for the SPA (Single Page Application) by returning Blade views for Admin and Front interfaces. Handles language and UI style switching.

### Controllers (`app/Http/Controllers/`)
- **Admin/**: Contains 24 controllers managing various aspects of the system.
    - `AuthController`: Admin login/logout and password management.
    - `UserController` & `AgentController`: Manage the two main user types.
    - `CommissionController`: Handles calculation and settlement of agent earnings.
    - `RiskController`: Monitors positions and margin calls.
- **Front/**: Contains 12 controllers for the user-facing portal.
    - `AuthController`: Registration and login for agents/customers.
    - `AgentController` & `CustomerController`: Allow agents to view their network and stats.
    - `DepositController` & `WithdrawController`: Handle fund operations.
- **Common/**:
    - `LanguageController`: Handles locale switching.
    - `UploadController`: Shared file upload logic.

### Models (`app/Models/`)
- `Admin`: Represents system administrators.
- `UserLogin`: Central authentication record for front users (agents/customers).
- `UserInfo`: Extended profile data, including trading stats (equity, margin) and hierarchy data (`family_tree`).
- `Role` & `Permission`: RBAC (Role-Based Access Control) system.
- `AgentDescendant`: Closure-like table tracking all ancestors and descendants for reporting.
- `CommissionRecord`: Tracks earnings generated from trading activities.
- `IdSequence`: Manages custom ID generation for different user types.

### Middleware (`app/Http/Middleware/`)
- `JwtAuthMiddleware`: Extracts and validates JWT from Authorization header; identifies the correct guard (`admin` or `user`).
- `SingleSignOn`: Implements SSO by verifying the `jti` (JWT ID) against a cached "latest session" identifier.
- `CheckPermission`: Verifies if the authenticated user has the required permission slug.
- `SetLocale`: Handles application localization based on session or request.

### Services (`app/Services/`)
- `JwtService`: Core logic for generating, parsing, and invalidating JWTs.
- `UserRegistrationService`: Complex logic for creating new users, assigning IDs from sequences, building the `family_tree`, and updating the `agent_descendants` table.
- `CommissionService`: (Inferred) Logic for calculating commission based on rates and trading volume.

---

## 2. Key Logic Implementations

### Authentication & SSO
The system uses a custom JWT implementation instead of Laravel Passport/Sanctum for its core API.
- **SSO**: When a user logs in, a unique `jti` is generated and stored in Cache (`sso:{guard}:{id}`). The `SingleSignOn` middleware checks if the incoming token's `jti` matches the cached one. If not, the user is considered "logged in elsewhere" and the request is rejected.

### Hierarchy Management
- **Family Tree**: Stored as a comma-separated string in `user_infos.family_tree` (e.g., `1001,1002,1005`). This allows for quick path-based queries using `FIND_IN_SET` or `LIKE`.
- **Agent Descendants**: A dedicated table `agent_descendants` maps every agent to every one of their descendants (direct or indirect) with a `depth` and `is_direct` flag. This facilitates high-performance reporting for large agent networks.

### User Identification
- Agents receive IDs starting from **1001**.
- Customers (non-agents) receive IDs starting from **600001**.
- These are managed via the `id_sequences` table to ensure consistency across different registration sources.

---

## 3. Database Schema Highlights

### Core Tables
- `admins`: `username`, `password`, `role_id`, `jwt_token_id`.
- `user_logins`: `user_id` (business ID), `email`, `password`, `account_type` (1=Agent, 2=Customer).
- `user_infos`:
    - `family_tree`: Ancestor chain.
    - `equity`, `used_margin`, `avail_margin`: Financial state.
    - `comm_rate`: Individual commission percentage.
- `roles` & `permissions`: `permissions` column in `roles` stores a JSON array of slugs.
- `agent_descendants`: `agent_id`, `descendant_id`, `is_direct`, `depth`.

---

## 4. UI & Templates
- **Framework**: The project uses **LayUI** (a Chinese UI framework) for the blade-based layout.
- **Layouts**:
    - `admin.layouts.app`: Sidebar-based admin dashboard.
    - `front.layouts.app`: Horizontal-nav portal for users.
- **SPA Integration**: Both admin and front routes have a catch-all "SPA" route that serves the main layout, suggesting the actual content is driven by JavaScript (likely Vue or similar) or AJAX-loaded fragments.

---

## 5. Implementation Gaps & Observations
1. **SSO Persistence**: The `jwt_token_id` column exists in the database but the `JwtService` currently uses Redis/File Cache for SSO verification. Syncing this to the DB might be planned for persistence across cache clears.
2. **MT4 Integration**: Many fields exist for MT4 syncing (`is_mt4_synced`, `mt4_group`, etc.), but the direct connection logic to an MT4 server was not found in the primary service files, suggesting it might be handled by an external process or a yet-to-be-explored service.
3. **Commission Calculation**: While the models and routes are there, the complex logic for multi-level commission distribution (overriding, gap-based) is likely concentrated in `CommissionService` or handled via database procedures/triggers.

---
*Report generated on: 2026-03-30*
