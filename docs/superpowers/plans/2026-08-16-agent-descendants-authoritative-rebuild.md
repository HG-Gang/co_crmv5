# 代理层级闭包权威重建实施计划

**目标：** 让 `user_infos.parent_id`、`user_infos.family_tree` 与 `agent_descendants` 永久保持一致，完整覆盖任意代理的直属/间接代理及每层代理名下客户，并消除历史反向关系、陈旧关系和软删除残留。

**权威规则：** `parent_id` 是直属拓扑唯一事实源；`family_tree` 和 `agent_descendants` 均从活动用户的 `parent_id` 链派生。历史 `family_tree` 只用于迁移审计，不得覆盖当前直属拓扑。父链缺节点、父节点不是代理、出现循环或超过安全深度时，重建失败并回滚，禁止生成半套关系。

## Task 1：锁定失败场景

- 新增闭包重建专项测试，覆盖旧格式 `,0,A,B,C,` 解析、已有软删除闭包再次重建、完整多层代理/客户关系、陈旧/反向关系清除，以及孤儿/非代理父级/循环失败回滚。
- 新增迁移契约测试，禁止 `agent_relations.parent_id -> agent_id` 的反向导入，要求全量迁移只按规范化 `parent_id` 拓扑生成闭包。
- 先运行目标测试并确认因现有实现缺陷失败。

## Task 2：统一层级生成器

- 修改 `FamilyTreeService`：按 `parent_id` 构建规范化祖先链，统一输出 `family_tree`、`depth`、`is_direct` 与 `descendant_type`。
- `rebuildDescendants()` 使用物理删除和 `updateOrInsert`，不再依赖可能陈旧的 `family_tree` LIKE 查询。
- 提供事务化全量重建入口：先在内存中验证所有活动用户的拓扑，再原子替换活动闭包；软删除用户相关关系不保留。
- `getAllDescendants()` 与 `getNetworkTree()` 使用完整 `parent_id` 范围，避免闭包缺行造成漏数。

## Task 3：修复迁移入口

- 修改 `full_reset_and_migrate.sql`，移除不可信的旧 `agent_relations` 直接导入，先规范化 `family_tree`，再由新库 `parent_id` 生成闭包。
- 两个 PHP 旧数据迁移入口在用户和代理写入完成后调用同一层级重建逻辑，并把闭包表纳入清理与校验。
- 增加可审计命令，默认只读，显式 `--apply` 才事务化重建；输出活动用户数、预期关系数和异常详情。

## Task 4：消费端审计

- 逐一检查交易、入金、出金、资金流水、风控、实时返佣、用户列表和代理统计。
- 业务范围必须包含绑定代理自身、全部下级代理和全部客户；仅“代理列表”可以筛选 `descendant_type=1`。
- 所有范围查询统一使用闭包与 `parent_id` 完整范围，不允许单独依赖可能缺行的闭包表。

## Task 5：验证与数据修复

- 串行运行闭包、注册/MT4 outbox、数据权限、统计与返佣专项测试。
- 运行 PHP 语法检查、迁移契约测试和只读审计。
- 在当前开发库先创建可恢复备份，再执行 `--apply`；执行后要求缺失、属性错误和额外活动关系均为 0。
- 删除临时审计配置/脚本；当前目录无 Git 元数据，不伪造提交记录。
