<?php

namespace App\Http\Controllers;
use App\Models\PaymentJobs;
use App\Services\Crypto;
use App\Services\NativeCoin;
use App\Services\TokenManage;
use Illuminate\Support\Facades\Http;

class PaymentJobController extends Controller
{
    protected Crypto $crypto;
    protected TokenManage $tokenManage;
    protected NativeCoin $nativeCoin;
    public function __construct(Crypto $crypto, TokenManage $tokenManage, NativeCoin $nativeCoin){
        $this->crypto = $crypto;
        $this->tokenManage = $tokenManage;
        $this->nativeCoin = $nativeCoin;
    }

    public function Jobs()
    {

        $jobs = PaymentJobs::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get();

        if ($jobs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No pending jobs found.',
            ]);
        }

        foreach ($jobs as $job) {
            try {
//                if ($job->created_at->lt(now()->subMinutes(20))) {
//                    $this->expireJob($job);
//                    continue;
//                }

                $walletAddress = $this->crypto->decrypt($job->wallet_address);
                $walletKey     = $this->crypto->decrypt($job->key);

                $balance = $job->type === 'native'
                    ? $this->nativeCoin->getAnyNativeCoinBalance($walletAddress, $job->rpc_url)
                    : $this->tokenManage->getTokenBalance($walletAddress, $job->contract_address, $job->rpc_url);

                if (empty($balance['balance']) || $balance['balance'] == 0) {
                    continue;
                }

                if ($job->type === 'native') {
                    $res = $this->nativeCoin->sendAnyChainNativeBalance(
                        $walletAddress,
                        "0x86ed528e743b77a727badc5e24da4b41da9839e0",
                        $walletKey,
                        $job->rpc_url,
                        $job->chain_id
                    );

                    if (!empty($res['success']) && !empty($res['tx_hash'])) {
                        Http::post($job->webhook_url, [
                            'status'     => true,
                            'invoice_id' => $job->id,
                            'tx_hash'    => $res["tx_hash"],
                        ]);

                        $job->status = 'completed';
                        $job->save();
                    } else {
                        break;
                    }
                }elseif ($job->type === 'token' && !empty($job->contract_address)) {

                }

            } catch (\Throwable $e) {
                continue;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Job processing completed.',
        ]);
    }


    protected function expireJob(PaymentJobs $job): void
    {
        $job->status = 'expired';
        $job->save();

        Http::post($job->webhook_url, [
            'status' => 'expired',
            'data' => [
                'invoice_id' => $job->id,
            ],
        ]);
    }




}
