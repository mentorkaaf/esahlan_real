// eWholesale data models — matches Phase 3 API response shapes

class EwHomePayload {
  final List<EwBanner> banners;
  final List<EwCategory> categories;
  final List<EwProduct> topDeals;
  final List<EwSupplierCard> verifiedSuppliers;
  final List<EwProduct> bestSellers;
  final List<EwProduct> newArrivals;
  final int openRfqCount;

  const EwHomePayload({
    required this.banners,
    required this.categories,
    required this.topDeals,
    required this.verifiedSuppliers,
    required this.bestSellers,
    required this.newArrivals,
    required this.openRfqCount,
  });

  factory EwHomePayload.fromJson(Map<String, dynamic> j) => EwHomePayload(
    banners:           _list(j['banners'],            EwBanner.fromJson),
    categories:        _list(j['categories'],          EwCategory.fromJson),
    topDeals:          _list(j['deals'] ?? j['top_deals'],                        EwProduct.fromJson),
    verifiedSuppliers: _list(j['verifiedSuppliers'] ?? j['verified_suppliers'],  EwSupplierCard.fromJson),
    bestSellers:       _list(j['bestSellers'] ?? j['best_sellers'],               EwProduct.fromJson),
    newArrivals:       _list(j['newArrivals'] ?? j['new_arrivals'],               EwProduct.fromJson),
    openRfqCount:      (j['openRfqCount'] ?? j['open_rfq_count'] ?? 0) as int,
  );
}

class EwBanner {
  final int id;
  final String title;
  final String? subtitle;
  final String? image;
  final String? ctaLabel;
  final String? ctaUrl;
  final String? bgColor;

  const EwBanner({required this.id, required this.title, this.subtitle,
    this.image, this.ctaLabel, this.ctaUrl, this.bgColor});

  factory EwBanner.fromJson(Map<String, dynamic> j) => EwBanner(
    id:        j['id'] as int,
    title:     j['title'] ?? '',
    subtitle:  j['subtitle'],
    image:     j['image'],
    ctaLabel:  j['cta_label'],
    ctaUrl:    j['cta_url'],
    bgColor:   j['bg_color'],
  );
}

class EwCategory {
  final int id;
  final String name;
  final String slug;
  final String? icon;
  final String? image;
  final int? parentId;
  final List<EwCategory> children;

  const EwCategory({required this.id, required this.name, required this.slug,
    this.icon, this.image, this.parentId, this.children = const []});

  factory EwCategory.fromJson(Map<String, dynamic> j) => EwCategory(
    id:       j['id'] as int,
    name:     j['name'] ?? '',
    slug:     j['slug'] ?? '',
    icon:     j['icon'],
    image:    j['image'],
    parentId: j['parent_id'] as int?,
    children: _list(j['children'], EwCategory.fromJson),
  );
}

class EwPriceTier {
  final double minQty;
  final double? maxQty;
  final double unitPrice;

  const EwPriceTier({required this.minQty, this.maxQty, required this.unitPrice});

  factory EwPriceTier.fromJson(Map<String, dynamic> j) => EwPriceTier(
    minQty:    _d(j['min_qty']),
    maxQty:    j['max_qty'] != null ? _d(j['max_qty']) : null,
    unitPrice: _d(j['unit_price']),
  );

  bool containsQty(double qty) =>
    qty >= minQty && (maxQty == null || qty <= maxQty!);
}

class EwVariant {
  final int id;
  final String? sku;
  final Map<String, String> attributes; // {"size":"L","color":"Blue"}
  final double stockQty;
  final double reservedQty;
  final bool isActive;
  final bool isDefault;

  double get availableQty => stockQty - reservedQty;

  const EwVariant({required this.id, this.sku, required this.attributes,
    required this.stockQty, required this.reservedQty,
    required this.isActive, required this.isDefault});

  factory EwVariant.fromJson(Map<String, dynamic> j) => EwVariant(
    id:          j['id'] as int,
    sku:         j['sku'],
    attributes:  (j['attributes'] as Map<String, dynamic>? ?? {})
                    .map((k, v) => MapEntry(k, v.toString())),
    stockQty:    _d(j['stock_qty']),
    reservedQty: _d(j['reserved_qty']),
    isActive:    j['is_active'] == true,
    isDefault:   j['is_default'] == true,
  );
}

