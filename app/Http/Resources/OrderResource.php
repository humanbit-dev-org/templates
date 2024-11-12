<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Resources\OrderDetailResource;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'ecommerce' => new EcommerceResource($this->ecommerce),
            'order_detail' => OrderDetailResource::collection($this->details),
            'created_at' => Carbon::parse($this->created_at)->format('d/m/Y'),
        ];
    }
}
