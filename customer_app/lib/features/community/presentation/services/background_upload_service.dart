import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_compress/video_compress.dart';
import 'package:image_picker/image_picker.dart';
import '../../data/repositories/community_repository.dart';
import '../../data/models/community_models.dart';

enum UploadStatus { idle, compressing, uploading, success, error }

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
  BackgroundUploadNotifier(this.ref) : super(const UploadState());

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

    try {
      final resolvedType = type != 'text' ? type : (hasVideo ? 'video' : (mediaFiles.isNotEmpty ? 'image' : 'text'));

      List<MultipartFile>? files;
      if (mediaFiles.isNotEmpty) {
        files = [];
        for (var i = 0; i < mediaFiles.length; i++) {
          final f = mediaFiles[i];
          final mime = f.mimeType ?? '';

          if (mime.startsWith('video/')) {
            state = state.copyWith(message: 'Compressing video...', progress: 0.1);
            final compressed = await VideoCompress.compressVideo(
              f.path,
              quality: VideoQuality.MediumQuality,
              deleteOrigin: false,
              includeAudio: true,
            );
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

      state = state.copyWith(status: UploadStatus.uploading, progress: 0.2, message: 'Uploading...');

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
            final p = 0.2 + (sent / total) * 0.8;
            state = state.copyWith(progress: p, message: 'Uploading ${(p * 100).toInt()}%');
          }
        },
      );

      final post = CommunityPost.fromJson(r.data['data'] as Map<String, dynamic>);
      state = UploadState(status: UploadStatus.success, progress: 1.0, message: 'Posted!', post: post);

      // Auto-dismiss after 4 seconds
      await Future.delayed(const Duration(seconds: 4));
      if (state.status == UploadStatus.success) {
        state = const UploadState();
      }
    } catch (e) {
      state = UploadState(status: UploadStatus.error, progress: 0, message: 'Upload failed: $e');
      // Auto-dismiss error after 5 seconds
      await Future.delayed(const Duration(seconds: 5));
      if (state.status == UploadStatus.error) {
        state = const UploadState();
      }
    }
  }

  void dismiss() => state = const UploadState();
}

final backgroundUploadProvider = StateNotifierProvider<BackgroundUploadNotifier, UploadState>(
  (ref) => BackgroundUploadNotifier(ref),
);
