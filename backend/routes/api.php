<?php
use App\Http\Controllers\Api\Community\CommunityFeedController;
use App\Http\Controllers\Api\Community\EMarryController;
use App\Http\Controllers\Api\Community\EMarryPaymentController;
use App\Http\Controllers\Api\Community\CommunityPostController;
use App\Http\Controllers\Api\Community\CommunityCommentController;
use App\Http\Controllers\Api\Community\CommunityProfileController;
use App\Http\Controllers\Api\Community\CommunityFollowController;
use App\Http\Controllers\Api\Community\CommunityStoryController;
use App\Http\Controllers\Api\Community\CommunityGroupController;
use App\Http\Controllers\Api\Community\CommunityChatController;
use App\Http\Controllers\Api\Community\CommunityNotificationController;
use App\Http\Controllers\Api\Community\CommunityReportController;
use App\Http\Controllers\Api\Community\CommunityModerationController;
use App\Http\Controllers\Api\Community\CopyrightController;
use App\Http\Controllers\Api\Community\UserSettingsController;
use App\Http\Controllers\Api\Community\CommunityHighlightController;
use App\Http\Controllers\Api\Community\CommunityBusinessPageController;
use App\Http\Controllers\Api\Community\CommunityAdController;
use App\Http\Controllers\Api\Community\ESpaceAdController;
use App\Http\Controllers\Api\Community\CommunityBlockController;
use App\Http\Controllers\Api\Podcast\PodcastFeedController;
use App\Http\Controllers\Api\Podcast\PodcastCategoryController;
use App\Http\Controllers\Api\Podcast\PodcastController;
use App\Http\Controllers\Api\Podcast\PodcastEpisodeController;
use App\Http\Controllers\Api\Podcast\PodcastSearchController;
use App\Http\Controllers\Api\Podcast\PodcastPublishController;
use App\Http\Controllers\Api\Podcast\PodcastHomeController;
use App\Http\Controllers\Api\Podcast\PodcastCommentController;
use App\Http\Controllers\Api\Podcast\PodcastLibraryController;


use App\Http\Controllers\Api\Call\CallController;
use App\Http\Controllers\Api\Live\LiveRoomController;
use App\Http\Controllers\Api\Live\GiftController;
use App\Http\Controllers\Api\Live\CoinController;
use App\Http\Controllers\Api\Live\LiveGuestController;
use App\Http\Controllers\Api\Live\LiveBattleController;
use App\Http\Controllers\Api\Live\LiveStatsController;
use App\Http\Controllers\Api\Live\LiveLeaderboardController;
use App\Http\Controllers\Api\Live\LiveModerationController;
use App\Http\Controllers\Api\Live\LiveDiscoveryController;
use App\Http\Controllers\Api\Live\LiveReportController;
use App\Http\Controllers\Api\Live\LiveSubscriptionController;
use App\Http\Controllers\Api\Live\LiveGoalController;
use App\Http\Controllers\Api\Live\LiveQAController;
use App\Http\Controllers\Api\Live\LiveRaidController;
use App\Http\Controllers\Api\Live\LiveVODController;
use Illuminate\Support\Facades\Route;

// ─── Auth ───────────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\Auth\AuthController;

// ─── Customer ────────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\OrderChatController;
use App\Http\Controllers\Api\Customer\HomeController;
use App\Http\Controllers\Api\Customer\OrderController;
use App\Http\Controllers\Api\Customer\WalletController;
use App\Http\Controllers\Api\Customer\AffiliateController;
use App\Http\Controllers\Api\Customer\GamificationController;
use App\Http\Controllers\Api\Customer\RewardController;
use App\Http\Controllers\Api\Customer\MobilePayController;
use App\Http\Controllers\Api\Payment\PaymentController;
use App\Http\Controllers\Api\Customer\VendorController;
use App\Http\Controllers\Api\Customer\CartController;
use App\Http\Controllers\Api\Customer\AddressController;
use App\Http\Controllers\Api\Customer\NotificationController;
use App\Http\Controllers\Api\Customer\WishlistController;
use App\Http\Controllers\Api\Customer\ChatController;
use App\Http\Controllers\Api\Customer\AdController;

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
use App\Http\Controllers\Api\Crypto\CryptoMarketController;
use App\Http\Controllers\Api\Crypto\CryptoWalletController;
use App\Http\Controllers\Api\Crypto\CryptoBuySellController;
use App\Http\Controllers\Api\Crypto\P2pController;
use App\Http\Controllers\Api\Modules\EParcelController;
use App\Http\Controllers\Api\Modules\EExchangeController;
use App\Http\Controllers\Api\Modules\EMovingController;
use App\Http\Controllers\Api\Modules\EFoodController;
use App\Http\Controllers\Api\Modules\ELaundryController;
use App\Http\Controllers\Api\Modules\EHealthController;
use App\Http\Controllers\Api\Modules\ERentController;
use App\Http\Controllers\Agent\AgentController;
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
// Streams via PHP (see proxy_storage_file) so CORS headers survive on LiteSpeed.
Route::get('img/{path}', fn (string $path) => proxy_storage_file($path))
    ->where('path', '.*');

