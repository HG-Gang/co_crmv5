# 后台普通客户上级代理变更闭环设计

## 目标

恢复旧项目 `CustomerController::cust_save_info` 对 `userparentId` 的真实业务语义：后台管理员修改普通客户上级代理时，同步 MT4 层级字段，并原子更新新项目的 `user_infos.parent_id`、`user_infos.family_tree`、`agent_descendants` 和操作日志。

## 范围与边界

- 只接受旧字段 `userparentId`，兼容旧 Blade 的 `data.userparentId` 嵌套结构。
- 现代字段 `parent_id` 继续被资料编辑接口忽略，避免扩大 REST 接口的敏感字段权限。
- 只允许修改 `account_type=2` 的普通客户。代理商层级调整属于代理商专用流程，不在本闭环中混用。
- `userparentId=0` 表示客户改为平台直属；非零上级必须是存在的代理商。
- 当前管理员必须同时有权访问目标客户和目标代理，防止把客户转移到数据范围外。

## 数据链路

1. `AdminUserController::normalizedUserUpdatePayload` 将旧 `userparentId` 归一为内部字段 `legacy_parent_id`。
2. `updateUser` 严格校验目标用户、上级代理类型和管理员数据范围。
3. `FamilyTreeService` 根据新上级的 `parent_id` 链构造客户的新 `family_tree`，并生成旧 MT4 协议需要的五段代理关系码。
4. `Mt4ManagerService::updateUserHierarchy` 使用一次幂等 `update_user` 命令发送 `acc/zip/cny`。
5. MT4 明确成功后，在本地事务内锁定客户和上级，更新 `parent_id/family_tree`，重建受影响代理的 `agent_descendants`，并写 `operation_logs`。
6. 本地事务异常时，用旧 `parent_id` 和旧关系码补偿 MT4；响应返回服务器错误，不能把远端成功、本地失败伪装成业务成功。

## 一致性规则

- 新 `family_tree` 必须按“全部祖先代理 ID + 当前客户 ID”保存，平台直属客户只保存自身 ID。
- 祖先链沿 `user_infos.parent_id` 读取并检测循环；发现循环、缺失节点或非代理祖先时失败关闭。
- `agent_descendants` 对旧祖先移除客户关系，对新祖先写入正确的 `depth` 和 `is_direct`。
- MT4 未返回 `status=ok` 且 `err=0` 时，不进入本地事务。
- 审计日志记录 `parent_id:旧值->新值` 和 `family_tree:旧值->新值`，便于追踪客户归属变更。

## 返回结果

- `ResponseCode::UPDATED`：MT4 层级、用户资料、闭包关系和审计日志全部完成。
- `ResponseCode::VALIDATION_FAILED`：上级参数不合法、目标不是普通客户、上级不是代理或层级链不完整。
- `ResponseCode::PERMISSION_DENIED`：管理员无权访问目标客户或目标上级代理。
- `ResponseCode::MT4_SYNC_FAILED`：MT4 未明确确认 `zip/cny` 更新，本地数据保持原值。
- `ResponseCode::INTERNAL_ERROR`：MT4 已成功但本地事务失败；系统已尝试把 MT4 补偿回旧层级，日志保留补偿结果。

## 测试设计

- 成功迁移：断言 MT4 调用发生在本地写入前，并验证新 `parent_id/family_tree/agent_descendants/operation_logs`。
- 远端失败：断言客户姓名、上级、家谱和闭包关系全部不变。
- 非法上级：覆盖普通客户充当上级、目标代理不存在和管理员数据范围外上级。
- 字段边界：证明 `parent_id` 现代字段仍不能修改归属。
- 协议测试：证明 `update_user` 帧同时包含 `acc/zip/cny`。
- 文档契约：最终清单记录路由、字段、返回码与测试文件。

## 规格自查

- 无 `TBD`、`TODO` 或未定义返回分支。
- 普通客户和代理商层级修改职责已明确分离。
- 外部 MT4 与本地事务的先后关系、失败关闭和补偿边界一致。

