// ─────────────────────────────────────────────────────────────────────────────
// eSahlan — App Translations
// Languages: English (en) | Somali (so) | Arabic (ar)
// ─────────────────────────────────────────────────────────────────────────────

import 'package:flutter/material.dart';
import '../providers/app_settings_provider.dart';

/// Simple translation helper.
/// Usage:  AppL10n.of(context).home
///         AppL10n.of(context).tr('home')
class AppL10n {
  final String _lang;
  const AppL10n._(this._lang);

  static AppL10n of(BuildContext context) {
    return AppL10n._(AppSettingsNotifier.current.language);
  }

  /// Use when you don't have a BuildContext (e.g. in popup widgets)
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

  // ── Coupon ───────────────────────────────────────────────────────────────────
  String get coupon          => _t('coupon', _lang);
  String get myCoupons       => _t('myCoupons', _lang);
  String get collectAll      => _t('collectAll', _lang);
  String get collect         => _t('collect', _lang);
  String get collected       => _t('collected', _lang);
  String get applyCoupon     => _t('applyCoupon', _lang);
  String get couponApplied   => _t('couponApplied', _lang);
  String get enterCouponCode => _t('enterCouponCode', _lang);
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

  // ── Status messages ───────────────────────────────────────────────────────────
  String get pleaseWait  => _t('pleaseWait', _lang);
  String get somethingWentWrong => _t('somethingWentWrong', _lang);
  String get noInternet  => _t('noInternet', _lang);
  String get tryAgain    => _t('tryAgain', _lang);
  String get loginRequired => _t('loginRequired', _lang);
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

  // Status
  'pleaseWait':         {'en': 'Please wait…',       'so': 'Fadlan sug…',             'ar': 'يرجى الانتظار…'},
  'somethingWentWrong': {'en': 'Something went wrong','so': 'Wax baa khalad ahaade',  'ar': 'حدث خطأ ما'},
  'noInternet':         {'en': 'No internet connection','so': 'Xiriirka internet ma jiro','ar': 'لا يوجد اتصال بالإنترنت'},
  'tryAgain':           {'en': 'Try again',           'so': 'Mar kale isku day',       'ar': 'حاول مرة أخرى'},
  'loginRequired':      {'en': 'Please log in first', 'so': 'Fadlan marka hore soo gal','ar': 'يرجى تسجيل الدخول أولاً'},
};
