<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public const ALLOWED_FIELDS = ['name', 'ape_pat', 'ape_mat', 'email'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->replace([
            'name' => trim((string) $this->input('name', '')),
            'ape_pat' => trim((string) $this->input('ape_pat', '')),
            'ape_mat' => trim((string) $this->input('ape_mat', '')),
            'email' => strtolower(trim((string) $this->input('email', ''))),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'ape_pat' => ['required', 'string', 'max:255'],
            'ape_mat' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user()->getKey()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'ape_pat.required' => 'El apellido paterno es obligatorio.',
            'ape_mat.required' => 'El apellido materno es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingresa un correo válido.',
            'email.unique' => 'Este correo ya está registrado.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function safeProfileData(): array
    {
        return $this->safe()->only(self::ALLOWED_FIELDS);
    }
}
