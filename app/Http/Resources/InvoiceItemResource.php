<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
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
            'name' => $this->name,
            'qty' => $this->formatQuantity((float) $this->qty),
            'price' => $this->fromMinorUnits((int) $this->price),
            'line_total' => $this->fromMinorUnits((int) $this->line_total),
        ];
    }

    /**
     * Convert minor units (amount * 100) to a clean JSON number.
     */
    private function fromMinorUnits(int $minor): int|float
    {
        return $minor % 100 === 0 ? intdiv($minor, 100) : round($minor / 100, 2);
    }

    /**
     * Format quantity as int when whole, otherwise as float.
     */
    private function formatQuantity(float $qty): int|float
    {
        return fmod($qty, 1.0) === 0.0 ? (int) $qty : $qty;
    }
}
