<?php

namespace App\Domains\Broker\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrokerQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'queue_name' => ['sometimes', 'required', 'string', 'max:255'],
            'exchange' => ['nullable', 'string', 'max:255'],
            'routing_key' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
