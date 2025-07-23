<?php

namespace App\Http\Controllers\api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CreateWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAuthController extends Controller
{
    protected CreateWallet $createWallet;
    public function __construct(CreateWallet $createWallet){
        $this->createWallet = $createWallet;
    }
    public function createAccount(Request $request):JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required',
        ]);

        $userWallet = $this->createWallet->createAddress();

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => bcrypt($validatedData['password']),
            'wallet_address' => $userWallet->address,
            'two_factor_secret' => $userWallet->key,
        ]);
        if ($user) {
            return response()->json([
                'success' => true,
                'message' => 'Account successfully created.',
                'user' => $user,
            ]);
        }else{
            return response()->json([
                'success' => false,
                'message' => 'Account not created.',
            ]);
        }
    }
}
