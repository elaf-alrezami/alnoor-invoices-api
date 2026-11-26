<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Calculate totals server-side in minor units and persist the invoice atomically.
     *
     * When an idempotency key is provided, duplicate submissions return the
     * previously created invoice instead of creating a new record.
     *
     * @param  array{customer: string, items: array<int, array{name: string, qty: int|float|string, price: int|float|string}>}  $validatedData
     */
    public function create(array $validatedData, ?string $idempotencyKey = null): Invoice
    {
        if ($idempotencyKey !== null) {
            $existing = Invoice::with('items')
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $taxRate = (float) config('invoice.tax_rate', 0.15);
        $currency = (string) config('invoice.currency', 'YER');

        $preparedItems = [];
        $subtotalMinor = 0;

        foreach ($validatedData['items'] as $item) {
            $priceMinor = (int) round(((float) $item['price']) * 100, 0, PHP_ROUND_HALF_UP);
            $qty = (float) $item['qty'];
            $lineTotalMinor = (int) round($qty * $priceMinor, 0, PHP_ROUND_HALF_UP);

            $subtotalMinor += $lineTotalMinor;

            $preparedItems[] = [
                'name' => $item['name'],
                'qty' => $qty,
                'price' => $priceMinor,
                'line_total' => $lineTotalMinor,
            ];
        }

        $taxMinor = (int) round($subtotalMinor * $taxRate, 0, PHP_ROUND_HALF_UP);
        $totalMinor = $subtotalMinor + $taxMinor;

        try {
            return DB::transaction(function () use (
                $validatedData,
                $idempotencyKey,
                $subtotalMinor,
                $taxMinor,
                $totalMinor,
                $taxRate,
                $currency,
                $preparedItems
            ): Invoice {
                $invoice = Invoice::create([
                    'customer' => $validatedData['customer'],
                    'idempotency_key' => $idempotencyKey,
                    'subtotal' => $subtotalMinor,
                    'tax' => $taxMinor,
                    'total' => $totalMinor,
                    'tax_rate' => $taxRate,
                    'currency' => $currency,
                ]);

                $invoice->items()->createMany($preparedItems);

                return $invoice->load('items');
            });
        } catch (QueryException $e) {
            if ($idempotencyKey !== null) {
                $existing = Invoice::with('items')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }
    }
}
