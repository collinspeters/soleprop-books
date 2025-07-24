<?php

namespace App\Http\Requests\Banking;

use App\Abstracts\Http\FormRequest;

class PlaidLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // You can add authorization logic here
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'public_token' => 'required|string',
            'metadata' => 'sometimes|array',
            'metadata.institution' => 'sometimes|array',
            'metadata.accounts' => 'sometimes|array',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'public_token' => trans('plaid.public_token'),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'public_token.required' => trans('validation.required', ['attribute' => trans('plaid.public_token')]),
            'public_token.string' => trans('validation.string', ['attribute' => trans('plaid.public_token')]),
        ];
    }
}