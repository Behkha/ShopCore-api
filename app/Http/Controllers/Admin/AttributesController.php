<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttributeResource;
use App\Http\Resources\AttributeSet as AttributeSetResource;
use App\Models\Attribute;
use App\Models\AttributeSet;
use Illuminate\Http\Request;

class AttributesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function indexSets()
    {
        $sets = AttributeSet::all();
        return AttributeSetResource::collection($sets);
    }

    public function showSet(AttributeSet $set)
    {
        $set->load('attributes');
        return new AttributeSetResource($set);
    }

    public function createSet(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $set = AttributeSet::create($request->only(['name']));
        return new AttributeSetResource($set);
    }

    public function updateSet(AttributeSet $set, Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $set->update($request->only(['name']));
        return new AttributeSetResource($set);
    }

    public function deleteSet(AttributeSet $set)
    {
        $set->delete();
        return new AttributeSetResource($set);
    }

    public function index(Request $request)
    {
        if ($request->query('name')) {
            $attrs = Attribute::where('name', 'like', '%' . $request->query('name') . '%')
                ->paginate();
            return AttributeResource::collection($attrs);
        }
        $attrs = Attribute::all();
        return AttributeResource::collection($attrs);
    }

    public function show(Attribute $attribute)
    {
        return new AttributeResource($attribute);
    }

    public function create(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'unit' => 'string|max:255', 'attribute_set_id' => 'exists:attribute_sets,id', 'is_filter' => 'boolean']);
        $attr = Attribute::create($request->only(['name', 'unit', 'attribute_set_id', 'is_filter']));
        return new AttributeResource($attr);
    }

    public function update(Attribute $attribute, Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'unit' => 'string|max:255', 'attribute_set_id' => 'exists:attribute_sets,id', 'is_filter']);
        $attribute->update($request->only(['name', 'unit', 'attribute_set_id', 'is_filter']));
        return new AttributeResource($attribute);
    }

    public function delete(Attribute $attribute)
    {
        $attribute->delete();
        return new AttributeResource($attribute);
    }
}
