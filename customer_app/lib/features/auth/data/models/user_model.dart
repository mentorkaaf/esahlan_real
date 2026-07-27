import 'dart:convert';

class UserModel {
  final int id;
  final String uuid;
  final String name;
  final String? email;
  final String phone;
  final String? avatar;
  final String? roleSlug;
  final String? roleName;
  final String status;
  final String? referralCode;
  final int loyaltyPoints;
  final String tier;
  final String preferredLanguage;
  final int? districtId;
  final String? districtName;
  final double? districtLat;
  final double? districtLng;

  const UserModel({
    required this.id,
    required this.uuid,
    required this.name,
    this.email,
    required this.phone,
    this.avatar,
    this.roleSlug,
    this.roleName,
    required this.status,
    this.referralCode,
    this.loyaltyPoints = 0,
    this.tier = 'bronze',
    this.preferredLanguage = 'so',
    this.districtId,
    this.districtName,
    this.districtLat,
    this.districtLng,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    final role = json['role'] as Map<String, dynamic>?;
    final district = json['district'] as Map<String, dynamic>?;
    return UserModel(
      id:                json['id'] as int,
      uuid:              json['uuid'] as String? ?? '',
      name:              json['name'] as String? ?? '',
      email:             json['email'] as String?,
      phone:             json['phone'] as String? ?? '',
      avatar:            json['avatar'] as String?,
      roleSlug:          role?['slug'] as String?,
      roleName:          role?['name'] as String?,
      status:            json['status'] as String? ?? 'active',
      referralCode:      json['referral_code'] as String?,
      loyaltyPoints:     json['points_balance'] as int? ?? json['loyalty_points'] as int? ?? 0,
      tier:              json['tier'] as String? ?? 'bronze',
      preferredLanguage: json['preferred_language'] as String? ?? 'so',
      districtId:        json['district_id'] as int?,
      districtName:      district?['name'] as String?,
      districtLat:       (district?['latitude'] as num?)?.toDouble(),
      districtLng:       (district?['longitude'] as num?)?.toDouble(),
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id, 'uuid': uuid, 'name': name, 'email': email,
    'phone': phone, 'avatar': avatar, 'status': status,
    'referral_code': referralCode, 'points_balance': loyaltyPoints, 'tier': tier,
    'preferred_language': preferredLanguage,
    'district_id': districtId,
    'role': roleSlug != null ? {'slug': roleSlug, 'name': roleName} : null,
    if (districtName != null) 'district': {'name': districtName, 'latitude': districtLat, 'longitude': districtLng},
  };

  String toJsonString() => jsonEncode(toJson());
  factory UserModel.fromJsonString(String s) => UserModel.fromJson(jsonDecode(s));

  String get initials {
    final parts = name.trim().split(' ');
    if (parts.length >= 2) return '${parts[0][0]}${parts[1][0]}'.toUpperCase();
    return name.isNotEmpty ? name[0].toUpperCase() : 'U';
  }

  UserModel copyWith({String? name, String? email, String? avatar, String? preferredLanguage}) =>
      UserModel(
        id: id, uuid: uuid, phone: phone, status: status,
        name: name ?? this.name,
        email: email ?? this.email,
        avatar: avatar ?? this.avatar,
        roleSlug: roleSlug, roleName: roleName,
        referralCode: referralCode, loyaltyPoints: loyaltyPoints, tier: tier,
        preferredLanguage: preferredLanguage ?? this.preferredLanguage,
        districtId: districtId, districtName: districtName,
        districtLat: districtLat, districtLng: districtLng,
      );
}
