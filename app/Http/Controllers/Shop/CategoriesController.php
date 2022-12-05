<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategory;
use App\Http\Resources\Category as CategoryResource;
use App\Http\Resources\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CategoriesController extends Controller
{
    public function __construct(Request $request)
    {
        $this
            ->middleware('auth:admin')
            ->only([
                'create',
                'update',
            ]);
    }

    public function index(Request $request)
    {
        if ($request->query('name')) {
            $categories = Category::where('name', 'like', '%' . $request->query('name') . '%')
                ->paginate();
            return CategoryResource::collection($categories);
        }
        $query = Category::orderBy('id', 'asc');
        if ($request->input('all')) {
            $categories = Category::where('parent_id', null)
                ->get();
            if ($categories->count() === 0) {
                return response()->json(['data' => '']);
            }
            foreach ($categories as $category) {
                $category->children = collect();
                foreach (Category::where('parent_id', $category->id)->get() as $child) {
                    $fchildren = [];
                    foreach (Category::where('parent_id', $child->id)->get() as $second) {
                        $schildren = [];
                        foreach (Category::where('parent_id', $second->id)->get() as $third) {
                            $tChildren = [];
                            foreach (Category::where('parent_id', $third->id)->get() as $fourth) {
                                array_push($tChildren, $fourth);
                            }
                            $third->children = $tChildren;
                            array_push($schildren, $third);
                        }
                        $second->children = $schildren;
                        array_push($fchildren, $second);
                    }
                    $child->children = $fchildren;
                    $category->children->push($child);
                }
            }
            if (!$category->children) {
                $category->children = [];
            }
            return $categories;
        }
        return CategoryResource::collection($query->paginate());
    }

    public function show(Category $category)
    {
        $category->parent = Category::find($category->parent_id);
        $category->load('attributes', 'attributes.attributeSet');
        $category->children = collect();
        foreach (Category::where('parent_id', $category->id)->get() as $child) {
            $fchildren = [];
            foreach (Category::where('parent_id', $child->id)->get() as $second) {
                $schildren = [];
                foreach (Category::where('parent_id', $second->id)->get() as $third) {
                    $tChildren = [];
                    foreach (Category::where('parent_id', $third->id)->get() as $fourth) {
                        $fourth->load('attributes');
                        array_push($tChildren, $fourth);
                    }
                    $third->load('attributes');
                    $third->children = $tChildren;
                    array_push($schildren, $third);
                }
                $second->load('attributes');
                $second->children = $schildren;
                array_push($fchildren, $second);
            }
            $child->load('attributes');
            $child->children = $fchildren;
            $category->children->push($child);
        }
        return $category;
    }

    public function products(Category $category, Request $request)
    {
        $products = collect();
        $temp = Category::where('parent_id', $category->id)
            ->get();
        $products->push(Product::collection($category->products));
        while ($temp->count() !== 0) {
            $element = $temp->pop();
            $tempChild = Category::find($element->id);
            if ($tempChild->products->count() > 0) {
                $products->push(Product::collection($tempChild->products));
            }
            Category::where('parent_id', $tempChild->id)
                ->get()
                ->each(function ($item, $key) use ($temp) {
                    $temp->push($item);
                });
        }
        return response()->json(['data' => $products]);
    }

    public function create(StoreCategory $request)
    {
        $category = new Category($request->only(['name']));
        if ($request->hasFile('logo')) {
            $category->logo = $request->file('logo')->store('categories');
        }
        if ($request->input('parent_id')) {
            $category->parent_id = $request->input('parent_id');
        }
        $category->save();
        // save attributes
        if ($request->input('attributes')) {
            $category->attributes()->attach($request->input('attributes'));
        }
        return new CategoryResource($category);
    }

    public function update(Category $category, Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'exists:categories,id',
            'attributes' => 'array|min:1',
            'attributes.*' => 'required|distinct|exists:attributes,id',
            'logo' => 'file|image|max:5000',
        ]);
        if ($request->input('parent_id') == $category->id) {
            return response()->json(['errors' => 'invalid parent_id'], 400);
        }
        $category->name = $request->input('name');
        $category->parent_id = $request->input('parent_id');
        if ($request->input('delete_logo')) {
            Storage::delete($category->getOriginal('logo'));
            $category->logo = null;
        }
        if ($request->hasFile('logo')) {
            Storage::delete($category->getOriginal('logo'));
            $category->logo = $request->file('logo')->store('categories');
        }
        $category->save();
        if ($request->input('attributes')) {
            $category->attributes()->detach();
            $category->attributes()->attach($request->input('attributes'));
        }
        return new CategoryResource($category);
    }

    public function attributes(Category $category)
    {
        $attributes = collect();
        $category->attributes->each(function ($item, $key) use ($attributes) {
            $item->values = collect();
            $item->values->push(DB::table('attribute_product')->where('attribute_id', $item->id)->get()->pluck('value')->unique());
            $attributes->push($item);
        });
        $parent = Category::find($category->parent_id);
        while ($parent) {
            $parent->attributes->each(function ($item, $key) use ($attributes) {
                $item->values = collect();
                $item->values->push(DB::table('attribute_product')->where('attribute_id', $item->id)->get()->pluck('value')->unique());
                $attributes->push($item);
            });
            $parent = Category::find($parent->parent_id);
        }
        return response()->json(['data' => $attributes]);
    }
}
