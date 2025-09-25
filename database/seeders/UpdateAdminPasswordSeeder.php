<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateAdminPasswordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@signaturestore.com')->first();
        
        if ($adminUser) {
            $adminUser->update([
                'password' => Hash::make('admin123')
            ]);
            
            echo "Admin password updated successfully to 'admin123'\n";
        } else {
            echo "Admin user not found\n";
        }
    }
}
