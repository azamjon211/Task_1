<?php

namespace App\Services;

use App\Models\Category;

class CategoryService
{
    public function create(array $data){
        if(!empty($data['parent_id'])){
            $parent = Category::findOrFail($data['parent_id']);
            $providerId = $parent->provider->id;
        }elseif(!empty($data['provider_id'])){
            $providerId = $data['provider_id'];
        }else{
            throw new \InvalidArgumentException('Root categories must specify provider');
        }
        return Category::create([
            'parent_id' => $data['parent_id']?? null,
            'provider_id' => $providerId,
            'name' => $data['name'],
        ]);
    }
}
