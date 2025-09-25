<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "Checking admin user...\n";

$adminUser = User::where('email', 'admin@signaturestore.com')->first();

if ($adminUser) {
    echo "Admin user found:\n";
    echo "- ID: " . $adminUser->id . "\n";
    echo "- Name: " . $adminUser->name . "\n";
    echo "- Email: " . $adminUser->email . "\n";
    echo "- Active: " . ($adminUser->is_active ? 'Yes' : 'No') . "\n";
    
    // Update password
    $adminUser->update([
        'password' => Hash::make('admin123')
    ]);
    
    echo "Password updated to 'admin123'\n";
    
    // Test password
    if (Hash::check('admin123', $adminUser->fresh()->password)) {
        echo "✅ Password verification successful!\n";
    } else {
        echo "❌ Password verification failed!\n";
    }
} else {
    echo "❌ Admin user not found!\n";
}
