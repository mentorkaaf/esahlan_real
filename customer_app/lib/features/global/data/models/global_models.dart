// ignore_for_file: non_constant_identifier_names

// Safe parser — handles both num and String from API
double _d(dynamic v) {
  if (v == null) return 0.0;
  if (v is num) return v.toDouble();
  return double.tryParse(v.toString()) ?? 0.0;
}

int _i(dynamic v) {
  if (v == null) return 0;
  if (v is int) return v;
  if (v is num) return v.toInt();
  return int.tryParse(v.toString()) ?? 0;
}

class GlobalUser {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? avatar;
  final String? country;
  final List<GlobalAddress> addresses;

  const GlobalUser({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.avatar,
    this.country,
    this.addresses = const [],
  });

  factory GlobalUser.fromJson(Map<String, dynamic> j) => GlobalUser(
        id: j['id'],
        name: j['name'] ?? '',
        email: j['email'] ?? '',
        phone: j['phone'],
        avatar: j['avatar'],
        country: j['country'],
        addresses: (j['addresses'] as List? ?? [])
            .map((a) => GlobalAddress.fromJson(a))
            .toList(),
      );
}

class GlobalAddress {
  final int id;
  final String name;
  final String? phone;
  final String addressLine1;
  final String? addressLine2;
  final String city;
  final String? state;
  final String zip;
  final String country;
  final bool isDefault;

  const GlobalAddress({
    required this.id,
    required this.name,
    this.phone,
    required this.addressLine1,
    this.addressLine2,
    required this.city,
    this.state,
    required this.zip,
    required this.country,
    this.isDefault = false,
  });

  factory GlobalAddress.fromJson(Map<String, dynamic> j) => GlobalAddress(
        id: j['id'] ?? 0,
        name: j['name'] ?? j['first_name'] ?? '',
        phone: j['phone'],
        addressLine1: j['address_line1'] ?? j['address_line1'] ?? '',
        addressLine2: j['address_line2'],
        city: j['city'] ?? '',
        state: j['state'],
        zip: j['zip'] ?? j['zip_code'] ?? '',
        country: j['country'] ?? j['country_code'] ?? 'US',
        isDefault: j['is_default'] == true,
      );

  String get fullAddress =>
      '$addressLine1, $city${state != null ? ', $state' : ''} $zip, $country';
}

class GlobalCategory {
  final int id;
  final String name;
  final String? icon;
  final String? image;
  final int productsCount;

  const GlobalCategory({
    required this.id,
    required this.name,
    this.icon,
    this.image,
    this.productsCount = 0,
  });

  factory GlobalCategory.fromJson(Map<String, dynamic> j) => GlobalCategory(
        id: j['id'],
        name: j['name'] ?? '',
        icon: j['icon'],
        image: j['image'],
        productsCount: j['products_count'] ?? 0,
      );
}

class GlobalProduct {
  final int id;
  final String name;
  final String? slug;
  final double price;
  final double? comparePrice;
  final int? discountPct;
  final String? thumbnail;
  final double rating;
  final int reviewsCount;
  final bool isFeatured;
  final String? type;
  final String? category;
  // Detail fields
  final String? description;
  final String? sku;
  final int? stock;
  final bool inStock;
  final List<String> images;
  final List<dynamic> variants;
  final List<String> tags;

  const GlobalProduct({
    required this.id,
    required this.name,
    this.slug,
    required this.price,
    this.comparePrice,
    this.discountPct,
    this.thumbnail,
    this.rating = 0,
    this.reviewsCount = 0,
    this.isFeatured = false,
    this.type,
    this.category,
    this.description,
    this.sku,
    this.stock,
    this.inStock = true,
    this.images = const [],
    this.variants = const [],
    this.tags = const [],
  });

  factory GlobalProduct.fromJson(Map<String, dynamic> j) => GlobalProduct(
        id: j['id'],
        name: j['name'] ?? '',
        slug: j['slug'],
        price: _d(j['price']),
        comparePrice: j['compare_price'] != null ? _d(j['compare_price']) : null,
        discountPct: j['discount_pct'],
        thumbnail: j['thumbnail'],
        rating: _d(j['rating']),
        reviewsCount: j['reviews_count'] ?? 0,
        isFeatured: j['is_featured'] == true,
        type: j['type'],
        category: j['category'],
        description: j['description'],
        sku: j['sku'],
        stock: j['stock'],
        inStock: j['in_stock'] ?? true,
        images: (j['images'] as List? ?? []).cast<String>(),
        variants: j['variants'] ?? [],
        tags: (j['tags'] as List? ?? []).cast<String>(),
      );

  String get formattedPrice => '\$${price.toStringAsFixed(2)}';
  String? get formattedComparePrice =>
      comparePrice != null ? '\$${comparePrice!.toStringAsFixed(2)}' : null;
}

class GlobalCartItem {
  final int id;
  final int productId;
  final String name;
  final String? thumbnail;
  final double price;
  final String? variant;
  int quantity;
  final double subtotal;
  final bool inStock;

  GlobalCartItem({
    required this.id,
    required this.productId,
    required this.name,
    this.thumbnail,
    required this.price,
    this.variant,
    required this.quantity,
    required this.subtotal,
    this.inStock = true,
  });

