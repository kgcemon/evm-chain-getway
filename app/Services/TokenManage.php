<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use phpseclib\Math\BigInteger;

class TokenManage extends Crypto{

    public function getTokenBalance($address, $tokenAddress,$rpcUrl): string
    {
        try {
            $client = new Client();
            $data = '0x70a08231' . str_pad(substr($address, 2), 64, '0', STR_PAD_LEFT);
            $response = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_call',
                    'params' => [
                        [
                            'to' => $tokenAddress,
                            'data' => $data
                        ],
                        'latest'
                    ],
                    'id' => 1
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['result'])) {
                $balanceHex = $result['result'];
                $balance = new BigInteger(ltrim($balanceHex, '0x'), 16);
                $decimals = 18;
                $balanceString = $balance->toString();
                return bcdiv($balanceString, bcpow('10', (string)$decimals), $decimals);
            } else {
                return '0';
            }
        } catch (\Exception $e) {
            return  $e->getMessage();
        }
    }


    public function getEtherSupportTokenTransactions($address, $invoiceId,$chainID,$contractaddress): array
    {

        $response = Http::get('https://api.etherscan.io/v2/api', [
            'chainid' => $chainID, // BSC chain ID
            'module' => 'account',
            'action' => 'tokentx',
            'contractaddress' => $contractaddress, //'0x55d398326f99059fF775485246999027B3197955', // BSC USDT contract
            'address' => $address,
            'page' => 1,
            'offset' => 1,
            'sort' => 'desc',
            'apikey' => env('ETHERSCAN_API_KEY'),
        ]);

        $data = $response->json();
        if (isset($data['status']) && $data['status'] == '1') {
            return collect($data['result'])->map(function ($tx) use ($invoiceId) {
                return [
                    'invoice_id' => $invoiceId,
                    'hash' => $tx['hash'],
                    'from' => $tx['from'],
                    'to' => $tx['to'],
                    'value' => bcdiv($tx['value'], bcpow('10', $tx['tokenDecimal']), 6),
                    'token_symbol' => $tx['tokenSymbol'],
                    'timestamp' => date('Y-m-d H:i:s', $tx['timeStamp']),
                ];
            })->all();
        }

        return ['error' => $data['message'] ?? 'Failed to fetch transactions'];
    }


}

