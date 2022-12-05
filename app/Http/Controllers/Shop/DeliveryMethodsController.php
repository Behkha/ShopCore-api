<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use Illuminate\Http\Request;

class DeliveryMethodsController extends Controller
{
    public function __construct()
    {
        $this
            ->middleware('auth:admin')
            ->only([
                'create',
                'update',
            ]);
    }

    public function index()
    {
        if (auth('admin')->user()) {
            return response()->json(['data' => DeliveryMethod::all()]);
        }
        return response()->json(['data' => DeliveryMethod::where('is_active', true)->get()]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'string|max:255',
            'price' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);
        $dm = DeliveryMethod::create($request->only(['name', 'description', 'price', 'is_active']));
        return response()->json(['data' => $dm], 201);
    }

    public function update(DeliveryMethod $dm, Request $request)
    {
        $request->validate([
            'name' => 'string|max:255',
            'description' => 'string|max:255|nullable',
            'price' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);
        $dm->update($request->only(['name', 'description', 'price', 'is_active']));
        return response()->json(['data' => $dm]);
    }
}
