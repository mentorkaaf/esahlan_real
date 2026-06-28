import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_compress/video_compress.dart';
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
    state = const UploadState(status: UploadStatus.compressing, progress: 0, message: 'Preparing...');
    _showProgressNotification(0, 'Preparing your post...');

    try {
      final resolvedType = type != 'text' ? type : (hasVideo ? 'video' : (mediaFiles.isNotEmpty ? 'image' : 'text'));

      // Phase 1: Compress (0% → 40%)
      List<MultipartFile>? files;
      if (mediaFiles.isNotEmpty) {
        files = [];
        for (var i = 0; i < mediaFiles.length; i++) {
          final f = mediaFiles[i];
          final mime = f.mimeType ?? '';
          final fileProgress = i / mediaFiles.length;

          if (mime.startsWith('video/')) {
            state = state.copyWith(
              message: 'Compressing video${mediaFiles.length > 1 ? ' ${i + 1}/${mediaFiles.length}' : ''}...',
              progress: fileProgress * 0.4,
            );
            _showProgressNotification((fileProgress * 40).toInt(), 'Compressing video...');

            final subscription = VideoCompress.compressProgress$.subscribe((p) {
              final compressP = (fileProgress + (p / 100) / mediaFiles.length) * 0.4;
              state = state.copyWith(progress: compressP, message: 'Compressing ${p.toInt()}%');
              _showProgressNotification((compressP * 100).toInt(), 'Compressing ${p.toInt()}%');
            });

            final compressed = await VideoCompress.compressVideo(
              f.path,
              quality: VideoQuality.MediumQuality,
              deleteOrigin: false,
              includeAudio: true,
            );
            subscription.unsubscribe();

            if (compressed?.file != null) {
              final bytes = await compressed!.file!.readAsBytes();
              files.add(MultipartFile.fromBytes(bytes, filename: f.name));
              continue;
            }
          }

          final bytes = await f.readAsBytes();
          files.add(MultipartFile.fromBytes(bytes, filename: f.name));
        }
      }

      // Phase 2: Upload (40% → 90%)
      state = state.copyWith(status: UploadStatus.uploading, progress: 0.4, message: 'Uploading...');
      _showProgressNotification(40, 'Uploading...');

      final form = FormData.fromMap({
        'type': resolvedType,
        if (content != null) 'content': content,
        if (privacy != null) 'privacy': privacy,
        if (location != null) 'location': location,
        if (feeling != null) 'feeling': feeling,
        if (pageId != null) 'page_id': pageId,
        if (groupId != null) 'group_id': groupId,
        if (pollOptions != null) ...{for (var i = 0; i < pollOptions.length; i++) 'poll_options[$i]': pollOptions[i]},
      });

      if (files != null) {
        for (final file in files) {
          form.files.add(MapEntry('media[]', file));
        }
      }

      final dio = CommunityRepository.dioInstance;
      final r = await dio.post(
        '/community/posts',
        data: form,
        onSendProgress: (sent, total) {
          if (total > 0) {
            final p = 0.4 + (sent / total) * 0.5;
            final pct = (p * 100).toInt();
            state = state.copyWith(progress: p, message: 'Uploading $pct%');
            _showProgressNotification(pct, 'Uploading $pct%');
          }
        },
      );

      // Phase 3: Processing on server (90% → 100%)
      state = state.copyWith(status: UploadStatus.processing, progress: 0.95, message: 'Processing...');
      _showProgressNotification(95, 'Processing on server...');

      final post = CommunityPost.fromJson(r.data['data'] as Map<String, dynamic>);

      state = UploadState(status: UploadStatus.success, progress: 1.0, message: 'Posted!', post: post);
      _showDoneNotification();
      _playSuccessSound();

      await Future.delayed(const Duration(seconds: 4));
      if (state.status == UploadStatus.success) {
        state = const UploadState();
      }
    } catch (e) {
      state = UploadState(status: UploadStatus.error, progress: 0, message: 'Upload failed');
      _notifications.cancel(42);
      await Future.delayed(const Duration(seconds: 5));
      if (state.status == UploadStatus.error) {
        state = const UploadState();
      }
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
