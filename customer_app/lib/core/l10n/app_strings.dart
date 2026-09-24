// ─────────────────────────────────────────────────────────────────────────────
// eSahlan — App Translations
// Languages: English (en) | Somali (so) | Arabic (ar)
// ─────────────────────────────────────────────────────────────────────────────

import 'package:flutter/material.dart';
import '../providers/app_settings_provider.dart';

// ── InheritedWidget ──────────────────────────────────────────────────────────
/// Placed in MaterialApp.builder so ALL widgets rebuild when language changes.
class AppLangScope extends InheritedWidget {
  final String language;
  const AppLangScope({super.key, required this.language, required super.child});

  static String of(BuildContext context) {
    return context
            .dependOnInheritedWidgetOfExactType<AppLangScope>()
            ?.language ??
        AppSettingsNotifier.current.language;
  }

  @override
  bool updateShouldNotify(AppLangScope old) => old.language != language;
}

// ── Translation helper ────────────────────────────────────────────────────────
/// Usage:  AppL10n.of(context).home
///         AppL10n.of(context).tr('anyKey')
class AppL10n {
  final String _lang;
  const AppL10n._(this._lang);

  /// Reads language from InheritedWidget — widgets auto-rebuild on language change.
  static AppL10n of(BuildContext context) {
    return AppL10n._(AppLangScope.of(context));
  }

  /// Use when you don't have a BuildContext (e.g. in popups, background code)
  static AppL10n get current => AppL10n._(AppSettingsNotifier.current.language);

  /// Returns current language code: 'en' | 'so' | 'ar'
  String get langCode => _lang;

  bool get isArabic => _lang == 'ar';
  bool get isSomali => _lang == 'so';
  bool get isRtl => _lang == 'ar';

  /// Generic key-based lookup
  String tr(String key) => _t(key, _lang);

  // ── Navigation ───────────────────────────────────────────────────────────────
  String get home        => _t('home', _lang);
  String get community   => _t('community', _lang);
  String get profile     => _t('profile', _lang);
  String get search      => _t('search', _lang);
  String get cart        => _t('cart', _lang);
  String get myCart      => _t('myCart', _lang);
  String get messages    => _t('messages', _lang);
  String get explore     => _t('explore', _lang);

  // ── Auth ─────────────────────────────────────────────────────────────────────
  String get login       => _t('login', _lang);
  String get logout      => _t('logout', _lang);
  String get register    => _t('register', _lang);
  String get email       => _t('email', _lang);
  String get password    => _t('password', _lang);
  String get name        => _t('name', _lang);
  String get phone       => _t('phone', _lang);
  String get continueBtn => _t('continueBtn', _lang);
  String get orContinueWith => _t('orContinueWith', _lang);
  String get forgotPassword => _t('forgotPassword', _lang);
  String get createAccount  => _t('createAccount', _lang);
  String get alreadyHaveAccount => _t('alreadyHaveAccount', _lang);
  String get dontHaveAccount    => _t('dontHaveAccount', _lang);
  String get signIn      => _t('signIn', _lang);
  String get signUp      => _t('signUp', _lang);

  // ── Common Actions ────────────────────────────────────────────────────────────
  String get save        => _t('save', _lang);
  String get cancel      => _t('cancel', _lang);
  String get deleteBtn   => _t('deleteBtn', _lang);
  String get edit        => _t('edit', _lang);
  String get submit      => _t('submit', _lang);
  String get sendBtn     => _t('sendBtn', _lang);
  String get back        => _t('back', _lang);
  String get done        => _t('done', _lang);
  String get apply       => _t('apply', _lang);
  String get remove      => _t('remove', _lang);
  String get retry       => _t('retry', _lang);
  String get loading     => _t('loading', _lang);
  String get error       => _t('error', _lang);
  String get success     => _t('success', _lang);
  String get noData      => _t('noData', _lang);
  String get seeAll      => _t('seeAll', _lang);
  String get viewAll     => _t('viewAll', _lang);
  String get close       => _t('close', _lang);
  String get confirm     => _t('confirm', _lang);

  // ── Settings ─────────────────────────────────────────────────────────────────
  String get settings         => _t('settings', _lang);
  String get language         => _t('language', _lang);
  String get appearance       => _t('appearance', _lang);
  String get notifications    => _t('notifications', _lang);
  String get privacy          => _t('privacy', _lang);
  String get security         => _t('security', _lang);
  String get account          => _t('account', _lang);
  String get darkMode         => _t('darkMode', _lang);
  String get lightMode        => _t('lightMode', _lang);
  String get systemMode       => _t('systemMode', _lang);
  String get dataSaver        => _t('dataSaver', _lang);
  String get videoSettings    => _t('videoSettings', _lang);
  String get textSize         => _t('textSize', _lang);
  String get contentPrefs     => _t('contentPrefs', _lang);

  // ── Language names ────────────────────────────────────────────────────────────
  String get langEnglish => _t('langEnglish', _lang);
  String get langSomali  => _t('langSomali', _lang);
  String get langArabic  => _t('langArabic', _lang);
  String get chooseLanguage => _t('chooseLanguage', _lang);

  // ── Global Store ─────────────────────────────────────────────────────────────
  String get globalStore    => _t('globalStore', _lang);
  String get products       => _t('products', _lang);
  String get categories     => _t('categories', _lang);
  String get newArrivals    => _t('newArrivals', _lang);
  String get bestSellers    => _t('bestSellers', _lang);
  String get flashDeals     => _t('flashDeals', _lang);
  String get specialDeals   => _t('specialDeals', _lang);
  String get featured       => _t('featured', _lang);
  String get addToCart      => _t('addToCart', _lang);
  String get buyNow         => _t('buyNow', _lang);
  String get checkout       => _t('checkout', _lang);
  String get orderSummary   => _t('orderSummary', _lang);
  String get subtotal       => _t('subtotal', _lang);
  String get total          => _t('total', _lang);
  String get shipping       => _t('shipping', _lang);
  String get payment        => _t('payment', _lang);
  String get orders         => _t('orders', _lang);
  String get myOrders       => _t('myOrders', _lang);
  String get placeOrder     => _t('placeOrder', _lang);
  String get orderPlaced    => _t('orderPlaced', _lang);
  String get trackOrder     => _t('trackOrder', _lang);
  String get inStock        => _t('inStock', _lang);
  String get outOfStock     => _t('outOfStock', _lang);
  String get quantity       => _t('quantity', _lang);
  String get reviews        => _t('reviews', _lang);
  String get writeReview    => _t('writeReview', _lang);
  String get rating         => _t('rating', _lang);
  String get description    => _t('description', _lang);
  String get price          => _t('price', _lang);
  String get discount       => _t('discount', _lang);
  String get sold           => _t('sold', _lang);
  String get variants       => _t('variants', _lang);
  String get productNotFound => _t('productNotFound', _lang);
  String get showLess       => _t('showLess', _lang);
  String get readMore       => _t('readMore', _lang);
  String get openLabel      => _t('openLabel', _lang);
  String get closedLabel    => _t('closedLabel', _lang);
  String inStockCount(int n) => _t('inStock', _lang) + ' ($n ${_t("left", _lang)})';

  // ── Coupon ───────────────────────────────────────────────────────────────────
  String get coupon          => _t('coupon', _lang);
  String get myCoupons       => _t('myCoupons', _lang);
  String get collectAll      => _t('collectAll', _lang);
  String get collect         => _t('collect', _lang);
  String get collected       => _t('collected', _lang);
  String get applyCoupon     => _t('applyCoupon', _lang);
  String get couponApplied   => _t('couponApplied', _lang);
  String get enterCouponCode => _t('enterCouponCode', _lang);
  String get couponCode      => _t('couponCode', _lang);
  String get clearAll        => _t('clearAll', _lang);
  String get browseProducts  => _t('browseProducts', _lang);
  String get addProductsToStart => _t('addProductsToStart', _lang);
  String get viewOrders      => _t('viewOrders', _lang);
  String get orderPlacedSuccess => _t('orderPlacedSuccess', _lang);
  String get tapToChangeDistrict => _t('tapToChangeDistrict', _lang);
  String get chooseWhereToDeliver => _t('chooseWhereToDeliver', _lang);
  String get selectDistrictBtn => _t('selectDistrictBtn', _lang);
  String get minOrder        => _t('minOrder', _lang);
  String get expiresIn       => _t('expiresIn', _lang);
  String get expiresLabel    => _t('expiresLabel', _lang);
  String get newUserOnly     => _t('newUserOnly', _lang);
  String get specialDealsForYou => _t('specialDealsForYou', _lang);

  // ── Address ──────────────────────────────────────────────────────────────────
  String get address     => _t('address', _lang);
  String get addAddress  => _t('addAddress', _lang);
  String get city        => _t('city', _lang);
  String get country     => _t('country', _lang);
  String get zipCode     => _t('zipCode', _lang);
  String get firstName   => _t('firstName', _lang);
  String get lastName    => _t('lastName', _lang);

  // ── Community ────────────────────────────────────────────────────────────────
  String get feed        => _t('feed', _lang);
  String get reels       => _t('reels', _lang);
  String get stories     => _t('stories', _lang);
  String get like        => _t('like', _lang);
  String get comment     => _t('comment', _lang);
  String get share       => _t('share', _lang);
  String get follow      => _t('follow', _lang);
  String get unfollow    => _t('unfollow', _lang);
  String get following   => _t('following', _lang);
  String get followers   => _t('followers', _lang);
  String get posts       => _t('posts', _lang);
  String get createPost  => _t('createPost', _lang);
  String get chat        => _t('chat', _lang);
  String get online      => _t('online', _lang);
  String get offline     => _t('offline', _lang);
  String get typing      => _t('typing', _lang);
  String get writeSomething => _t('writeSomething', _lang);

  // ── Profile ──────────────────────────────────────────────────────────────────
  String get myAccount      => _t('myAccount', _lang);
  String get preferences    => _t('preferences', _lang);
  String get supportLegal   => _t('supportLegal', _lang);
  String get fullName       => _t('fullName', _lang);
  String get phoneNumber    => _t('phoneNumber', _lang);
  String get emailAddress   => _t('emailAddress', _lang);
  String get district       => _t('district', _lang);
  String get notAdded       => _t('notAdded', _lang);
  String get notSet         => _t('notSet', _lang);
  String get small          => _t('small', _lang);
  String get medium         => _t('medium', _lang);
  String get large          => _t('large', _lang);
  String get extraLarge     => _t('extraLarge', _lang);
  String get reduceDataQuality => _t('reduceDataQuality', _lang);
  String get helpCenter     => _t('helpCenter', _lang);
  String get termsOfService => _t('termsOfService', _lang);
  String get privacyPolicy  => _t('privacyPolicy', _lang);
  String get contactUs      => _t('contactUs', _lang);
  String get deleteAccount        => _t('deleteAccount', _lang);
  String get deleteAccountSub     => _t('deleteAccountSub', _lang);
  String get deleteAccountTitle   => _t('deleteAccountTitle', _lang);
  String get deleteAccountWarn    => _t('deleteAccountWarn', _lang);
  String get deleteAccountConfirm => _t('deleteAccountConfirm', _lang);
  String get deleteAccountSuccess => _t('deleteAccountSuccess', _lang);
  String get signOut        => _t('signOut', _lang);
  String get signOutSub     => _t('signOutSub', _lang);
  String get signOutTitle   => _t('signOutTitle', _lang);
  String get helpSupport    => _t('helpSupport', _lang);
  String get aboutApp       => _t('aboutApp', _lang);
  String get rateApp        => _t('rateApp', _lang);
  String get changeRegion   => _t('changeRegion', _lang);
  String get changeRegionMsg => _t('changeRegionMsg', _lang);
  String get change         => _t('change', _lang);
  String get light          => _t('light', _lang);
  String get dark           => _t('dark', _lang);
  String get systemDefault  => _t('systemDefault', _lang);

  // ── Status messages ───────────────────────────────────────────────────────────
  String get pleaseWait  => _t('pleaseWait', _lang);
  String get somethingWentWrong => _t('somethingWentWrong', _lang);
  String get noInternet  => _t('noInternet', _lang);
  String get tryAgain    => _t('tryAgain', _lang);
  String get loginRequired => _t('loginRequired', _lang);

  // ── Auth (extended) ───────────────────────────────────────────────────────────
  String get welcomeBack      => _t('welcomeBack', _lang);
  String get signInSubtitle   => _t('signInSubtitle', _lang);
  String get phoneAndPin      => _t('phoneAndPin', _lang);
  String get emailAndPass     => _t('emailAndPass', _lang);
  String get pinCode          => _t('pinCode', _lang);
  String get fourDigits       => _t('fourDigits', _lang);
  String get yourPassword     => _t('yourPassword', _lang);
  String get continueGoogle   => _t('continueGoogle', _lang);
  String get orDivider        => _t('orDivider', _lang);
  String get enterPhone       => _t('enterPhone', _lang);
  String get enterPin         => _t('enterPin', _lang);
  String get enterEmailAddr   => _t('enterEmailAddr', _lang);
  String get enterPassword    => _t('enterPassword', _lang);
  String get joinEsahlan      => _t('joinEsahlan', _lang);
  String get referralCode     => _t('referralCode', _lang);
  String get securityMethod   => _t('securityMethod', _lang);
  String get createPin        => _t('createPin', _lang);
  String get quickEasySignIn  => _t('quickEasySignIn', _lang);
  String get pinOption        => _t('pinOption', _lang);
  String get pinSubtitle      => _t('pinSubtitle', _lang);
  String get emailStrong      => _t('emailStrong', _lang);
  String get emailStrongSub   => _t('emailStrongSub', _lang);
  String get confirmPassword  => _t('confirmPassword', _lang);
  String get reenterPass      => _t('reenterPass', _lang);
  String get createStrongPass => _t('createStrongPass', _lang);
  String get selectDistrict   => _t('selectDistrict', _lang);
  String get requiredLabel    => _t('requiredLabel', _lang);
  String get failedDistricts  => _t('failedDistricts', _lang);
  String get weakPassword     => _t('weakPassword', _lang);
  String get fairPassword     => _t('fairPassword', _lang);
  String get goodPassword     => _t('goodPassword', _lang);
  String get strongPassword   => _t('strongPassword', _lang);
  String get pwCheck8chars    => _t('pwCheck8chars', _lang);
  String get pwCheckUpper     => _t('pwCheckUpper', _lang);
  String get pwCheckLower     => _t('pwCheckLower', _lang);
  String get pwCheckDigit     => _t('pwCheckDigit', _lang);
  String get pwCheckSymbol    => _t('pwCheckSymbol', _lang);

