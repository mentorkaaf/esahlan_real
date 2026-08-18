// ═══════════════════════════════════════════════════════════════════
// eGrocery — Data Models
// ═══════════════════════════════════════════════════════════════════

class EGVariant {
  final int id;
  final String label;
  final double stockQty;
  final bool inStock;
  final bool isDefault;
  final double effectivePrice;
  final double? originalPrice;
  final int discountPct;
  final bool isFlashDeal;
  // Flash deal extras
  final int? qtyLeft;
  final int? qtyLimit;
  final int? qtySold;
  final String? endsAt;

  const EGVariant({
    required this.id,
    required this.label,
    required this.stockQty,
    required this.inStock,
    required this.isDefault,
    required this.effectivePrice,
    this.originalPrice,
    this.discountPct = 0,
    this.isFlashDeal = false,
    this.qtyLeft,
    this.qtyLimit,
    this.qtySold,
    this.endsAt,
  });

  factory EGVariant.fromJson(Map<String, dynamic> j) => EGVariant(
        id: j['id'],
        label: j['label'] ?? '',
        stockQty: _d(j['stock_qty']),
        inStock: j['in_stock'] == true,
        isDefault: j['is_default'] == true,
        effectivePrice: _d(j['effective_price']),
        originalPrice: j['original_price'] != null ? _d(j['original_price']) : null,
        discountPct: j['discount_pct'] ?? 0,
        isFlashDeal: j['is_flash_deal'] == true,
        qtyLeft: j['qty_left'],
        qtyLimit: j['qty_limit'],
        qtySold: j['qty_sold'],
        endsAt: j['ends_at'],
      );
}

class EGProduct {
  final int id;
  final String name;
  final String? nameSo;
  final String slug;
  final String? image;
  final double avgRating;
  final String? brand;
  final List<EGVariant> variants;
  // Detail-only fields
  final String? description;
  final List<String> images;
  final int? reviewsCount;
  final bool isWeightBased;
  final List<EGReview> reviews;
  final List<EGProduct> related;
  final EGCategory? category;

  const EGProduct({
    required this.id,
    required this.name,
    this.nameSo,
    required this.slug,
    this.image,
    this.avgRating = 0,
    this.brand,
    this.variants = const [],
    this.description,
    this.images = const [],
    this.reviewsCount,
    this.isWeightBased = false,
    this.reviews = const [],
    this.related = const [],
    this.category,
  });

  EGVariant? get defaultVariant =>
      variants.firstWhere((v) => v.isDefault, orElse: () => variants.isNotEmpty ? variants.first : _dummy);

  static final _dummy = EGVariant(id: 0, label: '', stockQty: 0, inStock: false, isDefault: true, effectivePrice: 0);

  factory EGProduct.fromJson(Map<String, dynamic> j) => EGProduct(
        id: j['id'],
        name: j['name'] ?? '',
        nameSo: j['name_so'],
        slug: j['slug'] ?? '',
        image: j['image'],
        avgRating: _d(j['avg_rating']),
        brand: j['brand'] is String ? j['brand'] : (j['brand']?['name']),
        variants: _list(j['variants'], EGVariant.fromJson),
        description: j['description'],
        images: (j['images'] as List?)?.cast<String>() ?? [],
        reviewsCount: j['reviews_count'],
        isWeightBased: j['is_weight_based'] == true,
        reviews: _list(j['reviews'], EGReview.fromJson),
        related: _list(j['related'], EGProduct.fromJson),
        category: j['category'] != null ? EGCategory.fromJson(j['category']) : null,
      );
}

class EGCategory {
  final int id;
  final String name;
  final String? nameSo;
  final String slug;
  final String? icon;
  final String? image;
  final int childrenCount;
  final List<EGCategory> children;

  const EGCategory({
    required this.id,
    required this.name,
    this.nameSo,
    required this.slug,
    this.icon,
    this.image,
    this.childrenCount = 0,
    this.children = const [],
  });

  factory EGCategory.fromJson(Map<String, dynamic> j) => EGCategory(
        id: j['id'],
        name: j['name'] ?? '',
        nameSo: j['name_so'],
        slug: j['slug'] ?? '',
        icon: j['icon'],
        image: j['image'],
        childrenCount: j['children_count'] ?? 0,
        children: _list(j['children'], EGCategory.fromJson),
      );
}

class EGSection {
  final int id;
  final String title;
  final String? titleSo;
  final String type;   // best_sellers | new_arrivals | flash_deal | manual
  final String layout; // h_scroll | grid | banner_list
  final String? endsAt;
  final List<EGProduct> products;

