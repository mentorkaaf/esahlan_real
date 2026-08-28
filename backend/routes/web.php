<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Vendor\VendorAuthController;
use App\Http\Controllers\Vendor\VendorRegisterController;
use App\Http\Controllers\Vendor\VendorDashboardWebController;
use App\Http\Controllers\Vendor\VendorOrderWebController;
use App\Http\Controllers\Vendor\VendorProductWebController;
use App\Http\Controllers\Vendor\VendorStoreWebController;
use App\Http\Controllers\Vendor\VendorWalletWebController;
use App\Http\Controllers\Vendor\VendorCategoryController;
use App\Http\Controllers\Vendor\VendorAddonController;
use App\Http\Controllers\Admin\Auth\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminVendorController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminModuleController;
use App\Http\Controllers\Admin\AdminMobilePayController;
use App\Http\Controllers\Admin\AdminPaymentSettingsController;
use App\Http\Controllers\Admin\AdminAffiliateController;
use App\Http\Controllers\Admin\AdminRewardController;
use App\Http\Controllers\Admin\AdminBannerController;
use App\Http\Controllers\Admin\AdminAdController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminFinanceController;
use App\Http\Controllers\Admin\AdminDeliverymanController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\DispatchController;
use App\Http\Controllers\Admin\SosController;
use App\Http\Controllers\Admin\DriverPerformanceController;
use App\Http\Controllers\Admin\AdminModuleDataController;
use App\Http\Controllers\Admin\AdminEFoodController;
use App\Http\Controllers\Admin\AdminWalletController;
use App\Http\Controllers\Admin\AdminLandingController;
use App\Http\Controllers\Admin\AdminExchangeController;
use App\Http\Controllers\Admin\AdminCryptoController;
use App\Http\Controllers\Admin\AdminELearningController;
use App\Http\Controllers\Employee\EmployeeAuthController;
use App\Http\Controllers\Employee\EmployeeController;
use App\Http\Controllers\Admin\AdminEmailTemplateController;

// ─── Vendor Panel ────────────────────────────────────────────────────────────
Route::prefix('vendor')->name('vendor.')->group(function () {
    // Guest-only routes
    Route::middleware('guest')->group(function () {
        Route::get('/login', [VendorAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [VendorAuthController::class, 'login'])->name('login.post');
        Route::get('/register', [VendorRegisterController::class, 'showRegister'])->name('register');
        Route::post('/register', [VendorRegisterController::class, 'register'])->name('register.post');
    });

    // Authenticated vendor routes
    Route::middleware('vendor')->group(function () {
        Route::post('/logout', [VendorAuthController::class, 'logout'])->name('logout');

        // Dashboard
        Route::get('/', [VendorDashboardWebController::class, 'index'])->name('dashboard');
        Route::post('/toggle-store', [VendorDashboardWebController::class, 'toggleStore'])->name('toggle-store');

        // Orders
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [VendorOrderWebController::class, 'index'])->name('index');
            Route::get('/{order}', [VendorOrderWebController::class, 'show'])->name('show');
            Route::post('/{order}/accept', [VendorOrderWebController::class, 'accept'])->name('accept');
            Route::post('/{order}/reject', [VendorOrderWebController::class, 'reject'])->name('reject');
            Route::post('/{order}/ready', [VendorOrderWebController::class, 'markReady'])->name('ready');
        });

        // Earnings
        Route::get('/earnings', [VendorDashboardWebController::class, 'earnings'])->name('earnings');

        // ── eShop Vendor Panel ───────────────────────────────────────────
        Route::prefix('eshop')->name('eshop.')->group(function () {
            $es = \App\Http\Controllers\Vendor\VendorEShopController::class;
            Route::get('/dashboard',            [$es, 'dashboard'])->name('dashboard');
            Route::get('/products',             [$es, 'products'])->name('products');
            Route::post('/products',            [$es, 'productStore'])->name('products.store');
            Route::patch('/products/{id}',      [$es, 'productUpdate'])->name('products.update');
            Route::delete('/products/{id}',     [$es, 'productDelete'])->name('products.delete');
            Route::post('/products/{id}/toggle',[$es, 'productToggle'])->name('products.toggle');
            Route::get('/orders',               [$es, 'orders'])->name('orders');
            Route::post('/orders/{id}/status', [$es, 'updateOrderStatus'])->name('orders.status');
            Route::get('/store',                [$es, 'store'])->name('store');
            Route::post('/store',               [$es, 'storeUpdate'])->name('store.update');
            Route::get('/earnings',             [$es, 'earnings'])->name('earnings');
            Route::post('/withdraw',            [$es, 'requestWithdrawal'])->name('withdraw');
        });

        // eWholesale Supplier Portal
        Route::prefix('wholesale')->name('wholesale.')->group(function () {
            $ws = \App\Http\Controllers\Vendor\VendorWholesaleController::class;
            Route::get('/dashboard',            [$ws, 'dashboard'])->name('dashboard');
            Route::get('/products',             [$ws, 'products'])->name('products');
            Route::post('/products/{id}/toggle',[$ws, 'productToggle'])->name('products.toggle');
            Route::get('/orders',               [$ws, 'orders'])->name('orders');
            Route::get('/orders/{id}',          [$ws, 'orderShow'])->name('orders.show');
            Route::post('/orders/{id}/status',  [$ws, 'orderStatus'])->name('orders.status');
            Route::get('/rfqs',                 [$ws, 'rfqs'])->name('rfqs');
            Route::get('/store',                [$ws, 'store'])->name('store');
            Route::post('/store',               [$ws, 'storeUpdate'])->name('store.update');
        });

        // Branch Switcher
        Route::post('/switch-branch', function (\Illuminate\Http\Request $request) {
            $vendorId = $request->input('vendor_id');
            $owns = \App\Models\Vendor::where('id', $vendorId)->where('user_id', auth()->id())->exists();
            if ($owns) session(['active_vendor_id' => (int) $vendorId]);
            return back();
        })->name('switch-branch');

        // Menu Categories
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [VendorCategoryController::class, 'index'])->name('index');
            Route::post('/', [VendorCategoryController::class, 'store'])->name('store');
            Route::match(['PATCH','POST'], '/{category}', [VendorCategoryController::class, 'update'])->name('update');
            Route::delete('/{category}', [VendorCategoryController::class, 'destroy'])->name('destroy');
        });

        // Addons
        Route::prefix('addons')->name('addons.')->group(function () {
            Route::get('/', [VendorAddonController::class, 'index'])->name('index');
            Route::post('/', [VendorAddonController::class, 'store'])->name('store');
            Route::match(['PATCH','POST'], '/{addon}', [VendorAddonController::class, 'update'])->name('update');
            Route::delete('/{addon}', [VendorAddonController::class, 'destroy'])->name('destroy');
        });

        // Products
        Route::prefix('products')->name('products.')->group(function () {
            Route::get('/', [VendorProductWebController::class, 'index'])->name('index');
            Route::get('/create', [VendorProductWebController::class, 'create'])->name('create');
            Route::post('/', [VendorProductWebController::class, 'store'])->name('store');
            Route::get('/{product}/edit', [VendorProductWebController::class, 'edit'])->name('edit');
            Route::patch('/{product}', [VendorProductWebController::class, 'update'])->name('update');
            Route::delete('/{product}', [VendorProductWebController::class, 'destroy'])->name('destroy');
            Route::post('/{product}/toggle', [VendorProductWebController::class, 'toggle'])->name('toggle');
        });

        // Store Profile
        Route::prefix('store')->name('store.')->group(function () {
            Route::get('/', [VendorStoreWebController::class, 'index'])->name('index');
            Route::post('/', [VendorStoreWebController::class, 'update'])->name('update');
            Route::post('/schedule', [VendorStoreWebController::class, 'updateSchedule'])->name('schedule');
            Route::post('/toggle-open', [VendorStoreWebController::class, 'toggleOpen'])->name('toggle-open');
        });

        // Wallet
        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::get('/', [VendorWalletWebController::class, 'index'])->name('index');
            Route::post('/withdraw', [VendorWalletWebController::class, 'requestWithdrawal'])->name('withdraw');
        });
    });
});

// Public landing page
// ── Smart App Download Link — esahlan.com/download ─────────────────────────
// NOTE: /app/ is reserved by Nginx for Reverb WebSocket — use /download instead
Route::get('/download', function (\Illuminate\Http\Request $request) {
    // Detect device from User-Agent
    $ua = $request->userAgent() ?? '';
    if (preg_match('/android/i', $ua)) {
        $device = 'android';
    } elseif (preg_match('/iPad|iPhone|iPod/i', $ua)) {
        $device = 'ios';
    } else {
        $device = 'desktop';
    }

    // Detect browser
    $browser = 'Unknown';
    if (str_contains($ua, 'Chrome'))       $browser = 'Chrome';
    elseif (str_contains($ua, 'Firefox'))  $browser = 'Firefox';
    elseif (str_contains($ua, 'Safari'))   $browser = 'Safari';
    elseif (str_contains($ua, 'Edge'))     $browser = 'Edge';
    elseif (str_contains($ua, 'Opera'))    $browser = 'Opera';

    // Detect OS
    $os = 'Unknown';
    if (preg_match('/android/i', $ua))            $os = 'Android';
    elseif (preg_match('/iPad|iPhone|iPod/i', $ua)) $os = 'iOS';
    elseif (str_contains($ua, 'Windows'))          $os = 'Windows';
    elseif (str_contains($ua, 'Mac'))              $os = 'macOS';
    elseif (str_contains($ua, 'Linux'))            $os = 'Linux';

    // Quick country from Cloudflare header (if using CF) or skip
    $countryCode = $request->header('CF-IPCountry') ?: null;
    $countryMap  = [
        'SO'=>'Somalia','KE'=>'Kenya','ET'=>'Ethiopia','DJ'=>'Djibouti',
        'US'=>'United States','GB'=>'United Kingdom','AE'=>'UAE','SA'=>'Saudi Arabia',
        'CA'=>'Canada','AU'=>'Australia','DE'=>'Germany','FR'=>'France',
        'NL'=>'Netherlands','SE'=>'Sweden','NO'=>'Norway','FI'=>'Finland',
    ];
    $countryName = $countryCode ? ($countryMap[$countryCode] ?? $countryCode) : null;

    // Log the click (fire-and-forget, don't block the page)
    try {
        \Illuminate\Support\Facades\DB::table('app_link_clicks')->insert([
            'device_type'  => $device,
            'ip'           => $request->ip(),
            'country_code' => $countryCode,
            'country_name' => $countryName,
            'browser'      => $browser,
            'os'           => $os,
            'referer'      => substr($request->header('referer') ?? '', 0, 500),
            'created_at'   => now(),
        ]);
    } catch (\Throwable $e) { /* never block the page */ }

    // Dynamic store URLs from admin settings
    $iosUrl     = \App\Models\Global\GlobalSetting::get('app_link_ios_url',     'https://apps.apple.com/app/id000000000');
    $androidUrl = \App\Models\Global\GlobalSetting::get('app_link_android_url', 'https://play.google.com/store/apps/details?id=com.esahlan.app');

    return view('app-redirect', compact('iosUrl', 'androidUrl'));
})->name('app.download');