  // ── OTP ──────────────────────────────────────────────────────────────────────
  String get verifyNumber     => _t('verifyNumber', _lang);
  String get codeSentTo       => _t('codeSentTo', _lang);
  String get enterOtp         => _t('enterOtp', _lang);
  String get didntReceive     => _t('didntReceive', _lang);
  String get resend           => _t('resend', _lang);
  String get verify           => _t('verify', _lang);
  String get verifying        => _t('verifying', _lang);

  // ── Home (extended) ───────────────────────────────────────────────────────────
  String get ourServices      => _t('ourServices', _lang);
  String get exploreNow       => _t('exploreNow', _lang);
  String get searchHint       => _t('searchHint', _lang);

  // ── Community Chat ────────────────────────────────────────────────────────────
  String get chats            => _t('chats', _lang);
  String get noChats          => _t('noChats', _lang);
  String get startChat        => _t('startChat', _lang);
  String get searchMessages   => _t('searchMessages', _lang);
  String get noMessages       => _t('noMessages', _lang);
  String get typeMessage      => _t('typeMessage', _lang);
  String get send             => _t('send', _lang);
  String get sayHi            => _t('sayHi', _lang);
  String get lastSeenRecently => _t('lastSeenRecently', _lang);
  String get seen             => _t('seen', _lang);
  String get delivered        => _t('delivered', _lang);

  // ── Community Explore ─────────────────────────────────────────────────────────
  String get searchCommunity  => _t('searchCommunity', _lang);
  String get peopleYouMayKnow => _t('peopleYouMayKnow', _lang);
  String get trendingPosts    => _t('trendingPosts', _lang);
  String get noTrendingPosts  => _t('noTrendingPosts', _lang);

  // ── Orders (extended) ─────────────────────────────────────────────────────────
  String get tabAll           => _t('tabAll', _lang);
  String get tabPending       => _t('tabPending', _lang);
  String get tabPreparing     => _t('tabPreparing', _lang);
  String get tabDelivered     => _t('tabDelivered', _lang);
  String get tabCancelled     => _t('tabCancelled', _lang);
  String get ordersWillAppear => _t('ordersWillAppear', _lang);
  String get trackBtn         => _t('trackBtn', _lang);
  String get orderItems       => _t('orderItems', _lang);
  String get paymentSummary   => _t('paymentSummary', _lang);
  String get statusTimeline   => _t('statusTimeline', _lang);
  String get deliveryFee      => _t('deliveryFee', _lang);
  String get shippingAddress  => _t('shippingAddress', _lang);
  String get noOrdersYet      => _t('noOrdersYet', _lang);
  String get orderDetails     => _t('orderDetails', _lang);
  String get cancelOrder      => _t('cancelOrder', _lang);
  String get cancelOrderQ     => _t('cancelOrderQ', _lang);
  String get cancelOrderConfirm => _t('cancelOrderConfirm', _lang);
  String get no               => _t('no', _lang);
  String get yesCancel        => _t('yesCancel', _lang);

  // ── Global Store (extended) ───────────────────────────────────────────────────
  String get myGlobalOrders      => _t('myGlobalOrders', _lang);
  String get emptyCart           => _t('emptyCart', _lang);
  String get continueShopping    => _t('continueShopping', _lang);
  String get proceedCheckout     => _t('proceedCheckout', _lang);
  String get clearCart           => _t('clearCart', _lang);
  String get clearBtn            => _t('clearBtn', _lang);
  String get calculatedAtCheckout => _t('calculatedAtCheckout', _lang);
  String get signInToViewCart    => _t('signInToViewCart', _lang);
  String get addedToCart         => _t('addedToCart', _lang);
  String get viewCart            => _t('viewCart', _lang);
  String get chooseOption        => _t('chooseOption', _lang);
  String get shareExperience     => _t('shareExperience', _lang);
  String get helpOthersReview    => _t('helpOthersReview', _lang);
  String get reviewBtn           => _t('reviewBtn', _lang);
  String get customerReviews     => _t('customerReviews', _lang);
  String get noReviewsYet        => _t('noReviewsYet', _lang);
  String get beFirstToReview     => _t('beFirstToReview', _lang);
  String get startShoppingNow    => _t('startShoppingNow', _lang);
  String get signInToViewOrders  => _t('signInToViewOrders', _lang);
  String get startShoppingOrders => _t('startShoppingOrders', _lang);
  String get processing          => _t('processing', _lang);
  String get shipped             => _t('shipped', _lang);
  String get orderCancelled      => _t('orderCancelled', _lang);
  String get orderStatus         => _t('orderStatus', _lang);
  String get items               => _t('items', _lang);
  String get parcelDelivery      => _t('parcelDelivery', _lang);
  String get pickup              => _t('pickup', _lang);
  String get delivery            => _t('delivery', _lang);
  String get packageContents     => _t('packageContents', _lang);

  // ── Home screen (extended) ────────────────────────────────────────────────────
  String get goodMorning         => _t('goodMorning', _lang);
  String get goodAfternoon       => _t('goodAfternoon', _lang);
  String get goodEvening         => _t('goodEvening', _lang);
  String get allServices         => _t('allServices', _lang);
  String get activeOrder         => _t('activeOrder', _lang);
  String get recentOrders        => _t('recentOrders', _lang);
  String get noActiveOrders      => _t('noActiveOrders', _lang);
  String get shopNow             => _t('shopNow', _lang);
  String get trackNow            => _t('trackNow', _lang);
  String get viewOrder           => _t('viewOrder', _lang);
  String get bannerTagline       => _t('bannerTagline', _lang);

  // ── Service names ─────────────────────────────────────────────────────────────
  String get orderFood           => _t('orderFood', _lang);
  String get grocery             => _t('grocery', _lang);
  String get shopping            => _t('shopping', _lang);
  String get moving              => _t('moving', _lang);
  String get rentals             => _t('rentals', _lang);
  String get learning            => _t('learning', _lang);
  String get dataPlans           => _t('dataPlans', _lang);
  String get exchange            => _t('exchange', _lang);
  String get tickets             => _t('tickets', _lang);
  String get laundry             => _t('laundry', _lang);
  String get health              => _t('health', _lang);
  String get wholesale           => _t('wholesale', _lang);

  // ── eFood / eGrocery / eShop ──────────────────────────────────────────────────
  String get popularRestaurants  => _t('popularRestaurants', _lang);
  String get popularItems        => _t('popularItems', _lang);
  String get featuredStores      => _t('featuredStores', _lang);
  String get allRestaurants      => _t('allRestaurants', _lang);
  String get allStores           => _t('allStores', _lang);
  String get nearYou             => _t('nearYou', _lang);
  String get openNow             => _t('openNow', _lang);
  String get closed              => _t('closed', _lang);
  String get freeDelivery        => _t('freeDelivery', _lang);
  String get addItem             => _t('addItem', _lang);
  String get yourCart            => _t('yourCart', _lang);
  String get orderNow            => _t('orderNow', _lang);
  String get deliveryAddress     => _t('deliveryAddress', _lang);
  String get paymentMethod       => _t('paymentMethod', _lang);
  String get free                => _t('free', _lang);
  String get estimatedTime       => _t('estimatedTime', _lang);
  String get specialInstructions => _t('specialInstructions', _lang);
  String get optional            => _t('optional', _lang);
  String get selectPayment       => _t('selectPayment', _lang);
  String get cashOnDelivery      => _t('cashOnDelivery', _lang);
  String get walletPay           => _t('walletPay', _lang);
  String get confirmOrder        => _t('confirmOrder', _lang);
  String get orderConfirmed      => _t('orderConfirmed', _lang);
  String get thankYou            => _t('thankYou', _lang);
  String get foodCategories      => _t('foodCategories', _lang);
  String get topRated            => _t('topRated', _lang);
  String get searchFood          => _t('searchFood', _lang);
  String get searchRestaurants   => _t('searchRestaurants', _lang);
  String get searchFoodHint      => _t('searchFoodHint', _lang);
  String get searchMenuHint      => _t('searchMenuHint', _lang);
  String get restaurantClosed    => _t('restaurantClosed', _lang);
  String get cannotOrder         => _t('cannotOrder', _lang);
  String get menuTab             => _t('menuTab', _lang);
  String get infoTab             => _t('infoTab', _lang);
  String get noResults           => _t('noResults', _lang);
  String get searchFavFood       => _t('searchFavFood', _lang);
  String get distance            => _t('distance', _lang);
  String get deliveryTime        => _t('deliveryTime', _lang);
  String get deliveryBy          => _t('deliveryBy', _lang);
  String get menuCategories      => _t('menuCategories', _lang);
  String get offersAndCoupons    => _t('offersAndCoupons', _lang);
  String get noMinimum           => _t('noMinimum', _lang);
  String get minOrderLabel       => _t('minOrderLabel', _lang);
  String get gotIt               => _t('gotIt', _lang);
  String get searchGroceries     => _t('searchGroceries', _lang);
  String get noProductsFound     => _t('noProductsFound', _lang);
  String get updateCart          => _t('updateCart', _lang);
  String get cartUpdated         => _t('cartUpdated', _lang);
  String get addedToCartMsg      => _t('addedToCartMsg', _lang);
  String get selectDistrict2     => _t('selectDistrict2', _lang);
  String get groceryOrderPlaced  => _t('groceryOrderPlaced', _lang);
  String get orderSummaryLabel   => _t('orderSummaryLabel', _lang);

  // ── eParcel / eMoving ─────────────────────────────────────────────────────────
  String get pickupLocation      => _t('pickupLocation', _lang);
  String get dropoffLocation     => _t('dropoffLocation', _lang);
  String get packageType         => _t('packageType', _lang);
  String get weight              => _t('weight', _lang);
  String get getQuote            => _t('getQuote', _lang);
  String get bookNow             => _t('bookNow', _lang);
  String get scheduledTime       => _t('scheduledTime', _lang);
  String get selectDate          => _t('selectDate', _lang);
  String get selectTime          => _t('selectTime', _lang);

  // ── eRent ─────────────────────────────────────────────────────────────────────
  String get availableProperties => _t('availableProperties', _lang);
  String get rentPerMonth        => _t('rentPerMonth', _lang);
  String get bedrooms            => _t('bedrooms', _lang);
  String get bathrooms           => _t('bathrooms', _lang);
  String get requestViewing      => _t('requestViewing', _lang);

  // ── Notifications / Inbox ─────────────────────────────────────────────────────
  String get markAllRead         => _t('markAllRead', _lang);
  String get noNotifications     => _t('noNotifications', _lang);
  String get inbox               => _t('inbox', _lang);
  String get inboxSubtitle       => _t('inboxSubtitle', _lang);
  String get support             => _t('support', _lang);
  String get marketing           => _t('marketing', _lang);
  String get newRequest          => _t('newRequest', _lang);
  String get unread              => _t('unread', _lang);

  // ── Order Tracking ────────────────────────────────────────────────────────────
  String get orderTracking       => _t('orderTracking', _lang);
  String get estimatedDelivery   => _t('estimatedDelivery', _lang);
  String get driverOnTheWay      => _t('driverOnTheWay', _lang);
  String get orderPickedUp       => _t('orderPickedUp', _lang);
  String get orderDelivered      => _t('orderDelivered', _lang);
  String get autoRefresh         => _t('autoRefresh', _lang);
  String get orderPlacedStep     => _t('orderPlacedStep', _lang);
  String get confirmed           => _t('confirmed', _lang);
  String get preparing           => _t('preparing', _lang);
  String get onTheWay            => _t('onTheWay', _lang);
  String get restaurant          => _t('restaurant', _lang);
  String get driver              => _t('driver', _lang);
  String get liveTracking        => _t('liveTracking', _lang);
  String get mobileOnly          => _t('mobileOnly', _lang);

  // ── eShop extended ────────────────────────────────────────────────────────────
  String get searchProducts      => _t('searchProducts', _lang);
  String get stores              => _t('stores', _lang);
  String get campaigns           => _t('campaigns', _lang);
  String get featuredProducts    => _t('featuredProducts', _lang);
  String get mostPopular         => _t('mostPopular', _lang);
  String get allProducts         => _t('allProducts', _lang);
  String get dealsOfDay          => _t('dealsOfDay', _lang);
  String get hurryOfferEndsSoon  => _t('hurryOfferEndsSoon', _lang);
  String get limitedOffers       => _t('limitedOffers', _lang);
  String get upToOff             => _t('upToOff', _lang);
  String get off                 => _t('off', _lang);
  String get hrs                 => _t('hrs', _lang);
  String get minLabel            => _t('minLabel', _lang);
  String get secLabel            => _t('secLabel', _lang);

