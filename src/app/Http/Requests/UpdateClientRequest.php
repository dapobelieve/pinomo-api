<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }

    public function rules(): array
    {
        return [
            'client_type' => 'sometimes|required|in:individual,organization',
            'organization_business_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::unique('clients')->ignore($this->client->id)
            ],
            'first_name' => 'sometimes|required_if:client_type,individual|nullable|string|max:255',
            'last_name' => 'sometimes|required_if:client_type,individual|nullable|string|max:255',
            'date_of_birth' => 'sometimes|required_if:client_type,individual|nullable|date|before:today',
            'gender' => 'sometimes|nullable|in:male,female,other',
            'marital_status' => 'sometimes|nullable|string|max:50',
            'nationality' => 'sometimes|nullable|string|size:3',
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('clients')->ignore($this->client->id)
            ],
            'phone_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('clients')->ignore($this->client->id)
            ],
            'address' => 'sometimes|nullable|array',
            'address.street' => 'required_with:address|string|max:255',
            'address.city' => 'required_with:address|string|max:100',
            'address.state' => 'required_with:address|string|max:100',
            'address.country' => 'required_with:address|string|size:3',
            'address.postal_code' => 'required_with:address|string|max:20'
        ];
    }
}