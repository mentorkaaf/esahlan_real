import 'package:flutter/material.dart';

class AdModel {
  final int id;
  final String title;
  final String? description;
  final String? imageUrl;
  final String? videoUrl;
  final String adType;
  final String? targetModule;
  final String actionType;
  final String? actionValue;
  final String buttonText;
  final Color buttonColor;
  final int displayFrequency;
  final int displayDelaySeconds;
  final bool showOnAppOpen;
  final bool showAfterLogin;
  final bool allowDontShowToday;

  const AdModel({
    required this.id,
    required this.title,
    this.description,
    this.imageUrl,
    this.videoUrl,
    required this.adType,
    this.targetModule,
    required this.actionType,
    this.actionValue,
    required this.buttonText,
    required this.buttonColor,
    required this.displayFrequency,
    required this.displayDelaySeconds,
    required this.showOnAppOpen,
    required this.showAfterLogin,
    required this.allowDontShowToday,
  });

  factory AdModel.fromJson(Map<String, dynamic> j) {
    Color color;
    try {
      final hex = (j['button_color'] as String? ?? '#FF8A00').replaceAll('#', '');
      color = Color(int.parse('FF$hex', radix: 16));
    } catch (_) {
      color = const Color(0xFFFF8A00);
    }

    return AdModel(
      id:                  (j['id'] as num).toInt(),
      title:               j['title']?.toString() ?? '',
      description:         j['description']?.toString(),
      imageUrl:            j['image_url']?.toString(),
      videoUrl:            j['video_url']?.toString(),
      adType:              j['ad_type']?.toString() ?? 'popup_modal',
      targetModule:        j['target_module']?.toString(),
      actionType:          j['action_type']?.toString() ?? 'none',
      actionValue:         j['action_value']?.toString(),
      buttonText:          j['button_text']?.toString() ?? 'Learn More',
      buttonColor:         color,
      displayFrequency:    (j['display_frequency'] as num?)?.toInt() ?? 1,
      displayDelaySeconds: (j['display_delay_seconds'] as num?)?.toInt() ?? 2,
      showOnAppOpen:       j['show_on_app_open'] == true,
      showAfterLogin:      j['show_after_login'] == true,
      allowDontShowToday:  j['allow_dont_show_today'] != false,
    );
  }

  bool get isPopup   => adType == 'popup_modal' || adType == 'popup_fullscreen';
  bool get isBanner  => adType == 'banner_slider' || adType == 'banner_inline';
  bool get isCard    => adType == 'card';
  bool get isFullScreen => adType == 'popup_fullscreen';
}
