# 后台代理商上级迁移与整棵子树闭环设计

## 目标

恢复旧项目 `AgentControllerV3::agents_edit_save` 修改 `userparentId` 的业务能力，并修复旧实现只更新当前代理 MT4 关系码、没有同步整棵下级子树的问题。新项目通过代理专用接口完成管理员权限、代理等级、循环检测、整棵子树 MT4 关系码、本地 `parent_id/family_tree/agent_descendants` 和操作日志的一致迁移。

## 方案选择

1. 扩展普通用户资料接口：改动少，但会混淆普通客户与代理商权限，无法清晰表达整棵子树副作用，不采用。
2. 直接调用现有 `FamilyTreeService::reassignParent`：只能完成本地树重建，MT4 子树关系码仍会残留旧祖先，不采用。
3. 新增代理专用迁移接口：控制器编排权限、远端同步、补偿和事务，层级服务只负责确定性快照与本地重建。职责清晰且可独立测试，采用此方案。

## 路由与权限边界

- 资源路由：`PATCH /api/admin/agents/{agent}/parent`。
- 旧页面兼容路由：`POST /api/admin/updateAgentParent`，接收 `agent_id/userId` 与 `parent_id/userparentId`。
- 路由名统一为 `admin_api_updateAgentParent`，继续经过 `jwt.auth:admin`、`sso:admin` 和 `check.permission:admin`。
- 当前管理员必须同时能访问被移动代理和非零目标代理；平台根 `parent_id=0` 不需要目标对象权限。
- 只允许 `account_type=1` 的代理商进入该接口；普通客户继续使用 `AdminUserController::updateUser` 的客户专用分支。

## 层级与等级规则

- 目标上级为 `0` 时表示平台根。
- 非零目标必须存在且 `account_type=1`。
- 目标不能是自己，也不能位于被移动代理的子树中，否则会形成循环。
- 非零目标代理的 `agent_levels.level_code` 必须小于被移动代理的等级编码，保持旧项目“上级等级高于下级”的规则；等级不存在或编码无效时失败关闭。
- 子树通过真实 `user_infos.parent_id` 广度遍历，不依赖可能过期的 `family_tree`。

## MT4 与本地一致性链路

1. 层级服务读取当前树并生成不可变快照：子树顺序、每个账号当前直属上级、旧关系码、迁移后的目标直属上级和新关系码。
2. 控制器按父节点到子节点顺序调用 `Mt4ManagerService::updateUserHierarchy`。根代理更新 `zip`，所有子树账号更新新的 `cny`。
3. 任一 MT4 调用失败时，按成功调用的逆序写回旧 `zip/cny`；本地数据库保持不变。
4. 全部 MT4 成功后进入本地事务，锁定子树、旧祖先和新祖先，再按快照复核 `parent_id/account_type/level_id`。快照变化时回滚事务并补偿 MT4。
5. 事务内更新根代理 `parent_id`，按父到子顺序重建 `family_tree`，物理重建受影响用户的 `agent_descendants` 唯一行，并写 `operation_logs`。
6. 本地事务失败时，事务回滚后逆序补偿全部已同步 MT4 账号；接口返回服务器错误并记录补偿结果，不能伪装成功。

## 返回结果

- `ResponseCode::UPDATED`：整棵子树的 MT4 与本地层级、闭包关系和审计日志全部完成。
- `ResponseCode::VALIDATION_FAILED`：代理 ID、目标上级、代理等级或循环规则不合法。
- `ResponseCode::PERMISSION_DENIED`：管理员无权访问被移动代理或目标代理。
- `ResponseCode::MT4_SYNC_FAILED`：至少一个 MT4 更新未获明确成功，本地层级未提交，已尝试补偿先前远端调用。
- `ResponseCode::INTERNAL_ERROR`：全部远端成功后本地事务失败，系统已尝试把 MT4 恢复到旧快照。

## 前端交互

- Layui、CrmUI 和 Naive 代理列表增加“调整上级”行操作。
- 表单只提交新的上级代理 ID；`0` 表示迁移到平台根。
- 按钮使用现有 Lucide 图标映射，不增加表情符号；成功后刷新代理列表，失败时展示后端多语言消息。
- 操作必须由 `admin_api_updateAgentParent` 对应按钮权限控制，不能只在前端隐藏。

## 测试设计

- 成功用例覆盖根代理、下级代理和普通客户，断言每个 MT4 调用发生在本地写入前，且 `family_tree/agent_descendants/operation_logs` 全部正确。
- 远端中途失败覆盖逆序补偿和数据库零写入。
- 本地事务失败覆盖整棵子树 MT4 补偿和本地回滚。
- 非代理目标、自己、子孙节点、等级倒挂与数据范围外目标均在调用 MT4 前拒绝。
- 路由、权限迁移、三套后台入口、Lucide 图标和中文注释纳入契约测试。

## 规格自查

- 无 `TBD`、`TODO` 或未定义返回分支。
- 普通客户和代理商迁移职责分离。
- 远端部分成功、本地失败和并发快照变化均有明确补偿路径。
- 前端操作、后端权限、数据范围、MT4、本地事务和审计日志形成同一闭环。
