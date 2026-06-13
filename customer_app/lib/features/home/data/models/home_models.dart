class ModuleModel {
  final int id;
  final String name;
  final String slug;
  final String? icon;
  final String? description;
  final bool isActive;
  final int sortOrder;

  const ModuleModel({
    required this.id,
    required this.name,
    required this.slug,
    this.icon,
    this.description,
    required this.isActive,
    required this.sortOrder,
  });

  factory ModuleModel.fromJson(Map<String, dynamic> j) => ModuleModel(
    id: j['id'],
    name: j['name'],
    slug: j['slug'],
    icon: j['icon'],
    description: j['description'],
    isActive: j['is_active'] == true || j['is_active'] == 1,
    sortOrder: j['sort_order'] ?? 0,
  );

  // Emoji fallback per slug
  String get emoji {
    const map = {
      'efood': '🍔', 'eshop': '🛍️', 'eticket': '🎫',
      'ehealth': '🏥', 'edata': '📡', 'eparcel': '📦',
      'erent': '🏠', 'emoving': '🚛', 'ewholesale': '🏭',
      'egrocery': '🛒', 'eexchange': '💱', 'elaundry': '👕',
    };
    return map[slug] ?? '📱';
  }
}

class BannerModel {
  final int id;
  final String title;
  final String? subtitle;
  final String? imageUrl;
  final String? actionUrl;
  final String? actionType;

  const BannerModel({
    required this.id,
    required this.title,
    this.subtitle,
    this.imageUrl,
    this.actionUrl,
    this.actionType,
  });

  factory BannerModel.fromJson(Map<String, dynamic> j) => BannerModel(
    id: j['id'],
    title: j['title'] ?? '',
    subtitle: j['subtitle'],
    imageUrl: j['image_url'] ?? j['image'],
    actionUrl: j['action_url'],
    actionType: j['action_type'],
  );
}

class VendorModel {
  final int id;
  final String name;
  final String? logoUrl;
  final String? coverUrl;
  final String? description;
  final String moduleSlug;
  final double? rating;
  final int? reviewsCount;
  final double deliveryFee;
  final int? deliveryTime;
  final bool isOpen;
  final bool isFeatured;

  const VendorModel({
    required this.id,
    required this.name,
    this.logoUrl,
    this.coverUrl,
    this.description,
    required this.moduleSlug,
    this.rating,
    this.reviewsCount,
    this.deliveryFee = 0,
    this.deliveryTime,
    this.isOpen = true,
    this.isFeatured = false,
  });

  factory VendorModel.fromJson(Map<String, dynamic> j) => VendorModel(
    id: j['id'],
    name: j['name'] ?? '',
    logoUrl: j['logo_url'] ?? j['logo'],
    coverUrl: j['cover_url'] ?? j['cover'],
    description: j['description'],
    moduleSlug: j['module_slug'] ?? '',
    rating: (j['rating'] as num?)?.toDouble(),
    reviewsCount: j['reviews_count'],
    deliveryFee: (j['delivery_fee'] as num?)?.toDouble() ?? 0,
    deliveryTime: j['delivery_time'],
    isOpen: j['is_open'] == true || j['is_open'] == 1,
    isFeatured: j['is_featured'] == true || j['is_featured'] == 1,
  );
}

class ProductModel {
  final int id;
  final String name;
  final String? imageUrl;
  final double price;
  final double? discountPrice;
  final String? vendorName;
  final int vendorId;

  const ProductModel({
    required this.id,
    required this.name,
    this.imageUrl,
    required this.price,
    this.discountPrice,
    this.vendorName,
    required this.vendorId,
  });

  factory ProductModel.fromJson(Map<String, dynamic> j) => ProductModel(
    id: j['id'],
    name: j['name'] ?? '',
    imageUrl: j['image_url'] ?? j['image'],
    price: (j['price'] as num).toDouble(),
    discountPrice: (j['discount_price'] as num?)?.toDouble(),
    vendorName: j['vendor']?['name'],
    vendorId: j['vendor_id'] ?? j['vendor']?['id'] ?? 0,
  );

  double get effectivePrice => discountPrice ?? price;
  bool get hasDiscount => discountPrice != null && discountPrice! < price;
}
