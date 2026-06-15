<?php
use App\Http\Controllers\Api\Community\CommunityFeedController;
use App\Http\Controllers\Api\Community\CommunityPostController;
use App\Http\Controllers\Api\Community\CommunityCommentController;
use App\Http\Controllers\Api\Community\CommunityProfileController;
use App\Http\Controllers\Api\Community\CommunityFollowController;
use App\Http\Controllers\Api\Community\CommunityStoryController;
use App\Http\Controllers\Api\Community\CommunityGroupController;
use App\Http\Controllers\Api\Community\CommunityChatController;
use App\Http\Controllers\Api\Community\CommunityNotificationController;
use App\Http\Controllers\Api\Community\CommunityReportController;


use Illuminate\Support\Facades\Route;

// ─── Auth ───────────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\Auth\AuthController;

// ─── Customer ────────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\Customer\HomeController;
use App\Http\Controllers\Api\Customer\OrderController;
use App\Http\Controllers\Api\Customer\WalletController;
use App\Http\Controllers\Api\Payment\PaymentController;
use App\Http\Controllers\Api\Customer\VendorController;
use App\Http\Controllers\Api\Customer\CartController;
use App\Http\Controllers\Api\Customer\AddressController;
use App\Http\Controllers\Api\Customer\NotificationController;
use App\Http\Controllers\Api\Customer\WishlistController;
use App\Http\Controllers\Api\Customer\ChatController;

// ─── Delivery ────────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\Delivery\DeliveryController;

// ─── Vendor ──────────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\Vendor\VendorDashboardController;
use App\Http\Controllers\Api\Vendor\VendorOrderController;
use App\Http\Controllers\Api\Vendor\VendorProductController;
use App\Http\Controllers\Api\Vendor\VendorStoreController;
use App\Http\Controllers\Api\Vendor\VendorWalletController;

// ─── Modules ─────────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\Modules\EDataController;
use App\Http\Controllers\Api\Modules\EParcelController;
use App\Http\Controllers\Api\Modules\EExchangeController;
use App\Http\Controllers\Api\Modules\EMovingController;
use App\Http\Controllers\Api\Modules\EFoodController;
use App\Http\Controllers\Api\Modules\ELaundryController;
use App\Http\Controllers\Api\Modules\EHealthController;
use App\Http\Controllers\Api\Modules\ERentController;
use App\Http\Controllers\Api\Modules\EShopController;
use App\Http\Controllers\Api\Modules\EWholesaleController;
use App\Http\Controllers\Api\Modules\EGroceryController;
use App\Http\Controllers\Api\Modules\ETicketController;

// ─── Image proxy — serves storage files with CORS headers ────────────────────
// Flutter Web (CanvasKit) needs CORS on images; static storage files bypass
// Laravel middleware. This route proxies them through the API (which has CORS).
// OPTIONS preflight for img/* (required by Flutter Web CanvasKit renderer)
Route::options('img/{path}', function () {
    return response('', 200, [
        'Access-Control-Allow-Origin'  => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Origin, Accept, Content-Type, Range',
        'Access-Control-Max-Age'       => '86400',
    ]);
})->where('path', '.*');

// Keep old path for backwards compat, but the v1 path (below) is canonical.
Route::get('img/{path}', function (\Illuminate\Http\Request $request, string $path) {
    $realPath = storage_path('app/public/' . $path);
    if (!file_exists($realPath) || is_dir($realPath)) {
        abort(404);
    }
    $ext  = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png'         => 'image/png',
        'gif'         => 'image/gif',
        'webp'        => 'image/webp',
        'svg'         => 'image/svg+xml',
        'mp4'         => 'video/mp4',
        'webm'        => 'video/webm',
        'mov'         => 'video/quicktime',
        'ogg'         => 'video/ogg',
        default       => 'application/octet-stream',
    };

    $headers = [
        'Content-Type'                 => $mime,
        'Access-Control-Allow-Origin'  => '*',
        'Access-Control-Allow-Headers' => 'Range, Content-Type',
        'Access-Control-Expose-Headers'=> 'Content-Range, Accept-Ranges, Content-Length',
        'Accept-Ranges'                => 'bytes',
        'Cache-Control'                => 'no-cache, private',
    ];

    // response()->file() handles Range/206 automatically in Laravel
    return response()->file($realPath, $headers);
})->where('path', '.*');

