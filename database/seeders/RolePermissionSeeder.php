<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::beginTransaction();
            
            // Create Permissions
        $permissions = [
            // Product Management
            ['name' => 'products.view', 'display_name' => 'View Products', 'description' => 'View product listings', 'category' => 'products'],
            ['name' => 'products.create', 'display_name' => 'Create Products', 'description' => 'Create new products', 'category' => 'products'],
            ['name' => 'products.edit', 'display_name' => 'Edit Products', 'description' => 'Edit existing products', 'category' => 'products'],
            ['name' => 'products.delete', 'display_name' => 'Delete Products', 'description' => 'Delete products', 'category' => 'products'],
            
            // Order Management
            ['name' => 'orders.view', 'display_name' => 'View Orders', 'description' => 'View order listings', 'category' => 'orders'],
            ['name' => 'orders.edit', 'display_name' => 'Edit Orders', 'description' => 'Edit order status and details', 'category' => 'orders'],
            ['name' => 'orders.delete', 'display_name' => 'Delete Orders', 'description' => 'Delete orders', 'category' => 'orders'],
            
            // Payment Management
            ['name' => 'payments.view', 'display_name' => 'View Payments', 'description' => 'View payment details', 'category' => 'payments'],
            ['name' => 'payments.approve', 'display_name' => 'Approve Payments', 'description' => 'Approve manual payments', 'category' => 'payments'],
            ['name' => 'payments.reject', 'display_name' => 'Reject Payments', 'description' => 'Reject manual payments', 'category' => 'payments'],
            
            // User Management
            ['name' => 'users.view', 'display_name' => 'View Users', 'description' => 'View user listings', 'category' => 'users'],
            ['name' => 'users.edit', 'display_name' => 'Edit Users', 'description' => 'Edit user details', 'category' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'description' => 'Delete users', 'category' => 'users'],
            
            // Content Management
            ['name' => 'content.view', 'display_name' => 'View Content', 'description' => 'View content pages', 'category' => 'content'],
            ['name' => 'content.edit', 'display_name' => 'Edit Content', 'description' => 'Edit content pages', 'category' => 'content'],
            
            // Dashboard
            ['name' => 'dashboard.view', 'display_name' => 'View Dashboard', 'description' => 'Access admin dashboard', 'category' => 'dashboard'],
            
            // Customer Permissions
            ['name' => 'shop.browse', 'display_name' => 'Browse Shop', 'description' => 'Browse products and shop', 'category' => 'customer'],
            ['name' => 'orders.own', 'display_name' => 'Manage Own Orders', 'description' => 'View and manage own orders', 'category' => 'customer'],
            ['name' => 'profile.edit', 'display_name' => 'Edit Profile', 'description' => 'Edit own profile', 'category' => 'customer'],
            ['name' => 'wishlist.manage', 'display_name' => 'Manage Wishlist', 'description' => 'Add/remove items from wishlist', 'category' => 'customer'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        // Create Roles
        $customerRole = Role::firstOrCreate(
            ['name' => 'customer'],
            [
                'display_name' => 'Customer',
                'description' => 'Regular customer with shopping privileges',
                'is_admin' => false
            ]
        );

        $productManagerRole = Role::firstOrCreate(
            ['name' => 'product_manager'],
            [
                'display_name' => 'Product Manager',
                'description' => 'Manages products and inventory',
                'is_admin' => true
            ]
        );

        $orderManagerRole = Role::firstOrCreate(
            ['name' => 'order_manager'],
            [
                'display_name' => 'Order Manager',
                'description' => 'Manages orders and payments',
                'is_admin' => true
            ]
        );

        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin'],
            [
                'display_name' => 'Super Administrator',
                'description' => 'Full system access',
                'is_admin' => true
            ]
        );

        // Assign permissions to roles
        
        // Customer permissions
        $customerPermissions = Permission::whereIn('name', [
            'shop.browse',
            'orders.own',
            'profile.edit',
            'wishlist.manage'
        ])->get();
        $customerRole->permissions()->sync($customerPermissions->pluck('id'));

        // Product Manager permissions
        $productManagerPermissions = Permission::whereIn('category', [
            'products',
            'dashboard'
        ])->get();
        $productManagerRole->permissions()->sync($productManagerPermissions->pluck('id'));

        // Order Manager permissions
        $orderManagerPermissions = Permission::whereIn('category', [
            'orders',
            'payments',
            'users',
            'dashboard'
        ])->get();
        $orderManagerRole->permissions()->sync($orderManagerPermissions->pluck('id'));

        // Super Admin gets all permissions
        $allPermissions = Permission::all();
        $superAdminRole->permissions()->sync($allPermissions->pluck('id'));

        // Create default admin user
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@signaturestore.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_active' => true
            ]
        );
        
        $adminUser->roles()->sync([$superAdminRole->id]);

        // Create sample customer
        $customerUser = User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'John Customer',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_active' => true
            ]
        );
        
        $customerUser->roles()->sync([$customerRole->id]);
        
        DB::commit();
        Log::info('RolePermissionSeeder completed successfully');
        
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RolePermissionSeeder failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
