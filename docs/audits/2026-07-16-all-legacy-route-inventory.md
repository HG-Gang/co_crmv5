# Legacy Route Inventory Audit

- Scope: `all`
- Total legacy routes: 395
- Matched: 171
- Intentional method restrictions: 20
- Gaps: 204

| Legacy methods | Legacy URI | Legacy action | Status | Missing methods | Decision reason | Current name | Current action |
|---|---|---|---|---|---|---|---|
| GET | `/` | `Closure` | `matched` |  |  | `` | `Closure` |
| GET | `agents/login` | `App\Http\Controllers\Admin\BigNumberController@agentsLogin` | `matched` |  |  | `legacy_agents_login_page` | `App\Http\Controllers\Front\BigNumberController@agentsLogin` |
| GET | `captcha/api/{config?}` | `\Mews\Captcha\CaptchaController@getCaptchaApi` | `matched` |  |  | `` | `\Mews\Captcha\CaptchaController@getCaptchaApi` |
| GET | `captcha/{config?}` | `\Mews\Captcha\CaptchaController@getCaptcha` | `matched` |  |  | `` | `\Mews\Captcha\CaptchaController@getCaptcha` |
| GET | `close/order_detail/{orderId}/{orderType}/{role}` | `App\Http\Controllers\User\CloseOrderController@close_order_detail` | `matched` |  |  | `legacy_user_close_order_detail` | `App\Http\Controllers\Front\OrderController@closeOrderDetail` |
| GET | `en/user/register/{register_type?}/{user_id?}/{comm_type?}` | `App\Http\Controllers\User\RegisterController@enIndex` | `matched` |  |  | `legacy_en_user_register_page` | `App\Http\Controllers\Front\AuthController@legacyRegisterPage` |
| GET | `importAgents` | `App\Http\Controllers\User\RegisterController@importAgents` | `matched` |  |  | `legacy_import_agents` | `App\Http\Controllers\Front\LegacyMaintenanceController@importAgents` |
| GET | `importLang` | `App\Http\Controllers\User\RegisterController@importLang` | `matched` |  |  | `legacy_import_lang` | `App\Http\Controllers\Front\LegacyMaintenanceController@importLang` |
| GET | `importUser` | `App\Http\Controllers\User\RegisterController@importUser` | `matched` |  |  | `legacy_import_user` | `App\Http\Controllers\Front\LegacyMaintenanceController@importUser` |
| GET | `index/admin/Administrators` | `App\Http\Controllers\Admin\AdministratorsController@index` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/Administrators/add` | `App\Http\Controllers\Admin\AdministratorsController@add` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/Administrators/addsave` | `App\Http\Controllers\Admin\AdministratorsController@addsave` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/Administrators/del` | `App\Http\Controllers\Admin\AdministratorsController@del` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/Administrators/edit/{id?}` | `App\Http\Controllers\Admin\AdministratorsController@show` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/Administrators/editsave` | `App\Http\Controllers\Admin\AdministratorsController@save` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/Administrators/start` | `App\Http\Controllers\Admin\AdministratorsController@start` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/Administrators/stop` | `App\Http\Controllers\Admin\AdministratorsController@stop` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/agent/edit/{user_id?}` | `App\Http\Controllers\Admin\AgentControllerV3@AgentEdir` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/agent/update` | `App\Http\Controllers\Admin\AgentControllerV3@AgentUpdate` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/agent/v2/agentsListSearchV2` | `App\Http\Controllers\Admin\AgentControllerV3@agentsListSearchV2` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/agent/{user_id?}` | `App\Http\Controllers\Admin\AgentControllerV3@index` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/agents/agentsExamineListSearch` | `App\Http\Controllers\Admin\AgentControllerV3@agentsExamineListSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/agents/agentsListSearch` | `App\Http\Controllers\Admin\AgentControllerV3@agentsListSearch` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/agents/agents_edit_info/{uid}` | `App\Http\Controllers\Admin\AgentControllerV3@agents_edit_info` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/agents/agents_edit_save` | `App\Http\Controllers\Admin\AgentControllerV3@agents_edit_save` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/agents_add` | `App\Http\Controllers\Admin\AgentControllerV3@agents_add_browse` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/agents_examine` | `App\Http\Controllers\Admin\AgentControllerV3@agents_examine_browse` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/agents_list` | `App\Http\Controllers\Admin\AgentControllerV3@agents_list_browse` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/agents_save` | `App\Http\Controllers\Admin\AgentControllerV3@agents_save` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/OTCwithdrawOrderIdDetail` | `App\Http\Controllers\Admin\WithdrawAmountController@OTCwithdrawOrderIdDetail` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/againDepositAmount` | `App\Http\Controllers\Admin\BatchAmountController@againDepositAmount` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/againWithdrawAmount` | `App\Http\Controllers\Admin\BatchAmountController@againWithdrawAmount` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/batchOperation` | `App\Http\Controllers\Admin\BatchAmountController@batchOperation` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/batchOperationWithdraw` | `App\Http\Controllers\Admin\BatchAmountController@batchOperationWithdraw` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/batchWithdrawApply` | `App\Http\Controllers\Admin\WithdrawAmountController@batchWithdrawApply` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/amount/batch_operation` | `App\Http\Controllers\Admin\BatchAmountController@batch_operation_browse` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/amount/batch_operation_withdraw` | `App\Http\Controllers\Admin\BatchAmountController@batch_operation_withdraw_browse` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/amount/channel_enable` | `App\Http\Controllers\Admin\PayChannelController@channel_enable` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/channel_enableV2` | `App\Http\Controllers\Admin\PayChannelController@channel_enableV2` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/confirm_options` | `App\Http\Controllers\Admin\RightsSummaryController@ConfirmWithdrawOrdeposit` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/amount/depositDownloadfile/{file}/{role}` | `App\Http\Controllers\Admin\DepositAmountController@DownloadFile` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/amount/depositExport` | `App\Http\Controllers\Admin\DepositAmountController@depositExport` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/depositFlowSearch` | `App\Http\Controllers\Admin\DepositAmountController@depositFlowSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/depositFlowSearchV2` | `App\Http\Controllers\Admin\DepositAmountController@depositFlowSearchV2` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/depositImportExcel` | `App\Http\Controllers\Admin\BatchAmountController@depositImportExcel` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/depositImportSearch` | `App\Http\Controllers\Admin\BatchAmountController@depositImportSearch` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/amount/deposit_flow` | `App\Http\Controllers\Admin\DepositAmountController@deposit_flow` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/amount/deposit_import_index` | `App\Http\Controllers\Admin\BatchAmountController@deposit_import_index` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/amount/generate_OTCorder` | `App\Http\Controllers\Admin\WithdrawAmountController@generateOTCorder` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/manual_confirm_options` | `App\Http\Controllers\Admin\RightsSummaryController@ManualConfirmWithdrawOrdeposit` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/amount/orderId_detail/{orderId}` | `App\Http\Controllers\Admin\WithdrawAmountController@withdrawOrderIdDetail` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/amount/order_status` | `App\Http\Controllers\Admin\WithdrawAmountController@withdrawOrderStaus` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/order_status_OTC` | `App\Http\Controllers\Admin\WithdrawAmountController@withdrawOrderStaus_OTC` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/rightsSumExport` | `App\Http\Controllers\Admin\RightsSummaryController@rightsSumExport` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/rightsSummarySearch` | `App\Http\Controllers\Admin\RightsSummaryController@RightsSummarySearch` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/amount/rightsSummarySearchDetail/{uid}/{status}/{sumdata}` | `App\Http\Controllers\Admin\RightsSummaryController@RightsSummarySearchDetail` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/amount/rights_downloadfile/{file}/{role}` | `App\Http\Controllers\Admin\RightsSummaryController@DownloadFile` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/amount/rights_summary` | `App\Http\Controllers\Admin\RightsSummaryController@rights_summary_browse` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/amount/show_channel_browse` | `App\Http\Controllers\Admin\PayChannelController@show_channel_browse` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/amount/undepositFlowSearch` | `App\Http\Controllers\Admin\UnDepositAmountController@undepositFlowSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/undepositFlowSearchV2` | `App\Http\Controllers\Admin\UnDepositAmountController@undepositFlowSearchV2` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/amount/undeposit_flow` | `App\Http\Controllers\Admin\UnDepositAmountController@undeposit_flow` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/amount/updateCurrOrderId` | `App\Http\Controllers\Admin\WithdrawAmountController@updateCurrOrderId` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/amount/whpj_rate` | `App\Http\Controllers\Admin\ExchangeRateController@whpj_rate_browse` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/amount/whpj_rate_save` | `App\Http\Controllers\Admin\ExchangeRateController@whpj_rate_save` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/withdrawApplySearch` | `App\Http\Controllers\Admin\WithdrawAmountController@withdrawApplySearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/withdrawApplySearchV2` | `App\Http\Controllers\Admin\WithdrawAmountController@withdrawApplySearchV2` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/amount/withdrawDownloadfile/{file}/{role}` | `App\Http\Controllers\Admin\WithdrawFlowController@DownloadFile` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/amount/withdrawExport` | `App\Http\Controllers\Admin\WithdrawAmountController@withdrawExport` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/withdrawFlowExport` | `App\Http\Controllers\Admin\WithdrawFlowController@withdrawFlowExport` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/withdrawFlowSearch` | `App\Http\Controllers\Admin\WithdrawFlowController@withdrawFlowSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/withdrawFlowSearchV2` | `App\Http\Controllers\Admin\WithdrawFlowController@withdrawFlowSearchV2` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/withdrawImportExcel` | `App\Http\Controllers\Admin\BatchAmountController@withdrawImportExcel` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/amount/withdrawImportSearch` | `App\Http\Controllers\Admin\BatchAmountController@withdrawImportSearch` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/amount/withdraw_apply` | `App\Http\Controllers\Admin\WithdrawAmountController@withdraw_apply` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/amount/withdraw_downloadfile/{file}/{role}` | `App\Http\Controllers\Admin\WithdrawAmountController@withdraw_downloadfile` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/amount/withdraw_flow` | `App\Http\Controllers\Admin\WithdrawFlowController@withdraw_flow` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/amount/withdraw_import_index` | `App\Http\Controllers\Admin\BatchAmountController@withdraw_import_index` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/auth/userCertifiedSearch` | `App\Http\Controllers\Admin\AuthenticationController@userCertifiedSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/auth/userCertifiedSearchV2` | `App\Http\Controllers\Admin\AuthenticationController@userCertifiedSearchV2` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/auth/userExaminSearch` | `App\Http\Controllers\Admin\AuthenticationController@userExaminSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/auth/userExaminSearchV2` | `App\Http\Controllers\Admin\AuthenticationController@userExaminSearchV2` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/auth/user_certified` | `App\Http\Controllers\Admin\AuthenticationController@user_certified` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/auth/user_certified_detail/{uid}` | `App\Http\Controllers\Admin\AuthenticationController@userCertifiedDetail` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/auth/user_examine` | `App\Http\Controllers\Admin\AuthenticationController@user_examine` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/auth/user_examine/detail/{mode}/{uid}` | `App\Http\Controllers\Admin\AuthenticationController@user_examine_detail` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/auth/user_idcard_bank` | `App\Http\Controllers\Admin\AuthenticationController@user_idcard_bank` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/auth/user_voucher/detail/{recId}/{uid}` | `App\Http\Controllers\Admin\AuthenticationController@voucherInfoDetail` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/auth/voucherInfoSearch` | `App\Http\Controllers\Admin\AuthenticationController@voucherInfoSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/auth/voucherInfoSearchV2` | `App\Http\Controllers\Admin\AuthenticationController@voucherInfoSearchV2` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/auth/voucherReviewSave` | `App\Http\Controllers\Admin\AuthenticationController@voucherReviewSave` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/auth/voucher_info_browse` | `App\Http\Controllers\Admin\AuthenticationController@voucher_info_browse` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/bigAgents` | `App\Http\Controllers\Admin\BigAgentController@index` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/bigAgents/add` | `App\Http\Controllers\Admin\BigAgentController@add` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/bigAgents/agentsListSearch` | `App\Http\Controllers\Admin\BigAgentController@bigAgentsListSearch` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/bigAgents/del` | `App\Http\Controllers\Admin\BigAgentController@del` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/bigAgents/save` | `App\Http\Controllers\Admin\BigAgentController@save` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/bigAgents/show/{id?}` | `App\Http\Controllers\Admin\BigAgentController@show` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/bigAgents/start` | `App\Http\Controllers\Admin\BigAgentController@start` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/bigAgents/stop` | `App\Http\Controllers\Admin\BigAgentController@stop` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/bigAgents/subAgentsListSearch` | `App\Http\Controllers\Admin\BigAgentController@getSubAgentsStats` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/bigAgents/updateInfo` | `App\Http\Controllers\Admin\BigAgentController@updateInfo` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/big_agents_list` | `App\Http\Controllers\Admin\BigAgentController@big_agents_list_browse` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/cancel/cancel_apply_nopass` | `App\Http\Controllers\Admin\CancellationController@cancel_apply_nopass` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/cancel/cancel_apply_pass` | `App\Http\Controllers\Admin\CancellationController@cancel_apply_pass` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/cancel/update_cancel` | `App\Http\Controllers\Admin\CancellationController@update_cancel` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/cancel/user_list` | `App\Http\Controllers\Admin\CancellationController@cancel_user_list` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/cancel/userlistSearch` | `App\Http\Controllers\Admin\CancellationController@userlistSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/cancel/userlistSearchV2` | `App\Http\Controllers\Admin\CancellationController@userlistSearchV2` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/captcha` | `App\Http\Controllers\Admin\LoginController@captcha` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/credit/againCreditAmount` | `App\Http\Controllers\Admin\BatchCreditController@againCreditAmount` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/credit/creditImportExcel` | `App\Http\Controllers\Admin\BatchCreditController@creditImportExcel` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/credit/creditImportSearch` | `App\Http\Controllers\Admin\BatchCreditController@creditImportSearch` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/credit/credit_import_index` | `App\Http\Controllers\Admin\BatchCreditController@credit_import_index` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/cust/add` | `App\Http\Controllers\Admin\CustomerController@cust_add_browse` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/cust/change_list` | `App\Http\Controllers\Admin\CustomerController@change_list` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/cust/custChangeListSearch` | `App\Http\Controllers\Admin\CustomerController@custChangeListSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/cust/custChangeListSearchV2` | `App\Http\Controllers\Admin\CustomerController@custChangeListSearchV2` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/cust/custListSearch` | `App\Http\Controllers\Admin\CustomerController@custListSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/cust/custListSearchV2` | `App\Http\Controllers\Admin\CustomerController@custListSearchV2` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/cust/cust_apply_nopass` | `App\Http\Controllers\Admin\CustomerController@cust_apply_nopass` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/cust/cust_apply_pass` | `App\Http\Controllers\Admin\CustomerController@cust_apply_pass` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/cust/cust_detail/{acc_uid}` | `App\Http\Controllers\Admin\CustomerController@cust_detail` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/cust/cust_save_add` | `App\Http\Controllers\Admin\CustomerController@cust_save_add` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/cust/cust_save_info` | `App\Http\Controllers\Admin\CustomerController@cust_save_info` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/cust/list` | `App\Http\Controllers\Admin\CustomerController@user_list` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/customer/{user_id?}` | `App\Http\Controllers\Admin\AgentControllerV3@CustomerList` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/fengXian/IpaddressDeatail/{idaddr}` | `App\Http\Controllers\Admin\FengXianManageController@fengXian_Ipaddress_detail` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/fengXian/IpaddressSearch` | `App\Http\Controllers\Admin\FengXianManageController@fengXian_Ipaddress_list` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/fengXian/Ipaddress_list` | `App\Http\Controllers\Admin\FengXianManageController@fengXian_Ipaddress_browse` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/fengXian/positionSearch` | `App\Http\Controllers\Admin\FengXianManageController@fengXian_position_list` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/fengXian/positionSearchv2` | `App\Http\Controllers\Admin\FengXianManageController@fengXian_position_listV2` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/fengXian/position_list` | `App\Http\Controllers\Admin\FengXianManageController@fengXian_position_browse` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/fengXian/profitSearch` | `App\Http\Controllers\Admin\FengXianManageController@fengXian_profit_list` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/fengXian/profitSearchV2` | `App\Http\Controllers\Admin\FengXianManageController@fengXian_profit_listV2` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/fengXian/profit_list` | `App\Http\Controllers\Admin\FengXianManageController@fengXian_profit_browse` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/gift/addressList` | `App\Http\Controllers\Admin\GiftController@getUserAddressList` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/gift/send_gift` | `App\Http\Controllers\Admin\GiftController@send_gift` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/gift/send_gift_browse` | `App\Http\Controllers\Admin\GiftController@send_gift_browse` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/gift/shipment_list` | `App\Http\Controllers\Admin\GiftController@shipment_list_search` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/gift/shipment_list_browse` | `App\Http\Controllers\Admin\GiftController@shipment_list_browse` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/gift/shipment_list_export` | `App\Http\Controllers\Admin\GiftController@shipment_list_export` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/group/add` | `App\Http\Controllers\Admin\GroupConfigController@group_add_index` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/group/pairSelect` | `App\Http\Controllers\Admin\GroupConfigController@pairSelect` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/group/store` | `App\Http\Controllers\Admin\GroupConfigController@store` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/group/update` | `App\Http\Controllers\Admin\GroupConfigController@update` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/group/user_group_add` | `App\Http\Controllers\Admin\UserGroupController@user_group_add` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/group/user_group_browse` | `App\Http\Controllers\Admin\UserGroupController@user_group_browse` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/group/user_group_delete` | `App\Http\Controllers\Admin\UserGroupController@user_group_delete` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/group/user_group_edit/{recId}` | `App\Http\Controllers\Admin\UserGroupController@user_group_edit` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/group/user_group_search` | `App\Http\Controllers\Admin\UserGroupController@user_group_search` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/group/user_group_searchV2` | `App\Http\Controllers\Admin\UserGroupController@user_group_searchV2` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/group/user_group_store` | `App\Http\Controllers\Admin\UserGroupController@user_group_store` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/group/user_group_update` | `App\Http\Controllers\Admin\UserGroupController@user_group_update` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/index` | `App\Http\Controllers\Admin\AdminController@index` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/login` | `App\Http\Controllers\Admin\LoginController@index` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/logon` | `App\Http\Controllers\Admin\LoginController@logon` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/logout` | `App\Http\Controllers\Admin\LoginController@logout` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/news/del` | `App\Http\Controllers\Admin\NewsInfoController@newDel` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/news/newsListSearch` | `App\Http\Controllers\Admin\NewsInfoController@newsListSearch` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/news/news_add_browse` | `App\Http\Controllers\Admin\NewsInfoController@new_add_browse` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/news/news_edit/{newsid}` | `App\Http\Controllers\Admin\NewsInfoController@news_edit` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/news/news_list_browse` | `App\Http\Controllers\Admin\NewsInfoController@news_list_browse` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/news/news_save` | `App\Http\Controllers\Admin\NewsInfoController@news_save` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/news/news_update` | `App\Http\Controllers\Admin\NewsInfoController@news_update` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/online` | `App\Http\Controllers\Admin\UserLoginOnlineController@index` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/online/search` | `App\Http\Controllers\Admin\UserLoginOnlineController@search` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/order/closeListSearch` | `App\Http\Controllers\Admin\AdminCloseOrderController@closeListSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/order/closeListSearchV2` | `App\Http\Controllers\Admin\AdminCloseOrderController@closeListSearchV2` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/order/close_list` | `App\Http\Controllers\Admin\AdminCloseOrderController@close_list` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/order/oneKeySearch` | `App\Http\Controllers\Admin\AdminWhsExpZeroController@oneKeySearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/order/oneKeyZero` | `App\Http\Controllers\Admin\AdminWhsExpZeroController@oneKeyZero` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/order/open_list` | `App\Http\Controllers\Admin\AdminOpenOrderController@open_list` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/order/openlistSearch` | `App\Http\Controllers\Admin\AdminOpenOrderController@openlistSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/order/openlistSearchV2` | `App\Http\Controllers\Admin\AdminOpenOrderController@openlistSearchV2` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/order/positionSummarySearch` | `App\Http\Controllers\Admin\PositionSummaryController@positionSummarySearch` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/order/position_summary_list` | `App\Http\Controllers\Admin\PositionSummaryController@position_summary_list` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/order/productionListSearch` | `App\Http\Controllers\Admin\AdminProductionController@productionListSearchV2` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/order/productionListSearchV2` | `App\Http\Controllers\Admin\AdminProductionController@productionListSearchV3` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/order/production_list` | `App\Http\Controllers\Admin\AdminProductionController@production_list` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/order/realCommissionListSearch` | `App\Http\Controllers\Admin\AdminRealCommissionController@realCommissionListSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/order/realCommissionListSearchV2` | `App\Http\Controllers\Admin\AdminRealCommissionController@realCommissionListSearchV2` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/order/real_commission_list` | `App\Http\Controllers\Admin\AdminRealCommissionController@real_commission_list` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/order/v2/parentPath` | `App\Http\Controllers\Admin\PositionSummaryController@parentPathV2` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/order/v2/positionSummarySearchV2` | `App\Http\Controllers\Admin\PositionSummaryController@positionSummarySearchV2` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/order/v2/subAgentsListSearchV2` | `App\Http\Controllers\Admin\PositionSummaryController@subAgentsListSearchV2` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/order/whsExpZeroListSearch` | `App\Http\Controllers\Admin\AdminWhsExpZeroController@whsExpZeroListSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/order/whsExpZeroListSearchV2` | `App\Http\Controllers\Admin\AdminWhsExpZeroController@whsExpZeroListSearchV2` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/order/whs_exp_zero_list` | `App\Http\Controllers\Admin\AdminWhsExpZeroController@whs_exp_zero_list` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/role` | `App\Http\Controllers\Admin\RoleController@index` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/role/add` | `App\Http\Controllers\Admin\RoleController@create` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/role/addsave` | `App\Http\Controllers\Admin\RoleController@store` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/role/del` | `App\Http\Controllers\Admin\RoleController@del` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/role/edit/{id?}` | `App\Http\Controllers\Admin\RoleController@show` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/role/editsave` | `App\Http\Controllers\Admin\RoleController@editsave` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/send/againSendSms` | `App\Http\Controllers\Admin\AgentControllerV3@againSendSms` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/userinfo` | `App\Http\Controllers\Admin\AdminController@UserInfo` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/userinfo/save` | `App\Http\Controllers\Admin\AdminController@UserIfoSave` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/userpwd` | `App\Http\Controllers\Admin\AdminController@UserPwd` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/userpwd/save` | `App\Http\Controllers\Admin\AdminController@UserPewdSave` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/welcome` | `App\Http\Controllers\Admin\AdminController@create` | `missing_uri` | GET |  | `` | `` |
| GET | `index/admin/withdraw/completed` | `App\Http\Controllers\Admin\WithdrawStatusController@completed` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/withdraw/completedExport` | `App\Http\Controllers\Admin\WithdrawStatusController@completedExport` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/withdraw/completedSearch` | `App\Http\Controllers\Admin\WithdrawStatusController@completedSearch` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/withdraw/failed` | `App\Http\Controllers\Admin\WithdrawStatusController@failed` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/withdraw/failedExport` | `App\Http\Controllers\Admin\WithdrawStatusController@failedExport` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/withdraw/failedSearch` | `App\Http\Controllers\Admin\WithdrawStatusController@failedSearch` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/withdraw/pending` | `App\Http\Controllers\Admin\WithdrawStatusController@pending` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/withdraw/pendingExport` | `App\Http\Controllers\Admin\WithdrawStatusController@pendingExport` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/withdraw/pendingSearch` | `App\Http\Controllers\Admin\WithdrawStatusController@pendingSearch` | `missing_uri` | POST |  | `` | `` |
| GET | `index/admin/withdraw/processing` | `App\Http\Controllers\Admin\WithdrawStatusController@processing` | `missing_uri` | GET |  | `` | `` |
| POST | `index/admin/withdraw/processingExport` | `App\Http\Controllers\Admin\WithdrawStatusController@processingExport` | `missing_uri` | POST |  | `` | `` |
| POST | `index/admin/withdraw/processingSearch` | `App\Http\Controllers\Admin\WithdrawStatusController@processingSearch` | `missing_uri` | POST |  | `` | `` |
| POST | `localRegisterNotifyByAgents` | `App\Http\Controllers\User\RegisterController@localRegisterNotifyByAgents` | `matched` |  |  | `legacy_local_register_notify_by_agents` | `App\Http\Controllers\Front\LegacyMaintenanceController@localRegisterNotifyByAgents` |
| GET | `open/order_detail/{orderId}/{orderType}/{role}` | `App\Http\Controllers\User\OpenOrderController@open_order_detail` | `matched` |  |  | `legacy_user_open_order_detail` | `App\Http\Controllers\Front\OrderController@openOrderDetail` |
| GET | `show/user_detail/{userId}/{role}` | `App\Http\Controllers\User\LoginController@show_user_detail` | `matched` |  |  | `legacy_user_detail` | `App\Http\Controllers\Front\AgentController@legacyUserDetailPage` |
| POST | `syncAgents` | `App\Http\Controllers\User\RegisterController@syncAgents` | `matched` |  |  | `legacy_sync_agents` | `App\Http\Controllers\Front\LegacyMaintenanceController@syncAgents` |
| POST | `syncDisableUserToT4` | `App\Http\Controllers\User\RegisterController@syncDisableUserToT4` | `matched` |  |  | `legacy_sync_disable_user_to_t4` | `App\Http\Controllers\Front\LegacyMaintenanceController@syncDisableUserToT4` |
| GET | `syncToT4ByLocalAgents` | `App\Http\Controllers\User\RegisterController@syncToT4ByLocalAgents` | `matched` |  |  | `legacy_sync_to_t4_by_local_agents` | `App\Http\Controllers\Front\LegacyMaintenanceController@syncToT4ByLocalAgents` |
| POST | `syncToT4ByLocalUser` | `App\Http\Controllers\User\RegisterController@syncToT4ByLocalUser` | `matched` |  |  | `legacy_sync_to_t4_by_local_user` | `App\Http\Controllers\Front\LegacyMaintenanceController@syncToT4ByLocalUser` |
| POST | `syncUser` | `App\Http\Controllers\User\RegisterController@syncUser` | `matched` |  |  | `legacy_sync_user` | `App\Http\Controllers\Front\LegacyMaintenanceController@syncUser` |
| GET | `test` | `Closure` | `matched` |  |  | `legacy_test_register_page` | `App\Http\Controllers\Front\LegacyMaintenanceController@testRegisterPage` |
| POST | `test/deposit` | `App\Http\Controllers\HelloWordController@deposit` | `matched` |  |  | `legacy_test_deposit` | `App\Http\Controllers\Front\LegacyMaintenanceController@testDeposit` |
| POST | `test/getAccountInfo` | `App\Http\Controllers\HelloWordController@getAccountInfo` | `matched` |  |  | `legacy_test_account_info` | `App\Http\Controllers\Front\LegacyMaintenanceController@testGetAccountInfo` |
| POST | `test/helloRegister` | `App\Http\Controllers\HelloWordController@helloRegisterUser` | `matched` |  |  | `legacy_test_hello_register` | `App\Http\Controllers\Front\LegacyMaintenanceController@testHelloRegister` |
| POST | `test_export` | `App\Http\Controllers\User\PositionSummaryController@position_summary_export` | `matched` |  |  | `legacy_test_export` | `App\Http\Controllers\Front\LegacyMaintenanceController@testExport` |
| GET | `test_info` | `App\Http\Controllers\admin\AgentController@test_info` | `matched` |  |  | `legacy_test_info` | `App\Http\Controllers\Front\LegacyMaintenanceController@testInfo` |
| GET | `test_order` | `App\Http\Controllers\Admin\WithdrawAmountController@OTCwithdrawOrderIdDetail` | `matched` |  |  | `legacy_test_order` | `App\Http\Controllers\Front\LegacyMaintenanceController@testOrder` |
| GET | `test_rights_sum` | `App\Http\Controllers\Admin\RightsSummaryController@sum_agents_online_settlement_amount` | `matched` |  |  | `legacy_test_rights_sum` | `App\Http\Controllers\Front\LegacyMaintenanceController@testRightsSum` |
| GET | `test_serach/{id}` | `App\Http\Controllers\User\PositionSummaryController@test_serach_id` | `matched` |  |  | `legacy_test_search` | `App\Http\Controllers\Front\LegacyMaintenanceController@testSearch` |
| GET | `test_sms` | `App\Http\Controllers\User\LoginController@test_register` | `matched` |  |  | `legacy_test_sms` | `App\Http\Controllers\Front\LegacyMaintenanceController@testSms` |
| GET | `trades_exp_zero` | `App\Http\Controllers\Admin\AdminWhsExpZeroController@trades_whs_exp_zero` | `matched` |  |  | `legacy_trades_exp_zero` | `App\Http\Controllers\Front\LegacyMaintenanceController@tradesExpZero` |
| GET | `user/account` | `App\Http\Controllers\User\UserCenterController@user_account_browse` | `matched` |  |  | `legacy_user_account_page` | `App\Http\Controllers\Front\LegacyPageController@account` |
| GET | `user/address/add` | `App\Http\Controllers\User\AddressController@address_add_browse` | `matched` |  |  | `legacy_user_address_add_page` | `App\Http\Controllers\Front\LegacyPageController@address` |
| GET | `user/address/info/{recId}` | `App\Http\Controllers\User\AddressController@address_edit_browse` | `matched` |  |  | `legacy_user_address_edit_page` | `App\Http\Controllers\Front\LegacyPageController@address` |
| GET | `user/address/list` | `App\Http\Controllers\User\AddressController@address_list_browse` | `matched` |  |  | `legacy_user_address_page` | `App\Http\Controllers\Front\LegacyPageController@address` |
| POST | `user/address/search` | `App\Http\Controllers\User\AddressController@addressSearch` | `matched` |  |  | `legacy_user_address_search` | `App\Http\Controllers\Front\GiftController@addressSearch` |
| POST | `user/address/update` | `App\Http\Controllers\User\AddressController@addressUpdate` | `matched` |  |  | `legacy_user_address_update` | `App\Http\Controllers\Front\GiftController@addressUpdate` |
| POST | `user/agents/changePassword` | `App\Http\Controllers\Admin\BigNumberController@changePasswordSave` | `matched` |  |  | `legacy_user_agents_change_password` | `App\Http\Controllers\Front\BigNumberController@changePasswordSave` |
| POST | `user/agents/close/closeOrderSearch` | `App\Http\Controllers\Admin\BigNumberController@bigCloseOrderSearch` | `matched` |  |  | `legacy_user_agents_close_order_search` | `App\Http\Controllers\Front\BigNumberController@bigCloseOrderSearch` |
| GET | `user/agents/close/order` | `App\Http\Controllers\Admin\BigNumberController@big_close_order_browse` | `matched` |  |  | `legacy_user_agents_close_order` | `App\Http\Controllers\Front\BigNumberController@big_close_order_browse` |
| GET | `user/agents/editpsw` | `App\Http\Controllers\Admin\BigNumberController@agents_editpsw_browse` | `matched` |  |  | `legacy_user_agents_edit_password_page` | `App\Http\Controllers\Front\LegacyPageController@profile` |
| POST | `user/agents/editpsw_save` | `App\Http\Controllers\User\UserCenterController@agents_editpsw_save` | `matched` |  |  | `legacy_user_agents_edit_password_save` | `App\Http\Controllers\Front\ProfileController@user_editpsw_save` |
| GET | `user/agents/index` | `App\Http\Controllers\Admin\BigNumberController@agentsIndex` | `matched` |  |  | `legacy_user_agents_index` | `App\Http\Controllers\Front\BigNumberController@agentsIndex` |
| GET | `user/agents/loginOut` | `App\Http\Controllers\Admin\BigNumberController@loginOut` | `matched` |  |  | `legacy_user_agents_logout` | `App\Http\Controllers\Front\BigNumberController@loginOut` |
| GET | `user/agents/main/home` | `App\Http\Controllers\Admin\BigNumberController@agentsMainHome` | `matched` |  |  | `legacy_user_agents_main_home` | `App\Http\Controllers\Front\BigNumberController@agentsMainHome` |
| POST | `user/agents/open/openOrderSearch` | `App\Http\Controllers\Admin\BigNumberController@bigOpenOrderSearch` | `matched` |  |  | `legacy_user_agents_open_order_search` | `App\Http\Controllers\Front\BigNumberController@bigOpenOrderSearch` |
| GET | `user/agents/open/order` | `App\Http\Controllers\Admin\BigNumberController@big_open_order_browse` | `matched` |  |  | `legacy_user_agents_open_order` | `App\Http\Controllers\Front\BigNumberController@big_open_order_browse` |
| POST | `user/agents/position/positionSummarySearch` | `App\Http\Controllers\Admin\BigNumberController@bigPositionSummarySearch` | `matched` |  |  | `legacy_user_agents_position_search` | `App\Http\Controllers\Front\BigNumberController@bigPositionSummarySearch` |
| POST | `user/agents/position/subAgentsListSearch` | `App\Http\Controllers\Admin\BigNumberController@bigSubPositionSummaryStats` | `matched` |  |  | `legacy_user_agents_position_sub_search` | `App\Http\Controllers\Front\BigNumberController@bigSubPositionSummaryStats` |
| GET | `user/agents/position/summary` | `App\Http\Controllers\Admin\BigNumberController@position_agents_summary_browse` | `matched` |  |  | `legacy_user_agents_position_summary` | `App\Http\Controllers\Front\BigNumberController@position_agents_summary_browse` |
| GET | `user/agents/proxy/list` | `App\Http\Controllers\Admin\BigNumberController@proxy_agents_list_browse` | `matched` |  |  | `legacy_user_agents_proxy_list` | `App\Http\Controllers\Front\BigNumberController@proxy_agents_list_browse` |
| POST | `user/agents/proxy/proxySearch` | `App\Http\Controllers\Admin\BigNumberController@bigNumberListSearch` | `matched` |  |  | `legacy_user_agents_proxy_search` | `App\Http\Controllers\Front\BigNumberController@bigNumberListSearch` |
| POST | `user/agents/proxy/proxySearchBySub` | `App\Http\Controllers\Admin\BigNumberController@bigNumberListSearchBySubAgents` | `matched` |  |  | `legacy_user_agents_proxy_search_by_sub` | `App\Http\Controllers\Front\BigNumberController@bigNumberListSearchBySubAgents` |
| POST | `user/agents/relationShipHtml` | `App\Http\Controllers\User\UserCenterController@relationShipHtmlV2` | `matched` |  |  | `legacy_user_agents_relationship_html` | `App\Http\Controllers\Front\ProfileController@relationShipHtmlV2` |
| POST | `user/agents/signIn` | `App\Http\Controllers\Admin\BigNumberController@agentsSignIn` | `matched` |  |  | `legacy_user_agents_sign_in` | `App\Http\Controllers\Front\BigNumberController@agentsSignIn` |
| GET | `user/captcha` | `App\Http\Controllers\User\LoginController@captcha` | `matched` |  |  | `legacy_user_captcha` | `App\Http\Controllers\Front\AuthController@registerCaptcha` |
| GET | `user/center` | `App\Http\Controllers\User\UserCenterController@user_info_browse` | `matched` |  |  | `legacy_user_center_page` | `App\Http\Controllers\Front\LegacyPageController@profile` |
| POST | `user/center/ajaxCancelAccount` | `App\Http\Controllers\User\UserCenterController@ajaxCancelAccount` | `matched` |  |  | `legacy_user_center_ajax_cancel` | `App\Http\Controllers\Front\CancelController@ajaxCancelAccount` |
| GET | `user/center/cancelAccount` | `App\Http\Controllers\User\UserCenterController@cancelAccount_browse` | `matched` |  |  | `legacy_user_center_cancel_page` | `App\Http\Controllers\Front\LegacyPageController@cancelAccount` |
| POST | `user/center/cancelVerifyInfo` | `App\Http\Controllers\User\UserCenterController@cancelVerifyInfo` | `matched` |  |  | `legacy_user_center_cancel_verify_info` | `App\Http\Controllers\Front\ProfileController@cancelVerifyInfo` |
| POST | `user/center/cancelVerifyPassSendCode` | `App\Http\Controllers\User\UserCenterController@cancelVerifyPassSendCode` | `matched` |  |  | `legacy_user_center_cancel_verify_code` | `App\Http\Controllers\Front\ProfileController@cancelVerifyPassSendCode` |
| POST | `user/center/changeBankCardSendCode` | `App\Http\Controllers\User\UserCenterController@changeBankCardSendCode` | `matched` |  |  | `legacy_user_center_change_bank_code` | `App\Http\Controllers\Front\ProfileController@changeBankCardSendCode` |
| POST | `user/center/changeBankCardVerifyCode` | `App\Http\Controllers\User\UserCenterController@changeBankCardVerifyCode` | `matched` |  |  | `legacy_user_center_change_bank_verify_code` | `App\Http\Controllers\Front\ProfileController@changeBankCardVerifyCode` |
| GET | `user/center/updPhoneEmail/{type}` | `App\Http\Controllers\User\UserCenterController@updPhoneEmail_browse` | `matched` |  |  | `legacy_user_center_phone_email_page` | `App\Http\Controllers\Front\LegacyPageController@profile` |
| POST | `user/center/updVerifyPassSendCode` | `App\Http\Controllers\User\UserCenterController@updVerifyPassSendCode` | `matched` |  |  | `legacy_user_center_update_verify_code` | `App\Http\Controllers\Front\ProfileController@updVerifyPassSendCode` |
| POST | `user/center/updatePhoneEmailInfo` | `App\Http\Controllers\User\UserCenterController@updatePhoneEmailInfo` | `matched` |  |  | `legacy_user_center_update_phone_email` | `App\Http\Controllers\Front\ProfileController@updatePhoneEmailInfo` |
| POST | `user/center/updateVerifyInfo` | `App\Http\Controllers\User\UserCenterController@updateVerifyInfo` | `matched` |  |  | `legacy_user_center_update_verify_info` | `App\Http\Controllers\Front\ProfileController@updateVerifyInfo` |
| GET | `user/center/uploadBank` | `App\Http\Controllers\User\UserCenterController@uploadBank_browse` | `matched` |  |  | `legacy_user_center_upload_bank_page` | `App\Http\Controllers\Front\LegacyPageController@profile` |
| POST | `user/center/uploadBankCard` | `App\Http\Controllers\User\UserCenterController@uploadBankCard` | `matched` |  |  | `legacy_user_center_upload_bank_card` | `App\Http\Controllers\Front\ProfileController@uploadBankCard` |
| GET | `user/center/uploadChangeBank/{type}` | `App\Http\Controllers\User\UserCenterController@uploadChangeBank_browse` | `matched` |  |  | `legacy_user_center_change_bank_page` | `App\Http\Controllers\Front\LegacyPageController@profile` |
| POST | `user/center/uploadChangeBankCard` | `App\Http\Controllers\User\UserCenterController@uploadChangeBankCard` | `matched` |  |  | `legacy_user_center_upload_change_bank_card` | `App\Http\Controllers\Front\ProfileController@uploadChangeBankCard` |
| POST | `user/center/uploadHeadImg` | `App\Http\Controllers\User\UserCenterController@uploadHeadImg` | `matched` |  |  | `legacy_user_center_upload_head_img` | `App\Http\Controllers\Front\ProfileController@uploadHeadImg` |
| GET | `user/center/uploadHead_browse` | `App\Http\Controllers\User\UserCenterController@uploadHead_browse` | `matched` |  |  | `legacy_user_center_upload_head_page` | `App\Http\Controllers\Front\LegacyPageController@profile` |
| GET | `user/center/uploadIdCard` | `App\Http\Controllers\User\UserCenterController@uploadIdCard_browse` | `matched` |  |  | `legacy_user_center_upload_id_page` | `App\Http\Controllers\Front\LegacyPageController@profile` |
| POST | `user/center/uploadIdCard` | `App\Http\Controllers\User\UserCenterController@uploadIdCard` | `matched` |  |  | `legacy_user_center_upload_id_card` | `App\Http\Controllers\Front\ProfileController@uploadIdCard` |
| GET | `user/change/list` | `App\Http\Controllers\User\DirectCustomerController@cust_list_chang_group_browse` | `matched` |  |  | `legacy_user_customer_change_list_page` | `App\Http\Controllers\Front\LegacyPageController@groupChange` |
| POST | `user/change_account_save` | `App\Http\Controllers\User\UserCenterController@change_account_save` | `matched` |  |  | `legacy_user_change_account_save` | `App\Http\Controllers\Front\AccountController@changeAccountSave` |
| POST | `user/change_password` | `App\Http\Controllers\User\UserForgetPswController@saveChangePassword` | `matched` |  |  | `legacy_user_change_password` | `App\Http\Controllers\Front\ForgotPasswordController@saveChangePassword` |
| POST | `user/check_user_info` | `App\Http\Controllers\User\UserForgetPswController@checkUserInfo` | `matched` |  |  | `legacy_user_forget_check_info` | `App\Http\Controllers\Front\ForgotPasswordController@checkUserInfo` |
| POST | `user/close/closeOrder2Search` | `App\Http\Controllers\User\CloseOrder2Controller@closeOrder2Search` | `matched` |  |  | `legacy_user_close_order2_search` | `App\Http\Controllers\Front\OrderController@closeOrder2Search` |
| POST | `user/close/closeOrderSearch` | `App\Http\Controllers\User\CloseOrderController@closeOrderSearch` | `matched` |  |  | `legacy_user_close_order_search` | `App\Http\Controllers\Front\OrderController@closeOrderSearch` |
| GET | `user/close/order` | `App\Http\Controllers\User\CloseOrderController@close_order_browse` | `matched` |  |  | `legacy_user_close_order_page` | `App\Http\Controllers\Front\LegacyPageController@orderClosed` |
| GET | `user/close/order2` | `App\Http\Controllers\User\CloseOrder2Controller@close_order2_browse` | `matched` |  |  | `legacy_user_close_order2_page` | `App\Http\Controllers\Front\LegacyPageController@orderClosed` |
| GET | `user/cust/change/group/{uid}` | `App\Http\Controllers\User\DirectCustomerController@changeDirectCustGroupInfo` | `matched` |  |  | `legacy_user_customer_change_group_page` | `App\Http\Controllers\Front\LegacyPageController@groupChange` |
| POST | `user/cust/change/group_edit` | `App\Http\Controllers\User\DirectCustomerController@changeDirectCustGroupEdit` | `matched` |  |  | `legacy_user_customer_change_group_edit` | `App\Http\Controllers\Front\AgentController@changeDirectCustGroupEdit` |
| POST | `user/cust/directCustChangeListSearch` | `App\Http\Controllers\User\DirectCustomerController@directCustChangeListSearch` | `matched` |  |  | `legacy_user_customer_direct_change_search` | `App\Http\Controllers\Front\AgentController@directCustChangeListSearch` |
| POST | `user/cust/directCustListSearch` | `App\Http\Controllers\User\DirectCustomerController@directCustListSearch` | `matched` |  |  | `legacy_user_customer_direct_list_search` | `App\Http\Controllers\Front\AgentController@directCustListSearch` |
| GET | `user/cust/list` | `App\Http\Controllers\User\DirectCustomerController@cust_list_browse` | `matched` |  |  | `legacy_user_customer_list_page` | `App\Http\Controllers\Front\LegacyPageController@customerList` |
| POST | `user/cust/loginHistorySearch/{uid}` | `App\Http\Controllers\User\DirectCustomerController@loginHistorySearch` | `matched` |  |  | `legacy_user_customer_login_history` | `App\Http\Controllers\Front\AgentController@legacyLoginHistorySearch` |
| GET | `user/cust/show_direct_cust_info/{role}/{uid}` | `App\Http\Controllers\User\DirectCustomerController@show_direct_cust_info` | `matched` |  |  | `legacy_user_customer_detail` | `App\Http\Controllers\Front\AgentController@legacyUserDetailPage` |
| GET | `user/deposit` | `App\Http\Controllers\User\UserDepositController@deposit_browse` | `matched` |  |  | `legacy_user_deposit_page` | `App\Http\Controllers\Front\LegacyPageController@deposit` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_btb_notify` | `App\Http\Controllers\PayController\PayCallBackController@deposit_btb_notify` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；BTB 异步支付通知只允许 POST，避免 GET 或写方法绕过回调边界。 | `legacy_user_deposit_btb_notify` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_btb_return` | `App\Http\Controllers\PayController\PayCallBackController@deposit_btb_return` | `intentional_method_restriction` | DELETE,PATCH,POST,PUT | 旧 Route::any 暴露了未使用的方法；BTB 同步返回页只允许 GET，且不能据此确认支付成功。 | `legacy_user_deposit_btb_return` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_exlink_bbnotify` | `App\Http\Controllers\PayController\PayCallBackController@deposit_exlink_bbnotify` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；Exlink BB 异步通知只允许 POST 并统一执行验签和状态机。 | `legacy_user_deposit_exlink_bbnotify` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_exlink_bbreturn` | `App\Http\Controllers\PayController\PayCallBackController@deposit_exlink_bbreturn` | `intentional_method_restriction` | DELETE,PATCH,POST,PUT | 旧 Route::any 暴露了未使用的方法；Exlink BB 同步返回只允许 GET 且仅展示待确认状态。 | `legacy_user_deposit_exlink_bbreturn` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_exlink_fbnotify` | `App\Http\Controllers\PayController\PayCallBackController@deposit_exlink_fbnotify` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；Exlink FB 异步通知只允许 POST 并统一执行验签和状态机。 | `legacy_user_deposit_exlink_fbnotify` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_exlink_fbreturn` | `App\Http\Controllers\PayController\PayCallBackController@deposit_exlink_fbreturn` | `intentional_method_restriction` | DELETE,PATCH,POST,PUT | 旧 Route::any 暴露了未使用的方法；Exlink FB 同步返回只允许 GET 且仅展示待确认状态。 | `legacy_user_deposit_exlink_fbreturn` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_notfiy` | `App\Http\Controllers\PayController\PayCallBackController@deposit_notify_response_success` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；默认支付异步通知只允许 POST，订单变化必须经过验签与幂等状态机。 | `legacy_user_deposit_notify` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_notfiy2` | `App\Http\Controllers\PayController\PayCallBackController@deposit_notify_response_success2` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；第二支付异步通知只允许 POST，订单变化必须经过验签与幂等状态机。 | `legacy_user_deposit_notify2` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_notfiy_otc` | `App\Http\Controllers\PayController\PayCallBackController@deposit_notify_response_success_otc` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；OTC 入金异步通知只允许 POST，配置或验签不完整时失败关闭。 | `legacy_user_deposit_notify_otc` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_passto_notify` | `App\Http\Controllers\PayController\PayCallBackController@deposit_passto_notify` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；PassTo 异步通知只允许 POST 并统一执行验签和状态机。 | `legacy_user_deposit_passto_notify` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_request` | `App\Http\Controllers\User\UserDepositController@deposit_request` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；入金创建属于资金写操作，只允许 POST 并复用现代幂等建单链。 | `legacy_user_deposit_request` | `App\Http\Controllers\Front\DepositController@deposit_request` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_request_otc` | `App\Http\Controllers\User\UserDepositController@deposit_request_otc` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；OTC 入金创建属于资金写操作，只允许 POST 并在无可信协议时失败关闭。 | `legacy_user_deposit_request_otc` | `App\Http\Controllers\Front\DepositController@deposit_request_otc` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_return` | `App\Http\Controllers\PayController\PayCallBackController@deposit_return_response_success` | `intentional_method_restriction` | DELETE,PATCH,POST,PUT | 旧 Route::any 暴露了未使用的方法；默认支付同步返回只允许 GET，不能直接修改订单状态。 | `legacy_user_deposit_return` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_return2` | `App\Http\Controllers\PayController\PayCallBackController@deposit_return_response_success2` | `intentional_method_restriction` | DELETE,PATCH,POST,PUT | 旧 Route::any 暴露了未使用的方法；第二支付同步返回只允许 GET，不能直接修改订单状态。 | `legacy_user_deposit_return2` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_switch_notify` | `App\Http\Controllers\PayController\PayCallBackController@deposit_switch_notify` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；Switch 异步通知只允许 POST 并统一执行验签和状态机。 | `legacy_user_deposit_switch_notify` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_tigerpay_notify` | `App\Http\Controllers\PayController\PayCallBackController@deposit_tigerpay_notify` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；TigerPay 异步通知只允许 POST 并统一执行 RSA 验签和状态机。 | `legacy_user_deposit_tigerpay_notify` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_wppay_notify` | `App\Http\Controllers\PayController\PayCallBackController@deposit_wppay_notify` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；WPPay 异步通知只允许 POST 并统一执行验签和状态机。 | `legacy_user_deposit_wppay_notify` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| DELETE,GET,PATCH,POST,PUT | `user/deposit_wppay_return` | `App\Http\Controllers\PayController\PayCallBackController@deposit_wppay_return` | `intentional_method_restriction` | DELETE,PATCH,POST,PUT | 旧 Route::any 暴露了未使用的方法；WPPay 同步返回只允许 GET，不能直接修改订单状态。 | `legacy_user_deposit_wppay_return` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| GET | `user/editpsw` | `App\Http\Controllers\User\UserCenterController@user_editpsw_browse` | `matched` |  |  | `legacy_user_edit_password_page` | `App\Http\Controllers\Front\LegacyPageController@profile` |
| POST | `user/editpsw_save` | `App\Http\Controllers\User\UserCenterController@user_editpsw_save` | `matched` |  |  | `legacy_user_edit_password_save` | `App\Http\Controllers\Front\ProfileController@user_editpsw_save` |
| POST | `user/flow/depositExport` | `App\Http\Controllers\User\CustomerFlowController@depositExport` | `matched` |  |  | `legacy_user_deposit_export` | `App\Http\Controllers\Front\FlowController@depositExport` |
| POST | `user/flow/depositFlowSearch` | `App\Http\Controllers\User\CustomerFlowController@depositFlowSearch` | `matched` |  |  | `legacy_user_flow_deposit_search` | `App\Http\Controllers\Front\FlowController@depositFlowSearch` |
| POST | `user/flow/directAgentsDepositFlowSearch` | `App\Http\Controllers\User\CustomerFlowController@directDepositFlowSearch` | `matched` |  |  | `legacy_user_flow_direct_agents_deposit_search` | `App\Http\Controllers\Front\FlowController@directDepositFlowSearch` |
| POST | `user/flow/directAgentsWithdrawalFlowSearch` | `App\Http\Controllers\User\CustomerFlowController@directWithdrawalFlowSearch` | `matched` |  |  | `legacy_user_flow_direct_agents_withdrawal_search` | `App\Http\Controllers\Front\FlowController@directWithdrawalFlowSearch` |
| POST | `user/flow/directDepositFlowSearch` | `App\Http\Controllers\User\CustomerFlowController@directDepositFlowSearch` | `matched` |  |  | `legacy_user_flow_direct_deposit_search` | `App\Http\Controllers\Front\FlowController@directDepositFlowSearch` |
| POST | `user/flow/directWithdrawalFlowSearch` | `App\Http\Controllers\User\CustomerFlowController@directWithdrawalFlowSearch` | `matched` |  |  | `legacy_user_flow_direct_withdrawal_search` | `App\Http\Controllers\Front\FlowController@directWithdrawalFlowSearch` |
| GET | `user/flow/downloadfile/{file}/{role}` | `App\Http\Controllers\User\CustomerFlowController@DownloadFile` | `matched` |  |  | `legacy_user_download_file` | `App\Http\Controllers\Front\FlowController@downloadFile` |
| GET | `user/flow/main` | `App\Http\Controllers\User\CustomerFlowController@main_browse` | `matched` |  |  | `legacy_user_flow_page` | `App\Http\Controllers\Front\LegacyPageController@flow` |
| POST | `user/flow/withdrawApplyFlowSearch` | `App\Http\Controllers\User\CustomerFlowController@withdrawApplyFlowSearch` | `matched` |  |  | `legacy_user_flow_withdraw_apply_search` | `App\Http\Controllers\Front\FlowController@withdrawApplyFlowSearch` |
| POST | `user/flow/withdrawalFlowSearch` | `App\Http\Controllers\User\CustomerFlowController@withdrawalFlowSearch` | `matched` |  |  | `legacy_user_flow_withdrawal_search` | `App\Http\Controllers\Front\FlowController@withdrawalFlowSearch` |
| POST | `user/forgetPasswordInfoVerification` | `App\Http\Controllers\User\UserForgetPswController@forgetPasswordInfoVerification` | `matched` |  |  | `legacy_user_forget_verify` | `App\Http\Controllers\Front\ForgotPasswordController@forgetPasswordInfoVerification` |
| GET | `user/forget_password` | `App\Http\Controllers\User\UserForgetPswController@forget_password_browse` | `matched` |  |  | `legacy_user_forget_password_page` | `App\Http\Controllers\Front\ForgotPasswordController@showForgotPassword` |
| POST | `user/forgetpswSendCode` | `App\Http\Controllers\User\UserForgetPswController@forgetpswSendCode` | `matched` |  |  | `legacy_user_forget_send_code` | `App\Http\Controllers\Front\ForgotPasswordController@sendResetCode` |
| GET | `user/front/message` | `App\Http\Controllers\User\LoginController@frontMsg` | `matched` |  |  | `legacy_user_front_message` | `App\Http\Controllers\Front\DashboardController@frontMsg` |
| GET | `user/gift/list` | `App\Http\Controllers\User\AddressController@gift_list_browse` | `matched` |  |  | `legacy_user_gift_page` | `App\Http\Controllers\Front\LegacyPageController@gift` |
| POST | `user/gift/search` | `App\Http\Controllers\User\AddressController@giftSearch` | `matched` |  |  | `legacy_user_gift_search` | `App\Http\Controllers\Front\GiftController@giftSearch` |
| GET | `user/index` | `App\Http\Controllers\User\LoginController@index` | `matched` |  |  | `legacy_user_index_page` | `App\Http\Controllers\Front\LegacyPageController@dashboard` |
| GET | `user/index/index` | `App\Http\Controllers\User\LoginController@index` | `matched` |  |  | `legacy_user_index_index_page` | `App\Http\Controllers\Front\LegacyPageController@dashboard` |
| GET | `user/index/login` | `App\Http\Controllers\User\LoginController@loginGmtk` | `matched` |  |  | `legacy_user_index_login_page` | `App\Http\Controllers\Front\AuthController@showLogin` |
| GET | `user/index/register/{register_type?}/{user_id?}/{comm_type?}` | `App\Http\Controllers\User\RegisterController@indexGmtk` | `matched` |  |  | `legacy_user_index_register_page` | `App\Http\Controllers\Front\AuthController@legacyRegisterPage` |
| POST | `user/index/signIn` | `App\Http\Controllers\User\LoginController@signIn` | `matched` |  |  | `legacy_user_index_sign_in` | `App\Http\Controllers\Front\AuthController@legacySignIn` |
| POST | `user/indexreg` | `App\Http\Controllers\User\LoginController@index` | `matched` |  |  | `legacy_user_indexreg_page` | `App\Http\Controllers\Front\LegacyPageController@dashboard` |
| GET | `user/login` | `App\Http\Controllers\User\LoginController@loginGmtk` | `matched` |  |  | `legacy_user_login_page` | `App\Http\Controllers\Front\AuthController@showLogin` |
| GET | `user/loginOut` | `App\Http\Controllers\User\LoginController@loginOut` | `matched` |  |  | `legacy_user_logout` | `App\Http\Controllers\Front\LegacyPageController@logout` |
| POST | `user/main/hasShowGiftTips` | `App\Http\Controllers\User\LoginController@hasShowGiftTips` | `matched` |  |  | `legacy_user_has_show_gift_tips` | `App\Http\Controllers\Front\DashboardController@hasShowGiftTips` |
| GET | `user/main/home` | `App\Http\Controllers\User\LoginController@mainHome` | `matched` |  |  | `legacy_user_main_home_page` | `App\Http\Controllers\Front\LegacyPageController@dashboard` |
| POST | `user/main/hot/news` | `App\Http\Controllers\User\LoginController@hotNews` | `matched` |  |  | `legacy_user_hot_news` | `App\Http\Controllers\Front\DashboardController@hotNews` |
| POST | `user/main/hot/newsV2` | `App\Http\Controllers\User\LoginController@hotNewsV2` | `matched` |  |  | `legacy_user_hot_news_v2` | `App\Http\Controllers\Front\DashboardController@hotNewsV2` |
| POST | `user/multiple/file` | `App\Http\Controllers\User\UploadFileController@multipleFileUpload` | `matched` |  |  | `legacy_user_multiple_file` | `App\Http\Controllers\Front\UploadController@multipleFileUpload` |
| GET | `user/news/news_detail/{newsId}` | `App\Http\Controllers\User\NewsListController@news_detail` | `matched` |  |  | `legacy_user_news_detail` | `App\Http\Controllers\Front\NewsController@newsDetail` |
| POST | `user/newsListSearch` | `App\Http\Controllers\User\NewsListController@newsListSearch` | `matched` |  |  | `legacy_user_news_list_search` | `App\Http\Controllers\Front\NewsController@newsListSearch` |
| GET | `user/news_list_browse` | `App\Http\Controllers\User\NewsListController@news_list_browse` | `matched` |  |  | `legacy_user_news_page` | `App\Http\Controllers\Front\LegacyPageController@news` |
| POST | `user/offweb/feedback` | `App\Http\Controllers\User\RegisterController@demandFeedback` | `matched` |  |  | `legacy_user_offweb_feedback` | `App\Http\Controllers\Front\LegacyPageController@feedback` |
| POST | `user/open/openOrder2Search` | `App\Http\Controllers\User\OpenOrder2Controller@openOrder2Search` | `matched` |  |  | `legacy_user_open_order2_search` | `App\Http\Controllers\Front\OrderController@openOrder2Search` |
| POST | `user/open/openOrderSearch` | `App\Http\Controllers\User\OpenOrderController@openOrderSearch` | `matched` |  |  | `legacy_user_open_order_search` | `App\Http\Controllers\Front\OrderController@openOrderSearch` |
| GET | `user/open/order` | `App\Http\Controllers\User\OpenOrderController@open_order_browse` | `matched` |  |  | `legacy_user_open_order_page` | `App\Http\Controllers\Front\LegacyPageController@orderOpen` |
| GET | `user/open/order2` | `App\Http\Controllers\User\OpenOrder2Controller@open_order2_browse` | `matched` |  |  | `legacy_user_open_order2_page` | `App\Http\Controllers\Front\LegacyPageController@orderOpen` |
| GET | `user/position/comm_summary` | `App\Http\Controllers\User\PositionSummaryController@_exte_mt4_sync_comm_summary` | `matched` |  |  | `legacy_user_position_comm_summary_page` | `App\Http\Controllers\Front\LegacyPageController@positionSummary` |
| GET | `user/position/comm_summaryv2` | `App\Http\Controllers\User\PositionSummaryController@realTimerealTimeV2` | `matched` |  |  | `legacy_user_position_comm_summary_v2_page` | `App\Http\Controllers\Front\LegacyPageController@positionSummary` |
| POST | `user/position/positionSummary2Search` | `App\Http\Controllers\User\PositionSummary2Controller@positionSummary2Search` | `matched` |  |  | `legacy_user_position_summary2_search` | `App\Http\Controllers\Front\PositionController@positionSummary2Search` |
| POST | `user/position/positionSummarySearch` | `App\Http\Controllers\User\PositionSummaryController@positionSummarySearch` | `matched` |  |  | `legacy_user_position_summary_search` | `App\Http\Controllers\Front\PositionController@positionSummary` |
| GET | `user/position/summary` | `App\Http\Controllers\User\PositionSummaryController@position_summary_browse` | `matched` |  |  | `legacy_user_position_summary_page` | `App\Http\Controllers\Front\LegacyPageController@positionSummary` |
| GET | `user/position/summary/deatil/{id}` | `App\Http\Controllers\User\PositionSummaryController@position_summary_detail` | `matched` |  |  | `legacy_user_position_summary_detail_page` | `App\Http\Controllers\Front\LegacyPageController@positionSummary` |
| GET | `user/position/summary2` | `App\Http\Controllers\User\PositionSummary2Controller@position_summary2_browse` | `matched` |  |  | `legacy_user_position_summary2_page` | `App\Http\Controllers\Front\LegacyPageController@positionSummary` |
| POST | `user/position/v2/positionSummaryClickSearch` | `App\Http\Controllers\User\PositionSummaryController@positionSummaryClickSearch` | `matched` |  |  | `legacy_user_position_click_search` | `App\Http\Controllers\Front\PositionController@clickSearch` |
| POST | `user/position/v2/subAgentsListSearchV2` | `App\Http\Controllers\User\PositionSummaryController@subPositionSummarySearch` | `matched` |  |  | `legacy_user_position_sub_agents_search` | `App\Http\Controllers\Front\PositionController@subPositionSummary` |
| GET | `user/proxy/confirm` | `App\Http\Controllers\User\ProxyListController@proxy_confirm_browse` | `matched` |  |  | `legacy_user_proxy_confirm_page` | `App\Http\Controllers\Front\LegacyPageController@proxyConfirm` |
| POST | `user/proxy/confirmLevelChange` | `App\Http\Controllers\User\ProxyListController@confirmLevelChange` | `matched` |  |  | `legacy_user_proxy_confirm_change` | `App\Http\Controllers\Front\AgentController@confirmLevelChange` |
| POST | `user/proxy/directUserCommTrans` | `App\Http\Controllers\User\ProxyListController@directUserCommTrans` | `matched` |  |  | `legacy_user_proxy_commission_transfer` | `App\Http\Controllers\Front\AgentController@directUserCommTrans` |
| GET | `user/proxy/direct_cust_detail/{puid}` | `App\Http\Controllers\User\ProxyListController@proxy_direct_cust_detail` | `matched` |  |  | `legacy_user_proxy_direct_customer_page` | `App\Http\Controllers\Front\LegacyPageController@proxyDirectCustomerDetail` |
| POST | `user/proxy/direct_cust_detail_list` | `App\Http\Controllers\User\ProxyListController@direct_cust_detail_list` | `matched` |  |  | `legacy_user_proxy_direct_customer_list` | `App\Http\Controllers\Front\AgentController@directCustDetailList` |
| GET | `user/proxy/direct_user_commTrans_browse/{uid}` | `App\Http\Controllers\User\ProxyListController@direct_user_commTrans_browse` | `matched` |  |  | `legacy_user_proxy_commission_transfer_page` | `App\Http\Controllers\Front\LegacyPageController@commissionTransfer` |
| POST | `user/proxy/getSubAgentsGrpIdList` | `App\Http\Controllers\User\ProxyListController@getSubAgentsGrpIdList` | `matched` |  |  | `legacy_user_proxy_group_list` | `App\Http\Controllers\Front\AgentController@getSubAgentsGrpIdList` |
| GET | `user/proxy/list` | `App\Http\Controllers\User\ProxyListController@proxy_list_browse` | `matched` |  |  | `legacy_user_proxy_list_page` | `App\Http\Controllers\Front\LegacyPageController@proxyList` |
| POST | `user/proxy/parentPath` | `App\Http\Controllers\User\ProxyListController@getParentPath` | `matched` |  |  | `legacy_user_proxy_parent_path` | `App\Http\Controllers\Front\AgentController@getParentPath` |
| POST | `user/proxy/proxyConfirmSearch` | `App\Http\Controllers\User\ProxyListController@proxyConfirmSearch` | `matched` |  |  | `legacy_user_proxy_confirm_search` | `App\Http\Controllers\Front\AgentController@proxyConfirmSearch` |
| POST | `user/proxy/proxyListSearch` | `App\Http\Controllers\User\ProxyListController@proxyListSearch` | `matched` |  |  | `legacy_user_proxy_list_search` | `App\Http\Controllers\Front\AgentController@proxyListSearch` |
| POST | `user/realtime/realtimeRebateSearch` | `App\Http\Controllers\User\RealCommissionController@realtimeRebateSearch` | `matched` |  |  | `legacy_user_realtime_rebate_search` | `App\Http\Controllers\Front\CommissionController@realtimeRebateSearch` |
| GET | `user/realtime/rebate` | `App\Http\Controllers\User\RealCommissionController@realtime_rebate_browse` | `matched` |  |  | `legacy_user_realtime_rebate_page` | `App\Http\Controllers\Front\LegacyPageController@realtimeRebate` |
| GET | `user/realtime/rebate_detail/{orderNo}/{role}` | `App\Http\Controllers\User\RealCommissionController@realtime_rebate_detail` | `matched` |  |  | `legacy_user_realtime_rebate_detail` | `App\Http\Controllers\Front\CommissionController@realtimeRebateDetail` |
| GET | `user/register/captcha` | `App\Http\Controllers\User\RegisterController@registercaptcha` | `matched` |  |  | `legacy_user_register_captcha` | `App\Http\Controllers\Front\AuthController@registerCaptcha` |
| GET | `user/register/hotnews` | `App\Http\Controllers\User\RegisterController@hotnews` | `matched` |  |  | `legacy_user_register_hotnews` | `App\Http\Controllers\Front\DashboardController@registerHotNews` |
| GET | `user/register/rebateDeposit` | `App\Http\Controllers\User\RegisterController@orderRebateDeposit` | `matched` |  |  | `legacy_user_register_rebate_deposit` | `App\Http\Controllers\Front\LegacyMaintenanceController@orderRebateDeposit` |
| POST | `user/register/registerSendCode` | `App\Http\Controllers\User\RegisterController@registerSendCode` | `matched` |  |  | `legacy_user_register_send_code` | `App\Http\Controllers\Front\AuthController@registerSendCode` |
| POST | `user/register/registerVerifyInfo` | `App\Http\Controllers\User\RegisterController@registerVerifyInfo` | `matched` |  |  | `legacy_user_register_verify_info` | `App\Http\Controllers\Front\AuthController@registerVerifyInfo` |
| POST | `user/register/registerinto` | `App\Http\Controllers\User\RegisterController@registerinto` | `matched` |  |  | `legacy_user_register_into` | `App\Http\Controllers\Front\AuthController@register` |
| GET | `user/register/testemail` | `App\Http\Controllers\User\RegisterController@testemail` | `matched` |  |  | `legacy_user_register_testemail` | `App\Http\Controllers\Front\AuthController@checkEmail` |
| GET | `user/register/testmodel` | `App\Http\Controllers\User\RegisterController@testmodel` | `matched` |  |  | `legacy_user_register_testmodel` | `App\Http\Controllers\Front\LegacyMaintenanceController@testmodel` |
| GET | `user/register/{register_type?}/{user_id?}/{comm_type?}` | `App\Http\Controllers\User\RegisterController@indexGmtk` | `matched` |  |  | `legacy_user_register_page` | `App\Http\Controllers\Front\AuthController@legacyRegisterPage` |
| POST | `user/relationShip` | `App\Http\Controllers\User\UserCenterController@relationShip` | `matched` |  |  | `legacy_user_relationship` | `App\Http\Controllers\Front\ProfileController@relationShip` |
| POST | `user/relationShipHtml` | `App\Http\Controllers\User\UserCenterController@relationShipHtml` | `matched` |  |  | `legacy_user_relationship_html` | `App\Http\Controllers\Front\ProfileController@relationShipHtml` |
| POST | `user/signIn` | `App\Http\Controllers\User\LoginController@signIn` | `matched` |  |  | `legacy_user_sign_in` | `App\Http\Controllers\Front\AuthController@legacySignIn` |
| POST | `user/upload/file` | `App\Http\Controllers\User\UploadFileController@singleFileUpload` | `matched` |  |  | `legacy_user_upload_file` | `App\Http\Controllers\Front\UploadController@singleFileUpload` |
| POST | `user/user_voucher_save` | `App\Http\Controllers\User\UserCenterController@user_voucher_save` | `matched` |  |  | `legacy_user_voucher_save` | `App\Http\Controllers\Front\AccountController@userVoucherSave` |
| GET | `user/voucher` | `App\Http\Controllers\User\UserCenterController@user_voucher_browse` | `matched` |  |  | `legacy_user_voucher_page` | `App\Http\Controllers\Front\LegacyPageController@voucher` |
| POST | `user/voucher/voucherSearch` | `App\Http\Controllers\User\UserVoucherController@voucherSearch` | `matched` |  |  | `legacy_user_voucher_search` | `App\Http\Controllers\Front\AccountController@voucherList` |
| GET | `user/voucher/voucher_browse` | `App\Http\Controllers\User\UserVoucherController@voucher_browse` | `matched` |  |  | `legacy_user_voucher_browse_page` | `App\Http\Controllers\Front\LegacyPageController@voucher` |
| GET | `user/withdraw` | `App\Http\Controllers\User\UserWithdrawController@withdraw_browse` | `matched` |  |  | `legacy_user_withdraw_page` | `App\Http\Controllers\Front\LegacyPageController@withdraw` |
| DELETE,GET,PATCH,POST,PUT | `user/withdraw_notfiy_otc` | `App\Http\Controllers\Admin\WithdrawAmountController@withdraw_notify_response_success_otc` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；OTC 出金异步通知只允许 POST，配置或验签不完整时失败关闭。 | `legacy_user_withdraw_notify_otc` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| POST | `user/withdraw_request` | `App\Http\Controllers\User\UserWithdrawController@withdraw_request` | `matched` |  |  | `legacy_user_withdraw_request` | `App\Http\Controllers\Front\WithdrawController@withdraw_request` |
| POST | `user/withdraw_request_OTC` | `App\Http\Controllers\User\UserWithdrawController@withdraw_request_OTC` | `matched` |  |  | `legacy_user_withdraw_request_otc` | `App\Http\Controllers\Front\WithdrawController@withdraw_request_OTC` |
| DELETE,GET,PATCH,POST,PUT | `user/withdraw_verify_otc` | `App\Http\Controllers\Admin\WithdrawAmountController@withdraw_verify_success_otc` | `intentional_method_restriction` | DELETE,GET,PATCH,PUT | 旧 Route::any 暴露了未使用的方法；OTC 出金验证回调只允许 POST，配置或验签不完整时失败关闭。 | `legacy_user_withdraw_verify_otc` | `App\Http\Controllers\Front\PaymentNotifyController@legacyCallback` |
| GET | `whstest` | `App\Http\Controllers\Admin\AdminWhsExpZeroController@whsCustListSearch` | `matched` |  |  | `legacy_whs_test` | `App\Http\Controllers\Front\LegacyMaintenanceController@whsTest` |