Route::prefix('v1')->group(function () {

    // Canonical media proxy. The storage path is a ?f= query param so the URL has NO
    // file extension — the Hostinger CDN then treats it as DYNAMIC (pass-through) and
    // does not cache+strip the CORS headers. Streams via PHP (proxy_storage_file) so
    // the headers actually reach Flutter Web. See media_proxy_url()/cdn_url().
    Route::match(['GET', 'OPTIONS'], 'media', function (\Illuminate\Http\Request $request) {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204, [
                'Access-Control-Allow-Origin'  => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Origin, Accept, Content-Type, Range',
                'Access-Control-Max-Age'       => '86400',
            ]);
        }
        $f = (string) $request->query('f', '');
        // Disallow path traversal; only allow the public storage tree.
        $f = ltrim(str_replace(['..', "\0"], '', $f), '/');
        abort_if($f === '', 404);
        return proxy_storage_file($f);
    })->middleware('throttle:media');

    // Legacy extension-based proxy. Kept for backwards compatibility, but the CDN
    // caches these and strips CORS — new URLs use /media above.
    Route::get('img/{path}', fn (string $path) => proxy_storage_file($path))
        ->where('path', '.*');

    // ─── External image proxy (for Flutter Web CORS) ─────────────────────────
    // Proxies any external image URL so Flutter Web (global.esahlan.com) can
    // load images from external CDNs (e.g. cdn.phototourl.com) that lack CORS.
    // Usage: /api/v1/ext-image?url=https://cdn.phototourl.com/...
    Route::match(['GET', 'OPTIONS'], 'ext-image', function (\Illuminate\Http\Request $request) {
        $corsHeaders = [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, Accept, Content-Type',
            'Access-Control-Max-Age'       => '86400',
        ];
        if ($request->isMethod('OPTIONS')) {
            return response('', 204, $corsHeaders);
        }
        $url = (string) $request->query('url', '');
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            abort(400, 'Invalid url parameter');
        }
        // Security: only allow http/https schemes
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        abort_unless(in_array($scheme, ['http', 'https']), 400, 'Only http/https allowed');

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders(['User-Agent' => 'eSahlan-Proxy/1.0'])
                ->get($url);

            $contentType = $response->header('Content-Type') ?? 'image/jpeg';
            // Only allow image content types
            if (!str_starts_with($contentType, 'image/')) {
                abort(400, 'Not an image');
            }

            return response($response->body(), 200, array_merge($corsHeaders, [
                'Content-Type'  => $contentType,
                'Cache-Control' => 'public, max-age=86400',
                'X-Proxied-For' => 'esahlan-ext-image',
            ]));
        } catch (\Exception $e) {
            abort(502, 'Could not fetch image');
        }
    })->middleware('throttle:60,1');

    // ═══════════════════════════════════════════════════════════════
    // PUBLIC ROUTES
    // ═══════════════════════════════════════════════════════════════

    // App config (public — non-sensitive keys only)
    Route::get('app-config', function () {
        return response()->json(['success' => true, 'data' => [
            'fcm_enabled'         => (bool)\App\Helpers\AppSettings::get('fcm_enabled', true),
            'firebase_project_id' => \App\Helpers\AppSettings::get('firebase_project_id', ''),
            'currency_symbol'     => \App\Helpers\AppSettings::get('app_currency_symbol', '$'),
            'app_name'            => \App\Helpers\AppSettings::get('app_name', 'eSahlan'),
            'community_enabled'   => (bool)\App\Helpers\AppSettings::get('community_enabled', true),
        ]]);
    });

    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('register',        [AuthController::class, 'register']);
        Route::post('verify-otp',      [AuthController::class, 'verifyOtp']);
        Route::post('login',           [AuthController::class, 'login'])->middleware('brute_force');
        Route::post('google',          [AuthController::class, 'googleLogin']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password',  [AuthController::class, 'resetPassword']);
        // OTP has its own stricter limit on top of auth
        Route::post('send-otp',        [AuthController::class, 'sendOtp'])->middleware('throttle:otp');
    });

    // Deliveryman auth (public — no token required)
    Route::middleware('throttle:auth')->group(function () {
        Route::post('delivery/auth/register', [DeliveryController::class, 'register']);
        Route::post('delivery/auth/login',    [DeliveryController::class, 'login'])->middleware('brute_force');
    });

    // Vendor / Agent self-registration (public)
    Route::middleware('throttle:auth')->group(function () {
        Route::get('vendor/register/districts',  [\App\Http\Controllers\Vendor\VendorApiRegisterController::class, 'districts']);
        Route::post('vendor/register',           [\App\Http\Controllers\Vendor\VendorApiRegisterController::class, 'register']);
    });

    // Public info
    Route::get('ads',                   [AdController::class, 'index']);         // ?type=popup|banner|card &module=efood
    Route::post('ads/{id}/track',       [AdController::class, 'track']);         // body: {action: impression|click}
    Route::get('modules',               [HomeController::class, 'modules']);
    Route::get('modules/{slug}',        [HomeController::class, 'moduleDetails']);
    Route::get('payment/methods',           function () {
        return response()->json([
            'enabled' => \App\Http\Controllers\Admin\AdminPaymentSettingsController::enabledMethods(),
            'logos'   => \App\Http\Controllers\Admin\AdminPaymentSettingsController::paymentLogos(),
        ]);
    });
    Route::get('mobile-pay/accounts',      [MobilePayController::class, 'accounts']);
    Route::post('mobile-pay/submit-proof', [MobilePayController::class, 'submitProof']);
    Route::post('mobile-pay/attach-proof', [MobilePayController::class, 'attachProof']);
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
    Route::get('efood/delivery-fee',                     [EFoodController::class, 'calculateDeliveryFee']);
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
    Route::get('eexchange/wallet-logos',                 [EExchangeController::class, 'walletLogos']);
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
    Route::get('eshop/stores',                           [EShopController::class, 'stores']);
    Route::get('eshop/stores/{id}',                      [EShopController::class, 'storeDetail']);
    Route::get('eshop/popular',                          [EShopController::class, 'popular']);
    Route::get('eshop/products/{id}/reviews',            [EShopController::class, 'productReviews']);
    Route::patch('eshop/products/{id}/view',             [EShopController::class, 'trackView']);

    // ── eWholesale v2 — Public routes (no auth required) ────────────────────────
    Route::prefix('ewholesale')->group(function () {
        $pub = \App\Http\Controllers\Api\EWholesale\EWPublicController::class;
        Route::get('home',                       [$pub, 'home']);
        Route::get('categories',                 [$pub, 'categories']);
        Route::get('categories/{id}',            [$pub, 'category']);
        Route::get('products',                   [$pub, 'products']);
        Route::get('products/{slug}',            [$pub, 'product']);
        Route::get('suppliers/{id}',             [$pub, 'supplier']);
        Route::get('search',                     [$pub, 'search']);
        Route::get('search/suggest',             [$pub, 'searchSuggest']);
        // Cart validate — optional auth (prices differ for approved buyers)
        Route::post('cart/validate',             [\App\Http\Controllers\Api\EWholesale\EWCartController::class, 'validate']);
    });

    // eGrocery v2 (public)
    Route::get('egrocery/home',                          [\App\Http\Controllers\Api\EGrocery\EGroceryHomeController::class, 'index']);
    Route::get('egrocery/categories',                    [\App\Http\Controllers\Api\EGrocery\EGroceryCatalogController::class, 'categories']);
    Route::get('egrocery/categories/{id}',               [\App\Http\Controllers\Api\EGrocery\EGroceryCatalogController::class, 'category']);
    Route::get('egrocery/products',                      [\App\Http\Controllers\Api\EGrocery\EGroceryCatalogController::class, 'products']);
    Route::get('egrocery/products/{slug}',               [\App\Http\Controllers\Api\EGrocery\EGroceryCatalogController::class, 'product']);
    Route::get('egrocery/search/suggest',                [\App\Http\Controllers\Api\EGrocery\EGroceryCatalogController::class, 'suggest']);

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
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Sensitive config — only returned to authenticated users (Maps API key etc.)
    Route::get('app-config/sensitive', function () {
        return response()->json(['success' => true, 'data' => [
            'google_maps_api_key'     => \App\Helpers\AppSettings::get('google_maps_api_key', ''),
            'google_maps_default_lat' => (float)\App\Helpers\AppSettings::get('google_maps_default_lat', 2.0469),
            'google_maps_default_lng' => (float)\App\Helpers\AppSettings::get('google_maps_default_lng', 45.3182),
            'google_maps_default_zoom'=> (int)\App\Helpers\AppSettings::get('google_maps_default_zoom', 13),
        ]]);
    });

    // Reverb private/presence channel authorization for mobile clients.
    // The framework auto-registers broadcasting/auth under the "web"
    // (session-cookie) middleware group, which Bearer-token mobile clients
    // can't use — this Sanctum-protected duplicate is what Flutter/Echo
    // actually points at. Shared by every module's private/presence channels,
    // not just Community.
    Route::post('broadcasting/auth', function (\Illuminate\Http\Request $request) {
        return \Illuminate\Support\Facades\Broadcast::auth($request);
    });

    // ── Community Module ──────────────────────────────────────────────────────
    Route::prefix('community')->group(function () {
        // Feed (throttle:feed — heavier reads capped at 60/min)
        Route::middleware('throttle:feed')->group(function () {
            Route::get('feed',        [CommunityFeedController::class, 'following']);
            Route::get('explore',     [CommunityFeedController::class, 'explore']);
            Route::get('reels',       [CommunityFeedController::class, 'reels']);
            Route::get('stories',     [CommunityFeedController::class, 'stories']);
            Route::get('trending',    [CommunityFeedController::class, 'trending']);
            Route::get('search',      [CommunityFeedController::class, 'search']);
            Route::get('suggestions', [CommunityFeedController::class, 'suggestions']);
        });

        // Feed interaction tracking
        Route::post('feed/track', [CommunityFeedController::class, 'trackInteraction']);
        Route::post('feed/impressions', [CommunityFeedController::class, 'trackImpressions']);
        Route::post('feed/heartbeat', [CommunityFeedController::class, 'heartbeat']);
        Route::delete('feed/heartbeat', [CommunityFeedController::class, 'leave']);
        Route::post('feed/onboarding-interests', [CommunityFeedController::class, 'saveOnboardingInterests']);

        // Posts (upload-creating routes get stricter limit)
        Route::post('posts', [CommunityPostController::class, 'store'])->middleware('throttle:upload');
        Route::get('posts/saved', [CommunityPostController::class, 'saved']);
        Route::apiResource('posts', CommunityPostController::class)->except(['index', 'store']);
        Route::post('posts/{id}/react', [CommunityPostController::class, 'react']);
        Route::get('posts/{id}/reactions', [CommunityPostController::class, 'reactions']);
        Route::post('posts/{id}/share', [CommunityPostController::class, 'share']);
        Route::post('posts/{id}/save', [CommunityPostController::class, 'save']);
        Route::post('posts/{id}/vote', [CommunityPostController::class, 'votePoll']);
        Route::get('media/{mediaId}/transcoding-status', [CommunityPostController::class, 'transcodingStatus']);

        // Promoted posts (boost)
        Route::get('ads/analytics', [CommunityAdController::class, 'analytics']);
        Route::post('posts/{id}/boost', [CommunityAdController::class, 'boostPost']);

        // Comments
        Route::get('posts/{postId}/comments', [CommunityCommentController::class, 'index']);
        Route::post('posts/{postId}/comments', [CommunityCommentController::class, 'store']);
        Route::put('comments/{id}', [CommunityCommentController::class, 'update']);
        Route::delete('comments/{id}', [CommunityCommentController::class, 'destroy']);
        Route::post('comments/{id}/react', [CommunityCommentController::class, 'react']);

        // Profile
        Route::get('geo/countries', [\App\Http\Controllers\Api\GeoController::class, 'countries']);
        Route::get('geo/cities/{code}', [\App\Http\Controllers\Api\GeoController::class, 'cities']);

        Route::get('profile/me', fn() => app(CommunityProfileController::class)->show(auth()->id()));
        Route::get('profile/onboarding-check', [CommunityProfileController::class, 'checkOnboarding']);
        Route::post('profile/onboarding', [CommunityProfileController::class, 'onboarding']);
        Route::post('profile/avatar', [CommunityProfileController::class, 'updateAvatar']);
        Route::put('profile', [CommunityProfileController::class, 'update']);
        Route::get('profile/{userId}', [CommunityProfileController::class, 'show']);
        Route::get('profile/{userId}/posts', [CommunityProfileController::class, 'posts']);
        Route::get('profile/{userId}/liked-posts', [CommunityProfileController::class, 'likedPosts']);
        Route::get('profile/me/saved-posts', [CommunityProfileController::class, 'savedPosts']);
        Route::get('profile/{userId}/saved-posts', [CommunityProfileController::class, 'savedPostsByUser']);
        Route::get('profile/{userId}/followers', [CommunityProfileController::class, 'followers']);
        Route::get('profile/{userId}/following', [CommunityProfileController::class, 'following']);

        // Follow + Follow Requests
        Route::post('follow/{userId}', [CommunityFollowController::class, 'toggle']);
        Route::get('follow-requests', [CommunityFollowController::class, 'requests']);
        Route::post('follow-requests/{id}/accept', [CommunityFollowController::class, 'accept']);
        Route::post('follow-requests/{id}/reject', [CommunityFollowController::class, 'reject']);

        // Stories (creation throttled as upload)
        Route::post('stories', [CommunityStoryController::class, 'store'])->middleware('throttle:upload');
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

        // Chats (message sending throttled separately)
        Route::get('chats', [CommunityChatController::class, 'index']);
        Route::post('chats/start/{userId}', [CommunityChatController::class, 'startOrGet']);
        Route::get('chats/{chatId}/messages', [CommunityChatController::class, 'messages']);
        Route::post('chats/{chatId}/messages', [CommunityChatController::class, 'send'])->middleware('throttle:chat');
        Route::post('chats/{chatId}/read', [CommunityChatController::class, 'markRead']);
        Route::post('chats/{chatId}/typing', [CommunityChatController::class, 'typing']);
        Route::post('messages/{msgId}/react', [CommunityChatController::class, 'reactToMessage']);
        Route::delete('messages/{msgId}', [CommunityChatController::class, 'deleteMessage']);

        // Story Highlights
        Route::get('highlights/{userId}', [\App\Http\Controllers\Api\Community\CommunityHighlightController::class, 'index']);
        Route::post('highlights', [\App\Http\Controllers\Api\Community\CommunityHighlightController::class, 'store']);
        Route::post('highlights/{id}/add', [\App\Http\Controllers\Api\Community\CommunityHighlightController::class, 'addStory']);
        Route::delete('highlights/{id}', [\App\Http\Controllers\Api\Community\CommunityHighlightController::class, 'destroy']);

        // Notifications
        Route::get('notifications', [CommunityNotificationController::class, 'index']);
        Route::get('notifications/unread-count', [CommunityNotificationController::class, 'unreadCount']);
        Route::post('notifications/{id}/read', [CommunityNotificationController::class, 'markRead']);
        Route::post('notifications/read-all', [CommunityNotificationController::class, 'markAllRead']);

        // Business Pages
        Route::get('pages', [CommunityBusinessPageController::class, 'index']);
        Route::get('pages/mine', [CommunityBusinessPageController::class, 'myPages']);
        Route::post('pages', [CommunityBusinessPageController::class, 'store']);
        Route::get('pages/{id}', [CommunityBusinessPageController::class, 'show']);
        Route::put('pages/{id}', [CommunityBusinessPageController::class, 'update']);
        Route::post('pages/{id}/follow', [CommunityBusinessPageController::class, 'toggleFollow']);
        Route::get('pages/{id}/posts', [CommunityBusinessPageController::class, 'posts']);

        // eSpace internal module ads
        Route::get('espace-ads', [ESpaceAdController::class, 'index']);
        Route::post('espace-ads/{ad}/impression', [ESpaceAdController::class, 'impression']);
        Route::post('espace-ads/{ad}/click', [ESpaceAdController::class, 'click']);

        // Ads
        Route::get('ads/pricing', [CommunityAdController::class, 'pricing']);
        Route::get('ads/mine', [CommunityAdController::class, 'myAds']);
        Route::post('ads', [CommunityAdController::class, 'store']);
        Route::get('ads/settings', [CommunityAdController::class, 'displaySettings']);
        Route::post('ads/estimate-audience', [CommunityAdController::class, 'estimateAudience']);
        Route::get('ads/preroll', [CommunityAdController::class, 'preroll']);
        Route::post('ads/{id}/click', [CommunityAdController::class, 'trackClick']);

        // Reports & Block
        Route::post('report', [CommunityReportController::class, 'store']);
        Route::post('posts/{id}/report', [CommunityModerationController::class, 'reportPost']);
        Route::post('block/{userId}', [CommunityReportController::class, 'block']);

        // Block & Mute
        Route::post('block/{userId}/toggle', [CommunityBlockController::class, 'toggle']);
        Route::post('mute/{userId}', [CommunityBlockController::class, 'toggleMute']);
        Route::get('block/{userId}/check', [CommunityBlockController::class, 'checkBlock']);

        // Transparency Center (user's own moderation status & appeals)
        Route::prefix('my')->group(function () {
            Route::get('moderation', [CommunityModerationController::class, 'myStatus']);
            Route::get('appeals', [CommunityModerationController::class, 'myAppeals']);
            Route::post('appeals', [CommunityModerationController::class, 'submitAppeal']);
            Route::get('copyright-claims', [CopyrightController::class, 'myClaims']);
            Route::get('copyright-against-me', [CopyrightController::class, 'claimsAgainstMe']);
        });

        // Copyright Claims
        Route::prefix('copyright')->group(function () {
            Route::post('claims', [CopyrightController::class, 'submitClaim']);
            Route::post('claims/{id}/counter', [CopyrightController::class, 'submitCounter']);
        });
    });

        // Settings
        Route::get('community/settings',                              [UserSettingsController::class, 'index']);
        Route::put('community/settings',                              [UserSettingsController::class, 'update']);

        // ── eMarry ────────────────────────────────────────────────────────────
        Route::prefix('emarry')->group(function () {
            Route::get('profiles',                    [EMarryController::class, 'index']);
            Route::get('profile/me',                  [EMarryController::class, 'myProfile']);
            Route::post('profile',                    [EMarryController::class, 'saveProfile']);
            Route::post('photo',                      [EMarryController::class, 'uploadPhoto']);
            Route::post('photo/delete',               [EMarryController::class, 'deletePhoto']);
            Route::post('interest/{userId}',          [EMarryController::class, 'sendInterest']);
            Route::post('interest/{senderId}/respond',[EMarryController::class, 'respondInterest']);
            Route::get('interests/received',          [EMarryController::class, 'receivedInterests']);
            Route::get('interests/sent',              [EMarryController::class, 'sentInterests']);
            Route::get('matches',                     [EMarryController::class, 'matches']);
            Route::post('pass/{userId}',              [EMarryController::class, 'passProfile']);

            // Payment & monetization
            Route::get ('payment/plans',                        [EMarryPaymentController::class, 'plans']);
            Route::get ('payment/status',                       [EMarryPaymentController::class, 'status']);
            Route::get ('payment/poll/{ref}',                   [EMarryPaymentController::class, 'poll']);
            Route::post('payment/subscribe',                    [EMarryPaymentController::class, 'subscribe']);
            Route::post('payment/credits/buy',                  [EMarryPaymentController::class, 'buyCredits']);
            Route::post('payment/mobile-pay/screenshot',        [EMarryPaymentController::class, 'uploadMobilePayScreenshot']);
            Route::post('credits/use',                          [EMarryPaymentController::class, 'useCredit']);
        });
        Route::get('community/settings/blocked-users',                [UserSettingsController::class, 'blockedUsers']);
        Route::delete('community/settings/blocked-users/{id}',        [UserSettingsController::class, 'unblock']);
        Route::get('community/settings/muted-users',                  [UserSettingsController::class, 'mutedUsers']);
        Route::delete('community/settings/muted-users/{id}',          [UserSettingsController::class, 'unmute']);
        Route::get('community/settings/restricted-users',             [UserSettingsController::class, 'restrictedUsers']);

        // Highlights
        Route::get('community/users/{userId}/highlights',        [CommunityHighlightController::class, 'index']);
        Route::post('community/highlights',                      [CommunityHighlightController::class, 'store']);
        Route::post('community/highlights/{id}',                 [CommunityHighlightController::class, 'update']);
        Route::delete('community/highlights/{id}',               [CommunityHighlightController::class, 'destroy']);
        // Highlight items
        Route::get('community/highlights/{id}/items',            [CommunityHighlightController::class, 'getItems']);
        Route::post('community/highlights/{id}/items',           [CommunityHighlightController::class, 'addItem']);
        Route::delete('community/highlights/{id}/items/{itemId}',[CommunityHighlightController::class, 'removeItem']);

        // Auth
        Route::post('auth/logout',              [AuthController::class, 'logout']);
        Route::post('auth/logout-all',          [AuthController::class, 'logoutAll']);
        Route::post('auth/deactivate',          [AuthController::class, 'deactivateAccount']);
        Route::get('auth/sessions',             [AuthController::class, 'sessions']);
        Route::delete('auth/sessions/{id}',     [AuthController::class, 'revokeSession']);
        Route::get('auth/me',                   [AuthController::class, 'me']);
        Route::post('auth/update-profile',      [AuthController::class, 'updateProfile']);
        Route::delete('auth/delete-account',    [AuthController::class, 'deleteAccount']);
        Route::post('auth/fcm-token',           [AuthController::class, 'updateFcmToken']);
        Route::post('auth/location',        [AuthController::class, 'updateLocation']);

        // ─── CUSTOMER ─────────────────────────────────────────────
        Route::middleware('role:customer')->group(function () {

            // Home
            Route::get('home',              [HomeController::class, 'index']);
            Route::get('home/live-offers',  [HomeController::class, 'liveOffers']);
            Route::get('home/near-you',     [HomeController::class, 'nearYou']);
            Route::get('home/best-sellers', [HomeController::class, 'bestSellers']);
            Route::get('home/rent-homes',   [HomeController::class, 'rentHomes']);
            Route::get('home/flights',      [HomeController::class, 'upcomingFlights']);
            Route::get('search',            [HomeController::class, 'search']);

            // Vendors
            Route::post('vendors/{vendor}/reviews', [VendorController::class, 'storeReview']);

            // Cart
            Route::get('cart',              [CartController::class, 'index']);
            Route::post('cart/add',         [CartController::class, 'add']);
            Route::patch('cart/{item}',     [CartController::class, 'update']);
            Route::delete('cart',           [CartController::class, 'clear']);

            // Cart abandonment sync
            Route::post('cart/sync',        [\App\Http\Controllers\Api\Customer\CartSyncController::class, 'sync']);
            Route::post('cart/clear-module',[\App\Http\Controllers\Api\Customer\CartSyncController::class, 'clear']);

            // Orders
            Route::get('orders',            [OrderController::class, 'index']);
            Route::post('orders',           [OrderController::class, 'store']);
            Route::get('orders/analytics',  [OrderController::class, 'analytics']);
            Route::get('orders/{order}',    [OrderController::class, 'show']);
            Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
            Route::get('orders/{order}/tracking', [OrderController::class, 'tracking']);
            // Order chat (customer ↔ driver real-time)
            Route::get('orders/{order}/chat',               [OrderChatController::class, 'messages']);
            Route::post('orders/{order}/chat',              [OrderChatController::class, 'send']);
            Route::post('orders/{order}/chat/location',     [OrderChatController::class, 'shareLocation']);

            // Wallet + Payment (throttle:payment — max 10/min, fraud protection)
            Route::middleware('throttle:payment')->group(function () {
                Route::post('wallet/topup',                [WalletController::class, 'topup']);
                Route::post('wallet/topup/mobile-pay',     [WalletController::class, 'topupMobilePay']);
                Route::post('wallet/send',           [WalletController::class, 'send']);
                Route::post('wallet/withdraw',       [WalletController::class, 'requestWithdrawal']);
                Route::post('wallet/verify-pin',     [WalletController::class, 'verifyPin']);
                Route::post('payment/initiate',      [PaymentController::class, 'initiate']);
            });
            Route::get('wallet',                    [WalletController::class, 'index']);
            Route::get('wallet/transactions',       [WalletController::class, 'transactions']);
            Route::get('wallet/topup/status/{ref}', [WalletController::class, 'topupStatus']);
            Route::post('wallet/set-pin',           [WalletController::class, 'setPin']);
            Route::get('wallet/loyalty-points',     [WalletController::class, 'loyaltyPoints']);
            Route::get('wallet/referral',           [WalletController::class, 'referral']);

            // Rewards (Points)
            Route::prefix('rewards')->group(function () {
                Route::get('/',                  [RewardController::class, 'index']);
                Route::get('/history',           [RewardController::class, 'history']);
                Route::post('/validate-redeem',  [RewardController::class, 'validateRedeem']);
                Route::post('/redeem-to-wallet', [RewardController::class, 'redeemToWallet']);
                Route::get('/earn-preview',      [RewardController::class, 'earnPreview']);
            });
            Route::prefix('gamification')->group(function () {
                Route::get('/',             [GamificationController::class, 'profile']);
                Route::get('/leaderboard',  [GamificationController::class, 'leaderboard']);
            });
            Route::prefix('affiliate')->group(function () {
                Route::get('/',              [AffiliateController::class, 'dashboard']);
                Route::post('/apply',        [AffiliateController::class, 'apply']);
                Route::post('/payout',       [AffiliateController::class, 'requestPayout']);
            });
            // In-app notifications
            Route::prefix('notifications')->group(function () {
                Route::get('/',         [\App\Http\Controllers\Api\Customer\NotificationController::class, 'index']);
                Route::post('/read',    [\App\Http\Controllers\Api\Customer\NotificationController::class, 'markRead']);
                Route::get('/unread',   [\App\Http\Controllers\Api\Customer\NotificationController::class, 'unreadCount']);
            });

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
            Route::post('eexchange/transfer',        [EExchangeController::class, 'transfer']);
            Route::post('eexchange/confirm',         [EExchangeController::class, 'transfer']);    // alias
            Route::get('eexchange/orders',           [EExchangeController::class, 'myOrders']);
            Route::get('eexchange/accounts',         [EExchangeController::class, 'myAccounts']);
            Route::post('eexchange/accounts',        [EExchangeController::class, 'addAccount']);
            Route::delete('eexchange/accounts/{id}', [EExchangeController::class, 'removeAccount']);

            // ── Crypto Exchange ───────────────────────────────────────
            Route::prefix('crypto')->group(function () {
                $mc = CryptoMarketController::class;
                $wc = CryptoWalletController::class;
                $bc = CryptoBuySellController::class;
                $pc = P2pController::class;
                // Markets
                Route::get('markets',                       [$mc,'index']);
                Route::get('markets/{symbol}',              [$mc,'show']);
                Route::get('markets/{symbol}/chart',        [$mc,'chart']);
                // Wallet
                Route::get('wallet/check',                  [$wc,'check']);
                Route::post('wallet/setup',                 [$wc,'setup']);
                Route::get('wallet/portfolio',              [$wc,'portfolio']);
                Route::get('wallet/{symbol}/deposit',       [$wc,'depositAddress']);
                Route::post('wallet/withdraw',              [$wc,'withdraw']);
                Route::post('wallet/transfer',              [$wc,'transfer']);
                Route::get('wallet/transactions',           [$wc,'transactions']);
                // Buy/Sell
                Route::post('quote',                        [$bc,'quote']);
                Route::post('buy',                          [$bc,'buy']);
                Route::post('sell',                         [$bc,'sell']);
                Route::get('orders',                        [$bc,'orders']);
                // P2P
                Route::get('p2p/ads',                       [$pc,'ads']);
                Route::post('p2p/ads',                      [$pc,'createAd']);
                Route::get('p2p/ads/mine',                  [$pc,'myAds']);
                Route::delete('p2p/ads/{uuid}',             [$pc,'cancelAd']);
                Route::post('p2p/orders',                   [$pc,'placeOrder']);
                Route::get('p2p/orders',                    [$pc,'myOrders']);
                Route::get('p2p/orders/{uuid}',             [$pc,'orderDetail']);
                Route::post('p2p/orders/{uuid}/paid',       [$pc,'markPaid']);
                Route::post('p2p/orders/{uuid}/release',    [$pc,'releaseCrypto']);
                Route::post('p2p/orders/{uuid}/cancel',     [$pc,'cancelOrder']);
                Route::post('p2p/orders/{uuid}/dispute',    [$pc,'openDispute']);
                Route::get('p2p/orders/{uuid}/messages',    [$pc,'orderMessages']);
                Route::post('p2p/orders/{uuid}/messages',   [$pc,'sendMessage']);
            });
            Route::post('emoving/order',        [EMovingController::class, 'createOrder']);
            Route::get('emoving/my-orders',     [EMovingController::class, 'myOrders']);
            Route::post('elaundry/order',       [ELaundryController::class, 'createOrder']);
            Route::post('ehealth/ambulance',    [EHealthController::class, 'requestAmbulance']);
            Route::post('ehealth/book',         [EHealthController::class, 'bookAppointment']);
            Route::post('erent/house-requests',                                       [ERentController::class, 'storeHouseRequest']);
            Route::get('erent/house-requests/mine',                               [ERentController::class, 'myHouseRequests']);
            Route::get('erent/house-requests/{id}',                                [ERentController::class, 'getHouseRequest']);
            // Phase 2+3: Recommendations, Viewings, Chat (customer)
            Route::get('erent/house-requests/{id}/recommendations',               [ERentController::class, 'requestRecommendations']);
            Route::post('erent/house-requests/{id}/recommendations/{recId}/respond', [ERentController::class, 'respondRecommendation']);
            Route::get('erent/house-requests/{id}/viewings',                      [ERentController::class, 'requestViewings']);
            Route::post('erent/house-requests/{id}/viewings/{viewId}/confirm',    [ERentController::class, 'confirmViewing']);
            Route::get('erent/house-requests/{id}/messages',                      [ERentController::class, 'getMessages']);
            Route::post('erent/house-requests/{id}/messages',                     [ERentController::class, 'sendMessage']);
            Route::post('erent/book',                          [ERentController::class, 'book']);
            Route::get('erent/my-bookings',                    [ERentController::class, 'myBookings']);
            Route::post('erent/bookings/{id}/cancel',          [ERentController::class, 'cancelBooking']);
            Route::post('erent/bookings/{id}/pay-remaining',   [ERentController::class, 'payRemaining']);
            Route::post('erent/bookings/{id}/request-refund',  [ERentController::class, 'requestRefund']);

            Route::get('eshop/delivery-fee',        [EShopController::class, 'deliveryFee']);
            Route::post('eshop/order',              [EShopController::class, 'createOrder']);
            Route::post('eshop/coupon/validate',    [EShopController::class, 'validateCoupon']);
            Route::post('eshop/products/{id}/reviews', [EShopController::class, 'submitReview']);
            // ── eWholesale v2 — Supplier Portal ───────────────────────────────────
            Route::prefix('ewholesale/supplier')->group(function () {
                $portal = \App\Http\Controllers\Api\EWholesale\EWSupplierPortalController::class;

                // Registration & profile
                Route::get('me',                        [$portal, 'me']);
                Route::post('register',                 [$portal, 'register']);
                Route::put('me',                        [$portal, 'updateProfile']);

                // Dashboard
                Route::get('dashboard',                 [$portal, 'dashboard']);

                // Products
                Route::get('products',                  [$portal, 'products']);
                Route::post('products',                 [$portal, 'productStore']);
                Route::get('products/{id}',             [$portal, 'productShow']);
                Route::put('products/{id}',             [$portal, 'productUpdate']);
                Route::post('products/{id}/toggle',     [$portal, 'productToggle']);

                // Orders
                Route::get('orders',                    [$portal, 'orders']);
                Route::get('orders/{id}',               [$portal, 'orderShow']);
                Route::post('orders/{id}/confirm',      [$portal, 'orderConfirm']);
                Route::post('orders/{id}/status',       [$portal, 'orderUpdateStatus']);
                Route::patch('orders/{id}/status',      [$portal, 'orderUpdateStatus']);

                // Inquiries & Quotes
                Route::get('inquiries',                 [$portal, 'inquiries']);
                Route::post('inquiries/{id}/quote',     [$portal, 'sendQuote']);

                // RFQ responses
                Route::get('rfqs',                      [$portal, 'rfqs']);
                Route::post('rfqs/{id}/quote',          [$portal, 'rfqSubmitQuote']);
            });

            // ── eWholesale v2 — Authed buyer routes ───────────────────────────────
            Route::prefix('ewholesale')->group(function () {
                $buyer   = \App\Http\Controllers\Api\EWholesale\EWBuyerController::class;
                $inq     = \App\Http\Controllers\Api\EWholesale\EWQuoteController::class;
                $rfq     = \App\Http\Controllers\Api\EWholesale\EWRfqApiController::class;
                $orders  = \App\Http\Controllers\Api\EWholesale\EWOrderApiController::class;
                $lists   = \App\Http\Controllers\Api\EWholesale\EWSavedListController::class;

                // Buyer profile
                Route::post('buyer/register',           [$buyer, 'register']);
                Route::get('buyer/me',                  [$buyer, 'me']);

                // Inquiry & quotes
                Route::post('products/{id}/inquiry',    [$inq, 'inquiry']);
                Route::get('quotes',                    [$inq, 'index']);
                Route::get('quotes/{id}',               [$inq, 'show']);
                Route::post('quotes/{id}/counter',      [$inq, 'counter']);
                Route::post('quotes/{id}/accept',       [$inq, 'accept']);
                Route::post('quotes/{id}/decline',      [$inq, 'decline']);

                // RFQs
                Route::post('rfqs',                     [$rfq, 'store']);
                Route::get('rfqs/mine',                 [$rfq, 'mine']);
                Route::get('rfqs/{id}',                 [$rfq, 'show']);
                Route::post('rfq-quotes/{id}/accept',   [$rfq, 'acceptQuote']);
                Route::post('rfq-quotes/{id}/shortlist',[$rfq, 'shortlistQuote']);
                Route::post('rfq-quotes/{id}/reject',   [$rfq, 'rejectQuote']);

                // Orders
                Route::post('orders',                   [$orders, 'checkout']);
                Route::get('orders',                    [$orders, 'index']);
                Route::get('orders/{id}',               [$orders, 'show']);
                Route::post('orders/{id}/pay-balance',  [$orders, 'payBalance']);
                Route::post('orders/{id}/cancel',       [$orders, 'cancel']);
                Route::post('orders/{id}/dispute',      [$orders, 'dispute']);
                Route::post('orders/{id}/review',       [$orders, 'review']);
                Route::post('orders/{id}/reorder',      [$orders, 'reorder']);

                // Saved lists
                Route::get('lists',                     [$lists, 'index']);
                Route::post('lists',                    [$lists, 'store']);
                Route::get('lists/{id}',                [$lists, 'show']);
                Route::put('lists/{id}',                [$lists, 'update']);
                Route::delete('lists/{id}',             [$lists, 'destroy']);
                Route::post('lists/{id}/items',         [$lists, 'addItem']);
                Route::delete('lists/{listId}/items/{itemId}', [$lists, 'removeItem']);
                Route::post('lists/{id}/to-cart',       [$lists, 'toCart']);
            });
            // eGrocery v2 (authed)
            Route::prefix('egrocery')->group(function () {
                Route::get('favorites',                      [\App\Http\Controllers\Api\EGrocery\EGroceryUserController::class, 'favorites']);
                Route::post('favorites',                     [\App\Http\Controllers\Api\EGrocery\EGroceryUserController::class, 'addFavorite']);
                Route::delete('favorites/{productId}',       [\App\Http\Controllers\Api\EGrocery\EGroceryUserController::class, 'removeFavorite']);
                Route::get('lists',                          [\App\Http\Controllers\Api\EGrocery\EGroceryUserController::class, 'lists']);
                Route::post('lists',                         [\App\Http\Controllers\Api\EGrocery\EGroceryUserController::class, 'createList']);
                Route::get('lists/{id}',                     [\App\Http\Controllers\Api\EGrocery\EGroceryUserController::class, 'showList']);
                Route::put('lists/{id}',                     [\App\Http\Controllers\Api\EGrocery\EGroceryUserController::class, 'updateList']);
                Route::delete('lists/{id}',                  [\App\Http\Controllers\Api\EGrocery\EGroceryUserController::class, 'deleteList']);
                Route::post('lists/{id}/items',              [\App\Http\Controllers\Api\EGrocery\EGroceryUserController::class, 'addListItem']);
                Route::patch('lists/{listId}/items/{itemId}',[\App\Http\Controllers\Api\EGrocery\EGroceryUserController::class, 'updateListItem']);
                Route::delete('lists/{listId}/items/{itemId}',[\App\Http\Controllers\Api\EGrocery\EGroceryUserController::class, 'deleteListItem']);
                Route::post('lists/{id}/add-all-to-cart',   [\App\Http\Controllers\Api\EGrocery\EGroceryUserController::class, 'listAddAllToCart']);
                Route::post('cart/validate',                 [\App\Http\Controllers\Api\EGrocery\EGroceryUserController::class, 'cartValidate']);
                Route::get('checkout/slots',                 [\App\Http\Controllers\Api\EGrocery\EGroceryOrderController::class, 'slots']);
                Route::post('orders',                        [\App\Http\Controllers\Api\EGrocery\EGroceryOrderController::class, 'checkout']);
                Route::get('orders',                         [\App\Http\Controllers\Api\EGrocery\EGroceryOrderController::class, 'orders']);
                Route::get('orders/{id}',                    [\App\Http\Controllers\Api\EGrocery\EGroceryOrderController::class, 'orderShow']);
                Route::post('orders/{id}/cancel',            [\App\Http\Controllers\Api\EGrocery\EGroceryOrderController::class, 'cancelOrder']);
                Route::post('orders/{id}/reorder',           [\App\Http\Controllers\Api\EGrocery\EGroceryOrderController::class, 'reorder']);
                Route::post('orders/{orderId}/items/{itemId}/substitution', [\App\Http\Controllers\Api\EGrocery\EGroceryOrderController::class, 'respondToSubstitution']);
                Route::post('products/{id}/reviews',         [\App\Http\Controllers\Api\EGrocery\EGroceryOrderController::class, 'submitReview']);
            });
            // Legacy
            Route::post('egrocery/order',       [EGroceryController::class, 'createOrder']);
            Route::post('eticket/book',         [ETicketController::class, 'book']);
            Route::get('eticket/my-bookings',   [ETicketController::class, 'myBookings']);
            Route::get('eticket/my-bookings/{id}',  [ETicketController::class, 'myBookingDetail']);
        });

        // ─── AGENT (eRent) ───────────────────────────────────────
        Route::middleware('role:rent_agent')->prefix('agent')->name('agent.')->group(function () {
            Route::get('dashboard',                  [AgentController::class, 'dashboard']);
            Route::get('districts',                  [AgentController::class, 'districts']);
            Route::get('properties',                 [AgentController::class, 'properties']);
            Route::post('properties',                [AgentController::class, 'store']);
            Route::put('properties/{id}',            [AgentController::class, 'update']);
            Route::post('properties/{id}/rented',    [AgentController::class, 'markRented']);
            Route::post('properties/{id}/available', [AgentController::class, 'markAvailable']);
            Route::delete('properties/{id}',         [AgentController::class, 'destroy']);
            Route::get('wallet',                     [AgentController::class, 'wallet']);
            Route::get('house-requests',                          [AgentController::class, 'houseRequests']);
            Route::post('house-requests/{id}/assign',             [AgentController::class, 'assignRequest']);
            Route::post('house-requests/{id}/update-status',      [AgentController::class, 'updateRequestStatus']);
            Route::post('house-requests/{id}/contact',            [AgentController::class, 'contactRequest']);
            Route::post('house-requests/{id}/close',              [AgentController::class, 'closeRequest']);
            // Phase 2: Recommendations + Viewings
            Route::post('house-requests/{id}/recommend',          [AgentController::class, 'recommendProperty']);
            Route::get('house-requests/{id}/recommendations',     [AgentController::class, 'getRecommendations']);
            Route::post('house-requests/{id}/viewings',           [AgentController::class, 'scheduleViewing']);
            Route::post('house-requests/viewings/{viewId}/status',[AgentController::class, 'updateViewingStatus']);
            // Phase 3: Chat
            Route::get('house-requests/{id}/messages',            [AgentController::class, 'getMessagesAgent']);
            Route::post('house-requests/{id}/messages',           [AgentController::class, 'sendMessageAgent']);
        });

        // ─── DELIVERYMAN ──────────────────────────────────────────
        Route::prefix('delivery')->middleware('role:deliveryman')->group(function () {
            Route::get('dashboard',                         [DeliveryController::class, 'dashboard']);
            Route::get('orders/available',                  [DeliveryController::class, 'availableOrders']);
            Route::get('orders/active',                     [DeliveryController::class, 'activeOrders']);
            Route::get('orders/history',                    [DeliveryController::class, 'orderHistory']);
            Route::post('orders/{order}/accept',            [DeliveryController::class, 'acceptOrder']);
            Route::post('orders/{order}/reject',            [DeliveryController::class, 'rejectOrder']);
            Route::post('orders/{order}/status',            [DeliveryController::class, 'updateOrderStatus']);
            Route::post('location',                         [DeliveryController::class, 'updateLocation']);
            Route::post('toggle-status',                    [DeliveryController::class, 'toggleStatus']);
            Route::get('earnings',                          [DeliveryController::class, 'earnings']);
            Route::get('profile',                           [DeliveryController::class, 'profile']);
            Route::post('profile/update',                   [DeliveryController::class, 'updateProfile']);
            Route::post('fcm-token',                        [DeliveryController::class, 'updateFcmToken']);
            Route::get('wallet',                            [DeliveryController::class, 'wallet']);
            Route::get('wallet/transactions',               [DeliveryController::class, 'walletTransactions']);
            Route::post('wallet/withdraw',                  [DeliveryController::class, 'withdrawRequest']);
            Route::post('documents/upload',                 [DeliveryController::class, 'uploadDocument']);
            // Order chat (driver ↔ customer) — same controller, role:deliveryman guard
            Route::get('orders/{order}/chat',               [OrderChatController::class, 'messages']);
            Route::post('orders/{order}/chat',              [OrderChatController::class, 'send']);
        });

        // ─── VENDOR ───────────────────────────────────────────────
        Route::prefix('vendor')->middleware('role:vendor_owner,vendor_employee')->group(function () {
            Route::get('dashboard',         [VendorDashboardController::class, 'index']);
            Route::post('store/toggle',     [VendorDashboardController::class, 'toggleStore']);
            Route::post('fcm-token',           [VendorDashboardController::class, 'updateFcmToken']);
            Route::post('test-notification',   [VendorDashboardController::class, 'testNotification']);


            Route::get('orders',            [VendorOrderController::class, 'index']);
            Route::get('orders/{order}',    [VendorOrderController::class, 'show']);
            Route::post('orders/{order}/accept',     [VendorOrderController::class, 'accept']);
            Route::post('orders/{order}/reject',     [VendorOrderController::class, 'reject']);
            Route::post('orders/{order}/ready',      [VendorOrderController::class, 'markReady']);

            Route::get('categories',            [VendorProductController::class, 'categories']);
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

            // SOC — Security Operations Center (requires platform.audit.view gate)
            Route::middleware('can:platform.audit.view')->group(function () {
                Route::get('soc/stats',  [\App\Http\Controllers\Api\Admin\SocController::class, 'stats']);
                Route::get('soc/events', [\App\Http\Controllers\Api\Admin\SocController::class, 'recentEvents']);
            });

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

// ─── Podcast Platform ─────────────────────────────────────────────────────────
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::prefix('podcast')->group(function () {
        // Home + Discovery
        Route::get('home',                           [PodcastHomeController::class,    'home']);
        Route::get('stats',                          [PodcastHomeController::class,    'stats']);
        Route::get('top-charts',                     [PodcastHomeController::class,    'topCharts']);
        Route::get('live-rooms',                     [PodcastHomeController::class,    'liveRooms']);

        // Categories
        Route::get('categories',                     [PodcastCategoryController::class,'index']);
        Route::get('categories/{slug}',              [PodcastCategoryController::class,'show']);

        // Podcasts (Shows)
        Route::get('shows',                          [PodcastController::class,        'index']);
        Route::get('shows/{slug}',                   [PodcastController::class,        'show']);
        Route::get('following',                      [PodcastController::class,        'following']);
        Route::post('shows/{id}/follow',             [PodcastController::class,        'follow']);

        // Episodes
        Route::get('episodes/{slug}',                [PodcastEpisodeController::class, 'show']);
        Route::post('episodes/{id}/play',            [PodcastEpisodeController::class, 'recordPlay']);
        Route::post('episodes/{id}/like',            [PodcastEpisodeController::class, 'like']);
        Route::post('episodes/{id}/save',            [PodcastEpisodeController::class, 'save']);

        // Comments
        Route::get('episodes/{id}/comments',         [PodcastCommentController::class, 'index']);
        Route::post('episodes/{id}/comments',        [PodcastCommentController::class, 'store']);
        Route::get('comments/{id}/replies',          [PodcastCommentController::class, 'replies']);
        Route::post('comments/{id}/like',            [PodcastCommentController::class, 'like']);
        Route::delete('comments/{id}',               [PodcastCommentController::class, 'destroy']);

        // Library
        Route::get('library',                        [PodcastLibraryController::class, 'index']);
        Route::get('library/history',                [PodcastLibraryController::class, 'history']);
        Route::get('library/queue',                  [PodcastLibraryController::class, 'queue']);
        Route::post('library/queue/{episodeId}',     [PodcastLibraryController::class, 'addToQueue']);
        Route::delete('library/queue/{episodeId}',   [PodcastLibraryController::class, 'removeFromQueue']);
        Route::get('library/playlists',              [PodcastLibraryController::class, 'playlists']);
        Route::post('library/playlists',             [PodcastLibraryController::class, 'createPlaylist']);
        Route::post('library/playlists/{id}/episodes/{episodeId}', [PodcastLibraryController::class, 'addToPlaylist']);
        Route::get('saved',                          [PodcastEpisodeController::class, 'saved']);

        // Search + Recommendations
        Route::get('search',                         [PodcastSearchController::class,  'search']);
        Route::get('recommendations',                [PodcastSearchController::class,  'recommendations']);

        // Creator / Publish
        Route::get('my-shows',                       [PodcastPublishController::class, 'myShows']);
        Route::post('shows',                         [PodcastPublishController::class, 'createShow']);
        Route::put('shows/{id}',                     [PodcastPublishController::class, 'updateShow']);
        Route::post('episodes',                      [PodcastPublishController::class, 'quickPublish']);
        Route::post('shows/{id}/episodes',           [PodcastPublishController::class, 'publishEpisode']);
        Route::delete('episodes/{id}',               [PodcastPublishController::class, 'deleteEpisode']);
        Route::post('rss-import',                    [PodcastPublishController::class, 'rssImport']);
    });
});

