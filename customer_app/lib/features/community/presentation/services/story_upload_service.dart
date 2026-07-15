import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';

enum StoryUploadStatus { idle, uploading, success, error }

class StoryUploadState {
  final StoryUploadStatus status;
  final double progress;
  final String? localFilePath; // local path for thumbnail preview
  final String? storyType;    // 'image', 'video', 'text'
  final String? errorMsg;

  const StoryUploadState({
    this.status = StoryUploadStatus.idle,
    this.progress = 0,
    this.localFilePath,
    this.storyType,
    this.errorMsg,
  });

  bool get isActive => status == StoryUploadStatus.uploading;

  StoryUploadState copyWith({
    StoryUploadStatus? status,
    double? progress,
    String? localFilePath,
    String? storyType,
    String? errorMsg,
  }) =>
      StoryUploadState(
        status: status ?? this.status,
        progress: progress ?? this.progress,
        localFilePath: localFilePath ?? this.localFilePath,
        storyType: storyType ?? this.storyType,
        errorMsg: errorMsg ?? this.errorMsg,
      );
}

class StoryUploadNotifier extends StateNotifier<StoryUploadState> {
  final Ref _ref;
  StoryUploadNotifier(this._ref) : super(const StoryUploadState());

  Future<void> upload({
    required String type,
    String? localFilePath,
    String? textContent,
    String? bgColor,
  }) async {
    state = StoryUploadState(
      status: StoryUploadStatus.uploading,
      progress: 0,
      localFilePath: localFilePath,
      storyType: type,
    );

    try {
      final dio = CommunityRepository.dioInstance;
      final formData = FormData();
      formData.fields.add(MapEntry('type', type));
      if (textContent != null) formData.fields.add(MapEntry('text_content', textContent));
      if (bgColor != null) formData.fields.add(MapEntry('bg_color', bgColor));

      if (localFilePath != null) {
        final fileName = localFilePath.split(Platform.pathSeparator).last;
        formData.files.add(MapEntry(
          'media',
          await MultipartFile.fromFile(localFilePath, filename: fileName),
        ));
      }

      await dio.post(
        '/community/stories',
        data: formData,
        onSendProgress: (sent, total) {
          if (total > 0) {
            state = state.copyWith(progress: sent / total);
          }
        },
        options: Options(
          sendTimeout: const Duration(minutes: 10),
          receiveTimeout: const Duration(minutes: 2),
        ),
      );

      state = state.copyWith(status: StoryUploadStatus.success, progress: 1.0);

      // Refresh stories list so the new story appears
      _ref.invalidate(communityStoriesProvider);

      // Auto-clear after a short delay
      await Future.delayed(const Duration(seconds: 3));
      if (state.status == StoryUploadStatus.success) {
        state = const StoryUploadState();
      }
    } catch (e) {
      state = state.copyWith(
        status: StoryUploadStatus.error,
        errorMsg: 'Upload failed. Tap to retry.',
      );
      await Future.delayed(const Duration(seconds: 6));
      if (state.status == StoryUploadStatus.error) {
        state = const StoryUploadState();
      }
    }
  }

  void dismiss() => state = const StoryUploadState();
}

final storyUploadProvider =
    StateNotifierProvider<StoryUploadNotifier, StoryUploadState>(
  (ref) => StoryUploadNotifier(ref),
);
