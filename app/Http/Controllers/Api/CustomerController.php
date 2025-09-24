<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::whereHas('roles', function($q) {
            $q->where('name', 'customer');
        })->with(['roles', 'orders']);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', $request->status === 'active');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination - Fixed at 10 items per page
        $customers = $query->paginate(10);

        // Add additional data for each customer
        $customers->getCollection()->transform(function ($customer) {
            $customer->total_orders = $customer->orders->count();
            $customer->total_spent = $customer->orders->sum('total_amount');
            $customer->last_order_date = $customer->orders->max('created_at');
            return $customer;
        });

        return response()->json([
            'success' => true,
            'data' => $customers,
            'message' => 'Customers retrieved successfully'
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create the user
            $customer = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'date_of_birth' => $request->date_of_birth,
                'is_active' => $request->get('is_active', true),
                'email_verified_at' => now(), // Auto-verify admin created customers
            ]);

            // Assign customer role
            $customerRole = Role::where('name', 'customer')->first();
            if ($customerRole) {
                $customer->roles()->attach($customerRole->id);
            }

            // Load relationships for response
            $customer->load(['roles', 'orders']);
            $customer->total_orders = 0;
            $customer->total_spent = 0;
            $customer->last_order_date = null;

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'Customer created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $customer = User::whereHas('roles', function($q) {
                $q->where('name', 'customer');
            })->with(['roles', 'orders', 'addresses'])->findOrFail($id);

            // Add additional data
            $customer->total_orders = $customer->orders->count();
            $customer->total_spent = $customer->orders->sum('total_amount');
            $customer->last_order_date = $customer->orders->max('created_at');

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'Customer retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $customer = User::whereHas('roles', function($q) {
                $q->where('name', 'customer');
            })->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($customer->id)],
                'password' => 'nullable|string|min:8|confirmed',
                'phone' => 'nullable|string|max:20',
                'date_of_birth' => 'nullable|date|before:today',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update customer data
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'date_of_birth' => $request->date_of_birth,
                'is_active' => $request->get('is_active', $customer->is_active),
            ];

            // Only update password if provided
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $customer->update($updateData);

            // Load relationships for response
            $customer->load(['roles', 'orders']);
            $customer->total_orders = $customer->orders->count();
            $customer->total_spent = $customer->orders->sum('total_amount');
            $customer->last_order_date = $customer->orders->max('created_at');

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'Customer updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found or update failed',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $customer = User::whereHas('roles', function($q) {
                $q->where('name', 'customer');
            })->findOrFail($id);

            // Check if customer has orders
            if ($customer->orders()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete customer with existing orders. Consider deactivating instead.'
                ], 400);
            }

            $customer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found or deletion failed',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Toggle customer active status.
     */
    public function toggleStatus(string $id): JsonResponse
    {
        try {
            $customer = User::whereHas('roles', function($q) {
                $q->where('name', 'customer');
            })->findOrFail($id);

            $customer->update([
                'is_active' => !$customer->is_active
            ]);

            $customer->load(['roles', 'orders']);
            $customer->total_orders = $customer->orders->count();
            $customer->total_spent = $customer->orders->sum('total_amount');
            $customer->last_order_date = $customer->orders->max('created_at');

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'Customer status updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found or status update failed',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Toggle customer VIP status.
     */
    public function toggleVipStatus(string $id): JsonResponse
    {
        try {
            $customer = User::whereHas('roles', function($q) {
                $q->where('name', 'customer');
            })->findOrFail($id);

            $isVip = !$customer->is_vip;
            $vipSince = $isVip ? now() : null;

            $customer->update([
                'is_vip' => $isVip,
                'vip_since' => $vipSince
            ]);

            $customer->load(['roles', 'orders']);
            $customer->total_orders = $customer->orders->count();
            $customer->total_spent = $customer->orders->sum('total_amount');
            $customer->last_order_date = $customer->orders->max('created_at');

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => $isVip ? 'Customer promoted to VIP successfully' : 'Customer VIP status removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found or VIP status update failed',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Bulk update VIP status for multiple customers.
     */
    public function bulkUpdateVipStatus(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_ids' => 'required|array',
                'customer_ids.*' => 'integer|exists:users,id',
                'is_vip' => 'required|boolean'
            ]);

            $customers = User::whereHas('roles', function($q) {
                $q->where('name', 'customer');
            })->whereIn('id', $validated['customer_ids'])->get();

            $vipSince = $validated['is_vip'] ? now() : null;

            foreach ($customers as $customer) {
                $customer->update([
                    'is_vip' => $validated['is_vip'],
                    'vip_since' => $vipSince
                ]);
            }

            $message = $validated['is_vip'] 
                ? count($validated['customer_ids']) . ' customers promoted to VIP successfully'
                : count($validated['customer_ids']) . ' customers VIP status removed successfully';

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk VIP status update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer statistics.
     */
    public function statistics(): JsonResponse
    {
        try {
            $totalCustomers = User::whereHas('roles', function($q) {
                $q->where('name', 'customer');
            })->count();

            $activeCustomers = User::whereHas('roles', function($q) {
                $q->where('name', 'customer');
            })->where('is_active', true)->count();

            $newCustomersThisMonth = User::whereHas('roles', function($q) {
                $q->where('name', 'customer');
            })->whereMonth('created_at', now()->month)
              ->whereYear('created_at', now()->year)
              ->count();

            $customersWithOrders = User::whereHas('roles', function($q) {
                $q->where('name', 'customer');
            })->whereHas('orders')->count();

            $vipCustomers = User::whereHas('roles', function($q) {
                $q->where('name', 'customer');
            })->where('is_vip', true)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_customers' => $totalCustomers,
                    'active_customers' => $activeCustomers,
                    'inactive_customers' => $totalCustomers - $activeCustomers,
                    'new_customers_this_month' => $newCustomersThisMonth,
                    'customers_with_orders' => $customersWithOrders,
                    'vip_customers' => $vipCustomers,
                ],
                'message' => 'Customer statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customer statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export customers to CSV or Excel.
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'csv'); // Default to CSV
            
            $query = User::whereHas('roles', function($q) {
                $q->where('name', 'customer');
            })->with(['roles', 'orders']);

            // Apply same filters as index method
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('phone', 'LIKE', "%{$search}%");
                });
            }

            if ($request->has('status') && $request->status !== '') {
                $query->where('is_active', $request->status === 'active');
            }

            $customers = $query->get();

            // Prepare data
            $headers = [
                'ID',
                'Name',
                'Email',
                'Phone',
                'Date of Birth',
                'Status',
                'VIP Status',
                'Total Orders',
                'Total Spent (KS)',
                'Last Order Date',
                'Joined Date'
            ];

            $data = [];
            foreach ($customers as $customer) {
                $totalOrders = $customer->orders->count();
                $totalSpent = $customer->orders->sum('total_amount');
                $lastOrderDate = $customer->orders->max('created_at');

                $data[] = [
                    $customer->id,
                    $customer->name,
                    $customer->email,
                    $customer->phone ?: 'N/A',
                    $customer->date_of_birth ?: 'N/A',
                    $customer->is_active ? 'Active' : 'Inactive',
                    $customer->is_vip ? 'VIP Member' : 'Regular',
                    $totalOrders,
                    number_format($totalSpent, 0) . ' KS',
                    $lastOrderDate ? $lastOrderDate->format('Y-m-d H:i:s') : 'N/A',
                    $customer->created_at->format('Y-m-d H:i:s')
                ];
            }

            if ($format === 'excel') {
                return $this->exportToExcel($headers, $data);
            } else {
                return $this->exportToCsv($headers, $data);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export customers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data to CSV format.
     */
    private function exportToCsv($headers, $data)
    {
        $filename = 'customers_export_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $callback = function() use ($headers, $data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export data to Excel format.
     */
    private function exportToExcel($headers, $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $sheet->fromArray($headers, null, 'A1');
        
        // Style headers
        $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E2E8F0');
        
        // Add data
        $sheet->fromArray($data, null, 'A2');
        
        // Auto-size columns
        foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $filename = 'customers_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        
        // Create writer and save to output
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        return response()->stream(function() use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