// ─── Calls ────────────────────────────────────────────────────────────────────
Route::prefix('v1/calls')->middleware('auth:sanctum')->group(function () {
    Route::post('/',            [CallController::class, 'initiate']);
    Route::post('{id}/accept',  [CallController::class, 'accept']);
    Route::post('{id}/reject',  [CallController::class, 'reject']);
    Route::post('{id}/end',     [CallController::class, 'end']);
    Route::get('history',       [CallController::class, 'history']);
});

// ─── Live Rooms + Gifts ────────────────────────────────────────────────────────
Route::prefix('v1/live')->middleware('auth:sanctum')->group(function () {
    Route::get('rooms',                    [LiveRoomController::class, 'index']);
    Route::get('rooms/past',               [LiveRoomController::class, 'past']);
    Route::post('rooms',                   [LiveRoomController::class, 'create']);
    Route::post('rooms/{id}/join',         [LiveRoomController::class, 'join']);
    Route::post('rooms/{id}/leave',        [LiveRoomController::class, 'leave']);
    Route::post('rooms/{id}/end',          [LiveRoomController::class, 'end']);
    Route::post('rooms/{id}/message',      [LiveRoomController::class, 'message']);

    Route::get('gifts',                    [GiftController::class, 'index']);
    Route::post('rooms/{id}/gifts',        [GiftController::class, 'send']);
    Route::get('rooms/{id}/top-gifters',   [GiftController::class, 'topGifters']);

    // Coins
    Route::get('coins/balance',            [CoinController::class, 'balance']);
    Route::get('coins/packages',           [CoinController::class, 'packages']);
    Route::post('coins/buy',               [CoinController::class, 'buy']);
    Route::get('coins/history',            [CoinController::class, 'history']);

    // Multi-guest
    Route::get('rooms/{id}/guests',             [LiveGuestController::class, 'index']);
    Route::post('rooms/{id}/guest/request',     [LiveGuestController::class, 'request']);
    Route::post('rooms/{id}/guest/cancel',      [LiveGuestController::class, 'cancel']);
    Route::post('rooms/{id}/guest/accept/{requestId}', [LiveGuestController::class, 'accept']);
    Route::post('rooms/{id}/guest/reject/{requestId}', [LiveGuestController::class, 'reject']);
    Route::post('rooms/{id}/guest/{userId}/remove', [LiveGuestController::class, 'remove']);
    Route::post('rooms/{id}/guest/{userId}/mute',   [LiveGuestController::class, 'mute']);

    // PK Battle
    Route::get('rooms/{id}/battle/hosts',         [LiveBattleController::class, 'availableHosts']);
    Route::post('rooms/{id}/battle/invite',       [LiveBattleController::class, 'invite']);
    Route::get('rooms/{id}/battle/status',        [LiveBattleController::class, 'status']);
    Route::post('battle/accept/{inviteId}',       [LiveBattleController::class, 'accept']);
    Route::post('battle/reject/{inviteId}',       [LiveBattleController::class, 'reject']);
    Route::get('battle/{battleId}/viewer-token',  [LiveBattleController::class, 'viewerToken']);
    Route::post('battle/{battleId}/end',          [LiveBattleController::class, 'end']);

    // Subscriptions
    Route::get('hosts/{hostId}/subscription-tiers',  [LiveSubscriptionController::class, 'tiers']);
    Route::post('hosts/{hostId}/subscribe',           [LiveSubscriptionController::class, 'subscribe']);
    Route::get('hosts/{hostId}/my-subscription',      [LiveSubscriptionController::class, 'mySubscription']);
    Route::post('hosts/my/subscription-tiers',        [LiveSubscriptionController::class, 'setTiers']);
    Route::get('rooms/{id}/subscriber-check',         [LiveSubscriptionController::class, 'roomCheck']);

    // Live Goals
    Route::get('rooms/{id}/goal',    [LiveGoalController::class, 'get']);
    Route::post('rooms/{id}/goal',   [LiveGoalController::class, 'set']);
    Route::delete('rooms/{id}/goal', [LiveGoalController::class, 'cancel']);

    // Q&A
    Route::post('rooms/{id}/questions',                    [LiveQAController::class, 'submit']);
    Route::get('rooms/{id}/questions',                     [LiveQAController::class, 'list']);
    Route::post('rooms/{id}/questions/{qId}/activate',     [LiveQAController::class, 'activate']);
    Route::post('rooms/{id}/questions/{qId}/dismiss',      [LiveQAController::class, 'dismiss']);

    // Raid
    Route::get('rooms/{id}/raid/targets',  [LiveRaidController::class, 'targets']);
    Route::post('rooms/{id}/raid',         [LiveRaidController::class, 'raid']);

    // Stats + likes
    Route::get('rooms/{id}/stats',   [LiveStatsController::class, 'stats']);
    Route::post('rooms/{id}/like',   [LiveStatsController::class, 'like']);

    // Leaderboard
    Route::get('rooms/{id}/leaderboard', [LiveLeaderboardController::class, 'room']);
    Route::get('leaderboard/global',     [LiveLeaderboardController::class, 'global']);

    // Safety — report a live room
    Route::post('rooms/{id}/report', [LiveReportController::class, 'report']);

    // Discovery
    Route::get('discovery',              [LiveDiscoveryController::class, 'index']);
    Route::get('discovery/categories',   [LiveDiscoveryController::class, 'categories']);
    Route::get('discovery/recommended',  [LiveDiscoveryController::class, 'recommended']);
    Route::get('discovery/search',       [LiveDiscoveryController::class, 'search']);

    // Moderation
    Route::get('rooms/{id}/settings',                      [LiveModerationController::class, 'settings']);
    Route::patch('rooms/{id}/settings',                    [LiveModerationController::class, 'updateSettings']);
    Route::post('rooms/{id}/chat/{userId}/mute',           [LiveModerationController::class, 'muteUser']);
    Route::delete('rooms/{id}/chat/{userId}/mute',         [LiveModerationController::class, 'unmuteUser']);
    Route::post('rooms/{id}/chat/pin/{messageId}',         [LiveModerationController::class, 'pinMessage']);
    Route::delete('rooms/{id}/chat/pin',                   [LiveModerationController::class, 'unpinMessage']);

    // VOD
    Route::get('vod',                  [LiveVODController::class, 'index']);
    Route::get('vod/host/{hostId}',    [LiveVODController::class, 'byHost']);
    Route::post('vod/{id}/view',       [LiveVODController::class, 'view']);
});

