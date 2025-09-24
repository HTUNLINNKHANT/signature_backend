<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TownshipDeliveryPrice;

class TownshipDeliveryPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $townships = [
            // Yangon Region
            [
                'township_name' => 'Yangon',
                'township_name_mm' => 'ရန်ကုန်',
                'state_region' => 'Yangon Region',
                'state_region_mm' => 'ရန်ကုန်တိုင်းဒေသကြီး',
                'delivery_price' => 2000,
                'estimated_days' => 1,
                'is_active' => true,
                'notes' => 'Same day delivery available in city center'
            ],
            [
                'township_name' => 'Insein',
                'township_name_mm' => 'အင်းစိန်',
                'state_region' => 'Yangon Region',
                'state_region_mm' => 'ရန်ကုန်တိုင်းဒေသကြီး',
                'delivery_price' => 2500,
                'estimated_days' => 1,
                'is_active' => true,
                'notes' => null
            ],
            [
                'township_name' => 'Thanlyin',
                'township_name_mm' => 'သန်လျင်',
                'state_region' => 'Yangon Region',
                'state_region_mm' => 'ရန်ကုန်တိုင်းဒေသကြီး',
                'delivery_price' => 3000,
                'estimated_days' => 2,
                'is_active' => true,
                'notes' => null
            ],
            
            // Mandalay Region
            [
                'township_name' => 'Mandalay',
                'township_name_mm' => 'မန္တလေး',
                'state_region' => 'Mandalay Region',
                'state_region_mm' => 'မန္တလေးတိုင်းဒေသကြီး',
                'delivery_price' => 3500,
                'estimated_days' => 2,
                'is_active' => true,
                'notes' => 'Express delivery available'
            ],
            [
                'township_name' => 'Amarapura',
                'township_name_mm' => 'အမရပူရ',
                'state_region' => 'Mandalay Region',
                'state_region_mm' => 'မန္တလေးတိုင်းဒေသကြီး',
                'delivery_price' => 4000,
                'estimated_days' => 2,
                'is_active' => true,
                'notes' => null
            ],
            
            // Naypyidaw
            [
                'township_name' => 'Naypyidaw',
                'township_name_mm' => 'နေပြည်တော်',
                'state_region' => 'Naypyidaw Union Territory',
                'state_region_mm' => 'နေပြည်တော် ပြည်ထောင်စုနယ်မြေ',
                'delivery_price' => 4500,
                'estimated_days' => 3,
                'is_active' => true,
                'notes' => 'Government area - special handling required'
            ],
            
            // Shan State
            [
                'township_name' => 'Taunggyi',
                'township_name_mm' => 'တောင်ကြီး',
                'state_region' => 'Shan State',
                'state_region_mm' => 'ရှမ်းပြည်နယ်',
                'delivery_price' => 5000,
                'estimated_days' => 3,
                'is_active' => true,
                'notes' => 'Mountain area - weather dependent'
            ],
            [
                'township_name' => 'Lashio',
                'township_name_mm' => 'လားရှိုး',
                'state_region' => 'Shan State',
                'state_region_mm' => 'ရှမ်းပြည်နယ်',
                'delivery_price' => 6000,
                'estimated_days' => 4,
                'is_active' => true,
                'notes' => 'Border area - additional security checks'
            ],
            
            // Mon State
            [
                'township_name' => 'Mawlamyine',
                'township_name_mm' => 'မော်လမြိုင်',
                'state_region' => 'Mon State',
                'state_region_mm' => 'မွန်ပြည်နယ်',
                'delivery_price' => 4500,
                'estimated_days' => 2,
                'is_active' => true,
                'notes' => 'Coastal area delivery'
            ],
            
            // Kachin State
            [
                'township_name' => 'Myitkyina',
                'township_name_mm' => 'မြစ်ကြီးနား',
                'state_region' => 'Kachin State',
                'state_region_mm' => 'ကချင်ပြည်နယ်',
                'delivery_price' => 7000,
                'estimated_days' => 5,
                'is_active' => true,
                'notes' => 'Remote area - limited transport options'
            ],
            
            // Rakhine State
            [
                'township_name' => 'Sittwe',
                'township_name_mm' => 'စစ်တွေ',
                'state_region' => 'Rakhine State',
                'state_region_mm' => 'ရခိုင်ပြည်နယ်',
                'delivery_price' => 6500,
                'estimated_days' => 4,
                'is_active' => true,
                'notes' => 'Coastal delivery via boat/air transport'
            ],
            
            // Ayeyarwady Region
            [
                'township_name' => 'Pathein',
                'township_name_mm' => 'ပုသိမ်',
                'state_region' => 'Ayeyarwady Region',
                'state_region_mm' => 'ဧရာဝတီတိုင်းဒေသကြီး',
                'delivery_price' => 3500,
                'estimated_days' => 2,
                'is_active' => true,
                'notes' => 'Delta region delivery'
            ],
            
            // Bago Region
            [
                'township_name' => 'Bago',
                'township_name_mm' => 'ပဲခူး',
                'state_region' => 'Bago Region',
                'state_region_mm' => 'ပဲခူးတိုင်းဒေသကြီး',
                'delivery_price' => 3000,
                'estimated_days' => 2,
                'is_active' => true,
                'notes' => null
            ],
            
            // Magway Region
            [
                'township_name' => 'Magway',
                'township_name_mm' => 'မကွေး',
                'state_region' => 'Magway Region',
                'state_region_mm' => 'မကွေးတိုင်းဒေသကြီး',
                'delivery_price' => 4000,
                'estimated_days' => 3,
                'is_active' => true,
                'notes' => 'Dry zone delivery'
            ],
            
            // Sagaing Region
            [
                'township_name' => 'Sagaing',
                'township_name_mm' => 'စစ်ကိုင်း',
                'state_region' => 'Sagaing Region',
                'state_region_mm' => 'စစ်ကိုင်းတိုင်းဒေသကြီး',
                'delivery_price' => 4500,
                'estimated_days' => 3,
                'is_active' => true,
                'notes' => null
            ]
        ];

        foreach ($townships as $township) {
            TownshipDeliveryPrice::create($township);
        }
    }
}
