<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cart as CartRsource;
use App\Http\Resources\Product as ProductResource;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:user');
    }

    public function addToCart(Request $request)
    {
        $request->validate([
            'product_item_id' => 'required|exists:product_items,id',
            'quantity' => 'required|integer|min:0',
        ]);
        if (ProductItem::find($request->input('product_item_id'))->quantity < $request->input('quantity')) {
            return response()->json(['errors' => 'invalid quantity'], 400);
        }
        if (auth('user')->user()->cart->items->contains('id', $request->input('product_item_id'))) {
            return response()->json(['errors' => 'item already in cart'], 400);
        }
        DB::table('cart_product')
            ->insert([
                'product_item_id' => $request->input('product_item_id'),
                'quantity' => $request->input('quantity'),
                'cart_id' => auth('user')->user()->cart->id,
            ]);
        return response()->json(['message' => 'item added to cart'], 201);
    }

    public function showCart()
    {
//        return new CartRsource(auth('user')->user()->cart);
        $cartId = auth('user')
            ->user()
            ->cart
            ->id;
        $cartProds = \DB::table('cart_product')
            ->where('cart_id', $cartId)
            ->get();
        $data = ['products' => []];
        $totalPrice = 0;
        $totalDiscountPrice = 0;
        foreach ($cartProds as $cartProd) {
            $resource = ProductItem::with('product')->where('id', $cartProd->product_item_id)->first();
            $images = [];
            foreach ($resource->product->gallery as $image) {
                    array_push($images, env('STORAGE_PATH') . $image);
            }
            $resource->product->gallery = $images;
            $totalPrice += $cartProd->quantity * $resource->price;
            $product = [
                'details' => $resource,
                'cart_qty' => $cartProd->quantity,
                'final_price' => $cartProd->quantity * $resource->price,
//                'total_discount' => ($cartProd->quantity * $resource->price) - ($cartProd->quantity * $resource->discount_price),
//                'total_bonus' => $cartProd->quantity * $resource->bonus,
            ];


            $discount = Discount::where('product_item_id', $cartProd->product_item_id)
                                    ->where('expiration_date', '>', date('Y-m-d', strtotime('now')))
                                    ->first();

            if ($discount) {
                $product['final_price_after_discount'] = ($cartProd->quantity * $resource->price) - ($cartProd->quantity * ($resource->price * $discount->off / 100));
                $product['discount'] = $discount;
                $totalDiscountPrice += ($cartProd->quantity * ($resource->price * $discount->off / 100));
            }
            array_push($data['products'], $product);
        }
        // Cart Total Price
        $data['total_price'] = $totalPrice;
        $data['total_discount'] = $totalDiscountPrice;
        $data['total_price_with_discount'] = $totalPrice - $totalDiscountPrice;
        return response()->json(['data' => $data]);

    }

    public function updateCart($item, Request $request)
    {
        $request->validate(['quantity' => 'required|integer|min:0']);
        if (!DB::table('cart_product')->where('cart_id', auth('user')->user()->cart->id)->where('product_item_id', $item)->exists()) {
            return response()->json(['errors' => 'item does not exist in cart'], 400);
        }
        if ($request->input('quantity') == 0) {
            DB::table('cart_product')
                ->where('cart_id', auth('user')->user()->cart->id)
                ->where('product_item_id', $item)
                ->delete();
        } else {
            if (ProductItem::find($item)->quantity < $request->input('quantity')) {
                return response()->json(['errors' => 'invalid quantity'], 400);
            }
            DB::table('cart_product')
                ->where('cart_id', auth('user')->user()->cart->id)
                ->where('product_item_id', $item)
                ->update(['quantity' => $request->input('quantity')]);
        }
        return response()->json(['message' => 'cart updated']);
    }

    public function deleteItem($item)
    {
        if (!DB::table('cart_product')->where('cart_id', auth('user')->user()->cart->id)->where('product_item_id', $item)->exists()) {
            return response()->json(['errors' => 'item does not exist in cart'], 400);
        }
        DB::table('cart_product')
            ->where('cart_id', auth('user')->user()->cart->id)
            ->where('product_item_id', $item)
            ->delete();
        return response()->json(['Message' => 'Product Deleted From Cart']);
    }

    public function similarProducts()
    {
        $cartProducts = auth('user')->user()->cart->items;
        $cartProductsCategoryIds = [];
        $cartProductsBrandIds = [];
        $cartProducts->each(function ($item, $key) use (&$cartProductsCategoryIds, &$cartProductsBrandIds) {
            array_push($cartProductsCategoryIds, $item->category_id);
            array_push($cartProductsBrandIds, $item->brand_id);
        });
        $similarProducts = Product::where(function ($query) use ($cartProductsBrandIds, $cartProductsCategoryIds) {
            $query->whereHas('category', function ($category) use ($cartProductsCategoryIds) {
                $category->whereIn('id', $cartProductsCategoryIds);
            })->orWhereHas('brand', function ($brand) use ($cartProductsBrandIds) {
                $brand->whereIn('id', $cartProductsBrandIds);
            });
        })->whereNotIn('id', $cartProducts->pluck('id'))
            ->paginate();
        return ProductResource::collection($similarProducts);
    }
}
