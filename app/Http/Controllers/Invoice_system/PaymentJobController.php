<?php

namespace App\Http\Controllers\Invoice_system;
use App\Http\Controllers\Controller;
use App\Models\PaymentJobs;
use App\Models\User;
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
                if ($job->created_at->lt(now()->subMinutes(20))) {
                    $this->expireJob($job);
                    continue;
                }

                $walletAddress = $this->crypto->decrypt($job->wallet_address);
                $walletKey     = $this->crypto->decrypt($job->key);
                $user = User::where('id', $job->user_id)->first();

                if ($job->type === 'native') {
                    $res = $this->nativeCoin->sendAnyChainNativeBalance(
                        $walletAddress,
                        $user->wallet_address,
                        $walletKey,
                        $job->rpc_url,
                        $job->chain_id
                    );
                    if (!empty($res['success']) && !empty($res['tx_hash'])) {
                      $data =  Http::post($job->webhook_url, [
                            'status'     => true,
                            'invoice_id' => $job->invoice_id,
                            'amount' => $res['sent_amount'],
                            'tx_hash'    => $res["tx_hash"],
                        ]);
                        $job->status = 'completed';
                        $job->tx_hash = $res["tx_hash"];
                        $job->save();
                        return $data;
                    } else {
                        break;
                    }
                }elseif ($job->type == 'token') {
                  $data = $this->tokenManage->sendAnyChainTokenTransaction(
                      "$walletAddress",
                      $job->contract_address,
                      "0x86ed528e743b77a727badc5e24da4b41da9839e0",
                      "$walletKey",
                      "$job->rpc_url",
                      "$job->chain_id",
                      "$user->wallet_address",
                      "$user->two_factor_secret",
                      null,
                      true
                  );

                  $mainData = $data->getData();
                  if ($mainData->status === true) {
                      $job->status = 'completed';
                      $job->tx_hash = $mainData->txHash;
                      $job->save();
                      return  Http::post($job->webhook_url,$data->getData());
                  }else{
                      continue;
                  }

                }

            } catch (\Throwable $e) {
                return $e->getMessage();
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
                'invoice_id' => $job->invoice_id,
                'message'   => 'time has been expired.',
                ],
        ]);
    }


}
