<?php

namespace App\Console\Commands;

use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Clears all business data so the platform starts fresh, keeping only the
 * platform team and the shared catalog: admin users and roles, animal
 * types, product categories, approved catalog products (with photos),
 * producers, regions, business types and platform settings.
 *
 * With --admin-only the catalog goes too (products, variants, categories,
 * animal types, life stages, producers and every photo), leaving only the
 * admin users, roles, regions, business types and platform settings.
 */
class FreshStart extends Command
{
    protected $signature = 'app:fresh-start {--dry-run : Only show what would be deleted} {--force : Skip the typed confirmation} {--admin-only : Also delete the whole catalog, keep only admin users}';

    protected $description = 'Delete all sellers, stores, stock, sales, orders and customers; keep admins and the catalog';

    /** Every table holding seller, customer or activity data. */
    private const WIPE = [
        'merchants', 'branches', 'branch_user', 'kyc_documents', 'discount_limits',
        'inventory_categories', 'inventory_items', 'stock_movements', 'damage_reports',
        'stock_receipts', 'stock_receipt_items', 'stock_transfers', 'stock_transfer_items',
        'suppliers', 'supplier_transactions', 'customers', 'payment_methods', 'payment_records',
        'sales_records', 'sale_items', 'expense_categories', 'expenses',
        'invoices', 'invoice_items', 'invoice_payments',
        'carts', 'cart_items', 'orders', 'order_items', 'order_events',
        'notifications', 'ai_requests', 'activity_log',
        'sessions', 'password_reset_tokens', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks',
    ];

    /** The shared catalog, wiped only with --admin-only. */
    private const CATALOG = [
        'products', 'product_variants', 'product_attributes', 'product_attribute_values',
        'animal_type_product', 'life_stage_product', 'animal_type_product_category',
        'product_categories', 'animal_types', 'life_stages', 'producers', 'search_keywords',
    ];

    public function handle(): int
    {
        $adminOnly = (bool) $this->option('admin-only');
        $wipe = $adminOnly ? [...self::WIPE, ...self::CATALOG] : self::WIPE;
        $tables = array_values(array_filter($wipe, fn ($t) => Schema::hasTable($t)));
        $users = User::where('user_type', '!=', User::TYPE_INTERNAL);
        $products = Product::where('status', '!=', Product::STATUS_APPROVED);
        $media = $adminOnly ? Media::query() : Media::whereNotIn('model_type', [(new Product)->getMorphClass(), (new Producer)->getMorphClass()]);

        $this->table(['What', 'Rows to delete'], [
            ...array_map(fn ($t) => [$t, DB::table($t)->count()], $tables),
            ['users (sellers, staff, customers)', (clone $users)->count()],
            ...($adminOnly ? [] : [['catalog products not approved', (clone $products)->count()]]),
            ['photos and files'.($adminOnly ? '' : ' (not catalog/producer)'), (clone $media)->count()],
        ]);
        $this->info($adminOnly
            ? 'Kept: '.User::where('user_type', User::TYPE_INTERNAL)->count().' admin user(s), roles, regions, business types and settings.'
            : 'Kept: '.User::where('user_type', User::TYPE_INTERNAL)->count().' admin user(s), '
                .Product::where('status', Product::STATUS_APPROVED)->count().' approved catalog products, '
                .Producer::count().' producers, animal types, categories, regions, business types and settings.');

        if ($this->option('dry-run')) {
            $this->comment('Dry run: nothing was deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && $this->ask('This cannot be undone. Type CLEAR to delete the data above') !== 'CLEAR') {
            $this->warn('Cancelled. Nothing was deleted.');

            return self::FAILURE;
        }

        // Files first, through the media model, so they leave the disk too.
        (clone $media)->each(fn (Media $m) => $m->delete());
        if (! $adminOnly) {
            (clone $products)->get()->each(fn (Product $p) => $p->delete());
            Product::query()->update(['proposed_by_merchant_id' => null]);
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $table) {
                DB::table($table)->truncate();
            }

            $userIds = (clone $users)->pluck('id');
            $userClass = (new User)->getMorphClass();
            foreach ($userIds->chunk(500) as $chunk) {
                DB::table('model_has_roles')->where('model_type', $userClass)->whereIn('model_id', $chunk)->delete();
                DB::table('model_has_permissions')->where('model_type', $userClass)->whereIn('model_id', $chunk)->delete();
                DB::table('personal_access_tokens')->where('tokenable_type', $userClass)->whereIn('tokenable_id', $chunk)->delete();
            }
            (clone $users)->delete();
            User::query()->update(['merchant_id' => null]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        Artisan::call('cache:clear');
        $this->info('Done. The platform is empty and ready for real sellers and customers.');

        return self::SUCCESS;
    }
}
