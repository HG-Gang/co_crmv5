<?php

namespace App\Http\Controllers\Front;

use App\Constants\ResponseCode;
use App\Models\DepositRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Payment gateway notify/return endpoints (legacy /user/deposit_* paths ported).
 */
class PaymentNotifyController extends FrontBaseController
{
    public function notify(Request $request, string $gateway)
    {
        Log::info('front.payment.notify', [
            'gateway' => $gateway,
            'payload' => $request->all(),
        ]);

        $orderNo = $request->input('order_no', $request->input('local_order_no', $request->input('out_trade_no')));
        if ($orderNo) {
            $record = DepositRecord::where('local_order_no', $orderNo)->first();
            if ($record && $request->input('status') === 'success') {
                $record->update([
                    'status' => '02',
                    'payment_time' => time(),
                ]);
            }
        }

        return response('success');
    }

    public function returnPage(Request $request, string $gateway)
    {
        return redirect('/front/deposit?gateway=' . urlencode($gateway) . '&status=' . urlencode((string) $request->input('status', 'pending')));
    }
}
