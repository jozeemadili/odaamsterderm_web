<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Coarse, module-level permissions for v1. Split into finer-grained
     * permissions later if a role needs partial access within a module.
     */
    private const PERMISSIONS = [
        // Internal / admin
        'manage-users',
        'manage-roles',
        'view-merchants',
        'manage-merchants',
        'review-kyc',
        'verify-payments',
        'support-merchants',
        'view-audit-log',
        'manage-lookup-data',
        'manage-catalog',
        'monitor-marketplace',
        'manage-platform-settings',

        // Merchant portal
        'manage-own-business',
        'manage-own-staff',
        'approve-stock-receipts',
        'manage-discount-limits',
    ];

    private const ROLE_PERMISSIONS = [
        'super_admin' => self::PERMISSIONS,
        'risk_compliance_officer' => ['view-merchants', 'review-kyc', 'verify-payments', 'view-audit-log', 'monitor-marketplace'],
        'support_agent' => ['view-merchants', 'support-merchants', 'monitor-marketplace'],
        'finance_ops' => ['view-merchants', 'manage-merchants', 'verify-payments'],

        'merchant_owner' => ['manage-own-business', 'manage-own-staff', 'approve-stock-receipts', 'manage-discount-limits'],
        'merchant_staff' => ['manage-own-business'],

        // Roles a shop owner assigns to their own staff. Base access is the
        // same coarse permission for now — split into finer-grained module
        // permissions once per-role activity scopes are defined. Stock
        // receipt approval and discount-limit configuration are the two
        // exceptions already called out by the business flow.
        'merchant_manager' => ['manage-own-business', 'approve-stock-receipts', 'manage-discount-limits'],
        'merchant_supervisor' => ['manage-own-business', 'approve-stock-receipts'],
        'merchant_sales' => ['manage-own-business'],
        'merchant_accountant' => ['manage-own-business'],

        // Marketplace buyers — access is by account type, no admin permissions.
        'customer' => [],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
                ->syncPermissions($permissions);
        }
    }
}