class EwProduct {
  final int id;
  final String name;
  final String slug;
  final String? description;
  final List<String> images;
  final String? videoUrl;
  final String unit;
  final int? unitsPerPack;
  final double moq;
  final int leadTimeDays;
  final String? brand;
  final String? originCountry;
  final List<Map<String, String>> specs;
  final double minPrice;
  final double? maxPrice;
  final List<EwPriceTier> priceTiers;
  final List<EwVariant> variants;
  final EwSupplierCard? supplier;
  final String? tierSummaryLabel; // "From $34 / bag at 200+"
  final String status;
  final DateTime? dealEndsAt;
  final double? dealDiscount;

  const EwProduct({
    required this.id, required this.name, required this.slug,
    this.description, required this.images, this.videoUrl,
    required this.unit, this.unitsPerPack, required this.moq,
    required this.leadTimeDays, this.brand, this.originCountry,
    required this.specs, required this.minPrice, this.maxPrice,
    required this.priceTiers, required this.variants, this.supplier,
    this.tierSummaryLabel, required this.status, this.dealEndsAt, this.dealDiscount,
  });

  factory EwProduct.fromJson(Map<String, dynamic> j) => EwProduct(
    id:               j['id'] as int,
    name:             j['name'] ?? '',
    slug:             j['slug'] ?? '',
    description:      j['description'],
    images:           (j['images'] as List? ?? []).map((e) => e.toString()).toList(),
    videoUrl:         j['video_url'],
    unit:             j['unit'] ?? 'piece',
    unitsPerPack:     j['units_per_pack'] as int?,
    moq:              _d(j['moq']),
    leadTimeDays:     (j['lead_time_days'] ?? 0) as int,
    brand:            j['brand'],
    originCountry:    j['origin_country'],
    specs:            (j['specs'] as List? ?? [])
                        .map((s) => {'key': s['key']?.toString() ?? '', 'value': s['value']?.toString() ?? ''})
                        .toList(),
    minPrice:         _d(j['min_price']),
    maxPrice:         j['max_price'] != null ? _d(j['max_price']) : null,
    priceTiers:       _list(j['price_tiers'], EwPriceTier.fromJson),
    variants:         _list(j['variants'], EwVariant.fromJson),
    supplier:         j['supplier'] != null ? EwSupplierCard.fromJson(j['supplier']) : null,
    tierSummaryLabel: j['tier_summary_label'],
    status:           j['status'] ?? 'active',
    dealEndsAt:       j['deal_ends_at'] != null ? DateTime.tryParse(j['deal_ends_at']) : null,
    dealDiscount:     j['deal_discount'] != null ? _d(j['deal_discount']) : null,
  );

  double priceForQty(double qty) {
    if (priceTiers.isEmpty) return minPrice;
    EwPriceTier? match;
    for (final t in priceTiers) {
      if (qty >= t.minQty) match = t;
    }
    return match?.unitPrice ?? minPrice;
  }
}

class EwSupplierCard {
  final int id;
  final String displayName;
  final String? logo;
  final String? banner;
  final String verification; // unverified/pending/verified/gold
  final double rating;
  final int totalOrders;
  final double responseRate;
  final int responseTimeAvg; // minutes
  final double onTimeDeliveryRate;
  final String? about;

  const EwSupplierCard({
    required this.id, required this.displayName, this.logo, this.banner,
    required this.verification, required this.rating, required this.totalOrders,
    required this.responseRate, required this.responseTimeAvg,
    required this.onTimeDeliveryRate, this.about,
  });

  factory EwSupplierCard.fromJson(Map<String, dynamic> j) => EwSupplierCard(
    id:                 j['id'] as int,
    displayName:        j['display_name'] ?? '',
    logo:               j['logo'],
    banner:             j['banner'],
    verification:       j['verification'] ?? 'unverified',
    rating:             _d(j['rating']),
    totalOrders:        (j['total_orders'] ?? 0) as int,
    responseRate:       _d(j['response_rate']),
    responseTimeAvg:    (j['response_time_avg'] ?? 0) as int,
    onTimeDeliveryRate: _d(j['on_time_delivery_rate'] ?? j['on_time'] ?? 0),
    about:              j['about'],
  );

