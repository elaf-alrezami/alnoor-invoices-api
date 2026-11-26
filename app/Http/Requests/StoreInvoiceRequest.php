<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'customer' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * Get custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer.required' => 'Customer name is required.',
            'customer.string' => 'Customer name must be a valid string.',
            'customer.max' => 'Customer name may not exceed 255 characters.',
            'items.required' => 'At least one invoice item is required.',
            'items.array' => 'Invoice items must be provided as a list.',
            'items.min' => 'At least one invoice item is required.',
            'items.*.name.required' => 'Each item must have a name.',
            'items.*.name.string' => 'Each item name must be a valid string.',
            'items.*.name.max' => 'Each item name may not exceed 255 characters.',
            'items.*.qty.required' => 'Each item must have a quantity.',
            'items.*.qty.numeric' => 'Each item quantity must be a number.',
            'items.*.qty.gt' => 'Each item quantity must be greater than zero.',
            'items.*.price.required' => 'Each item must have a unit price.',
            'items.*.price.numeric' => 'Each item price must be a number.',
            'items.*.price.gt' => 'Each item price must be greater than zero.',
        ];
    }
}
