<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Concerns\InteractsWithApiRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\AllocatePaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Services\Billing\BillingService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use App\Support\ApiError;

/**
 * Payment receipts and their allocation across outstanding invoices.
 * All amounts in poisha (minor units).
 */
final class PaymentController extends Controller
{
    use InteractsWithApiRequest;

    public function __construct(
        private readonly BillingService $billing,
        private readonly AuditLogger    $audit,
        private readonly TenantContext  $context,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->string('method')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('paid_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('paid_at', '<=', $request->string('to')))
            ->with('customer')
            ->orderByDesc('paid_at')
            ->paginate($this->perPage($request));

        return PaymentResource::collection($payments);
    }

    public function show(Payment $payment): PaymentResource
    {
        $payment->load(['customer', 'allocations']);

        return PaymentResource::make($payment);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data        = $request->validated();
        $allocations = $data['allocations'] ?? [];
        unset($data['allocations']);

        try {
            $payment = $this->billing->recordPayment(
                $data,
                $allocations,
                (int) $this->context->tenantId(),
            );
        } catch (ValidationException $e) {
            return ApiError::make('VALIDATION_ERROR', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
        }

        $this->audit->log('payment.recorded', $payment, [
            'description' => "Payment {$payment->payment_number} of {$payment->amount_poisha} poisha recorded.",
            'new' => $payment->only(['payment_number', 'method', 'amount_poisha', 'customer_id']),
        ]);

        $payment->load(['customer', 'allocations']);

        return PaymentResource::make($payment)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Apply additional allocations to an already-recorded payment.
     */
    public function allocate(AllocatePaymentRequest $request, Payment $payment): JsonResponse
    {
        if (! $payment->isCompleted()) {
            return ApiError::make(
                'payment_not_completed',
                'Only completed payments can be allocated to invoices.',
                Response::HTTP_CONFLICT,
            );
        }

        try {
            $this->billing->applyAllocations($payment, $request->validated()['allocations']);
        } catch (ValidationException $e) {
            return ApiError::make('VALIDATION_ERROR', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
        }

        $this->audit->log('payment.allocated', $payment, [
            'description' => "Payment {$payment->payment_number} allocated to invoices.",
        ]);

        $payment->refresh()->load(['customer', 'allocations']);

        return response()->json(['data' => PaymentResource::make($payment)]);
    }
}