// ── Inbox / Chat / Support / Marketing ───────────────────────────────────────
Route::prefix('v1/inbox')->middleware('auth:sanctum')->group(function () {
    $ic = \App\Http\Controllers\Api\InboxController::class;

    // Marketing broadcasts
    Route::get('broadcasts',                        [$ic, 'broadcasts']);
    Route::get('broadcasts/{uuid}',                 [$ic, 'getBroadcast']);
    Route::post('broadcasts/{uuid}/read',           [$ic, 'markBroadcastRead']);
    Route::post('broadcasts/{uuid}/cta',            [$ic, 'trackCtaClick']);

    // Support conversations
    Route::get('conversations',                     [$ic, 'conversations']);
    Route::post('conversations',                    [$ic, 'createConversation']);
    Route::get('conversations/{uuid}/messages',     [$ic, 'messages']);
    Route::post('conversations/{uuid}/messages',    [$ic, 'sendMessage']);
    Route::post('conversations/{uuid}/typing',      [$ic, 'typing']);
    Route::post('conversations/{uuid}/reopen',      [$ic, 'reopen']);

    // Audio calls
    Route::post('conversations/{uuid}/call',        [$ic, 'initiateCall']);
    Route::post('calls/{callUuid}/join',            [$ic, 'joinCall']);
    Route::post('calls/{callUuid}/end',             [$ic, 'endCall']);
    Route::post('calls/{callUuid}/ice',             [$ic, 'sendIceCandidate']);
});

