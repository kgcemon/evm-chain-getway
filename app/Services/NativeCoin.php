<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Web3;
use Web3p\EthereumTx\Transaction;


class NativeCoin extends Crypto {


    public function sendAnyChainNativeBalance(
        $fromAddress,
        $toAddress,
        $encryptedPrivateKey,
        $rpcUrl,
        $chainId
    ) {
        try {
            if (!extension_loaded('gmp')) {
                throw new Exception('GMP extension is required for large number handling.');
            }
            $client = new Client();

            $nonceRes = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_getTransactionCount',
                    'params' => [$fromAddress, 'pending'],
                    'id' => 1
                ]
            ]);
            $nonceResult = json_decode($nonceRes->getBody()->getContents(), true);
            if (!isset($nonceResult['result']) || !preg_match('/^0x[0-9a-fA-F]+$/', $nonceResult['result'])) {
                throw new Exception('Invalid nonce: ' . ($nonceResult['error']['message'] ?? 'Unknown error'));
            }
            $nonce = hexdec($nonceResult['result']);
            $gasPriceRes = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_gasPrice',
                    'params' => [],
                    'id' => 2
                ]
            ]);
            $gasPriceResult = json_decode($gasPriceRes->getBody()->getContents(), true);
            if (!isset($gasPriceResult['result']) || !preg_match('/^0x[0-9a-fA-F]+$/', $gasPriceResult['result'])) {
                throw new Exception('Invalid gas price: ' . ($gasPriceResult['error']['message'] ?? 'Unknown error'));
            }
            $gasPrice = hexdec($gasPriceResult['result']);
            $gasLimit = 21000;
            $gasCost = gmp_mul(gmp_init($gasPrice), gmp_init($gasLimit));
            $balanceRes = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_getBalance',
                    'params' => [$fromAddress, 'latest'],
                    'id' => 3
                ]
            ]);
            $balanceResult = json_decode($balanceRes->getBody()->getContents(), true);
            if (!isset($balanceResult['result']) || !preg_match('/^0x[0-9a-fA-F]+$/', $balanceResult['result'])) {
                throw new Exception('Invalid balance response: ' . ($balanceResult['error']['message'] ?? 'Unknown error'));
            }
            $balanceHex = $balanceResult['result'];
            $balanceWei = gmp_init($balanceHex, 16); // Parse hex directly

            // Log values for debugging
            \Log::debug("BalanceHex: $balanceHex, BalanceWei: " . gmp_strval($balanceWei) . ", GasCost: " . gmp_strval($gasCost));

            // Validate balance
            if (gmp_cmp($balanceWei, 0) <= 0) {
                return [
                    'success' => false,
                    'message' => 'Account balance is zero or negative.'
                ];
            }
            $amountWei = gmp_sub($balanceWei, $gasCost);
            if (gmp_cmp($amountWei, 0) <= 0) {
                return [
                    'success' => false,
                    'message' => 'Insufficient balance to cover gas fees.'
                ];
            }
            $amountWeiStr = gmp_strval($amountWei);
            if (!is_numeric($amountWeiStr) || $amountWeiStr === '') {
                throw new Exception('Invalid amountWeiStr for bcdiv: ' . $amountWeiStr);
            }

            // Convert to Ether
            $amountInEther = bcdiv($amountWeiStr, bcpow('10', '18'), 18);


            \Log::debug("AmountWei: " . gmp_strval($amountWei) . ", AmountWeiStr: $amountWeiStr, AmountInEther: $amountInEther");

            // Step 4: Create transaction
            $tx = new Transaction([
                'nonce' => '0x' . dechex($nonce),
                'gasPrice' => '0x' . dechex($gasPrice),
                'gas' => '0x' . dechex($gasLimit),
                'to' => $toAddress,
                'value' => '0x' . gmp_strval($amountWei, 16), // Convert to hex
                'chainId' => $chainId
            ]);

            $privateKey = $encryptedPrivateKey;

            $signedTx = '0x' . $tx->sign($privateKey);


            $sendRes = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_sendRawTransaction',
                    'params' => [$signedTx],
                    'id' => 4
                ]
            ]);
            $result = json_decode($sendRes->getBody()->getContents(), true);

            if (isset($result['result'])) {
                return [
                    'success' => true,
                    'tx_hash' => $result['result'],
                    'sent_amount' => $amountInEther
                ];
            }

            return [
                'success' => false,
                'message' => $result['error']['message'] ?? 'Unknown error occurred'
            ];

        } catch (\Exception $e) {
            \Log::error("Error in sendAnyChainNativeBalance: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }





    //native balance
    public function getAnyNativeCoinBalance($address, $rpcUrl)
    {
        try {
            $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 30)));
            $balance = null;
            $done = false;

            $web3->eth->getBalance($address, function ($err, $bal) use (&$balance, &$done) {
                if ($err !== null) {
                    throw new \Exception($err->getMessage());
                }
                $balance = $bal;
                $done = true;
            });
            $start = microtime(true);
            while (!$done && (microtime(true) - $start) < 5) {
                usleep(100000); // 0.1 sec
            }

            if (!$done) {
                throw new \Exception('Timeout while fetching balance.');
            }

            $rawBalance = $balance->toString();

            $humanReadableBalance = bcdiv($rawBalance, bcpow('10', '18'), 18);
            $finalBalance = rtrim(rtrim($humanReadableBalance, '0'), '.');

            return [
                'success' => true,
                'balance' => $finalBalance,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

}


