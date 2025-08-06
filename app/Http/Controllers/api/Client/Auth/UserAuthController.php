<?php

namespace App\Http\Controllers\api\Client\Auth;

use App\Http\Controllers\Controller;
use App\Models\Transactions;
use App\Models\User;
use App\Services\CreateWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

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
    public function login(Request $request):JsonResponse{
        $validatedData = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $user = User::where('email', $validatedData['email'])->first();

        if (!$user || !Hash::check($validatedData['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ]);
        }else{
            $token = $user->createToken('auth_token')->plainTextToken;
            try {
                $res = Http::get('http://ip-api.com/json/118.179.177.97');
                $data = $res->json();

                $user->last_login_data = $data['country'];
                $user->save();
            } catch (\Exception $exception) {
            }


            return response()->json([
                'status' => true,
                'message' => 'User logged in successfully',
                'user' => $user,
                'token' => $token,
            ]);
        }
    }

    public function profile(Request $request):JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'total_transactions' => Transactions::where('user_id', $user->id)->count(),
            ],
        ]);
    }

  public function updateProfile(Request $request):JsonResponse
  {
      $validatedData = $request->validate([
          'name' => 'required',
          'phone' => 'required',
          'password' => 'sometimes',
      ]);
      $user = $request->user();
      $user->name = $validatedData['name'];
      $user->phone = $validatedData['phone'];
      if(!empty($validatedData['password'])){
          $user->password = bcrypt($validatedData['password']);
      }
      $user->save();
      return response()->json([
          'success' => true,
          'data' => [
              'user' => $user,
              'message' => 'Profile updated successfully.',
          ]
      ]);
  }
}
