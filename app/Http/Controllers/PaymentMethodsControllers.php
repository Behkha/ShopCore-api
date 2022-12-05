<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentMethodsControllers extends Controller
{
    public function __construct()
    {
        $this
            ->middleware('auth:admin')
            ->only(['update']);
    }

    public function index()
    {
        $paymentMethods = DB::table('payment_methods')
            ->get();
        return response()->json(['data' => $paymentMethods]);
    }

    public function update($id, Request $request)
    {
        $paymentMethod = DB::table('payment_methods')
            ->where('id', $id)
            ->first();
        if (!$paymentMethod) {
            throw new ModelNotFoundException();
        }
        DB::table('payment_methods')
            ->where('id', $id)
            ->update(['is_enabled' => !$paymentMethod->is_enabled]);
        return response()->json(['message' => 'ok']);
    }
}