  bool get isVerified => verification == 'verified' || verification == 'gold';
  bool get isGold     => verification == 'gold';
}

// ── Inquiry / Quote ──────────────────────────────────────────────────────────

class EwInquiry {
  final int id;
  final int productId;
  final String productName;
  final double qty;
  final String message;
  final String status;
  final List<EwQuote> quotes;
  final DateTime createdAt;

  const EwInquiry({required this.id, required this.productId,
    required this.productName, required this.qty, required this.message,
    required this.status, required this.quotes, required this.createdAt});

  factory EwInquiry.fromJson(Map<String, dynamic> j) => EwInquiry(
    id:          j['id'] as int,
    productId:   j['product_id'] as int,
    productName: j['product_name'] ?? '',
    qty:         _d(j['qty']),
    message:     j['message'] ?? '',
    status:      j['status'] ?? 'open',
    quotes:      _list(j['quotes'], EwQuote.fromJson),
    createdAt:   DateTime.tryParse(j['created_at'] ?? '') ?? DateTime.now(),
  );
}

class EwQuoteLine {
  final int productId;
  final String productName;
  final double qty;
  final String unit;
  final double unitPrice;
  final double? proposedPrice;

  double get subtotal => unitPrice * qty;

  const EwQuoteLine({required this.productId, required this.productName,
    required this.qty, required this.unit, required this.unitPrice, this.proposedPrice});

  factory EwQuoteLine.fromJson(Map<String, dynamic> j) => EwQuoteLine(
    productId:     j['product_id'] as int,
    productName:   j['product_name'] ?? '',
    qty:           _d(j['qty']),
    unit:          j['unit'] ?? 'piece',
    unitPrice:     _d(j['unit_price']),
    proposedPrice: j['proposed_price'] != null ? _d(j['proposed_price']) : null,
  );
}

class EwQuote {
  final int id;
  final int inquiryId;
  final EwSupplierCard? supplier;
  final String status; // pending/sent/countered/accepted/declined/expired
  final List<EwQuoteLine> lines;
  final String? note;
  final DateTime? validUntil;
  final List<Map<String, dynamic>> negotiationChain;
  final DateTime createdAt;

  double get total => lines.fold(0, (s, l) => s + l.subtotal);

  const EwQuote({required this.id, required this.inquiryId, this.supplier,
    required this.status, required this.lines, this.note,
    this.validUntil, required this.negotiationChain, required this.createdAt});

  factory EwQuote.fromJson(Map<String, dynamic> j) => EwQuote(
    id:               j['id'] as int,
    inquiryId:        j['inquiry_id'] as int,
    supplier:         j['supplier'] != null ? EwSupplierCard.fromJson(j['supplier']) : null,
    status:           j['status'] ?? 'pending',
    lines:            _list(j['lines'], EwQuoteLine.fromJson),
    note:             j['note'],
    validUntil:       j['valid_until'] != null ? DateTime.tryParse(j['valid_until']) : null,
    negotiationChain: (j['negotiation_chain'] as List? ?? []).cast<Map<String, dynamic>>(),
    createdAt:        DateTime.tryParse(j['created_at'] ?? '') ?? DateTime.now(),
  );
}

// ── RFQ ─────────────────────────────────────────────────────────────────────

class EwRfq {
  final int id;
  final int categoryId;
  final String? categoryName;
  final String title;
  final double qty;
  final String unit;
  final double? targetPrice;
  final DateTime? neededBy;
  final String status;
  final List<EwRfqQuote> quotes;
  final DateTime createdAt;
  final DateTime expiresAt;

  const EwRfq({required this.id, required this.categoryId, this.categoryName,
    required this.title, required this.qty, required this.unit,
    this.targetPrice, this.neededBy, required this.status, required this.quotes,
    required this.createdAt, required this.expiresAt});

