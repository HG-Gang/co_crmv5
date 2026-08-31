# Admin News Legacy Parity Design

## 状态与范围

本设计关闭旧 `NewsInfoController` 的 7 条方法级路由，不扩展到前台新闻功能或新的内容管理能力：

- `GET index/admin/news/news_list_browse`
- `GET index/admin/news/news_add_browse`
- `GET index/admin/news/news_edit/{newsid}`
- `POST index/admin/news/newsListSearch`
- `POST index/admin/news/news_save`
- `POST index/admin/news/news_update`
- `POST index/admin/news/del`

旧项目和旧库只读，正式 `co_crmv5` 禁写，只有 PHPUnit 可写 `co_crmv5_test`。本模块不调用 MT4，不执行迁移命令，不执行全量重置 SQL。

## 方案选择

采用“现代业务控制器 + 专用旧契约适配器”方案。

1. `NewsController` 是新闻查询和写入的唯一业务入口，负责现代校验、白名单写入、作者快照和翻译镜像同步。
2. `LegacyAdminController` 只负责旧字段、旧分页和旧响应 envelope，不复制新闻 SQL。
3. 旧 GET 页面继续复用现有 Layui 新闻页，通过受控 `list/create/edit` 模式恢复旧深链接语义。
4. CrmUI 继续复用通用模块页，只补准确筛选和权限声明。

未采用的方案：

- 不在 `NewsController` 内根据旧 URI 返回两种响应，这会把现代 API 与旧协议耦合。
- 不新建第二套新闻 CRUD 控制器或表，这会产生查询、校验和写入的双事实源。

## 查询与旧列表契约

现代列表接受 `page`、`per_page`、`title`、`start_date`、`end_date`、`is_published`。页码必须大于等于 1，每页必须在 1 到 100，日期必须为 `Y-m-d` 且开始日期不能晚于结束日期，发布状态只允许 0 或 1。日期按 `config('app.timezone')` 转成 Unix 秒：开始日 `00:00:00`、结束日 `23:59:59`，匹配旧控制器的闭区间语义。排序固定为 `updated_at DESC, id DESC`。

旧列表适配器执行以下转换：

- `rows` 优先映射为 `per_page`，其次接受 `limit/per_page`，旧 WidgetPage 无分页参数时默认 20；现代 API 自身仍默认 15。
- `startdate/enddate` 映射为 `start_date/end_date`；空值分别默认为 `2024-01-01` 和当天。
- 响应转换为顶层 `rows/total`；有数据时为数组/整数，空结果严格保留旧控制器的 `rows=''`、`total=''`。
- 每行同时提供现代字段和旧字段：`news_id/news_title/news_content/is_push/news_user/rec_upd_date/rec_crt_date`。
- 数组、对象、非法日期、倒置日期、非法分页和非法发布状态均返回 `VALIDATION_FAILED`，不静默修正。

## 写入边界与翻译一致性

创建和更新只从已校验字段构造 payload：`title`、`content`、`is_published`。请求中的 `id`、`deleted_at`、`created_at`、`updated_at`、`author_id`、`author_name` 均不得进入批量写入。创建缺少 `is_published` 时使用数据库语义默认 0；现代更新缺少该 key 时必须保留锁定记录的原发布状态，不能静默改成 0。主表允许 `title` 最长 500 个字符和 longText 正文；同步翻译时必须额外尊重 `news_langs.title` 255 字符与 `news_langs.content` TEXT 65535 字节上限。

现代更新、删除和发布切换的目标 ID 必须为正整数。路由参数只要存在，即使值为字符串 `"0"`，也绝不能回退到 body 的 `id`；只有不存在路由参数的内部兼容调用才可读取 body。这样可防止非法路由 ID 被请求体替换后操作另一条有效记录。旧适配器必须先严格校验 `newsId/newsid`，再用该值构造现代 URI。

作者快照由当前 admin guard 生成：`author_id=admin.id`、`author_name=admin.username`。创建和更新均在事务中执行。更新必须在事务内先对目标 `news` 行执行 `lockForUpdate()`，再读取旧标题和正文；活动翻译候选行也必须在判断和写入前锁定，避免两个并发更新留下可遮蔽主表的中间镜像。