// ── Admin Inbox / Marketing ───────────────────────────────────────────────────
Route::prefix('v1/admin/inbox')->middleware(['auth:sanctum', 'role:super_admin,admin,support_agent'])->group(function () {
    $ai = \App\Http\Controllers\Admin\AdminInboxController::class;

    Route::get('stats',                              [$ai, 'stats']);
    Route::get('conversations',                      [$ai, 'conversations']);
    Route::get('conversations/{uuid}/messages',      [$ai, 'conversationMessages']);
    Route::post('conversations/{uuid}/reply',        [$ai, 'reply']);
    Route::post('conversations/{uuid}/assign',       [$ai, 'assignAgent']);
    Route::patch('conversations/{uuid}/status',      [$ai, 'updateStatus']);
    Route::post('conversations/{uuid}/typing',       [$ai, 'typingIndicator']);

    Route::get('broadcasts',                         [$ai, 'broadcasts']);
    Route::post('broadcasts',                        [$ai, 'createBroadcast']);
    Route::post('broadcasts/{uuid}/send',            [$ai, 'sendBroadcast']);
    Route::get('broadcasts/{uuid}/stats',            [$ai, 'broadcastStats']);
});

    // Admin engagement generator
    Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
        Route::post('engagement/likes', [\App\Http\Controllers\Api\Admin\EngagementGeneratorController::class, 'generateLikes']);
        Route::post('engagement/views', [\App\Http\Controllers\Api\Admin\EngagementGeneratorController::class, 'generateViews']);
        Route::post('engagement/comments', [\App\Http\Controllers\Api\Admin\EngagementGeneratorController::class, 'generateComments']);
        Route::post('engagement/generate', [\App\Http\Controllers\Api\Admin\EngagementGeneratorController::class, 'generateAll']);
        Route::get('engagement/bots', [\App\Http\Controllers\Api\Admin\EngagementGeneratorController::class, 'botUsers']);
    });

