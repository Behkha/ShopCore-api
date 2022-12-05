<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrdersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        if ($request->query('code')) {
            $order = Order::where('code', $request->query('code'))
                ->firstOrFail();
            return new OrderResource($order);
        }
        $orders = Order::orderBy('created_at', 'desc')->paginate();
        return OrderResource::collection($orders);
    }

    public function show(Order $order)
    {
        $order->load('user', 'items', 'items.product', 'items.product.attributes');
        return new OrderResource($order);
    }

    public function update(Order $order, Request $request)
    {
//        $request->validate(['status' => ['required', Rule::in(Order::STATUS)]]);
        if ($order->status == 6) return response()->json(['message' => 'ok']);
        $order->status = $order->status + 1;
        $order->save();
        return response()->json(['message' => 'ok']);
    }
}
