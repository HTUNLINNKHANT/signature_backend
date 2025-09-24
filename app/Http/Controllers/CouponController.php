<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query();

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            switch ($request->status) {
                case 'active':
                    $query->active();
                    break;
                case 'inactive':
                    $query->where('is_active', false);
                    break;
                case 'expired':
                    $query->where('expires_at', '<', Carbon::now());
                    break;
                case 'scheduled':
                    $query->where('starts_at', '>', Carbon::now());
                    break;
            }
        }

        // Discount type filter
        if ($request->filled('discount_type')) {
            $query->where('discount_type', $request->discount_type);
        }

        $coupons = $query->orderBy('created_at', 'desc')->get();

        // Add computed status to each coupon
        $coupons->each(function ($coupon) {
            $coupon->computed_status = $coupon->status;
            $coupon->computed_discount_display = $coupon->discount_display;
        });

        return response()->json($coupons);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'name' => 'required|string|max:255',
            'discount_type' => 'required|in:percentage,fixed_amount',
            'discount_value' => 'required|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'description' => 'nullable|string|max:1000',
        ]);

        // Additional validation for percentage discount
        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
            return response()->json([
                'message' => 'Percentage discount cannot exceed 100%',
                'errors' => ['discount_value' => ['Percentage discount cannot exceed 100%']]
            ], 422);
        }

        // Convert dates to proper format
        if (isset($validated['starts_at'])) {
            $validated['starts_at'] = Carbon::parse($validated['starts_at']);
        }
        if (isset($validated['expires_at'])) {
            $validated['expires_at'] = Carbon::parse($validated['expires_at']);
        }

        $coupon = Coupon::create($validated);
        $coupon->computed_status = $coupon->status;
        $coupon->computed_discount_display = $coupon->discount_display;

        return response()->json([
            'message' => 'Coupon created successfully',
            'coupon' => $coupon
        ], 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        $coupon->computed_status = $coupon->status;
        $coupon->computed_discount_display = $coupon->discount_display;
        
        return response()->json($coupon);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons')->ignore($coupon->id)],
            'name' => 'required|string|max:255',
            'discount_type' => 'required|in:percentage,fixed_amount',
            'discount_value' => 'required|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'description' => 'nullable|string|max:1000',
        ]);

        // Additional validation for percentage discount
        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
            return response()->json([
                'message' => 'Percentage discount cannot exceed 100%',
                'errors' => ['discount_value' => ['Percentage discount cannot exceed 100%']]
            ], 422);
        }

        // Convert dates to proper format
        if (isset($validated['starts_at'])) {
            $validated['starts_at'] = Carbon::parse($validated['starts_at']);
        }
        if (isset($validated['expires_at'])) {
            $validated['expires_at'] = Carbon::parse($validated['expires_at']);
        }

        $coupon->update($validated);
        $coupon->computed_status = $coupon->status;
        $coupon->computed_discount_display = $coupon->discount_display;

        return response()->json([
            'message' => 'Coupon updated successfully',
            'coupon' => $coupon
        ]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        // Check if coupon has been used
        if ($coupon->used_count > 0) {
            return response()->json([
                'message' => 'Cannot delete coupon that has been used'
            ], 422);
        }

        $coupon->delete();

        return response()->json([
            'message' => 'Coupon deleted successfully'
        ]);
    }

    public function validateCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'amount' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', $request->code)->first();

        if (!$coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Coupon not found'
            ], 404);
        }

        if (!$coupon->canBeUsedForAmount($request->amount)) {
            $message = 'Coupon is not valid';
            
            if (!$coupon->is_active) {
                $message = 'Coupon is inactive';
            } elseif ($coupon->starts_at && $coupon->starts_at->gt(Carbon::now())) {
                $message = 'Coupon is not yet active';
            } elseif ($coupon->expires_at && $coupon->expires_at->lt(Carbon::now())) {
                $message = 'Coupon has expired';
            } elseif ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
                $message = 'Coupon usage limit reached';
            } elseif ($coupon->minimum_amount && $request->amount < $coupon->minimum_amount) {
                $message = "Minimum order amount is $" . number_format((float) $coupon->minimum_amount, 2);
            }

            return response()->json([
                'valid' => false,
                'message' => $message
            ], 422);
        }

        $discount = $coupon->calculateDiscount($request->amount);

        return response()->json([
            'valid' => true,
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'discount_type' => $coupon->discount_type,
                'discount_value' => $coupon->discount_value,
                'discount_display' => $coupon->discount_display,
            ],
            'discount_amount' => $discount,
            'message' => 'Coupon applied successfully'
        ]);
    }

    public function getStats(): JsonResponse
    {
        $total = Coupon::count();
        $active = Coupon::active()->count();
        $expired = Coupon::where('expires_at', '<', Carbon::now())->count();
        $used = Coupon::where('used_count', '>', 0)->count();

        return response()->json([
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'used' => $used,
        ]);
    }
}
