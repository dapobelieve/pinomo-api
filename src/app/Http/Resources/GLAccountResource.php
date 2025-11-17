<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GLAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_code' => $this->account_code,
            'account_name' => $this->account_name,
            'account_type' => $this->account_type,
            'currency' => $this->currency,
            'parent_account_id' => $this->parent_account_id,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'current_balance' => $this->current_balance,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Include relationships when loaded
            'parent' => new GLAccountResource($this->whenLoaded('parent')),
            'children' => GLAccountResource::collection($this->whenLoaded('children')),
            
            // Additional computed attributes
            'has_children' => $this->when(!is_null($this->children), function () {
                return $this->children->isNotEmpty();
            }),
        ];
    }
}