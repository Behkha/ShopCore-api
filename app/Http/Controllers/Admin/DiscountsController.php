<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Discount as DiscountResource;
use App\Models\Discount;
use App\Models\DiscountGroup;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

class DiscountsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function create(Request $request)
    {
        $request->validate([
            'category_id' => 'required_without_all:product_id,product_item_id|exists:categories,id',
            'product_id' => 'required_without_all:product_item_id,category_id|exists:products,id',
            'product_item_id' => 'required_without_all:product_id,category_id|exists:product_items,id',
            'off' => 'required|integer|min:1|max:100',
            'starting_date' => 'required|string',
            'expiration_date' => 'required|string',
        ]);
        // exp date
        $year = explode('-', $request->input('expiration_date'))[0];
        $month = explode('-', $request->input('expiration_date'))[1];
        $day = explode('-', $request->input('expiration_date'))[2];
        $jdate = new Jalalian($year, $month, $day);
        $request->merge(['expiration_date' => $jdate->toCarbon()]);
        // starting date
        $year = explode('-', $request->input('starting_date'))[0];
        $month = explode('-', $request->input('starting_date'))[1];
        $day = explode('-', $request->input('starting_date'))[2];
        $jdate = new Jalalian($year, $month, $day);
        $request->merge(['starting_date' => $jdate->toCarbon()]);
        if ($request->input('category_id')) {
            $products = Product::where('category_id', $request->input('category_id'))
                ->get();
            $discountGroup = DiscountGroup::create(['is_on' => 'category']);
            $products->each(function ($item, $value) use ($request, $discountGroup) {
                $item->productItems->each(function ($item2, $key) use ($item, $request, $discountGroup) {
                    DB::table('discounts')
                        ->insert([
                            'off' => $request->input('off'),
                            'expiration_date' => $request->input('expiration_date'),
                            'starting_date' => $request->input('starting_date'),
                            'product_item_id' => $item2->id,
                            'discount_group_id' => $discountGroup->id,
                        ]);
                });
            });
            return response()->json(['message' => 'created'], 201);
        }
        if ($request->input('product_item_id')) {
            DB::table('discounts')
                ->insert([
                    'off' => $request->input('off'),
                    'expiration_date' => $request->input('expiration_date'),
                    'starting_date' => $request->input('starting_date'),
                    'product_item_id' => $request->input('product_item_id'),
                ]);

            return response()->json(['message' => 'created'], 201);
        }
        if ($request->input('product_id')) {
            $product = Product::find($request->input('product_id'));
            $discountGroup = DiscountGroup::create(['is_on' => 'product']);
            $product->productItems->each(function ($item, $key) use ($product, $request, $discountGroup) {
                if ($item->discounts->count() === 0) {
                    DB::table('discounts')
                        ->insert([
                            'off' => $request->input('off'),
                            'expiration_date' => $request->input('expiration_date'),
                            'starting_date' => $request->input('starting_date'),
                            'product_item_id' => $item->id,
                            'discount_group_id' => $discountGroup->id,
                        ]);
                }
            });
            return response()->json(['message' => 'created'], 201);
        }
    }

    public function update(Discount $discount, Request $request)
    {
        $request->validate([
            'off' => 'required|integer|min:1|max:100',
            'starting_date' => 'required|string',
            'expiration_date' => 'required|string',
        ]);
        // exp date
        $year = explode('-', $request->input('expiration_date'))[0];
        $month = explode('-', $request->input('expiration_date'))[1];
        $day = explode('-', $request->input('expiration_date'))[2];
        $jdate = new Jalalian($year, $month, $day);
        $request->merge(['expiration_date' => $jdate->toCarbon()]);
        // starting date
        $year = explode('-', $request->input('starting_date'))[0];
        $month = explode('-', $request->input('starting_date'))[1];
        $day = explode('-', $request->input('starting_date'))[2];
        $jdate = new Jalalian($year, $month, $day);
        $request->merge(['starting_date' => $jdate->toCarbon()]);
        $discount->off = $request->input('off');
        $discount->starting_date = $request->input('starting_date');
        $discount->expiration_date = $request->input('expiration_date');
        $discount->save();
        return response()->json(['message' => 'ok']);
    }

    public function delete(Request $request)
    {
        if ($request->query('group_id')) {
            DiscountGroup::destroy($request->query('group_id'));
        } elseif ($request->query('discount_id')) {
            Discount::destroy($request->query('discount_id'));
        }
        return response()->json(['message' => 'ok']);
    }

    public function index(Request $request)
    {
        $discounts = Discount::paginate();
        return DiscountResource::collection($discounts);
    }

    public function show($id, Request $request)
    {
        if ($request->query('is_group')) {
            $discounts = Discount::with('item.product')
                ->where('discount_group_id', $id)
                ->get();
            return DiscountResource::collection($discounts);
        }
        $discount = Discount::findOrFail($id);
        $discount->load('item.product');
        return new DiscountResource($discount);
    }
}
