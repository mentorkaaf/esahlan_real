import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../features/community/data/repositories/community_repository.dart';

// ── State ─────────────────────────────────────────────────────────────────────
class AppSettings {
  final ThemeMode themeMode;
  final String language;       // en|so|ar|am|sw|fr
  final String fontSize;       // small|medium|large|xlarge
  final bool reduceMotion;
  final bool dataSaverEnabled;
  final bool disableAutoplay;
  final bool wifiOnlyDownload;
  final bool preloadWifiOnly;
  final String videoAutoplay;  // always|wifi_only|never
  final bool videoLoop;
  final bool pipEnabled;

  const AppSettings({
    this.themeMode      = ThemeMode.light,
    this.language       = 'en',
    this.fontSize       = 'medium',
    this.reduceMotion   = false,
    this.dataSaverEnabled  = false,
    this.disableAutoplay   = false,
    this.wifiOnlyDownload  = false,
    this.preloadWifiOnly   = false,
    this.videoAutoplay  = 'wifi_only',
    this.videoLoop      = false,
    this.pipEnabled     = true,
  });

  Locale get locale => Locale(language);

  double get textScaleFactor => switch (fontSize) {
    'small'  => 0.88,
    'large'  => 1.15,
    'xlarge' => 1.30,
    _        => 1.0,
  };

  AppSettings copyWith({
    ThemeMode? themeMode, String? language, String? fontSize,
    bool? reduceMotion, bool? dataSaverEnabled, bool? disableAutoplay,
    bool? wifiOnlyDownload, bool? preloadWifiOnly,
    String? videoAutoplay, bool? videoLoop, bool? pipEnabled,
  }) => AppSettings(
    themeMode:       themeMode       ?? this.themeMode,
    language:        language        ?? this.language,
    fontSize:        fontSize        ?? this.fontSize,
    reduceMotion:    reduceMotion    ?? this.reduceMotion,
    dataSaverEnabled: dataSaverEnabled ?? this.dataSaverEnabled,
    disableAutoplay: disableAutoplay ?? this.disableAutoplay,
    wifiOnlyDownload: wifiOnlyDownload ?? this.wifiOnlyDownload,
    preloadWifiOnly: preloadWifiOnly ?? this.preloadWifiOnly,
    videoAutoplay:   videoAutoplay   ?? this.videoAutoplay,
    videoLoop:       videoLoop       ?? this.videoLoop,
    pipEnabled:      pipEnabled      ?? this.pipEnabled,
  );
}

// ── Notifier ──────────────────────────────────────────────────────────────────
class AppSettingsNotifier extends StateNotifier<AppSettings> {
  // Static singleton so non-widget code (VideoPool, image widgets) can read it
  static AppSettingsNotifier? _instance;
  static AppSettings get current => _instance?.state ?? const AppSettings();

  AppSettingsNotifier() : super(const AppSettings()) {
    _instance = this;
    _load();
  }

  Future<void> _load() async {
    final p = await SharedPreferences.getInstance();
    state = AppSettings(
      themeMode:       _theme(p.getString('app_theme') ?? 'light'),
      language:        p.getString('app_language') ?? 'en',
      fontSize:        p.getString('app_font_size') ?? 'medium',
      reduceMotion:    p.getBool('app_reduce_motion') ?? false,
      dataSaverEnabled: p.getBool('app_data_saver') ?? false,
      disableAutoplay: p.getBool('app_disable_autoplay') ?? false,
      wifiOnlyDownload: p.getBool('app_wifi_only') ?? false,
      preloadWifiOnly: p.getBool('app_preload_wifi') ?? false,
      videoAutoplay:   p.getString('app_video_autoplay') ?? 'wifi_only',
      videoLoop:       p.getBool('app_video_loop') ?? false,
      pipEnabled:      p.getBool('app_video_pip') ?? true,
    );
  }

  ThemeMode _theme(String v) => switch (v) {
    'dark'  => ThemeMode.dark,
    'light' => ThemeMode.light,
    _       => ThemeMode.system,
  };

  Future<SharedPreferences> get _prefs => SharedPreferences.getInstance();

  void _backend(String section, Map<String, dynamic> data) {
    CommunityRepository().updateSettings(section, data).catchError((_) {});
  }

  // ── Theme ───────────────────────────────────────────────────────────────────
  Future<void> setTheme(String v) async {
    (await _prefs).setString('app_theme', v);
    state = state.copyWith(themeMode: _theme(v));
    _backend('appearance', {'theme': v});
  }

  // ── Language ─────────────────────────────────────────────────────────────────
  Future<void> setLanguage(String code) async {
    (await _prefs).setString('app_language', code);
    state = state.copyWith(language: code);
    _backend('language', {'code': code});
  }

  // ── Font size ────────────────────────────────────────────────────────────────
  Future<void> setFontSize(String v) async {
    (await _prefs).setString('app_font_size', v);
    state = state.copyWith(fontSize: v);
    _backend('appearance', {'font_size': v});
  }

  // ── Reduce Motion ────────────────────────────────────────────────────────────
  Future<void> setReduceMotion(bool v) async {
    (await _prefs).setBool('app_reduce_motion', v);
    state = state.copyWith(reduceMotion: v);
    _backend('appearance', {'reduce_motion': v});
  }

  // ── Data Saver ───────────────────────────────────────────────────────────────
  Future<void> setDataSaver(bool v) async {
    (await _prefs).setBool('app_data_saver', v);
    state = state.copyWith(dataSaverEnabled: v);
    _backend('data_saver', {'enabled': v});
  }

  Future<void> setDisableAutoplay(bool v) async {
    (await _prefs).setBool('app_disable_autoplay', v);
    state = state.copyWith(disableAutoplay: v);
    _backend('data_saver', {'disable_autoplay': v});
  }

  Future<void> setWifiOnly(bool v) async {
    (await _prefs).setBool('app_wifi_only', v);
    state = state.copyWith(wifiOnlyDownload: v);
    _backend('data_saver', {'wifi_only_download': v});
  }

  Future<void> setPreloadWifiOnly(bool v) async {
    (await _prefs).setBool('app_preload_wifi', v);
    state = state.copyWith(preloadWifiOnly: v);
    _backend('data_saver', {'preload_wifi_only': v});
  }

  // ── Video ────────────────────────────────────────────────────────────────────
  Future<void> setVideoAutoplay(String v) async {
    (await _prefs).setString('app_video_autoplay', v);
    state = state.copyWith(videoAutoplay: v);
    _backend('video', {'autoplay': v});
  }

  Future<void> setVideoLoop(bool v) async {
    (await _prefs).setBool('app_video_loop', v);
    state = state.copyWith(videoLoop: v);
    _backend('video', {'loop': v});
  }

  Future<void> setVideoPip(bool v) async {
    (await _prefs).setBool('app_video_pip', v);
    state = state.copyWith(pipEnabled: v);
    _backend('video', {'pip_enabled': v});
  }
}

// ── Provider ──────────────────────────────────────────────────────────────────
final appSettingsProvider =
    StateNotifierProvider<AppSettingsNotifier, AppSettings>((ref) {
  return AppSettingsNotifier();
});