  // ── eParcel extended ──────────────────────────────────────────────────────────
  String get fastReliable        => _t('fastReliable', _lang);
  String get senderInfo          => _t('senderInfo', _lang);
  String get receiverInfo        => _t('receiverInfo', _lang);
  String get pkgTypeStep         => _t('pkgTypeStep', _lang);
  String get routeStep           => _t('routeStep', _lang);
  String get getDeliveryPrice    => _t('getDeliveryPrice', _lang);
  String get sendParcelNow       => _t('sendParcelNow', _lang);
  String get parcelPlaced        => _t('parcelPlaced', _lang);
  String get calculating         => _t('calculating', _lang);
  String get placingOrder        => _t('placingOrder', _lang);
  String get changeDetails       => _t('changeDetails', _lang);
  String get enterReceiverName   => _t('enterReceiverName', _lang);
  String get enterReceiverPhone  => _t('enterReceiverPhone', _lang);
  String get nameLabel           => _t('nameLabel', _lang);
  String get phoneLabel          => _t('phoneLabel', _lang);
  String get receiverName        => _t('receiverName', _lang);
  String get receiverPhone       => _t('receiverPhone', _lang);
  String get selectPkgType       => _t('selectPkgType', _lang);
  String get pickupDistrict      => _t('pickupDistrict', _lang);
  String get deliveryDistrict    => _t('deliveryDistrict', _lang);
  String get pkgContentsHint     => _t('pkgContentsHint', _lang);
  String get pickupLabel         => _t('pickupLabel', _lang);
  String get deliveryLabel       => _t('deliveryLabel', _lang);
  String get flatRate            => _t('flatRate', _lang);

  // ── eMoving extended ──────────────────────────────────────────────────────────
  String get bookMove            => _t('bookMove', _lang);
  String get myMovingOrders      => _t('myMovingOrders', _lang);
  String get professionalMoving  => _t('professionalMoving', _lang);
  String get selectMoveType      => _t('selectMoveType', _lang);
  String get chooseMoveNeeds     => _t('chooseMoveNeeds', _lang);
  String get selectRoute         => _t('selectRoute', _lang);
  String get pickupDeliveryDistricts => _t('pickupDeliveryDistricts', _lang);
  String get extraServices       => _t('extraServices', _lang);
  String get optionalAddOns      => _t('optionalAddOns', _lang);
  String get priceEstimate       => _t('priceEstimate', _lang);
  String get tapCalculate        => _t('tapCalculate', _lang);
  String get bookMovingService   => _t('bookMovingService', _lang);
  String get basePrice           => _t('basePrice', _lang);
  String get roomCost            => _t('roomCost', _lang);
  String get packageCost         => _t('packageCost', _lang);
  String get distanceFee         => _t('distanceFee', _lang);
  String get calculatePrice      => _t('calculatePrice', _lang);
  String get numberOfRooms       => _t('numberOfRooms', _lang);
  String get fromDistrict        => _t('fromDistrict', _lang);
  String get toDistrict          => _t('toDistrict', _lang);
  String get selectExtraServices => _t('selectExtraServices', _lang);
  String get roomCountQ          => _t('roomCountQ', _lang);
  String get selectPackage       => _t('selectPackage', _lang);
  String get howManyRooms        => _t('howManyRooms', _lang);
  String get pickPackageFits     => _t('pickPackageFits', _lang);
  String get noPackagesContact   => _t('noPackagesContact', _lang);
  String get orSelectPackage     => _t('orSelectPackage', _lang);
  String get tapToSelect         => _t('tapToSelect', _lang);
  String get selected            => _t('selected', _lang);
  String get bookingFailed       => _t('bookingFailed', _lang);
  String get calcFailed          => _t('calcFailed', _lang);
  String get searchDistrict      => _t('searchDistrict', _lang);

  // ── eRent extended ────────────────────────────────────────────────────────────
  String get browse              => _t('browse', _lang);
  String get myBookings          => _t('myBookings', _lang);
  String get findAgent           => _t('findAgent', _lang);
  String get noDistricts         => _t('noDistricts', _lang);
  String get browseByDistrict    => _t('browseByDistrict', _lang);
  String get searchPropertiesBtn => _t('searchPropertiesBtn', _lang);
  String get noProperties        => _t('noProperties', _lang);
  String get reserved            => _t('reserved', _lang);
  String get agentLabel          => _t('agentLabel', _lang);
  String get anyType             => _t('anyType', _lang);
  String get anyDistrict         => _t('anyDistrict', _lang);
  String get anyPrice            => _t('anyPrice', _lang);
  String get fullRent            => _t('fullRent', _lang);
  String get selectLocation      => _t('selectLocation', _lang);
  String get propertyType        => _t('propertyType', _lang);
  String get unitType            => _t('unitType', _lang);
  String get priceRange          => _t('priceRange', _lang);
  String get findYourHome        => _t('findYourHome', _lang);
  String get browsePropertiesByDistrict => _t('browsePropertiesByDistrict', _lang);
  String get noPropertiesIn      => _t('noPropertiesIn', _lang);

  // ── eData extended ────────────────────────────────────────────────────────────
  String get buyData             => _t('buyData', _lang);
  String get history             => _t('history', _lang);
  String get mobileDataBundles   => _t('mobileDataBundles', _lang);
  String get chooseProvider      => _t('chooseProvider', _lang);
  String get noProviders         => _t('noProviders', _lang);
  String get dataProvidersHere   => _t('dataProvidersHere', _lang);
  String get chooseProviderPickPackage => _t('chooseProviderPickPackage', _lang);
  String get noPackages          => _t('noPackages', _lang);
  String get noBundles           => _t('noBundles', _lang);
  String get choosePackage       => _t('choosePackage', _lang);
  String get chooseBundle        => _t('chooseBundle', _lang);
  String get bookingConfirmed    => _t('bookingConfirmed', _lang);
  String get dataDestination     => _t('dataDestination', _lang);
  String get phoneForData        => _t('phoneForData', _lang);
  String get bundleLabel         => _t('bundleLabel', _lang);
  String get providerLabel       => _t('providerLabel', _lang);
  String get instantLabel        => _t('instantLabel', _lang);
  String get secureLabel         => _t('secureLabel', _lang);
  String get viewBundles         => _t('viewBundles', _lang);

  // ── eTicket extended ──────────────────────────────────────────────────────────
  String get bookFlight          => _t('bookFlight', _lang);
  String get myTickets           => _t('myTickets', _lang);
  String get bookFlightDesc      => _t('bookFlightDesc', _lang);
  String get oneWay              => _t('oneWay', _lang);
  String get roundTrip           => _t('roundTrip', _lang);
  String get fromCity            => _t('fromCity', _lang);
  String get toCity              => _t('toCity', _lang);
  String get departureCity       => _t('departureCity', _lang);
  String get destinationCity     => _t('destinationCity', _lang);
  String get departureDate       => _t('departureDate', _lang);
  String get returnDate          => _t('returnDate', _lang);
  String get searchFlights       => _t('searchFlights', _lang);
  String get updateSearch        => _t('updateSearch', _lang);
  String get selectCity          => _t('selectCity', _lang);
  String get searchCity          => _t('searchCity', _lang);
}

// ─────────────────────────────────────────────────────────────────────────────
// Translation database
// ─────────────────────────────────────────────────────────────────────────────

String _t(String key, String lang) {
  final row = _db[key];
  if (row == null) return key;
  return row[lang] ?? row['en'] ?? key;
}

