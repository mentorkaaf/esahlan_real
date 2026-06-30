import 'dart:async';
import 'dart:convert';
import 'dart:math';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:web_socket_channel/web_socket_channel.dart';
import '../constants/app_constants.dart';
import '../storage/local_storage.dart';

enum RealtimeConnectionState { disconnected, connecting, connected, reconnecting, failed }

/// Single realtime client for the entire app — every module (Community,
/// Chat, Delivery, Vendor, Wallet, Admin, future modules) shares this one
/// Reverb connection instead of opening separate sockets. Mirrors the
/// server-side RealtimeService/RealtimeEvent pattern: one shared transport,
/// many feature-specific subscribers.
///
/// Talks the Pusher wire protocol directly over web_socket_channel rather
/// than using a Pusher SDK — those SDKs assume pusher.com's infrastructure
/// and don't cleanly support pointing at a self-hosted Reverb host without
/// version conflicts (pusher_channels_flutter >=2.5 collides with
/// flutter_secure_storage). The protocol itself is simple JSON messages,
/// already verified working end-to-end against this exact Reverb deployment.
class RealtimeClient {
  RealtimeClient._();
  static final instance = RealtimeClient._();

  WebSocketChannel? _socket;
  StreamSubscription? _socketSub;
  final _authDio = Dio(BaseOptions(headers: {'Accept': 'application/json'}));

  // channelName -> set of (eventName -> listeners)
  final _listeners = <String, Map<String, List<void Function(dynamic data)>>>{};
  final _subscribedChannels = <String>{};

  final _stateController = StreamController<RealtimeConnectionState>.broadcast();
  Stream<RealtimeConnectionState> get connectionState => _stateController.stream;
  RealtimeConnectionState _state = RealtimeConnectionState.disconnected;
  RealtimeConnectionState get state => _state;

  String? _socketId;
  int _reconnectAttempts = 0;
  Timer? _reconnectTimer;
  Timer? _pingTimer;
  bool _intentionallyDisconnected = false;

  String get _wsUrl {
    final scheme = AppConstants.reverbUseTLS ? 'wss' : 'ws';
    return '$scheme://${AppConstants.reverbHost}:${AppConstants.reverbPort}/app/${AppConstants.reverbAppKey}'
        '?protocol=7&client=flutter&version=1.0&flash=false';
  }

  Future<void> connect() async {
    if (_state == RealtimeConnectionState.connected || _state == RealtimeConnectionState.connecting) {
      debugPrint('[Realtime] connect() skipped — already $_state');
      return;
    }
    _intentionallyDisconnected = false;
    _setState(RealtimeConnectionState.connecting);
    debugPrint('[Realtime] Connecting to $_wsUrl');

    try {
      _socket = WebSocketChannel.connect(Uri.parse(_wsUrl));
      await _socket!.ready;
      debugPrint('[Realtime] Socket ready, waiting for connection_established...');

      _socketSub = _socket!.stream.listen(
        _onMessage,
        onDone: () {
          debugPrint('[Realtime] Socket closed');
          if (!_intentionallyDisconnected) _scheduleReconnect();
        },
        onError: (e) {
          debugPrint('[Realtime] Socket error: $e');
          if (!_intentionallyDisconnected) _scheduleReconnect();
        },
        cancelOnError: true,
      );
    } catch (e) {
      debugPrint('[Realtime] Connect failed: $e');
      _scheduleReconnect();
    }
  }

  void _onMessage(dynamic raw) async {
    try {
      final msg = jsonDecode(raw as String) as Map<String, dynamic>;
      final event = msg['event'] as String?;
      final channel = msg['channel'] as String?;
      final dataRaw = msg['data'];
      final data = dataRaw is String ? _tryDecode(dataRaw) : dataRaw;

      switch (event) {
        case 'pusher:connection_established':
          _socketId = (data as Map)['socket_id'] as String?;
          _reconnectAttempts = 0;
          _setState(RealtimeConnectionState.connected);
          debugPrint('[Realtime] CONNECTED, socket_id=$_socketId. Resubscribing to: $_subscribedChannels');
          _startPing();
          // Re-subscribe to any channels that were active before a reconnect.
          for (final ch in List.of(_subscribedChannels)) {
            await _sendSubscribe(ch);
          }
          break;
        case 'pusher:error':
          debugPrint('[Realtime] Server error: $data');
          break;
        case 'pusher:pong':
          break;
        case 'pusher_internal:subscription_succeeded':
        case 'pusher:subscription_succeeded':
          debugPrint('[Realtime] Subscription confirmed: $channel');
          break;
        default:
          debugPrint('[Realtime] Message: event=$event channel=$channel data=$data');
          if (channel != null && event != null) {
            final handlers = _listeners[channel]?[event];
            debugPrint('[Realtime] -> ${handlers?.length ?? 0} handler(s) registered for $channel/$event '
                '(known channels: ${_listeners.keys.toList()})');
            if (handlers != null) {
              for (final cb in List.of(handlers)) {
                try {
                  cb(data);
                } catch (e) {
                  debugPrint('[Realtime] Listener error on $channel/$event: $e');
                }
              }
            }
          }
      }
    } catch (e) {
      debugPrint('[Realtime] Failed to parse message: $e');
    }
  }

