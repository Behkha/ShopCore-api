<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\Product as ProductResource;
use App\Jobs\EmptyCart;
use App\Models\Address;
use App\Models\DeliveryMethod;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Shetabit\Payment\Exceptions\InvalidPaymentException;
use Shetabit\Payment\Facade\Payment;
use Shetabit\Payment\Invoice;

class OrdersController extends Controller
{
    public function __construct()
    {
        $this
            ->middleware('auth:user')
            ->only(['index', 'show', 'create', 'discount']);
    }

    public function index(Request $request)
    {
        if ($request->query('code')) {
            $order = Order::where('code', $request->query('code'))
                ->where('user_id', auth('user')->user()->id)
                ->where('status', '!=', Order::STATUS['canceled'])
                ->first();
            if ($order) {
                return new OrderResource($order);
            }
            return response()->json(['data' => '']);
        }
        if ($request->query('all')) {
            $orders = Order::where('user_id', auth('user')->user()->id)
                ->where('status', '!=', Order::STATUS['canceled'])
                ->orderBy('created_at', 'desc')
                ->get();
            return OrderResource::collection($orders);
        }
        $orders = Order::where('user_id', auth('user')->user()->id)
            ->where('status', '!=', Order::STATUS['canceled'])
            ->orderBy('created_at', 'desc')
            ->paginate();
        return OrderResource::collection($orders);
    }

    public function show($id)
    {
        $order = Order::where('user_id', auth('user')->user()->id)->findOrFail($id);
        $order->load('items', 'items.product', 'items.product.attributes');
        return new OrderResource($order);
    }

    public function create(Request $request)
    {
        $error = $this->validateCreate($request);
        if ($error) {
            return $error;
        }
        $orderPrice = 0;
        foreach (auth('user')->user()->cart->items as $item) {
            $orderPrice += $item->pivot->quantity * $item->price_after_discount;
        }
        $priceAfterDiscountCode = $orderPrice;
        if ($request->input('discount_code')) {
            $dcode = DiscountCode::where('code', $request->input('discount_code'));
            if (floor(($dcode->percent * $orderPrice) / 100) > $dcode->max) {
                $priceAfterDiscountCode = $orderPrice - $dcode->max;
            } else {
                $priceAfterDiscountCode = $orderPrice - floor(($dcode->percent * $orderPrice) / 100);
            }
        }
        $order = Order::create([
            'address_id' => $request->input('address_id'),
            'delivery_method_id' => $request->input('delivery_method_id'),
            'status' => Order::STATUS['unknown'],
            'code' => Str::random(5) . substr(str_replace('.', '', microtime(true)), -10) . Str::random(5),
            'user_id' => auth('user')->user()->id,
            'price_before_discount_code' => $orderPrice + DeliveryMethod::find($request->input('delivery_method_id'))->price,
            'price_after_discount_code' => $priceAfterDiscountCode + DeliveryMethod::find($request->input('delivery_method_id'))->price,
            'payment_method_id' => $request->input('payment_method_id'),
        ]);
        $result;
        DB::transaction(function () use (&$result, $order, $priceAfterDiscountCode, $request) {
            $orderItems = [];
            foreach (auth('user')->user()->cart->items as $item) {
                array_push($orderItems, [
                    'product_item_id' => $item->id,
                    'order_id' => $order->id,
                    'price_before_discount' => $item->price,
                    'price_after_discount' => $item->price_after_discount,
                    'quantity' => $item->pivot->quantity,
                ]);
            }
            $order->items()->attach($orderItems);
            $invoice = new Invoice;
            $invoice->amount($priceAfterDiscountCode + DeliveryMethod::find($request->input('delivery_method_id'))->price);
            $result = Payment::callbackUrl(env('CALLBACK_URL'))->purchase($invoice, function ($driver, $transactionId) use ($order, $priceAfterDiscountCode, $request) {
                $order->transaction()->save(new Transaction([
                    'amount' => $priceAfterDiscountCode + DeliveryMethod::find($request->input('delivery_method_id'))->price,
                    'code' => $transactionId,
                    'type' => Transaction::TRANSACTION_TYPES['zarinpal'],
                    'user_id' => auth('user')->user()->id,
                    'is_verified' => false,
                ]));
            }
            )->pay();
        });
        return response()->json(['data' => $result->getTargetUrl()]);
    }