const _db = <String, Map<String, String>>{
  // Navigation
  'home':        {'en': 'Home',        'so': 'Guriga',        'ar': 'الرئيسية'},
  'community':   {'en': 'Community',   'so': 'Bulshada',      'ar': 'المجتمع'},
  'profile':     {'en': 'Profile',     'so': 'Xogtayda',      'ar': 'الملف الشخصي'},
  'search':      {'en': 'Search',      'so': 'Raadi',         'ar': 'بحث'},
  'cart':        {'en': 'Cart',        'so': 'Dambiishta',    'ar': 'عربة التسوق'},
  'myCart':      {'en': 'My Cart',     'so': 'Dambiishdayda', 'ar': 'سلتي'},
  'messages':    {'en': 'Messages',    'so': 'Farriimaha',    'ar': 'الرسائل'},
  'explore':     {'en': 'Explore',     'so': 'Baadhi',        'ar': 'استكشاف'},

  // Auth
  'login':       {'en': 'Log In',      'so': 'Soo gal',       'ar': 'تسجيل الدخول'},
  'logout':      {'en': 'Log Out',     'so': 'Ka bax',        'ar': 'تسجيل الخروج'},
  'register':    {'en': 'Register',    'so': 'Is diiwaan geli','ar': 'إنشاء حساب'},
  'email':       {'en': 'Email',       'so': 'Iimayl',        'ar': 'البريد الإلكتروني'},
  'password':    {'en': 'Password',    'so': 'Furaha sirta',  'ar': 'كلمة المرور'},
  'name':        {'en': 'Name',        'so': 'Magaca',        'ar': 'الاسم'},
  'phone':       {'en': 'Phone',       'so': 'Telefoon',      'ar': 'رقم الهاتف'},
  'continueBtn': {'en': 'Continue',    'so': 'Sii wad',       'ar': 'متابعة'},
  'orContinueWith': {'en': 'Or continue with', 'so': 'Ama ku sii wad', 'ar': 'أو تابع عبر'},
  'forgotPassword': {'en': 'Forgot password?', 'so': 'Ma ilowday furaha sirta?', 'ar': 'نسيت كلمة المرور؟'},
  'createAccount':  {'en': 'Create Account',  'so': 'Samee koonto', 'ar': 'إنشاء حساب'},
  'alreadyHaveAccount': {'en': 'Already have an account?', 'so': 'Ma haysataa koonto?', 'ar': 'لديك حساب بالفعل؟'},
  'dontHaveAccount': {'en': "Don't have an account?", 'so': 'Ma haysatid koonto?', 'ar': 'ليس لديك حساب؟'},
  'signIn':      {'en': 'Sign In',     'so': 'Gal',           'ar': 'دخول'},
  'signUp':      {'en': 'Sign Up',     'so': 'Is diiwaan geli','ar': 'تسجيل'},

  // Common actions
  'save':        {'en': 'Save',        'so': 'Keydi',         'ar': 'حفظ'},
  'cancel':      {'en': 'Cancel',      'so': 'Ka noqo',       'ar': 'إلغاء'},
  'deleteBtn':   {'en': 'Delete',      'so': 'Tirtir',        'ar': 'حذف'},
  'edit':        {'en': 'Edit',        'so': 'Wax ka bedel',  'ar': 'تعديل'},
  'submit':      {'en': 'Submit',      'so': 'Dir',           'ar': 'إرسال'},
  'sendBtn':     {'en': 'Send',        'so': 'Dir',           'ar': 'إرسال'},
  'back':        {'en': 'Back',        'so': 'Dib u noqo',    'ar': 'رجوع'},
  'done':        {'en': 'Done',        'so': 'Dhameystay',    'ar': 'تم'},
  'apply':       {'en': 'Apply',       'so': 'Codso',         'ar': 'تطبيق'},
  'remove':      {'en': 'Remove',      'so': 'Ka saar',       'ar': 'إزالة'},
  'retry':       {'en': 'Retry',       'so': 'Isku day',      'ar': 'إعادة المحاولة'},
  'loading':     {'en': 'Loading…',    'so': 'Waa la rarayo…','ar': 'جارٍ التحميل…'},
  'error':       {'en': 'Error',       'so': 'Khalad',        'ar': 'خطأ'},
  'success':     {'en': 'Success',     'so': 'Guul',          'ar': 'نجاح'},
  'noData':      {'en': 'No data',     'so': 'Xog ma jirto',  'ar': 'لا توجد بيانات'},
  'seeAll':      {'en': 'See all',     'so': 'Dhammaan arag', 'ar': 'عرض الكل'},
  'viewAll':     {'en': 'View all',    'so': 'Dhammaan arag', 'ar': 'عرض الكل'},
  'close':       {'en': 'Close',       'so': 'Xidh',          'ar': 'إغلاق'},
  'confirm':     {'en': 'Confirm',     'so': 'Xaqiiji',       'ar': 'تأكيد'},

  // Settings
  'settings':       {'en': 'Settings',         'so': 'Goobaha',              'ar': 'الإعدادات'},
  'language':       {'en': 'Language',         'so': 'Luuqadda',             'ar': 'اللغة'},
  'appearance':     {'en': 'Appearance',       'so': 'Muuqaalka',            'ar': 'المظهر'},
  'notifications':  {'en': 'Notifications',    'so': 'Ogeysiisyada',         'ar': 'الإشعارات'},
  'privacy':        {'en': 'Privacy',          'so': 'Asturnaanta',          'ar': 'الخصوصية'},
  'security':       {'en': 'Security',         'so': 'Amniga',               'ar': 'الأمان'},
  'account':        {'en': 'Account',          'so': 'Akoonka',              'ar': 'الحساب'},
  'darkMode':       {'en': 'Dark Mode',        'so': 'Muuqaalka madow',      'ar': 'الوضع الداكن'},
  'lightMode':      {'en': 'Light Mode',       'so': 'Muuqaalka ifka',       'ar': 'الوضع الفاتح'},
  'systemMode':     {'en': 'System Default',   'so': 'Nidaamka caadiga',     'ar': 'إعداد النظام'},
  'dataSaver':      {'en': 'Data Saver',       'so': 'Kaydi xogta',          'ar': 'توفير البيانات'},
  'videoSettings':  {'en': 'Video Settings',   'so': 'Goobaha fiidiyaha',    'ar': 'إعدادات الفيديو'},
  'textSize':       {'en': 'Text Size',        'so': 'Cabbirka qoraalka',    'ar': 'حجم النص'},
  'contentPrefs':   {'en': 'Content Preferences','so': 'Doorbidaha waxa la rabo','ar': 'تفضيلات المحتوى'},

  // Language names
  'langEnglish':    {'en': 'English',    'so': 'Ingiriisi',    'ar': 'الإنجليزية'},
  'langSomali':     {'en': 'Somali',     'so': 'Soomaali',     'ar': 'الصومالية'},
  'langArabic':     {'en': 'Arabic',     'so': 'Carabi',       'ar': 'العربية'},
  'chooseLanguage': {'en': 'Choose your language', 'so': 'Dooro luuqaddaada', 'ar': 'اختر لغتك'},

  // Global Store
  'globalStore':    {'en': 'eSahlan Global',   'so': 'eSahlan Caalamiga',    'ar': 'eSahlan عالمي'},
  'products':       {'en': 'Products',         'so': 'Badeecadaha',          'ar': 'المنتجات'},
  'categories':     {'en': 'Categories',       'so': 'Qaybaha',              'ar': 'الفئات'},
  'newArrivals':    {'en': 'New Arrivals',      'so': 'Cusub yimid',          'ar': 'وصل حديثاً'},
  'bestSellers':    {'en': 'Best Sellers',      'so': 'Ugu iibsiga badan',    'ar': 'الأكثر مبيعاً'},
  'flashDeals':     {'en': 'Flash Deals',       'so': 'Ganacsiga degdega',    'ar': 'عروض سريعة'},
  'specialDeals':   {'en': 'Special Deals',     'so': 'Ganacsiga gaarka',     'ar': 'عروض خاصة'},
  'featured':       {'en': 'Featured',          'so': 'La xushay',            'ar': 'مميز'},
  'addToCart':      {'en': 'Add to Cart',       'so': 'U dar dambiishta',     'ar': 'أضف للسلة'},
  'buyNow':         {'en': 'Buy Now',           'so': 'Hadda iibso',          'ar': 'اشتر الآن'},
  'checkout':       {'en': 'Checkout',          'so': 'Bix lacagta',          'ar': 'الدفع'},
  'orderSummary':   {'en': 'Order Summary',     'so': 'Koobaad dalabka',      'ar': 'ملخص الطلب'},
  'subtotal':       {'en': 'Subtotal',          'so': 'Wadarta hoose',        'ar': 'المجموع الفرعي'},
  'total':          {'en': 'Total',             'so': 'Wadarta',              'ar': 'الإجمالي'},
  'shipping':       {'en': 'Shipping',          'so': 'Gaadiidka',            'ar': 'الشحن'},
  'payment':        {'en': 'Payment',           'so': 'Lacag bixinta',        'ar': 'الدفع'},
  'orders':         {'en': 'Orders',            'so': 'Dalabyada',            'ar': 'الطلبات'},
  'myOrders':       {'en': 'My Orders',         'so': 'Dalabaadayga',         'ar': 'طلباتي'},
  'placeOrder':     {'en': 'Place Order',       'so': 'Dir dalabka',          'ar': 'تأكيد الطلب'},
  'orderPlaced':    {'en': 'Order Placed!',     'so': 'Dalabka la diray!',    'ar': 'تم الطلب!'},
  'trackOrder':     {'en': 'Track Order',       'so': 'La sooc dalabka',      'ar': 'تتبع الطلب'},
  'inStock':        {'en': 'In Stock',          'so': 'La heli karaa',        'ar': 'متوفر'},
  'outOfStock':     {'en': 'Out of Stock',      'so': 'Khasnadda maran',      'ar': 'غير متوفر'},
  'quantity':       {'en': 'Quantity',          'so': 'Tirooyinka',           'ar': 'الكمية'},
  'reviews':        {'en': 'Reviews',           'so': 'Dib u eegisyada',      'ar': 'التقييمات'},
  'writeReview':    {'en': 'Write a Review',    'so': 'Faallo qor',           'ar': 'اكتب مراجعة'},
  'rating':         {'en': 'Rating',            'so': 'Qiimaynta',            'ar': 'التقييم'},
  'description':    {'en': 'Description',       'so': 'Sharaxaadda',          'ar': 'الوصف'},
  'price':          {'en': 'Price',             'so': 'Qiimaha',              'ar': 'السعر'},
  'discount':       {'en': 'Discount',          'so': 'Dhimista qiimaha',     'ar': 'الخصم'},
  'sold':           {'en': 'sold',              'so': 'la iibsaday',          'ar': 'مباع'},
  'productNotFound':{'en': 'Product not found', 'so': 'Alaabta lama helin',   'ar': 'المنتج غير موجود'},
  'variants':       {'en': 'Variants',         'so': 'Noocyada',             'ar': 'الأنواع'},
  'showLess':       {'en': 'Show less',        'so': 'Tus wax yar',          'ar': 'عرض أقل'},
  'readMore':       {'en': 'Read more',        'so': 'Akhri wax dheeraad ah','ar': 'اقرأ المزيد'},
  'openLabel':      {'en': 'Open',             'so': 'Furan',                'ar': 'مفتوح'},
  'closedLabel':    {'en': 'Closed',           'so': 'Xidhan',               'ar': 'مغلق'},
  'left':           {'en': 'left',             'so': 'hadhay',               'ar': 'متبقي'},

  // Coupon
  'coupon':          {'en': 'Coupon',          'so': 'Kuuboon',              'ar': 'قسيمة'},
  'myCoupons':       {'en': 'My Coupons',      'so': 'Kuuboonaadayga',       'ar': 'قسائمي'},
  'collectAll':      {'en': 'Collect All',     'so': 'Dhammaan ururso',      'ar': 'جمع الكل'},
  'collect':         {'en': 'Collect',         'so': 'Ururso',               'ar': 'جمع'},
  'collected':       {'en': 'Collected',       'so': 'La uruuriyay',         'ar': 'تم الجمع'},
  'applyCoupon':     {'en': 'Coupon Code',     'so': 'Lambarka kuuboonka',   'ar': 'رمز القسيمة'},
  'couponApplied':   {'en': 'Coupon applied!', 'so': 'Kuuboonka la codsaday!','ar': 'تم تطبيق القسيمة!'},
  'enterCouponCode': {'en': 'Enter coupon code','so': 'Geli lambarka kuuboonka','ar': 'أدخل رمز القسيمة'},
  'minOrder':        {'en': 'Min. order',       'so': 'Ugu yar dalabka',      'ar': 'الحد الأدنى للطلب'},
  'expiresIn':       {'en': 'Expires in',       'so': 'Dhacaya',              'ar': 'ينتهي خلال'},
  'expiresLabel':    {'en': 'Expires today!',   'so': 'Maanta dhacayaa!',     'ar': 'ينتهي اليوم!'},
  'newUserOnly':     {'en': 'New User',         'so': 'Cusub',                'ar': 'مستخدم جديد'},
  'specialDealsForYou': {'en': 'Special Deals for You! 🎉', 'so': 'Ganacsi Gaarka ah oo Kuu Gaar ah! 🎉', 'ar': 'عروض خاصة لك! 🎉'},

  // Address
  'address':    {'en': 'Address',         'so': 'Cinwaanka',          'ar': 'العنوان'},
  'addAddress': {'en': 'Add Address',     'so': 'Cinwaan ku dar',     'ar': 'إضافة عنوان'},
  'city':       {'en': 'City',            'so': 'Magaalada',          'ar': 'المدينة'},
  'country':    {'en': 'Country',         'so': 'Dalka',              'ar': 'الدولة'},
  'zipCode':    {'en': 'ZIP / Postal',    'so': 'Lambarka boostada',  'ar': 'الرمز البريدي'},
  'firstName':  {'en': 'First Name',      'so': 'Magaca hore',        'ar': 'الاسم الأول'},
  'lastName':   {'en': 'Last Name',       'so': 'Magaca reerka',      'ar': 'اسم العائلة'},

  // Community
  'feed':          {'en': 'Feed',          'so': 'Wargeysyada',       'ar': 'الخلاصة'},
  'reels':         {'en': 'Reels',         'so': 'Filimiyaha',        'ar': 'ريلز'},
  'stories':       {'en': 'Stories',       'so': 'Sheekoyinka',       'ar': 'القصص'},
  'like':          {'en': 'Like',          'so': 'Jecel',             'ar': 'إعجاب'},
  'comment':       {'en': 'Comment',       'so': 'Faallo',            'ar': 'تعليق'},
  'share':         {'en': 'Share',         'so': 'Wadaag',            'ar': 'مشاركة'},
  'follow':        {'en': 'Follow',        'so': 'Raac',              'ar': 'متابعة'},
  'unfollow':      {'en': 'Unfollow',      'so': 'Raacista jooji',    'ar': 'إلغاء المتابعة'},
  'following':     {'en': 'Following',     'so': 'Raacaya',           'ar': 'متابَع'},
  'followers':     {'en': 'Followers',     'so': 'Raacayaasha',       'ar': 'المتابعون'},
  'posts':         {'en': 'Posts',         'so': 'Qoraaladda',        'ar': 'المنشورات'},
  'createPost':    {'en': 'Create Post',   'so': 'Qoraal cusub samai','ar': 'إنشاء منشور'},
  'chat':          {'en': 'Chat',          'so': 'Xiriir',            'ar': 'دردشة'},
  'online':        {'en': 'Online',        'so': 'Shabakadda jooga',  'ar': 'متصل'},
  'offline':       {'en': 'Offline',       'so': 'Offline',           'ar': 'غير متصل'},
  'typing':        {'en': 'typing…',       'so': 'wuu qoraya…',       'ar': 'يكتب…'},
  'writeSomething':{'en': 'Write something…','so': 'Wax qor…',        'ar': 'اكتب شيئاً…'},

  // Profile
  'myAccount':    {'en': 'My Account',       'so': 'Akoonkayga',         'ar': 'حسابي'},
  'preferences':  {'en': 'Preferences',      'so': 'Doorbidayaasha',     'ar': 'التفضيلات'},
  'supportLegal': {'en': 'Support & Legal',  'so': 'Taageero & Sharci',  'ar': 'الدعم والقانوني'},
  'fullName':     {'en': 'Full Name',        'so': 'Magaca buuxa',       'ar': 'الاسم الكامل'},
  'phoneNumber':  {'en': 'Phone Number',     'so': 'Lambarka telefoonka', 'ar': 'رقم الهاتف'},
  'emailAddress': {'en': 'Email Address',    'so': 'Ciwaanka iimayl',    'ar': 'عنوان البريد الإلكتروني'},
  'district':     {'en': 'District',         'so': 'Degmada',            'ar': 'المنطقة'},
  'notAdded':     {'en': 'Not added',        'so': 'Lama darin',         'ar': 'لم يُضَف'},
  'notSet':       {'en': 'Not set',          'so': 'Lama dejin',         'ar': 'غير محدد'},
  'small':        {'en': 'Small',            'so': 'Yar',                'ar': 'صغير'},
  'medium':       {'en': 'Medium',           'so': 'Dhexdhexaad',        'ar': 'متوسط'},
  'large':        {'en': 'Large',            'so': 'Weyn',               'ar': 'كبير'},
  'extraLarge':   {'en': 'Extra Large',      'so': 'Aad u weyn',         'ar': 'كبير جداً'},
  'reduceDataQuality': {'en': 'Reduce media quality to save data', 'so': 'Tayada media yaree si xogta la kaydiyo', 'ar': 'تقليل جودة الوسائط لتوفير البيانات'},
  'helpCenter':   {'en': 'Help Center',      'so': 'Xarunta caawimaada', 'ar': 'مركز المساعدة'},
  'termsOfService':{'en': 'Terms of Service','so': 'Xeerarka adeegga',   'ar': 'شروط الخدمة'},
  'privacyPolicy':{'en': 'Privacy Policy',   'so': 'Siyaasadda asturnaanta','ar': 'سياسة الخصوصية'},
  'contactUs':    {'en': 'Contact Us',       'so': 'Nala soo xiriir',    'ar': 'اتصل بنا'},
  'deleteAccount':       {'en': 'Delete Account',                  'so': 'Akoonka tirtir',              'ar': 'حذف الحساب'},
  'deleteAccountSub':    {'en': 'Permanently remove your account', 'so': 'Akoonkaaga si joogto ah u tirtir','ar': 'حذف حسابك نهائياً'},
  'deleteAccountTitle':  {'en': 'Delete Account?',                 'so': 'Ma tirtiraysa akoonkaaga?',    'ar': 'حذف الحساب؟'},
  'deleteAccountWarn':   {'en': 'This action is permanent and cannot be undone. All your data, orders, and history will be permanently deleted.', 'so': 'Falkan waa mid joogto ah oo aan la laabta karin. Dhammaan xogtaada, dalabyadaada, iyo taariikhda si joogto ah ayaa la tirtiri doonaa.', 'ar': 'هذا الإجراء دائم ولا يمكن التراجع عنه. سيتم حذف جميع بياناتك وطلباتك وسجلك بشكل دائم.'},
  'deleteAccountConfirm':{'en': 'Yes, Delete My Account',          'so': 'Haa, Akoonkeyga Tirtir',       'ar': 'نعم، احذف حسابي'},
  'deleteAccountSuccess':{'en': 'Account deleted successfully',    'so': 'Akoonku si guul leh ayuu u tirtirnay', 'ar': 'تم حذف الحساب بنجاح'},
  'signOut':      {'en': 'Sign Out',         'so': 'Ka bax',             'ar': 'تسجيل الخروج'},
  'signOutSub':   {'en': 'You can always log back in', 'so': 'Mar walba waxaad dib u geli kartaa', 'ar': 'يمكنك تسجيل الدخول مجدداً'},
  'signOutTitle': {'en': 'Sign Out?',        'so': 'Ma ka baxaysaa?',    'ar': 'تسجيل الخروج؟'},
  'helpSupport':  {'en': 'Help & Support',   'so': 'Caawimaada & Taageero', 'ar': 'المساعدة والدعم'},
  'aboutApp':     {'en': 'About eSahlan',    'so': 'Ku saabsan eSahlan', 'ar': 'عن eSahlan'},
  'rateApp':      {'en': 'Rate eSahlan',     'so': 'eSahlan qiimee',     'ar': 'قيّم eSahlan'},
  'changeRegion': {'en': 'Change Region',    'so': 'Gobolka beddel',     'ar': 'تغيير المنطقة'},
  'changeRegionMsg': {'en': 'This will take you to the region selector. Your current session will be kept.', 'so': 'Tani waxay kuu qaadaysaa xulashada gobolka. Fadhigaaga hadda jira ayaa la ilaalin doonaa.', 'ar': 'سيأخذك هذا إلى محدد المنطقة. سيتم الاحتفاظ بجلستك الحالية.'},
  'change':       {'en': 'Change',           'so': 'Beddel',             'ar': 'تغيير'},
  'light':        {'en': 'Light',            'so': 'Ifka',               'ar': 'فاتح'},
  'dark':         {'en': 'Dark',             'so': 'Mugdi',              'ar': 'داكن'},
  'systemDefault':{'en': 'System',           'so': 'Nidaamka',           'ar': 'النظام'},

  // Status
  'pleaseWait':         {'en': 'Please wait…',       'so': 'Fadlan sug…',             'ar': 'يرجى الانتظار…'},
  'somethingWentWrong': {'en': 'Something went wrong','so': 'Wax baa khalad ahaade',  'ar': 'حدث خطأ ما'},
  'noInternet':         {'en': 'No internet connection','so': 'Xiriirka internet ma jiro','ar': 'لا يوجد اتصال بالإنترنت'},
  'tryAgain':           {'en': 'Try again',           'so': 'Mar kale isku day',       'ar': 'حاول مرة أخرى'},
  'loginRequired':      {'en': 'Please log in first', 'so': 'Fadlan marka hore soo gal','ar': 'يرجى تسجيل الدخول أولاً'},

  // Auth extended
  'welcomeBack':     {'en': 'Welcome back 👋',              'so': 'Soo dhowoow 👋',                    'ar': 'مرحباً بعودتك 👋'},
  'signInSubtitle':  {'en': 'Sign in to your eSahlan account','so': 'Gal akoonkaaga eSahlan',          'ar': 'سجّل دخولك لحساب eSahlan'},
  'phoneAndPin':     {'en': '📱 Phone & PIN',               'so': '📱 Taleefon & PIN',                'ar': '📱 الهاتف ورمز PIN'},
  'emailAndPass':    {'en': '✉️ Email & Password',          'so': '✉️ Iimayl & Furaha',               'ar': '✉️ الإيميل وكلمة المرور'},
  'pinCode':         {'en': 'PIN Code',                     'so': 'Lambarka PIN',                     'ar': 'رمز PIN'},
  'fourDigits':      {'en': '4 digits',                     'so': '4 lambarro',                       'ar': '4 أرقام'},
  'yourPassword':    {'en': 'Your password',                'so': 'Furaha sirta',                     'ar': 'كلمة المرور'},
  'continueGoogle':  {'en': 'Continue with Google',         'so': 'Google ku sii wad',                'ar': 'المتابعة عبر Google'},
  'orDivider':       {'en': 'or',                           'so': 'ama',                              'ar': 'أو'},
  'enterPhone':      {'en': 'Enter your phone number',      'so': 'Geli lambarka telefoontaada',      'ar': 'أدخل رقم هاتفك'},
  'enterPin':        {'en': 'Enter your 4-digit PIN',       'so': 'Geli lambarkaaga 4-xagal ee PIN',  'ar': 'أدخل رمز PIN المكون من 4 أرقام'},
  'enterEmailAddr':  {'en': 'Enter your email address',     'so': 'Geli cinwaanka iimaylkaada',       'ar': 'أدخل عنوان بريدك الإلكتروني'},
  'enterPassword':   {'en': 'Enter your password',          'so': 'Geli furaha sirta',                'ar': 'أدخل كلمة المرور'},
  'joinEsahlan':     {'en': 'Join eSahlan in seconds',      'so': 'Inu eSahlan ku biir dhawaato',     'ar': 'انضم لـ eSahlan في ثوانٍ'},
  'referralCode':    {'en': 'Referral Code (optional)',     'so': 'Lambarka tilmaamaha (ikhtiyaari)',  'ar': 'رمز الإحالة (اختياري)'},
  'securityMethod':  {'en': 'Security Method',              'so': 'Hab ammaan',                       'ar': 'طريقة الحماية'},
  'createPin':       {'en': 'Create PIN',                   'so': 'PIN samee',                        'ar': 'إنشاء رمز PIN'},
  'quickEasySignIn': {'en': 'Quick & easy sign in every time','so': 'Gal degdeg ah oo fudud mar walba','ar': 'تسجيل دخول سريع وسهل في كل مرة'},
  'pinOption':       {'en': '4-Digit PIN',                  'so': '4-Xagal PIN',                     'ar': 'رمز PIN من 4 أرقام'},
  'pinSubtitle':     {'en': 'Fast & easy — sign in with your phone + PIN','so': 'Degdeg & fudud — gal taleefon + PIN','ar': 'سريع وسهل — سجّل بهاتفك ورمز PIN'},
  'emailStrong':     {'en': 'Email & Strong Password',      'so': 'Iimayl & Furaha xooggan',          'ar': 'البريد الإلكتروني وكلمة مرور قوية'},
  'emailStrongSub':  {'en': 'Higher security — uppercase, numbers & symbols required','so': 'Ammaanka sare — xarfo waaweyn, lambarrada & astaamaha loo baahan yahay','ar': 'أمان أعلى — أحرف كبيرة وأرقام ورموز مطلوبة'},
  'confirmPassword': {'en': 'Confirm Password',             'so': 'Xaqiiji furaha sirta',             'ar': 'تأكيد كلمة المرور'},
  'reenterPass':     {'en': 'Re-enter password',            'so': 'Fur-ha mar labaad geli',           'ar': 'أعد إدخال كلمة المرور'},
  'createStrongPass':{'en': 'Create a strong password',     'so': 'Fur xooggan samee',                'ar': 'أنشئ كلمة مرور قوية'},
  'selectDistrict':  {'en': 'Select your district',         'so': 'Degmadaada dooro',                 'ar': 'اختر منطقتك'},
  'requiredLabel':   {'en': 'Required',                     'so': 'Waajib',                           'ar': 'مطلوب'},
  'failedDistricts': {'en': 'Failed to load districts',     'so': 'Degmooyinka lama soo raraan',      'ar': 'فشل تحميل المناطق'},
  'weakPassword':    {'en': 'Weak',                         'so': 'Liita',                            'ar': 'ضعيفة'},
  'fairPassword':    {'en': 'Fair',                         'so': 'Dhexdhexaad',                      'ar': 'مقبولة'},
  'goodPassword':    {'en': 'Good',                         'so': 'Wanaagsan',                        'ar': 'جيدة'},
  'strongPassword':  {'en': 'Strong',                       'so': 'Xooggan',                          'ar': 'قوية'},
  'pwCheck8chars':   {'en': '8+ characters',                'so': '8+ xaraf',                         'ar': '8+ أحرف'},
  'pwCheckUpper':    {'en': 'Uppercase (A-Z)',               'so': 'Xarfo waaweyn (A-Z)',              'ar': 'أحرف كبيرة (A-Z)'},
  'pwCheckLower':    {'en': 'Lowercase (a-z)',               'so': 'Xarfo yaryar (a-z)',               'ar': 'أحرف صغيرة (a-z)'},
  'pwCheckDigit':    {'en': 'Number (0-9)',                  'so': 'Lambar (0-9)',                     'ar': 'رقم (0-9)'},
  'pwCheckSymbol':   {'en': r'Symbol (@$!%*#?&)',            'so': r'Calaamad (@$!%*#?&)',             'ar': r'رمز (@$!%*#?&)'},

  // OTP
  'verifyNumber':    {'en': 'Verify Your Number',           'so': 'Lambarka xaqiiji',                 'ar': 'تحقق من رقمك'},
  'codeSentTo':      {'en': 'We sent a 6-digit code to',   'so': 'Koodka 6-tirood ayaa loo diray',   'ar': 'أرسلنا رمزاً من 6 أرقام إلى'},
  'enterOtp':        {'en': 'Enter OTP',                   'so': 'Geli koodka OTP',                  'ar': 'أدخل رمز OTP'},
  'didntReceive':    {'en': "Didn't receive it?",           'so': 'Maaad helin?',                     'ar': 'لم تستلمه؟'},
  'resend':          {'en': 'Resend',                       'so': 'Dib u dir',                        'ar': 'إعادة الإرسال'},
  'verify':          {'en': 'Verify',                       'so': 'Xaqiiji',                          'ar': 'تحقق'},
  'verifying':       {'en': 'Verifying…',                  'so': 'Waa la xaqiijinayaa…',              'ar': 'جارٍ التحقق…'},

  // Home
  'ourServices':     {'en': 'Our Services',                 'so': 'Adeegyadeena',                     'ar': 'خدماتنا'},
  'exploreNow':      {'en': 'Explore Now',                  'so': 'Hadda baadhi',                     'ar': 'استكشف الآن'},
  'searchMinLength': {'en': 'Enter at least 2 characters to search', 'so': 'Geli ugu yaraan 2 xaraf si aad u raadiso', 'ar': 'أدخل حرفين على الأقل للبحث'},
  'searchVendors': {'en': 'Stores and restaurants', 'so': 'Dukaamada iyo makhaayadaha', 'ar': 'المتاجر والمطاعم'},
  'searchHint':      {'en': 'Search services, restaurants, houses...','so': 'Adeegyada, makhaayadaha, guryaha raadi...','ar': 'ابحث عن خدمات، مطاعم، منازل...'},

  // Community Chat
  'chats':           {'en': 'Chats',                        'so': 'Xiriirrada',                       'ar': 'المحادثات'},
  'noChats':         {'en': 'No conversations yet',         'so': 'Xiriir ma jiro wali',              'ar': 'لا توجد محادثات بعد'},
  'startChat':       {'en': 'Start a conversation',         'so': 'Xiriir bilow',                     'ar': 'ابدأ محادثة'},
  'searchMessages':  {'en': 'Search messages or users',     'so': 'Farriimaha ama dadka raadi',       'ar': 'ابحث عن رسائل أو أشخاص'},
  'noMessages':      {'en': 'No messages yet',              'so': 'Fariin ma jirto wali',             'ar': 'لا توجد رسائل بعد'},
  'typeMessage':     {'en': 'Type a message…',              'so': 'Fariin qor…',                      'ar': 'اكتب رسالة…'},
  'send':            {'en': 'Send',                         'so': 'Dir',                              'ar': 'إرسال'},
  'sayHi':           {'en': 'Say hi! 👋',                  'so': 'Salaan dir! 👋',                   'ar': 'قل مرحبا! 👋'},
  'seen':            {'en': 'Seen',              'so': 'La arkay',          'ar': 'مُشاهَد'},
  'delivered':       {'en': 'Delivered',         'so': 'La gaarsiiyo',      'ar': 'مُسلَّم'},
  'lastSeenRecently':{'en': 'last seen recently',           'so': 'dhowaan la arkay',                 'ar': 'آخر ظهور مؤخراً'},

  // Community Explore
  'searchCommunity': {'en': 'Search eSahlan Community...', 'so': 'eSahlan Bulshada raadi...',         'ar': 'ابحث في مجتمع eSahlan...'},
  'peopleYouMayKnow':{'en': 'People You May Know',         'so': 'Dad aad taqaan karaysid',           'ar': 'أشخاص قد تعرفهم'},
  'trendingPosts':   {'en': 'Trending Posts',               'so': 'Qoraaladda maamuusan',             'ar': 'المنشورات الرائجة'},
  'noTrendingPosts': {'en': 'No trending posts yet',        'so': 'Qoraal maamuusan ma jiro wali',    'ar': 'لا توجد منشورات رائجة بعد'},

  // Orders extended
  'tabAll':          {'en': 'All',                          'so': 'Dhammaan',                         'ar': 'الكل'},
  'tabPending':      {'en': 'Pending',                      'so': 'Sugaya',                           'ar': 'قيد الانتظار'},
  'tabPreparing':    {'en': 'Preparing',                    'so': 'La diyaarinayaa',                  'ar': 'جارٍ التحضير'},
  'tabDelivered':    {'en': 'Delivered',                    'so': 'La gaarsiiay',                     'ar': 'تم التوصيل'},
  'tabCancelled':    {'en': 'Cancelled',                    'so': 'La joojiyay',                      'ar': 'ملغى'},
  'ordersWillAppear':{'en': 'Your orders will appear here', 'so': 'Dalabaadaadu waxay halkaan ka muuqan doonaan','ar': 'ستظهر طلباتك هنا'},
  'trackBtn':        {'en': 'Track',                        'so': 'La sooc',                          'ar': 'تتبع'},
  'orderItems':      {'en': 'Order Items',                  'so': 'Walxaha dalabka',                  'ar': 'عناصر الطلب'},
  'paymentSummary':  {'en': 'Payment Summary',              'so': 'Koobaad lacag bixinta',            'ar': 'ملخص الدفع'},
  'statusTimeline':  {'en': 'Status Timeline',              'so': 'Taariikhda xaaladda',              'ar': 'الجدول الزمني للحالة'},
  'deliveryFee':     {'en': 'Delivery Fee',                 'so': 'Khidmadda gaarsiinta',             'ar': 'رسوم التوصيل'},
  'shippingAddress': {'en': 'Shipping Address',             'so': 'Cinwaanka dooflayaasha',           'ar': 'عنوان الشحن'},
  'noOrdersYet':     {'en': 'No orders yet',                'so': 'Wali dalabo ma jiraan',            'ar': 'لا توجد طلبات بعد'},
  'orderDetails':    {'en': 'Order Details',                'so': 'Faahfaahinta dalabka',             'ar': 'تفاصيل الطلب'},
  'cancelOrder':     {'en': 'Cancel Order',                'so': 'Dalabka jooji',                    'ar': 'إلغاء الطلب'},
  'cancelOrderQ':    {'en': 'Cancel Order?',               'so': 'Ma joojisaa dalabka?',             'ar': 'إلغاء الطلب؟'},
  'cancelOrderConfirm': {'en': 'Are you sure you want to cancel this order?', 'so': 'Ma hubtaa inaad dalabkan joojinayso?', 'ar': 'هل أنت متأكد من إلغاء هذا الطلب؟'},
  'no':              {'en': 'No',                          'so': 'Maya',                             'ar': 'لا'},
  'yesCancel':       {'en': 'Yes, Cancel',                 'so': 'Haa, Jooji',                       'ar': 'نعم، إلغاء'},

  // Global Store extended
  'myGlobalOrders':      {'en': 'My Global Orders',         'so': 'Dalabaadayga caalamiga',           'ar': 'طلباتي العالمية'},
  'emptyCart':           {'en': 'Your cart is empty',       'so': 'Dambiishta waa maran tahay',       'ar': 'سلتك فارغة'},
  'continueShopping':    {'en': 'Continue Shopping',         'so': 'Iibsiga sii wad',                 'ar': 'مواصلة التسوق'},
  'proceedCheckout':     {'en': 'Proceed to Checkout →',    'so': 'Lacag bixinta u gudub →',          'ar': 'المتابعة للدفع →'},
  'clearCart':           {'en': 'Clear cart?',              'so': 'Dambiishta nadiifi?',               'ar': 'إفراغ السلة؟'},
  'clearBtn':            {'en': 'Clear',                    'so': 'Nadiifi',                           'ar': 'إفراغ'},
  'calculatedAtCheckout':{'en': 'Calculated at checkout',   'so': 'Dhamaadka laga xisaabin doonaa',   'ar': 'يُحسب عند الدفع'},
  'signInToViewCart':    {'en': 'Sign in to view your cart','so': 'Gal dambiishaada arag',             'ar': 'سجّل دخولك لعرض سلتك'},
  'addedToCart':         {'en': '✓  Added to cart',         'so': '✓  Dambiishta lagu daray',         'ar': '✓  تمت الإضافة للسلة'},
  'viewCart':            {'en': 'View Cart',                 'so': 'Dambiishta eeg',                   'ar': 'عرض السلة'},
  'chooseOption':        {'en': 'Choose Option',             'so': 'Doorasho dooro',                   'ar': 'اختر خياراً'},
  'customerReviews':     {'en': 'Customer Reviews',          'so': 'Dhagsiinaadaha macaamiisha',        'ar': 'تقييمات العملاء'},
  'noReviewsYet':        {'en': 'No reviews yet',            'so': 'Wali dhagsiinaad ma jiro',          'ar': 'لا توجد تقييمات بعد'},
  'beFirstToReview':     {'en': 'Be the first to review this product!', 'so': 'Adiga ku noqo kii ugu horeeyay ee uu dhagsiinaaya!', 'ar': 'كن أول من يقيّم هذا المنتج!'},
  'shareExperience':     {'en': 'Share Your Experience',    'so': 'Khibradaada wadaag',                'ar': 'شارك تجربتك'},
  'helpOthersReview':    {'en': 'Help others by reviewing the products you received.','so': 'Dadka kale ka caawin dhagsiinaanta badeecadaha aad heshay.','ar': 'ساعد الآخرين بمراجعة المنتجات التي تلقيتها.'},
  'reviewBtn':           {'en': 'Review',                   'so': 'Dib u eeg',                        'ar': 'مراجعة'},
  'startShoppingNow':    {'en': 'Start Shopping',           'so': 'Iibsiga bilow',                     'ar': 'ابدأ التسوق'},
  'signInToViewOrders':  {'en': 'Sign In to View Orders',   'so': 'Gal si aad dalabyada u aragto',    'ar': 'سجّل دخولك لعرض الطلبات'},
  'processing':          {'en': 'Processing',   'so': 'La diyaarinayaa',    'ar': 'جارٍ المعالجة'},
  'shipped':             {'en': 'Shipped',       'so': 'La diray',           'ar': 'تم الشحن'},
  'orderCancelled':      {'en': 'Order Cancelled','so': 'Dalabka la joojiyay','ar': 'تم إلغاء الطلب'},
  'orderStatus':         {'en': 'Order Status',  'so': 'Xaaladda dalabka',   'ar': 'حالة الطلب'},
  'parcelDelivery':      {'en': 'Parcel Delivery','so': 'Gaarsiinta xirmada', 'ar': 'توصيل الطرد'},
  'pickup':              {'en': 'Pickup',         'so': 'La qaato',           'ar': 'الاستلام'},
  'delivery':            {'en': 'Delivery',       'so': 'Gaarsiinta',         'ar': 'التوصيل'},
  'packageContents':     {'en': 'Package Contents','so': 'Waxa xirmadu ku jirto','ar': 'محتويات الطرد'},
  'items':               {'en': 'Items',         'so': 'Walxaha',            'ar': 'العناصر'},
  'startShoppingOrders': {'en': 'Start shopping to see your orders here','so': 'Wax iibso si aad halkaan dalabaadaada uga aragto','ar': 'تسوّق لترى طلباتك هنا'},

  // Home screen extended
  'goodMorning':      {'en': 'Good morning',           'so': 'Subax wanaagsan',          'ar': 'صباح الخير'},
  'goodAfternoon':    {'en': 'Good afternoon',          'so': 'Galab wanaagsan',           'ar': 'مساء الخير'},
  'goodEvening':      {'en': 'Good evening',            'so': 'Habeenimo wanaagsan',       'ar': 'مساء النور'},
  'allServices':      {'en': 'All Services',            'so': 'Dhammaan adeegyada',        'ar': 'جميع الخدمات'},
  'activeOrder':      {'en': 'Active Order',            'so': 'Dalabka socda',             'ar': 'الطلب النشط'},
  'recentOrders':     {'en': 'Recent Orders',           'so': 'Dalabyada dhowaan',         'ar': 'الطلبات الأخيرة'},
  'noActiveOrders':   {'en': 'No active orders',        'so': 'Wali dalabo firfircoon ma jiraan','ar': 'لا توجد طلبات نشطة'},
  'shopNow':          {'en': 'Shop Now',                'so': 'Hadda iibso',               'ar': 'تسوّق الآن'},
  'trackNow':         {'en': 'Track',                   'so': 'La sooc',                   'ar': 'تتبع'},
  'viewOrder':        {'en': 'View Order',              'so': 'Dalabka eeg',               'ar': 'عرض الطلب'},
  'bannerTagline':    {'en': 'Everything you need\nis now in one App', 'so': 'Wax kasta oo aad u baahan tahay\nhadda hal App ayay ku jiraan', 'ar': 'كل ما تحتاجه\nالآن في تطبيق واحد'},

  // Service names
  'orderFood':        {'en': 'Order Food',              'so': 'Cunto dalbo',               'ar': 'اطلب طعاماً'},
  'grocery':          {'en': 'Grocery',                 'so': 'Khudradda',                 'ar': 'البقالة'},
  'shopping':         {'en': 'Shopping',                'so': 'Iibsi',                     'ar': 'التسوق'},
  'moving':           {'en': 'Moving',                  'so': 'Guri u guuris',             'ar': 'النقل'},
  'rentals':          {'en': 'Rentals',                 'so': 'Kiro',                      'ar': 'الإيجارات'},
  'learning':         {'en': 'Learning',                'so': 'Barashada',                 'ar': 'التعلم'},
  'dataPlans':        {'en': 'Data Plans',              'so': 'Xogta interneetka',         'ar': 'خطط البيانات'},
  'exchange':         {'en': 'Exchange',                'so': 'Beddelka lacagta',           'ar': 'الصرافة'},
  'tickets':          {'en': 'Tickets',                 'so': 'Tikitida',                  'ar': 'التذاكر'},
  'laundry':          {'en': 'Laundry',                 'so': 'Dhaqida dharka',            'ar': 'غسيل الملابس'},
  'health':           {'en': 'Health',                  'so': 'Caafimaadka',               'ar': 'الصحة'},
  'wholesale':        {'en': 'Wholesale',               'so': 'Kubta',                     'ar': 'الجملة'},

  // eFood / eGrocery / eShop
  'popularRestaurants':{'en': 'Popular Restaurants',   'so': 'Makhaayadaha caanka ah',    'ar': 'المطاعم الشهيرة'},
  'popularItems':      {'en': 'Popular Items',          'so': 'Wax badan la iibsado',      'ar': 'الأصناف الشائعة'},
  'featuredStores':    {'en': 'Featured Stores',        'so': 'Dukaanada la xushay',       'ar': 'المتاجر المميزة'},
  'allRestaurants':    {'en': 'All Restaurants',        'so': 'Dhammaan makhaayadaha',     'ar': 'جميع المطاعم'},
  'allStores':         {'en': 'All Stores',             'so': 'Dhammaan dukaanada',        'ar': 'جميع المتاجر'},
  'nearYou':           {'en': 'Near You',               'so': 'Kaa dhow',                  'ar': 'بالقرب منك'},
  'openNow':           {'en': 'Open Now',               'so': 'Hadda furan',               'ar': 'مفتوح الآن'},
  'closed':            {'en': 'Closed',                 'so': 'Xidhan',                    'ar': 'مغلق'},
  'freeDelivery':      {'en': 'Free Delivery',          'so': 'Bilaash gaadhsiin',         'ar': 'توصيل مجاني'},
  'addItem':           {'en': 'Add',                    'so': 'Ku dar',                    'ar': 'أضف'},
  'yourCart':          {'en': 'Your Cart',              'so': 'Dambiishtagaaga',           'ar': 'سلتك'},
  'orderNow':          {'en': 'Order Now',              'so': 'Hadda dalbo',               'ar': 'اطلب الآن'},
  'deliveryAddress':   {'en': 'Delivery Address',       'so': 'Cinwaanka gaadhsiinta',     'ar': 'عنوان التوصيل'},
  'paymentMethod':     {'en': 'Payment Method',         'so': 'Hab bixinta lacagta',       'ar': 'طريقة الدفع'},
  'free':              {'en': 'Free',                   'so': 'Bilaash',                   'ar': 'مجاناً'},
  'estimatedTime':     {'en': 'Estimated Time',         'so': 'Waqtiga la filayo',         'ar': 'الوقت التقديري'},
  'specialInstructions':{'en': 'Special Instructions',  'so': 'Tilmaamo gaar ah',          'ar': 'تعليمات خاصة'},
  'optional':          {'en': 'Optional',               'so': 'Ikhtiyaari',                'ar': 'اختياري'},
  'selectPayment':     {'en': 'Select Payment Method',  'so': 'Hab bixinta dooro',         'ar': 'اختر طريقة الدفع'},
  'cashOnDelivery':    {'en': 'Cash on Delivery',       'so': 'Lacag markuu yimaado',      'ar': 'الدفع عند الاستلام'},
  'walletPay':         {'en': 'Wallet',                 'so': 'Bursadda',                  'ar': 'المحفظة'},
  'confirmOrder':      {'en': 'Confirm Order',          'so': 'Dalabka xaqiiji',           'ar': 'تأكيد الطلب'},
  'orderConfirmed':    {'en': 'Order Confirmed!',       'so': 'Dalabkii la xaqiijiyay!',   'ar': 'تم تأكيد الطلب!'},
  'thankYou':          {'en': 'Thank you for your order','so': 'Dalabkaaga mahadsanid',    'ar': 'شكراً على طلبك'},
  'foodCategories':    {'en': 'Food Categories',        'so': 'Qaybaha cuntada',           'ar': 'فئات الطعام'},
  'topRated':          {'en': 'Top Rated',              'so': 'Ugu qiimaha sarreeya',      'ar': 'الأعلى تقييماً'},
  'searchFood':        {'en': 'Search',                 'so': 'Raadi',                     'ar': 'بحث'},
  'searchRestaurants': {'en': 'Search restaurants, food...','so': 'Makhaayadaha, cuntada raadi...','ar': 'ابحث عن مطاعم، طعام...'},
  'searchFoodHint':    {'en': 'Search for food or restaurants...','so': 'Cunto ama makhaayadaha raadi...','ar': 'ابحث عن طعام أو مطاعم...'},
  'searchMenuHint':    {'en': 'Search menu items...',   'so': 'Waxyaabaha liiska raadi...', 'ar': 'ابحث في القائمة...'},
  'restaurantClosed':  {'en': 'This restaurant is currently closed. You cannot place an order at this time.',
                        'so': 'Makhaayaddan hadda waa xidhan tahay. Haddeer dalabo kama samayn kartid.', 'ar': 'هذا المطعم مغلق حالياً. لا يمكنك تقديم طلب الآن.'},
  'cannotOrder':       {'en': 'This restaurant is currently closed. Cannot place order.',
                        'so': 'Makhaayaddan waa xidhan tahay. Dalabo ma samayn kartid.', 'ar': 'المطعم مغلق حالياً. لا يمكن تقديم الطلب.'},
  'menuTab':           {'en': 'Menu',                   'so': 'Liiska',                    'ar': 'القائمة'},
  'infoTab':           {'en': 'Info',                   'so': 'Macluumaad',                'ar': 'معلومات'},
  'noResults':         {'en': 'No results',             'so': 'Natiijo ma jirto',          'ar': 'لا توجد نتائج'},
  'searchFavFood':     {'en': 'Search for your favourite food','so': 'Cuntadaada jeceshahay raadi','ar': 'ابحث عن طعامك المفضل'},
  'distance':          {'en': 'Distance',               'so': 'Masaafada',                 'ar': 'المسافة'},
  'deliveryTime':      {'en': 'Delivery Time',          'so': 'Waqtiga gaadhsiinta',       'ar': 'وقت التوصيل'},
  'deliveryBy':        {'en': 'Delivery by',            'so': 'Gaadhsiinta waxaa qabtaa',  'ar': 'التوصيل عبر'},
  'menuCategories':    {'en': 'Menu Categories',        'so': 'Qaybaha liiska',            'ar': 'فئات القائمة'},
  'offersAndCoupons':  {'en': 'Offers & Coupons',       'so': 'Dulsoorka & Kuuboonada',    'ar': 'العروض والقسائم'},
  'noMinimum':         {'en': 'No minimum',             'so': 'Kama yar',                  'ar': 'لا حد أدنى'},
  'minOrderLabel':     {'en': 'Min order',              'so': 'Ugu yar dalabka',           'ar': 'الحد الأدنى للطلب'},
  'gotIt':             {'en': 'Got it!',                'so': 'Waad garatay!',             'ar': 'فهمت!'},
  'searchGroceries':   {'en': 'Search groceries...',    'so': 'Khudrada raadi...',         'ar': 'ابحث عن البقالة...'},
  'noProductsFound':   {'en': 'No products found',      'so': 'Badeecad la ma helin',      'ar': 'لم يتم العثور على منتجات'},
  'updateCart':        {'en': 'Update Cart',            'so': 'Dambiishta cusboone',       'ar': 'تحديث السلة'},
  'cartUpdated':       {'en': 'Cart updated!',          'so': 'Dambiishta la cusbooneeye!','ar': 'تم تحديث السلة!'},
  'addedToCartMsg':    {'en': 'Added to cart!',         'so': 'Dambiishta lagu daray!',    'ar': 'تمت الإضافة للسلة!'},
  'selectDistrict2':   {'en': 'Select delivery district','so': 'Degmada gaarsiinta dooro', 'ar': 'اختر منطقة التوصيل'},
  'groceryOrderPlaced':{'en': 'Grocery order placed! 🛒','so': 'Dalabkii khudrada la diray! 🛒','ar': 'تم تقديم طلب البقالة! 🛒'},
  'orderSummaryLabel': {'en': 'Order Summary',          'so': 'Koobaad dalabka',           'ar': 'ملخص الطلب'},

  // eParcel / eMoving
  'pickupLocation':    {'en': 'Pickup Location',        'so': 'Meelaha la qaadayo',        'ar': 'موقع الاستلام'},
  'dropoffLocation':   {'en': 'Drop-off Location',      'so': 'Meelaha lagu dhiibayo',     'ar': 'موقع التسليم'},
  'packageType':       {'en': 'Package Type',           'so': 'Nooca xirmada',             'ar': 'نوع الطرد'},
  'weight':            {'en': 'Weight',                 'so': 'Culus',                     'ar': 'الوزن'},
  'getQuote':          {'en': 'Get Quote',              'so': 'Qiimaha hel',               'ar': 'احصل على عرض سعر'},
  'bookNow':           {'en': 'Book Now',               'so': 'Hadda buugso',              'ar': 'احجز الآن'},
  'scheduledTime':     {'en': 'Scheduled Time',         'so': 'Waqtiga la qorsheeyay',     'ar': 'الوقت المحدد'},
  'selectDate':        {'en': 'Select Date',            'so': 'Taariikha dooro',           'ar': 'اختر التاريخ'},
  'selectTime':        {'en': 'Select Time',            'so': 'Waqtiga dooro',             'ar': 'اختر الوقت'},

  // eRent
  'availableProperties':{'en': 'Available Properties',  'so': 'Guraha la kireyn karo',     'ar': 'العقارات المتاحة'},
  'rentPerMonth':       {'en': '/month',                'so': '/bishii',                   'ar': '/شهرياً'},
  'bedrooms':           {'en': 'Bedrooms',              'so': 'Qolalka jiifka',            'ar': 'غرف النوم'},
  'bathrooms':          {'en': 'Bathrooms',             'so': 'Musqusha',                  'ar': 'دورات المياه'},
  'requestViewing':     {'en': 'Request Viewing',       'so': 'Arag codso',                'ar': 'طلب معاينة'},

  // Notifications / Inbox
  'markAllRead':        {'en': 'Mark all as read',      'so': 'Dhammaan akhri',            'ar': 'تعيين الكل كمقروء'},
  'noNotifications':    {'en': 'No notifications yet',  'so': 'Ogeysiis ma jirto wali',    'ar': 'لا توجد إشعارات بعد'},
  'inbox':              {'en': 'Inbox',                  'so': 'Sanduuqa',                  'ar': 'صندوق الوارد'},
  'inboxSubtitle':      {'en': 'Your messages & notifications','so': 'Farriimahaada & ogeysiisyada','ar': 'رسائلك وإشعاراتك'},
  'support':            {'en': 'Support',                'so': 'Taageero',                  'ar': 'الدعم'},
  'marketing':          {'en': 'Promotions',             'so': 'Xayaysiisyada',             'ar': 'العروض الترويجية'},
  'newRequest':         {'en': 'New Request',            'so': 'Codsi cusub',               'ar': 'طلب جديد'},
  'unread':             {'en': 'unread',                 'so': 'la akhrinin',               'ar': 'غير مقروء'},

  // Order Tracking
  'orderTracking':      {'en': 'Order Tracking',         'so': 'La socoshada dalabka',      'ar': 'تتبع الطلب'},
  'estimatedDelivery':  {'en': 'Estimated Delivery',     'so': 'Gaadhsiinta la filayo',     'ar': 'التسليم التقديري'},
  'driverOnTheWay':     {'en': 'Driver is on the way',   'so': 'Darawalku wuu socdaa',      'ar': 'السائق في الطريق'},
  'orderPickedUp':      {'en': 'Order picked up',        'so': 'Dalabka la qaaday',         'ar': 'تم استلام الطلب'},
  'orderDelivered':     {'en': 'Order delivered!',       'so': 'Dalabkii la gaarsiiay!',    'ar': 'تم توصيل الطلب!'},
  'autoRefresh':        {'en': 'Auto-refresh',           'so': 'Dib u cusboonaysiin',       'ar': 'تحديث تلقائي'},
  'orderPlacedStep':    {'en': 'Order Placed',           'so': 'Dalabka la diray',          'ar': 'تم الطلب'},
  'confirmed':          {'en': 'Confirmed',              'so': 'La xaqiijiyay',             'ar': 'مؤكد'},
  'preparing':          {'en': 'Preparing',              'so': 'La diyaarinayaa',           'ar': 'جارٍ التحضير'},
  'onTheWay':           {'en': 'On the way',             'so': 'Wuu socondaa',              'ar': 'في الطريق'},
  'restaurant':         {'en': 'Restaurant',             'so': 'Makhaayad',                 'ar': 'المطعم'},
  'driver':             {'en': 'Driver',                 'so': 'Darawal',                   'ar': 'السائق'},
  'liveTracking':       {'en': 'Live Tracking',          'so': 'Raadraac toos ah',          'ar': 'تتبع مباشر'},
  'mobileOnly':         {'en': 'Available on the mobile app','so': 'App-ka mobilka ku heli kartaa','ar': 'متاح في تطبيق الهاتف'},

  // eShop cart/checkout misc
  'clearAll':          {'en': 'Clear All',              'so': 'Dhammaan tirtir',           'ar': 'مسح الكل'},
  'browseProducts':    {'en': 'Browse Products',        'so': 'Badeecadaha raadi',         'ar': 'تصفح المنتجات'},
  'addProductsToStart':{'en': 'Add products to get started','so': 'Badeecado ku dar si aad bilowdid','ar': 'أضف منتجات للبدء'},
  'viewOrders':        {'en': 'View Orders',            'so': 'Dalabyada eeg',             'ar': 'عرض الطلبات'},
  'orderPlacedSuccess':{'en': 'Your order has been placed successfully. You will receive a confirmation shortly.','so': 'Dalabkaagii si fiican ayaa loo diray. Xaqiijin ayaad waxaad helaysaa dhawaan.','ar': 'تم تقديم طلبك بنجاح. ستتلقى تأكيداً قريباً.'},
  'tapToChangeDistrict':{'en': 'Tap to change district','so': 'Degmada beddesho taabo',    'ar': 'اضغط لتغيير المنطقة'},
  'chooseWhereToDeliver':{'en': 'Choose where to deliver','so': 'Meeshii gaarsiinta u dooro','ar': 'اختر مكان التوصيل'},
  'selectDistrictBtn': {'en': 'Select District',        'so': 'Degmada dooro',             'ar': 'اختر المنطقة'},
  'couponCode':        {'en': 'Coupon Code',            'so': 'Koodhka kuuboonka',         'ar': 'رمز القسيمة'},

  // eShop
  'searchProducts':    {'en': 'Search products...',      'so': 'Badeecadaha raadi...',       'ar': 'ابحث عن منتجات...'},
  'stores':            {'en': 'Stores',                  'so': 'Dukaanada',                  'ar': 'المتاجر'},
  'campaigns':         {'en': 'Campaigns',               'so': 'Ololaha',                    'ar': 'الحملات'},
  'featuredProducts':  {'en': 'Featured Products',       'so': 'Badeecadaha la xushay',      'ar': 'المنتجات المميزة'},
  'mostPopular':       {'en': 'Most Popular',            'so': 'Ugu badan la iibsado',       'ar': 'الأكثر شعبية'},
  'allProducts':       {'en': 'All Products',            'so': 'Dhammaan badeecadaha',       'ar': 'جميع المنتجات'},
  'dealsOfDay':        {'en': 'Deals of the Day',        'so': 'Dulsoorada maanta',          'ar': 'عروض اليوم'},
  'hurryOfferEndsSoon':{'en': 'Hurry! Offer ends soon',  'so': 'Deg deg! Bixitaanku waxa uu dhammaanayaa degdeg', 'ar': 'أسرع! العرض ينتهي قريباً'},
  'limitedOffers':     {'en': 'Limited offers',          'so': 'Bixitaano xaddidan',         'ar': 'عروض محدودة'},
  'upToOff':           {'en': 'Up to',                   'so': 'Ilaa',                       'ar': 'حتى'},
  'off':               {'en': 'OFF',                     'so': 'DHIMIS',                     'ar': 'خصم'},
  'hrs':               {'en': 'HRS',                     'so': 'SAA',                        'ar': 'س'},
  'minLabel':          {'en': 'MIN',                     'so': 'DQ',                         'ar': 'د'},
  'secLabel':          {'en': 'SEC',                     'so': 'SN',                         'ar': 'ث'},

  // eParcel
  'senderInfo':        {'en': 'Sender Information',      'so': 'Macluumaadka dirayaha',      'ar': 'معلومات المرسِل'},
  'receiverInfo':      {'en': 'Receiver Information',    'so': 'Macluumaadka qaabilaha',     'ar': 'معلومات المستلِم'},
  'pkgTypeStep':       {'en': 'Package Type',            'so': 'Nooca xirmada',              'ar': 'نوع الطرد'},
  'routeStep':         {'en': 'Route',                   'so': 'Wadada',                     'ar': 'المسار'},
  'fastReliable':      {'en': 'Fast & Reliable Delivery','so': 'Gaarsiinta degdega & la isku halleyn karo','ar': 'توصيل سريع وموثوق'},
  'getDeliveryPrice':  {'en': 'Get Delivery Price',      'so': 'Qiimaha gaarsiinta hel',     'ar': 'احصل على سعر التوصيل'},
  'sendParcelNow':     {'en': 'Send Parcel Now',         'so': 'Xirmada hadda dir',          'ar': 'أرسل الطرد الآن'},
  'parcelPlaced':      {'en': 'Parcel order placed successfully! 🎉','so': 'Dalabka xirmada si fiican ayaa loo diray! 🎉','ar': 'تم تقديم طلب الطرد بنجاح! 🎉'},
  'calculating':       {'en': 'Calculating...',          'so': 'Xisaabinaya...',             'ar': 'جارٍ الحساب...'},
  'placingOrder':      {'en': 'Placing Order...',        'so': 'Dalabka la dirayaa...',      'ar': 'جارٍ تقديم الطلب...'},
  'changeDetails':     {'en': 'Change details',          'so': 'Faahfaahinta bedel',         'ar': 'تغيير التفاصيل'},
  'enterReceiverName': {'en': 'Enter receiver name',     'so': 'Magaca qaabilaha geli',      'ar': 'أدخل اسم المستلم'},
  'enterReceiverPhone':{'en': 'Enter receiver phone',    'so': 'Lambarka qaabilaha geli',    'ar': 'أدخل هاتف المستلم'},
  'nameLabel':         {'en': 'Name',                    'so': 'Magaca',                     'ar': 'الاسم'},
  'phoneLabel':        {'en': 'Phone',                   'so': 'Telefoonka',                 'ar': 'الهاتف'},
  'receiverName':      {'en': 'Receiver Name',           'so': 'Magaca qaabilaha',           'ar': 'اسم المستلم'},
  'receiverPhone':     {'en': 'Receiver Phone',          'so': 'Telefoonka qaabilaha',       'ar': 'هاتف المستلم'},
  'selectPkgType':     {'en': 'Select package type',     'so': 'Nooca xirmada dooro',        'ar': 'اختر نوع الطرد'},
  'pickupDistrict':    {'en': 'Pickup District',         'so': 'Degmada qaadista',           'ar': 'منطقة الاستلام'},
  'deliveryDistrict':  {'en': 'Delivery District',       'so': 'Degmada gaarsiinta',         'ar': 'منطقة التوصيل'},
  'pkgContentsHint':   {'en': 'What is inside the package? (Optional)','so': 'Xirmadu maxay ku jirtaa? (Ikhtiyaari)','ar': 'ما يوجد داخل الطرد؟ (اختياري)'},
  'pickupLabel':       {'en': 'Pickup',                  'so': 'Qaadista',                   'ar': 'الاستلام'},
  'deliveryLabel':     {'en': 'Delivery Price',          'so': 'Qiimaha gaarsiinta',         'ar': 'سعر التوصيل'},
  'flatRate':          {'en': 'Flat Rate',               'so': 'Qiime go\'an',               'ar': 'سعر ثابت'},

  // eMoving
  'bookMove':          {'en': 'Book Move',               'so': 'Guuris buugso',              'ar': 'احجز نقل'},
  'myMovingOrders':    {'en': 'My Orders',               'so': 'Dalabyadadayda',             'ar': 'طلباتي'},
  'professionalMoving':{'en': 'Professional Moving Services','so': 'Adeegyada guurista xirfadlaha ah','ar': 'خدمات نقل احترافية'},
  'selectMoveType':    {'en': 'Select Move Type',        'so': 'Nooca guurista dooro',       'ar': 'اختر نوع النقل'},
  'chooseMoveNeeds':   {'en': 'Choose what you need to move','so': 'Maxaad rabtaa in la guuriyo dooro','ar': 'اختر ما تريد نقله'},
  'selectRoute':       {'en': 'Select Route',            'so': 'Wadada dooro',               'ar': 'اختر المسار'},
  'pickupDeliveryDistricts':{'en': 'Pickup and delivery districts','so': 'Degmada qaadista iyo gaarsiinta','ar': 'مناطق الاستلام والتوصيل'},
  'extraServices':     {'en': 'Extra Services',          'so': 'Adeegyada dheeraadka ah',    'ar': 'خدمات إضافية'},
  'optionalAddOns':    {'en': 'Optional add-ons for your move','so': 'Ikhtiyaar ah oo kuu darsanaya guuris','ar': 'إضافات اختيارية لنقلك'},
  'priceEstimate':     {'en': 'Price Estimate',          'so': 'Qiyaasta qiimaha',           'ar': 'تقدير السعر'},
  'tapCalculate':      {'en': 'Tap Calculate to get your quote','so': 'Xisaabi taabo si aad qiimaha u heshid','ar': 'اضغط حساب للحصول على عرض سعر'},
  'bookMovingService': {'en': 'Book Moving Service',     'so': 'Adeegga guurista buugso',    'ar': 'احجز خدمة النقل'},
  'basePrice':         {'en': 'Base Price',              'so': 'Qiimaha asaasiga',           'ar': 'السعر الأساسي'},
  'roomCost':          {'en': 'Room Cost',               'so': 'Kharashka qolalka',          'ar': 'تكلفة الغرف'},
  'packageCost':       {'en': 'Package Cost',            'so': 'Kharashka xirmada',          'ar': 'تكلفة الحزمة'},
  'distanceFee':       {'en': 'Distance Fee',            'so': 'Kharashka masaafada',        'ar': 'رسوم المسافة'},
  'calculatePrice':    {'en': 'Calculate Price',         'so': 'Qiimaha xisaabi',            'ar': 'احسب السعر'},
  'numberOfRooms':     {'en': 'Number of Rooms',         'so': 'Tirada qolalka',             'ar': 'عدد الغرف'},
  'fromDistrict':      {'en': 'From District',           'so': 'Degmada laga bilaabayo',     'ar': 'من منطقة'},
  'toDistrict':        {'en': 'To District',             'so': 'Degmada loo socdo',          'ar': 'إلى منطقة'},
  'selectExtraServices':{'en': 'Select extra services',  'so': 'Adeegyada dheeraadka dooro', 'ar': 'اختر الخدمات الإضافية'},
  'roomCountQ':        {'en': 'Room Count',              'so': 'Tirada qolalka',             'ar': 'عدد الغرف'},
  'selectPackage':     {'en': 'Select Package',          'so': 'Xirmada dooro',              'ar': 'اختر الحزمة'},
  'howManyRooms':      {'en': 'How many rooms are you moving?','so': 'Imisa qol baad guurinaysa?','ar': 'كم غرفة ستنقل؟'},
  'pickPackageFits':   {'en': 'Pick the package that fits your needs','so': 'Xirmada aad u baahantahay dooro','ar': 'اختر الحزمة المناسبة لاحتياجاتك'},
  'noPackagesContact': {'en': 'No packages available — contact us for a custom quote','so': 'Xirmo ma jiraan — nala soo xiriir qiime gaara','ar': 'لا توجد حزم — تواصل معنا للحصول على عرض مخصص'},
  'orSelectPackage':   {'en': 'Or select a package:',   'so': 'Ama xirmo dooro:',           'ar': 'أو اختر حزمة:'},
  'searchDistrict':    {'en': 'Search district...',      'so': 'Degmada raadi...',           'ar': 'ابحث عن منطقة...'},
  'tapToSelect':       {'en': 'Tap to select',          'so': 'Taabo si aad u dooratid',    'ar': 'اضغط للاختيار'},
  'selected':          {'en': 'Selected',                'so': 'La doortay',                 'ar': 'محدد'},
  'bookingFailed':     {'en': 'Booking failed',          'so': 'Buugashada way ku guul darraysatay','ar': 'فشل الحجز'},
  'calcFailed':        {'en': 'Calculation failed',      'so': 'Xisaabintu way ku guul darraysatay','ar': 'فشل الحساب'},

  // eRent
  'browse':            {'en': 'Browse',                  'so': 'Raadi',                      'ar': 'تصفح'},
  'myBookings':        {'en': 'My Bookings',             'so': 'Buugashadadayda',            'ar': 'حجوزاتي'},
  'findAgent':         {'en': 'Find Agent',              'so': 'Wakiil raadi',               'ar': 'إيجاد وكيل'},
  'noDistricts':       {'en': 'No Districts',            'so': 'Degmo ma jiraan',            'ar': 'لا توجد مناطق'},
  'browseByDistrict':  {'en': 'Browse by District',      'so': 'Degmada ku raadi',           'ar': 'تصفح حسب المنطقة'},
  'searchPropertiesBtn':{'en': 'Search Properties',      'so': 'Guryaha raadi',              'ar': 'البحث عن عقارات'},
  'noProperties':      {'en': 'No Properties',           'so': 'Guri ma jiraan',             'ar': 'لا توجد عقارات'},
  'reserved':          {'en': 'Reserved',                'so': 'La qabsaday',                'ar': 'محجوز'},
  'available':         {'en': 'Available',               'so': 'La heli karo',               'ar': 'متاح'},
  'agentLabel':        {'en': 'Agent',                   'so': 'Wakiil',                     'ar': 'الوكيل'},
  'anyType':           {'en': 'Any Type',                'so': 'Nooc kasta',                 'ar': 'أي نوع'},
  'anyDistrict':       {'en': 'Any District',            'so': 'Degmo kasta',                'ar': 'أي منطقة'},
  'anyPrice':          {'en': 'Any Price',               'so': 'Qiime kasta',                'ar': 'أي سعر'},
  'fullRent':          {'en': 'Full Rent',               'so': 'Kiro buuxda',                'ar': 'إيجار كامل'},
  'selectLocation':    {'en': 'Select Location',         'so': 'Goobta dooro',               'ar': 'اختر الموقع'},
  'propertyType':      {'en': 'Property Type',           'so': 'Nooca guriga',               'ar': 'نوع العقار'},
  'unitType':          {'en': 'Unit Type (Bedrooms)',     'so': 'Nooca qolka (Qolalka Jiifka)','ar': 'نوع الوحدة (غرف النوم)'},
  'priceRange':        {'en': 'Price Range',             'so': 'Kala duwan qiimaha',         'ar': 'نطاق السعر'},
  'findYourHome':      {'en': 'Find Your Home',          'so': 'Gurigaaga raadi',            'ar': 'ابحث عن منزلك'},
  'browsePropertiesByDistrict':{'en': 'Browse properties by district','so': 'Guryaha degmada ku raadi','ar': 'تصفح العقارات حسب المنطقة'},
  'noPropertiesIn':    {'en': 'No properties available in', 'so': 'Guri lama helin',         'ar': 'لا توجد عقارات في'},

  // eData
  'buyData':           {'en': 'Buy Data',                'so': 'Xog iibso',                  'ar': 'شراء بيانات'},
  'history':           {'en': 'History',                 'so': 'Taariikhda',                 'ar': 'السجل'},
  'mobileDataBundles': {'en': 'Mobile Data & Bundles',   'so': 'Xogta mobilka & xirmada',    'ar': 'بيانات الجوال والحزم'},
  'chooseProvider':    {'en': 'Choose Provider',         'so': 'Bixiyaha dooro',             'ar': 'اختر المزود'},
  'noProviders':       {'en': 'No Providers',            'so': 'Bixiye ma jiraan',           'ar': 'لا يوجد مزودون'},
  'dataProvidersHere': {'en': 'Data providers will appear here','so': 'Bixiyayaasha xogta halkan ka muuqan doonaan','ar': 'سيظهر مزودو البيانات هنا'},
  'chooseProviderPickPackage':{'en': 'Choose provider, pick a package,\nthen select your bundle','so': 'Bixiyaha dooro, xirmada xeli,\nkaddibna xog-guuris dooro','ar': 'اختر المزود، اختر الحزمة،\nثم اختر البيانات'},
  'noPackages':        {'en': 'No Packages',             'so': 'Xirmo ma jiraan',            'ar': 'لا توجد حزم'},
  'noBundles':         {'en': 'No Bundles',              'so': 'Xog-guuris ma jiraan',       'ar': 'لا توجد حزم بيانات'},
  'choosePackage':     {'en': 'Choose Package',          'so': 'Xirmada dooro',              'ar': 'اختر الحزمة'},
  'chooseBundle':      {'en': 'Choose Bundle',           'so': 'Xog-guuris dooro',           'ar': 'اختر مجموعة البيانات'},
  'bookingConfirmed':  {'en': 'Booking Confirmed',       'so': 'Buugashada la xaqiijiyay',   'ar': 'تم تأكيد الحجز'},
  'dataDestination':   {'en': 'Data Destination',        'so': 'Xogta adeegsaduhu',          'ar': 'وجهة البيانات'},
  'phoneForData':      {'en': 'Phone number that will receive the data','so': 'Lambarka telefoonka ee xogta helaya','ar': 'رقم الهاتف الذي سيستقبل البيانات'},
  'bundleLabel':       {'en': 'Bundle',                  'so': 'Xog-guuriska',               'ar': 'الحزمة'},
  'providerLabel':     {'en': 'Provider',                'so': 'Bixiyaha',                   'ar': 'المزود'},
  'instantLabel':      {'en': 'Instant',                 'so': 'Degdeg',                     'ar': 'فوري'},
  'secureLabel':       {'en': 'Secure',                  'so': 'Ammaan',                     'ar': 'آمن'},
  'viewBundles':       {'en': 'View bundles',            'so': 'Xog-guurisyada eeg',         'ar': 'عرض الحزم'},

  // eTicket
  'bookFlight':        {'en': 'Book Flight',             'so': 'Duulaan buugso',             'ar': 'احجز رحلة'},
  'myTickets':         {'en': 'My Tickets',              'so': 'Tikitidayda',                'ar': 'تذاكري'},
  'bookFlightDesc':    {'en': 'Book your flight across Somalia & beyond','so': 'Duulaanka Soomaaliya iyo meelo kale ka buugso','ar': 'احجز رحلتك عبر الصومال وخارجه'},
  'oneWay':            {'en': 'One Way',                 'so': 'Dhinac keliya',              'ar': 'ذهاب فقط'},
  'roundTrip':         {'en': 'Round Trip',              'so': 'Dhowr dhinac',               'ar': 'ذهاب وعودة'},
  'fromCity':          {'en': 'From',                    'so': 'Ka',                         'ar': 'من'},
  'toCity':            {'en': 'To',                      'so': 'Ula soco',                   'ar': 'إلى'},
  'departureCity':     {'en': 'Departure city',          'so': 'Magaalada dhegreydaa',       'ar': 'مدينة المغادرة'},
  'destinationCity':   {'en': 'Destination city',        'so': 'Magaalada aad tageysid',     'ar': 'مدينة الوصول'},
  'departureDate':     {'en': 'Departure',               'so': 'Bixitaanka',                 'ar': 'المغادرة'},
  'returnDate':        {'en': 'Return',                  'so': 'Celitaanka',                 'ar': 'العودة'},
  'searchFlights':     {'en': 'Search Flights',          'so': 'Duulaanada raadi',           'ar': 'البحث عن رحلات'},
  'updateSearch':      {'en': 'Update Search',           'so': 'Raadinta cusboone',          'ar': 'تحديث البحث'},
  'selectCity':        {'en': 'Select City',             'so': 'Magaalada dooro',            'ar': 'اختر المدينة'},
  'searchCity':        {'en': 'Search...',               'so': 'Raadi...',                   'ar': 'ابحث...'},
};