Route::get('/', function () {
    if (auth()->check()) {
        $role = auth()->user()->role?->slug ?? '';
        if (in_array($role, ['vendor_owner', 'vendor_employee'])) {
            return redirect()->route('vendor.dashboard');
        }
        if ($role === 'admin' || str_starts_with($role, 'admin')) {
            return redirect()->route('admin.dashboard');
        }
        if ($role === 'employee') {
            return redirect()->route('employee.dashboard');
        }
    }
    return view('landing');
});

// Admin root redirect
Route::get('/admin', fn() => redirect('/admin/dashboard'));

// ─── Employee Self-Service Portal ─────────────────────────────────────────────
// Guard: 'employee' — authenticates against hr_employees table (NOT users/hr_staff)
Route::prefix('employee')->name('employee.')->group(function () {
    // Public: login
    Route::get('/login',  [\App\Http\Controllers\Employee\EmployeeAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Employee\EmployeeAuthController::class, 'login'])->name('login.post');

    // Protected: requires employee guard session
    Route::middleware(['auth.employee'])->group(function () {
        Route::get('/',                    [\App\Http\Controllers\Employee\EmployeeController::class, 'dashboard'])->name('dashboard');
        Route::post('/logout',             [\App\Http\Controllers\Employee\EmployeeAuthController::class, 'logout'])->name('logout');

        // Workspace
        Route::get('/workspace/{slug}',               [\App\Http\Controllers\Employee\EmployeeController::class, 'workspace'])->name('workspace');
        Route::post('/workspace/switch',              [\App\Http\Controllers\Employee\EmployeeController::class, 'switchWorkspace'])->name('workspace.switch');
        Route::post('/workspace/{slug}/status/{id}',  [\App\Http\Controllers\Employee\EmployeeController::class, 'updateStatus'])->name('workspace.status');

        // Performance
        Route::get('/performance',         [\App\Http\Controllers\Employee\EmployeeController::class, 'performance'])->name('performance');

        // Attendance
        Route::get('/attendance',          [\App\Http\Controllers\Employee\EmployeeController::class, 'attendance'])->name('attendance');

        // Leaves
        Route::get('/leaves',              [\App\Http\Controllers\Employee\EmployeeController::class, 'leaves'])->name('leaves');
        Route::get('/leaves/create',       [\App\Http\Controllers\Employee\EmployeeController::class, 'leaveCreate'])->name('leaves.create');
        Route::post('/leaves',             [\App\Http\Controllers\Employee\EmployeeController::class, 'leaveStore'])->name('leaves.store');

        // Notifications (JSON API)
        Route::get('/notifications/count', [\App\Http\Controllers\Employee\EmployeeNotificationController::class, 'unreadCount'])->name('notifications.count');
        Route::get('/notifications',       [\App\Http\Controllers\Employee\EmployeeNotificationController::class, 'index'])->name('notifications');
        Route::post('/notifications/read-all', [\App\Http\Controllers\Employee\EmployeeNotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Employee\EmployeeNotificationController::class, 'markRead'])->name('notifications.read');

        // Custom Reverb broadcasting auth for the employee guard
        Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
            $employee = \Illuminate\Support\Facades\Auth::guard('employee')->user();
            if (! $employee) abort(403);

            $channel  = $request->input('channel_name', '');
            $socketId = $request->input('socket_id', '');

            // Only allow subscription to own channel
            if ($channel !== "private-employee.{$employee->id}") abort(403);

            $appKey    = config('broadcasting.connections.reverb.key');
            $appSecret = config('broadcasting.connections.reverb.secret');
            $signature = hash_hmac('sha256', "{$socketId}:{$channel}", $appSecret);

            return response()->json(['auth' => "{$appKey}:{$signature}"]);
        })->name('employee.broadcasting.auth');

        // Payslips
        Route::get('/payslips',            [\App\Http\Controllers\Employee\EmployeeController::class, 'payslips'])->name('payslips');
        Route::get('/payslips/{payslip}',  [\App\Http\Controllers\Employee\EmployeeController::class, 'payslipShow'])->name('payslips.show');

        // Documents
        Route::get('/documents',           [\App\Http\Controllers\Employee\EmployeeController::class, 'documents'])->name('documents');

        // Announcements
        Route::get('/announcements',       [\App\Http\Controllers\Employee\EmployeeController::class, 'announcements'])->name('announcements');

        // Profile
        Route::get('/profile',             [\App\Http\Controllers\Employee\EmployeeController::class, 'profile'])->name('profile');
        Route::patch('/profile',           [\App\Http\Controllers\Employee\EmployeeController::class, 'profileUpdate'])->name('profile.update');
        Route::patch('/profile/password',  [\App\Http\Controllers\Employee\EmployeeController::class, 'passwordUpdate'])->name('profile.password');
    });
});

// Run pending migrations + clear caches (token-guarded, same secret as deploy).
Route::get('/api-migrate', function (\Illuminate\Http\Request $request) {
    $secret = 'eSahlan_Deploy_2026_Secret';
    $token  = $request->header('X-Deploy-Token') ?? $request->query('token', '');
    if (!hash_equals($secret, $token)) { abort(403); }

    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    $clear = \Illuminate\Support\Facades\Artisan::output();
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    $migrate = \Illuminate\Support\Facades\Artisan::output();

    return response('<pre>' . e($clear . "\n" . $migrate) . '</pre>');
});

// Deploy webhook (called by GitHub Actions)
Route::get('/api-sync', function (\Illuminate\Http\Request $request) {
    $secret = 'eSahlan_Deploy_2026_Secret';
    $token  = $request->header('X-Deploy-Token') ?? $request->query('token', '');
    if (!hash_equals($secret, $token)) { abort(403); }
    $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $proc = proc_open('bash /home/u801770158/deploy.sh', $desc, $pipes);
    $output = '';
    if (is_resource($proc)) {
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        proc_close($proc);
    }
    return response('<pre>' . e($output) . '</pre>');
});

