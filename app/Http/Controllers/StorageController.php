<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStorageRequest;
use App\Models\Storage;

class StorageController extends Controller
{
    public function index(){
        return response() ->json(['data' => Storage::all()]);
    }
    public function store(StoreStorageRequest $request){
        $storage = Storage::create([
            'name' => $request->name,
            'location' => $request->location,
        ]);
        return response()->json(['data' => $storage], 201);
    }
}