  factory GlobalCartItem.fromJson(Map<String, dynamic> j) => GlobalCartItem(
        id: j['id'],
        productId: j['product_id'],
        name: j['name'] ?? '',
        thumbnail: j['thumbnail'],
        price: _d(j['price']),
        variant: j['variant'],
        quantity: _i(j['quantity']) == 0 ? 1 : _i(j['quantity']),
        subtotal: _d(j['subtotal']),
        inStock: j['in_stock'] ?? true,
      );
}

class GlobalCart {
  final List<GlobalCartItem> items;
  final int count;
  final double subtotal;

  const GlobalCart({
    this.items = const [],
    this.count = 0,
    this.subtotal = 0,
  });

  factory GlobalCart.fromJson(Map<String, dynamic> j) => GlobalCart(
        items: (j['items'] as List? ?? [])
            .map((i) => GlobalCartItem.fromJson(i))
            .toList(),
        count: j['count'] ?? 0,
        subtotal: _d(j['subtotal']),
      );
}

class GlobalOrder {
  final int id;
  final String orderNumber;
  final String status;
  final double total;
  final String currency;
  final int itemsCount;
  final String? thumbnail;
  final String createdAt;
  // Detail fields
  final List<GlobalOrderItem> items;
  final String? shippingAddress;
  final String? trackingNumber;
  final String? trackingUrl;
  final String? paymentMethod;
  final String? paymentStatus;
  final String? paidAt;
  final String? shippedAt;
  final String? shippingCarrier;

  const GlobalOrder({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.total,
    this.currency = 'USD',
    this.itemsCount = 0,
    this.thumbnail,
    required this.createdAt,
    this.items = const [],
    this.shippingAddress,
    this.trackingNumber,
    this.trackingUrl,
    this.paymentMethod,
    this.paymentStatus,
    this.paidAt,
    this.shippedAt,
    this.shippingCarrier,
  });

  factory GlobalOrder.fromJson(Map<String, dynamic> j) => GlobalOrder(
        id: j['id'],
        orderNumber: j['order_number'] ?? '',
        status: j['status'] ?? 'pending',
        total: _d(j['total']),
        currency: j['currency'] ?? 'USD',
        itemsCount: j['items_count'] ?? (j['items'] as List?)?.length ?? 0,
        thumbnail: j['thumbnail'],
        createdAt: j['created_at'] ?? '',
        items: (j['items'] as List? ?? [])
            .map((i) => GlobalOrderItem.fromJson(i))
            .toList(),
        shippingAddress: j['shipping_address'],
        trackingNumber: j['tracking_number'],
        trackingUrl: j['tracking_url'],
        paymentMethod: j['payment_method'],
        paymentStatus: j['payment_status'],
        paidAt: j['paid_at'],
        shippedAt: j['shipped_at'],
        shippingCarrier: j['shipping_carrier'],
      );
}

class GlobalOrderItem {
  final int id;
  final String name;
  final String? variant;
  final int quantity;
  final double unitPrice;
  final double total;
  final String? thumbnail;

  const GlobalOrderItem({
    required this.id,
    required this.name,
    this.variant,
    required this.quantity,
    required this.unitPrice,
    required this.total,
    this.thumbnail,
  });

  factory GlobalOrderItem.fromJson(Map<String, dynamic> j) => GlobalOrderItem(
        id: j['id'],
        name: j['name'] ?? j['product_name'] ?? '',
        variant: j['variant'],
        quantity: j['quantity'] ?? 1,
        unitPrice: _d(j['unit_price']),
        total: _d(j['total']),
        thumbnail: j['thumbnail'],
      );
}

class GlobalReview {
  final int id;
  final int rating;
  final String? title;
  final String? body;
  final String userName;
  final String? userCountry;
  final String createdAt;

  const GlobalReview({
    required this.id,
    required this.rating,
    this.title,
    this.body,
    required this.userName,
    this.userCountry,
    required this.createdAt,
  });

  factory GlobalReview.fromJson(Map<String, dynamic> j) => GlobalReview(
        id: j['id'] ?? 0,
        rating: _i(j['rating']),
        title: j['title'],
        body: j['body'],
        userName: j['user_name'] ?? 'Anonymous',
        userCountry: j['user_country'],
        createdAt: j['created_at'] ?? '',
      );
}

class GlobalReviewStats {
  final int total;
  final double average;
  final Map<int, int> distribution; // 1-5 => count

  const GlobalReviewStats({
    required this.total,
    required this.average,
    required this.distribution,
  });

  factory GlobalReviewStats.fromJson(Map<String, dynamic> j) =>
      GlobalReviewStats(
        total: _i(j['total']),
        average: _d(j['average']),
        distribution: {
          5: _i(j['r5']),
          4: _i(j['r4']),
          3: _i(j['r3']),
          2: _i(j['r2']),
          1: _i(j['r1']),
        },
      );
}

class GlobalCheckoutSummary {
  final double subtotal;
  final double shipping;
  final String shippingLabel;
  final double tax;
  final double total;
  final int itemsCount;

  const GlobalCheckoutSummary({
    required this.subtotal,
    required this.shipping,
    required this.shippingLabel,
    required this.tax,
    required this.total,
    required this.itemsCount,
  });

  factory GlobalCheckoutSummary.fromJson(Map<String, dynamic> j) =>
      GlobalCheckoutSummary(
        subtotal: _d(j['subtotal']),
        shipping: _d(j['shipping']),
        shippingLabel: j['shipping_label'] ?? 'Shipping',
        tax: _d(j['tax']),
        total: _d(j['total']),
        itemsCount: j['items_count'] ?? 0,
      );
}
