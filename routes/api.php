<?php

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\CustomerProfileController;
use App\Http\Controllers\Api\DamageReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\KycDocumentController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\SellerOrderController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StockReceiptController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserNotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // --- Auth (public) --------------------------------------------------
    Route::post('/auth/google', [AuthController::class, 'google']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/register-customer', [AuthController::class, 'registerCustomer']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Reference data for the registration screen — no session token yet.
    Route::get('/business-types', [LookupController::class, 'businessTypes']);
    Route::get('/regions', [LookupController::class, 'regions']);

    // --- Public marketplace (no sign-in) --------------------------------
    Route::prefix('market')->group(function () {
        Route::get('/filters', [MarketplaceController::class, 'filters']);
        Route::get('/products', [MarketplaceController::class, 'products']);
        Route::get('/products/{product}', [MarketplaceController::class, 'product']);
        Route::get('/stores', [MarketplaceController::class, 'stores']);
        Route::get('/stores/{store}', [MarketplaceController::class, 'store']);
        Route::get('/ai/config', [AiController::class, 'config']);
        Route::post('/ai/search', [AiController::class, 'search']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Order notifications for any signed-in user (customer or seller staff).
        Route::get('/me/notifications', [UserNotificationController::class, 'index']);
        Route::post('/me/notifications/read', [UserNotificationController::class, 'markAllRead']);

        // --- Marketplace customers --------------------------------------
        Route::middleware('customer')->prefix('customer')->group(function () {
            Route::get('/profile', [CustomerProfileController::class, 'show']);
            Route::patch('/profile', [CustomerProfileController::class, 'update']);

            Route::get('/cart', [CustomerOrderController::class, 'cart']);
            Route::post('/cart/items', [CustomerOrderController::class, 'addToCart']);
            Route::put('/cart/items/{offer}', [CustomerOrderController::class, 'setQuantity']);
            Route::post('/checkout', [CustomerOrderController::class, 'checkout']);
            Route::get('/orders', [CustomerOrderController::class, 'index']);
            Route::get('/orders/{order}', [CustomerOrderController::class, 'show']);
            Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel']);
            Route::post('/ai/order', [AiController::class, 'order']);
        });

        // Everything below requires an active merchant account.
        Route::middleware('api.merchant')->group(function () {

            // --- Merchant / branding ------------------------------------
            Route::get('/merchant', [MerchantController::class, 'show']);
            Route::patch('/merchant', [MerchantController::class, 'update']);
            Route::post('/merchant/logo', [MerchantController::class, 'uploadLogo']);

            // --- Inventory -----------------------------------------------
            Route::get('/inventory', [InventoryController::class, 'index']);
            Route::post('/inventory', [InventoryController::class, 'store']);
            Route::get('/inventory/categories', [InventoryController::class, 'categories']);
            Route::post('/inventory/categories', [InventoryController::class, 'storeCategory']);
            Route::get('/inventory/lookup-barcode/{code}', [InventoryController::class, 'lookupBarcode']);
            Route::post('/inventory/barcode-labels', [InventoryController::class, 'barcodeLabelsPdf']);
            Route::get('/inventory/{item}', [InventoryController::class, 'show']);
            Route::patch('/inventory/{item}', [InventoryController::class, 'update']);
            Route::delete('/inventory/{item}', [InventoryController::class, 'destroy']);
            Route::post('/inventory/{item}/image', [InventoryController::class, 'uploadImage']);
            Route::delete('/inventory/{item}/image', [InventoryController::class, 'removeImage']);
            Route::post('/inventory/{item}/barcode', [InventoryController::class, 'generateBarcode']);
            Route::post('/inventory/{item}/stock-movement', [InventoryController::class, 'stockMovement']);
            Route::patch('/inventory/{item}/marketplace', [InventoryController::class, 'marketplace']);

            // --- Marketplace orders (fulfilment) ------------------------
            Route::get('/orders', [SellerOrderController::class, 'index']);
            Route::get('/orders/{order}', [SellerOrderController::class, 'show']);
            Route::post('/orders/{order}/status', [SellerOrderController::class, 'updateStatus']);

            // --- Shared product catalog ---------------------------------
            Route::get('/catalog/animal-types', [CatalogController::class, 'animalTypes']);
            Route::get('/catalog/categories', [CatalogController::class, 'categories']);
            Route::get('/catalog/products', [CatalogController::class, 'products']);
            Route::get('/catalog/producers', [CatalogController::class, 'producers']);
            Route::post('/catalog/products', [CatalogController::class, 'propose']);
            Route::get('/catalog/proposals', [CatalogController::class, 'proposals']);

            // --- Branches / stores -----------------------------------------
            Route::get('/branches', [BranchController::class, 'index']);
            Route::post('/branches', [BranchController::class, 'store']);
            Route::get('/branches/{branch}', [BranchController::class, 'show']);
            Route::patch('/branches/{branch}', [BranchController::class, 'update']);

            // --- Stock receipts --------------------------------------------
            Route::get('/stock-receipts', [StockReceiptController::class, 'index']);
            Route::post('/stock-receipts', [StockReceiptController::class, 'store']);
            Route::get('/stock-receipts/{receipt}', [StockReceiptController::class, 'show']);
            Route::post('/stock-receipts/{receipt}/items', [StockReceiptController::class, 'addItem']);
            Route::delete('/stock-receipts/{receipt}/items/{item}', [StockReceiptController::class, 'removeItem']);
            Route::post('/stock-receipts/{receipt}/approve', [StockReceiptController::class, 'approve']);
            Route::post('/stock-receipts/{receipt}/reject', [StockReceiptController::class, 'reject']);

            // --- Point of sale -----------------------------------------
            Route::post('/pos/checkout', [PosController::class, 'checkout']);
            Route::get('/payment-methods', [PaymentMethodController::class, 'index']);

            // --- Sales ---------------------------------------------------
            Route::get('/sales', [SalesController::class, 'index']);
            Route::get('/sales/{sale}', [SalesController::class, 'show']);

            // --- Invoices ------------------------------------------------
            Route::get('/invoices', [InvoiceController::class, 'index']);
            Route::post('/invoices', [InvoiceController::class, 'store']);
            Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
            Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
            Route::post('/invoices/{invoice}/items', [InvoiceController::class, 'addItem']);
            Route::delete('/invoices/{invoice}/items/{item}', [InvoiceController::class, 'removeItem']);
            Route::patch('/invoices/{invoice}/discount', [InvoiceController::class, 'setDiscount']);
            Route::post('/invoices/{invoice}/approve', [InvoiceController::class, 'approve']);
            Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
            Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment']);

            // --- Customers -------------------------------------------------
            Route::get('/customers', [CustomerController::class, 'index']);
            Route::post('/customers', [CustomerController::class, 'store']);
            Route::get('/customers/{customer}', [CustomerController::class, 'show']);
            Route::patch('/customers/{customer}', [CustomerController::class, 'update']);

            // --- Suppliers -------------------------------------------------
            Route::get('/suppliers', [SupplierController::class, 'index']);
            Route::post('/suppliers', [SupplierController::class, 'store']);
            Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
            Route::patch('/suppliers/{supplier}', [SupplierController::class, 'update']);

            // --- Expenses & payments -----------------------------------
            Route::get('/expenses', [ExpenseController::class, 'index']);
            Route::post('/expenses', [ExpenseController::class, 'store']);
            Route::get('/payments', [PaymentController::class, 'index']);
            Route::post('/payments', [PaymentController::class, 'store']);

            // --- Dashboard & notifications -------------------------------
            Route::get('/dashboard', [DashboardController::class, 'index']);
            Route::get('/notifications', [NotificationController::class, 'index']);

            // --- Damaged stock reporting -----------------------------------
            Route::get('/damage-reports', [DamageReportController::class, 'index']);
            Route::post('/damage-reports', [DamageReportController::class, 'store']);

            // --- Reports ---------------------------------------------------
            Route::get('/reports/sales/today', [ReportController::class, 'todaySales']);
            Route::get('/reports/product-performance', [ReportController::class, 'productPerformance']);

            // --- KYC & staff -------------------------------------------
            Route::get('/kyc-documents', [KycDocumentController::class, 'index']);
            Route::post('/kyc-documents', [KycDocumentController::class, 'store']);
            Route::get('/staff', [StaffController::class, 'index']);
            Route::post('/staff', [StaffController::class, 'store']);
            Route::patch('/staff/{staff}', [StaffController::class, 'update']);
        });
    });
});
