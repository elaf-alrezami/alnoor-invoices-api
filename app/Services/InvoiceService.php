<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Calculate totals server-side in minor units and persist the invoice atomically.
     *
     * @param  array{customer: string, items: array<int, array{name: string, qty: int|float|string, price: int|float|string}>}  $validatedData
     */
    public function create(array $validatedData): Invoice
    {
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

        return DB::transaction(function () use (
            $validatedData,
            $subtotalMinor,
            $taxMinor,
            $totalMinor,
            $taxRate,
            $currency,
            $preparedItems
        ): Invoice {
            $invoice = Invoice::create([
                'customer' => $validatedData['customer'],
                'subtotal' => $subtotalMinor,
                'tax' => $taxMinor,
                'total' => $totalMinor,
                'tax_rate' => $taxRate,
                'currency' => $currency,
            ]);

            $invoice->items()->createMany($preparedItems);

            return $invoice->load('items');
        });
    }
}
