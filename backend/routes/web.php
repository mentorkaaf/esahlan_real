<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Auth\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminVendorController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminModuleController;
use App\Http\Controllers\Admin\AdminBannerController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminFinanceController;
use App\Http\Controllers\Admin\AdminDeliverymanController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\DispatchController;
use App\Http\Controllers\Admin\AdminModuleDataController;
use App\Http\Controllers\Admin\AdminEFoodController;
use App\Http\Controllers\Admin\AdminWalletController;
use App\Http\Controllers\Admin\AdminLandingController;

// Redirect root to admin
Route::get('/', fn() => view('landing'));

// Admin Auth
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login.post');
    });

    Route::middleware(['auth', 'role:super_admin,admin,operations_manager,finance_manager,marketing_manager,customer_support'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Users
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [AdminUserController::class, 'index'])->name('index');
            Route::get('/{user}', [AdminUserController::class, 'show'])->name('show');
            Route::patch('/{user}/status', [AdminUserController::class, 'updateStatus'])->name('status');
            Route::delete('/{user}', [AdminUserController::class, 'destroy'])->name('destroy');
        });

        // Vendors
        Route::prefix('vendors')->name('vendors.')->group(function () {
            Route::get('/', [AdminVendorController::class, 'index'])->name('index');
            Route::get('/{vendor}', [AdminVendorController::class, 'show'])->name('show');
            Route::post('/{vendor}/approve', [AdminVendorController::class, 'approve'])->name('approve');
            Route::post('/{vendor}/reject', [AdminVendorController::class, 'reject'])->name('reject');
            Route::post('/{vendor}/toggle-featured', [AdminVendorController::class, 'toggleFeatured'])->name('toggle-featured');
            Route::delete('/{vendor}', [AdminVendorController::class, 'destroy'])->name('destroy');
        });

        // Orders
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [AdminOrderController::class, 'index'])->name('index');
            Route::get('/{order}', [AdminOrderController::class, 'show'])->name('show');
            Route::patch('/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('status');
            Route::post('/{order}/assign', [AdminOrderController::class, 'assignDeliveryman'])->name('assign');
            Route::post('/bulk', [AdminOrderController::class, 'bulkAction'])->name('bulk');
        });

        // Deliverymen
        Route::prefix('deliverymen')->name('deliverymen.')->group(function () {
            Route::get('/', [AdminDeliverymanController::class, 'index'])->name('index');
            Route::get('/{deliveryman}', [AdminDeliverymanController::class, 'show'])->name('show');
            Route::post('/{deliveryman}/approve', [AdminDeliverymanController::class, 'approve'])->name('approve');
            Route::post('/{deliveryman}/toggle-block', [AdminDeliverymanController::class, 'toggleBlock'])->name('toggle-block');
        });

        // Modules
        Route::prefix('modules')->name('modules.')->group(function () {
            Route::get('/', [AdminModuleController::class, 'index'])->name('index');
            Route::get('/{module}', [AdminModuleController::class, 'show'])->name('show');
            Route::patch('/{module}', [AdminModuleController::class, 'update'])->name('update');
            Route::post('/{module}/toggle', [AdminModuleController::class, 'toggleStatus'])->name('toggle');
            Route::post('/{module}/districts', [AdminModuleController::class, 'updateDistricts'])->name('districts');
        });

        // Banners
        Route::prefix('banners')->name('banners.')->group(function () {
            Route::get('/', [AdminBannerController::class, 'index'])->name('index');
            Route::post('/', [AdminBannerController::class, 'store'])->name('store');
            Route::delete('/{banner}', [AdminBannerController::class, 'destroy'])->name('destroy');
            Route::post('/{banner}/toggle', [AdminBannerController::class, 'toggleStatus'])->name('toggle');
            Route::post('/reorder', [AdminBannerController::class, 'reorder'])->name('reorder');
        });

        // Finance
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [AdminFinanceController::class, 'index'])->name('index');
            Route::get('/transactions', [AdminFinanceController::class, 'transactions'])->name('transactions');
            Route::get('/commissions', [AdminFinanceController::class, 'commissions'])->name('commissions');
            Route::post('/withdrawals/{withdrawal}/approve', [AdminFinanceController::class, 'approveWithdrawal'])->name('withdrawals.approve');
            Route::post('/withdrawals/{withdrawal}/reject', [AdminFinanceController::class, 'rejectWithdrawal'])->name('withdrawals.reject');
        });

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/sales', [AdminReportController::class, 'salesReport'])->name('sales');
            Route::get('/vendors', [AdminReportController::class, 'vendorReport'])->name('vendors');
            Route::get('/commissions', [AdminReportController::class, 'commissionReport'])->name('commissions');
            Route::get('/sales/export', [AdminReportController::class, 'exportSalesCsv'])->name('sales.export');
        });

        // Notifications
        
        // Community Management
        Route::prefix('community')->name('community.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'index'])->name('index');
            Route::get('/posts', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'posts'])->name('posts');
            Route::delete('/posts/{id}', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'deletePost'])->name('posts.delete');
            Route::get('/reports', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'reports'])->name('reports');
            Route::post('/reports/{id}/action', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'actionReport'])->name('reports.action');
            Route::get('/groups', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'groups'])->name('groups');
            Route::delete('/groups/{id}', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'deleteGroup'])->name('groups.delete');
            Route::get('/users', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'users'])->name('users');
            Route::post('/users/{id}/verify', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'toggleVerify'])->name('users.verify');
        });
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [AdminNotificationController::class, 'index'])->name('index');
            Route::post('/send', [AdminNotificationController::class, 'send'])->name('send');
            Route::delete('/{id}', [AdminNotificationController::class, 'destroy'])->name('destroy');
            Route::delete('/', [AdminNotificationController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::get('/users/search', [AdminNotificationController::class, 'searchUsers'])->name('users.search');
        });

        // Settings
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [AdminSettingController::class, 'index'])->name('index');
            Route::post('/', [AdminSettingController::class, 'update'])->name('update');
            Route::post('/logo', [AdminSettingController::class, 'uploadLogo'])->name('logo');
            Route::post('/test-firebase', [AdminSettingController::class, 'testFirebase'])->name('test-firebase');
        });

        // ─── Module Data Management ───────────────────────────────────
        Route::prefix('module-data')->name('module-data.')->group(function () {
            $ctrl = AdminModuleDataController::class;

            // eLaundry
            Route::get('/laundry',                   [$ctrl, 'laundryIndex'])->name('laundry');
            Route::post('/laundry/items',             [$ctrl, 'laundryStore'])->name('laundry.store');
            Route::patch('/laundry/items/{item}',     [$ctrl, 'laundryUpdate'])->name('laundry.update');
            Route::delete('/laundry/items/{item}',    [$ctrl, 'laundryDestroy'])->name('laundry.destroy');

            // eMoving
            Route::get('/moving',                    [$ctrl, 'movingIndex'])->name('moving');
            Route::post('/moving/pricing',            [$ctrl, 'movingPricingStore'])->name('moving.pricing.store');
            Route::patch('/moving/pricing/{id}',      [$ctrl, 'movingPricingUpdate'])->name('moving.pricing.update');
            Route::delete('/moving/pricing/{pricing}',[$ctrl, 'movingPricingDestroy'])->name('moving.pricing.destroy');
            Route::post('/moving/extras',             [$ctrl, 'movingExtraStore'])->name('moving.extra.store');
            Route::patch('/moving/extras/{extra}',    [$ctrl, 'movingExtraUpdate'])->name('moving.extra.update');
            Route::delete('/moving/extras/{extra}',   [$ctrl, 'movingExtraDestroy'])->name('moving.extra.destroy');
            Route::post('/moving/packages',           [$ctrl, 'movingPackageStore'])->name('moving.package.store');
            Route::patch('/moving/packages/{id}',     [$ctrl, 'movingPackageUpdate'])->name('moving.package.update');
            Route::delete('/moving/packages/{id}',    [$ctrl, 'movingPackageDestroy'])->name('moving.package.destroy');

            // eParcel
            Route::get('/parcel',                    [$ctrl, 'parcelIndex'])->name('parcel');
            Route::post('/parcel/types',              [$ctrl, 'parcelTypeStore'])->name('parcel.type.store');
            Route::patch('/parcel/types/{type}',      [$ctrl, 'parcelTypeUpdate'])->name('parcel.type.update');
            Route::delete('/parcel/types/{type}',     [$ctrl, 'parcelTypeDestroy'])->name('parcel.type.destroy');
            Route::post('/parcel/zones',              [$ctrl, 'parcelZoneStore'])->name('parcel.zone.store');
            Route::post('/parcel/zones/bulk',         [$ctrl, 'parcelZoneBulk'])->name('parcel.zone.bulk');
            Route::delete('/parcel/zones/{zone}',     [$ctrl, 'parcelZoneDestroy'])->name('parcel.zone.destroy');

            // eData
            Route::get('/data',                      [$ctrl, 'dataIndex'])->name('data');
            Route::post('/data/providers',            [$ctrl, 'dataProviderStore'])->name('data.provider.store');
            Route::match(['PUT','PATCH'],'/data/providers/{provider}',[$ctrl, 'dataProviderUpdate'])->name('data.provider.update');
            Route::delete('/data/providers/{provider}',[$ctrl, 'dataProviderDestroy'])->name('data.provider.destroy');
            Route::post('/data/packages',             [$ctrl, 'dataPackageStore'])->name('data.package.store');
            Route::match(['PUT','PATCH'],'/data/packages/{package}',  [$ctrl, 'dataPackageUpdate'])->name('data.package.update');
            Route::delete('/data/packages/{package}', [$ctrl, 'dataPackageDestroy'])->name('data.package.destroy');
            Route::post('/data/bundles',              [$ctrl, 'dataBundleStore'])->name('data.bundle.store');
            Route::match(['PUT','PATCH'],'/data/bundles/{id}',        [$ctrl, 'dataBundleUpdate'])->name('data.bundle.update');
            Route::delete('/data/bundles/{id}',       [$ctrl, 'dataBundleDestroy'])->name('data.bundle.destroy');

            // eExchange
            Route::get('/exchange',                  [$ctrl, 'exchangeIndex'])->name('exchange');
            Route::post('/exchange/rates',            [$ctrl, 'exchangeStore'])->name('exchange.store');
            Route::delete('/exchange/rates/{rate}',   [$ctrl, 'exchangeDestroy'])->name('exchange.destroy');

            // eHealth
            Route::get('/health',                    [$ctrl, 'healthIndex'])->name('health');
            Route::post('/health/doctors',            [$ctrl, 'doctorStore'])->name('health.doctor.store');
            Route::patch('/health/doctors/{id}',      [$ctrl, 'doctorUpdate'])->name('health.doctor.update');
            Route::delete('/health/doctors/{id}',     [$ctrl, 'doctorDestroy'])->name('health.doctor.destroy');

            // eRent
            Route::get('/rent',                      [$ctrl, 'rentIndex'])->name('rent');
            Route::post('/rent/properties',           [$ctrl, 'propertyStore'])->name('rent.property.store');
            Route::patch('/rent/properties/{id}',     [$ctrl, 'propertyUpdate'])->name('rent.property.update');
            Route::delete('/rent/properties/{id}',    [$ctrl, 'propertyDestroy'])->name('rent.property.destroy');
            Route::post('/rent/properties/{id}/mark-available', [$ctrl, 'propertyMarkAvailable'])->name('rent.property.mark_available');
            Route::patch('/rent/bookings/{id}',           [$ctrl, 'bookingUpdate'])->name('rent.booking.update');
            Route::post('/rent/bookings/{id}/approve-refund', [$ctrl, 'bookingApproveRefund'])->name('rent.booking.approve_refund');
            Route::post('/rent/bookings/{id}/deny-refund',    [$ctrl, 'bookingDenyRefund'])->name('rent.booking.deny_refund');
            Route::post('/rent/districts',          [$ctrl, 'districtStore'])->name('rent.district.store');
            Route::patch('/rent/districts/{id}',    [$ctrl, 'districtUpdate'])->name('rent.district.update');
            Route::delete('/rent/districts/{id}',   [$ctrl, 'districtDestroy'])->name('rent.district.destroy');

            // eFood — full management
            Route::prefix('efood')->name('efood.')->group(function () {
                $ef = AdminEFoodController::class;
                Route::get('/',                             [$ef, 'index'])->name('index');
                // Restaurants
                Route::post('/restaurants',                [$ef, 'restaurantStore'])->name('restaurant.store');
                Route::patch('/restaurants/{id}',          [$ef, 'restaurantUpdate'])->name('restaurant.update');
                Route::delete('/restaurants/{id}',         [$ef, 'restaurantDestroy'])->name('restaurant.destroy');
                Route::post('/restaurants/{id}/toggle',    [$ef, 'restaurantToggle'])->name('restaurant.toggle');
                // Categories
                Route::post('/categories',                 [$ef, 'categoryStore'])->name('category.store');
                Route::patch('/categories/{id}',           [$ef, 'categoryUpdate'])->name('category.update');
                Route::delete('/categories/{id}',          [$ef, 'categoryDestroy'])->name('category.destroy');
                // Food Items
                Route::post('/items',                      [$ef, 'itemStore'])->name('item.store');
                Route::patch('/items/{id}',                [$ef, 'itemUpdate'])->name('item.update');
                Route::delete('/items/{id}',               [$ef, 'itemDestroy'])->name('item.destroy');
                // Addons
                Route::post('/addons',                     [$ef, 'addonStore'])->name('addon.store');
                Route::patch('/addons/{id}',               [$ef, 'addonUpdate'])->name('addon.update');
                Route::delete('/addons/{id}',              [$ef, 'addonDestroy'])->name('addon.destroy');
                // Banners
                Route::post('/banners',                    [$ef, 'bannerStore'])->name('banner.store');
                Route::patch('/banners/{id}',              [$ef, 'bannerUpdate'])->name('banner.update');
                Route::delete('/banners/{id}',             [$ef, 'bannerDestroy'])->name('banner.destroy');
                // Coupons
                Route::post('/coupons',                    [$ef, 'couponStore'])->name('coupon.store');
                Route::patch('/coupons/{id}',              [$ef, 'couponUpdate'])->name('coupon.update');
                Route::delete('/coupons/{id}',             [$ef, 'couponDestroy'])->name('coupon.destroy');
                // Discount Campaigns
                Route::post('/campaigns',                  [$ef, 'campaignStore'])->name('campaign.store');
                Route::patch('/campaigns/{id}',            [$ef, 'campaignUpdate'])->name('campaign.update');
                Route::delete('/campaigns/{id}',           [$ef, 'campaignDestroy'])->name('campaign.destroy');
                // Image Upload (returns JSON {url:...})
                Route::post('/upload-image',               [$ef, 'uploadImage'])->name('upload.image');
                // Orders
                Route::patch('/orders/{id}/status',        [$ef, 'orderUpdateStatus'])->name('order.status');
            });

            // eShop / eWholesale / eGrocery
            Route::get('/shop',                      [$ctrl, 'shopIndex'])->name('shop');
            Route::get('/wholesale',                 [$ctrl, 'wholesaleIndex'])->name('wholesale');
            Route::get('/grocery',                   [$ctrl, 'groceryIndex'])->name('grocery');
            Route::post('/products',                  [$ctrl, 'productStore'])->name('product.store');
            Route::patch('/products/{product}',       [$ctrl, 'productUpdate'])->name('product.update');
            Route::delete('/products/{product}',      [$ctrl, 'productDestroy'])->name('product.destroy');

            // eTicket
            Route::get('/ticket',                    [$ctrl, 'ticketIndex'])->name('ticket');
            Route::post('/ticket/airlines',           [$ctrl, 'airlineStore'])->name('ticket.airline.store');
            Route::patch('/ticket/airlines/{id}',     [$ctrl, 'airlineUpdate'])->name('ticket.airline.update');
            Route::delete('/ticket/airlines/{id}',    [$ctrl, 'airlineDestroy'])->name('ticket.airline.destroy');
            Route::post('/ticket/routes',             [$ctrl, 'routeStore'])->name('ticket.route.store');
            Route::patch('/ticket/routes/{id}',       [$ctrl, 'routeUpdate'])->name('ticket.route.update');
            Route::delete('/ticket/routes/{id}',      [$ctrl, 'routeDestroy'])->name('ticket.route.destroy');
            Route::post('/ticket/flights',            [$ctrl, 'flightStore'])->name('ticket.flight.store');
            Route::patch('/ticket/flights/{id}',      [$ctrl, 'flightUpdate'])->name('ticket.flight.update');
            Route::delete('/ticket/flights/{id}',     [$ctrl, 'flightDestroy'])->name('ticket.flight.destroy');
        });

        // ── eShop Full Management ─────────────────────────────────────────
        Route::prefix('eshop')->name('eshop.')->group(function () {
            $es = \App\Http\Controllers\Admin\AdminEShopController::class;
            Route::get('/', [$es, 'index'])->name('index');
            // Categories
            Route::post('/categories',           [$es, 'categoryStore'])->name('category.store');
            Route::patch('/categories/{id}',     [$es, 'categoryUpdate'])->name('category.update');
            Route::delete('/categories/{id}',    [$es, 'categoryDestroy'])->name('category.destroy');
            // Products
            Route::post('/products',             [$es, 'productStore'])->name('product.store');
            Route::patch('/products/{id}',       [$es, 'productUpdate'])->name('product.update');
            Route::delete('/products/{id}',      [$es, 'productDestroy'])->name('product.destroy');
            Route::post('/products/{id}/toggle',    [$es, 'productToggle'])->name('product.toggle');
            Route::post('/products/{id}/feature',   [$es, 'productFeatureToggle'])->name('product.feature');
            Route::post('/products/{id}/duplicate', [$es, 'productDuplicate'])->name('product.duplicate');
            Route::get('/product-images/{id}/delete', [$es, 'productImageDelete'])->name('product-image.delete');
            // Units
            Route::post('/units',                [$es, 'unitStore'])->name('unit.store');
            Route::patch('/units/{id}',          [$es, 'unitUpdate'])->name('unit.update');
            Route::delete('/units/{id}',         [$es, 'unitDestroy'])->name('unit.destroy');
            // Attributes
            Route::post('/attributes',                [$es, 'attributeStore'])->name('attribute.store');
            Route::patch('/attributes/{id}',          [$es, 'attributeUpdate'])->name('attribute.update');
            Route::delete('/attributes/{id}',         [$es, 'attributeDestroy'])->name('attribute.destroy');
            Route::post('/attributes/{id}/values',    [$es, 'attributeValueStore'])->name('attribute.value.store');
            Route::delete('/attributes/values/{id}',  [$es, 'attributeValueDestroy'])->name('attribute.value.destroy');
            // Flash Deals
            Route::post('/flash-deals',               [$es, 'flashDealStore'])->name('flash-deal.store');
            Route::patch('/flash-deals/{id}',         [$es, 'flashDealUpdate'])->name('flash-deal.update');
            Route::delete('/flash-deals/{id}',        [$es, 'flashDealDestroy'])->name('flash-deal.destroy');
            Route::post('/flash-deals/{id}/toggle',   [$es, 'flashDealToggle'])->name('flash-deal.toggle');
            Route::post('/flash-deals/{id}/products', [$es, 'flashDealProductAdd'])->name('flash-deal.product.add');
            Route::delete('/flash-deals/{dealId}/products/{productId}', [$es, 'flashDealProductRemove'])->name('flash-deal.product.remove');
            // Deals of Day
            Route::post('/deals-of-day',          [$es, 'dealOfDayStore'])->name('deal-of-day.store');
            Route::delete('/deals-of-day/{id}',   [$es, 'dealOfDayDestroy'])->name('deal-of-day.destroy');
            // Coupons
            Route::post('/coupons',               [$es, 'couponStore'])->name('coupon.store');
            Route::patch('/coupons/{id}',         [$es, 'couponUpdate'])->name('coupon.update');
            Route::delete('/coupons/{id}',        [$es, 'couponDestroy'])->name('coupon.destroy');
            Route::post('/coupons/{id}/toggle',   [$es, 'couponToggle'])->name('coupon.toggle');
            // Campaigns
            Route::post('/campaigns',             [$es, 'campaignStore'])->name('campaign.store');
            Route::patch('/campaigns/{id}',       [$es, 'campaignUpdate'])->name('campaign.update');
            Route::delete('/campaigns/{id}',      [$es, 'campaignDestroy'])->name('campaign.destroy');
            Route::post('/campaigns/{id}/toggle', [$es, 'campaignToggle'])->name('campaign.toggle');
            Route::post('/campaigns/{id}/products', [$es, 'campaignProductAdd'])->name('campaign.product.add');
            Route::delete('/campaigns/{campaignId}/products/{productId}', [$es, 'campaignProductRemove'])->name('campaign.product.remove');
            // Orders
            Route::patch('/orders/{id}/status',   [$es, 'orderUpdateStatus'])->name('order.status');
            // Image Upload
            Route::post('/upload-image',          [$es, 'uploadImage'])->name('upload.image');
        });

        // Landing Page Management
        Route::get('/landing', [AdminLandingController::class, 'index'])->name('landing.index');
        Route::put('/landing', [AdminLandingController::class, 'update'])->name('landing.update');

        // Wallet Management & Payment Settings
        Route::prefix('wallet')->name('wallet.')->group(function () {
            $wc = AdminWalletController::class;
            Route::get('/',                      [$wc, 'index'])->name('index');
            Route::get('/transactions',          [$wc, 'transactions'])->name('transactions');
            Route::post('/credit',               [$wc, 'creditUser'])->name('credit');
            Route::get('/withdrawals',           [$wc, 'withdrawals'])->name('withdrawals');
            Route::post('/withdrawals/{id}/approve', [$wc, 'approveWithdrawal'])->name('withdrawal.approve');
            Route::post('/withdrawals/{id}/reject',  [$wc, 'rejectWithdrawal'])->name('withdrawal.reject');
            Route::get('/settings',              [$wc, 'settings'])->name('settings');
            Route::post('/settings',             [$wc, 'saveSettings'])->name('settings.save');
        });

        // Dispatch Center
        Route::get('/dispatch', [DispatchController::class, 'index'])->name('dispatch');
        Route::get('/dispatch/orders', [DispatchController::class, 'activeOrders'])->name('dispatch.orders');
        Route::post('/dispatch/assign', [DispatchController::class, 'manualAssign'])->name('dispatch.assign');
        Route::get('/dispatch/deliverymen/available', [DispatchController::class, 'availableDeliverymen'])->name('dispatch.deliverymen');
    });
});