Route::prefix('v1')->group(function () {

    // Image proxy under v1 prefix — CDN treats /api/v1/* as DYNAMIC (no caching),
    // so CORS headers pass through. The /api/img/ path above gets cached and strips them.
    Route::get('img/{path}', function (\Illuminate\Http\Request $request, string $path) {
        $realPath = storage_path('app/public/' . $path);
        if (!file_exists($realPath) || is_dir($realPath)) {
            abort(404);
        }
        $ext  = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        $mime = match($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            'mp4'         => 'video/mp4',
            'webm'        => 'video/webm',
            'mov'         => 'video/quicktime',
            'ogg'         => 'video/ogg',
            default       => 'application/octet-stream',
        };
        $headers = [
            'Content-Type'                 => $mime,
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Headers' => 'Range, Content-Type',
            'Access-Control-Expose-Headers'=> 'Content-Range, Accept-Ranges, Content-Length',
            'Accept-Ranges'                => 'bytes',
            'Cache-Control'                => 'no-cache, private',
        ];
        return response()->file($realPath, $headers);
    })->where('path', '.*');

    // ═══════════════════════════════════════════════════════════════
    // PUBLIC ROUTES
    // ═══════════════════════════════════════════════════════════════

    // App config (public — non-sensitive keys for Flutter app)
    Route::get('app-config', function () {
        return response()->json(['success' => true, 'data' => [
            'google_maps_api_key'    => \App\Helpers\AppSettings::get('google_maps_api_key', ''),
            'google_maps_default_lat'=> (float)\App\Helpers\AppSettings::get('google_maps_default_lat', 2.0469),
            'google_maps_default_lng'=> (float)\App\Helpers\AppSettings::get('google_maps_default_lng', 45.3182),
            'google_maps_default_zoom'=> (int)\App\Helpers\AppSettings::get('google_maps_default_zoom', 13),
            'fcm_enabled'            => (bool)\App\Helpers\AppSettings::get('fcm_enabled', true),
            'firebase_project_id'    => \App\Helpers\AppSettings::get('firebase_project_id', ''),
            'currency_symbol'        => \App\Helpers\AppSettings::get('app_currency_symbol', '$'),
            'app_name'               => \App\Helpers\AppSettings::get('app_name', 'eSahlan'),
        ]]);
    });

    Route::prefix('auth')->group(function () {
        Route::post('register',     [AuthController::class, 'register']);
        Route::post('send-otp',     [AuthController::class, 'sendOtp']);
        Route::post('verify-otp',   [AuthController::class, 'verifyOtp']);
        Route::post('login',        [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password',  [AuthController::class, 'resetPassword']);
    });

    // Public info
    Route::get('modules',               [HomeController::class, 'modules']);
    Route::get('modules/{slug}',        [HomeController::class, 'moduleDetails']);
    Route::get('districts',             [HomeController::class, 'districts']);
    Route::get('banners',               [HomeController::class, 'banners']);   // public banner endpoint — filter by ?position=home_top
    Route::get('vendors',               [VendorController::class, 'index']);
    Route::get('vendors/{vendor}',      [VendorController::class, 'show']);
    Route::get('vendors/{vendor}/products',  [VendorController::class, 'products']);
    Route::get('vendors/{vendor}/reviews',   [VendorController::class, 'reviews']);

    // ── Module public endpoints ───────────────────────────────────────────────
    // eFood
    Route::get('efood/banners',                          [EFoodController::class, 'banners']);
    Route::get('efood/categories',                       [EFoodController::class, 'foodCategories']);
    Route::get('efood/restaurants',                      [EFoodController::class, 'restaurants']);
    Route::get('efood/restaurants/{id}',                 [EFoodController::class, 'restaurant']);
    Route::get('efood/restaurants/{id}/products',        [EFoodController::class, 'products']);
    Route::get('efood/restaurants/{restaurantId}/menu',  [EFoodController::class, 'products']);   // alias Flutter uses
    Route::get('efood/items/{id}',                       [EFoodController::class, 'getItem']);
    Route::get('efood/restaurants/{id}/coupons',           [EFoodController::class, 'restaurantCoupons']);
    Route::get('efood/restaurants/{id}/campaigns',         [EFoodController::class, 'restaurantCampaigns']);
    // Order placement — kept public so guest browsing works; wallet auth enforced in controller
    // Orders
    Route::get('efood/orders',                             [EFoodController::class, 'orders']);
    Route::get('efood/orders/{id}',                        [EFoodController::class, 'order']);
    Route::get('efood/orders/{id}/track',                  [EFoodController::class, 'trackOrder']);
    // Favorites
    Route::get('efood/favorites',                          [EFoodController::class, 'favorites']);
    Route::get('efood/favorites/ids',                      [EFoodController::class, 'favoriteIds']);
    Route::post('efood/favorites/toggle',                  [EFoodController::class, 'toggleFavorite']);

    // eData
    Route::get('edata/providers',                        [EDataController::class, 'providers']);
    Route::get('edata/all',                              [EDataController::class, 'all']);
    Route::get('edata/history',                          [EDataController::class, 'purchaseHistory']); // Flutter eData tab
    Route::get('edata/favorites',                        [EDataController::class, 'favorites']);
    Route::post('edata/favorites/toggle',                [EDataController::class, 'toggleFavorite']);
    Route::get('edata/providers/{id}/packages',          [EDataController::class, 'packages']);
    Route::get('edata/providers/{id}/bundles',           [EDataController::class, 'bundles']);
    Route::get('edata/packages/{id}/bundles',            [EDataController::class, 'packageBundles']);

    // eExchange
    Route::get('eexchange/rates',                        [EExchangeController::class, 'rates']);
    Route::post('eexchange/calculate',                   [EExchangeController::class, 'calculate']);
    Route::post('eexchange/preview',                     [EExchangeController::class, 'calculate']);   // alias used by Flutter


    // eParcel
    Route::get('eparcel/types',                          [EParcelController::class, 'types']);
    Route::get('eparcel/districts',                      [EParcelController::class, 'districts']);
    Route::get('eparcel/zones',                          [EParcelController::class, 'zones']);
    Route::post('eparcel/calculate',                     [EParcelController::class, 'calculate']);

    // eMoving
    Route::get('emoving/move-types',                     [EMovingController::class, 'moveTypes']);
    Route::get('emoving/districts',                      [EMovingController::class, 'districts']);
    Route::get('emoving/extra-services',                 [EMovingController::class, 'extraServices']);
    Route::get('emoving/packages/{type}',                [EMovingController::class, 'packages']);
    Route::post('emoving/calculate',                     [EMovingController::class, 'calculate']);

    // eLaundry
    Route::get('elaundry/items',                         [ELaundryController::class, 'items']);
    Route::post('elaundry/estimate',                     [ELaundryController::class, 'estimate']);

    // eHealth
    Route::get('ehealth/categories',                     [EHealthController::class, 'categories']);
    Route::get('ehealth/doctors',                        [EHealthController::class, 'doctors']);
    Route::get('ehealth/doctors/{id}',                   [EHealthController::class, 'doctor']);

    // eRent
    Route::get('erent/districts',                        [ERentController::class, 'districts']);
    Route::get('erent/properties',                       [ERentController::class, 'properties']);
    Route::get('erent/properties/{id}',                  [ERentController::class, 'property']);
    Route::get('erent/reels',                            [ERentController::class, 'reels']);

    // eShop
    Route::get('eshop/home',                             [EShopController::class, 'home']);
    Route::get('eshop/banners',                          [EShopController::class, 'banners']);
    Route::get('eshop/categories',                       [EShopController::class, 'categories']);
    Route::get('eshop/products',                         [EShopController::class, 'products']);
    Route::get('eshop/products/{id}',                    [EShopController::class, 'product']);
    Route::get('eshop/flash-deals',                      [EShopController::class, 'flashDeals']);
    Route::get('eshop/deals-of-day',                     [EShopController::class, 'dealsOfDay']);
    Route::get('eshop/campaigns',                        [EShopController::class, 'campaigns']);

    // eWholesale
    Route::get('ewholesale/categories',                  [EWholesaleController::class, 'categories']);
    Route::get('ewholesale/products',                    [EWholesaleController::class, 'products']);
    Route::post('ewholesale/inquire',                    [EWholesaleController::class, 'inquire']);    // alias Flutter uses

    // eGrocery
    Route::get('egrocery/categories',                    [EGroceryController::class, 'categories']);
    Route::get('egrocery/products',                      [EGroceryController::class, 'products']);

    // eTicket
    Route::get('eticket/airlines',                       [ETicketController::class, 'airlines']);
    Route::get('eticket/routes',                         [ETicketController::class, 'routes']);
    Route::get('eticket/cities',                         [ETicketController::class, 'cities']);
    Route::get('eticket/search',                         [ETicketController::class, 'search']);
    Route::get('eticket/active-dates',                   [ETicketController::class, 'activeDates']);
    Route::get('eticket/home',                           [ETicketController::class, 'home']);
    Route::get('eticket/flights/{id}',                   [ETicketController::class, 'flightDetail']);

    // ═══════════════════════════════════════════════════════════════
    // AUTHENTICATED — ALL ROLES
    // ═══════════════════════════════════════════════════════════════
    Route::middleware('auth:sanctum')->group(function () {

    // ── Community Module ──────────────────────────────────────────────────────
    Route::prefix('community')->group(function () {
        // Feed
        Route::get('feed', [CommunityFeedController::class, 'following']);
        Route::get('explore', [CommunityFeedController::class, 'explore']);
        Route::get('reels', [CommunityFeedController::class, 'reels']);
        Route::get('stories', [CommunityFeedController::class, 'stories']);
        Route::get('trending', [CommunityFeedController::class, 'trending']);
        Route::get('search', [CommunityFeedController::class, 'search']);
        Route::get('suggestions', [CommunityFeedController::class, 'suggestions']);

        // Posts
        Route::get('posts/saved', [CommunityPostController::class, 'saved']);
        Route::apiResource('posts', CommunityPostController::class)->except(['index']);
        Route::post('posts/{id}/react', [CommunityPostController::class, 'react']);
        Route::post('posts/{id}/share', [CommunityPostController::class, 'share']);
        Route::post('posts/{id}/save', [CommunityPostController::class, 'save']);
        Route::post('posts/{id}/vote', [CommunityPostController::class, 'votePoll']);

        // Comments
        Route::get('posts/{postId}/comments', [CommunityCommentController::class, 'index']);
        Route::post('posts/{postId}/comments', [CommunityCommentController::class, 'store']);
        Route::put('comments/{id}', [CommunityCommentController::class, 'update']);
        Route::delete('comments/{id}', [CommunityCommentController::class, 'destroy']);
        Route::post('comments/{id}/react', [CommunityCommentController::class, 'react']);

        // Profile
        Route::get('profile/me', fn() => app(CommunityProfileController::class)->show(auth()->id()));
        Route::post('profile/avatar', [CommunityProfileController::class, 'updateAvatar']);
        Route::put('profile', [CommunityProfileController::class, 'update']);
        Route::get('profile/{userId}', [CommunityProfileController::class, 'show']);
        Route::get('profile/{userId}/posts', [CommunityProfileController::class, 'posts']);
        Route::get('profile/{userId}/followers', [CommunityProfileController::class, 'followers']);
        Route::get('profile/{userId}/following', [CommunityProfileController::class, 'following']);

        // Follow
        Route::post('follow/{userId}', [CommunityFollowController::class, 'toggle']);

        // Stories
        Route::post('stories', [CommunityStoryController::class, 'store']);
        Route::post('stories/{id}/view', [CommunityStoryController::class, 'view']);
        Route::get('stories/{id}/viewers', [CommunityStoryController::class, 'viewers']);
        Route::post('stories/{id}/react', [CommunityStoryController::class, 'react']);
        Route::post('stories/{id}/comment', [CommunityStoryController::class, 'comment']);
        Route::delete('stories/{id}', [CommunityStoryController::class, 'destroy']);

        // Groups
        Route::get('groups', [CommunityGroupController::class, 'index']);
        Route::post('groups', [CommunityGroupController::class, 'store']);
        Route::get('groups/{id}', [CommunityGroupController::class, 'show']);
        Route::post('groups/{id}/join', [CommunityGroupController::class, 'join']);
        Route::delete('groups/{id}/leave', [CommunityGroupController::class, 'leave']);
        Route::get('groups/{id}/posts', [CommunityGroupController::class, 'posts']);

        // Chats
        Route::get('chats', [CommunityChatController::class, 'index']);
        Route::post('chats/start/{userId}', [CommunityChatController::class, 'startOrGet']);
        Route::get('chats/{chatId}/messages', [CommunityChatController::class, 'messages']);
        Route::post('chats/{chatId}/messages', [CommunityChatController::class, 'send']);
        Route::delete('messages/{msgId}', [CommunityChatController::class, 'deleteMessage']);

        // Notifications
        Route::get('notifications', [CommunityNotificationController::class, 'index']);
        Route::get('notifications/unread-count', [CommunityNotificationController::class, 'unreadCount']);
        Route::post('notifications/{id}/read', [CommunityNotificationController::class, 'markRead']);
        Route::post('notifications/read-all', [CommunityNotificationController::class, 'markAllRead']);

        // Reports & Block
        Route::post('report', [CommunityReportController::class, 'store']);
        Route::post('block/{userId}', [CommunityReportController::class, 'block']);
    });

        // Auth
        Route::post('auth/logout',          [AuthController::class, 'logout']);
        Route::get('auth/me',               [AuthController::class, 'me']);
        Route::post('auth/update-profile',  [AuthController::class, 'updateProfile']);
        Route::delete('auth/delete-account',[AuthController::class, 'deleteAccount']);
        Route::post('auth/fcm-token',       [AuthController::class, 'updateFcmToken']);

        // ─── CUSTOMER ─────────────────────────────────────────────
        Route::middleware('role:customer')->group(function () {

            // Home
            Route::get('home',      [HomeController::class, 'index']);
            Route::get('search',    [HomeController::class, 'search']);

            // Vendors
            Route::post('vendors/{vendor}/reviews', [VendorController::class, 'storeReview']);

            // Cart
            Route::get('cart',              [CartController::class, 'index']);
            Route::post('cart/add',         [CartController::class, 'add']);
            Route::patch('cart/{item}',     [CartController::class, 'update']);
            Route::delete('cart',           [CartController::class, 'clear']);

            // Orders
            Route::get('orders',            [OrderController::class, 'index']);
            Route::post('orders',           [OrderController::class, 'store']);
            Route::get('orders/{order}',    [OrderController::class, 'show']);
            Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
            Route::get('orders/{order}/tracking', [OrderController::class, 'tracking']);

            // Wallet
            Route::get('wallet',                    [WalletController::class, 'index']);
            Route::get('wallet/transactions',       [WalletController::class, 'transactions']);
            Route::post('wallet/topup',             [WalletController::class, 'topup']);
            Route::get('wallet/topup/status/{ref}', [WalletController::class, 'topupStatus']);
            Route::post('wallet/send',              [WalletController::class, 'send']);
            Route::post('wallet/withdraw',          [WalletController::class, 'requestWithdrawal']);
            Route::post('wallet/verify-pin',        [WalletController::class, 'verifyPin']);
            Route::get('wallet/loyalty-points',     [WalletController::class, 'loyaltyPoints']);
            Route::get('wallet/referral',           [WalletController::class, 'referral']);
            // Centralized payment (Waafi Pay)
            Route::post('payment/initiate',         [PaymentController::class, 'initiate']);
            Route::get('payment/status/{ref}',      [PaymentController::class, 'status']);

            // Addresses
            Route::get('addresses',             [AddressController::class, 'index']);
            Route::post('addresses',            [AddressController::class, 'store']);
            Route::patch('addresses/{address}', [AddressController::class, 'update']);
            Route::delete('addresses/{address}',[AddressController::class, 'destroy']);
            Route::post('addresses/{address}/default', [AddressController::class, 'setDefault']);

            // Wishlist
            Route::get('wishlist',          [WishlistController::class, 'index']);
            Route::post('wishlist/toggle',  [WishlistController::class, 'toggle']);

            // Notifications
            Route::get('notifications',                     [NotificationController::class, 'index']);
            Route::post('notifications/{id}/read',          [NotificationController::class, 'markRead']);
            Route::post('notifications/read-all',           [NotificationController::class, 'markAllRead']);

            // Chat
            Route::get('chats',                         [ChatController::class, 'conversations']);
            Route::get('chats/{conversation}/messages', [ChatController::class, 'messages']);
            Route::post('chats/{conversation}/send',    [ChatController::class, 'send']);
            Route::post('chats/order',                  [ChatController::class, 'getOrCreateByOrder']);

            // Module orders (authenticated)
            Route::post('efood/order',          [EFoodController::class, 'createOrder']);
            Route::post('edata/purchase',       [EDataController::class, 'purchasePackage']);
            Route::post('eparcel/order',        [EParcelController::class, 'createOrder']);
            Route::post('eexchange/transfer',   [EExchangeController::class, 'transfer']);
            Route::post('eexchange/confirm',    [EExchangeController::class, 'transfer']);    // alias used by Flutter
            Route::post('emoving/order',        [EMovingController::class, 'createOrder']);
            Route::get('emoving/my-orders',     [EMovingController::class, 'myOrders']);
            Route::post('elaundry/order',       [ELaundryController::class, 'createOrder']);
            Route::post('ehealth/ambulance',    [EHealthController::class, 'requestAmbulance']);
            Route::post('ehealth/book',         [EHealthController::class, 'bookAppointment']);
            Route::post('erent/book',                          [ERentController::class, 'book']);
            Route::get('erent/my-bookings',                    [ERentController::class, 'myBookings']);
            Route::post('erent/bookings/{id}/cancel',          [ERentController::class, 'cancelBooking']);
            Route::post('erent/bookings/{id}/pay-remaining',   [ERentController::class, 'payRemaining']);
            Route::post('erent/bookings/{id}/request-refund',  [ERentController::class, 'requestRefund']);
            Route::post('eshop/order',              [EShopController::class, 'createOrder']);
            Route::post('eshop/coupon/validate',    [EShopController::class, 'validateCoupon']);
            Route::post('ewholesale/order',     [EWholesaleController::class, 'inquire']);
            Route::post('egrocery/order',       [EGroceryController::class, 'createOrder']);
            Route::post('eticket/book',         [ETicketController::class, 'book']);
            Route::get('eticket/my-bookings',   [ETicketController::class, 'myBookings']);
            Route::get('eticket/my-bookings/{id}',  [ETicketController::class, 'myBookingDetail']);
        });

        // ─── DELIVERYMAN ──────────────────────────────────────────
        Route::prefix('delivery')->middleware('role:deliveryman')->group(function () {
            Route::get('dashboard',                         [DeliveryController::class, 'dashboard']);
            Route::get('orders/available',                  [DeliveryController::class, 'availableOrders']);
            Route::get('orders/active',                     [DeliveryController::class, 'activeOrders']);
            Route::post('orders/{order}/accept',            [DeliveryController::class, 'acceptOrder']);
            Route::post('orders/{order}/reject',            [DeliveryController::class, 'rejectOrder']);
            Route::post('orders/{order}/status',            [DeliveryController::class, 'updateOrderStatus']);
            Route::post('location',                         [DeliveryController::class, 'updateLocation']);
            Route::post('toggle-status',                    [DeliveryController::class, 'toggleStatus']);
            Route::get('earnings',                          [DeliveryController::class, 'earnings']);
        });

        // Deliveryman registration
        Route::post('delivery/auth/register', [DeliveryController::class, 'register']);

        // ─── VENDOR ───────────────────────────────────────────────
        Route::prefix('vendor')->middleware('role:vendor_owner,vendor_employee')->group(function () {
            Route::get('dashboard',         [VendorDashboardController::class, 'index']);
            Route::post('store/toggle',     [VendorDashboardController::class, 'toggleStore']);

            Route::get('orders',            [VendorOrderController::class, 'index']);
            Route::get('orders/{order}',    [VendorOrderController::class, 'show']);
            Route::post('orders/{order}/accept',     [VendorOrderController::class, 'accept']);
            Route::post('orders/{order}/reject',     [VendorOrderController::class, 'reject']);
            Route::post('orders/{order}/ready',      [VendorOrderController::class, 'markReady']);

            Route::get('products',              [VendorProductController::class, 'index']);
            Route::post('products',             [VendorProductController::class, 'store']);
            Route::patch('products/{product}',  [VendorProductController::class, 'update']);
            Route::delete('products/{product}', [VendorProductController::class, 'destroy']);
            Route::post('products/{product}/toggle', [VendorProductController::class, 'toggleAvailability']);

            Route::get('store',                 [VendorStoreController::class, 'profile']);
            Route::post('store',                [VendorStoreController::class, 'updateProfile']);
            Route::post('store/schedule',       [VendorStoreController::class, 'updateSchedule']);
            Route::post('store/documents',      [VendorStoreController::class, 'uploadDocument']);

            Route::get('wallet',                    [VendorWalletController::class, 'index']);
            Route::get('wallet/transactions',       [VendorWalletController::class, 'transactions']);
            Route::post('wallet/withdraw',          [VendorWalletController::class, 'requestWithdrawal']);
        });

        // ─── ADMIN ────────────────────────────────────────────────
        Route::prefix('admin')->middleware('role:super_admin,admin,operations_manager,finance_manager')->group(function () {
            // dashboard stats via API
            Route::get('stats', [\App\Http\Controllers\Admin\DashboardController::class, 'stats']);
        });
    });
});

// ─── eLearning ────────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\ELearning\ELearningPublicController;
use App\Http\Controllers\Api\ELearning\ELearningStudentController;
use App\Http\Controllers\Api\ELearning\ELearningInstructorApiController;
use App\Http\Controllers\Api\ELearning\ELearningPaymentController;

Route::prefix('v1/elearning')->group(function () {
    // Public
    Route::get('categories', [ELearningPublicController::class, 'categories']);
    Route::get('courses', [ELearningPublicController::class, 'courses']);
    Route::get('courses/{slug}', [ELearningPublicController::class, 'courseDetail']);
    Route::get('instructors', [ELearningPublicController::class, 'instructors']);

    // Student + Instructor — authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('my-learning', [ELearningStudentController::class, 'myLearning']);
        Route::post('enroll', [ELearningStudentController::class, 'enroll']);
        Route::post('lesson-progress', [ELearningStudentController::class, 'lessonProgress']);
        Route::post('complete-lesson', [ELearningStudentController::class, 'completeLesson']);
        Route::get('certificates', [ELearningStudentController::class, 'myCertificates']);
        Route::get('wishlist', [ELearningStudentController::class, 'wishlist']);
        Route::post('wishlist/toggle', [ELearningStudentController::class, 'toggleWishlist']);
        Route::post('reviews', [ELearningStudentController::class, 'submitReview']);
        Route::get('lessons/{id}', [ELearningStudentController::class, 'getLesson']);
        Route::get('notes', [ELearningStudentController::class, 'notes']);
        Route::post('notes', [ELearningStudentController::class, 'saveNote']);
        Route::get('quiz/{quizId}', [ELearningStudentController::class, 'quizStart']);
        Route::post('quiz/submit', [ELearningStudentController::class, 'quizSubmit']);

        // Payment
        Route::post('purchase', [ELearningPaymentController::class, 'initiatePurchase']);
        Route::post('purchase/verify', [ELearningPaymentController::class, 'verifyPurchase']);

        // Instructor
        Route::prefix('instructor')->group(function () {
            // Application (open to any authenticated user)
            Route::get('status', [ELearningInstructorApiController::class, 'status']);
            Route::post('apply', [ELearningInstructorApiController::class, 'apply']);

            Route::get('dashboard', [ELearningInstructorApiController::class, 'dashboard']);
            Route::get('courses', [ELearningInstructorApiController::class, 'myCourses']);
            Route::get('courses/{id}/structure', [ELearningInstructorApiController::class, 'courseStructure']);
            Route::post('courses', [ELearningInstructorApiController::class, 'createCourse']);
            Route::put('courses/{id}', [ELearningInstructorApiController::class, 'updateCourse']);
            Route::delete('courses/{id}', [ELearningInstructorApiController::class, 'deleteCourse']);
            Route::post('sections', [ELearningInstructorApiController::class, 'addSection']);
            Route::post('lessons', [ELearningInstructorApiController::class, 'addLesson']);
            Route::get('students', [ELearningInstructorApiController::class, 'students']);
            Route::get('earnings', [ELearningInstructorApiController::class, 'earnings']);
            Route::post('withdrawal', [ELearningInstructorApiController::class, 'requestWithdrawal']);
            Route::post('quizzes', [ELearningInstructorApiController::class, 'createQuiz']);
            Route::post('quiz-questions', [ELearningInstructorApiController::class, 'addQuestion']);
            Route::get('submissions', [ELearningInstructorApiController::class, 'reviewSubmissions']);
            Route::put('submissions/{id}/grade', [ELearningInstructorApiController::class, 'gradeSubmission']);
        });
    });
});
