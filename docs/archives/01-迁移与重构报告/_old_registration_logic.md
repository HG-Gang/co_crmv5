# Legacy Registration Logic Analysis (GMTK CRM v3)

This document details the analysis of the registration and agent hierarchy logic from the legacy GMTK CRM (v3) to ensure compatibility in the new v5 system.

## 1. Registration Flow

### 1.1 Frontend Validation
- **Files:** 
    - `public/js/plugins/layui/layadmin/modul/login/register_gmtk.js`
    - `public/js/formevent/form.core.js`
    - `public/js/formevent/formevent2.js`
- **Logic:** 
    - Uses Layui for form layout and validation.
    - Performs basic checks: username, email format, phone number, and password complexity (starts with letter, ends with number, > 5 chars).
    - Captcha verification before submission.
    - Submits via AJAX to `/user/register/registerinto`.

### 1.2 Backend Logic (`RegisterController@registerinto`)
- **Validation:** 
    - Checks if email, phone, and ID card already exist in `DataList` (unified table for users and agents).
    - Validates invitation code if applicable.
- **Hierarchy & Commission:**
    - Identifies `parent_id` from the invitation link/code.
    - Sets `comm_prop` (commission proportion) based on the parent's settings.
    - Assigns `mt4_grp` (MT4 group) which dictates the trading environment and commission structure.
- **Data Persistence:**
    - Inserts into `user` or `agents` table.
    - Updates `DataList` for global lookup.
- **MT4 Synchronization:**
    - Calls `_exte_sync_mt4_register` (inherited from `Abstract_Service_Controller`).

## 2. MT4 Integration (`Abstract_Service_Controller`)

### 2.1 Communication Protocol
- **Method:** Raw TCP Socket.
- **Address/Port:** Configured in `.env` (typically port 3490).
- **Packet Format:**
    - `act=register`: Action code for registration.
    - `ver=000005`: Version string.
    - Fields: `nam` (Name, GBK encoded), `ctp` (Password), `eml` (Email), `tel` (Phone), `idn` (ID Card), `zip` (Parent ID), `grp` (Group).
- **Process:** Opens socket -> Sends packet -> Reads response -> Parses result (success/fail).

## 3. Agent Hierarchy & Commissions

### 3.1 Structure
- Uses a `parent_id` based adjacency list.
- A `family_tree` is calculated dynamically (e.g., `getFamilyTreeByUserIdV2`) to determine the full path from the root.
- Depth is usually limited (e.g., 4 levels as seen in some search queries).

### 3.2 Commission Assignment
- When a new user/agent registers, their `comm_prop` is inherited or derived from their parent.
- Group-based commissions: The `mt4_grp` determines the base commission rate, which is then multiplied by the user's `comm_prop`.

## 4. Key Models & Database Tables
- `agents`: Stores agent-specific data (name, money, parent, commission).
- `user`: Stores regular customer data.
- `DataList`: A view or table for unified user/agent lookup.
- `MT4_USERS` / `MT4_TRADES`: Shadow tables containing synced data from the MT4 server.
- `AdRole` / `Role`: Admin user and permission management.

## 5. Implementation Requirements for v5
1.  **Exact Replication of Password Hashing:** Must use `password_hash($password, PASSWORD_DEFAULT)` to maintain compatibility with legacy logins.
2.  **MT4 Socket Layer:** The new system must implement the same socket packet structure for registration and other real-time MT4 commands.
3.  **Hierarchy Migration:** Ensure `parent_id` relationships are preserved.
4.  **Commission Logic:** Replicate the logic for assigning `comm_prop` and `mt4_grp` during registration to prevent commission leakage or incorrect assignments.
