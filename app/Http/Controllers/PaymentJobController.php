<?php

namespace App\Http\Controllers;
use App\Models\PaymentJobs;
use App\Services\Crypto;
use App\Services\NativeCoin;
use App\Services\TokenManage;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use phpseclib\Math\BigInteger;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Web3;
use Web3p\EthereumTx\Transaction;

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

    public function Jobs(){
        $jobs = PaymentJobs::where('status', 'pending')->limit(5)->get();
        if($jobs == null){return response()->json(['success' => false, 'message' => 'Jobs not found',]);}
        foreach ($jobs as $job){
            if ($job->created_at->lt(now()->subMinutes(20))) {
                $job->status = 'expired';
                $job->save();
                Http::post($job->webhook_url, [
                    'status' => 'expired',
                    'data' => [
                        'invoice_id' => $job->id,
                    ],
                ]);
                continue;
            }

            $balance = $job->type == 'native' ?
                $this->nativeCoin->getAnyNativeCoinBalance($this->crypto->decrypt($job->wallet_address),"$job->rpc_url")
                : $this->tokenManage->getTokenBalance($this->crypto->decrypt($job->wallet_address),"$job->contract_address","$job->rpc_url");
            if($balance["balance"] == 0){continue;}
            $data = $this->tokenManage->getEtherSupportTokenTransactions($this->crypto->decrypt($job->wallet_address), $job->id,'56','0x55d398326f99059fF775485246999027B3197955');
            if($data != null){
                Http::post($job->webhook_url,[
                    'status' => true,
                    'data' => $data,
                ]);
                $job->status = 'completed';
                $job->save();
            }
        }
    }



}
