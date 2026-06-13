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
  final String preferredLanguage;

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
    this.preferredLanguage = 'so',
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    final role = json['role'] as Map<String, dynamic>?;
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
      loyaltyPoints:     json['loyalty_points'] as int? ?? 0,
      preferredLanguage: json['preferred_language'] as String? ?? 'so',
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id, 'uuid': uuid, 'name': name, 'email': email,
    'phone': phone, 'avatar': avatar, 'status': status,
    'referral_code': referralCode, 'loyalty_points': loyaltyPoints,
    'preferred_language': preferredLanguage,
    'role': roleSlug != null ? {'slug': roleSlug, 'name': roleName} : null,
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
        referralCode: referralCode, loyaltyPoints: loyaltyPoints,
        preferredLanguage: preferredLanguage ?? this.preferredLanguage,
      );
}
