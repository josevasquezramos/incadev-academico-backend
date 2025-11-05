<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnrollmentRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'operation_number' => 'required|string|max:255',
            'agency_number' => 'required|string|max:255',
            'operation_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'evidence_path' => 'required|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operation_number.required' => 'El número de operación es obligatorio',
            'agency_number.required' => 'El número de agencia es obligatorio',
            'operation_date.required' => 'La fecha de operación es obligatoria',
            'operation_date.date' => 'La fecha de operación debe ser una fecha válida',
            'amount.required' => 'El monto es obligatorio',
            'amount.numeric' => 'El monto debe ser un número',
            'amount.min' => 'El monto debe ser mayor a 0',
            'evidence_path.required' => 'La evidencia de pago es obligatoria',
        ];
    }
}