<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'purchased_at' => ['nullable', 'date'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'integer', 'exists:products,id'],
            'products.*.storage_id' => ['required', 'integer', 'exists:storages,id'],
            'products.*.qty' => ['required', 'integer', 'min:1'],
            'products.*.purchase_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