  factory EwRfq.fromJson(Map<String, dynamic> j) => EwRfq(
    id:           j['id'] as int,
    categoryId:   j['category_id'] as int,
    categoryName: j['category_name'],
    title:        j['title'] ?? '',
    qty:          _d(j['qty']),
    unit:         j['unit'] ?? 'piece',
    targetPrice:  j['target_price'] != null ? _d(j['target_price']) : null,
    neededBy:     j['needed_by'] != null ? DateTime.tryParse(j['needed_by']) : null,
    status:       j['status'] ?? 'open',
    quotes:       _list(j['quotes'], EwRfqQuote.fromJson),
    createdAt:    DateTime.tryParse(j['created_at'] ?? '') ?? DateTime.now(),
    expiresAt:    DateTime.tryParse(j['expires_at'] ?? '') ?? DateTime.now().add(const Duration(days: 14)),
  );
}

class EwRfqQuote {
  final int id;
  final int rfqId;
  final EwSupplierCard? supplier;
  final double unitPrice;
  final double? totalPrice;
  final int? leadTimeDays;
  final String status; // sent/shortlisted/accepted/rejected
  final String? note;
  final DateTime createdAt;

  const EwRfqQuote({required this.id, required this.rfqId, this.supplier,
    required this.unitPrice, this.totalPrice, this.leadTimeDays,
    required this.status, this.note, required this.createdAt});

  factory EwRfqQuote.fromJson(Map<String, dynamic> j) => EwRfqQuote(
    id:          j['id'] as int,
    rfqId:       j['rfq_id'] as int,
    supplier:    j['supplier'] != null ? EwSupplierCard.fromJson(j['supplier']) : null,
    unitPrice:   _d(j['unit_price']),
    totalPrice:  j['total_price'] != null ? _d(j['total_price']) : null,
    leadTimeDays: j['lead_time_days'] as int?,
    status:      j['status'] ?? 'sent',
    note:        j['note'],
    createdAt:   DateTime.tryParse(j['created_at'] ?? '') ?? DateTime.now(),
  );
}

// ── Cart ─────────────────────────────────────────────────────────────────────

class EwCartLine {
  final int productId;
  final String productName;
  final String? productImage;
  final int? variantId;
  final Map<String, String> variantAttributes;
  final double qty;
  final String unit;
  final double unitPrice;
  final double moq;
  final int supplierId;

  double get subtotal => unitPrice * qty;

  const EwCartLine({required this.productId, required this.productName,
    this.productImage, this.variantId, required this.variantAttributes,
    required this.qty, required this.unit, required this.unitPrice,
    required this.moq, required this.supplierId});

  EwCartLine copyWith({double? qty, double? unitPrice}) => EwCartLine(
    productId: productId, productName: productName, productImage: productImage,
    variantId: variantId, variantAttributes: variantAttributes,
    qty: qty ?? this.qty, unit: unit, unitPrice: unitPrice ?? this.unitPrice,
    moq: moq, supplierId: supplierId,
  );

  Map<String, dynamic> toJson() => {
    'product_id': productId,
    if (variantId != null) 'variant_id': variantId,
    'qty': qty,
  };
}

class EwCartGroup {
  final int supplierId;
  final String supplierName;
  final String supplierVerification;
  final List<EwCartLine> lines;
  final double subtotal;
  final double deliveryFee;
  final double platformFee;
  final double total;
  final List<String> availablePaymentPlans; // prepaid/deposit/credit

  const EwCartGroup({
    required this.supplierId, required this.supplierName,
    required this.supplierVerification, required this.lines,
    required this.subtotal, required this.deliveryFee,
    required this.platformFee, required this.total,
    required this.availablePaymentPlans,
  });

  factory EwCartGroup.fromJson(Map<String, dynamic> j) {
    final lines = (j['lines'] as List? ?? []).map((l) {
      return EwCartLine(
        productId:         l['product_id'] as int,
        productName:       l['product_name'] ?? '',
        productImage:      l['product_image'],
        variantId:         l['variant_id'] as int?,
        variantAttributes: (l['variant_attributes'] as Map<String, dynamic>? ?? {})
                              .map((k, v) => MapEntry(k, v.toString())),
        qty:        _d(l['qty']),
        unit:       l['unit'] ?? 'piece',
        unitPrice:  _d(l['unit_price']),
        moq:        _d(l['moq']),
        supplierId: j['supplier_id'] as int,
      );
    }).toList();

    return EwCartGroup(
      supplierId:             j['supplier_id'] as int,
      supplierName:           j['supplier_name'] ?? '',
      supplierVerification:   j['supplier_verification'] ?? 'unverified',
      lines:                  lines,
      subtotal:               _d(j['subtotal']),
      deliveryFee:            _d(j['delivery_fee']),
      platformFee:            _d(j['platform_fee']),
      total:                  _d(j['total']),
      availablePaymentPlans:  (j['available_payment_plans'] as List? ?? [])
                                .map((e) => e.toString()).toList(),
    );
  }
}

