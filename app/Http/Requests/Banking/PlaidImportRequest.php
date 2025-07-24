<?php

namespace App\Http\Requests\Banking;

use App\Abstracts\Http\FormRequest;

class PlaidImportRequest extends FormRequest
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
            'access_token' => 'required|string',
            'start_date' => 'sometimes|date|before_or_equal:end_date',
            'end_date' => 'sometimes|date|after_or_equal:start_date|before_or_equal:today',
            'account_ids' => 'sometimes|array',
            'account_ids.*' => 'string',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'access_token' => trans('plaid.access_token'),
            'start_date' => trans('general.start_date'),
            'end_date' => trans('general.end_date'),
            'account_ids' => trans('plaid.account_ids'),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'access_token.required' => trans('validation.required', ['attribute' => trans('plaid.access_token')]),
            'start_date.date' => trans('validation.date', ['attribute' => trans('general.start_date')]),
            'end_date.date' => trans('validation.date', ['attribute' => trans('general.end_date')]),
            'start_date.before_or_equal' => trans('validation.before_or_equal', [
                'attribute' => trans('general.start_date'),
                'date' => trans('general.end_date')
            ]),
            'end_date.after_or_equal' => trans('validation.after_or_equal', [
                'attribute' => trans('general.end_date'),
                'date' => trans('general.start_date')
            ]),
            'end_date.before_or_equal' => trans('validation.before_or_equal', [
                'attribute' => trans('general.end_date'),
                'date' => 'today'
            ]),
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default date range if not provided (last 30 days)
        if (!$this->has('start_date') && !$this->has('end_date')) {
            $this->merge([
                'start_date' => now()->subDays(30)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
            ]);
        }
    }
}