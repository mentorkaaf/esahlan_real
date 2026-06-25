import 'dart:io';
import 'package:video_compress/video_compress.dart';
import 'package:image_picker/image_picker.dart';

class VideoOptimizer {
  static Future<File?> compress(XFile file) async {
    try {
      final info = await VideoCompress.compressVideo(
        file.path,
        quality: VideoQuality.MediumQuality,
        deleteOrigin: false,
        includeAudio: true,
      );
      return info?.file;
    } catch (_) {
      return File(file.path);
    }
  }

  static Future<File?> getThumbnail(String videoPath) async {
    try {
      final thumb = await VideoCompress.getFileThumbnail(
        videoPath,
        quality: 70,
        position: -1,
      );
      return thumb;
    } catch (_) {
      return null;
    }
  }

  static void cancelCompression() {
    VideoCompress.cancelCompression();
  }
}
