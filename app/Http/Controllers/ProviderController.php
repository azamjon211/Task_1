<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProviderRequest;
use App\Models\Provider;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function index(){
        return response()->json(['data' => Provider::all()]);
    }
    public function store(StoreProviderRequest $request){
        $provider = Provider::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
        ]);
        return response()->json(['data' => $provider], 201);
    }

}
