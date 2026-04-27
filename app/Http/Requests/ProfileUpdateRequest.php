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
            'ape_pat' => ['nullable', 'string', 'max:255'],
            'ape_mat' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'settings' => ['nullable', 'array'],
            'settings.working_hours' => ['nullable', 'array'],
            'settings.working_hours.monday' => ['nullable', 'array'],
            'settings.working_hours.tuesday' => ['nullable', 'array'],
            'settings.working_hours.wednesday' => ['nullable', 'array'],
            'settings.working_hours.thursday' => ['nullable', 'array'],
            'settings.working_hours.friday' => ['nullable', 'array'],
            'settings.working_hours.saturday' => ['nullable', 'array'],
            'settings.working_hours.sunday' => ['nullable', 'array'],
            'settings.working_hours.monday.start' => ['nullable', 'date_format:H:i'],
            'settings.working_hours.monday.end' => ['nullable', 'date_format:H:i'],
            'settings.working_hours.tuesday.start' => ['nullable', 'date_format:H:i'],
            'settings.working_hours.tuesday.end' => ['nullable', 'date_format:H:i'],
            'settings.working_hours.wednesday.start' => ['nullable', 'date_format:H:i'],
            'settings.working_hours.wednesday.end' => ['nullable', 'date_format:H:i'],
            'settings.working_hours.thursday.start' => ['nullable', 'date_format:H:i'],
            'settings.working_hours.thursday.end' => ['nullable', 'date_format:H:i'],
            'settings.working_hours.friday.start' => ['nullable', 'date_format:H:i'],
            'settings.working_hours.friday.end' => ['nullable', 'date_format:H:i'],
            'settings.working_hours.saturday.start' => ['nullable', 'date_format:H:i'],
            'settings.working_hours.saturday.end' => ['nullable', 'date_format:H:i'],
            'settings.working_hours.sunday.start' => ['nullable', 'date_format:H:i'],
            'settings.working_hours.sunday.end' => ['nullable', 'date_format:H:i'],

            'settings.services' => ['nullable', 'array'],
            'settings.services.*.name' => ['required_with:settings.services', 'string', 'max:120'],
            'settings.services.*.duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'settings.services.*.price' => ['nullable', 'numeric', 'min:0'],
            'settings.services.*.currency' => ['nullable', 'string', 'max:8'],
        ];
    }
}
