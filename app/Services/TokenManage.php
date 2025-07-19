<?php

namespace App\Services;

use Exception;
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


    public function sendAnyChainFullTokenBalance(
        $fromAddress,
        $toAddress,
        $encryptedPrivateKey,
        $rpcUrl,
        $chainId,
        $tokenContractAddress
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

            // Step 2: Get gas price
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
            $gasLimit = 65000; // Typical gas limit for ERC-20 transfer
            $gasCost = gmp_mul(gmp_init($gasPrice), gmp_init($gasLimit));

            // Step 3: Get native token balance (e.g., BNB) to cover gas fees
            $nativeBalanceRes = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_getBalance',
                    'params' => [$fromAddress, 'latest'],
                    'id' => 3
                ]
            ]);
            $nativeBalanceResult = json_decode($nativeBalanceRes->getBody()->getContents(), true);
            if (!isset($nativeBalanceResult['result']) || !preg_match('/^0x[0-9a-fA-F]+$/', $nativeBalanceResult['result'])) {
                throw new Exception('Invalid native balance response: ' . ($nativeBalanceResult['error']['message'] ?? 'Unknown error'));
            }
            $nativeBalanceWei = gmp_init($nativeBalanceResult['result'], 16);

            // Check if enough native token to cover gas fees
            if (gmp_cmp($nativeBalanceWei, $gasCost) < 0) {
                return [
                    'success' => false,
                    'message' => 'Insufficient native token (e.g., BNB) to cover gas fees.'
                ];
            }

            // Step 4: Get token balance
            $balanceOfData = '0x70a08231' . str_pad(substr($fromAddress, 2), 64, '0', STR_PAD_LEFT); // balanceOf(address)
            $tokenBalanceRes = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_call',
                    'params' => [
                        [
                            'to' => $tokenContractAddress,
                            'data' => $balanceOfData
                        ],
                        'latest'
                    ],
                    'id' => 4
                ]
            ]);
            $tokenBalanceResult = json_decode($tokenBalanceRes->getBody()->getContents(), true);
            if (!isset($tokenBalanceResult['result']) || !preg_match('/^0x[0-9a-fA-F]+$/', $tokenBalanceResult['result'])) {
                throw new Exception('Invalid token balance response: ' . ($tokenBalanceResult['error']['message'] ?? 'Unknown error'));
            }
            $tokenBalance = gmp_init($tokenBalanceResult['result'], 16);

            // Log values for debugging
            \Log::debug("TokenContract: $tokenContractAddress, TokenBalance: " . gmp_strval($tokenBalance) . ", NativeBalanceWei: " . gmp_strval($nativeBalanceWei) . ", GasCost: " . gmp_strval($gasCost));

            // Check if token balance is zero
            if (gmp_cmp($tokenBalance, 0) <= 0) {
                return [
                    'success' => false,
                    'message' => 'Token balance is zero or negative.'
                ];
            }

            $decimalsData = '0x313ce567'; // decimals()
            $decimalsRes = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_call',
                    'params' => [
                        [
                            'to' => $tokenContractAddress,
                            'data' => $decimalsData
                        ],
                        'latest'
                    ],
                    'id' => 5
                ]
            ]);
            $decimalsResult = json_decode($decimalsRes->getBody()->getContents(), true);
            $decimals = isset($decimalsResult['result']) ? hexdec($decimalsResult['result']) : 18; // Default to 18 if call fails

            // Convert token balance to human-readable format
            $tokenBalanceStr = gmp_strval($tokenBalance);
            $tokenBalanceHuman = bcdiv($tokenBalanceStr, bcpow('10', (string)$decimals), $decimals);

            // Step 6: Create transfer transaction
            $transferData = '0xa9059cbb' . str_pad(substr($toAddress, 2), 64, '0', STR_PAD_LEFT) . str_pad(gmp_strval($tokenBalance, 16), 64, '0', STR_PAD_LEFT); // transfer(address,uint256)
            $tx = new Transaction([
                'nonce' => '0x' . dechex($nonce),
                'gasPrice' => '0x' . dechex($gasPrice),
                'gas' => '0x' . dechex($gasLimit),
                'to' => $tokenContractAddress,
                'value' => '0x0', // No native token sent
                'data' => $transferData,
                'chainId' => $chainId
            ]);

            $privateKey = $encryptedPrivateKey;

            $signedTx = '0x' . $tx->sign($privateKey);

            // Step 7: Send transaction
            $sendRes = $client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_sendRawTransaction',
                    'params' => [$signedTx],
                    'id' => 6
                ]
            ]);
            $result = json_decode($sendRes->getBody()->getContents(), true);

            // Log transaction details
            \Log::debug("TokenBalance: $tokenBalanceStr, TokenBalanceHuman: $tokenBalanceHuman, SignedTx: $signedTx");

            if (isset($result['result'])) {
                return [
                    'success' => true,
                    'tx_hash' => $result['result'],
                    'sent_amount' => $tokenBalanceHuman,
                    'decimals' => $decimals
                ];
            }

            return [
                'success' => false,
                'message' => $result['error']['message'] ?? 'Unknown error occurred'
            ];

        } catch (\Exception $e) {
            \Log::error("Error in sendAnyChainFullTokenBalance: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
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

