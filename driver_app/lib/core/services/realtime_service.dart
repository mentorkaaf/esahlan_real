// ignore_for_file: avoid_print
import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

import '../constants/app_constants.dart';
import '../storage/local_storage.dart';

enum RealtimeState { disconnected, connecting, connected, reconnecting }

/// Lightweight Pusher-protocol Reverb client for the driver app.
/// Mirrors the customer app's RealtimeClient but kept as a separate singleton
/// so driver and customer connections are independent.
class RealtimeService {
  RealtimeService._();
  static final RealtimeService instance = RealtimeService._();

  WebSocketChannel? _socket;
  StreamSubscription<dynamic>? _sub;
  String? _socketId;
  RealtimeState _state = RealtimeState.disconnected;
  bool _intentionallyDisconnected = false;
  int _reconnectAttempts = 0;
  Timer? _reconnectTimer;
  Timer? _pingTimer;

  // channel → event → [callbacks]
  final Map<String, Map<String, List<void Function(dynamic)>>> _listeners = {};
  final Set<String> _subscribed = {};

  final _stateCtrl = StreamController<RealtimeState>.broadcast();
  Stream<RealtimeState> get stateStream => _stateCtrl.stream;
  RealtimeState get state => _state;

  String get _wsUrl {
    final scheme = AppConstants.reverbUseTLS ? 'wss' : 'ws';
    return '$scheme://${AppConstants.reverbHost}:${AppConstants.reverbPort}/app/${AppConstants.reverbAppKey}'
        '?protocol=7&client=dart&version=1.0';
  }

  Future<void> connect() async {
    if (_state == RealtimeState.connected || _state == RealtimeState.connecting) return;
    _intentionallyDisconnected = false;
    _setState(RealtimeState.connecting);

    try {
      _socket = WebSocketChannel.connect(Uri.parse(_wsUrl));
      await _socket!.ready.timeout(const Duration(seconds: 15));
      _sub = _socket!.stream.listen(_onMessage, onError: _onError, onDone: _onDone);
    } catch (e) {
      if (kDebugMode) debugPrint('[DriverRealtime] connect error: $e');
      _scheduleReconnect();
    }
  }

  void _onMessage(dynamic raw) {
    try {
      final msg = jsonDecode(raw as String) as Map<String, dynamic>;
      final event = msg['event'] as String?;
      final data = msg['data'];
      final channel = msg['channel'] as String?;

      if (event == 'pusher:connection_established') {
        final d = jsonDecode(data as String) as Map<String, dynamic>;
        _socketId = d['socket_id'] as String;
        _reconnectAttempts = 0;
        _setState(RealtimeState.connected);
        // Re-subscribe to any pending channels
        for (final ch in List.of(_subscribed)) {
          _sendSubscribe(ch);
        }
        // Start ping every 30s
        _pingTimer?.cancel();
        _pingTimer = Timer.periodic(const Duration(seconds: 30), (_) {
          _send({'event': 'pusher:ping', 'data': {}});
        });
        return;
      }
      if (event == 'pusher:pong') return;
      if (event == 'pusher:error') {
        if (kDebugMode) debugPrint('[DriverRealtime] Server error: $data');
        return;
      }

      if (channel != null && event != null) {
        final channelMap = _listeners[channel];
        if (channelMap != null) {
          // Reverb sends event names prefixed with channel name for private channels
          final handlers = channelMap[event] ?? channelMap[event.replaceFirst('pusher_internal:', '')];
          if (handlers != null) {
            dynamic payload = data;
            if (data is String) {
              try { payload = jsonDecode(data); } catch (_) {}
            }
            for (final h in List.of(handlers)) h(payload);
          }
        }
      }
    } catch (e) {
      if (kDebugMode) debugPrint('[DriverRealtime] _onMessage error: $e');
    }
  }

  void _onError(dynamic err) {
    if (kDebugMode) debugPrint('[DriverRealtime] WebSocket error: $err');
    _scheduleReconnect();
  }

  void _onDone() {
    if (_intentionallyDisconnected) return;
    if (kDebugMode) debugPrint('[DriverRealtime] WebSocket closed');
    _scheduleReconnect();
  }

  void _send(Map<String, dynamic> payload) {
    try { _socket?.sink.add(jsonEncode(payload)); } catch (_) {}
  }

  Future<void> _sendSubscribe(String channel) async {
    if (channel.startsWith('private-') || channel.startsWith('presence-')) {
      final auth = await _authorize(channel);
      if (auth == null) return;
      _send({'event': 'pusher:subscribe', 'data': {'channel': channel, 'auth': auth['auth']}});
    } else {
      _send({'event': 'pusher:subscribe', 'data': {'channel': channel}});
    }
  }

  Future<Map<String, dynamic>?> _authorize(String channel) async {
    try {
      final token = await LocalStorage.getToken();
      final dio = Dio(BaseOptions(
        baseUrl: AppConstants.baseUrl,
        headers: {'Accept': 'application/json'},
      ));
      final response = await dio.post(
        '/broadcasting/auth',
        data: {'socket_id': _socketId, 'channel_name': channel},
        options: Options(headers: token != null ? {'Authorization': 'Bearer $token'} : null),
      );
      return Map<String, dynamic>.from(response.data as Map);
    } catch (e) {
      if (kDebugMode) debugPrint('[DriverRealtime] auth failed for $channel: $e');
      return null;
    }
  }

  void _scheduleReconnect() {
    if (_intentionallyDisconnected) return;
    _pingTimer?.cancel();
    _setState(RealtimeState.reconnecting);
    _reconnectTimer?.cancel();
    _reconnectAttempts++;
    final base = min(30, pow(2, min(_reconnectAttempts, 6)).toInt());
    final jitter = Random().nextInt(1000);
    _reconnectTimer = Timer(Duration(seconds: base, milliseconds: jitter), connect);
  }

  void _setState(RealtimeState s) {
    _state = s;
    _stateCtrl.add(s);
  }

  /// Subscribe to [channel] and register a callback for [event].
  Future<void> listen(String channel, String event, void Function(dynamic) callback) async {
    if (_state != RealtimeState.connected) await connect();
    _listeners.putIfAbsent(channel, () => {}).putIfAbsent(event, () => []).add(callback);
    if (!_subscribed.contains(channel)) {
      _subscribed.add(channel);
      if (_state == RealtimeState.connected) await _sendSubscribe(channel);
    }
  }

  void removeListener(String channel, String event, void Function(dynamic) callback) {
    _listeners[channel]?[event]?.remove(callback);
  }

  Future<void> unsubscribe(String channel) async {
    _subscribed.remove(channel);
    _listeners.remove(channel);
    _send({'event': 'pusher:unsubscribe', 'data': {'channel': channel}});
  }

  Future<void> disconnect() async {
    _intentionallyDisconnected = true;
    _pingTimer?.cancel();
    _reconnectTimer?.cancel();
    await _sub?.cancel();
    await _socket?.sink.close();
    _subscribed.clear();
    _listeners.clear();
    _socketId = null;
    _setState(RealtimeState.disconnected);
  }
}
