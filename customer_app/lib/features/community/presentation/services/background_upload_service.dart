import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:audioplayers/audioplayers.dart' as ap;
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import '../../data/repositories/community_repository.dart';
import '../../data/models/community_models.dart';

enum UploadStatus { idle, compressing, uploading, processing, success, error }

class UploadState {
  final UploadStatus status;
  final double progress;
  final String? message;
  final CommunityPost? post;

  const UploadState({
    this.status = UploadStatus.idle,
    this.progress = 0,
    this.message,
    this.post,
  });

  UploadState copyWith({UploadStatus? status, double? progress, String? message, CommunityPost? post}) =>
      UploadState(
        status: status ?? this.status,
        progress: progress ?? this.progress,
        message: message ?? this.message,
        post: post ?? this.post,
      );
}

class BackgroundUploadNotifier extends StateNotifier<UploadState> {
  final Ref ref;
  static final _notifications = FlutterLocalNotificationsPlugin();
  static bool _notifInitialized = false;

  BackgroundUploadNotifier(this.ref) : super(const UploadState());

  static Future<void> _initNotifications() async {
    if (_notifInitialized) return;
    _notifInitialized = true;
    await _notifications.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      ),
    );
  }

  Future<void> _showProgressNotification(int progress, String body) async {
    await _initNotifications();
    await _notifications.show(
      42,
      'eSahlan',
      body,
      NotificationDetails(android: AndroidNotificationDetails(
        'upload_channel', 'Uploads',
        channelDescription: 'Video upload progress',
        importance: Importance.low,
        priority: Priority.low,
        onlyAlertOnce: true,
        showProgress: true,
        maxProgress: 100,
        progress: progress,
        ongoing: true,
        autoCancel: false,
      )),
    );
  }

  Future<void> _showDoneNotification() async {
    await _initNotifications();
    await _notifications.cancel(42);
    await _notifications.show(
      43,
      'eSahlan',
      'Your post has been published!',
      const NotificationDetails(android: AndroidNotificationDetails(
        'upload_channel', 'Uploads',
        channelDescription: 'Video upload progress',
        importance: Importance.high,
        priority: Priority.high,
      )),
    );
  }

  Future<void> _playSuccessSound() async {
    try {
      final player = ap.AudioPlayer();
      await player.play(ap.AssetSource('sounds/post_success.mp3'));
      await Future.delayed(const Duration(seconds: 2));
      player.dispose();
    } catch (_) {
      HapticFeedback.mediumImpact();
    }
  }

  Future<void> uploadPost({
    required String type,
    String? content,
    String? privacy,
    String? feeling,
    String? location,
    int? pageId,
    int? groupId,
    List<String>? pollOptions,
    required List<XFile> mediaFiles,
    required bool hasVideo,
  }) async {
    state = const UploadState(status: UploadStatus.uploading, progress: 0, message: 'Uploading...');
    _showProgressNotification(0, 'Uploading...');

    try {
      final resolvedType = type != 'text' ? type : (hasVideo ? 'video' : (mediaFiles.isNotEmpty ? 'image' : 'text'));
      final dio = CommunityRepository.dioInstance;

      final formData = FormData();

      formData.fields.add(MapEntry('type', resolvedType));
      if (content != null) formData.fields.add(MapEntry('content', content));
      if (privacy != null) formData.fields.add(MapEntry('privacy', privacy));
      if (location != null) formData.fields.add(MapEntry('location', location));
      if (feeling != null) formData.fields.add(MapEntry('feeling', feeling));
      if (pageId != null) formData.fields.add(MapEntry('page_id', pageId.toString()));
      if (groupId != null) formData.fields.add(MapEntry('group_id', groupId.toString()));
      if (pollOptions != null) {
        for (var i = 0; i < pollOptions.length; i++) {
          formData.fields.add(MapEntry('poll_options[$i]', pollOptions[i]));
        }
      }

      for (final f in mediaFiles) {
        formData.files.add(MapEntry(
          'media[]',
          await MultipartFile.fromFile(f.path, filename: f.name),
        ));
      }

      final r = await dio.post(
        '/community/posts',
        data: formData,
        onSendProgress: (sent, total) {
          if (total > 0) {
            final pct = ((sent / total) * 90).toInt();
            state = state.copyWith(progress: sent / total * 0.9, message: 'Uploading $pct%');
            _showProgressNotification(pct, 'Uploading $pct%');
          }
        },
        options: Options(
          sendTimeout: const Duration(minutes: 30),
          receiveTimeout: const Duration(minutes: 5),
        ),
      );

      state = state.copyWith(status: UploadStatus.processing, progress: 0.95, message: 'Processing...');
      _showProgressNotification(95, 'Processing on server...');

      final post = CommunityPost.fromJson(r.data['data'] as Map<String, dynamic>);

      state = UploadState(status: UploadStatus.success, progress: 1.0, message: 'Posted!', post: post);
      _showDoneNotification();
      _playSuccessSound();

      await Future.delayed(const Duration(seconds: 4));
      if (state.status == UploadStatus.success) state = const UploadState();
    } catch (e) {
      state = UploadState(status: UploadStatus.error, progress: 0, message: 'Upload failed');
      _notifications.cancel(42);
      await Future.delayed(const Duration(seconds: 5));
      if (state.status == UploadStatus.error) state = const UploadState();
    }
  }

  void dismiss() {
    _notifications.cancel(42);
    state = const UploadState();
  }
}

final backgroundUploadProvider = StateNotifierProvider<BackgroundUploadNotifier, UploadState>(
  (ref) => BackgroundUploadNotifier(ref),
);
