<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Concerns\InteractsWithApiRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceLineRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\VoidInvoiceRequest;
use App\Http\Resources\InvoiceLineResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\AuditLogger;
use App\Services\Billing\BillingService;
use App\Support\ApiError;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invoice lifecycle: draft → issued → (partially_paid | paid | overdue) → void.
 * Lines are managed as sub-resources and only editable while the invoice is a draft.
 */
final class InvoiceController extends Controller
{
    use InteractsWithApiRequest;

    public function __construct(
        private readonly BillingService $billing,
        private readonly AuditLogger    $audit,
        private readonly TenantContext  $context,
    ) {
    }

    // ── Listing & detail ─────────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('issue_date', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('issue_date', '<=', $request->string('to')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('invoice_number', 'like', $term)->orWhere('notes', 'like', $term));
            })
            ->with('customer')
            ->orderByDesc('issue_date')
            ->paginate($this->perPage($request));

        return InvoiceResource::collection($invoices);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $invoice->load(['customer', 'lines']);

        return InvoiceResource::make($invoice);
    }

    // ── Create ───────────────────────────────────────────────────────────

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $tenantId = $this->context->tenantId();

        $invoice = DB::transaction(function () use ($request, $tenantId): Invoice {
            $number = $this->billing->generateInvoiceNumber((int) $tenantId);

            return Invoice::create(array_merge($request->validated(), [
                'invoice_number' => $number,
                'status'         => 'draft',
            ]));
        });

        $this->audit->log('invoice.created', $invoice, [
            'new' => $invoice->only(['invoice_number', 'customer_id', 'status', 'issue_date']),
        ]);

        $invoice->load('customer');

        return InvoiceResource::make($invoice)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update mutable fields on a draft invoice (dates, notes, invoice-level discount).
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        if (! $invoice->status->isEditable()) {
            return ApiError::make(
                'invoice_not_draft',
                "Invoice {$invoice->invoice_number} is not editable (status: {$invoice->status->value}).",
                Response::HTTP_CONFLICT,
            );
        }

        $validated = $request->validate([
            'due_date'        => ['nullable', 'date'],
            'period_start'    => ['nullable', 'date'],
            'period_end'      => ['nullable', 'date', 'after_or_equal:period_start'],
            'currency'        => ['nullable', 'string', 'size:3'],
            'discount_poisha' => ['nullable', 'integer', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($invoice, $validated): void {
            $invoice->update($validated);
            $this->billing->syncInvoiceTotals($invoice);
        });

        $invoice->load(['customer', 'lines']);

        return response()->json(['data' => InvoiceResource::make($invoice)]);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        if (! $invoice->status->isEditable()) {
            return ApiError::make(
                'invoice_not_draft',
                'Only draft invoices can be deleted. Use void to cancel an issued invoice.',
                Response::HTTP_CONFLICT,
            );
        }

        $invoice->delete();

        $this->audit->log('invoice.deleted', $invoice, [
            'description' => "Draft invoice {$invoice->invoice_number} deleted.",
        ]);

        return response()->json(['data' => ['message' => 'Invoice deleted.']]);
    }

    // ── Status transitions ───────────────────────────────────────────────

    public function issue(Invoice $invoice): JsonResponse
    {
        try {
            $this->billing->issueInvoice($invoice, (int) $this->context->userId());
        } catch (ValidationException $e) {
            return ApiError::make('VALIDATION_ERROR', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
        }

        $this->audit->log('invoice.issued', $invoice, [
            'description' => "Invoice {$invoice->invoice_number} issued (total: {$invoice->total_poisha} poisha).",
        ]);

        return response()->json(['data' => InvoiceResource::make($invoice->refresh()->load(['customer', 'lines']))]);
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $this->billing->voidInvoice($invoice, $request->string('void_reason')->toString(), (int) $this->context->userId());
        } catch (ValidationException $e) {
            return ApiError::make('VALIDATION_ERROR', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
        }

        $this->audit->log('invoice.voided', $invoice, [
            'description' => "Invoice {$invoice->invoice_number} voided.",
            'new' => ['void_reason' => $request->string('void_reason')],
        ]);

        return response()->json(['data' => InvoiceResource::make($invoice->refresh())]);
    }

    // ── Line management ──────────────────────────────────────────────────

    public function storeLine(StoreInvoiceLineRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $line = $this->billing->addLine($invoice, $request->validated());
        } catch (ValidationException $e) {
            return ApiError::make('VALIDATION_ERROR', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
        }

        return InvoiceLineResource::make($line)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateLine(StoreInvoiceLineRequest $request, Invoice $invoice, InvoiceLine $line): JsonResponse
    {
        // Ensure the line belongs to this invoice (scopeBindings handles this in routes).
        if ($line->invoice_id !== $invoice->getKey()) {
            return ApiError::make('NOT_FOUND', 'Resource not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $line = $this->billing->updateLine($invoice, $line, $request->validated());
        } catch (ValidationException $e) {
            return ApiError::make('VALIDATION_ERROR', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
        }

        return response()->json(['data' => InvoiceLineResource::make($line)]);
    }

    public function destroyLine(Invoice $invoice, InvoiceLine $line): JsonResponse
    {
        if ($line->invoice_id !== $invoice->getKey()) {
            return ApiError::make('NOT_FOUND', 'Resource not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $this->billing->removeLine($invoice, $line);
        } catch (ValidationException $e) {
            return ApiError::make('VALIDATION_ERROR', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
        }

        return response()->json(['data' => ['message' => 'Line removed.']]);
    }
}
