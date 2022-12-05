<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DiscountCode as DiscountCodeResource;
use App\Models\DiscountCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Morilog\Jalali\Jalalian;

class DiscountCodesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function create(Request $request)
    {
        $request->validate([
            'count' => 'required|integer|min:1',
            'expiration_date' => 'required|string',
            'max' => 'required|integer|min:1',
            'percent' => 'required|integer|min:1|max:100',
        ]);
        $year = explode('-', $request->input('expiration_date'))[0];
        $month = explode('-', $request->input('expiration_date'))[1];
        $day = explode('-', $request->input('expiration_date'))[2];
        $jdate = new Jalalian($year, $month, $day);
        $dcode = DB::table('discount_codes')
            ->orderBy('group_id', 'desc')
            ->first();
        for ($i = 0; $i < $request->input('count'); $i++) {
            $time = str_replace('.', '', microtime(true));
            $groupId = $dcode ? $dcode->group_id + 1 : 1;
            DB::table('discount_codes')
                ->insert([
                    'code' => substr($time, -5) . Str::random(10) . substr($time, -10, 5),
                    'expiration_date' => $jdate->toCarbon(),
                    'max' => $request->input('max'),
                    'percent' => $request->input('percent'),
                    'group_id' => $groupId,
                ]);
        }
        return response()->json(['message' => 'created'], 201);
    }

    public function index(Request $request)
    {
        if ($request->query('percent')) {
            $dcodes = DiscountCode::groupBy('group_id')
                ->selectRaw('group_id as id, percent, max, expiration_date')
                ->where('percent', $request->query('percent'))
                ->paginate();
            return response()->json(['data' => $dcodes]);
        }
        if ($request->query('code')) {
            $dcode = DiscountCode::where('code', $request->query('code'))
                ->firstOrFail();
            return new DiscountCodeResource($dcode);
        }
        $dcodes = DiscountCode::groupBy('group_id')
            ->selectRaw('group_id as id, percent, max, expiration_date')
            ->paginate();
        return response()->json(['data' => $dcodes]);
    }

    public function show($id)
    {
        $dcodes = DiscountCode::where('group_id', $id)
            ->get();
        return DiscountCodeResource::collection($dcodes);
    }

    public function update($id, Request $request)
    {
        $request->validate([
            'expiration_date' => 'required|string',
            'max' => 'required|integer|min:1',
            'percent' => 'required|integer|min:1|max:100',
        ]);
        $year = explode('-', $request->input('expiration_date'))[0];
        $month = explode('-', $request->input('expiration_date'))[1];
        $day = explode('-', $request->input('expiration_date'))[2];
        $jdate = new Jalalian($year, $month, $day);
        $request->merge(['expiration_date' => $jdate->toCarbon()]);
        $dcodes = DiscountCode::where('group_id', $id)
            ->get();
        $canUpdate = true;
        $dcodes->each(function ($item, $key) use ($canUpdate) {
            if (!$item->is_used) {
                $canUpdate = false;
                return false;
            }
        });
        if ($canUpdate) {
            DiscountCode::where('group_id', $id)
                ->update($request->only(['expiration_date', 'max', 'percent']));
            return response()->json(['message' => 'ok']);
        }
        return response()->json(['errors' => 'can not update'], 403);
    }
}