    public function discount(Request $request)
    {
        $request->validate(['code' => 'required|string|max:100']);
        $dcode = DiscountCode::where('code', $request->input('code'))
            ->where('is_used', false)
            ->where('expiration_date', '>=', now())
            ->first();
        if (!$dcode) {
            return response()->json(['error' => 'bad request'], 400);
        }
        $cartId = auth('user')
            ->user()
            ->cart
            ->id;
        $cartProds = \DB::table('cart_product')
            ->where('cart_id', $cartId)
            ->get();
        $priceBeforeDiscount = 0;
        foreach ($cartProds as $cartProd) {
            $resource = new ProductResource(
                Product::find($cartProd->product_id)
            );
            $priceBeforeDiscount +=
            $cartProd->quantity * $resource->discount_price;
        }
        $priceAfterDiscount = 0;
        $discountPrice = ($priceBeforeDiscount * ($dcode->percent)) / 100;
        if ($discountPrice >= $dcode->max) {
            $priceAfterDiscount = $priceBeforeDiscount - $dcode->max;
        } else {
            $priceAfterDiscount = $priceBeforeDiscount - $discountPrice;
        }
        return response()->json(['data' => ['price_before_discount' => $priceBeforeDiscount, 'price_after_discount' => $priceAfterDiscount]]);
    }

    public function verify(Request $request)
    {
        $code = $request->query('Authority');
        $transaction = Transaction::where('code', $code)->firstOrFail();
        $order = $transaction->transactionable;
        try {
            $receipt = Payment::amount($transaction->amount)->transactionId($code)->verify();
            DB::transaction(function () use ($transaction, $order) {
                $transaction->is_verified = true;
                $order->status = Order::STATUS['registered'];
                $transaction->save();
                $order->save();
            });
            foreach ($order->items as $item) {
                $item->quantity -= $item->pivot->quantity;
                $item->save();
            }
            EmptyCart::dispatchNow($transaction->user_id);
            return redirect(env('REDIRECT_URL') . '?code=' . $code . '&status=ok' . '&order=' . $order->code . '&id=' . $order->id . '&payment=' . $order->payment_method);
        } catch (InvalidPaymentException $exception) {
            $order->status = Order::STATUS['unsuccessful'];
            $order->save();
            return redirect(env('REDIRECT_URL') . '?code=' . $code . '&status=nok' . '&order=' . $order->code . '&id=' . $order->id . '&payment=' . $order->payment_method);
        }
    }

    private function validateCreate($request)
    {
        $request->validate([
            'address_id' => 'required|exists:addresses,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'delivery_method_id' => 'required|exists:delivery_methods,id',
            'discount_code' => 'string|max:100',
        ]);
        // check address id belongs to user
        if (!Address::where('user_id', auth('user')->user()->id)->where('id', $request->input('address_id'))->exists()) {
            return response()->json(['errors' => 'invalid address id'], 400);
        }
        // env variables must be set
        if (!(env('MERCHANT_ID') && (env('WEB_REDIRECT_URL') || env('MOBILE_REDIRECT_URL')) && env('CALLBACK_URL'))) {
            return response()->json(['errors' => 'env variables are not set'], 400);
        }
        // check cart is not empty
        if (auth('user')->user()->cart->items->count() === 0) {
            return response()->json(['errors' => 'cart is empty'], 400);
        }
        // check item qty
        foreach (auth('user')->user()->cart->items as $item) {
            if (ProductItem::find($item->id)->quantity < $item->pivot->quantity) {
                return response()->json(['errors' => 'invalid quantity'], 400);
            }
        }
        // check discount code
        if ($request->input('discount_code')) {
            if (!DiscountCode::where('code', $request->input('code'))->where('is_used', false)->where('expiration_date', '>', now()->toDateString())->exists()) {
                return response()->json(['errors' => 'invalid discount code'], 400);
            }
        }
        // check if payment method is enabled
        if (!DB::table('payment_methods')->where('id', $request->input('payment_method_id'))->first()->is_enabled) {
            return response()->json(['errors' => 'invalid payment method'], 400);
        }
    }
}
