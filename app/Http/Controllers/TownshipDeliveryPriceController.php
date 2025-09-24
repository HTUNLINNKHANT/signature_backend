<?php

namespace App\Http\Controllers;

use App\Models\TownshipDeliveryPrice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TownshipDeliveryPriceController extends Controller
{
    /**
     * Display a listing of township delivery prices.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TownshipDeliveryPrice::query();

        // Filter by state/region if provided
        if ($request->has('state_region') && $request->state_region) {
            $query->byStateRegion($request->state_region);
        }

        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('township_name', 'like', "%{$search}%")
                  ->orWhere('township_name_mm', 'like', "%{$search}%")
                  ->orWhere('state_region', 'like', "%{$search}%")
                  ->orWhere('state_region_mm', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'township_name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination - Fixed at 10 items per page
        $townships = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $townships,
            'message' => 'Township delivery prices retrieved successfully'
        ]);
    }

    /**
     * Store a newly created township delivery price.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'township_name' => 'required|string|max:255',
                'township_name_mm' => 'nullable|string|max:255',
                'state_region' => 'required|string|max:255',
                'state_region_mm' => 'nullable|string|max:255',
                'delivery_price' => 'required|numeric|min:0',
                'estimated_days' => 'required|integer|min:1|max:30',
                'is_active' => 'boolean',
                'notes' => 'nullable|string|max:1000'
            ]);

            // Check for duplicate township in same state/region
            $exists = TownshipDeliveryPrice::where('township_name', $validated['township_name'])
                ->where('state_region', $validated['state_region'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Township delivery price already exists for this location'
                ], 422);
            }

            $township = TownshipDeliveryPrice::create($validated);

            return response()->json([
                'success' => true,
                'data' => $township,
                'message' => 'Township delivery price created successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Display the specified township delivery price.
     */
    public function show(TownshipDeliveryPrice $townshipDeliveryPrice): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $townshipDeliveryPrice,
            'message' => 'Township delivery price retrieved successfully'
        ]);
    }

    /**
     * Update the specified township delivery price.
     */
    public function update(Request $request, TownshipDeliveryPrice $townshipDeliveryPrice): JsonResponse
    {
        try {
            $validated = $request->validate([
                'township_name' => 'required|string|max:255',
                'township_name_mm' => 'nullable|string|max:255',
                'state_region' => 'required|string|max:255',
                'state_region_mm' => 'nullable|string|max:255',
                'delivery_price' => 'required|numeric|min:0',
                'estimated_days' => 'required|integer|min:1|max:30',
                'is_active' => 'boolean',
                'notes' => 'nullable|string|max:1000'
            ]);

            // Check for duplicate township in same state/region (excluding current record)
            $exists = TownshipDeliveryPrice::where('township_name', $validated['township_name'])
                ->where('state_region', $validated['state_region'])
                ->where('id', '!=', $townshipDeliveryPrice->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Township delivery price already exists for this location'
                ], 422);
            }

            $townshipDeliveryPrice->update($validated);

            return response()->json([
                'success' => true,
                'data' => $townshipDeliveryPrice->fresh(),
                'message' => 'Township delivery price updated successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Remove the specified township delivery price.
     */
    public function destroy(TownshipDeliveryPrice $townshipDeliveryPrice): JsonResponse
    {
        $townshipDeliveryPrice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Township delivery price deleted successfully'
        ]);
    }

    /**
     * Get delivery price for a specific township
     */
    public function getDeliveryPrice(Request $request): JsonResponse
    {
        $request->validate([
            'township_name' => 'required|string',
            'state_region' => 'required|string'
        ]);

        $township = TownshipDeliveryPrice::active()
            ->where('township_name', $request->township_name)
            ->where('state_region', $request->state_region)
            ->first();

        if (!$township) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery price not found for this township'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'delivery_price' => $township->delivery_price,
                'estimated_days' => $township->estimated_days,
                'formatted_price' => $township->formatted_delivery_price
            ],
            'message' => 'Delivery price retrieved successfully'
        ]);
    }

    /**
     * Get unique state/regions
     */
    public function getStateRegions(): JsonResponse
    {
        $stateRegions = TownshipDeliveryPrice::select('state_region', 'state_region_mm')
            ->distinct()
            ->orderBy('state_region')
            ->get()
            ->map(function ($item) {
                $label = $item->state_region;
                if ($item->state_region_mm) {
                    $label .= ' (' . $item->state_region_mm . ')';
                }
                return [
                    'value' => $item->state_region,
                    'label' => $label
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $stateRegions,
            'message' => 'State/regions retrieved successfully'
        ]);
    }

    /**
     * Get townships by state/region
     */
    public function getTownshipsByRegion(Request $request): JsonResponse
    {
        $request->validate([
            'state_region' => 'required|string'
        ]);

        $townships = TownshipDeliveryPrice::active()
            ->where('state_region', $request->state_region)
            ->select('township_name', 'township_name_mm', 'delivery_price', 'estimated_days')
            ->orderBy('township_name')
            ->get()
            ->map(function ($item) {
                $label = $item->township_name;
                if ($item->township_name_mm) {
                    $label .= ' (' . $item->township_name_mm . ')';
                }
                return [
                    'value' => $item->township_name,
                    'label' => $label,
                    'delivery_price' => $item->delivery_price,
                    'estimated_days' => $item->estimated_days
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $townships,
            'message' => 'Townships retrieved successfully'
        ]);
    }

    /**
     * Get all available locations (state/regions with townships)
     */
    public function getAvailableLocations(): JsonResponse
    {
        $locations = TownshipDeliveryPrice::active()
            ->select('state_region', 'state_region_mm', 'township_name', 'township_name_mm', 'delivery_price', 'estimated_days')
            ->orderBy('state_region')
            ->orderBy('township_name')
            ->get()
            ->groupBy('state_region')
            ->map(function ($townships, $stateRegion) {
                $stateRegionMm = $townships->first()->state_region_mm;
                $stateLabel = $stateRegion;
                if ($stateRegionMm) {
                    $stateLabel .= ' (' . $stateRegionMm . ')';
                }

                return [
                    'value' => $stateRegion,
                    'label' => $stateLabel,
                    'townships' => $townships->map(function ($township) {
                        $townshipLabel = $township->township_name;
                        if ($township->township_name_mm) {
                            $townshipLabel .= ' (' . $township->township_name_mm . ')';
                        }
                        return [
                            'value' => $township->township_name,
                            'label' => $townshipLabel,
                            'delivery_price' => $township->delivery_price,
                            'estimated_days' => $township->estimated_days
                        ];
                    })->values()
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $locations,
            'message' => 'Available locations retrieved successfully'
        ]);
    }

    /**
     * Bulk update active status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:township_delivery_prices,id',
            'is_active' => 'required|boolean'
        ]);

        $updated = TownshipDeliveryPrice::whereIn('id', $request->ids)
            ->update(['is_active' => $request->is_active]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} township delivery prices updated successfully"
        ]);
    }
}
