<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\FulfillmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi input resi & pembaruan fulfillment status pesanan (PRD 6.8).
 */
class AdminFulfillOrderRequest extends FormRequest
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
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'fulfillment_status' => [
                'required',
                Rule::enum(FulfillmentStatus::class),
            ],
        ];
    }
}
