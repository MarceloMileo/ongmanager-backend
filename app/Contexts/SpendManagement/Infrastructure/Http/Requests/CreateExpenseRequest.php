<?php

namespace App\Contexts\SpendManagement\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file_reference'          => ['required', 'string'],
            'document_value_cents'    => ['required', 'integer', 'min:1'],
            'document_value_currency' => ['required', 'string', 'size:3'],
            'total_amount_cents'      => ['required', 'integer', 'min:1'],
            'total_amount_currency'   => ['required', 'string', 'size:3'],
            'submitter_id'            => ['required', 'uuid'],
            'document_number'         => ['nullable', 'string'],
            'issuer_identifier'       => ['nullable', 'string'],
            'issued_at'               => ['nullable', 'date'],
        ];
    }
}
