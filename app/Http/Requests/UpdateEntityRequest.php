<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEntityRequest extends FormRequest
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
            'title'        => 'sometimes|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'content'      => 'nullable|string|max:10000',
            'start_time'   => 'sometimes|string',
            'end_time'     => 'nullable|string',
            'scheduled_at' => 'sometimes|string',
            'remind_at'    => 'sometimes|string',
            'datetime'     => 'sometimes|string',
            'date'         => 'sometimes|string',
            'time'         => 'sometimes|string',
            'due_date'     => 'sometimes|string',
            'status'       => 'sometimes|string|max:50',
            'priority'     => 'sometimes|string|in:low,medium,high',
            'channel'      => 'sometimes|string|max:50',
            'location'     => 'nullable|string|max:255',
            'tags'         => 'sometimes|array',
            'tags.*'       => 'string|max:50',
            'is_pinned'    => 'sometimes|boolean',
            'fields'       => 'sometimes|array',
            'updates'      => 'sometimes|array',
        ];
    }
}