// Legal pages (public)
Route::get('/pages/{slug}', [\App\Http\Controllers\Api\LegalPageController::class, 'show']);

// ── Global eCommerce API ───────────────────────────────────────────────────────
Route::prefix('v1/global')->group(function () {
    // Store status — public (no auth needed, Flutter checks on startup)
    Route::get('/store-status', function () {
        $enabled = \App\Models\Global\GlobalSetting::getBool('global_store_enabled', true);
        return response()->json(['enabled' => $enabled]);
    });
    Route::get('/crypto-status', function () {
        $enabled = \App\Models\Global\GlobalSetting::getBool('crypto_exchange_enabled', true);
        return response()->json(['enabled' => $enabled]);
    });

    // Public endpoints
    Route::post('/auth/register',          [\App\Http\Controllers\Api\Global\GlobalAuthController::class, 'register']);
    Route::post('/auth/login',             [\App\Http\Controllers\Api\Global\GlobalAuthController::class, 'login']);
    Route::post('/auth/google',            [\App\Http\Controllers\Api\Global\GlobalAuthController::class, 'googleAuth']);
    Route::get('/products',                [\App\Http\Controllers\Api\Global\GlobalProductsController::class, 'index']);
    Route::get('/products/featured',       [\App\Http\Controllers\Api\Global\GlobalProductsController::class, 'featured']);
    Route::get('/products/flash',          [\App\Http\Controllers\Api\Global\GlobalProductsController::class, 'flash']);
    Route::get('/products/new-arrivals',   [\App\Http\Controllers\Api\Global\GlobalProductsController::class, 'newArrivals']);
    Route::get('/products/best-sellers',   [\App\Http\Controllers\Api\Global\GlobalProductsController::class, 'bestSellers']);

    // Coupons (public: available deals; auth: collect + my wallet)
    Route::get('/coupons/available',       [\App\Http\Controllers\Api\Global\GlobalCouponsController::class, 'available']);
    Route::post('/coupons/validate',       [\App\Http\Controllers\Api\Global\GlobalCouponsController::class, 'validate']);
    Route::get('/products/{id}',           [\App\Http\Controllers\Api\Global\GlobalProductsController::class, 'show']);
    Route::get('/products/{id}/reviews',   [\App\Http\Controllers\Api\Global\GlobalReviewsController::class, 'index']);
    Route::get('/categories',              [\App\Http\Controllers\Api\Global\GlobalProductsController::class, 'categories']);
    Route::get('/sliders',                 [\App\Http\Controllers\Api\Global\GlobalSliderController::class, 'index']);

    // Stripe/PayPal webhooks (public, no auth)
    Route::post('/checkout/stripe/webhook',  [\App\Http\Controllers\Api\Global\GlobalCheckoutController::class, 'stripeWebhook']);
    Route::post('/checkout/paypal/webhook',  [\App\Http\Controllers\Api\Global\GlobalCheckoutController::class, 'paypalWebhook']);

    // Authenticated endpoints
    Route::middleware('auth:global_users')->group(function () {
        Route::post('/auth/logout',            [\App\Http\Controllers\Api\Global\GlobalAuthController::class, 'logout']);
        Route::get('/auth/me',                 [\App\Http\Controllers\Api\Global\GlobalAuthController::class, 'me']);
        Route::put('/auth/profile',            [\App\Http\Controllers\Api\Global\GlobalAuthController::class, 'updateProfile']);
        Route::post('/auth/addresses',         [\App\Http\Controllers\Api\Global\GlobalAuthController::class, 'addAddress']);
        Route::put('/auth/addresses/{id}',    [\App\Http\Controllers\Api\Global\GlobalAuthController::class, 'updateAddress']);

        Route::get('/cart',                    [\App\Http\Controllers\Api\Global\GlobalCartController::class, 'index']);
        Route::post('/cart',                   [\App\Http\Controllers\Api\Global\GlobalCartController::class, 'add']);
        Route::put('/cart/{id}',               [\App\Http\Controllers\Api\Global\GlobalCartController::class, 'update']);
        Route::delete('/cart/{id}',            [\App\Http\Controllers\Api\Global\GlobalCartController::class, 'remove']);
        Route::delete('/cart',                 [\App\Http\Controllers\Api\Global\GlobalCartController::class, 'clear']);

        Route::get('/checkout/summary',        [\App\Http\Controllers\Api\Global\GlobalCheckoutController::class, 'summary']);
        Route::post('/checkout/stripe',            [\App\Http\Controllers\Api\Global\GlobalCheckoutController::class, 'stripe']);
        Route::post('/checkout/stripe-web-session',[\App\Http\Controllers\Api\Global\GlobalCheckoutController::class, 'stripeWebSession']);
        Route::post('/checkout/paypal',            [\App\Http\Controllers\Api\Global\GlobalCheckoutController::class, 'paypal']);

        Route::get('/orders',                  [\App\Http\Controllers\Api\Global\GlobalOrdersController::class, 'index']);
        Route::get('/orders/{id}',             [\App\Http\Controllers\Api\Global\GlobalOrdersController::class, 'show']);

        Route::get('/products/{id}/reviews/can-review', [\App\Http\Controllers\Api\Global\GlobalReviewsController::class, 'canReview']);
        Route::post('/products/{id}/reviews',  [\App\Http\Controllers\Api\Global\GlobalReviewsController::class, 'store']);

        // Coupons (auth required)
        Route::get('/coupons/my',              [\App\Http\Controllers\Api\Global\GlobalCouponsController::class, 'myCoupons']);
        Route::post('/coupons/collect-all',    [\App\Http\Controllers\Api\Global\GlobalCouponsController::class, 'collectAll']);
        Route::post('/coupons/{coupon}/collect',[\App\Http\Controllers\Api\Global\GlobalCouponsController::class, 'collect']);

        // Confirm endpoints (also authenticated)
        Route::post('/checkout/stripe/confirm',[\App\Http\Controllers\Api\Global\GlobalCheckoutController::class, 'stripeConfirm']);
        Route::post('/checkout/paypal/capture',[\App\Http\Controllers\Api\Global\GlobalCheckoutController::class, 'paypalCapture']);
    });
});

// ── Phase 6: Employee Self-Service (Sanctum) ──────────────────────────────────
Route::prefix('v1/hr/me')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->name('api.hr.me.')
    ->group(function () {
        $ctrl = \App\Http\Controllers\Api\HR\HrSelfServiceController::class;

        Route::get('/',                       [$ctrl, 'profile'])->name('profile');
        Route::get('/attendance',             [$ctrl, 'attendance'])->name('attendance');
        Route::post('/attendance/check-in',   [$ctrl, 'checkIn'])->name('check_in');
        Route::post('/attendance/check-out',  [$ctrl, 'checkOut'])->name('check_out');
        Route::get('/leave/balances',         [$ctrl, 'leaveBalances'])->name('leave.balances');
        Route::get('/leave/requests',         [$ctrl, 'leaveRequests'])->name('leave.requests');
        Route::post('/leave/requests',        [$ctrl, 'submitLeave'])->name('leave.store');
        Route::get('/payslips',               [$ctrl, 'payslips'])->name('payslips');
        Route::get('/payslips/{id}/pdf',      [$ctrl, 'payslipPdf'])->name('payslips.pdf');
        Route::get('/announcements',          [$ctrl, 'announcements'])->name('announcements');
    });