// Admin Auth
Route::prefix('admin')->name('admin.')->middleware('admin.monitor')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login.post');
    });

    Route::middleware(['auth', 'role:super_admin,admin,operations_manager,finance_manager,marketing_manager,customer_support,employee', 'admin.gate'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/analytics', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'index'])->name('analytics');
        Route::get('/analytics/api', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'api'])->name('analytics.api');

        // Users
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [AdminUserController::class, 'index'])->name('index');
            Route::get('/live-locations', [AdminUserController::class, 'liveLocations'])->name('live-locations');
            Route::delete('/bulk-destroy', [AdminUserController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::get('/{user}', [AdminUserController::class, 'show'])->name('show');
            Route::patch('/{user}/status', [AdminUserController::class, 'updateStatus'])->name('status');
            Route::post('/{user}/reset-pin', [AdminUserController::class, 'resetPin'])->name('reset-pin');
            Route::delete('/{user}', [AdminUserController::class, 'destroy'])->name('destroy');
        });

        // Vendors
        Route::prefix('vendors')->name('vendors.')->group(function () {
            Route::get('/', [AdminVendorController::class, 'index'])->name('index');
            Route::delete('/bulk-destroy', [AdminVendorController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::get('/{vendor}', [AdminVendorController::class, 'show'])->name('show');
            Route::post('/{vendor}/approve', [AdminVendorController::class, 'approve'])->name('approve');
            Route::post('/{vendor}/reject', [AdminVendorController::class, 'reject'])->name('reject');
            Route::post('/{vendor}/toggle-featured', [AdminVendorController::class, 'toggleFeatured'])->name('toggle-featured');
            Route::delete('/{vendor}', [AdminVendorController::class, 'destroy'])->name('destroy');
        });

        // Orders
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [AdminOrderController::class, 'index'])->name('index');
            Route::get('/poll-new', [AdminOrderController::class, 'pollNew'])->name('poll');
            Route::post('/bulk', [AdminOrderController::class, 'bulkAction'])->name('bulk');
            Route::get('/{order}', [AdminOrderController::class, 'show'])->name('show');
            Route::get('/{order}/print', [AdminOrderController::class, 'print'])->name('print');
            Route::patch('/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('status');
            Route::post('/{order}/assign', [AdminOrderController::class, 'assignDeliveryman'])->name('assign');
            Route::post('/{order}/unassign-driver', [AdminOrderController::class, 'unassignDriver'])->name('unassign-driver');
            Route::post('/{order}/reassign-driver', [AdminOrderController::class, 'reassignDriver'])->name('reassign-driver');
        });

        // Deliverymen
        Route::prefix('deliverymen')->name('deliverymen.')->group(function () {
            Route::get('/', [AdminDeliverymanController::class, 'index'])->name('index');
            Route::get('/earnings', [AdminDeliverymanController::class, 'earnings'])->name('earnings');
            Route::post('/earnings/bulk-reset', [AdminDeliverymanController::class, 'bulkResetEarnings'])->name('earnings.bulk-reset');
            Route::post('/{deliveryman}/reset-earning', [AdminDeliverymanController::class, 'resetEarning'])->name('reset-earning');
            Route::post('/', [AdminDeliverymanController::class, 'store'])->name('store');
            Route::get('/{deliveryman}', [AdminDeliverymanController::class, 'show'])->name('show');
            Route::post('/{deliveryman}/approve', [AdminDeliverymanController::class, 'approve'])->name('approve');
            Route::post('/{deliveryman}/reject', [AdminDeliverymanController::class, 'reject'])->name('reject');
            Route::post('/{deliveryman}/toggle-block', [AdminDeliverymanController::class, 'toggleBlock'])->name('toggle-block');
            Route::post('/{deliveryman}/change-password', [AdminDeliverymanController::class, 'changePassword'])->name('change-password');
            Route::delete('/{deliveryman}', [AdminDeliverymanController::class, 'destroy'])->name('destroy');
            Route::post('/settings', [AdminDeliverymanController::class, 'saveSettings'])->name('settings');
            Route::post('/documents/{document}/approve', [AdminDeliverymanController::class, 'approveDocument'])->name('document.approve');
            Route::post('/documents/{document}/reject', [AdminDeliverymanController::class, 'rejectDocument'])->name('document.reject');
        });

        // Modules
        Route::prefix('modules')->name('modules.')->group(function () {
            Route::get('/', [AdminModuleController::class, 'index'])->name('index');
            Route::get('/{module}', [AdminModuleController::class, 'show'])->name('show');
            Route::patch('/{module}', [AdminModuleController::class, 'update'])->name('update');
            Route::post('/{module}/toggle', [AdminModuleController::class, 'toggleStatus'])->name('toggle');
            Route::post('/{module}/districts', [AdminModuleController::class, 'updateDistricts'])->name('districts');
        });

        // Rewards & Points
        Route::prefix('rewards')->name('rewards.')->group(function () {
            Route::get('/',       [AdminRewardController::class, 'index'])->name('index');
            Route::patch('/',     [AdminRewardController::class, 'update'])->name('update');
            Route::get('/ledger', [AdminRewardController::class, 'ledger'])->name('ledger');
        });
        Route::prefix('affiliates')->name('affiliates.')->group(function () {
            Route::get('/',                          [AdminAffiliateController::class, 'index'])->name('index');
            Route::get('/{id}',                      [AdminAffiliateController::class, 'show'])->name('show');
            Route::patch('/{id}/status',             [AdminAffiliateController::class, 'updateStatus'])->name('status');
            Route::get('/payouts/list',              [AdminAffiliateController::class, 'payouts'])->name('payouts');
            Route::post('/payouts/{id}/process',     [AdminAffiliateController::class, 'processPayout'])->name('payout.process');
            Route::get('/settings/config',           [AdminAffiliateController::class, 'settings'])->name('settings');
            Route::post('/settings/config',          [AdminAffiliateController::class, 'saveSettings'])->name('settings.save');
        });

        // Payment Settings (enable/disable + config)
        Route::prefix('payment-settings')->name('payment-settings.')->group(function () {
            Route::get('/',                                      [AdminPaymentSettingsController::class, 'index'])->name('index');
            Route::post('/toggle/{method}',                      [AdminPaymentSettingsController::class, 'toggle'])->name('toggle');
            Route::post('/waafi',                                [AdminPaymentSettingsController::class, 'updateWaafi'])->name('waafi');
            Route::post('/wallet',                               [AdminPaymentSettingsController::class, 'updateWallet'])->name('wallet');
            Route::post('/mobile',                               [AdminPaymentSettingsController::class, 'mobileStore'])->name('mobile.store');
            Route::patch('/mobile/{account}',                    [AdminPaymentSettingsController::class, 'mobileUpdate'])->name('mobile.update');
            Route::delete('/mobile/{account}',                   [AdminPaymentSettingsController::class, 'mobileDestroy'])->name('mobile.destroy');
            Route::patch('/mobile/{account}/toggle',             [AdminPaymentSettingsController::class, 'mobileToggle'])->name('mobile.toggle');
        });

        // Mobile Pay Accounts (legacy — keep for backward compat)
        Route::prefix('mobile-pay')->name('mobile-pay.')->group(function () {
            Route::get('/',                              [AdminMobilePayController::class, 'index'])->name('index');
            Route::post('/',                             [AdminMobilePayController::class, 'store'])->name('store');
            Route::patch('/{account}',                   [AdminMobilePayController::class, 'update'])->name('update');
            Route::delete('/{account}',                  [AdminMobilePayController::class, 'destroy'])->name('destroy');
            Route::patch('/{account}/toggle',            [AdminMobilePayController::class, 'toggleStatus'])->name('toggle');
        });

        // Banners
        Route::prefix('banners')->name('banners.')->group(function () {
            Route::get('/', [AdminBannerController::class, 'index'])->name('index');
            Route::post('/', [AdminBannerController::class, 'store'])->name('store');
            Route::delete('/{banner}', [AdminBannerController::class, 'destroy'])->name('destroy');
            Route::post('/{banner}/toggle', [AdminBannerController::class, 'toggleStatus'])->name('toggle');
            Route::post('/reorder', [AdminBannerController::class, 'reorder'])->name('reorder');
        });

        // Ads Management
        Route::prefix('ads')->name('ads.')->group(function () {
            Route::get('/',            [AdminAdController::class, 'index'])->name('index');
            Route::post('/',           [AdminAdController::class, 'store'])->name('store');
            Route::put('/{ad}',        [AdminAdController::class, 'update'])->name('update');
            Route::delete('/{ad}',     [AdminAdController::class, 'destroy'])->name('destroy');
            Route::post('/{ad}/toggle',[AdminAdController::class, 'toggleStatus'])->name('toggle');
        });

        // Finance
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [AdminFinanceController::class, 'index'])->name('index');
            Route::get('/transactions', [AdminFinanceController::class, 'transactions'])->name('transactions');
            Route::get('/commissions', [AdminFinanceController::class, 'commissions'])->name('commissions');
            Route::post('/withdrawals/{withdrawal}/approve', [AdminFinanceController::class, 'approveWithdrawal'])->name('withdrawals.approve');
            Route::post('/withdrawals/{withdrawal}/reject', [AdminFinanceController::class, 'rejectWithdrawal'])->name('withdrawals.reject');
            Route::post('/transactions/reset', [AdminFinanceController::class, 'resetTransactions'])->name('transactions.reset');
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
        // ── Live Management ────────────────────────────────────────────────────
        Route::prefix('live')->name('live.')->group(function () {
            Route::get('/',                             [\App\Http\Controllers\Admin\AdminLiveController::class, 'index'])->name('index');
            Route::get('/history',                      [\App\Http\Controllers\Admin\AdminLiveController::class, 'history'])->name('history');
            Route::get('/{id}/detail',                  [\App\Http\Controllers\Admin\AdminLiveController::class, 'detail'])->name('detail');
            Route::post('/{id}/force-end',              [\App\Http\Controllers\Admin\AdminLiveController::class, 'forceEnd'])->name('force-end');
            Route::post('/users/{userId}/toggle-ban',   [\App\Http\Controllers\Admin\AdminLiveController::class, 'toggleLiveBan'])->name('users.toggle-ban');
            Route::get('/banned',                       [\App\Http\Controllers\Admin\AdminLiveController::class, 'banned'])->name('banned');
            Route::get('/gifts',                        [\App\Http\Controllers\Admin\AdminLiveController::class, 'gifts'])->name('gifts');
            Route::post('/gifts',                       [\App\Http\Controllers\Admin\AdminLiveController::class, 'storeGift'])->name('gifts.store');
            Route::put('/gifts/{id}',                   [\App\Http\Controllers\Admin\AdminLiveController::class, 'updateGift'])->name('gifts.update');
            Route::post('/gifts/{id}/toggle-status',    [\App\Http\Controllers\Admin\AdminLiveController::class, 'toggleGiftStatus'])->name('gifts.toggle');
            Route::delete('/gifts/{id}',                [\App\Http\Controllers\Admin\AdminLiveController::class, 'destroyGift'])->name('gifts.destroy');
            Route::get('/transactions',                 [\App\Http\Controllers\Admin\AdminLiveController::class, 'transactions'])->name('transactions');
            Route::get('/reports',                      [\App\Http\Controllers\Admin\AdminLiveController::class, 'reports'])->name('reports');
            Route::post('/reports/{reportId}/review',   [\App\Http\Controllers\Admin\AdminLiveController::class, 'reviewReport'])->name('reports.review');
            Route::get('/coin-revenue',                 [\App\Http\Controllers\Admin\AdminLiveController::class, 'coinRevenue'])->name('coin-revenue');
        });

        Route::prefix('coins')->name('coins.')->controller(\App\Http\Controllers\Admin\AdminCoinController::class)->group(function () {
            Route::get('/',                    'index')->name('index');
            Route::get('/purchases',           'purchases')->name('purchases');
            Route::post('/packages',           'storePackage')->name('packages.store');
            Route::put('/packages/{id}',       'updatePackage')->name('packages.update');
            Route::post('/packages/{id}/toggle', 'togglePackage')->name('packages.toggle');
            Route::delete('/packages/{id}',    'destroyPackage')->name('packages.destroy');
        });

        Route::prefix('emarry')->name('emarry.')->group(function () {
            $em = \App\Http\Controllers\Admin\AdminEMarryController::class;
            Route::get('/',                    [$em, 'index'])->name('index');
            Route::post('/{id}/approve',       [$em, 'approve'])->name('approve');
            Route::post('/{id}/reject',        [$em, 'reject'])->name('reject');
            Route::delete('/{id}',             [$em, 'delete'])->name('delete');
            Route::get('/{id}/detail',         [$em, 'detail'])->name('detail');
            Route::get('/all/interests',                [$em, 'interests'])->name('interests');
            // Monetization management
            Route::get('/monetization',                 [$em, 'monetization'])->name('monetization');
            Route::post('/monetization/mobile-pay/{id}/approve', [$em, 'approveMobilePay'])->name('monetization.mobile-pay.approve');
            Route::post('/monetization/mobile-pay/{id}/reject',  [$em, 'rejectMobilePay'])->name('monetization.mobile-pay.reject');
            Route::post('/monetization/subscription/{id}/cancel',[$em, 'cancelSubscription'])->name('monetization.subscription.cancel');
            Route::post('/monetization/credits/grant',           [$em, 'grantCredits'])->name('monetization.credits.grant');
        });

        Route::prefix('community')->name('community.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'index'])->name('index');
            Route::post('/toggle-enabled', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'toggleEnabled'])->name('toggle-enabled');
            Route::get('/posts', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'posts'])->name('posts');
            Route::delete('/posts/{id}', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'deletePost'])->name('posts.delete');
            Route::post('/posts-bulk-delete', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'bulkDeletePosts'])->name('posts.bulk-delete');
            Route::post('/posts/{id}/toggle-privacy', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'togglePostPrivacy'])->name('posts.toggle-privacy');
            Route::post('/posts-bulk-privacy', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'bulkPrivacyPosts'])->name('posts.bulk-privacy');
            Route::get('/reports', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'reports'])->name('reports');
            Route::post('/reports/{id}/action', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'actionReport'])->name('reports.action');
            Route::get('/groups', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'groups'])->name('groups');
            Route::delete('/groups/{id}', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'deleteGroup'])->name('groups.delete');
            Route::get('/users', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'users'])->name('users');
            Route::post('/users/{id}/verify', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'toggleVerify'])->name('users.verify');
            Route::get('/moderation', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'moderation'])->name('moderation');
            Route::post('/moderation', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'updateModeration'])->name('moderation.update');
            Route::post('/moderation/{id}/approve', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'approvePost'])->name('moderation.approve');
            Route::post('/moderation/{id}/reject', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'rejectPost'])->name('moderation.reject');
            Route::get('/engagement', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'engagement'])->name('engagement');
            Route::post('/engagement/generate', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'generateEngagement'])->name('engagement.generate');
            Route::post('/engagement/reset', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'resetEngagement'])->name('engagement.reset');
            Route::post('/engagement/reset-all', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'resetAllEngagement'])->name('engagement.reset-all');
            Route::get('/algorithm', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'algorithm'])->name('algorithm');
            Route::get('/algorithm/data', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'algorithmData'])->name('algorithm.data');
            Route::get('/users/{id}/detail', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'userDetail'])->name('users.detail');
            Route::get('/users/{id}/chats', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'userChats'])->name('users.chats');
            Route::get('/chats/{chatId}/messages', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'chatMessages'])->name('chats.messages');
            Route::get('/chat-monitor', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'chatMonitor'])->name('chat-monitor');
            Route::delete('/messages/{id}', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'deleteMessage'])->name('messages.delete');
            Route::post('/users/{userId}/ban', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'banUser'])->name('users.ban');
            Route::post('/users/{userId}/unban', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'unbanUser'])->name('users.unban');
            Route::post('/users/{userId}/unrestrict', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'unrestrictUser'])->name('users.unrestrict');
            Route::post('/users/{userId}/clear-strikes', [\App\Http\Controllers\Admin\AdminCommunityController::class, 'clearStrikes'])->name('users.clear-strikes');
        });
        // Podcast Management
        Route::prefix('podcast')->name('podcast.')->group(function () {
            $pc = \App\Http\Controllers\Admin\AdminPodcastController::class;
            Route::get('/',                     [$pc, 'index'])->name('index');
            Route::get('/categories',           [$pc, 'categories'])->name('categories');
            Route::get('/{id}',                 [$pc, 'show'])->name('show');
            Route::post('/{id}/verify',         [$pc, 'toggleVerify'])->name('verify');
            Route::delete('/{id}',              [$pc, 'destroy'])->name('destroy');
            Route::delete('/episodes/{id}',     [$pc, 'destroyEpisode'])->name('episodes.destroy');
            Route::post('/categories',           [$pc, 'storeCategory'])->name('categories.store');
            Route::put('/categories/{id}',       [$pc, 'updateCategory'])->name('categories.update');
            Route::post('/categories/{id}/toggle', [$pc, 'toggleCategoryStatus'])->name('categories.toggle');
            Route::delete('/categories/{id}',    [$pc, 'destroyCategory'])->name('categories.destroy');
        });
        // Trust & Safety
        Route::prefix('trust-safety')->name('trust-safety.')->group(function () {
            $c = \App\Http\Controllers\Admin\AdminTrustSafetyController::class;
            Route::get('/',              [$c, 'dashboard'])->name('dashboard');
            Route::get('/api/data',      [$c, 'dashboardApi'])->name('api');
            Route::get('/reports',       [$c, 'reports'])->name('reports');
            Route::post('/reports/{id}', [$c, 'resolveReport'])->name('reports.resolve');
            Route::get('/queue',         [$c, 'queue'])->name('queue');
            Route::post('/queue/{id}',   [$c, 'moderatePost'])->name('queue.moderate');
            Route::get('/strikes',       [$c, 'strikes'])->name('strikes');
            Route::get('/appeals',       [$c, 'appeals'])->name('appeals');
            Route::post('/appeals/{id}', [$c, 'resolveAppeal'])->name('appeals.resolve');
            Route::get('/settings',      [$c, 'settings'])->name('settings');
            Route::post('/terms',        [$c, 'addBlockedTerm'])->name('terms.store');
            Route::delete('/terms/{id}', [$c, 'deleteBlockedTerm'])->name('terms.delete');
        });

        // Copyright Claims
        Route::prefix('copyright')->name('copyright.')->group(function () {
            $cc = \App\Http\Controllers\Admin\AdminCopyrightController::class;
            Route::get('/',                         [$cc, 'index'])->name('index');
            Route::get('/{id}',                     [$cc, 'show'])->name('show');
            Route::post('/{id}/resolve',            [$cc, 'resolve'])->name('resolve');
            Route::post('/counter/{id}/resolve',    [$cc, 'resolveCounter'])->name('counter.resolve');
        });

        // eSpace Internal Module Ads
        Route::prefix('espace-ads')->name('espace-ads.')->group(function () {
            Route::get('/',                        [\App\Http\Controllers\Admin\AdminESpaceAdController::class, 'index'])->name('index');
            Route::get('/create',                  [\App\Http\Controllers\Admin\AdminESpaceAdController::class, 'create'])->name('create');
            Route::post('/',                       [\App\Http\Controllers\Admin\AdminESpaceAdController::class, 'store'])->name('store');
            Route::get('/{espaceAd}/edit',         [\App\Http\Controllers\Admin\AdminESpaceAdController::class, 'edit'])->name('edit');
            Route::put('/{espaceAd}',              [\App\Http\Controllers\Admin\AdminESpaceAdController::class, 'update'])->name('update');
            Route::delete('/{espaceAd}',           [\App\Http\Controllers\Admin\AdminESpaceAdController::class, 'destroy'])->name('destroy');
            Route::post('/{espaceAd}/toggle',      [\App\Http\Controllers\Admin\AdminESpaceAdController::class, 'toggle'])->name('toggle');
        });

        // Community Ads & Business Pages
        Route::prefix('community-ads')->name('community-ads.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminCommunityAdsController::class, 'index'])->name('index');
            Route::patch('/ads/{id}/status', [\App\Http\Controllers\Admin\AdminCommunityAdsController::class, 'updateAdStatus'])->name('status');
            Route::post('/ads/{id}/delete', [\App\Http\Controllers\Admin\AdminCommunityAdsController::class, 'deleteAd'])->name('delete');
            Route::post('/ads-bulk-delete', [\App\Http\Controllers\Admin\AdminCommunityAdsController::class, 'bulkDelete'])->name('bulk-delete');
            Route::post('/settings', [\App\Http\Controllers\Admin\AdminCommunityAdsController::class, 'saveSettings'])->name('save-settings');
            Route::patch('/pricing/{id}', [\App\Http\Controllers\Admin\AdminCommunityAdsController::class, 'updatePricing'])->name('pricing');
            Route::post('/ads/{id}/reactivate', [\App\Http\Controllers\Admin\AdminCommunityAdsController::class, 'reactivateAd'])->name('reactivate');
        });

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [AdminNotificationController::class, 'index'])->name('index');
            Route::post('/send', [AdminNotificationController::class, 'send'])->name('send');
            Route::delete('/{id}', [AdminNotificationController::class, 'destroy'])->name('destroy');
            Route::delete('/', [AdminNotificationController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::get('/users/search', [AdminNotificationController::class, 'searchUsers'])->name('users.search');
            Route::get('/templates', [AdminNotificationController::class, 'templates'])->name('templates');
            Route::post('/templates', [AdminNotificationController::class, 'saveTemplates'])->name('templates.save');

            // Cart abandonment templates
            Route::get('/cart-templates',         [AdminNotificationController::class, 'cartTemplates'])->name('cart-templates');
            Route::post('/cart-templates/update', [AdminNotificationController::class, 'updateCartTemplate'])->name('cart-templates.update');
            Route::post('/cart-templates/toggle', [AdminNotificationController::class, 'toggleCartTemplate'])->name('cart-templates.toggle');

            // Notification analytics
            Route::get('/{id}/stats',   [AdminNotificationController::class, 'stats'])->name('stats');
            Route::post('/{id}/resend', [AdminNotificationController::class, 'resend'])->name('resend');

            // Discount campaign notifications
            Route::get('/discount-campaigns',                  [AdminNotificationController::class, 'discountCampaigns'])->name('discount-campaigns');
            Route::post('/discount-campaigns/{id}/send',       [AdminNotificationController::class, 'sendCampaignNotification'])->name('discount-campaigns.send');
            Route::post('/discount-campaigns/{id}/template',   [AdminNotificationController::class, 'updateCampaignTemplate'])->name('discount-campaigns.template');
            Route::post('/discount-campaigns/{id}/pause',      [AdminNotificationController::class, 'toggleCampaignPause'])->name('discount-campaigns.pause');

            // ── Auto Notifications (eTicket flights, future types) ──────────
            Route::get('/auto',                    [AdminNotificationController::class, 'autoNotifications'])->name('auto');
            Route::post('/auto/{slug}/update',     [AdminNotificationController::class, 'updateAutoTemplate'])->name('auto.update');
            Route::post('/auto/{slug}/toggle',     [AdminNotificationController::class, 'toggleAutoTemplate'])->name('auto.toggle');
            Route::post('/auto/{slug}/send-now',   [AdminNotificationController::class, 'sendAutoNow'])->name('auto.send-now');
        });

        // ── Admin Email Alert Settings ─────────────────────────────────────
        Route::prefix('alerts')->name('alerts.')->group(function () {
            $ac = \App\Http\Controllers\Admin\AdminAlertSettingsController::class;
            Route::get('/',                   [$ac, 'index'])->name('index');
            Route::post('/email',             [$ac, 'saveEmail'])->name('save-email');
            Route::post('/toggle/{key}',      [$ac, 'toggle'])->name('toggle');
            Route::post('/test',              [$ac, 'test'])->name('test');
            Route::post('/clear-logs',        [$ac, 'clearLogs'])->name('clear-logs');
        });

        // Database Cleaner
        Route::prefix('db-clean')->name('db-clean.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminDbCleanController::class, 'index'])->name('index');
            Route::post('/run', [\App\Http\Controllers\Admin\AdminDbCleanController::class, 'run'])->name('run');
        });

        // Inbox (Support + Marketing broadcasts)
        Route::prefix('inbox')->name('inbox.')->controller(\App\Http\Controllers\Admin\AdminInboxWebController::class)->group(function () {
            Route::get('/conversations',               'conversations')->name('conversations');
            Route::get('/conversations/{uuid}',        'conversationMessages')->name('conversation.messages');
            Route::post('/conversations/{uuid}/reply', 'reply')->name('conversation.reply');
            Route::post('/conversations/{uuid}/assign', 'assignAgent')->name('conversation.assign');
            Route::post('/conversations/{uuid}/status', 'updateStatus')->name('conversation.status');
            Route::prefix('broadcasts')->name('broadcasts.')->group(function () {
                Route::get('/',                  'broadcasts')->name('index');
                Route::post('/',                 'createBroadcast')->name('store');
                Route::post('/{uuid}/send',      'sendBroadcast')->name('send');
                Route::post('/{uuid}/resend',    'resendBroadcast')->name('resend');
                Route::delete('/{uuid}',         'deleteBroadcast')->name('delete');
                Route::post('/bulk-delete',      'bulkDeleteBroadcasts')->name('bulk-delete');
                Route::get('/{uuid}/stats',      'broadcastStats')->name('stats');
            });
            Route::get('/stats', 'stats')->name('stats');
            Route::get('/conversations/{uuid}/poll', 'pollMessages')->name('conversation.poll');
            Route::post('/calls/{callUuid}/decline',   'declineCall')->name('call.decline');
            Route::post('/calls/{callUuid}/answer',    'answerCall')->name('call.answer');
            Route::post('/calls/{callUuid}/admin-ice', 'adminIce')->name('call.admin-ice');
            Route::get('/global-poll', 'globalPoll')->name('global-poll');
            Route::get('/users/search', 'searchUsers')->name('users.search');
        });

        // App Version Control (force update)
        Route::prefix('app-versions')->name('app-versions.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminAppVersionController::class, 'index'])->name('index');
            Route::patch('/{app_type}', [\App\Http\Controllers\Admin\AdminAppVersionController::class, 'update'])->name('update');
        });

        // Landing Page — Section Visibility Management
        Route::prefix('landing-sections')->name('landing-sections.')->group(function () {
            // GET /admin/landing-sections → redirect to /admin/landing (toggles embedded there)
            Route::get('/', fn() => redirect()->route('admin.landing.index'))->name('index');
            Route::post('/{slug}/toggle', [\App\Http\Controllers\Admin\AdminLandingPageController::class, 'toggle'])->name('toggle');
            Route::post('/reorder', [\App\Http\Controllers\Admin\AdminLandingPageController::class, 'reorder'])->name('reorder');
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
            Route::match(['PATCH','POST'], '/laundry/items/{item}', [$ctrl, 'laundryUpdate'])->name('laundry.update');
            Route::delete('/laundry/items/{item}',    [$ctrl, 'laundryDestroy'])->name('laundry.destroy');

            // eMoving
            Route::get('/moving',                    [$ctrl, 'movingIndex'])->name('moving');
            Route::post('/moving/pricing',            [$ctrl, 'movingPricingStore'])->name('moving.pricing.store');
            Route::match(['PATCH','POST'], '/moving/pricing/{id}', [$ctrl, 'movingPricingUpdate'])->name('moving.pricing.update');
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
            Route::post('/data/bundles/bulk',          [$ctrl, 'dataBundleStoreBulk'])->name('data.bundle.store-bulk');
            Route::post('/data/user-phones/{id}/email',[$ctrl, 'edataPhoneEmail'])->name('edata.phone.email');

            // eExchange
            Route::get('/exchange',                  [$ctrl, 'exchangeIndex'])->name('exchange');
            Route::post('/exchange/rates',            [$ctrl, 'exchangeStore'])->name('exchange.store');
            Route::delete('/exchange/rates/{rate}',   [$ctrl, 'exchangeDestroy'])->name('exchange.destroy');
            Route::post('/exchange/wallet-logos',     [$ctrl, 'exchangeUploadLogo'])->name('exchange.logo.upload');

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
            Route::post('/rent/agents/{id}/approve',  [$ctrl, 'agentApprove'])->name('rent.agent.approve');
            Route::post('/rent/agents/{id}/reject',   [$ctrl, 'agentReject'])->name('rent.agent.reject');
            Route::post('/rent/agents/{id}/toggle',   [$ctrl, 'agentToggle'])->name('rent.agent.toggle');
            Route::post('/rent/commission',           [$ctrl, 'saveRentCommission'])->name('rent.commission.save');

            // eFood — full management
            Route::prefix('efood')->name('efood.')->group(function () {
                $ef = AdminEFoodController::class;
                Route::get('/',                             [$ef, 'index'])->name('index');
                // Restaurants
                Route::post('/restaurants',                [$ef, 'restaurantStore'])->name('restaurant.store');
                Route::patch('/restaurants/{id}',          [$ef, 'restaurantUpdate'])->name('restaurant.update');
                Route::delete('/restaurants/{id}',         [$ef, 'restaurantDestroy'])->name('restaurant.destroy');
                Route::post('/restaurants/{id}/toggle',    [$ef, 'restaurantToggle'])->name('restaurant.toggle');
                Route::post('/restaurants/{id}/branch',   [$ef, 'createBranch'])->name('restaurant.branch');
                // Categories
                Route::post('/categories',                 [$ef, 'categoryStore'])->name('category.store');
                Route::patch('/categories/{id}',           [$ef, 'categoryUpdate'])->name('category.update');
                Route::delete('/categories/{id}',          [$ef, 'categoryDestroy'])->name('category.destroy');
                Route::post('/categories/{id}/assign',     [$ef, 'categoryAssign'])->name('category.assign');
                Route::delete('/categories-bulk',          [$ef, 'categoryBulkDestroy'])->name('category.bulk-destroy');
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

            // eShop
            Route::get('/shop',                      [$ctrl, 'shopIndex'])->name('shop');

            // eWholesale — full management suite
            Route::prefix('wholesale')->name('wholesale.')->group(function () {
                $ew = \App\Http\Controllers\Admin\AdminEWholesaleController::class;

                // Dashboard
                Route::get('/',                              [$ew, 'dashboard'])->name('dashboard');

                // Suppliers
                Route::get('/suppliers',                     [$ew, 'suppliers'])->name('suppliers');
                Route::get('/suppliers/create',              [$ew, 'supplierCreate'])->name('suppliers.create');
                Route::post('/suppliers',                    [$ew, 'supplierStore'])->name('suppliers.store');
                Route::get('/suppliers/{supplier}',          [$ew, 'supplierShow'])->name('suppliers.show');
                Route::get('/suppliers/{supplier}/edit',     [$ew, 'supplierEdit'])->name('suppliers.edit');
                Route::put('/suppliers/{supplier}',          [$ew, 'supplierUpdate'])->name('suppliers.update');
                Route::post('/suppliers/{supplier}/action',  [$ew, 'supplierAction'])->name('suppliers.action');
                Route::post('/suppliers/{supplier}/shipping',[$ew, 'supplierShippingUpdate'])->name('suppliers.shipping');

                // Banners
                Route::get('/banners',                       [$ew, 'banners'])->name('banners');
                Route::get('/banners/create',                [$ew, 'bannerCreate'])->name('banners.create');
                Route::post('/banners',                      [$ew, 'bannerStore'])->name('banners.store');
                Route::get('/banners/{banner}/edit',         [$ew, 'bannerEdit'])->name('banners.edit');
                Route::put('/banners/{banner}',              [$ew, 'bannerUpdate'])->name('banners.update');
                Route::post('/banners/{banner}/toggle',      [$ew, 'bannerToggle'])->name('banners.toggle');
                Route::delete('/banners/{banner}',           [$ew, 'bannerDelete'])->name('banners.delete');

                // Buyers & Credit
                Route::get('/buyers',                            [$ew, 'buyers'])->name('buyers');
                Route::post('/buyers/{buyer}/kyb',               [$ew, 'buyerKybAction'])->name('buyers.kyb');
                Route::post('/buyers/{buyer}/credit',            [$ew, 'buyerCreditStore'])->name('buyers.credit.store');
                Route::post('/buyers/{buyer}/credit/freeze',     [$ew, 'buyerCreditFreeze'])->name('buyers.credit.freeze');
                Route::post('/buyers/{buyer}/credit/adjust',     [$ew, 'buyerCreditAdjust'])->name('buyers.credit.adjust');
                Route::get('/buyers/{buyer}/ledger',             [$ew, 'buyerCreditLedger'])->name('buyers.credit.ledger');
                Route::get('/credit-aging',                      [$ew, 'creditAgingReport'])->name('credit.aging');

                // Catalog — Categories
                Route::get('/catalog/categories',               [$ew, 'categories'])->name('catalog.categories');
                Route::post('/catalog/categories',              [$ew, 'categoryStore'])->name('catalog.categories.store');
                Route::patch('/catalog/categories/{category}',  [$ew, 'categoryUpdate'])->name('catalog.categories.update');
                Route::post('/catalog/categories/sort',         [$ew, 'categorySortUpdate'])->name('catalog.categories.sort');

                // Catalog — Products
                Route::get('/catalog/products',                 [$ew, 'products'])->name('products');
                Route::get('/catalog/products/create',          [$ew, 'productCreate'])->name('products.create');
                Route::post('/catalog/products',                [$ew, 'productStore'])->name('products.store');
                Route::get('/catalog/products/{product}',       [$ew, 'productShow'])->name('products.show');
                Route::get('/catalog/products/{product}/edit',  [$ew, 'productEdit'])->name('products.edit');
                Route::put('/catalog/products/{product}',       [$ew, 'productUpdate'])->name('products.update');
                Route::patch('/catalog/products/{product}/status', [$ew, 'productStatusUpdate'])->name('products.status');

                // RFQ Center
                Route::get('/rfq',                              [$ew, 'rfqs'])->name('rfq');
                Route::get('/rfq/{rfq}',                        [$ew, 'rfqShow'])->name('rfq.show');
                Route::post('/rfq/{rfq}/moderate',              [$ew, 'rfqModerate'])->name('rfq.moderate');

                // Orders
                Route::get('/orders',                           [$ew, 'orders'])->name('orders');
                Route::get('/orders/{order}',                   [$ew, 'orderShow'])->name('orders.show');
                Route::get('/orders/{order}/print',             [$ew, 'orderPrint'])->name('orders.print');
                Route::patch('/orders/{order}/status',          [$ew, 'orderStatusUpdate'])->name('orders.status');
                Route::post('/orders/{order}/payment',          [$ew, 'orderPaymentRecord'])->name('orders.payment');
                Route::post('/orders/{order}/shipment',         [$ew, 'orderShipmentStore'])->name('orders.shipment');
                Route::post('/disputes/{dispute}/resolve',      [$ew, 'disputeResolve'])->name('disputes.resolve');

                // Settings
                Route::get('/settings',                         [$ew, 'settings'])->name('settings');
                Route::post('/settings',                        [$ew, 'settingsUpdate'])->name('settings.update');
                Route::post('/settings/price-list',             [$ew, 'priceListStore'])->name('settings.price-list.store');
                Route::post('/settings/platform-shipping',      [$ew, 'platformShippingUpdate'])->name('settings.platform-shipping');

                // Reports
                Route::get('/reports',                          [$ew, 'reports'])->name('reports');
                Route::get('/reports/export',                   [$ew, 'reportsCsvExport'])->name('reports.export');

                // Phase 5 — Settlement, Reviews, Disputes, Credit Reminder
                Route::get('/settlement',                       [$ew, 'settlementReport'])->name('settlement');
                Route::get('/reviews',                          [$ew, 'reviews'])->name('reviews');
                Route::post('/reviews/{review}/moderate',       [$ew, 'reviewModerate'])->name('reviews.moderate');
                Route::get('/disputes',                         [$ew, 'disputes'])->name('disputes.list');
                Route::post('/disputes/{dispute}/escalate',     [$ew, 'disputeEscalate'])->name('disputes.escalate');
                Route::post('/credit/{account}/remind',         [$ew, 'creditSendReminder'])->name('credit.remind');
                Route::get('/suppliers/{supplier}/scorecard',   [$ew, 'supplierScorecard'])->name('suppliers.scorecard');
            });

            // eGrocery — full management suite
            Route::prefix('egrocery')->name('egrocery.')->group(function () {
                $eg = \App\Http\Controllers\Admin\AdminEGroceryController::class;

                // Dashboard
                Route::get('/',  [$eg, 'index'])->name('index');

                // Categories
                Route::get('/categories',                    [$eg, 'categories'])->name('categories');
                Route::post('/categories',                   [$eg, 'categoryStore'])->name('category.store');
                Route::post('/categories/{id}',              [$eg, 'categoryUpdate'])->name('category.update');
                Route::post('/categories/{id}/toggle',       [$eg, 'categoryToggle'])->name('category.toggle');
                Route::post('/categories/sort',              [$eg, 'categorySortUpdate'])->name('category.sort');
                Route::delete('/categories/{id}',            [$eg, 'categoryDestroy'])->name('category.destroy');

                // Products — static routes BEFORE {id} to avoid param capture
                Route::get('/products',                      [$eg, 'products'])->name('products');
                Route::get('/products/create',               [$eg, 'productCreate'])->name('product.create');
                Route::get('/products/export',               [$eg, 'productCsvExport'])->name('product.export');
                Route::get('/products/template',             [$eg, 'productCsvTemplate'])->name('product.template');
                Route::post('/products',                     [$eg, 'productStore'])->name('product.store');
                Route::post('/products/bulk',                [$eg, 'productBulkAction'])->name('product.bulk');
                Route::post('/products/import',              [$eg, 'productCsvImport'])->name('product.import');
                Route::get('/products/{id}/edit',            [$eg, 'productEdit'])->name('product.edit');
                Route::post('/products/{id}',                [$eg, 'productUpdate'])->name('product.update');
                Route::post('/products/{id}/toggle',         [$eg, 'productToggle'])->name('product.toggle');
                Route::post('/products/{id}/quick-edit',     [$eg, 'productQuickEdit'])->name('product.quick-edit');
                Route::delete('/products/{id}',              [$eg, 'productDestroy'])->name('product.destroy');

                // Inventory
                Route::get('/inventory',                     [$eg, 'inventory'])->name('inventory');
                Route::post('/inventory/adjust',             [$eg, 'inventoryAdjust'])->name('inventory.adjust');
                Route::get('/inventory/variant-search',      [$eg, 'inventoryVariantSearch'])->name('inventory.variant-search');

                // Marketing
                Route::get('/marketing',                     [$eg, 'marketing'])->name('marketing');
                Route::post('/marketing/banners',            [$eg, 'bannerStore'])->name('banner.store');
                Route::post('/marketing/banners/{id}',       [$eg, 'bannerUpdate'])->name('banner.update');
                Route::post('/marketing/banners/{id}/toggle',[$eg, 'bannerToggle'])->name('banner.toggle');
                Route::delete('/marketing/banners/{id}',     [$eg, 'bannerDestroy'])->name('banner.destroy');
                Route::post('/marketing/sections',           [$eg, 'sectionStore'])->name('section.store');
                Route::post('/marketing/sections/{id}',      [$eg, 'sectionUpdate'])->name('section.update');
                Route::post('/marketing/sections/{id}/products', [$eg, 'sectionProductsUpdate'])->name('section.products');
                Route::post('/marketing/sections/{id}/toggle',   [$eg, 'sectionToggle'])->name('section.toggle');
                Route::delete('/marketing/sections/{id}',    [$eg, 'sectionDestroy'])->name('section.destroy');
                Route::post('/marketing/flash-deals',        [$eg, 'flashDealStore'])->name('flash.store');
                Route::delete('/marketing/flash-deals/{id}', [$eg, 'flashDealDestroy'])->name('flash.destroy');
                Route::get('/marketing/product-search',      [$eg, 'marketingProductSearch'])->name('marketing.product-search');
                Route::get('/marketing/variant-search',      [$eg, 'marketingVariantSearch'])->name('marketing.variant-search');

                // Orders
                Route::get('/orders',                        [$eg, 'orders'])->name('orders');
                Route::get('/orders/{id}',                   [$eg, 'orderShow'])->name('order.show');
                Route::post('/orders/{id}/status',           [$eg, 'orderUpdateStatus'])->name('order.status');
                Route::post('/orders/{id}/driver',           [$eg, 'orderAssignDriver'])->name('order.driver');
                Route::post('/orders/{id}/picked',           [$eg, 'orderPickedQty'])->name('order.picked');
                Route::post('/orders/{id}/substitute',       [$eg, 'orderSubstitute'])->name('order.substitute');
                Route::get('/orders/{id}/print',             [$eg, 'orderPrint'])->name('order.print');

                // Settings
                Route::get('/settings',                      [$eg, 'settings'])->name('settings');
                Route::post('/settings/zones',               [$eg, 'zoneStore'])->name('zone.store');
                Route::post('/settings/zones/{id}',          [$eg, 'zoneUpdate'])->name('zone.update');
                Route::delete('/settings/zones/{id}',        [$eg, 'zoneDestroy'])->name('zone.destroy');
                Route::post('/settings/slots',               [$eg, 'slotStore'])->name('slot.store');
                Route::post('/settings/slots/{id}',          [$eg, 'slotUpdate'])->name('slot.update');
                Route::delete('/settings/slots/{id}',        [$eg, 'slotDestroy'])->name('slot.destroy');

                // Coupons
                Route::get('/coupons',                       [$eg, 'coupons'])->name('coupons');
                Route::post('/coupons',                      [$eg, 'couponStore'])->name('coupon.store');
                Route::post('/coupons/{id}',                 [$eg, 'couponUpdate'])->name('coupon.update');
                Route::post('/coupons/{id}/toggle',          [$eg, 'couponToggle'])->name('coupon.toggle');
                Route::delete('/coupons/{id}',               [$eg, 'couponDestroy'])->name('coupon.destroy');

                // Reports
                Route::get('/reports',                       [$eg, 'reports'])->name('reports');

                // Reviews moderation
                Route::get('/reviews',                       [$eg, 'reviews'])->name('reviews');
                Route::post('/reviews/{id}/toggle',          [$eg, 'reviewToggle'])->name('review.toggle');
            });
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
            Route::post('/ticket/flights/bulk',       [$ctrl, 'flightBulkStore'])->name('ticket.flight.bulk');
            Route::patch('/ticket/flights/{id}',      [$ctrl, 'flightUpdate'])->name('ticket.flight.update');
            Route::delete('/ticket/flights/{id}',     [$ctrl, 'flightDestroy'])->name('ticket.flight.destroy');
        });

        // ── eShop Full Management ─────────────────────────────────────────
        Route::prefix('eshop')->name('eshop.')->group(function () {
            $es = \App\Http\Controllers\Admin\AdminEShopController::class;
            Route::get('/', [$es, 'index'])->name('index');
            // Withdrawals
            Route::get('/withdrawals',                    [$es, 'withdrawals'])->name('withdrawals');
            Route::post('/withdrawals/{id}/approve',      [$es, 'withdrawalApprove'])->name('withdrawals.approve');
            Route::post('/withdrawals/{id}/reject',       [$es, 'withdrawalReject'])->name('withdrawals.reject');
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
            // Vendors (multivendor)
            Route::post('/vendors/{id}/approve',           [$es, 'vendorApprove'])->name('vendor.approve');
            Route::post('/vendors/{id}/toggle-featured',   [$es, 'vendorToggleFeatured'])->name('vendor.toggle-featured');
            Route::patch('/vendors/{id}/commission',       [$es, 'vendorUpdateCommission'])->name('vendor.commission');
            // Commissions
            Route::post('/commissions/{id}/mark-paid',     [$es, 'commissionMarkPaid'])->name('commission.mark-paid');
            Route::patch('/withdrawals/{id}',              [$es, 'withdrawalProcess'])->name('withdrawal.process');
        });

        // Landing Page Management
        Route::get('/landing', [AdminLandingController::class, 'index'])->name('landing.index');
        Route::put('/landing', [AdminLandingController::class, 'update'])->name('landing.update');

        // ── Security / SOC Dashboard ──────────────────────────────────────────
        Route::middleware('can:platform.audit.view')->prefix('security')->name('security.')->group(function () {
            $sc = \App\Http\Controllers\Admin\AdminSecurityController::class;
            Route::get('/soc',               [$sc, 'soc'])->name('soc');
            Route::get('/stats',             [$sc, 'stats'])->name('stats');
            Route::get('/system-metrics',    [$sc, 'systemMetrics'])->name('system-metrics');
            Route::get('/live-events',       [$sc, 'liveEvents'])->name('live-events');
            Route::post('/quick-action',     [$sc, 'quickAction'])->name('quick-action');
            Route::get('/audit/export',      [$sc, 'exportAudit'])->name('audit.export');
        });

        // ── Roles & Access (assign roles + scope employees to modules) ────────
        // ── App Download Link Analytics ───────────────────────────────────────
        Route::prefix('app-link')->name('app-link.')->group(function () {
            $alc = \App\Http\Controllers\Admin\AdminAppLinkController::class;
            Route::get('/',           [$alc, 'index'])->name('index');
            Route::post('/urls',      [$alc, 'saveUrls'])->name('urls');
            Route::post('/clear',     [$alc, 'clearData'])->name('clear');
            Route::get('/chart-data', [$alc, 'chartData'])->name('chart-data');
        });

        Route::middleware('role:super_admin,admin')->prefix('access')->name('access.')->group(function () {
            Route::get('/',        [\App\Http\Controllers\Admin\AdminAccessController::class, 'index'])->name('index');
            Route::put('/{user}',  [\App\Http\Controllers\Admin\AdminAccessController::class, 'update'])->name('update');
        });

        // Wallet Management & Payment Settings
        Route::prefix('wallet')->name('wallet.')->group(function () {
            $wc = AdminWalletController::class;
            Route::get('/',                               [$wc, 'index'])->name('index');
            Route::get('/transactions',                   [$wc, 'transactions'])->name('transactions');
            Route::post('/credit',                        [$wc, 'creditUser'])->name('credit');
            Route::post('/debit',                         [$wc, 'debitUser'])->name('debit');
            Route::post('/transfer',                      [$wc, 'transferBetweenUsers'])->name('transfer');
            Route::post('/freeze/{userId}',               [$wc, 'freezeWallet'])->name('freeze');
            Route::post('/unfreeze/{userId}',             [$wc, 'unfreezeWallet'])->name('unfreeze');
            Route::get('/withdrawals',                    [$wc, 'withdrawals'])->name('withdrawals');
            Route::post('/withdrawals/{id}/approve',      [$wc, 'approveWithdrawal'])->name('withdrawal.approve');
            Route::post('/withdrawals/{id}/reject',       [$wc, 'rejectWithdrawal'])->name('withdrawal.reject');
            Route::post('/withdrawals/{id}/process',      [$wc, 'processWithdrawal'])->name('withdrawal.process');
            Route::post('/withdrawals/bulk-approve',      [$wc, 'bulkApproveWithdrawals'])->name('withdrawal.bulk-approve');
            Route::get('/user/{userId}',                  [$wc, 'userDetail'])->name('user-detail');
            Route::get('/user/{userId}/statement',        [$wc, 'statementExport'])->name('user-statement');
            Route::post('/reset/{userId}',                [$wc, 'resetWallet'])->name('reset');
            Route::post('/bulk-reset',                    [$wc, 'bulkResetWallets'])->name('bulk-reset');
            Route::post('/nuke-all',                      [$wc, 'nukeAll'])->name('nuke-all');
            Route::post('/reset-pin/{userId}',            [$wc, 'resetUserPin'])->name('reset-pin');
            Route::get('/settings',                       [$wc, 'settings'])->name('settings');
            Route::post('/settings',                      [$wc, 'saveSettings'])->name('settings.save');
            // Mobile Pay top-up requests (manual verification)
            Route::get('/topup-requests',                 [$wc, 'topupRequests'])->name('topup-requests');
            Route::post('/topup-requests/{id}/approve',   [$wc, 'approveTopupRequest'])->name('topup-requests.approve');
            Route::post('/topup-requests/{id}/reject',    [$wc, 'rejectTopupRequest'])->name('topup-requests.reject');
        });

        // ── Crypto Exchange Admin ─────────────────────────────────────────────
        Route::prefix('crypto')->name('crypto.')->group(function () {
            $cc = AdminCryptoController::class;
            Route::get('/',                              [$cc,'dashboard'])->name('dashboard');
            // Coins — static routes BEFORE parametric {id} routes
            Route::get('/coins',                         [$cc,'coins'])->name('coins');
            Route::post('/coins',                        [$cc,'storeCoin'])->name('coins.store');
            Route::post('/coins/spread',                 [$cc,'updateSpreads'])->name('coins.spread');
            Route::post('/prices/override',              [$cc,'overridePrice'])->name('prices.override');
            Route::patch('/coins/{id}/toggle',           [$cc,'toggleCoin'])->name('coins.toggle');
            Route::get('/coins/{id}/settings',           [$cc,'coinSettings'])->name('coins.settings');
            Route::post('/coins/{id}',                   [$cc,'updateCoin'])->name('coins.update');
            Route::post('/coins/{id}/price',             [$cc,'updatePrice'])->name('coins.price');
            Route::post('/prices/refresh',               [$cc,'refreshPrices'])->name('prices.refresh');
            // Orders
            Route::get('/orders',                        [$cc,'orders'])->name('orders');
            Route::patch('/orders/{id}/complete',        [$cc,'completeOrder'])->name('orders.complete');
            Route::patch('/orders/{id}/reject',          [$cc,'rejectOrder'])->name('orders.reject');
            // Deposits
            Route::get('/deposits',                      [$cc,'deposits'])->name('deposits');
            Route::patch('/deposits/{id}/approve',       [$cc,'approveDeposit'])->name('deposits.approve');
            Route::patch('/deposits/{id}/reject',        [$cc,'rejectDeposit'])->name('deposits.reject');
            // Withdrawals
            Route::get('/withdrawals',                   [$cc,'withdrawals'])->name('withdrawals');
            Route::patch('/withdrawals/{id}/approve',    [$cc,'approveWithdrawal'])->name('withdrawals.approve');
            Route::patch('/withdrawals/{id}/process',    [$cc,'processWithdrawal'])->name('withdrawals.process');
            Route::patch('/withdrawals/{id}/reject',     [$cc,'rejectWithdrawal'])->name('withdrawals.reject');
            // P2P
            Route::get('/p2p',                           [$cc,'p2p'])->name('p2p');
            Route::patch('/p2p/{orderId}/resolve',       [$cc,'p2pResolve'])->name('p2p.resolve');
            Route::patch('/p2p/{orderId}/release',       [$cc,'escrowRelease'])->name('p2p.release');
            Route::patch('/p2p/{orderId}/refund',        [$cc,'escrowRefund'])->name('p2p.refund');
            Route::patch('/p2p/ads/{id}/disable',        [$cc,'disableAd'])->name('p2p.disableAd');
            Route::get('/settings',                      [$cc,'settings'])->name('settings');
            Route::patch('/settings',                    [$cc,'updateSettings'])->name('settings.update');
        });

        // eExchange Orders (local currency swap — existing)
        Route::prefix('exchange')->name('exchange.')->group(function () {
            // Redirect old standalone orders page → unified management page
            Route::get('/', fn() => redirect()->route('admin.module-data.exchange'))->name('index');
            Route::post('/bulk-delete',               [AdminExchangeController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::get('/users',                      [AdminExchangeController::class, 'users'])->name('users');
            Route::get('/users/{userId}/accounts',    [AdminExchangeController::class, 'userAccounts'])->name('user-accounts');
            Route::delete('/accounts/{id}',           [AdminExchangeController::class, 'deleteAccount'])->name('account.destroy');
            Route::patch('/{id}/status',              [AdminExchangeController::class, 'updateStatus'])->name('update-status');
            Route::get('/{id}',                       [AdminExchangeController::class, 'show'])->name('show');
            Route::delete('/{id}',                    [AdminExchangeController::class, 'destroy'])->name('destroy');
        });

        // Dispatch Center
        // SOS Emergency
        Route::get('/sos', [SosController::class, 'index'])->name('sos.index');
        Route::get('/sos/live', [SosController::class, 'liveAlerts'])->name('sos.live');
        Route::get('/sos/{id}', [SosController::class, 'show'])->name('sos.show');
        Route::post('/sos/{id}/resolve', [SosController::class, 'resolve'])->name('sos.resolve');

        // Driver Performance
        Route::get('/drivers/performance', [DriverPerformanceController::class, 'index'])->name('drivers.performance');
        Route::get('/drivers/performance/data', [DriverPerformanceController::class, 'data'])->name('drivers.performance.data');

        Route::get('/dispatch', [DispatchController::class, 'index'])->name('dispatch');
        Route::get('/dispatch/map', [DispatchController::class, 'liveMap'])->name('dispatch.map');
        Route::get('/dispatch/live-drivers', [DispatchController::class, 'liveDrivers'])->name('dispatch.live-drivers');
        Route::post('/dispatch/drivers/{id}/request-location', [DispatchController::class, 'requestLocation'])->name('dispatch.request-location');
        Route::get('/dispatch/orders', [DispatchController::class, 'activeOrders'])->name('dispatch.orders');
        Route::post('/dispatch/assign', [DispatchController::class, 'manualAssign'])->name('dispatch.assign');
        Route::get('/dispatch/deliverymen/available', [DispatchController::class, 'availableDeliverymen'])->name('dispatch.deliverymen');

        // Email Templates
        Route::prefix('email-templates')->name('email-templates.')->group(function () {
            Route::get('/', [AdminEmailTemplateController::class, 'index'])->name('index');
            Route::get('/{emailTemplate}/edit', [AdminEmailTemplateController::class, 'edit'])->name('edit');
            Route::put('/{emailTemplate}', [AdminEmailTemplateController::class, 'update'])->name('update');
            Route::get('/{emailTemplate}/preview', [AdminEmailTemplateController::class, 'preview'])->name('preview');
            Route::post('/{emailTemplate}/send-test', [AdminEmailTemplateController::class, 'sendTest'])->name('send-test');
        });

        // ─── eLearning ────────────────────────────────────────────────────────
        Route::prefix('elearning')->name('elearning.')->group(function () {
            Route::get('/', [AdminELearningController::class, 'dashboard'])->name('dashboard');
            Route::get('instructors', [AdminELearningController::class, 'instructors'])->name('instructors');
            Route::get('instructors/{id}', [AdminELearningController::class, 'instructorDetail'])->name('instructors.show');
            Route::post('instructors/{id}/approve', [AdminELearningController::class, 'approveInstructor'])->name('instructors.approve');
            Route::post('instructors/{id}/reject', [AdminELearningController::class, 'rejectInstructor'])->name('instructors.reject');
            Route::get('courses', [AdminELearningController::class, 'courses'])->name('courses');
            Route::get('courses/{id}', [AdminELearningController::class, 'courseDetail'])->name('courses.show');
            Route::post('courses/{id}/approve', [AdminELearningController::class, 'approveCourse'])->name('courses.approve');
            Route::post('courses/{id}/reject', [AdminELearningController::class, 'rejectCourse'])->name('courses.reject');
            Route::delete('courses/{id}', [AdminELearningController::class, 'deleteCourse'])->name('courses.destroy');
            Route::get('categories', [AdminELearningController::class, 'categories'])->name('categories');
            Route::post('categories', [AdminELearningController::class, 'storeCategory'])->name('categories.store');
            Route::put('categories/{id}', [AdminELearningController::class, 'updateCategory'])->name('categories.update');
            Route::delete('categories/{id}', [AdminELearningController::class, 'destroyCategory'])->name('categories.destroy');
            Route::get('students', [AdminELearningController::class, 'students'])->name('students');
            Route::get('certificates', [AdminELearningController::class, 'certificates'])->name('certificates');
            Route::delete('certificates/{id}', [AdminELearningController::class, 'revokeCertificate'])->name('certificates.revoke');
            Route::get('withdrawals', [AdminELearningController::class, 'withdrawals'])->name('withdrawals');
            Route::post('withdrawals/{id}/approve', [AdminELearningController::class, 'approveWithdrawal'])->name('withdrawals.approve');
            Route::post('withdrawals/{id}/reject', [AdminELearningController::class, 'rejectWithdrawal'])->name('withdrawals.reject');
            Route::get('reviews', [AdminELearningController::class, 'reviews'])->name('reviews');
            Route::delete('reviews/{id}', [AdminELearningController::class, 'deleteReview'])->name('reviews.destroy');
            Route::get('settings', [AdminELearningController::class, 'settings'])->name('settings');
            Route::put('settings', [AdminELearningController::class, 'updateSettings'])->name('settings.update');
            Route::get('reports', [AdminELearningController::class, 'reports'])->name('reports');
        });

        // ─── Global eCommerce ─────────────────────────────────────────────────────
        Route::prefix('global')->name('global.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\Global\AdminGlobalDashboardController::class, 'index'])->name('dashboard');

            Route::prefix('categories')->name('categories.')->group(function () {
                Route::get('/',                [\App\Http\Controllers\Admin\Global\AdminGlobalCategoriesController::class, 'index'])->name('index');
                Route::post('/',               [\App\Http\Controllers\Admin\Global\AdminGlobalCategoriesController::class, 'store'])->name('store');
                Route::put('/{category}',      [\App\Http\Controllers\Admin\Global\AdminGlobalCategoriesController::class, 'update'])->name('update');
                Route::delete('/{category}',   [\App\Http\Controllers\Admin\Global\AdminGlobalCategoriesController::class, 'destroy'])->name('destroy');
                Route::patch('/{category}/toggle', [\App\Http\Controllers\Admin\Global\AdminGlobalCategoriesController::class, 'toggle'])->name('toggle');
            });

            Route::prefix('products')->name('products.')->group(function () {
                Route::get('/',               [\App\Http\Controllers\Admin\Global\AdminGlobalProductsController::class, 'index'])->name('index');
                Route::get('/create',         [\App\Http\Controllers\Admin\Global\AdminGlobalProductsController::class, 'create'])->name('create');
                Route::post('/',              [\App\Http\Controllers\Admin\Global\AdminGlobalProductsController::class, 'store'])->name('store');
                Route::get('/{product}/edit', [\App\Http\Controllers\Admin\Global\AdminGlobalProductsController::class, 'edit'])->name('edit');
                Route::put('/{product}',      [\App\Http\Controllers\Admin\Global\AdminGlobalProductsController::class, 'update'])->name('update');
                Route::delete('/{product}',   [\App\Http\Controllers\Admin\Global\AdminGlobalProductsController::class, 'destroy'])->name('destroy');
                Route::post('/{product}/toggle', [\App\Http\Controllers\Admin\Global\AdminGlobalProductsController::class, 'toggleActive'])->name('toggle');
            });

            Route::prefix('orders')->name('orders.')->group(function () {
                Route::get('/',               [\App\Http\Controllers\Admin\Global\AdminGlobalOrdersController::class, 'index'])->name('index');
                Route::post('/bulk',          [\App\Http\Controllers\Admin\Global\AdminGlobalOrdersController::class, 'bulk'])->name('bulk');
                Route::get('/{order}',        [\App\Http\Controllers\Admin\Global\AdminGlobalOrdersController::class, 'show'])->name('show');
                Route::put('/{order}/status', [\App\Http\Controllers\Admin\Global\AdminGlobalOrdersController::class, 'updateStatus'])->name('status');
                Route::post('/{order}/refund',[\App\Http\Controllers\Admin\Global\AdminGlobalOrdersController::class, 'refund'])->name('refund');
                Route::delete('/{order}',     [\App\Http\Controllers\Admin\Global\AdminGlobalOrdersController::class, 'destroy'])->name('destroy');
            });

            // Coupons
            Route::prefix('coupons')->name('coupons.')->group(function () {
                $cc = \App\Http\Controllers\Admin\Global\AdminGlobalCouponsController::class;
                Route::get('/',                 [$cc, 'index'])->name('index');
                Route::post('/',                [$cc, 'store'])->name('store');
                Route::put('/{coupon}',         [$cc, 'update'])->name('update');
                Route::delete('/{coupon}',      [$cc, 'destroy'])->name('destroy');
                Route::patch('/{coupon}/toggle',[$cc, 'toggle'])->name('toggle');
            });

            Route::get('/settings',                   [\App\Http\Controllers\Admin\Global\AdminGlobalSettingsController::class, 'index'])->name('settings');
            Route::put('/settings',                   [\App\Http\Controllers\Admin\Global\AdminGlobalSettingsController::class, 'update'])->name('settings.update');
            Route::post('/settings/toggle-store',     [\App\Http\Controllers\Admin\Global\AdminGlobalSettingsController::class, 'toggleStore'])->name('settings.toggle-store');
            Route::post('/settings/toggle-crypto',    [\App\Http\Controllers\Admin\Global\AdminGlobalSettingsController::class, 'toggleCrypto'])->name('settings.toggle-crypto');
            Route::put('/settings/shipping/{zone}',   [\App\Http\Controllers\Admin\Global\AdminGlobalSettingsController::class, 'updateShippingZone'])->name('settings.shipping.update');

            // Inventory
            Route::prefix('inventory')->name('inventory.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\Global\AdminGlobalInventoryController::class, 'index'])->name('index');
                Route::post('/bulk-update', [\App\Http\Controllers\Admin\Global\AdminGlobalInventoryController::class, 'bulkUpdate'])->name('bulk-update');
                Route::post('/{product}/adjust', [\App\Http\Controllers\Admin\Global\AdminGlobalInventoryController::class, 'adjust'])->name('adjust');
            });

            // Currency
            Route::prefix('currency')->name('currency.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\Global\AdminGlobalCurrencyController::class, 'index'])->name('index');
                Route::post('/update-rate', [\App\Http\Controllers\Admin\Global\AdminGlobalCurrencyController::class, 'updateRate'])->name('update-rate');
                Route::post('/sync-rates', [\App\Http\Controllers\Admin\Global\AdminGlobalCurrencyController::class, 'syncRates'])->name('sync-rates');
            });

            // Analytics
            Route::get('/analytics', [\App\Http\Controllers\Admin\Global\AdminGlobalAnalyticsController::class, 'index'])->name('analytics');

            // Revenue
            Route::prefix('revenue')->name('revenue.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\Global\AdminGlobalRevenueController::class, 'index'])->name('index');
                Route::get('/export', [\App\Http\Controllers\Admin\Global\AdminGlobalRevenueController::class, 'export'])->name('export');
            });

            // Refunds
            Route::prefix('refunds')->name('refunds.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\Global\AdminGlobalRefundsController::class, 'index'])->name('index');
                Route::post('/{payment}/process', [\App\Http\Controllers\Admin\Global\AdminGlobalRefundsController::class, 'process'])->name('process');
            });

            // Help Center / Support Tickets
            Route::prefix('help-center')->name('help-center.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\Global\AdminGlobalHelpCenterController::class, 'index'])->name('index');
                Route::get('/{ticket}', [\App\Http\Controllers\Admin\Global\AdminGlobalHelpCenterController::class, 'show'])->name('show');
                Route::post('/{ticket}/reply', [\App\Http\Controllers\Admin\Global\AdminGlobalHelpCenterController::class, 'reply'])->name('reply');
                Route::post('/{ticket}/close', [\App\Http\Controllers\Admin\Global\AdminGlobalHelpCenterController::class, 'close'])->name('close');
            });

            // Live Chat (LiveKit)
            Route::prefix('live-chat')->name('live-chat.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\Global\AdminGlobalLiveChatController::class, 'index'])->name('index');
                Route::post('/join', [\App\Http\Controllers\Admin\Global\AdminGlobalLiveChatController::class, 'joinRoom'])->name('join');
            });

            // Push Notifications
            Route::prefix('push-notifications')->name('push-notifications.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\Global\AdminGlobalPushNotificationsController::class, 'index'])->name('index');
                Route::post('/send', [\App\Http\Controllers\Admin\Global\AdminGlobalPushNotificationsController::class, 'send'])->name('send');
            });

            // Shipping
            Route::prefix('shipping')->name('shipping.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\Global\AdminGlobalShippingController::class, 'index'])->name('index');
                Route::post('/zones', [\App\Http\Controllers\Admin\Global\AdminGlobalShippingController::class, 'storeZone'])->name('zones.store');
                Route::put('/zones/{zone}', [\App\Http\Controllers\Admin\Global\AdminGlobalShippingController::class, 'updateZone'])->name('zones.update');
                Route::delete('/zones/{zone}', [\App\Http\Controllers\Admin\Global\AdminGlobalShippingController::class, 'destroyZone'])->name('zones.destroy');
            });

            // Dropshipping
            Route::prefix('dropshipping')->name('dropshipping.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\Global\AdminGlobalDropshippingController::class, 'index'])->name('index');
                Route::post('/import', [\App\Http\Controllers\Admin\Global\AdminGlobalDropshippingController::class, 'import'])->name('import');
                Route::post('/{product}/sync', [\App\Http\Controllers\Admin\Global\AdminGlobalDropshippingController::class, 'sync'])->name('sync');
            });

            // Physical Products
            Route::prefix('physical-products')->name('physical-products.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\Global\AdminGlobalPhysicalProductsController::class, 'index'])->name('index');
                Route::post('/{product}/restock', [\App\Http\Controllers\Admin\Global\AdminGlobalPhysicalProductsController::class, 'restock'])->name('restock');
            });

            // Security
            Route::prefix('security')->name('security.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\Global\AdminGlobalSecurityController::class, 'index'])->name('index');
                Route::post('/block-user/{user}', [\App\Http\Controllers\Admin\Global\AdminGlobalSecurityController::class, 'blockUser'])->name('block-user');
                Route::post('/unblock-user/{user}', [\App\Http\Controllers\Admin\Global\AdminGlobalSecurityController::class, 'unblockUser'])->name('unblock-user');
                Route::post('/block-ip', [\App\Http\Controllers\Admin\Global\AdminGlobalSecurityController::class, 'blockIp'])->name('block-ip');
            });

            // Global Users
            Route::prefix('users')->name('users.')->group(function () {
                Route::get('/',                        [\App\Http\Controllers\Admin\Global\AdminGlobalUsersController::class, 'index'])->name('index');
                Route::post('/bulk',                   [\App\Http\Controllers\Admin\Global\AdminGlobalUsersController::class, 'bulk'])->name('bulk');
                Route::get('/{user}',                  [\App\Http\Controllers\Admin\Global\AdminGlobalUsersController::class, 'show'])->name('show');
                Route::post('/{user}/ban',             [\App\Http\Controllers\Admin\Global\AdminGlobalUsersController::class, 'ban'])->name('ban');
                Route::post('/{user}/unban',           [\App\Http\Controllers\Admin\Global\AdminGlobalUsersController::class, 'unban'])->name('unban');
                Route::delete('/{user}',               [\App\Http\Controllers\Admin\Global\AdminGlobalUsersController::class, 'destroy'])->name('destroy');
                Route::post('/{user}/notify',          [\App\Http\Controllers\Admin\Global\AdminGlobalUsersController::class, 'sendNotification'])->name('notify');
            });

            // Global Sliders
            Route::prefix('sliders')->name('sliders.')->group(function () {
                Route::get('/',               [\App\Http\Controllers\Admin\Global\AdminGlobalSlidersController::class, 'index'])->name('index');
                Route::post('/',              [\App\Http\Controllers\Admin\Global\AdminGlobalSlidersController::class, 'store'])->name('store');
                Route::put('/{slider}',       [\App\Http\Controllers\Admin\Global\AdminGlobalSlidersController::class, 'update'])->name('update');
                Route::delete('/{slider}',    [\App\Http\Controllers\Admin\Global\AdminGlobalSlidersController::class, 'destroy'])->name('destroy');
                Route::post('/reorder',       [\App\Http\Controllers\Admin\Global\AdminGlobalSlidersController::class, 'reorder'])->name('reorder');
            });
        });

        // ─── HR Oversight (read-only) ─────────────────────────────────────────────
        Route::prefix('hr')->name('hr.')->group(function () {
            Route::get('/',               [\App\Http\Controllers\Admin\AdminHrController::class, 'dashboard'])->name('dashboard');
            Route::get('attendance',          [\App\Http\Controllers\Admin\AdminHrController::class, 'attendance'])->name('attendance');
            Route::get('leaves',              [\App\Http\Controllers\Admin\AdminHrController::class, 'leaves'])->name('leaves');
            Route::get('payroll',             [\App\Http\Controllers\Admin\AdminHrController::class, 'payroll'])->name('payroll');
            Route::get('payroll/{payslip}',   [\App\Http\Controllers\Admin\AdminHrController::class, 'payslip'])->name('payslip');
            Route::get('recruitment',         [\App\Http\Controllers\Admin\AdminHrController::class, 'recruitment'])->name('recruitment');
            Route::get('performance',         [\App\Http\Controllers\Admin\AdminHrController::class, 'performance'])->name('performance');
            Route::get('discipline',          [\App\Http\Controllers\Admin\AdminHrController::class, 'discipline'])->name('discipline');
            Route::get('audit',               [\App\Http\Controllers\Admin\AdminHrController::class, 'audit'])->name('audit');

            // Admin override actions (super_admin only)
            Route::post('payroll/{payroll}/unlock',    [\App\Http\Controllers\Admin\AdminHrController::class, 'unlockPayroll'])->name('payroll.unlock');
            Route::post('employees/{employee}/reactivate', [\App\Http\Controllers\Admin\AdminHrController::class, 'reactivateEmployee'])->name('employees.reactivate');
            Route::post('discipline/{case}/force-close', [\App\Http\Controllers\Admin\AdminHrController::class, 'forceCloseCase'])->name('discipline.force_close');
        });

        // ─── Legal Pages ──────────────────────────────────────────────────────────
        Route::prefix('legal-pages')->name('legal-pages.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminLegalPagesController::class, 'index'])->name('index');
            Route::get('/{page}/edit', [\App\Http\Controllers\Admin\AdminLegalPagesController::class, 'edit'])->name('edit');
            Route::put('/{page}', [\App\Http\Controllers\Admin\AdminLegalPagesController::class, 'update'])->name('update');
        });
    });
});

