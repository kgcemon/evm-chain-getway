<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayOutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => 'required',
            'type' => 'required',
            'to' => 'required',
            'token_address' => 'sometimes|string',
            'chain_id' => 'required',
            'rpc_url' => 'required',
            'user_id' => 'required',
        ];
    }
}
