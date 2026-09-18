<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi request generate nomor resi ekspedisi (PRD Phase 2 - Feature 3).
 */
class ShippingCreateWaybillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageStore() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'courier_code' => ['required', 'string', 'in:jne,jnt,sicepat,gosend'],
            'courier_service' => ['required', 'string', 'max:50'],
            'destination_postal_code' => ['required', 'string', 'max:10'],
            'destination_address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
