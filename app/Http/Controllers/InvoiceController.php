<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    /**
     * List invoices with optional customer search and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) ($request->query('search') ?? $request->query('customer') ?? ''));

        $rawPerPage = $request->query('per_page', 15);
        $perPage = is_numeric($rawPerPage) ? (int) $rawPerPage : 15;
        if ($perPage < 1) {
            $perPage = 15;
        }
        $perPage = min($perPage, 100);

        $invoices = Invoice::query()
            ->with('items')
            ->when($search !== '', fn ($query) => $query->where('customer', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate($perPage);

        return InvoiceResource::collection($invoices);
    }

    /**
     * Validate input, calculate totals server-side, and store a new invoice.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create($request->validated());

        return (new InvoiceResource($invoice))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display a single invoice with its line items.
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        $invoice->load('items');

        return new InvoiceResource($invoice);
    }
}
