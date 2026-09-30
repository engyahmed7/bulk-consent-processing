<?php

namespace App\Domains\Broker\Http\Requests;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrokerQueueRequest extends FormRequest
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
            'purpose' => ['required', Rule::enum(BrokerQueuePurpose::class), 'unique:broker_queues,purpose'],
            'queue_name' => ['required', 'string', 'max:255'],
            'exchange' => ['nullable', 'string', 'max:255'],
            'routing_key' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
