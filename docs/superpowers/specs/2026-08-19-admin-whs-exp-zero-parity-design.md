# Admin WHS Exp Zero Parity Design

## Goal

Close the six remaining `AdminWhsExpZeroController` review items with real database behavior, exact legacy contracts, administrator scope enforcement, and consistent Layui/CrmUI pages.

## Scope

The batch covers five executable admin routes and the broken historical `whstest` route:

- `GET index/admin/order/whs_exp_zero_list`
- `POST index/admin/order/oneKeySearch`
- `POST index/admin/order/oneKeyZero`
- `POST index/admin/order/whsExpZeroListSearch`
- `POST index/admin/order/whsExpZeroListSearchV2`
- `GET whstest`

`GET trades_exp_zero` is already verified by the public-maintenance fail-closed evidence group and is not reopened.

## Source Of Truth

- Candidate users come from `user_infos`, restricted to ordinary customers with a negative `total_funds` balance.
- Open-position exclusion comes from `user_trades` with trade commands `0..5` and the legacy open close-time sentinels.
- Pending, processing, completed, and failed records come from `whs_exp_zeros`.
- Administrator visibility and mutations remain constrained by `AdminDataScopeService`.
- No mock rows, fallback business constants, Seeder, migration, or production database write is introduced.

## Legacy Contracts

`oneKeySearch` scans eligible users and creates one `status=1` pending record per eligible user. It returns the old `msg/err/col` fields together with the modern code/message/data fields. An empty scan returns an explicit old failure envelope instead of the old method's implicit null response.

`oneKeyZero` ignores caller-supplied username, balance, and credit snapshots and reloads current database truth. It can claim an existing pending record created by `oneKeySearch`, or create a record for an eligible direct action. It returns old `SUC/noerr/enable` or `FAIL/crtfail|zerofail/nocol` fields in addition to the modern response.

`whsExpZeroListSearch` queries `whs_exp_zeros`, maps `wez_userid`, `wez_username`, `wez_status`, `startdate`, and `enddate`, and returns V1 `rows/total`. `whsExpZeroListSearchV2` uses the same scoped query and returns `code=200`, `msg`, integer `count`, `data`, and `totalRow`. The old V2 array-valued `count` defect is intentionally corrected because Layui requires an integer total.

`whstest` retains its route and always returns HTTP 423 without executing the missing old action.

## Mutation Safety

`status=0` is the explicit processing state, `1` is pending, `2` completed, and `3` failed. Candidate discovery excludes both active states `0` and `1`.

The clear action locks the user and active record in a transaction, atomically changes or creates the record as `status=0`, then calls `DepositSettlementGateway` outside the transaction. A concurrent request sees the processing record and fails before calling the gateway. A settled result updates the record to `2`, the local balance mirror to zero, and writes an operation log. Any non-settled result updates the record to `3` and leaves the balance unchanged.

## UI And Permissions

The legacy page permission maps to `admin_api_whsExpZeroList`; old record searches map to `admin_api_whsExpZeroRecords`; scan and clear mutations require `admin_api_whsExpZero`. Layui exposes candidate and record tabs, and the record tab includes status and date filters. CrmUI keeps the same two data views and only exposes the clear action in the candidate view. Both UIs use the real APIs.

## Verification

Tests must cover exact methods/routes/actions, anonymous and ordinary-admin permission failures, old field aliases and V1/V2 envelopes, scoped record visibility, pending-record creation, duplicate prevention, pending-record clearing, gateway failure, dual UI contracts, `whstest` HTTP 423, PHP/JS syntax, Blade cache, and the regenerated route matrix.
