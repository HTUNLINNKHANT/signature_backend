<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\TownshipDeliveryPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->get();
        
        // Add formatted_address to each address
        $addresses->each(function ($address) {
            $address->append('formatted_address');
        });
        
        return response()->json($addresses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'company' => 'nullable|string|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'township' => 'required|string|max:255',
            'state_region' => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:255',
            'is_default' => 'boolean'
        ]);

        $address = DB::transaction(function () use ($request, $validated) {
            $user = $request->user();
            // Ensure the user's name is always used for the new address
            $addressData = array_merge($validated, ['name' => $user->name]);
            $address = $user->addresses()->create($addressData);

            if ($request->input('is_default', false)) {
                $this->handleDefaultAddress($user, $address->id);
                $address->refresh();
            }

            return $address;
        });

        return response()->json($address, 201);
    }

    public function show(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);
        return response()->json($address);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'address_line_1' => 'sometimes|required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'township' => 'nullable|string|max:255',
            'state_region' => 'nullable|string|max:255',
            'state_province' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'sometimes|required|string|max:255',
            'is_default' => 'sometimes|boolean',
        ]);

        $address = DB::transaction(function () use ($request, $validated, $id) {
            $user = $request->user();
            $address = $user->addresses()->findOrFail($id);

            $address->update($validated);

            if ($request->has('is_default')) {
                if ($request->input('is_default')) {
                    $this->handleDefaultAddress($user, $address->id);
                }
            }
            
            $address->refresh();
            return $address;
        });

        return response()->json($address);
    }

    public function destroy(Request $request, $id)
    {
        $address = DB::transaction(function () use ($request, $id) {
            $address = $request->user()->addresses()->findOrFail($id);
            $address->delete();
            return $address;
        });

        return response()->json(null, 204);
    }

    public function setDefault(Request $request, $id)
    {
        $address = DB::transaction(function () use ($request, $id) {
            $user = $request->user();
            $address = $user->addresses()->findOrFail($id);
            $this->handleDefaultAddress($user, $address->id);
            $address->refresh();
            return $address;
        });

        return response()->json($address);
    }

    /**
     * Calculate delivery fee for an address
     */
    public function calculateDeliveryFee(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);
        
        if (!$address->township || !$address->state_region) {
            return response()->json([
                'error' => 'Township and state/region are required for delivery fee calculation'
            ], 400);
        }

        // Try exact match first
        $deliveryPrice = TownshipDeliveryPrice::active()
            ->where('township_name', $address->township)
            ->where('state_region', $address->state_region)
            ->first();

        // If no exact match, try case-insensitive match
        if (!$deliveryPrice) {
            $deliveryPrice = TownshipDeliveryPrice::active()
                ->whereRaw('LOWER(township_name) = LOWER(?)', [$address->township])
                ->whereRaw('LOWER(state_region) = LOWER(?)', [$address->state_region])
                ->first();
        }

        // If still no match, try partial matching for township
        if (!$deliveryPrice) {
            $deliveryPrice = TownshipDeliveryPrice::active()
                ->whereRaw('LOWER(state_region) = LOWER(?)', [$address->state_region])
                ->where(function($query) use ($address) {
                    $query->whereRaw('LOWER(township_name) LIKE LOWER(?)', ['%' . $address->township . '%'])
                          ->orWhereRaw('LOWER(?) LIKE LOWER(CONCAT("%", township_name, "%"))', [$address->township]);
                })
                ->first();
        }

        if (!$deliveryPrice) {
            // Log the failed lookup for debugging
            Log::warning('Delivery fee lookup failed', [
                'address_township' => $address->township,
                'address_state_region' => $address->state_region,
                'address_id' => $address->id
            ]);
            
            return response()->json([
                'error' => 'Delivery not available for this location',
                'debug_info' => [
                    'township' => $address->township,
                    'state_region' => $address->state_region
                ]
            ], 404);
        }

        return response()->json([
            'delivery_fee' => (float) $deliveryPrice->delivery_price,
            'estimated_days' => $deliveryPrice->estimated_days,
            'township_delivery_id' => $deliveryPrice->id,
            'formatted_price' => $deliveryPrice->formatted_delivery_price
        ]);
    }

    /**
     * Get available townships for delivery
     */
    public function getAvailableTownships(Request $request)
    {
        $stateRegion = $request->query('state_region');
        
        $query = TownshipDeliveryPrice::active();
        
        if ($stateRegion) {
            $query->where('state_region', $stateRegion);
        }
        
        $townships = $query->select('township_name', 'state_region', 'delivery_price', 'estimated_days')
            ->orderBy('state_region')
            ->orderBy('township_name')
            ->get()
            ->groupBy('state_region');

        return response()->json($townships);
    }

    private function handleDefaultAddress($user, $newDefaultAddressId)
    {
        $user->addresses()->where('id', '!=', $newDefaultAddressId)->update(['is_default' => false]);
        $user->addresses()->where('id', $newDefaultAddressId)->update(['is_default' => true]);
    }
}
