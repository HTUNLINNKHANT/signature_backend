<?php

namespace App\Http\Controllers;

use App\Models\WholesaleApplication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class WholesaleApplicationController extends Controller
{
    /**
     * Submit a new wholesale application (public endpoint)
     */
    public function submit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|in:retailer,distributor,online_store,other',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone_number' => 'required|string|max:20',
            'country_region' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state_province' => 'nullable|string|max:255',
            'product_interests' => 'required|array|min:1',
            'product_interests.*' => 'string|in:shorts,t-shirts,tracksuits,polo_shirts,vests,leggings,others',
            'additional_information' => 'nullable|string|max:1000',
            'shipping_address' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $application = WholesaleApplication::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Wholesale application submitted successfully. We will review your application and contact you soon.',
                'data' => $application
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application. Please try again.'
            ], 500);
        }
    }

    /**
     * Get all wholesale applications (admin only)
     */
    public function index(Request $request): JsonResponse
    {
        $query = WholesaleApplication::with('reviewer:id,name')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('business_type') && $request->business_type !== '') {
            $query->where('business_type', $request->business_type);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $applications = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    /**
     * Get a specific wholesale application (admin only)
     */
    public function show(WholesaleApplication $application): JsonResponse
    {
        $application->load('reviewer:id,name');

        return response()->json([
            'success' => true,
            'data' => $application
        ]);
    }

    /**
     * Update application status (admin only)
     */
    public function updateStatus(Request $request, WholesaleApplication $application): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,approved,rejected,under_review',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $application->update([
                'status' => $request->status,
                'admin_notes' => $request->admin_notes,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ]);

            $application->load('reviewer:id,name');

            return response()->json([
                'success' => true,
                'message' => 'Application status updated successfully',
                'data' => $application
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update application status'
            ], 500);
        }
    }

    /**
     * Delete a wholesale application (admin only)
     */
    public function destroy(WholesaleApplication $application): JsonResponse
    {
        try {
            $application->delete();

            return response()->json([
                'success' => true,
                'message' => 'Application deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete application'
            ], 500);
        }
    }

    /**
     * Get application statistics (admin only)
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => WholesaleApplication::count(),
            'pending' => WholesaleApplication::where('status', 'pending')->count(),
            'approved' => WholesaleApplication::where('status', 'approved')->count(),
            'rejected' => WholesaleApplication::where('status', 'rejected')->count(),
            'under_review' => WholesaleApplication::where('status', 'under_review')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get business types for dropdown
     */
    public function getBusinessTypes(): JsonResponse
    {
        $types = [
            ['value' => 'retailer', 'label' => 'Retailer'],
            ['value' => 'distributor', 'label' => 'Distributor'],
            ['value' => 'online_store', 'label' => 'Online Store'],
            ['value' => 'other', 'label' => 'Other'],
        ];

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Get product categories for dropdown
     */
    public function getProductCategories(): JsonResponse
    {
        $categories = [
            ['value' => 'shorts', 'label' => 'Shorts'],
            ['value' => 't-shirts', 'label' => 'T-Shirts'],
            ['value' => 'tracksuits', 'label' => 'Tracksuits'],
            ['value' => 'polo_shirts', 'label' => 'Polo Shirts'],
            ['value' => 'vests', 'label' => 'Vests'],
            ['value' => 'leggings', 'label' => 'Leggings'],
            ['value' => 'others', 'label' => 'Others'],
        ];

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
}
