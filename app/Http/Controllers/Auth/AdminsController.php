<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminsController extends Controller
{
    public function signin(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:30',
        ]);
        $token = auth('admin')->attempt($request->only(['username', 'password']));
        if (!$token) {
            return response()->json(['errors' => 'invalid username or password'], 400);
        }
        return response()->json(['data' => ['token' => $token]]);
    }
}
