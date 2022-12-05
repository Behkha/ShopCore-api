<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Http\Resources\Product as ProductResource;
use App\Jobs\AddToRedis;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Feature;
use App\Models\Product;
use App\Models\ProductItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class ProductsController extends Controller
{
    public function __construct(Request $request)
    {
        $this
            ->middleware('auth:user')
            ->only(['addComment']);
        $this
            ->middleware('auth:admin')
            ->only(['create', 'update', 'updateGallery', 'deleteImage']);
    }

    public function index(Request $request)
    {
        $request->validate([
            'categories' => 'array|min:1',
            'categories.*' => 'required|distinct|exists:categories,id',
            'min_price' => 'integer|min:1',
            'max_price' => 'integer|min:1',
            'is_available' => 'boolean',
            'has_discount' => 'boolean',
            'filters' => 'array|min:1',
            'filters.*.id' => 'required|distinct|exists:attributes,id',
            'filters.*.values' => 'required|array|min:1',
            'filters.*.values.*' => 'required|string|max:255',
        ]);
        $query = Product::with('features');
        if ($request->query('title')) {
            $products = $query
                ->where('title', 'like', '%' . $request->query('title') . '%')
                ->paginate();
            return ProductResource::collection($products);
        }
        // applying dynamic filters
        if ($request->query('filters')) {
            foreach ($request->query('filters') as $filter) {
                $query->orWhere(function ($builder) use ($filter) {
                    $builder->whereHas('attributes', function ($attribute) use ($filter) {
                        $attribute->where(function ($builder2) use ($filter) {
                            $builder2->where('id', $filter['id'])
                                ->whereIn('value', $filter['values']);
                        });
                    });
                });
            }
        }
        if ($request->query('categories')) {
            $temp = collect();
            foreach ($request->query('categories') as $category) {
                $temp->push(Category::find($category));
                Category::where('parent_id', $category)->get()->each(function ($item, $key) use ($temp) {
                    if ($item) {
                        $temp->push($item);
                    }
                });
            }
            $categories = collect();
            while ($temp->count() !== 0) {
                $element = $temp->pop();
                $categories->push($element);
                $tempChild = Category::find($element->id);
                Category::where('parent_id', $tempChild->id)
                    ->get()
                    ->each(function ($item, $key) use ($temp) {
                        $temp->push($item);
                    });
            }
            $query->whereHas('category', function ($builder) use ($categories) {
                $builder->whereIn('id', $categories->pluck('id'));
            });
        }
        if ($request->query('min_price')) {
            $query->where('price', '>=', $request->query('min_price'));
        }

        if ($request->query('max_price')) {
            $query->where('price', '<=', $request->query('max_price'));
        }
        if ($request->query('is_available')) {
            $query->whereHas('productAttributes', function ($productAttribute) {
                $productAttribute->where('quantity', '>', 0);
            });
        }
        if ($request->query('has_discount')) {
            $query->whereHas('productItems.discounts');
        }
        if ($request->query('sort_by_views')) {
            $query->orderBy('view_count', 'desc');
        }
        if ($request->query('sort_by_bookmarks')) {
            $query->withCount('bookmarks')->orderBy('bookmarks_count', 'desc');
        }
        if ($request->query('sort_by_sales')) {
            $query->whereHas('productItems', function ($builder) {
                $builder->withCount('orders')->orderBy('orders_count', 'desc');
            });
        }
        if ($request->query('sort_by_lowest_price')) {
            $products = $query->get();
            $sorted = $products->sortBy('price');
            $data = $sorted->paginate(15);
            return ProductResource::collection($data);
        } elseif ($request->query('sort_by_highest_price')) {
            $products = $query->get();
            $sorted = $products->sortByDesc('price');
            $data = $sorted->paginate(15);
            return ProductResource::collection($data);
        }
        $products = $query->paginate();
        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        $product->view_count++;
        $product->save();
        $product->load('attributes', 'productItems', 'features');
        return new ProductResource($product);
    }

    public function similarProducts(Product $product)
    {
        $products = Product::where(function ($query) use ($product) {
            $query->whereHas('category', function ($category) use ($product) {
                $category->where('id', $product->category_id);
            })->orWhereHas('brand', function ($brand) use ($product) {
                $brand->where('id', $product->brand_id);
            });
        })->where('id', '!=', $product->id)
            ->inRandomOrder()
            ->paginate();
        return ProductResource::collection($products);
    }

    public function newProducts()
    {
        $products = Product::orderBy('created_at', 'desc')
            ->take(20)
            ->get();
        return ProductResource::collection($products);
    }

    public function create(Request $request)
    {
        $error = $this->validateCreate($request);
        if ($error) {
            return $error;
        }
        $product = Product::create($request->only(['title', 'description', 'category_id', 'brand_id']));
        // save gallery
        $gallery = [];
        if ($request->file('gallery')) {
            foreach ($request->file('gallery') as $image) {
                array_push($gallery, $image->store('products'));
            }
        }
        $product->gallery = $gallery;
        // save option
        foreach ($request->input('option')[0]['values'] as $option) {
            ProductItem::create([
                'product_id' => $product->id,
                'price' => $option['price'],
                'quantity' => $option['quantity'],
                'attribute_id' => $request->input('option')[0]['id'],
                'value' => $option['value'],
            ]);
        }
        // save attributes
        $attributes = [];
        foreach ($request->input('attributes') as $attribute) {
            array_push($attributes, ['attribute_id' => $attribute['id'], 'value' => $attribute['value']]);
        }
        $product->attributes()->attach($attributes);
        // save features
        if ($request->input('features')) {
            foreach ($request->input('features') as $feature) {
                $product->features()->save(new Feature(['title' => $feature]));
            }
        }
        $product->save();
        return new ProductResource($product);
    }

    public function update(Product $product, Request $request)
    {
        $error = $this->validateUpdate($request);
        $product->update($request->only(['title', 'description', 'category_id', 'brand_id']));

        //update attributes
        $product->attributes()->detach();
        $attributes = [];
        foreach ($request->input('attributes') as $attribute) {
            array_push($attributes, ['attribute_id' => $attribute['id'], 'value' => $attribute['value']]);
        }
        $product->attributes()->attach($attributes);

        // update option
        if ($request->input('options')) {
            foreach ($request->input('options') as $option) {
                ProductItem::where('id', $option['id'])
                    ->update([
                        'value' => $option['value'],
                        'quantity' => $option['quantity'],
                        'price' => $option['price'],
                    ]);
            }
        }
        // save features
        $product->features()->delete();
        if ($request->input('features')) {
            foreach ($request->input('features') as $feature) {
                $product->features()->save(new Feature(['title' => $feature]));
            }
        }
        // new option values
        if ($request->input('new_values')) {
            foreach ($request->input('new_values') as $newValue) {
                ProductItem::create([
                    'product_id' => $product->id,
                    'attribute_id' => $product->productItems->first()->id,
                    'value' => $newValue['value'],
                    'price' => $newValue['price'],
                    'quantity' => $newValue['quantity'],
                ]);
            }
        }
        return new ProductResource($product);
    }

    private function validateCreate($request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'gallery' => 'array|max:10',
            'gallery.*' => 'required|file|image|max:5000',
            'description' => 'string|max:10000',
            'option' => 'required|array|max:1',
            'option.*.id' => 'required|exists:attributes,id',
            'option.*.values' => 'required|array',
            'option.*.values.*.value' => 'required|string|max:255',
            'option.*.values.*.price' => 'required|integer|min:0',
            'option.*.values.*.quantity' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'features' => 'array',
            'features.*' => 'required|string|max:255',
            'brand_id' => 'required|exists:brands,id',
            'attributes' => 'required|array',
            'attributes.*.id' => 'required|exists:attributes,id',
            'attributes.*.value' => 'required|string|max:255',
        ]);
        $category = Category::find($request->input('category_id'));
        $validAttributes = collect();
        $category->attributes->each(function ($item, $key) use ($validAttributes) {
            $validAttributes->push($item);
        });
        $parents = collect();
        $parents->push(Category::where('id', $category->parent_id)->first());
        while ($parents->count() > 0) {
            $parentCategory = $parents->pop();
            if ($parentCategory) {
                $parentCategory->attributes->each(function ($item, $key) use ($validAttributes) {
                    $validAttributes->push($item);
                });
                $parents->push(Category::where('id', $parentCategory->parent_id)->first());
            }
        }
        // check if attributes are correct
        foreach ($request->input('attributes') as $attribute) {
            if (!$validAttributes->contains('id', $attribute['id'])) {
                return response()->json(['errors' => 'invalid attribute'], 400);
            }
        }
        // check if option is correct
        if (!$validAttributes->contains('id', $request->input('option')[0]['id'])) {
            return response()->json(['errors' => 'invalid option'], 400);
        }
    }

    private function validateUpdate($request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'string|max:10000',
            'options' => 'array',
            'options.*.id' => 'required|exists:product_items,id',
            'options.*.price' => 'required|integer|min:0',
            'options.*.quantity' => 'required|integer|min:0',
            'options.*.value' => 'required|string|max:255',
            'new_values' => 'array',
            'new_values.*.value' => 'required|string|max:255',
            'new_values.*.price' => 'required|integer|min:0',
            'new_values.*.quantity' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'features' => 'array',
            'features.*' => 'required|string|max:255',
            'brand_id' => 'required|exists:brands,id',
            'attributes' => 'required|array',
            'attributes.*.id' => 'required|exists:attributes,id',
            'attributes.*.value' => 'required|string|max:255',
        ]);
    }

    public function updateGallery(Product $product, Request $request)
    {
        $request->validate([
            'gallery' => 'required|array',
            'gallery.*' => 'required|file|image|max:5000',
        ]);
        $gallery = $product->gallery;
        foreach ($request->gallery as $image) {
            array_push($gallery, $image->store('products'));
        }
        $product->gallery = $gallery;
        $product->save();
        return new ProductResource($product);
    }

    public function addComment(Product $product, Request $request)
    {
        $request->validate(['body' => 'required|string|max:255']);
        $product->comments()->save(new Comment([
            'body' => $request->input('body'),
            'commented_by' => auth('user')->user()->id,
        ]));
        return response()->json(['message' => 'comment added'], 201);
    }

    public function getComments(Product $product)
    {
        $comments = $product->comments()->paginate();
        return CommentResource::collection($comments);
    }

    private function getProducts($key, $start, $end)
    {
        if (($key === 'products_by_price' && request()->input('sort_order') === 'desc') || $key === 'products_by_bookmark') {

            $ids = Redis::ZREVRANGE($key, $start, $end);
        } else {

            $ids = Redis::ZRANGE($key, $start, $end);
        }

        $products = collect();

        foreach ($ids as $id) {

            $product = unserialize(Redis::GET('product:' . $id));

            if (!$product) {

                $product = Product::find($id);

                AddToRedis::dispatch('Product', $product->id, $product);
            }

            $products->push($product);
        }

        return ProductResource::collection($products);
    }

    public function getFilters(Request $request)
    {
        $request->validate(['category_id' => 'exists:categories,id']);
        if ($request->query('category_id')) {
            $attributes = collect();
            $category = Category::find($request->query('category_id'));
            $category->attributes->each(function ($value, $key) use ($attributes) {
                $attributes->push($value);
            });
            foreach (Category::where('parent_id', $category->id)->get() as $child) {
                foreach (Category::where('parent_id', $child->id)->get() as $second) {
                    foreach (Category::where('parent_id', $second->id)->get() as $third) {
                        $third->attributes->each(function ($value, $key) use ($attributes) {
                            $attributes->push($value);
                        });
                    }
                    $second->attributes->each(function ($value, $key) use ($attributes) {
                        $attributes->push($value);
                    });
                }
                $child->attributes->each(function ($value, $key) use ($attributes) {
                    $attributes->push($value);
                });
            }
            $result = [];
            foreach ($attributes->unique('id') as $item) {
                $values = \DB::table('attribute_product')
                    ->where('attribute_id', $item->id)
                    ->select('value')
                    ->groupBy('value')
                    ->get();
                if ($values->count() > 0) {
                    array_push($result, [
                        'key_id' => $item->id,
                        'key_name' => $item->name,
                        'values' => $values,
                    ]);
                }
            }
            return $result;
        }
        $result = [];
        $attrs = Attribute::all();
        foreach ($attrs as $attr) {
            $values = \DB::table('attribute_product')
                ->where('attribute_id', $attr->id)
                ->select('value')
                ->groupBy('value')
                ->get();
            array_push($result, ['key_id' => $attr->id, 'key_name' => $attr->name, 'values' => $values]);

        }
        return response()->json(['data' => $result]);
    }

    public function deleteImage(Product $product, $imageUrl)
    {
        if (sizeof($product->gallery) === 0) {
            return response()->json(['error' => 'does not have gallery'], 400);
        }
        foreach ($product->gallery as $key => $image) {
            if (explode('/', $image)[1] === $imageUrl) {
                $gallery = $product->gallery;
                unset($gallery[$key]);
                Storage::delete($image);
                $product->gallery = $gallery;
                $product->save();
                return response()->json(['message' => 'image deleted']);
            }
        }
        return response()->json(['error' => 'image not found'], 404);
    }
}
