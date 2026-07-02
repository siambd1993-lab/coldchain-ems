<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\InteractsWithApiRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\StockLot;
use App\Services\AuditLogger;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Depositors (customers who rent storage). Tenant-scoped; not branch-restricted —
 * a customer may hold goods across several branches, so the listing spans the
 * whole tenant. All rows are isolated by the global {@see \App\Models\Scopes\TenantScope}.
 */
final class CustomerController extends Controller
{
    use InteractsWithApiRequest;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $customers = Customer::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($sub) use ($term): void {
                    $sub->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated());

        $this->audit->log('customer.created', $customer, [
            'description' => "Customer {$customer->code} created.",
            'new' => $customer->only(['code', 'name', 'type', 'status']),
        ]);

        return CustomerResource::make($customer)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Customer $customer): CustomerResource
    {
        return CustomerResource::make($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $before = $customer->only(['code', 'name', 'type', 'status', 'credit_limit_poisha']);

        $customer->update($request->validated());

        $this->audit->log('customer.updated', $customer, [
            'description' => "Customer {$customer->code} updated.",
            'old' => $before,
            'new' => $customer->only(['code', 'name', 'type', 'status', 'credit_limit_poisha']),
        ]);

        return CustomerResource::make($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        // Refuse to remove a depositor who still has goods in storage — their
        // lots would be orphaned and the audit trail broken.
        $hasActiveLots = StockLot::withoutBranchScope()
            ->where('customer_id', $customer->getKey())
            ->whereIn('status', ['in_storage', 'partially_released'])
            ->exists();

        if ($hasActiveLots) {
            return ApiError::make(
                'customer_has_active_stock',
                'This customer still has goods in storage and cannot be deleted.',
                Response::HTTP_CONFLICT,
            );
        }

        $customer->delete();

        $this->audit->log('customer.deleted', $customer, [
            'description' => "Customer {$customer->code} deleted.",
        ]);

        return response()->json(['data' => ['message' => 'Customer deleted.']]);
    }
}