`news` 主表是后台写入事实源。为避免旧迁移产生的 `news_langs` 镜像遮蔽新内容，更新时先取出并锁定活动翻译，再用 PHP `===` 对标题和正文做字节级比较；只有两项都与更新前主表完全相同的行才属于镜像。不能使用受 `utf8mb4_unicode_ci` 大小写、重音和尾空格规则影响的 SQL 相等比较。已人工翻译的行保持不变。

新内容能装入翻译列（标题不超过 255 个字符、正文不超过 65535 字节）时更新精确镜像；超过任一翻译列容量时软删除精确镜像，使前台回退主表，不能让合法主表内容因翻译列较小而整体失败。新增记录不制造重复翻译。

旧写接口转换规则：

- `newsTitle -> title`
- `newsContent -> content`
- `ispush -> is_published`
- `newsId/newsid -> id` 或现代路由参数
- 新增、更新成功返回 `msg=SUC`、`code=0`，并保留 `modern_code`。
- 新增、更新失败返回 `msg=FAIL` 和真实现代错误码，且零写入。
- 删除成功返回旧 `code=0`，并保留 `modern_code=1003`；不存在或非法 ID 失败关闭。

`ispush` 与现代 `is_published` 按旧新增/编辑表单和控制器直接存储语义映射：0 表示不发布，1 表示发布。旧列表 Blade 的 formatter 文案与表单相反，属于旧显示缺陷，不复制该缺陷。

## 页面与 Visual C 交互

旧列表页输出 `list` 模式；旧新增页输出 `create` 模式并自动打开空表单；旧编辑页严格校验正整数 `newsid`，只预载未删除记录，不存在时返回 404，并自动打开目标记录表单。query string 不能覆盖服务器确定的模式或记录。

Layui 页面补齐标题、开始日期、结束日期和发布状态筛选。搜索、重置和刷新均回到可预测状态；弹窗宽高受视口约束，移动端不溢出。提交按钮显示处理中状态，错误通过现有消息组件反馈。字段保留可见 label，不使用 placeholder 代替 label。

CrmUI 明确声明：

- 新增权限 `admin_news_create`
- 更新权限 `admin_news_update`
- 删除权限 `admin_news_delete`
- 切换权限 `admin_news_toggle`
- 筛选字段 `title/start_date/end_date/is_published`，其中 `is_published` 必须是 1/0 下拉选项，不能渲染为自由文本。

顶部创建 action 和表单提交按钮都必须绑定 `admin_news_create`，不能只保护表单而留下无权限可见的创建入口。

沿用 Visual C token、Lucide 图标、44px 触控基线、现有 loading/empty/error 状态和双语言文案，不引入新的 CSS 框架或前端运行时。

## 权限与错误处理

三个旧 GET 页面分别绑定列表、创建、更新权限；四个旧 POST 分别绑定现有现代 API 权限。匿名请求不能进入控制器，普通角色缺权限时返回统一拒绝响应。

业务校验错误保留明确 `VALIDATION_FAILED`；记录不存在保留 `DATA_NOT_FOUND` 或 GET 404；异常通过现有 `serverErrorResponse()` 暴露为失败，不返回伪成功。测试验证路由 ID 为 0、负数、小数或带后缀时不能被 body ID 替换，且所有失败路径零写入。

## 测试与完成标准

实施严格使用 RED-GREEN-REFACTOR：

1. 锁定 7 条路由、权限和旧页面模式。
2. 锁定旧列表日期、分页、字段和 `rows/total`。
3. 锁定旧新增、更新、删除字段与响应。
4. 锁定现代白名单、作者快照、翻译镜像同步和事务回滚。
5. 锁定 Layui/CrmUI 筛选、权限、响应式弹窗和多语言契约。
6. 运行新闻关键词、Legacy UI、CrmUI、Visual C、PHP/JS 语法和 Blade 编译回归。
7. 独立规格复审和质量复审清零 Critical/Important 后，写入 7 条七维证据，矩阵目标更新为 `422/475 verified`、`53 needs_manual_business_review`。

浏览器四视口只在策略允许时执行；若仍为 `BLOCKED_BY_BROWSER_POLICY`，仅记录阻塞，不绕过策略，也不冒充运行时验证通过。
