class CallUser {
  final int id;
  final String name;
  final String username;
  final String avatar;

  const CallUser({
    required this.id,
    required this.name,
    required this.username,
    required this.avatar,
  });

  factory CallUser.fromJson(Map<String, dynamic> j) => CallUser(
        id: j['id'] ?? 0,
        name: j['name'] ?? '',
        username: j['username'] ?? '',
        avatar: j['avatar'] ?? '',
      );
}

class CallModel {
  final int id;
  final String type; // audio | video
  final String status; // ringing | accepted | rejected | missed | ended
  final String roomName;
  final int? duration; // seconds
  final bool isOutgoing;
  final CallUser caller;
  final CallUser receiver;
  final DateTime createdAt;

  const CallModel({
    required this.id,
    required this.type,
    required this.status,
    required this.roomName,
    this.duration,
    required this.isOutgoing,
    required this.caller,
    required this.receiver,
    required this.createdAt,
  });

  factory CallModel.fromJson(Map<String, dynamic> j) => CallModel(
        id: j['id'] ?? 0,
        type: j['type'] ?? 'audio',
        status: j['status'] ?? 'ringing',
        roomName: j['room_name'] ?? '',
        duration: j['duration'],
        isOutgoing: j['is_outgoing'] ?? false,
        caller: CallUser.fromJson(j['caller'] ?? {}),
        receiver: CallUser.fromJson(j['receiver'] ?? {}),
        createdAt: DateTime.tryParse(j['created_at'] ?? '') ?? DateTime.now(),
      );

  CallUser get otherParty => isOutgoing ? receiver : caller;
}

class CallSession {
  final CallModel call;
  final String token;
  final String livekitUrl;

  const CallSession({
    required this.call,
    required this.token,
    required this.livekitUrl,
  });

  factory CallSession.fromJson(Map<String, dynamic> j) => CallSession(
        call: CallModel.fromJson(j['call'] ?? {}),
        token: j['token'] ?? '',
        livekitUrl: j['livekit_url'] ?? '',
      );
}