// ─── Public Careers ──────────────────────────────────────────────────────────
Route::middleware(['throttle:30,1'])->prefix('careers')->name('careers.')->group(function () {
    Route::get('/',              [\App\Http\Controllers\CareersController::class, 'index'])->name('index');
    Route::get('/{posting}',     [\App\Http\Controllers\CareersController::class, 'show'])->name('show');
    Route::post('/{posting}/apply', [\App\Http\Controllers\CareersController::class, 'apply'])->name('apply');
});

// ─── Public Legal Pages ───────────────────────────────────────────────────────
Route::get('/privacy-policy', [\App\Http\Controllers\LegalPageWebController::class, 'show'])->defaults('slug', 'privacy-policy');
Route::get('/terms',          [\App\Http\Controllers\LegalPageWebController::class, 'show'])->defaults('slug', 'terms');
Route::get('/about',          [\App\Http\Controllers\LegalPageWebController::class, 'show'])->defaults('slug', 'about');

// ─── Global Store SEO Pages (global.esahlan.com) ──────────────────────────────
Route::prefix('seo/global')->name('global.seo.')->group(function () {
    Route::get('/product/{id}', [\App\Http\Controllers\Api\Global\GlobalSeoController::class, 'product'])->name('product');
    Route::get('/products',     [\App\Http\Controllers\Api\Global\GlobalSeoController::class, 'products'])->name('products');
});
Route::get('/sitemap-global.xml', [\App\Http\Controllers\Api\Global\GlobalSeoController::class, 'sitemap'])->name('global.sitemap');
