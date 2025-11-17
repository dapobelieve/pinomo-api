<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'charge_type' => $this->charge_type,
            'amount' => $this->amount,
            'percentage' => $this->percentage,
            'currency' => $this->currency,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'gl_income_account_id' => $this->gl_income_account_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}