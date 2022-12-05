<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\PasswordReset;
use Illuminate\Http\Request;

class VerifyForgetPassword extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(Request $request)
    {
        return [ 
            'phone' => [
                'required',
                'string',
                'digits:11',
                function ($attr, $value, $fail) {
                    $exists = PasswordReset::where('phone', $value)
                        ->where('is_verified', false)
                        ->where('created_at', '>=', now()->subMinutes(60)->toDateTimeString())
                        ->exists();

                    if (! $exists) {
                        $fail($attr . ' is invalid');
                    }
                },
            ],
            'token' => [
                'required',
                'string',
                'digits:4',
                function ($attr, $value, $fail) use ($request) {
                    $phone = PasswordReset::where('phone', $request->input('phone'))
                        ->latest()
                        ->first();

                    if ($phone && $phone->token !== $value) {
                        $fail($attr . ' is invalid');
                    }
                },
            ],
        ];
    }
}
