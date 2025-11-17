<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
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
        $rules = [
            'client_type' => 'required|in:individual,organization',
            'external_id' => 'sometimes|uuid|unique:clients',
            'email' => 'nullable|email|unique:clients|max:255',
            'phone_number' => 'nullable|string|unique:clients|max:20',
            'address' => 'nullable|array',
            'address.street' => 'nullable|string|max:255',
            'address.city' => 'nullable|string|max:100',
            'address.state' => 'nullable|string|max:100',
            'address.country' => 'nullable|string|size:3',
            'address.postal_code' => 'nullable|string|max:20'
        ];

        if ($this->input('client_type') === 'individual') {
            $rules += [
                'owner_client_id' => 'prohibited', // Individual clients cannot have owners
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'date_of_birth' => 'required|date|before:today',
                'gender' => 'nullable|in:male,female,other',
                'marital_status' => 'nullable|string|max:50',
                'nationality' => 'nullable|string|size:3',
                'organization_business_ids' => 'nullable|array',
                'organization_business_ids.*' => 'uuid|exists:clients,id',
                // Business fields should not be present for individuals
                'business_name' => 'prohibited',
                'business_registration_number' => 'prohibited',
                'business_registration_date' => 'prohibited',
                'business_type' => 'prohibited',
                'tax_identification_number' => 'prohibited',
                'industry_sector' => 'prohibited',
                'representative_first_name' => 'prohibited',
                'representative_last_name' => 'prohibited',
                'representative_position' => 'prohibited'
            ];
        } else { // organization
            $rules += [
                'owner_client_id' => [
                    'required',
                    'uuid',
                    Rule::exists('clients', 'id')->where('client_type', 'individual')
                ],
                'business_name' => 'required|string|max:255',
                'business_registration_number' => 'required|string|max:50|unique:clients',
                'business_registration_date' => 'required|date|before_or_equal:today',
                'business_type' => 'required|string|max:50',
                'tax_identification_number' => 'required|string|max:50|unique:clients',
                'industry_sector' => 'required|string|max:100',
                'representative_first_name' => 'required|string|max:255',
                'representative_last_name' => 'required|string|max:255',
                'representative_position' => 'required|string|max:100',
                // Individual fields should not be present for organizations
                'first_name' => 'prohibited',
                'last_name' => 'prohibited',
                'date_of_birth' => 'prohibited',
                'gender' => 'prohibited',
                'marital_status' => 'prohibited',
                'nationality' => 'prohibited',
                'organization_business_ids' => 'prohibited'
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'client_type.required' => 'Please specify whether this is an individual or organization client.',
            'owner_client_id.required' => 'Organization clients must have an owner (individual client).',
            'owner_client_id.exists' => 'The specified owner must be a valid individual client.',
            'owner_client_id.prohibited' => 'Individual clients cannot have an owner.',

            // Individual client messages
            'first_name.required' => 'First name is required for individual clients.',
            'last_name.required' => 'Last name is required for individual clients.',
            'date_of_birth.required' => 'Date of birth is required for individual clients.',
            'date_of_birth.before' => 'Date of birth must be in the past.',

            // Business client messages
            'business_name.required' => 'Business name is required for organization clients.',
            'business_registration_number.required' => 'Business registration number is required.',
            'business_registration_number.unique' => 'This business registration number is already registered.',
            'business_registration_date.required' => 'Business registration date is required.',
            'business_registration_date.before_or_equal' => 'Business registration date cannot be in the future.',
            'business_type.required' => 'Business type is required.',
            'tax_identification_number.required' => 'Tax identification number is required.',
            'tax_identification_number.unique' => 'This tax identification number is already registered.',
            'industry_sector.required' => 'Industry sector is required.',
            'representative_first_name.required' => 'Representative first name is required.',
            'representative_last_name.required' => 'Representative last name is required.',
            'representative_position.required' => 'Representative position is required.',

            // Prohibited field messages
            'business_name.prohibited' => 'Business name should not be provided for individual clients.',
            'first_name.prohibited' => 'First name should not be provided for organization clients.',
            'last_name.prohibited' => 'Last name should not be provided for organization clients.',

            // Common messages
            'nationality.size' => 'Nationality must be a valid ISO 3166-1 alpha-3 country code.',
            'address.country.size' => 'Country code must be a valid ISO 3166-1 alpha-3 code.'
        ];
    }
}
