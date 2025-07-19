<?php

namespace App\Services;

use GuzzleHttp\Client;
use phpseclib\Math\BigInteger;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Utils;
use Web3\Web3;
use Web3p\EthereumTx\Transaction;

class NativeCoin extends Crypto {

    public function sendAnyChainNativeBalance(
        $fromAddress,
        $toAddress,
        $privateKey,
        $amount,$rpcUrl,
        $chainId): array
    {
        try {
            $client = new Client();

            if (is_null($amount)) {
                throw new \InvalidArgumentException('amount is null.');
            }

            if (stripos($amount, 'e') !== false) {
                $amount = sprintf('%.18f', (float)$amount);
            }

            $amount = trim((string)$amount);
            $amount = str_replace(',', '', $amount);

            if (!is_numeric($amount)) {
                throw new \InvalidArgumentException("Invalid amount format: [$amount]");
            }

            $amountInWei = bcmul($amount, bcpow('10', '18', 0), 0);

            $response = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_getTransactionCount',
                    'params' => [$fromAddress, 'pending'],
                    'id' => 1
                ]
            ]);
            $nonceHex = json_decode($response->getBody()->getContents(), true)['result'];
            $nonce = hexdec($nonceHex);

            $response = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_gasPrice',
                    'params' => [],
                    'id' => 2
                ]
            ]);
            $gasPriceHex = json_decode($response->getBody()->getContents(), true)['result'];
            $gasPrice = hexdec($gasPriceHex);

            $gasLimit = 21000;

            $tx = new Transaction([
                'nonce' => '0x' . dechex($nonce),
                'gasPrice' => '0x' . dechex($gasPrice),
                'gas' => '0x' . dechex($gasLimit),
                'to' => $toAddress,
                'value' => '0x' . dechex($amountInWei),
                'chainId' => $chainId
            ]);

            $signedTx = $tx->sign(Crypto::class->decrypt($privateKey));

            $response = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_sendRawTransaction',
                    'params' => ['0x' . $signedTx],
                    'id' => 3
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['result'])) {
                return [
                    'success' => true,
                    'tx_hash' => $result['result']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['error']['message'] ?? 'Unknown error occurred'
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

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
                $balance = new BigInteger(ltrim($bal, '0x'), 16);
                $done = true;
            });

            $start = microtime(true);
            while (!$done && (microtime(true) - $start) < 5) {
                usleep(100000); // 100ms
            }

            if (!$done) {
                throw new \Exception('Timeout while fetching balance.');
            }
            $rawBalance = $balance->toString();

            return [
                'success' => true,
                'balance' => $rawBalance,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }


}


