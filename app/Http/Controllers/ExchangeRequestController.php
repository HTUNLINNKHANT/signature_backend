<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ExchangeRequestController extends Controller
{
    /**
     * Display a listing of exchange requests for admin
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExchangeRequest::with(['processedBy']);

        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('order_number', 'like', "%{$searchTerm}%")
                  ->orWhere('email_address', 'like', "%{$searchTerm}%")
                  ->orWhere('product_to_exchange_name', 'like', "%{$searchTerm}%")
                  ->orWhere('replacement_request_name', 'like', "%{$searchTerm}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSorts = ['created_at', 'updated_at', 'status', 'order_number'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $perPage = min($perPage, 100); // Max 100 items per page
        
        $exchangeRequests = $query->paginate($perPage);

        // Transform the data
        $exchangeRequests->getCollection()->transform(function ($request) {
            return $this->transformExchangeRequest($request);
        });

        return response()->json($exchangeRequests);
    }

    /**
     * Display the specified exchange request for admin
     */
    public function show(string $id): JsonResponse
    {
        $exchangeRequest = ExchangeRequest::with(['processedBy'])->findOrFail($id);
        
        return response()->json($this->transformExchangeRequest($exchangeRequest));
    }

    /**
     * Update exchange request status
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $exchangeRequest = ExchangeRequest::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected,processing,completed,cancelled',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Get authenticated user ID
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // Update the exchange request
            $exchangeRequest->update([
                'status' => $validated['status'],
                'admin_notes' => $validated['admin_notes'] ?? $exchangeRequest->admin_notes,
                'processed_by' => $userId,
                'processed_at' => now(),
            ]);

            // Reload with relationships
            $exchangeRequest->load('processedBy');

            return response()->json([
                'success' => true,
                'message' => 'Exchange request updated successfully',
                'data' => $this->transformExchangeRequest($exchangeRequest),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update exchange request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get exchange request statistics for admin dashboard
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total' => ExchangeRequest::count(),
                'pending' => ExchangeRequest::where('status', 'pending')->count(),
                'approved' => ExchangeRequest::where('status', 'approved')->count(),
                'rejected' => ExchangeRequest::where('status', 'rejected')->count(),
                'processing' => ExchangeRequest::where('status', 'processing')->count(),
                'completed' => ExchangeRequest::where('status', 'completed')->count(),
                'cancelled' => ExchangeRequest::where('status', 'cancelled')->count(),
            ];

            return response()->json($stats);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete exchange request
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $exchangeRequest = ExchangeRequest::findOrFail($id);
            $exchangeRequest->delete();

            return response()->json([
                'success' => true,
                'message' => 'Exchange request deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete exchange request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created exchange request
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string|max:255',
            'date_of_purchase' => 'required|date',
            'email_address' => 'required|email|max:255',
            'phone_number' => 'required|string|max:20',
            'product_to_exchange_name' => 'required|string|max:255',
            'size' => 'required|string|max:10',
            'item_color' => 'required|string|max:100',
            'reason_for_exchange' => 'required|string|max:255',
            'replacement_request_item' => 'required|string|max:255',
            'replacement_request_name' => 'required|string|max:255',
            'additional_comments' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $exchangeRequest = ExchangeRequest::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Exchange request submitted successfully',
                'data' => $this->transformExchangeRequest($exchangeRequest),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit exchange request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Transform an ExchangeRequest model to include frontend-friendly fields
     * without losing original attributes.
     */
    private function transformExchangeRequest(ExchangeRequest $er): array
    {
        $data = $er->toArray();

        // Provide processed_by_user alias expected by the frontend
        if ($er->relationLoaded('processedBy') && $er->processedBy) {
            $data['processed_by_user'] = [
                'id' => $er->processedBy->id,
                'name' => $er->processedBy->name,
                'email' => $er->processedBy->email,
            ];
        } else {
            $data['processed_by_user'] = null;
        }

        return $data;
    }
}
