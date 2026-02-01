<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'user_type' => ['nullable', Rule::in([
                'student', 'researcher', 'business', 'developer', 'other'
            ])],
            'phone_number' => ['nullable', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            ];
    }
}