class EwCartValidateResult {
  final List<EwCartGroup> groups;
  final List<Map<String, dynamic>> errors;
  final List<Map<String, dynamic>> warnings;

  const EwCartValidateResult({required this.groups, required this.errors, required this.warnings});

  factory EwCartValidateResult.fromJson(Map<String, dynamic> j) => EwCartValidateResult(
    groups:   _list(j['groups'], EwCartGroup.fromJson),
    errors:   (j['errors']   as List? ?? []).cast<Map<String, dynamic>>(),
    warnings: (j['warnings'] as List? ?? []).cast<Map<String, dynamic>>(),
  );

  bool get hasErrors => errors.isNotEmpty;
  double get grandTotal => groups.fold(0, (s, g) => s + g.total);
}

// ── Order ────────────────────────────────────────────────────────────────────

class EwOrderPayment {
  final int id;
  final String type; // deposit/balance/full
  final String method;
  final double amount;
  final String status;
  final DateTime paidAt;

  const EwOrderPayment({required this.id, required this.type, required this.method,
    required this.amount, required this.status, required this.paidAt});

  factory EwOrderPayment.fromJson(Map<String, dynamic> j) => EwOrderPayment(
    id:     j['id'] as int,
    type:   j['type'] ?? 'full',
    method: j['method'] ?? '',
    amount: _d(j['amount']),
    status: j['status'] ?? 'confirmed',
    paidAt: DateTime.tryParse(j['paid_at'] ?? j['created_at'] ?? '') ?? DateTime.now(),
  );
}

class EwOrderItem {
  final int id;
  final int productId;
  final String nameSnapshot;
  final String unit;
  final double qty;
  final double shippedQty;
  final double unitPrice;

  double get subtotal => unitPrice * qty;

  const EwOrderItem({required this.id, required this.productId,
    required this.nameSnapshot, required this.unit, required this.qty,
    required this.shippedQty, required this.unitPrice});

  factory EwOrderItem.fromJson(Map<String, dynamic> j) => EwOrderItem(
    id:           j['id'] as int,
    productId:    j['product_id'] as int,
    nameSnapshot: j['name_snapshot'] ?? '',
    unit:         j['unit'] ?? 'piece',
    qty:          _d(j['qty']),
    shippedQty:   _d(j['shipped_qty']),
    unitPrice:    _d(j['unit_price']),
  );
}

class EwShipment {
  final int id;
  final String trackingNo;
  final String carrier;
  final String status;
  final DateTime? estimatedDelivery;
  final List<Map<String, dynamic>> items; // [{product_id, name, qty}]

  const EwShipment({required this.id, required this.trackingNo,
    required this.carrier, required this.status, this.estimatedDelivery,
    required this.items});

  factory EwShipment.fromJson(Map<String, dynamic> j) => EwShipment(
    id:               j['id'] as int,
    trackingNo:       j['tracking_no'] ?? '',
    carrier:          j['carrier'] ?? '',
    status:           j['status'] ?? 'pending',
    estimatedDelivery: j['estimated_delivery'] != null ? DateTime.tryParse(j['estimated_delivery']) : null,
    items:            (j['items'] as List? ?? []).cast<Map<String, dynamic>>(),
  );
}

class EwOrder {
  final int id;
  final String orderNo;
  final EwSupplierCard? supplier;
  final String status;
  final String paymentPlan; // prepaid/deposit/credit
  final int depositPercent;
  final double total;
  final double balanceDue;
  final List<EwOrderItem> items;
  final List<EwOrderPayment> payments;
  final List<EwShipment> shipments;
  final List<Map<String, dynamic>> statusTimeline;
  final DateTime createdAt;
  final bool hasReview;

  const EwOrder({
    required this.id, required this.orderNo, this.supplier,
    required this.status, required this.paymentPlan, required this.depositPercent,
    required this.total, required this.balanceDue, required this.items,
    required this.payments, required this.shipments, required this.statusTimeline,
    required this.createdAt, required this.hasReview,
  });

