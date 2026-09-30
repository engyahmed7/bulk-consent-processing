<?php

namespace App\Domains\Bulk\Http\Requests;

use App\Domains\Bulk\Enums\ConsentAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkJobRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'mimes:xlsx',
                'max:'.(int) config('bulk.max_upload_kb', 51200),
            ],
            'action' => ['required', Rule::enum(ConsentAction::class)],
        ];
    }
}