  const EGSection({
    required this.id,
    required this.title,
    this.titleSo,
    required this.type,
    required this.layout,
    this.endsAt,
    this.products = const [],
  });

  factory EGSection.fromJson(Map<String, dynamic> j) => EGSection(
        id: j['id'],
        title: j['title'] ?? '',
        titleSo: j['title_so'],
        type: j['type'] ?? 'manual',
        layout: j['layout'] ?? 'h_scroll',
        endsAt: j['ends_at'],
        products: _list(j['products'], EGProduct.fromJson),
      );
}

class EGBanner {
  final int id;
  final String image;
  final String? placement;
  final String? linkType;
  final String? linkValue;

  const EGBanner({required this.id, required this.image, this.placement, this.linkType, this.linkValue});

  factory EGBanner.fromJson(Map<String, dynamic> j) =>
      EGBanner(id: j['id'], image: j['image'] ?? '', placement: j['placement'], linkType: j['link_type'], linkValue: j['link_value']);
}

class EGDeliveryInfo {
  final double deliveryFee;
  final double minOrder;
  final double? freeOver;
  final String? zoneName;

  const EGDeliveryInfo({required this.deliveryFee, required this.minOrder, this.freeOver, this.zoneName});

  factory EGDeliveryInfo.fromJson(Map<String, dynamic> j) => EGDeliveryInfo(
        deliveryFee: _d(j['delivery_fee']),
        minOrder: _d(j['min_order']),
        freeOver: j['free_over'] != null ? _d(j['free_over']) : null,
        zoneName: j['zone_name'],
      );
}

class EGHomePayload {
  final Map<String, List<EGBanner>> banners;
  final List<EGCategory> categories;
  final List<EGSection> sections;
  final EGDeliveryInfo? deliveryInfo;
  final List<EGProduct> buyAgain;

  const EGHomePayload({
    this.banners = const {},
    this.categories = const [],
    this.sections = const [],
    this.deliveryInfo,
    this.buyAgain = const [],
  });

  factory EGHomePayload.fromJson(Map<String, dynamic> j) {
    final rawBanners = (j['banners'] as Map?)?.map(
          (k, v) => MapEntry(k as String, _list(v as List, EGBanner.fromJson)),
        ) ??
        {};

    return EGHomePayload(
      banners: rawBanners,
      categories: _list(j['categories'], EGCategory.fromJson),
      sections: _list(j['sections'], EGSection.fromJson),
      deliveryInfo: j['delivery_info'] != null ? EGDeliveryInfo.fromJson(j['delivery_info']) : null,
      buyAgain: _list(j['buy_again'], EGProduct.fromJson),
    );
  }
}

class EGReview {
  final int id;
  final int rating;
  final String? comment;
  final String? userName;
  final String? userAvatar;
  final String? date;

  const EGReview({required this.id, required this.rating, this.comment, this.userName, this.userAvatar, this.date});

  factory EGReview.fromJson(Map<String, dynamic> j) => EGReview(
        id: j['id'],
        rating: j['rating'] ?? 0,
        comment: j['comment'],
        userName: j['user']?['name'],
        userAvatar: j['user']?['avatar'],
        date: j['date'],
      );
}

class EGDeliverySlot {
  final int slotId;
  final String label;
  final String date;
  final String startTime;
  final String endTime;
  final int capacity;
  final int booked;
  final int available;
  final bool isFull;

  const EGDeliverySlot({
    required this.slotId,
    required this.label,
    required this.date,
    required this.startTime,
    required this.endTime,
    required this.capacity,
    required this.booked,
    required this.available,
    required this.isFull,
  });

  factory EGDeliverySlot.fromJson(Map<String, dynamic> j) => EGDeliverySlot(
        slotId: j['slot_id'],
        label: j['label'] ?? '',
        date: j['date'] ?? '',
        startTime: j['start_time'] ?? '',
        endTime: j['end_time'] ?? '',
        capacity: j['capacity'] ?? 0,
        booked: j['booked'] ?? 0,
        available: j['available'] ?? 0,
        isFull: j['is_full'] == true,
      );
}

class EGOrder {
  final int id;
  final String orderNo;
  final String status;
  final String paymentMethod;
  final String paymentStatus;
  final double subtotal;
  final double discount;
  final double deliveryFee;
  final double total;
  final String? substitutionPref;
  final String? customerNote;
  final String? cancelledReason;
  final String? trackingChannel;
  final String? createdAt;
  final List<EGOrderItem> items;

  const EGOrder({
    required this.id,
    required this.orderNo,
    required this.status,
    required this.paymentMethod,
    required this.paymentStatus,
    required this.subtotal,
    required this.discount,
    required this.deliveryFee,
    required this.total,
    this.substitutionPref,
    this.customerNote,
    this.cancelledReason,
    this.trackingChannel,
    this.createdAt,
    this.items = const [],
  });

