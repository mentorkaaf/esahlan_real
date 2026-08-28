import 'package:flutter/material.dart';

class OrderModel {
  final int id;
  final String orderNumber;
  final String status;
  final double totalAmount;
  final double? deliveryFee;
  final double? discount;
  final String? moduleSlug;
  final String? vendorName;
  final String? note;
  final String? paymentMethod;
  final String? paymentStatus;
  final List<OrderItemModel> items;
  final String? deliveryAddress;
  final String createdAt;
  final Map<String, dynamic>? parcelDetails;
  final dynamic history;
  final OrderDriverModel? driver;
  // Tracking coordinates
  final double? pickupLat;
  final double? pickupLng;
  final double? deliveryLat;
  final double? deliveryLng;

  const OrderModel({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.totalAmount,
    this.deliveryFee,
    this.discount,
    this.moduleSlug,
    this.vendorName,
    this.note,
    this.paymentMethod,
    this.paymentStatus,
    this.items = const [],
    this.deliveryAddress,
    required this.createdAt,
    this.parcelDetails,
    this.history,
    this.driver,
    this.pickupLat,
    this.pickupLng,
    this.deliveryLat,
    this.deliveryLng,
  });

  factory OrderModel.fromJson(Map<String, dynamic> j) => OrderModel(
    id: j['id'],
    orderNumber: j['order_number'] ?? '#${j['id']}',
    status: j['status'] ?? 'pending',
    totalAmount: (j['total_amount'] as num?)?.toDouble() ?? 0,
    deliveryFee: (j['delivery_fee'] as num?)?.toDouble(),
    discount: (j['discount'] as num?)?.toDouble(),
    moduleSlug: j['module_slug'],
    vendorName: j['vendor']?['name'],
    note: j['note'],
    paymentMethod: j['payment_method'],
    paymentStatus: j['payment_status'],
    items: (j['items'] as List? ?? []).map((e) => OrderItemModel.fromJson(e)).toList(),
    deliveryAddress: j['delivery_address'] is Map
        ? (j['delivery_address']['label']
            ?? j['delivery_address']['address']
            ?? j['delivery_address']['city']
            ?? j['delivery_address'].toString())
        : j['delivery_address']?.toString(),
    createdAt: j['created_at'] ?? '',
    parcelDetails: j['parcel_details'] is Map
        ? Map<String, dynamic>.from(j['parcel_details'])
        : null,
    history: j['history'],
    driver: j['driver'] is Map ? OrderDriverModel.fromJson(Map<String, dynamic>.from(j['driver'])) : null,
    pickupLat:   (j['pickup_lat']   as num?)?.toDouble(),
    pickupLng:   (j['pickup_lng']   as num?)?.toDouble(),
    deliveryLat: (j['delivery_lat'] as num?)?.toDouble(),
    deliveryLng: (j['delivery_lng'] as num?)?.toDouble(),
  );

  String get statusLabel {
    const labels = {
      'pending': 'Pending', 'confirmed': 'Confirmed', 'preparing': 'Preparing',
      'ready': 'Ready', 'picked_up': 'Picked Up', 'delivered': 'Delivered',
      'cancelled': 'Cancelled', 'failed': 'Failed',
    };
    return labels[status] ?? status;
  }

  Color get statusColor {
    switch (status) {
      case 'delivered': return const Color(0xFF22C55E);
      case 'cancelled': case 'failed': return const Color(0xFFEF4444);
      case 'preparing': case 'confirmed': return const Color(0xFF3B82F6);
      default: return const Color(0xFFF59E0B);
    }
  }

  bool get isActive => !['delivered', 'cancelled', 'failed'].contains(status);
}

class OrderDriverModel {
  final int id;
  final String name;
  final String? phone;
  final String? vehicleType;
  final double rating;
  final String? photo;
  final double? lat;
  final double? lng;

  const OrderDriverModel({
    required this.id,
    required this.name,
    this.phone,
    this.vehicleType,
    this.rating = 5.0,
    this.photo,
    this.lat,
    this.lng,
  });

  factory OrderDriverModel.fromJson(Map<String, dynamic> j) => OrderDriverModel(
    id: j['id'] as int,
    name: j['name'] ?? 'Driver',
    phone: j['phone']?.toString(),
    vehicleType: j['vehicle_type']?.toString(),
    rating: (j['rating'] as num?)?.toDouble() ?? 5.0,
    photo: j['photo']?.toString(),
    lat: (j['lat'] as num?)?.toDouble(),
    lng: (j['lng'] as num?)?.toDouble(),
  );
}

class OrderItemModel {
  final int id;
  final String productName;
  final int quantity;
  final double price;
  final String? imageUrl;
  final String? variantName;
  final Map<String, dynamic>? variantAttrs;

  const OrderItemModel({
    required this.id,
    required this.productName,
    required this.quantity,
    required this.price,
    this.imageUrl,
    this.variantName,
    this.variantAttrs,
  });

  factory OrderItemModel.fromJson(Map<String, dynamic> j) => OrderItemModel(
    id: j['id'],
    productName: j['product']?['name'] ?? j['product_name'] ?? j['name'] ?? 'Item',
    quantity: j['quantity'] ?? 1,
    price: (j['price'] as num?)?.toDouble() ?? 0,
    imageUrl: j['product']?['image_url'],
    variantName: j['variant_name'] as String?,
    variantAttrs: j['variant_attrs'] as Map<String, dynamic>?,
  );

  double get total => price * quantity;

  /// Display string for variant: "Red (Color: Red, Size: XL)"
  String? get variantDisplay {
    if (variantName == null) return null;
    if (variantAttrs == null || variantAttrs!.isEmpty) return variantName;
    final attrs = variantAttrs!.entries.map((e) => '${e.key}: ${e.value}').join(', ');
    return '$variantName ($attrs)';
  }
}
