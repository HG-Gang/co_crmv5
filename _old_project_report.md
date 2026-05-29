# Legacy CRM Project Audit Report: new_co_gmtk_crmV3

## 1. Project Overview
The legacy CRM system (**new_co_gmtk_crmV3**) is built using the **Laravel** framework (likely version 5.x based on the structure and routes). It serves as a comprehensive management platform for a trading brokerage, handling user registrations, agent hierarchies, real-time commission calculations, and financial auditing.

### Core Technologies
- **Backend:** PHP (Laravel)
- **Database:** Likely MySQL or MariaDB (using Eloquent models)
- **Integration:** Directly interacts with **MT4 (MetaTrader 4)** database tables (`MT4_USERS`, `MT4_TRADES`).
- **Frontend:** Laravel Blade templates, jQuery, custom JS libraries (`form.core.js`, `formevent2.js`).

---

## 2. Architecture & System Structure

### Directory Layout
- `app/Http/Controllers/`: Divided into `Admin/` and `User/` namespaces.
- `app/Model/`: Contains Eloquent models mapping to both local CRM tables and MT4 tables.
- `app/Http/Middleware/`: Custom logic for registration flows and permission checking.
- `app/Http/routes.php` & `app/Http/routes-admin.php`: Split routing for separation of concerns.

### Database Models (Key)
- `Agents.php`: Manages agent profiles, hierarchies, and status.
- `User.php`: Manages direct customer accounts.
- `Mt4Trades.php`: Direct interface with MT4 trade history for commission and P/L calculations.
- `Mt4Users.php`: Syncs/interacts with MT4 user accounts.
- `Role.php` & `AdRole.php`: Implements a custom ACL system for admin users.

---

## 3. Registration Workflow

### Invite Code Mechanism
The system relies heavily on an invitation-based registration system.
- **Middleware Logic:** `RegisterMiddleware.php` and `RegisterEnMiddleware.php` parse URL parameters (`user_id`, `register_type`, `comm_type`).
- **Validation:** Checks if the parent `user_id` exists and if they have the authority to invite new agents or users.
- **Role Assignment:** Automatically assigns `group_id` (agent level) and `mt4_grp` (trading group) based on the invitation parameters.

### `family_tree` Creation
During registration (`RegisterController.php`), the system builds a hierarchical relationship:
- Every new user/agent is assigned a `parent_id`.
- The `family_tree` is often calculated or stored to track multi-level commission structures (up to 7 levels as indicated in some controller logic).
- **MT4 Sync:** Upon successful registration in the CRM, an account is typically created in MT4 with corresponding group settings.

---

## 4. Commission Logic

### Calculation Principles
Commission is calculated in real-time or via summary tables by auditing the `MT4_TRADES` table.
- **Comment-Based Logic:** The system uses specific string suffixes in MT4 trade comments to identify transaction types:
    - `-ZH` / `DBCT`: Commission transfer.
    - `-CZ` / `DBUN`: Deposit/Recharge.
    - `-QK` / `WBIN`: Withdrawal.
    - `-FY`: Rebate/Commission (Rebate).
- **Position Summary:** `PositionSummaryController` aggregates closed trades and applies commission rates based on the agent's tier and the symbol traded (Gold, Forex, etc.).

### Settlement Models
- **Standard Rebate:** Based on volume (lots).
- **Custom Spreads:** Derived from `SymbolSpread` and `SymbolPrices` models.

---

## 5. Admin Panel & Security

### ACL (Access Control List)
- Admin permissions are stored in the `roles` table under the `acl` column as a JSON string.
- `LoginController.php` loads these permissions into the session.
- `AdministratorsController.php` allows super-admins to manage these roles and assign them to various administrative staff.

### Financial Auditing
- **Withdrawal Management:** `WithdrawAmountController.php` handles the approval workflow for user withdrawals.
- **Deposit Tracking:** `DepositRecordLog` and `Mt4Trades` are joined to verify incoming funds.
- **Real-time Monitoring:** Admin tools provide a view into active positions and recent commission generations.

---

## 6. Frontend Assets
- `public/js/register_gmtk.js`: Handles complex client-side validation and dynamic field population for registration forms.
- `public/js/form.core.js`: A core library for handling AJAX submissions and UI feedback across the CRM.
- **Multi-language Support:** Detected through `_lang_id` variables in middleware and separate registration views (`register.blade.php` vs `register_en.blade.php`).

---

## 7. Key Findings & Legacy Challenges
- **Route Inclusion:** The project uses `include_once` in `routes.php` to load `routes-admin.php`, which is a legacy Laravel approach but effective for separating panels.
- **Direct MT4 Interaction:** The heavy reliance on reading/writing directly to MT4 tables requires the CRM to have high-privilege access to the MT4 database.
- **Custom Hierarchy:** The `family_tree` logic is tightly coupled with `Agents` and `User` models, making any changes to the hierarchy logic potentially complex.
