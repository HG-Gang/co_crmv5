# Admin Gift Legacy Parity Design

## Scope

Close the six legacy `Admin\GiftController` routes without changing the modern Gift catalog boundary:

- `GET index/admin/gift/send_gift_browse`
- `POST index/admin/gift/send_gift`
- `POST index/admin/gift/addressList`
- `GET index/admin/gift/shipment_list_browse`
- `POST index/admin/gift/shipment_list`
- `POST index/admin/gift/shipment_list_export`

The old database and source tree remain read-only. Runtime reads and writes use the new project's real `user_infos`, `user_addresses`, `gift_shipments`, `admins`, and `operation_logs` tables. `gift_items` remains a modern read-only catalog concern and is not coupled to shipment stock deduction.

## Architecture

`Admin\GiftController` remains the only Gift business implementation. It owns validation, data-scope application, authoritative address lookup, transactions, shipment queries, CSV escaping, and operation logs. `LegacyAdminController` receives the six old URIs and performs only protocol adaptation: legacy field normalization, default dates, `rec_id` aliases, legacy list envelopes, mutation codes, and the two-stage export response.

The compatibility dispatcher marks forwarded subrequests with a server-only request attribute. The modern Gift controller uses that attribute only for the two old write semantics that differ from the modern endpoint: an empty tracking number is stored as string `0`, and status is always `1`. A client cannot enable this mode by sending a header or form field.

## Data And Permission Rules

Shipment lists and exports use the same query and filters. The `created` scope is evaluated against `gift_shipments.admin_id`; address lists and sends evaluate it against `user_infos.created_by`. Other scope types keep using `AdminDataScopeService` user and agent-tree behavior. Single-record shipment updates compare the shipment's `admin_id` through `canAccessRecord()`.

Sending a gift accepts address IDs, but recipient names, phone numbers, and addresses are always reloaded from `user_addresses`. The address must exist, belong to the submitted user, be the default address, and belong to a user whose `is_gift_allowed=1`. The whole recipient batch is validated before the transaction, so one invalid recipient produces zero shipment rows.

Legacy address results always include only default addresses for gift-enabled users, sorted by address update time descending. Legacy shipment filters default to `2024-01-01` through the current date and support `gift_name`, `recipient_name`, `user_id`, `startdate`, and `enddate`.

## Legacy Contracts

Legacy list success uses `code=0`, `msg`, `count`, `data`, and `totalRow`. Address rows expose `rec_id`; shipment rows expose `rec_id` and the old visible fields only. Legacy send accepts `giftInfo` plus `recipients[*].rec_id`, returns `code=0`, `data=[]`, and `message=寄送成功`; validation or transaction failure returns `code=5000` and no partial rows.

Legacy export first creates a UTF-8 BOM CSV under an administrator-specific storage directory and returns JSON `code=0`, `data.path`, and `message`. A permission-protected one-time GET route downloads that exact file and deletes it after sending. Empty export results return `code=5000`. Every text cell beginning with `=`, `+`, `-`, `@`, tab, CR, or LF is prefixed with an apostrophe.

## UI Contract

The Layui Gift Blade accepts `giftPageMode=all|send|shipments`. The modern `/admin/gifts` page keeps all three operational views. The old `send_gift_browse` link renders only the default-address selection and send workflow; `shipment_list_browse` renders only shipment search/export. JavaScript initializes only DOM elements present in the active mode, explicitly loads Layui `jquery`, disables send submission while pending, and reloads only tables present on the page.

CrmUI keeps three explicit routes: shipments, gift addresses/send, and gift items. The shipments page no longer renders a manual recipient form. The address/send page defaults its address filter to `is_default=1` and builds recipients only from selected DB rows. Existing table overflow wrappers, loading states, permission attributes, and responsive dialog dimensions remain in use.

## Error Handling And Verification

Invalid arrays, malformed IDs, invalid dates, reversed date ranges, missing addresses, non-default addresses, gift-disabled users, and out-of-scope records fail before writes. Modern endpoints retain modern response codes; only old routes receive old envelopes.

Verification consists of a failing-first Gift parity feature suite, created-scope tests, existing Gift regression, legacy route compatibility, permission/UI contracts, PHP 7.4 lint, JS syntax, and Blade cache/clear. Browser four-viewport verification remains `BLOCKED_BY_BROWSER_POLICY` unless the environment policy changes; static and PHPUnit evidence must not be reported as browser approval.