  factory EGOrder.fromJson(Map<String, dynamic> j) => EGOrder(
        id: j['id'],
        orderNo: j['order_no'] ?? '',
        status: j['status'] ?? '',
        paymentMethod: j['payment_method'] ?? '',
        paymentStatus: j['payment_status'] ?? '',
        subtotal: _d(j['subtotal']),
        discount: _d(j['discount']),
        deliveryFee: _d(j['delivery_fee']),
        total: _d(j['total']),
        substitutionPref: j['substitution_pref'],
        customerNote: j['customer_note'],
        cancelledReason: j['cancelled_reason'],
        trackingChannel: j['tracking_channel'],
        createdAt: j['created_at'],
        items: _list(j['items'], EGOrderItem.fromJson),
      );
}

class EGOrderItem {
  final int id;
  final int variantId;
  final String productName;
  final String variantLabel;
  final double unitPrice;
  final double qty;
  final double lineTotal;
  final double? pickedQty;
  final String? substitutionStatus;

  const EGOrderItem({
    required this.id,
    required this.variantId,
    required this.productName,
    required this.variantLabel,
    required this.unitPrice,
    required this.qty,
    required this.lineTotal,
    this.pickedQty,
    this.substitutionStatus,
  });

  factory EGOrderItem.fromJson(Map<String, dynamic> j) => EGOrderItem(
        id: j['id'],
        variantId: j['variant_id'],
        productName: j['product_name'] ?? '',
        variantLabel: j['variant_label'] ?? '',
        unitPrice: _d(j['unit_price']),
        qty: _d(j['qty']),
        lineTotal: _d(j['line_total']),
        pickedQty: j['picked_qty'] != null ? _d(j['picked_qty']) : null,
        substitutionStatus: j['substitution_status'],
      );
}

class EGCartLine {
  final int variantId;
  double qty;
  // Filled after validate
  String productName;
  String variantLabel;
  String? image;
  double unitPrice;
  double lineTotal;
  Map<String, dynamic> pricing;

  EGCartLine({
    required this.variantId,
    required this.qty,
    this.productName = '',
    this.variantLabel = '',
    this.image,
    this.unitPrice = 0,
    this.lineTotal = 0,
    this.pricing = const {},
  });

  Map<String, dynamic> toJson() => {'variant_id': variantId, 'qty': qty};
}

class EGCartValidateResult {
  final List<EGCartLine> lines;
  final bool changed;
  final List<Map<String, dynamic>> warnings;
  final double subtotal;
  final double deliveryFee;
  final double discount;
  final double total;
  final double? freeOver;
  final double? freeDeliveryProgress;

  const EGCartValidateResult({
    required this.lines,
    required this.changed,
    required this.warnings,
    required this.subtotal,
    required this.deliveryFee,
    required this.discount,
    required this.total,
    this.freeOver,
    this.freeDeliveryProgress,
  });

  factory EGCartValidateResult.fromJson(Map<String, dynamic> j) {
    final lines = (j['lines'] as List? ?? []).map((l) {
      final line = EGCartLine(
        variantId: l['variant_id'],
        qty: _d(l['qty']),
        productName: l['product_name'] ?? '',
        variantLabel: l['variant_label'] ?? '',
        image: l['image'],
        unitPrice: _d(l['unit_price']),
        lineTotal: _d(l['line_total']),
        pricing: Map<String, dynamic>.from(l['pricing'] ?? {}),
      );
      return line;
    }).toList();

    return EGCartValidateResult(
      lines: lines,
      changed: j['changed'] == true,
      warnings: (j['warnings'] as List?)?.cast<Map<String, dynamic>>() ?? [],
      subtotal: _d(j['subtotal']),
      deliveryFee: _d(j['delivery_fee']),
      discount: _d(j['discount']),
      total: _d(j['total']),
      freeOver: j['free_over'] != null ? _d(j['free_over']) : null,
      freeDeliveryProgress: j['free_delivery_progress'] != null ? _d(j['free_delivery_progress']) : null,
    );
  }
}

// ─── helpers ────────────────────────────────────────────────────────────────

double _d(dynamic v) => v == null ? 0 : (v is num ? v.toDouble() : double.tryParse('$v') ?? 0);

List<T> _list<T>(dynamic raw, T Function(Map<String, dynamic>) fromJson) {
  if (raw == null) return [];
  if (raw is List) return raw.map((e) => fromJson(Map<String, dynamic>.from(e))).toList();
  return [];
}
