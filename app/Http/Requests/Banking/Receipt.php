<?php

namespace App\Http\Requests\Banking;

use App\Abstracts\Http\FormRequest;

class Receipt extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'vendor_name' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'receipt_date' => 'nullable|date',
            'category_id' => 'nullable|integer|exists:categories,id',
            'description' => 'nullable|string|max:1000',
        ];
    }
}