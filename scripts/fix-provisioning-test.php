<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 14:38
 */

/**
 * 修复 UserMt4ProvisioningGatewayTest 方法名的历史一次性脚本。
 *
 * 文件功能：
 * - 按行号从后往前恢复被损坏的方法名（避免替换偏移）。
 *
 * 适用场景：
 * - 仅历史修复用，当前代码无需再执行。
 */

// 按行号恢复 UserMt4ProvisioningGatewayTest 的方法名（从后往前替换避免偏移）。
$f = 'tests/Unit/UserMt4ProvisioningGatewayTest.php';
$lines = file($f);

$fix = [
    415 => 'accountInfo',
    407 => 'register',
    401 => '__construct',
    391 => 'newGateway',
    378 => 'managerReturning',
    370 => 'payload',
    328 => 'reconciliationResponseProvider',
    290 => 'registerResponseProvider',
    244 => '__call',
    240 => '__construct',
    237 => 'test_account_info_mapping_does_not_invent_balance',
    224 => 'nonPositiveReconciliationUserIdProvider',
    203 => 'test_reconcile_rejects_non_positive_user_id',
    173 => 'test_unknown_status_reconcile_queries_account_info_only',
    153 => 'test_identity_verified_preserves_provider_reference',
    134 => 'register',
    130 => '__construct',
    127 => 'test_invalid_protocol_value_rejected_before_reconcile',
    107 => 'accountInfo',
    101 => 'register',
    97 => '__construct',
    91 => 'test_transport_exception_during_registration_is_unknown',
    72 => 'test_provision_classifies_register_response_without_account_info',
    55 => 'test_failure_rejects_empty_error_code',
    39 => 'test_result_exposes_terminal_and_retry_states',
];

foreach ($fix as $lineNo => $method) {
    $idx = $lineNo - 1;
    if (! isset($lines[$idx])) {
        echo "SKIP L$lineNo (不存在)\n";
        continue;
    }
    if (preg_match('/^(\s*(?:public|private|protected)\s+(?:static\s+)?)function\s*\(/', $lines[$idx], $m)) {
        $lines[$idx] = $m[1] . 'function ' . $method . '(' . substr($lines[$idx], strpos($lines[$idx], '('));
        echo "L$lineNo => $method\n";
    } else {
        echo "SKIP L$lineNo (非损坏行): " . trim($lines[$idx]) . "\n";
    }
}

file_put_contents($f, implode('', $lines));
echo "done\n";
