<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\InteractsWithApiRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StockAdjustRequest;
use App\Http\Requests\Stock\StockReleaseRequest;
use App\Http\Requests\Stock\StockTransferRequest;
use App\Http\Requests\Stock\StoreStockLotRequest;
use App\Http\Resources\StockLotResource;
use App\Http\Resources\StockMovementResource;
use App\Models\StockLot;
use App\Services\AuditLogger;
use App\Services\Inventory\StockService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inventory: lot lifecycle (intake → movements → release) and the per-lot
 * append-only movement ledger. All rows are scoped by tenant and the caller's
 * active branch.
 *
 * Movement endpoints follow the sub-resource pattern:
 *   POST /stock-lots/{lot}/release   → stock-out
 *   POST /stock-lots/{lot}/adjust    → quantity correction
 *   POST /stock-lots/{lot}/transfer  → relocation within the branch
 */
final class StockController extends Controller
{
    use InteractsWithApiRequest;

    public function __construct(
        private readonly StockService $stock,
        private readonly AuditLogger  $audit,
    ) {
    }

    // ── Lot listing & detail ─────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $lots = StockLot::query()
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('chamber_id'), fn ($q) => $q->where('chamber_id', $request->integer('chamber_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('lot_code', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('marks', 'like', $term));
            })
            ->with(['customer'])
            ->orderByDesc('received_at')
            ->paginate($this->perPage($request));

        return StockLotResource::collection($lots);
    }

    public function show(StockLot $lot): StockLotResource
    {
        $lot->load(['customer', 'product', 'movements' => fn ($q) => $q->with('performer')->orderBy('occurred_at')]);

        return StockLotResource::make($lot);
    }

    /**
     * Movement ledger for a single lot (paginated for lots with many events).
     */
    public function movements(Request $request, StockLot $lot): AnonymousResourceCollection
    {
        $movements = $lot->movements()
            ->with('performer')
            ->orderByDesc('occurred_at')
            ->paginate($this->perPage($request));

        return StockMovementResource::collection($movements);
    }

    // ── Lot mutations ────────────────────────────────────────────────────

    /**
     * Receive goods: create a lot and its opening stock_in movement.
     */
    public function intake(StoreStockLotRequest $request): JsonResponse
    {
        $performedBy = $request->user()?->getKey();

        try {
            $result = $this->stock->intake($request->validated(), $performedBy);
        } catch (ValidationException $e) {
            return ApiError::make('VALIDATION_ERROR', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
        }

        $lot = $result['lot'];

        $this->audit->log('stock.intake', $lot, [
            'description' => "Lot {$lot->lot_code} received ({$lot->quantity} {$lot->unit_of_measure}).",
            'new' => $lot->only(['lot_code', 'customer_id', 'quantity', 'chamber_id', 'status']),
        ]);

        $lot->load('customer');

        return StockLotResource::make($lot)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Release goods to the depositor (partial or full stock-out).
     */
    public function release(StockReleaseRequest $request, StockLot $lot): JsonResponse
    {
        if (! $lot->isInStorage()) {
            return ApiError::make(
                'lot_not_in_storage',
                "Lot {$lot->lot_code} is already released or disposed.",
                Response::HTTP_CONFLICT,
            );
        }

        try {
            $movement = $this->stock->release($lot, $request->validated(), $request->user()?->getKey());
        } catch (ValidationException $e) {
            return ApiError::make('VALIDATION_ERROR', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
        }

        $lot->refresh();

        $this->audit->log('stock.release', $lot, [
            'description' => "Released {$request->input('quantity')} {$lot->unit_of_measure} from lot {$lot->lot_code}.",
            'new' => ['quantity_released' => $request->input('quantity'), 'balance' => (float) $lot->quantity],
        ]);

        return response()->json([
            'data' => [
                'movement' => StockMovementResource::make($movement),
                'lot'      => StockLotResource::make($lot),
            ],
        ]);
    }

    /**
     * Correct a lot's running quantity (damage, recount, write-off…).
     */
    public function adjust(StockAdjustRequest $request, StockLot $lot): JsonResponse
    {
        try {
            $movement = $this->stock->adjust($lot, $request->validated(), $request->user()?->getKey());
        } catch (ValidationException $e) {
            return ApiError::make('VALIDATION_ERROR', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
        }

        $lot->refresh();

        $this->audit->log('stock.adjust', $lot, [
            'description' => "Lot {$lot->lot_code} adjusted by {$request->input('delta')} ({$request->input('reason')}).",
            'new' => ['delta' => $request->input('delta'), 'balance' => (float) $lot->quantity, 'reason' => $request->input('reason')],
        ]);

        return response()->json([
            'data' => [
                'movement' => StockMovementResource::make($movement),
                'lot'      => StockLotResource::make($lot),
            ],
        ]);
    }

    /**
     * Relocate a lot to a different chamber / storage unit within the same branch.
     */
    public function transfer(StockTransferRequest $request, StockLot $lot): JsonResponse
    {
        if (! $lot->isInStorage()) {
            return ApiError::make(
                'lot_not_in_storage',
                "Lot {$lot->lot_code} is not currently in storage.",
                Response::HTTP_CONFLICT,
            );
        }

        try {
            $movement = $this->stock->transfer($lot, $request->validated(), $request->user()?->getKey());
        } catch (ValidationException $e) {
            return ApiError::make('VALIDATION_ERROR', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
        }

        $lot->refresh();

        $this->audit->log('stock.transfer', $lot, [
            'description' => "Lot {$lot->lot_code} transferred to chamber {$request->input('to_chamber_id')}.",
            'new' => ['to_chamber_id' => $request->input('to_chamber_id'), 'to_storage_unit_id' => $request->input('to_storage_unit_id')],
        ]);

        return response()->json([
            'data' => [
                'movement' => StockMovementResource::make($movement),
                'lot'      => StockLotResource::make($lot),
            ],
        ]);
    }
}
