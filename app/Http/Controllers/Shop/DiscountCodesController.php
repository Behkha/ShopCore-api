<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DiscountCode;

class DiscountCodesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:user');
    }

    public function check(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $dcode = DiscountCode::where('code', $request->input('code'))
            ->where('is_used', false)
            ->where('expiration_date', '>=', now()->toDateString())
            ->orderBy('created_at', 'desc')
            ->first();
        if ($dcode) {
            return response()->json([
                'data' => $dcode,
                'is_correct' => true,
            ]);
        } else {
            return response()->json([
                'data' => '',
                'is_correct' => false,
            ]);
        }
    }
}