  dynamic _tryDecode(String s) {
    try {
      return jsonDecode(s);
    } catch (_) {
      return s;
    }
  }

  void _startPing() {
    _pingTimer?.cancel();
    _pingTimer = Timer.periodic(const Duration(seconds: 25), (_) {
      _send({'event': 'pusher:ping', 'data': {}});
    });
  }

  void _send(Map<String, dynamic> payload) {
    if (_socket == null) {
      debugPrint('[Realtime] _send() called with no socket — dropped: $payload');
      return;
    }
    _socket!.sink.add(jsonEncode(payload));
  }

  Future<void> _sendSubscribe(String channelName) async {
    debugPrint('[Realtime] Subscribing to $channelName (state=$_state)');
    if (channelName.startsWith('private-') || channelName.startsWith('presence-')) {
      final auth = await _authorize(channelName);
      if (auth == null) {
        debugPrint('[Realtime] Skipping subscribe to $channelName — auth failed');
        return;
      }
      _send({
        'event': 'pusher:subscribe',
        'data': {
          'channel': channelName,
          'auth': auth['auth'],
          if (auth['channel_data'] != null) 'channel_data': auth['channel_data'],
        },
      });
    } else {
      _send({'event': 'pusher:subscribe', 'data': {'channel': channelName}});
    }
  }

  Future<Map<String, dynamic>?> _authorize(String channelName) async {
    try {
      final token = await LocalStorage.getToken();
      final response = await _authDio.post(
        AppConstants.reverbAuthUrl,
        data: {'socket_id': _socketId, 'channel_name': channelName},
        options: Options(headers: token != null ? {'Authorization': 'Bearer $token'} : null),
      );
      return Map<String, dynamic>.from(response.data as Map);
    } catch (e) {
      debugPrint('[Realtime] Channel auth failed for $channelName: $e');
      return null;
    }
  }

  void _scheduleReconnect() {
    if (_intentionallyDisconnected) return;
    _pingTimer?.cancel();
    _setState(RealtimeConnectionState.reconnecting);
    _reconnectTimer?.cancel();
    _reconnectAttempts++;
    // Exponential backoff capped at 30s, with jitter to avoid every client
    // reconnecting in the same instant if the server bounces.
    final base = min(30, pow(2, min(_reconnectAttempts, 6)).toInt());
    final jitter = Random().nextInt(1000);
    final delay = Duration(seconds: base, milliseconds: jitter);
    debugPrint('[Realtime] Reconnecting in ${delay.inSeconds}s (attempt $_reconnectAttempts)');
    _reconnectTimer = Timer(delay, connect);
  }

  void _setState(RealtimeConnectionState s) {
    _state = s;
    _stateController.add(s);
  }

  /// Subscribe to a channel and listen for a specific event name. Safe to
  /// call repeatedly for the same channel/event from different widgets —
  /// each call just adds another listener; the underlying subscription is
  /// only sent once per channel.
  Future<void> listen(String channelName, String eventName, void Function(dynamic data) onEvent) async {
    debugPrint('[Realtime] listen($channelName, $eventName) — current state=$_state');
    if (_state != RealtimeConnectionState.connected) await connect();

    final channelMap = _listeners.putIfAbsent(channelName, () => {});
    channelMap.putIfAbsent(eventName, () => []).add(onEvent);

    if (!_subscribedChannels.contains(channelName)) {
      _subscribedChannels.add(channelName);
      if (_state == RealtimeConnectionState.connected) {
        await _sendSubscribe(channelName);
      } else {
        debugPrint('[Realtime] Deferred subscribe for $channelName — state is $_state, '
            'will subscribe once connection_established fires');
      }
    } else {
      debugPrint('[Realtime] $channelName already subscribed, just added a new $eventName listener');
    }
  }

  void removeListener(String channelName, String eventName, void Function(dynamic data) onEvent) {
    _listeners[channelName]?[eventName]?.remove(onEvent);
  }

  Future<void> unsubscribe(String channelName) async {
    _subscribedChannels.remove(channelName);
    _listeners.remove(channelName);
    _send({'event': 'pusher:unsubscribe', 'data': {'channel': channelName}});
  }

  Future<void> disconnect() async {
    _intentionallyDisconnected = true;
    _reconnectTimer?.cancel();
    _pingTimer?.cancel();
    await _socketSub?.cancel();
    await _socket?.sink.close();
    _subscribedChannels.clear();
    _listeners.clear();
    _socketId = null;
    _setState(RealtimeConnectionState.disconnected);
  }
}
