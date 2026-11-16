<?php

namespace App\Http\Requests;

use App\Enums\ScanMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ScanMode::class)],
            'document_no' => ['required', 'string', 'max:50'],
            'barcode' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'to_location' => ['nullable', 'string', 'max:100'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'client_scan_id' => ['nullable', 'uuid'],
        ];
    }
}
