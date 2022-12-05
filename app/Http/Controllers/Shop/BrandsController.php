<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\Brand as BrandResource;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandsController extends Controller
{
    public function __construct()
    {
        $this
            ->middleware('auth:admin')
            ->only(['create', 'update', 'updateImage']);
    }

    public function index()
    {
        $brands = Brand::all();
        return BrandResource::collection($brands);
    }

    public function show(Brand $brand)
    {
        return new BrandResource($brand);
    }

    public function create(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'file|image|max:5000',
        ]);
        if ($request->file('image')) {
            $url = $request->file('image')->store('brands');
        }
        $brand = new Brand([
            'title' => $request->input('title'),
            'image_url' => isset($url) ? $url : null,
        ]);
        $brand->save();
        return new BrandResource($brand);
    }

    public function update(Brand $brand, Request $request)
    {
        $request->validate(['title' => 'required|string|max:255']);
        $brand->title = $request->input('title');
        if ($request->input('delete_image')) {
            Storage::delete($brand->image_url);
            $brand->image_url = null;
        }
        $brand->save();
        return new BrandResource($brand);
    }

    public function updateImage(Brand $brand, Request $request)
    {
        $request->validate(['image' => 'required|image|max:5000']);
        if ($brand->image_url) {
            Storage::delete($brand->image_url);
        }
        $brand->image_url = $request->file('image')->store('brands');
        $brand->save();
        return new BrandResource($brand);
    }
}
