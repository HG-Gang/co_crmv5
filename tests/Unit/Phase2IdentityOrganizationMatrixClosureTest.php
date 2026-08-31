<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 21:42
 */

/**
 * Phase2IdentityOrganizationMatrixClosureTest
 *
 * 文件功能：
 * - 验证 Phase2 身份与组织路由证据矩阵：全局基线精确、各路由组由显式分组验证、证据状态计数由行派生、矩阵解码拒绝非法容器、路由与汇总 diff 报告漂移且基线可完整复现。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class Phase2IdentityOrganizationMatrixClosureTest extends TestCase
{
    /**
     * 二阶段（身份与组织）已接管旧动作的控制器类全集：后台登录/管理员/角色/实名、大代理、用户组、
     * 组别配置、客户与前台登录/注册/找回密码/用户中心/直属客户/代理列表。
     * 门禁按它断言这些类的路由已全部登记进核验矩阵；新增二阶段控制器必须登记，否则其路由不受门禁保护。
     *
     * @var array<int, class-string>
     */
    private const PHASE_TWO_LEGACY_ACTION_CLASSES = [
        'App\\Http\\Controllers\\Admin\\LoginController',
        'App\\Http\\Controllers\\Admin\\AdminController',
        'App\\Http\\Controllers\\Admin\\AdministratorsController',
        'App\\Http\\Controllers\\Admin\\RoleController',
        'App\\Http\\Controllers\\Admin\\AuthenticationController',
        'App\\Http\\Controllers\\Admin\\AgentControllerV3',
        'App\\Http\\Controllers\\Admin\\BigAgentController',
        'App\\Http\\Controllers\\Admin\\UserGroupController',
        'App\\Http\\Controllers\\Admin\\GroupConfigController',
        'App\\Http\\Controllers\\Admin\\CustomerController',
        'App\\Http\\Controllers\\User\\LoginController',
        'App\\Http\\Controllers\\User\\RegisterController',
        'App\\Http\\Controllers\\User\\UserForgetPswController',
        'App\\Http\\Controllers\\User\\UserCenterController',
        'App\\Http\\Controllers\\User\\DirectCustomerController',
        'App\\Http\\Controllers\\User\\ProxyListController',
    ];

    /**
     * 大代理（Big Number）控制器的全限定类名。矩阵行按它归属控制器归属；
     * 类改名或迁移命名空间时该常量与矩阵 JSON 必须同步更新。
     */
    private const BIG_NUMBER_CONTROLLER = 'App\\Http\\Controllers\\Admin\\BigNumberController';

    /**
     * 大代理控制器的旧路由键全集（登录/登出/改密/首页等 7 条）。门禁按它断言
     * 这些路由归属 Big Number 控制器且矩阵证据完整；路由增删必须同步维护。
     *
     * @var array<int, string>
     */
    private const BIG_NUMBER_ROUTE_KEYS = [
        'GET agents/login',
        'POST user/agents/signIn',
        'GET user/agents/loginOut',
        'GET user/agents/editpsw',
        'POST user/agents/changePassword',
        'GET user/agents/index',
        'GET user/agents/main/home',
    ];

    /**
     * 全局路由方法总数基线：476（2026-08-29 对照审计补记 POST test/withdraw 后自 475 递增）。核验矩阵 rows 必须恰好等于该数——矩阵行数与路由注册总数一一对应，
     * 出现偏差说明有路由漏登记或矩阵过期，整体闭包即不成立。
     */
    private const EXPECTED_GLOBAL_ROUTE_METHODS = 476;

    /**
     * 全局已验证（verified）路由数的下限：384。只设下限是为允许剩余 legacy 条目继续推进；
     * 低于该值说明大量路由证据回退，闭包质量失守，必须人工排查后再继续。
     */
    private const MINIMUM_GLOBAL_VERIFIED = 384;

    /**
     * 二阶段重点路由的期望清单：键为「HTTP 方法 旧URI」，值为该行必须命中的旧动作类与出证状态。
     * 与 PHASE_TWO_LEGACY_ACTION_CLASSES 的差异：这里按路由粒度逐一断言，而非按控制器聚合。
     *
     * @var array<string, array{legacy_action: class-string, evidence_state: string}>
     */
    private const EXPECTED_PHASE_TWO_ROUTES = [
        'GET user/change/list' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\DirectCustomerController@cust_list_chang_group_browse',
            'evidence_state' => 'verified',
        ],
        'GET user/cust/change/group/{uid}' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\DirectCustomerController@changeDirectCustGroupInfo',
            'evidence_state' => 'verified',
        ],
        'POST user/cust/change/group_edit' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\DirectCustomerController@changeDirectCustGroupEdit',
            'evidence_state' => 'verified',
        ],
        'POST user/cust/directCustChangeListSearch' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\DirectCustomerController@directCustChangeListSearch',
            'evidence_state' => 'verified',
        ],
        'POST user/cust/directCustListSearch' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\DirectCustomerController@directCustListSearch',
            'evidence_state' => 'verified',
        ],
        'GET user/cust/list' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\DirectCustomerController@cust_list_browse',
            'evidence_state' => 'verified',
        ],
        'POST user/cust/loginHistorySearch/{uid}' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\DirectCustomerController@loginHistorySearch',
            'evidence_state' => 'verified',
        ],
        'GET user/cust/show_direct_cust_info/{role}/{uid}' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\DirectCustomerController@show_direct_cust_info',
            'evidence_state' => 'verified',
        ],
        'GET user/proxy/confirm' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\ProxyListController@proxy_confirm_browse',
            'evidence_state' => 'verified',
        ],
        'POST user/proxy/confirmLevelChange' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\ProxyListController@confirmLevelChange',
            'evidence_state' => 'verified',
        ],
        'POST user/proxy/directUserCommTrans' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\ProxyListController@directUserCommTrans',
            'evidence_state' => 'verified',
        ],
        'GET user/proxy/direct_cust_detail/{puid}' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\ProxyListController@proxy_direct_cust_detail',
            'evidence_state' => 'verified',
        ],
        'POST user/proxy/direct_cust_detail_list' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\ProxyListController@direct_cust_detail_list',
            'evidence_state' => 'verified',
        ],
        'GET user/proxy/direct_user_commTrans_browse/{uid}' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\ProxyListController@direct_user_commTrans_browse',
            'evidence_state' => 'verified',
        ],
        'POST user/proxy/getSubAgentsGrpIdList' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\ProxyListController@getSubAgentsGrpIdList',
            'evidence_state' => 'verified',
        ],
        'GET user/proxy/list' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\ProxyListController@proxy_list_browse',
            'evidence_state' => 'verified',
        ],
        'POST user/proxy/parentPath' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\ProxyListController@getParentPath',
            'evidence_state' => 'verified',
        ],
        'POST user/proxy/proxyConfirmSearch' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\ProxyListController@proxyConfirmSearch',
            'evidence_state' => 'verified',
        ],
        'POST user/proxy/proxyListSearch' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\ProxyListController@proxyListSearch',
            'evidence_state' => 'verified',
        ],
        'GET agents/login' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigNumberController@agentsLogin',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/Administrators' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdministratorsController@index',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/Administrators/add' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdministratorsController@add',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/Administrators/addsave' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdministratorsController@addsave',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/Administrators/del' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdministratorsController@del',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/Administrators/edit/{id?}' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdministratorsController@show',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/Administrators/editsave' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdministratorsController@save',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/Administrators/start' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdministratorsController@start',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/Administrators/stop' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdministratorsController@stop',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/agent/edit/{user_id?}' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@AgentEdir',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/agent/update' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@AgentUpdate',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/agent/v2/agentsListSearchV2' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@agentsListSearchV2',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/agent/{user_id?}' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@index',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/agents/agentsExamineListSearch' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@agentsExamineListSearch',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/agents/agentsListSearch' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@agentsListSearch',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/agents/agents_edit_info/{uid}' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@agents_edit_info',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/agents/agents_edit_save' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@agents_edit_save',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/agents_add' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@agents_add_browse',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/agents_examine' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@agents_examine_browse',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/agents_list' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@agents_list_browse',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/agents_save' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@agents_save',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/auth/userCertifiedSearch' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@userCertifiedSearch',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/auth/userCertifiedSearchV2' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@userCertifiedSearchV2',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/auth/userExaminSearch' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@userExaminSearch',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/auth/userExaminSearchV2' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@userExaminSearchV2',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/auth/user_certified' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@user_certified',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/auth/user_certified_detail/{uid}' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@userCertifiedDetail',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/auth/user_examine' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@user_examine',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/auth/user_examine/detail/{mode}/{uid}' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@user_examine_detail',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/auth/user_idcard_bank' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@user_idcard_bank',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/auth/user_voucher/detail/{recId}/{uid}' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@voucherInfoDetail',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/auth/voucherInfoSearch' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@voucherInfoSearch',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/auth/voucherInfoSearchV2' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@voucherInfoSearchV2',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/auth/voucherReviewSave' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@voucherReviewSave',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/auth/voucher_info_browse' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AuthenticationController@voucher_info_browse',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/bigAgents' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigAgentController@index',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/bigAgents/add' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigAgentController@add',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/bigAgents/agentsListSearch' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigAgentController@bigAgentsListSearch',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/bigAgents/del' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigAgentController@del',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/bigAgents/save' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigAgentController@save',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/bigAgents/show/{id?}' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigAgentController@show',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/bigAgents/start' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigAgentController@start',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/bigAgents/stop' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigAgentController@stop',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/bigAgents/subAgentsListSearch' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigAgentController@getSubAgentsStats',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/bigAgents/updateInfo' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigAgentController@updateInfo',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/big_agents_list' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigAgentController@big_agents_list_browse',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/captcha' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\LoginController@captcha',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/cust/add' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\CustomerController@cust_add_browse',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/cust/change_list' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\CustomerController@change_list',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/cust/custChangeListSearch' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\CustomerController@custChangeListSearch',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/cust/custChangeListSearchV2' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\CustomerController@custChangeListSearchV2',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/cust/custListSearch' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\CustomerController@custListSearch',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/cust/custListSearchV2' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\CustomerController@custListSearchV2',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/cust/cust_apply_nopass' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\CustomerController@cust_apply_nopass',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/cust/cust_apply_pass' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\CustomerController@cust_apply_pass',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/cust/cust_detail/{acc_uid}' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\CustomerController@cust_detail',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/cust/cust_save_add' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\CustomerController@cust_save_add',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/cust/cust_save_info' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\CustomerController@cust_save_info',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/cust/list' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\CustomerController@user_list',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/customer/{user_id?}' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@CustomerList',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/group/add' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\GroupConfigController@group_add_index',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/group/pairSelect' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\GroupConfigController@pairSelect',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/group/store' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\GroupConfigController@store',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/group/update' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\GroupConfigController@update',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/group/user_group_add' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\UserGroupController@user_group_add',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/group/user_group_browse' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\UserGroupController@user_group_browse',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/group/user_group_delete' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\UserGroupController@user_group_delete',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/group/user_group_edit/{recId}' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\UserGroupController@user_group_edit',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/group/user_group_search' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\UserGroupController@user_group_search',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/group/user_group_searchV2' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\UserGroupController@user_group_searchV2',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/group/user_group_store' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\UserGroupController@user_group_store',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/group/user_group_update' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\UserGroupController@user_group_update',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/index' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdminController@index',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/login' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\LoginController@index',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/logon' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\LoginController@logon',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/logout' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\LoginController@logout',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/role' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\RoleController@index',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/role/add' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\RoleController@create',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/role/addsave' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\RoleController@store',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/role/del' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\RoleController@del',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/role/edit/{id?}' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\RoleController@show',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/role/editsave' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\RoleController@editsave',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/send/againSendSms' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AgentControllerV3@againSendSms',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/userinfo' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdminController@UserInfo',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/userinfo/save' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdminController@UserIfoSave',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/userpwd' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdminController@UserPwd',
            'evidence_state' => 'verified',
        ],
        'POST index/admin/userpwd/save' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdminController@UserPewdSave',
            'evidence_state' => 'verified',
        ],
        'GET index/admin/welcome' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\AdminController@create',
            'evidence_state' => 'verified',
        ],
        'POST user/agents/changePassword' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigNumberController@changePasswordSave',
            'evidence_state' => 'verified',
        ],
        'GET user/agents/editpsw' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigNumberController@agents_editpsw_browse',
            'evidence_state' => 'verified',
        ],
        'GET user/agents/index' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigNumberController@agentsIndex',
            'evidence_state' => 'verified',
        ],
        'GET user/agents/loginOut' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigNumberController@loginOut',
            'evidence_state' => 'verified',
        ],
        'GET user/agents/main/home' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigNumberController@agentsMainHome',
            'evidence_state' => 'verified',
        ],
        'POST user/agents/signIn' => [
            'legacy_action' => 'App\\Http\\Controllers\\Admin\\BigNumberController@agentsSignIn',
            'evidence_state' => 'verified',
        ],
        'POST user/agents/editpsw_save' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@agents_editpsw_save',
            'evidence_state' => 'verified',
        ],
        'POST user/agents/relationShipHtml' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@relationShipHtmlV2',
            'evidence_state' => 'verified',
        ],
        'GET en/user/register/{register_type?}/{user_id?}/{comm_type?}' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@enIndex',
            'evidence_state' => 'verified',
        ],
        'GET importAgents' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@importAgents',
            'evidence_state' => 'verified',
        ],
        'GET importLang' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@importLang',
            'evidence_state' => 'verified',
        ],
        'GET importUser' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@importUser',
            'evidence_state' => 'verified',
        ],
        'POST localRegisterNotifyByAgents' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@localRegisterNotifyByAgents',
            'evidence_state' => 'verified',
        ],
        'GET show/user_detail/{userId}/{role}' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@show_user_detail',
            'evidence_state' => 'verified',
        ],
        'POST syncAgents' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@syncAgents',
            'evidence_state' => 'verified',
        ],
        'POST syncDisableUserToT4' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@syncDisableUserToT4',
            'evidence_state' => 'verified',
        ],
        'GET syncToT4ByLocalAgents' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@syncToT4ByLocalAgents',
            'evidence_state' => 'verified',
        ],
        'POST syncToT4ByLocalUser' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@syncToT4ByLocalUser',
            'evidence_state' => 'verified',
        ],
        'POST syncUser' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@syncUser',
            'evidence_state' => 'verified',
        ],
        'GET test_sms' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@test_register',
            'evidence_state' => 'verified',
        ],
        'GET user/account' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@user_account_browse',
            'evidence_state' => 'verified',
        ],
        'GET user/captcha' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@captcha',
            'evidence_state' => 'verified',
        ],
        'GET user/center' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@user_info_browse',
            'evidence_state' => 'verified',
        ],
        'POST user/center/ajaxCancelAccount' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@ajaxCancelAccount',
            'evidence_state' => 'verified',
        ],
        'GET user/center/cancelAccount' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@cancelAccount_browse',
            'evidence_state' => 'verified',
        ],
        'POST user/center/cancelVerifyInfo' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@cancelVerifyInfo',
            'evidence_state' => 'verified',
        ],
        'POST user/center/cancelVerifyPassSendCode' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@cancelVerifyPassSendCode',
            'evidence_state' => 'verified',
        ],
        'POST user/center/changeBankCardSendCode' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@changeBankCardSendCode',
            'evidence_state' => 'verified',
        ],
        'POST user/center/changeBankCardVerifyCode' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@changeBankCardVerifyCode',
            'evidence_state' => 'verified',
        ],
        'GET user/center/updPhoneEmail/{type}' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@updPhoneEmail_browse',
            'evidence_state' => 'verified',
        ],
        'POST user/center/updVerifyPassSendCode' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@updVerifyPassSendCode',
            'evidence_state' => 'verified',
        ],
        'POST user/center/updatePhoneEmailInfo' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@updatePhoneEmailInfo',
            'evidence_state' => 'verified',
        ],
        'POST user/center/updateVerifyInfo' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@updateVerifyInfo',
            'evidence_state' => 'verified',
        ],
        'GET user/center/uploadBank' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@uploadBank_browse',
            'evidence_state' => 'verified',
        ],
        'POST user/center/uploadBankCard' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@uploadBankCard',
            'evidence_state' => 'verified',
        ],
        'GET user/center/uploadChangeBank/{type}' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@uploadChangeBank_browse',
            'evidence_state' => 'verified',
        ],
        'POST user/center/uploadChangeBankCard' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@uploadChangeBankCard',
            'evidence_state' => 'verified',
        ],
        'POST user/center/uploadHeadImg' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@uploadHeadImg',
            'evidence_state' => 'verified',
        ],
        'GET user/center/uploadHead_browse' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@uploadHead_browse',
            'evidence_state' => 'verified',
        ],
        'GET user/center/uploadIdCard' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@uploadIdCard_browse',
            'evidence_state' => 'verified',
        ],
        'POST user/center/uploadIdCard' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@uploadIdCard',
            'evidence_state' => 'verified',
        ],
        'POST user/change_account_save' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@change_account_save',
            'evidence_state' => 'verified',
        ],
        'POST user/change_password' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserForgetPswController@saveChangePassword',
            'evidence_state' => 'verified',
        ],
        'POST user/check_user_info' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserForgetPswController@checkUserInfo',
            'evidence_state' => 'verified',
        ],
        'GET user/editpsw' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@user_editpsw_browse',
            'evidence_state' => 'verified',
        ],
        'POST user/editpsw_save' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@user_editpsw_save',
            'evidence_state' => 'verified',
        ],
        'POST user/forgetPasswordInfoVerification' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserForgetPswController@forgetPasswordInfoVerification',
            'evidence_state' => 'verified',
        ],
        'GET user/forget_password' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserForgetPswController@forget_password_browse',
            'evidence_state' => 'verified',
        ],
        'POST user/forgetpswSendCode' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserForgetPswController@forgetpswSendCode',
            'evidence_state' => 'verified',
        ],
        'GET user/front/message' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@frontMsg',
            'evidence_state' => 'verified',
        ],
        'GET user/index' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@index',
            'evidence_state' => 'verified',
        ],
        'GET user/index/index' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@index',
            'evidence_state' => 'verified',
        ],
        'GET user/index/login' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@loginGmtk',
            'evidence_state' => 'verified',
        ],
        'GET user/index/register/{register_type?}/{user_id?}/{comm_type?}' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@indexGmtk',
            'evidence_state' => 'verified',
        ],
        'POST user/index/signIn' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@signIn',
            'evidence_state' => 'verified',
        ],
        'POST user/indexreg' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@index',
            'evidence_state' => 'verified',
        ],
        'GET user/login' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@loginGmtk',
            'evidence_state' => 'verified',
        ],
        'GET user/loginOut' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@loginOut',
            'evidence_state' => 'verified',
        ],
        'POST user/main/hasShowGiftTips' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@hasShowGiftTips',
            'evidence_state' => 'verified',
        ],
        'GET user/main/home' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@mainHome',
            'evidence_state' => 'verified',
        ],
        'POST user/main/hot/news' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@hotNews',
            'evidence_state' => 'verified',
        ],
        'POST user/main/hot/newsV2' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@hotNewsV2',
            'evidence_state' => 'verified',
        ],
        'POST user/offweb/feedback' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@demandFeedback',
            'evidence_state' => 'verified',
        ],
        'GET user/register/captcha' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@registercaptcha',
            'evidence_state' => 'verified',
        ],
        'GET user/register/hotnews' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@hotnews',
            'evidence_state' => 'verified',
        ],
        'GET user/register/rebateDeposit' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@orderRebateDeposit',
            'evidence_state' => 'verified',
        ],
        'POST user/register/registerSendCode' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@registerSendCode',
            'evidence_state' => 'verified',
        ],
        'POST user/register/registerVerifyInfo' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@registerVerifyInfo',
            'evidence_state' => 'verified',
        ],
        'POST user/register/registerinto' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@registerinto',
            'evidence_state' => 'verified',
        ],
        'GET user/register/testemail' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@testemail',
            'evidence_state' => 'verified',
        ],
        'GET user/register/testmodel' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@testmodel',
            'evidence_state' => 'verified',
        ],
        'GET user/register/{register_type?}/{user_id?}/{comm_type?}' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\RegisterController@indexGmtk',
            'evidence_state' => 'verified',
        ],
        'POST user/relationShip' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@relationShip',
            'evidence_state' => 'verified',
        ],
        'POST user/relationShipHtml' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@relationShipHtml',
            'evidence_state' => 'verified',
        ],
        'POST user/signIn' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@signIn',
            'evidence_state' => 'verified',
        ],
        'POST user/user_voucher_save' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@user_voucher_save',
            'evidence_state' => 'verified',
        ],
        'GET user/voucher' => [
            'legacy_action' => 'App\\Http\\Controllers\\User\\UserCenterController@user_voucher_browse',
            'evidence_state' => 'verified',
        ],
    ];

    public function test_matrix_has_the_expected_global_baseline(): void
    {
        $matrix = $this->readMatrix();
        $actualStateCounts = array_replace(
            array_fill_keys([
                'verified',
                'needs_manual_business_review',
                'unresolved_legacy_source',
                'unmatched_current_route',
            ], 0),
            $this->evidenceStateCounts($matrix['rows'])
        );
        $expectedSummary = array_merge([
            'legacy_route_methods' => self::EXPECTED_GLOBAL_ROUTE_METHODS,
        ], $actualStateCounts);
        $actualSummary = $matrix['summary'];
        ksort($expectedSummary, SORT_STRING);
        ksort($actualSummary, SORT_STRING);
        ksort($actualStateCounts, SORT_STRING);
        $summaryDifferences = $this->globalSummaryDifferences(
            $expectedSummary,
            $matrix['summary'],
            $matrix['rows']
        );

        $this->assertCount(self::EXPECTED_GLOBAL_ROUTE_METHODS, $matrix['rows']);
        $this->assertSame([
            'missing_summary_fields' => [],
            'unexpected_summary_fields' => [],
            'changed_summary_fields' => [],
            'unexpected_evidence_states' => [],
            'changed_evidence_counts' => [],
        ], $summaryDifferences, 'Global summary differences: ' . json_encode(
            $summaryDifferences,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ));
        $this->assertSame(
            $expectedSummary,
            $actualSummary,
            'Actual matrix summary:' . PHP_EOL . json_encode($actualSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $this->assertSame(
            array_diff_key($expectedSummary, ['legacy_route_methods' => true]),
            $actualStateCounts,
            'Evidence states derived from all rows:' . PHP_EOL
                . json_encode($actualStateCounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->assertGreaterThanOrEqual(self::MINIMUM_GLOBAL_VERIFIED, $actualStateCounts['verified'] ?? 0);
        $this->assertSame(0, $actualStateCounts['unresolved_legacy_source'] ?? 0);
        $this->assertSame(0, $actualStateCounts['unmatched_current_route'] ?? 0);
    }

    public function test_admin_legacy_login_routes_are_verified_by_one_explicit_group(): void
    {
        $expected = [
            'GET index/admin/captcha' => 'legacy_admin_112ffed00382cbc4',
            'GET index/admin/login' => 'legacy_admin_ab3b75e4093e3bd8',
            'POST index/admin/logon' => 'legacy_admin_ac69070e4a51d6c5',
            'GET index/admin/logout' => 'legacy_admin_9a18d0d69737a781',
        ];
        $matches = [];

        foreach ($this->readMatrix()['rows'] as $row) {
            $key = $this->routeKey($row);
            if (array_key_exists($key, $expected)) {
                $matches[$key] = $row;
            }
        }

        $this->assertSame(array_keys($expected), array_keys($matches));
        foreach ($expected as $key => $routeName) {
            $row = $matches[$key];
            $this->assertSame('verified', $row['evidence_state'], $key);
            $this->assertSame('admin_legacy_login_session_2026_08_10', $row['verification_group'], $key);
            $this->assertSame($routeName, $row['current_name'], $key);
            $this->assertSame(
                'App\\Http\\Controllers\\Admin\\LegacyAdminController@handle',
                $row['current_action'],
                $key
            );
        }
    }

    public function test_admin_identity_role_routes_are_verified_by_explicit_groups(): void
    {
        $groups = [
            'admin_legacy_profile_session_2026_08_10' => [
                'GET index/admin/index',
                'GET index/admin/welcome',
                'GET index/admin/userinfo',
                'POST index/admin/userinfo/save',
                'GET index/admin/userpwd',
                'POST index/admin/userpwd/save',
            ],
            'admin_legacy_roles_permissions_2026_08_10' => [
                'GET index/admin/role',
                'GET index/admin/role/add',
                'POST index/admin/role/addsave',
                'GET index/admin/role/edit/{id?}',
                'POST index/admin/role/editsave',
                'GET index/admin/role/del',
            ],
            'admin_legacy_administrators_regression_2026_08_10' => [
                'GET index/admin/Administrators',
                'GET index/admin/Administrators/add',
                'POST index/admin/Administrators/addsave',
                'GET index/admin/Administrators/del',
                'GET index/admin/Administrators/edit/{id?}',
                'POST index/admin/Administrators/editsave',
                'GET index/admin/Administrators/start',
                'GET index/admin/Administrators/stop',
            ],
        ];
        $expectedByKey = [];
        foreach ($groups as $group => $keys) {
            foreach ($keys as $key) {
                $expectedByKey[$key] = $group;
            }
        }

        $matches = [];
        foreach ($this->phaseTwoRows($this->readMatrix()['rows']) as $row) {
            $key = $this->routeKey($row);
            if (array_key_exists($key, $expectedByKey)) {
                $matches[$key] = $row;
            }
        }

        $expectedKeys = array_keys($expectedByKey);
        $actualKeys = array_keys($matches);
        sort($expectedKeys, SORT_STRING);
        sort($actualKeys, SORT_STRING);
        $this->assertSame($expectedKeys, $actualKeys);
        foreach ($expectedByKey as $key => $group) {
            $this->assertSame('verified', $matches[$key]['evidence_state'], $key);
            $this->assertSame($group, $matches[$key]['verification_group'], $key);
            $this->assertSame(
                'App\\Http\\Controllers\\Admin\\LegacyAdminController@handle',
                $matches[$key]['current_action'],
                $key
            );
        }
    }

    public function test_admin_task_four_routes_are_verified_by_explicit_groups(): void
    {
        $groups = [
            'admin_legacy_agent_management_2026_08_16' => [
                'GET index/admin/agent/edit/{user_id?}',
                'GET index/admin/agent/{user_id?}',
                'GET index/admin/agents/agents_edit_info/{uid}',
                'GET index/admin/agents_add',
                'GET index/admin/agents_examine',
                'GET index/admin/agents_list',
                'POST index/admin/agents_save',
                'GET index/admin/customer/{user_id?}',
                'POST index/admin/send/againSendSms',
            ],
            'admin_legacy_customer_management_2026_08_16' => [
                'GET index/admin/cust/add',
                'GET index/admin/cust/change_list',
                'POST index/admin/cust/custChangeListSearch',
                'POST index/admin/cust/custChangeListSearchV2',
                'POST index/admin/cust/custListSearch',
                'POST index/admin/cust/custListSearchV2',
                'GET index/admin/cust/cust_detail/{acc_uid}',
                'POST index/admin/cust/cust_save_add',
                'POST index/admin/cust/cust_save_info',
                'GET index/admin/cust/list',
            ],
            'admin_legacy_group_config_2026_08_16' => [
                'GET index/admin/group/add',
                'GET index/admin/group/pairSelect',
                'POST index/admin/group/store',
                'POST index/admin/group/update',
            ],
            'admin_legacy_user_group_2026_08_16' => [
                'GET index/admin/group/user_group_add',
                'GET index/admin/group/user_group_browse',
                'POST index/admin/group/user_group_delete',
                'GET index/admin/group/user_group_edit/{recId}',
                'POST index/admin/group/user_group_search',
                'POST index/admin/group/user_group_searchV2',
                'POST index/admin/group/user_group_store',
                'POST index/admin/group/user_group_update',
            ],
        ];
        $expectedByKey = [];
        foreach ($groups as $group => $keys) {
            foreach ($keys as $key) {
                $expectedByKey[$key] = $group;
            }
        }

        $matches = [];
        foreach ($this->phaseTwoRows($this->readMatrix()['rows']) as $row) {
            $key = $this->routeKey($row);
            if (array_key_exists($key, $expectedByKey)) {
                $matches[$key] = $row;
            }
        }

        $this->assertCount(31, $expectedByKey);
        $expectedKeys = array_keys($expectedByKey);
        $actualKeys = array_keys($matches);
        sort($expectedKeys, SORT_STRING);
        sort($actualKeys, SORT_STRING);
        $this->assertSame($expectedKeys, $actualKeys);
        foreach ($expectedByKey as $key => $group) {
            $this->assertSame('verified', $matches[$key]['evidence_state'], $key);
            $this->assertSame($group, $matches[$key]['verification_group'], $key);
            $this->assertSame(
                'App\\Http\\Controllers\\Admin\\LegacyAdminController@handle',
                $matches[$key]['current_action'],
                $key
            );
        }
    }

    public function test_admin_authentication_and_voucher_routes_are_verified_by_one_explicit_group(): void
    {
        $expected = [
            'POST index/admin/auth/userCertifiedSearch',
            'POST index/admin/auth/userCertifiedSearchV2',
            'POST index/admin/auth/userExaminSearch',
            'POST index/admin/auth/userExaminSearchV2',
            'GET index/admin/auth/user_certified',
            'GET index/admin/auth/user_certified_detail/{uid}',
            'GET index/admin/auth/user_examine',
            'GET index/admin/auth/user_examine/detail/{mode}/{uid}',
            'POST index/admin/auth/user_idcard_bank',
            'GET index/admin/auth/user_voucher/detail/{recId}/{uid}',
            'POST index/admin/auth/voucherInfoSearch',
            'POST index/admin/auth/voucherInfoSearchV2',
            'POST index/admin/auth/voucherReviewSave',
            'GET index/admin/auth/voucher_info_browse',
        ];
        $matches = [];
        foreach ($this->phaseTwoRows($this->readMatrix()['rows']) as $row) {
            $key = $this->routeKey($row);
            if (in_array($key, $expected, true)) {
                $matches[$key] = $row;
            }
        }

        sort($expected, SORT_STRING);
        $actual = array_keys($matches);
        sort($actual, SORT_STRING);
        $this->assertSame($expected, $actual);
        foreach ($matches as $key => $row) {
            $this->assertSame('verified', $row['evidence_state'], $key);
            $this->assertSame('admin_legacy_authentication_voucher_2026_08_16', $row['verification_group'], $key);
            $this->assertSame(
                'App\\Http\\Controllers\\Admin\\LegacyAdminController@handle',
                $row['current_action'],
                $key
            );
        }
    }

    public function test_admin_legacy_batch_amount_import_routes_are_verified_by_one_explicit_group(): void
    {
        $expected = [
            'POST index/admin/amount/batchOperation',
            'POST index/admin/amount/batchOperationWithdraw',
            'GET index/admin/amount/batch_operation',
            'GET index/admin/amount/batch_operation_withdraw',
            'POST index/admin/amount/depositImportExcel',
            'POST index/admin/amount/depositImportSearch',
            'GET index/admin/amount/deposit_import_index',
            'POST index/admin/amount/withdrawImportExcel',
            'POST index/admin/amount/withdrawImportSearch',
            'GET index/admin/amount/withdraw_import_index',
        ];
        $matches = [];
        foreach ($this->readMatrix()['rows'] as $row) {
            $key = $this->routeKey($row);
            if (in_array($key, $expected, true)) {
                $matches[$key] = $row;
            }
        }

        sort($expected, SORT_STRING);
        $actual = array_keys($matches);
        sort($actual, SORT_STRING);
        $this->assertSame($expected, $actual);
        foreach ($matches as $key => $row) {
            $this->assertSame('verified', $row['evidence_state'], $key);
            $this->assertSame('admin_legacy_batch_amount_import_2026_08_16', $row['verification_group'], $key);
            $this->assertSame(
                'App\\Http\\Controllers\\Admin\\LegacyAdminController@handle',
                $row['current_action'],
                $key
            );
        }
    }

    public function test_phase_two_big_agent_identity_routes_are_verified_by_one_explicit_group(): void
    {
        $expected = [
            'GET index/admin/bigAgents',
            'GET index/admin/bigAgents/add',
            'GET index/admin/bigAgents/del',
            'POST index/admin/bigAgents/save',
            'GET index/admin/bigAgents/show/{id?}',
            'GET index/admin/bigAgents/start',
            'GET index/admin/bigAgents/stop',
            'POST index/admin/bigAgents/updateInfo',
            'GET index/admin/big_agents_list',
            'GET agents/login',
            'POST user/agents/signIn',
            'GET user/agents/loginOut',
            'GET user/agents/editpsw',
            'POST user/agents/changePassword',
            'GET user/agents/index',
            'GET user/agents/main/home',
            'POST user/agents/editpsw_save',
        ];
        $matches = [];
        foreach ($this->phaseTwoRows($this->readMatrix()['rows']) as $row) {
            $key = $this->routeKey($row);
            if (in_array($key, $expected, true)) {
                $matches[$key] = $row;
            }
        }

        sort($expected, SORT_STRING);
        $actual = array_keys($matches);
        sort($actual, SORT_STRING);
        $this->assertSame($expected, $actual);
        foreach ($matches as $key => $row) {
            $this->assertSame('verified', $row['evidence_state'], $key);
            $this->assertSame('phase2_big_agent_identity_2026_08_16', $row['verification_group'], $key);
        }
    }

    public function test_phase_two_front_user_shell_routes_are_verified_by_one_explicit_group(): void
    {
        $expected = [
            'GET show/user_detail/{userId}/{role}',
            'GET user/account',
            'GET user/center',
            'POST user/center/ajaxCancelAccount',
            'GET user/center/cancelAccount',
            'POST user/center/cancelVerifyInfo',
            'POST user/center/cancelVerifyPassSendCode',
            'POST user/change_account_save',
            'GET user/front/message',
            'GET user/index',
            'GET user/index/index',
            'GET user/loginOut',
            'POST user/main/hasShowGiftTips',
            'GET user/main/home',
            'POST user/main/hot/newsV2',
            'POST user/offweb/feedback',
            'GET user/register/hotnews',
            'GET user/register/testemail',
            'POST user/user_voucher_save',
            'GET user/voucher',
        ];
        $matches = [];
        foreach ($this->phaseTwoRows($this->readMatrix()['rows']) as $row) {
            $key = $this->routeKey($row);
            if (in_array($key, $expected, true)) {
                $matches[$key] = $row;
            }
        }

        sort($expected, SORT_STRING);
        $actual = array_keys($matches);
        sort($actual, SORT_STRING);
        $this->assertSame($expected, $actual);
        foreach ($matches as $key => $row) {
            $this->assertSame('verified', $row['evidence_state'], $key);
            $this->assertSame('phase2_front_user_shell_profile_2026_08_16', $row['verification_group'], $key);
        }
    }

    public function test_phase_two_front_proxy_routes_are_verified_by_one_explicit_group(): void
    {
        $expected = [
            'GET user/proxy/confirm',
            'POST user/proxy/confirmLevelChange',
            'POST user/proxy/directUserCommTrans',
            'GET user/proxy/direct_cust_detail/{puid}',
            'POST user/proxy/direct_cust_detail_list',
            'GET user/proxy/direct_user_commTrans_browse/{uid}',
            'POST user/proxy/getSubAgentsGrpIdList',
            'GET user/proxy/list',
            'POST user/proxy/parentPath',
            'POST user/proxy/proxyConfirmSearch',
            'POST user/proxy/proxyListSearch',
        ];
        $matches = [];
        foreach ($this->phaseTwoRows($this->readMatrix()['rows']) as $row) {
            $key = $this->routeKey($row);
            if (in_array($key, $expected, true)) {
                $matches[$key] = $row;
            }
        }

        sort($expected, SORT_STRING);
        $actual = array_keys($matches);
        sort($actual, SORT_STRING);
        $this->assertSame($expected, $actual);
        foreach ($matches as $key => $row) {
            $this->assertSame('verified', $row['evidence_state'], $key);
            $this->assertSame('phase2_front_proxy_scope_2026_08_16', $row['verification_group'], $key);
        }
    }

    public function test_front_gift_news_open_order_and_voucher_routes_are_verified_by_one_explicit_group(): void
    {
        $expected = [
            'GET open/order_detail/{orderId}/{orderType}/{role}',
            'GET user/gift/list',
            'POST user/gift/search',
            'GET user/news/news_detail/{newsId}',
            'POST user/newsListSearch',
            'GET user/news_list_browse',
            'POST user/open/openOrder2Search',
            'POST user/open/openOrderSearch',
            'GET user/open/order',
            'GET user/open/order2',
            'POST user/voucher/voucherSearch',
            'GET user/voucher/voucher_browse',
        ];
        $matches = [];
        foreach ($this->readMatrix()['rows'] as $row) {
            $key = $this->routeKey($row);
            if (in_array($key, $expected, true)) {
                $matches[$key] = $row;
            }
        }

        sort($expected, SORT_STRING);
        $actual = array_keys($matches);
        sort($actual, SORT_STRING);
        $this->assertSame($expected, $actual);
        foreach ($matches as $key => $row) {
            $this->assertSame('verified', $row['evidence_state'], $key);
            $this->assertSame('front_gift_news_open_order_voucher_module_2026_08_17', $row['verification_group'], $key);
        }
    }

    public function test_front_address_closed_order_and_upload_routes_are_verified_by_one_explicit_group(): void
    {
        $expected = [
            'GET close/order_detail/{orderId}/{orderType}/{role}',
            'GET user/address/add',
            'GET user/address/info/{recId}',
            'GET user/address/list',
            'GET user/close/order',
            'GET user/close/order2',
            'POST user/address/search',
            'POST user/address/update',
            'POST user/close/closeOrder2Search',
            'POST user/close/closeOrderSearch',
            'POST user/multiple/file',
            'POST user/upload/file',
        ];
        $matches = [];
        foreach ($this->readMatrix()['rows'] as $row) {
            $key = $this->routeKey($row);
            if (in_array($key, $expected, true)) {
                $matches[$key] = $row;
            }
        }

        sort($expected, SORT_STRING);
        $actual = array_keys($matches);
        sort($actual, SORT_STRING);
        $this->assertSame($expected, $actual);
        foreach ($matches as $key => $row) {
            $this->assertSame('verified', $row['evidence_state'], $key);
            $this->assertSame('front_address_closed_order_upload_module_2026_08_17', $row['verification_group'], $key);
        }
    }

    public function test_evidence_state_counts_are_derived_from_rows_instead_of_summary(): void
    {
        $summary = [
            'unresolved_legacy_source' => 0,
            'unmatched_current_route' => 0,
        ];
        $rows = [
            ['evidence_state' => 'unresolved_legacy_source'],
            ['evidence_state' => 'unmatched_current_route'],
        ];

        $this->assertSame([
            'unresolved_legacy_source' => 1,
            'unmatched_current_route' => 1,
        ], $this->evidenceStateCounts($rows), 'Counts must come from rows, not summary: ' . json_encode($summary));
    }

    /**
     * @dataProvider invalidMatrixContainerProvider
     */
    public function test_matrix_decoder_rejects_non_object_or_non_list_containers(
        string $json,
        string $expectedMessage
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->decodeMatrix($json);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function invalidMatrixContainerProvider(): array
    {
        return [
            'root must be object' => ['[]', 'root must be a JSON object'],
            'summary must be object' => ['{"summary":[],"rows":[]}', 'summary must be a JSON object'],
            'rows must be list' => ['{"summary":{},"rows":{"0":{}}}', 'rows must be a JSON list'],
        ];
    }

    public function test_phase_two_route_diff_reports_verified_uri_action_and_state_drift(): void
    {
        $expected = [
            'GET verified/original' => [
                'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@show',
                'evidence_state' => 'verified',
            ],
            'POST verified/action' => [
                'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@save',
                'evidence_state' => 'verified',
            ],
            'GET verified/state' => [
                'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@state',
                'evidence_state' => 'verified',
            ],
        ];
        $actual = [
            'GET verified/renamed' => $expected['GET verified/original'],
            'POST verified/action' => [
                'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@update',
                'evidence_state' => 'verified',
            ],
            'GET verified/state' => [
                'legacy_action' => 'App\\Http\\Controllers\\User\\LoginController@state',
                'evidence_state' => 'needs_manual_business_review',
            ],
        ];

        $this->assertSame([
            'missing' => ['GET verified/original'],
            'unexpected' => ['GET verified/renamed'],
            'changed' => [
                'GET verified/state' => [
                    'expected' => $expected['GET verified/state'],
                    'actual' => $actual['GET verified/state'],
                ],
                'POST verified/action' => [
                    'expected' => $expected['POST verified/action'],
                    'actual' => $actual['POST verified/action'],
                ],
            ],
        ], $this->phaseTwoRouteDifferences($expected, $actual));
    }

    public function test_global_summary_diff_reports_extra_field_and_fifth_evidence_state(): void
    {
        $expectedSummary = [
            'legacy_route_methods' => 3,
            'verified' => 1,
            'needs_manual_business_review' => 1,
            'unresolved_legacy_source' => 0,
            'unmatched_current_route' => 0,
        ];
        $actualSummary = $expectedSummary;
        $actualSummary['extra_summary_field'] = 123;
        $rows = [
            ['evidence_state' => 'verified'],
            ['evidence_state' => 'needs_manual_business_review'],
            ['evidence_state' => 'experimental_fifth_state'],
        ];

        $this->assertSame([
            'missing_summary_fields' => [],
            'unexpected_summary_fields' => ['extra_summary_field'],
            'changed_summary_fields' => [],
            'unexpected_evidence_states' => ['experimental_fifth_state'],
            'changed_evidence_counts' => [],
        ], $this->globalSummaryDifferences($expectedSummary, $actualSummary, $rows));
    }

    public function test_phase_two_scope_and_evidence_state_baseline_are_exact(): void
    {
        $phaseTwoRows = $this->phaseTwoRows($this->readMatrix()['rows']);
        $expectedStateCounts = $this->evidenceStateCounts(array_values(self::EXPECTED_PHASE_TWO_ROUTES));

        $this->assertCount(184, $phaseTwoRows);
        $this->assertCount(184, self::EXPECTED_PHASE_TWO_ROUTES);

        $actualControllerClasses = [];
        $actualBigNumberKeys = [];
        $routeKeys = [];
        $stateCounts = [];

        foreach ($phaseTwoRows as $row) {
            $class = $this->legacyActionClass($row['legacy_action']);
            if (in_array($class, self::PHASE_TWO_LEGACY_ACTION_CLASSES, true)) {
                $actualControllerClasses[] = $class;
            }
            if ($class === self::BIG_NUMBER_CONTROLLER) {
                $actualBigNumberKeys[] = $this->routeKey($row);
            }

            $routeKeys[] = $this->routeKey($row);
            $state = $row['evidence_state'];
            $stateCounts[$state] = ($stateCounts[$state] ?? 0) + 1;
        }

        $expectedControllerClasses = self::PHASE_TWO_LEGACY_ACTION_CLASSES;
        $actualControllerClasses = array_values(array_unique($actualControllerClasses));
        sort($expectedControllerClasses, SORT_STRING);
        sort($actualControllerClasses, SORT_STRING);
        $this->assertSame($expectedControllerClasses, $actualControllerClasses);

        $expectedBigNumberKeys = self::BIG_NUMBER_ROUTE_KEYS;
        sort($expectedBigNumberKeys, SORT_STRING);
        sort($actualBigNumberKeys, SORT_STRING);
        $this->assertSame($expectedBigNumberKeys, $actualBigNumberKeys);

        $duplicateKeys = [];
        foreach (array_count_values($routeKeys) as $key => $count) {
            if ($count > 1) {
                $duplicateKeys[] = $key;
            }
        }
        sort($duplicateKeys, SORT_STRING);
        $this->assertSame([], $duplicateKeys, 'Duplicate Phase 2 keys: ' . implode(', ', $duplicateKeys));

        ksort($expectedStateCounts, SORT_STRING);
        ksort($stateCounts, SORT_STRING);
        $this->assertSame(184, $expectedStateCounts['verified'] ?? 0);
        $this->assertSame(0, $expectedStateCounts['needs_manual_business_review'] ?? 0);
        $this->assertSame($expectedStateCounts, $stateCounts);
        $this->assertSame(0, $stateCounts['unresolved_legacy_source'] ?? 0);
        $this->assertSame(0, $stateCounts['unmatched_current_route'] ?? 0);

        $unexpectedStates = array_diff(
            array_keys($stateCounts),
            ['verified', 'needs_manual_business_review']
        );
        sort($unexpectedStates, SORT_STRING);
        $this->assertSame([], $unexpectedStates, 'Unexpected Phase 2 evidence states: ' . implode(', ', $unexpectedStates));
    }

    public function test_phase_two_routes_match_the_complete_reproducible_baseline(): void
    {
        $expectedRoutes = self::EXPECTED_PHASE_TWO_ROUTES;
        $actualRoutes = $this->phaseTwoRouteBaseline(
            $this->phaseTwoRows($this->readMatrix()['rows'])
        );
        ksort($expectedRoutes, SORT_STRING);
        ksort($actualRoutes, SORT_STRING);
        $differences = $this->phaseTwoRouteDifferences($expectedRoutes, $actualRoutes);

        $this->assertSame(
            [],
            $differences['missing'],
            'Missing Phase 2 routes:' . PHP_EOL . implode(PHP_EOL, $differences['missing'])
        );
        $this->assertSame(
            [],
            $differences['unexpected'],
            'Unexpected Phase 2 routes:' . PHP_EOL . implode(PHP_EOL, $differences['unexpected'])
        );
        $this->assertSame(
            [],
            $differences['changed'],
            'Changed Phase 2 routes:' . PHP_EOL
                . json_encode($differences['changed'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $this->assertSame(
            $expectedRoutes,
            $actualRoutes,
            'Actual Phase 2 route baseline:' . PHP_EOL
                . json_encode($actualRoutes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @return array{summary: array<string, int>, rows: array<int, array<string, mixed>>}
     */
    private function readMatrix(): array
    {
        $path = dirname(__DIR__, 2) . '/storage/app/audits/旧项目模块逻辑迁移核验矩阵.json';
        if (! is_file($path)) {
            $this->fail('Phase 2 matrix file does not exist: ' . $path);
        }

        $json = file_get_contents($path);
        if ($json === false) {
            $this->fail('Phase 2 matrix file is not readable: ' . $path);
        }

        try {
            $matrix = $this->decodeMatrix($json);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());
        }

        foreach ([
            'legacy_route_methods',
            'verified',
            'needs_manual_business_review',
            'unresolved_legacy_source',
            'unmatched_current_route',
        ] as $field) {
            if (! isset($matrix['summary'][$field]) || ! is_int($matrix['summary'][$field])) {
                $this->fail('Phase 2 matrix summary field must be an integer: ' . $field);
            }
        }

        foreach ($matrix['rows'] as $index => $row) {
            if (! is_array($row)) {
                $this->fail('Phase 2 matrix row must be an object at index ' . $index . '.');
            }
            foreach (['legacy_method', 'legacy_uri', 'legacy_action', 'evidence_state'] as $field) {
                if (! isset($row[$field]) || ! is_string($row[$field])) {
                    $this->fail(sprintf(
                        'Phase 2 matrix row %d must contain a string-valued %s field.',
                        $index,
                        $field
                    ));
                }
            }
        }

        return $matrix;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function evidenceStateCounts(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $state = $row['evidence_state'];
            $counts[$state] = ($counts[$state] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMatrix(string $json): array
    {
        $decoded = json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Phase 2 matrix JSON is invalid: ' . json_last_error_msg());
        }
        if (! ($decoded instanceof \stdClass)) {
            throw new InvalidArgumentException('Phase 2 matrix root must be a JSON object.');
        }
        if (! property_exists($decoded, 'summary') || ! ($decoded->summary instanceof \stdClass)) {
            throw new InvalidArgumentException('Phase 2 matrix summary must be a JSON object.');
        }
        if (! property_exists($decoded, 'rows') || ! is_array($decoded->rows)) {
            throw new InvalidArgumentException('Phase 2 matrix rows must be a JSON list.');
        }
        foreach ($decoded->rows as $index => $row) {
            if (! ($row instanceof \stdClass)) {
                throw new InvalidArgumentException('Phase 2 matrix row must be a JSON object at index ' . $index . '.');
            }
        }

        $matrix = json_decode($json, true);
        if (! is_array($matrix)) {
            throw new InvalidArgumentException('Phase 2 matrix could not be decoded to an array.');
        }

        return $matrix;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<string, string>>
     */
    private function phaseTwoRouteBaseline(array $rows): array
    {
        $baseline = [];
        foreach ($rows as $row) {
            $baseline[$this->routeKey($row)] = [
                'legacy_action' => $row['legacy_action'],
                'evidence_state' => $row['evidence_state'],
            ];
        }
        ksort($baseline, SORT_STRING);

        return $baseline;
    }

    /**
     * @param array<string, array<string, string>> $expected
     * @param array<string, array<string, string>> $actual
     * @return array<string, mixed>
     */
    private function phaseTwoRouteDifferences(array $expected, array $actual): array
    {
        $missing = array_keys(array_diff_key($expected, $actual));
        $unexpected = array_keys(array_diff_key($actual, $expected));
        sort($missing, SORT_STRING);
        sort($unexpected, SORT_STRING);

        $changed = [];
        foreach ($expected as $key => $expectedRoute) {
            if (! array_key_exists($key, $actual) || $expectedRoute === $actual[$key]) {
                continue;
            }

            $changed[$key] = [
                'expected' => $expectedRoute,
                'actual' => $actual[$key],
            ];
        }
        ksort($changed, SORT_STRING);

        return [
            'missing' => $missing,
            'unexpected' => $unexpected,
            'changed' => $changed,
        ];
    }

    /**
     * @param array<string, int> $expectedSummary
     * @param array<string, int> $actualSummary
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function globalSummaryDifferences(
        array $expectedSummary,
        array $actualSummary,
        array $rows
    ): array {
        $missingSummaryFields = array_keys(array_diff_key($expectedSummary, $actualSummary));
        $unexpectedSummaryFields = array_keys(array_diff_key($actualSummary, $expectedSummary));
        sort($missingSummaryFields, SORT_STRING);
        sort($unexpectedSummaryFields, SORT_STRING);

        $changedSummaryFields = [];
        foreach ($expectedSummary as $field => $expectedValue) {
            if (! array_key_exists($field, $actualSummary) || $expectedValue === $actualSummary[$field]) {
                continue;
            }
            $changedSummaryFields[$field] = [
                'expected' => $expectedValue,
                'actual' => $actualSummary[$field],
            ];
        }
        ksort($changedSummaryFields, SORT_STRING);

        $expectedEvidenceCounts = $expectedSummary;
        unset($expectedEvidenceCounts['legacy_route_methods']);
        $actualEvidenceCounts = $this->evidenceStateCounts($rows);
        $unexpectedEvidenceStates = array_keys(array_diff_key($actualEvidenceCounts, $expectedEvidenceCounts));
        sort($unexpectedEvidenceStates, SORT_STRING);

        $changedEvidenceCounts = [];
        foreach ($expectedEvidenceCounts as $state => $expectedCount) {
            $actualCount = $actualEvidenceCounts[$state] ?? 0;
            if ($expectedCount === $actualCount) {
                continue;
            }
            $changedEvidenceCounts[$state] = [
                'expected' => $expectedCount,
                'actual' => $actualCount,
            ];
        }
        ksort($changedEvidenceCounts, SORT_STRING);

        return [
            'missing_summary_fields' => $missingSummaryFields,
            'unexpected_summary_fields' => $unexpectedSummaryFields,
            'changed_summary_fields' => $changedSummaryFields,
            'unexpected_evidence_states' => $unexpectedEvidenceStates,
            'changed_evidence_counts' => $changedEvidenceCounts,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function phaseTwoRows(array $rows): array
    {
        $phaseTwoRows = [];
        foreach ($rows as $row) {
            $class = $this->legacyActionClass($row['legacy_action']);
            if (in_array($class, self::PHASE_TWO_LEGACY_ACTION_CLASSES, true)) {
                $phaseTwoRows[] = $row;
                continue;
            }

            if (
                $class === self::BIG_NUMBER_CONTROLLER
                && in_array($this->routeKey($row), self::BIG_NUMBER_ROUTE_KEYS, true)
            ) {
                $phaseTwoRows[] = $row;
            }
        }

        return $phaseTwoRows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function routeKey(array $row): string
    {
        return strtoupper($row['legacy_method']) . ' ' . ltrim($row['legacy_uri'], '/');
    }

    private function legacyActionClass(string $action): string
    {
        $separator = strpos($action, '@');

        return $separator === false ? $action : substr($action, 0, $separator);
    }
}
