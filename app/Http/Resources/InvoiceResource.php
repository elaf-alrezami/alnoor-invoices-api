<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Disable outer "data" wrapper for single invoice responses.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => $this->customer,
            'currency' => $this->currency,
            'tax_rate' => (float) $this->tax_rate,
            'subtotal' => $this->fromMinorUnits((int) $this->subtotal),
            'tax' => $this->fromMinorUnits((int) $this->tax),
            'total' => $this->fromMinorUnits((int) $this->total),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Convert minor units (amount * 100) to a clean JSON number.
     */
    private function fromMinorUnits(int $minor): int|float
    {
        return $minor % 100 === 0 ? intdiv($minor, 100) : round($minor / 100, 2);
    }
}
