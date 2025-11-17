<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'product_type' => $this->product_type,
            'currency' => $this->currency,
            'minimum_amount' => $this->minimum_amount,
            'maximum_amount' => $this->maximum_amount,
            'interest_rate' => $this->interest_rate,
            'interest_rate_type' => $this->interest_rate_type,
            'interest_calculation_frequency' => $this->interest_calculation_frequency,
            'interest_posting_frequency' => $this->interest_posting_frequency,
            'repayment_frequency' => $this->repayment_frequency,
            'amortization_type' => $this->amortization_type,
            'grace_period_days' => $this->grace_period_days,
            'late_payment_penalty_rate' => $this->late_payment_penalty_rate,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'charges' => ChargeResource::collection($this->whenLoaded('charges')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}