  factory EwOrder.fromJson(Map<String, dynamic> j) => EwOrder(
    id:             j['id'] as int,
    orderNo:        j['order_no'] ?? '',
    supplier:       j['supplier'] != null ? EwSupplierCard.fromJson(j['supplier']) : null,
    status:         j['status'] ?? 'pending_confirmation',
    paymentPlan:    j['payment_plan'] ?? 'prepaid',
    depositPercent: (j['deposit_percent'] ?? 0) as int,
    total:          _d(j['total']),
    balanceDue:     _d(j['balance_due']),
    items:          _list(j['items'],     EwOrderItem.fromJson),
    payments:       _list(j['payments'],  EwOrderPayment.fromJson),
    shipments:      _list(j['shipments'], EwShipment.fromJson),
    statusTimeline: (j['status_timeline'] as List? ?? []).cast<Map<String, dynamic>>(),
    createdAt:      DateTime.tryParse(j['created_at'] ?? '') ?? DateTime.now(),
    hasReview:      j['has_review'] == true,
  );

  double get amountPaid => payments.fold(0, (s, p) => s + p.amount);
  bool get canCancel => ['pending_confirmation','awaiting_payment'].contains(status);
  bool get canPayBalance => status == 'awaiting_payment' && paymentPlan == 'deposit';
  bool get canDispute => ['processing','ready','partially_shipped','shipped','delivered'].contains(status);
  bool get canReview => status == 'completed' && !hasReview;
  bool get isActive => !['completed','cancelled'].contains(status);
}

// ── Buyer Profile ─────────────────────────────────────────────────────────────

class EwBuyerProfile {
  final int id;
  final String businessName;
  final String businessType;
  final String kybStatus; // none/pending/approved/rejected
  final String? kybRejectionReason;
  final DateTime? submittedAt;
  final String priceTier;
  final double? creditLimit;
  final double? creditUsed;
  final double? creditAvailable;
  final String? creditDueDate;

  const EwBuyerProfile({required this.id, required this.businessName,
    required this.businessType, required this.kybStatus, this.kybRejectionReason,
    this.submittedAt, required this.priceTier,
    this.creditLimit, this.creditUsed, this.creditAvailable, this.creditDueDate});

  factory EwBuyerProfile.fromJson(Map<String, dynamic> j) => EwBuyerProfile(
    id:                  j['id'] as int,
    businessName:        j['business_name'] ?? '',
    businessType:        j['business_type'] ?? 'shop',
    kybStatus:           j['kyb_status'] ?? 'none',
    kybRejectionReason:  j['kyb_rejection_reason'],
    submittedAt:         j['kyb_submitted_at'] != null ? DateTime.tryParse(j['kyb_submitted_at']) : null,
    priceTier:           j['price_list_tier'] ?? 'standard',
    creditLimit:         j['credit']?['limit'] != null ? _d(j['credit']['limit']) : null,
    creditUsed:          j['credit']?['used'] != null ? _d(j['credit']['used']) : null,
    creditAvailable:     j['credit']?['available'] != null ? _d(j['credit']['available']) : null,
    creditDueDate:       j['credit']?['next_due_date'],
  );

  bool get isApproved => kybStatus == 'approved';
  bool get isPending  => kybStatus == 'pending';
}

// ── Saved List ────────────────────────────────────────────────────────────────

class EwSavedList {
  final int id;
  final String name;
  final int itemsCount;
  int get itemCount => itemsCount;

  const EwSavedList({required this.id, required this.name, required this.itemsCount});

  factory EwSavedList.fromJson(Map<String, dynamic> j) => EwSavedList(
    id:         j['id'] as int,
    name:       j['name'] ?? '',
    itemsCount: (j['items_count'] ?? 0) as int,
  );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

double _d(dynamic v) {
  if (v == null) return 0.0;
  if (v is double) return v;
  if (v is int) return v.toDouble();
  return double.tryParse(v.toString()) ?? 0.0;
}

List<T> _list<T>(dynamic raw, T Function(Map<String, dynamic>) fromJson) {
  if (raw == null) return [];
  return (raw as List).map((e) => fromJson(e as Map<String, dynamic>)).toList();
}
