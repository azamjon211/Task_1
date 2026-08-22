<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Models\Client;

class ClientController extends Controller
{
    public function index(){
        return response()->json(['data' => Client::all()]);
    }
    public function store(StoreClientRequest $request){
        $client = Client::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
        ]);
        return response()->json(['data' => $client],201);
    }
}
