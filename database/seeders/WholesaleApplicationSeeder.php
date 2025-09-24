<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WholesaleApplication;

class WholesaleApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applications = [
            [
                'business_name' => 'Fashion Forward Retail',
                'business_type' => 'retailer',
                'full_name' => 'John Smith',
                'email' => 'john@fashionforward.com',
                'phone_number' => '+1-555-0123',
                'country_region' => 'United States',
                'city' => 'New York',
                'state_province' => 'NY',
                'product_interests' => ['t-shirts', 'shorts', 'polo_shirts'],
                'additional_information' => 'We are a growing retail chain with 5 locations in NYC. Looking for quality sportswear to expand our athletic wear section.',
                'shipping_address' => '123 Fashion Ave, New York, NY 10001',
                'status' => 'pending',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'business_name' => 'SportZone Distribution',
                'business_type' => 'distributor',
                'full_name' => 'Maria Garcia',
                'email' => 'maria@sportzone.com',
                'phone_number' => '+1-555-0456',
                'country_region' => 'Canada',
                'city' => 'Toronto',
                'state_province' => 'ON',
                'product_interests' => ['tracksuits', 'leggings', 'vests', 'shorts'],
                'additional_information' => 'We distribute to over 50 retail locations across Ontario and Quebec. Interested in becoming your exclusive Canadian distributor.',
                'shipping_address' => '456 Distribution Blvd, Toronto, ON M5V 3A8',
                'status' => 'under_review',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(1),
            ],
        ];

        foreach ($applications as $application) {
            WholesaleApplication::create($application);
        }
    }
}
