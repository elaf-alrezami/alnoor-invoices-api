<?php

return [
    'tax_rate' => (float) env('INVOICE_TAX_RATE', 0.15),
    'currency' => env('INVOICE_CURRENCY', 'YER'),
];
