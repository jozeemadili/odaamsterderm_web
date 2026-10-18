<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\InvoicePublicController;
use App\Http\Controllers\ReceiptController;
use App\Livewire\Admin;
use App\Livewire\Auth\CompleteRegistration;
use App\Livewire\Auth\CustomerRegister;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Market;
use App\Livewire\Portal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// --- Public marketplace -----------------------------------------------------
Route::get('/', Market\Home::class)->name('market.home');
Route::get('/products/{product:slug}', Market\ProductShow::class)->name('market.product');
Route::get('/stores/{store}', Market\StoreShow::class)->name('market.store');
Route::middleware(['auth', 'customer'])->group(function () {
    Route::get('/account', Market\Account::class)->name('market.account');
    Route::get('/cart', Market\Cart::class)->name('market.cart');
    Route::get('/account/orders', Market\Orders::class)->name('market.orders');
    Route::get('/account/orders/{order}', Market\OrderShow::class)->name('market.order');
});

// Branded receipts — signed links customers can open or share without signing in.
Route::get('/receipts/orders/{order}', [ReceiptController::class, 'order'])->name('receipts.order')->middleware('signed');
Route::get('/receipts/sales/{sale}', [ReceiptController::class, 'sale'])->name('receipts.sale')->middleware('signed');

Route::get('/i/{invoice}/pdf', [InvoicePublicController::class, 'download'])
    ->name('invoices.public-pdf')
    ->middleware('signed');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/signup', CustomerRegister::class)->name('customer.register');
    Route::get('/register/complete', CompleteRegistration::class)->name('register.complete');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');

    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('market.home');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'internal'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\Dashboard::class)->name('dashboard');

    Route::get('/merchants', Admin\Merchants\Index::class)->name('merchants.index');
    Route::get('/merchants/create', Admin\Merchants\Create::class)->name('merchants.create');
    Route::get('/merchants/{merchant}', Admin\Merchants\Show::class)->name('merchants.show');

    Route::get('/kyc', Admin\Kyc\Index::class)->name('kyc.index');
    Route::get('/payments', Admin\Payments\Index::class)->name('payments.index');
    Route::get('/users', Admin\Users\Index::class)->name('users.index');
    Route::get('/business-types', Admin\BusinessTypes\Index::class)->name('business-types.index');
    Route::get('/catalog/products', Admin\Products\Index::class)->name('products.index');
    Route::get('/marketplace', Admin\Marketplace\Index::class)->name('marketplace.index');
    Route::get('/marketplace/exceptions', Admin\Marketplace\Exceptions::class)->name('marketplace.exceptions');
    Route::get('/marketplace/orders', Admin\Orders\Index::class)->name('orders.index');
    Route::get('/marketplace/orders/{order}', Admin\Orders\Show::class)->name('orders.show');
    Route::get('/marketplace/stores', Admin\Stores\Index::class)->name('stores.index');
    Route::get('/reports/marketplace', Admin\Reports\Marketplace::class)->name('reports.marketplace');
    Route::get('/catalog/animal-types', Admin\AnimalTypes\Index::class)->name('animal-types.index');
    Route::get('/catalog/categories', Admin\ProductCategories\Index::class)->name('product-categories.index');
    Route::get('/catalog/producers', Admin\Producers\Index::class)->name('producers.index');
    Route::get('/audit-log', Admin\AuditLog\Index::class)->name('audit-log.index');
    Route::get('/settings/ai', Admin\Settings\Ai::class)->name('settings.ai');
});

Route::middleware(['auth', 'merchant'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', Portal\Dashboard::class)->name('dashboard');

    Route::get('/notifications', Portal\Notifications\Index::class)->name('notifications.index');
    Route::get('/reports/product-performance', Portal\Reports\ProductPerformance::class)->name('reports.product-performance');

    Route::get('/pos', Portal\Pos\Index::class)->name('pos.index');
    Route::get('/sales', Portal\Sales\Index::class)->name('sales.index');
    Route::get('/expenses', Portal\Expenses\Index::class)->name('expenses.index');
    Route::get('/inventory', Portal\Inventory\Index::class)->name('inventory.index');
    Route::get('/inventory/barcodes', Portal\Inventory\Barcodes::class)->name('inventory.barcodes');
    Route::get('/inventory/{item}', Portal\Inventory\Show::class)->name('inventory.show');
    Route::get('/marketplace', Portal\Marketplace\Index::class)->name('marketplace.index');
    Route::get('/orders', Portal\Orders\Index::class)->name('orders.index');
    Route::get('/orders/{order}', Portal\Orders\Show::class)->name('orders.show');
    Route::get('/catalog', Portal\Catalog\Index::class)->name('catalog.index');
    Route::get('/stock-receipts', Portal\StockReceipts\Index::class)->name('stock-receipts.index');
    Route::get('/stock-receipts/{receipt}', Portal\StockReceipts\Show::class)->name('stock-receipts.show');
    Route::get('/suppliers', Portal\Suppliers\Index::class)->name('suppliers.index');
    Route::get('/stores', Portal\Stores\Index::class)->name('stores.index');
    Route::get('/stores/{store}', Portal\Stores\Show::class)->name('stores.show');
    Route::get('/customers', Portal\Customers\Index::class)->name('customers.index');
    Route::get('/customers/{customer}', Portal\Customers\Show::class)->name('customers.show');
    Route::get('/payments', Portal\Payments\Index::class)->name('payments.index');
    Route::get('/payment-methods', Portal\PaymentMethods\Index::class)->name('payment-methods.index');
    Route::get('/invoices', Portal\Invoices\Index::class)->name('invoices.index');
    Route::get('/invoices/{invoice}', Portal\Invoices\Show::class)->name('invoices.show');
    Route::get('/kyc', Portal\Kyc\Index::class)->name('kyc.index');
    Route::get('/staff', Portal\Staff\Index::class)->name('staff.index');
    Route::get('/discount-limits', Portal\DiscountLimits\Index::class)->name('discount-limits.index');
    Route::get('/branding', Portal\Branding\Index::class)->name('branding.index');
});
