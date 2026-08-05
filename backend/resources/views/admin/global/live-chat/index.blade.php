@extends('admin.layouts.app')
@section('title', 'Live Chat')

@section('content')
<div class="page-header">
    <div><h1 class="page-title">💬 Live Chat — LiveKit</h1><p class="page-subtitle">Real-time customer support via LiveKit</p></div>
</div>

@if(!$isConfigured)
{{-- Setup Guide --}}
<div style="background:linear-gradient(135deg,#0f0260,#1a0a7c);border-radius:16px;padding:32px;margin-bottom:24px;color:#fff">
    <div style="font-size:28px;margin-bottom:12px">🎙️</div>
    <h2 style="font-size:20px;font-weight:800;margin-bottom:8px">LiveKit Not Configured</h2>
    <p style="font-size:14px;color:rgba(255,255,255,.75);margin-bottom:20px">Set up your LiveKit server to enable real-time voice/video/chat support.</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div style="background:rgba(255,255,255,.1);border-radius:10px;padding:16px">
            <div style="font-size:13px;font-weight:700;margin-bottom:6px">Step 1: Deploy LiveKit</div>
            <code style="font-size:11px;background:rgba(0,0,0,.3);padding:8px 10px;border-radius:6px;display:block;color:#a5b4fc">docker run -p 7880:7880 \<br>  livekit/livekit-server \<br>  --dev</code>
        </div>
        <div style="background:rgba(255,255,255,.1);border-radius:10px;padding:16px">
            <div style="font-size:13px;font-weight:700;margin-bottom:6px">Step 2: Or Use LiveKit Cloud</div>
            <p style="font-size:12px;color:rgba(255,255,255,.7);margin-bottom:8px">Free tier available at cloud.livekit.io</p>
            <a href="https://cloud.livekit.io" target="_blank" style="display:inline-block;padding:6px 14px;background:#FF8A00;color:#fff;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none">Open LiveKit Cloud →</a>
        </div>
    </div>
</div>
@endif

{{-- Config Form --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
    <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">⚙️ LiveKit Configuration</h3>
    <form method="POST" action="{{ route('admin.global.settings.update') }}">
        @csrf @method('PUT')
        <div style="display:flex;flex-direction:column;gap:12px">
            @foreach([
                ['global_livekit_url','LiveKit Server URL','wss://your-livekit-server.com'],
                ['global_livekit_api_key','API Key','APIxxxxxxxxx'],
                ['global_livekit_secret','API Secret','your-secret'],
            ] as [$k,$l,$p])
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">{{ $l }}</label>
                <input name="{{ $k }}" value="{{ \App\Models\Global\GlobalSetting::get($k,'') }}" placeholder="{{ $p }}" type="{{ str_contains($k,'secret')?'password':'text' }}" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;font-family:monospace">
            </div>
            @endforeach
            <button type="submit" style="padding:10px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;margin-top:4px">Save Configuration</button>
        </div>
    </form>
</div>

<div style="display:flex;flex-direction:column;gap:16px">
    @if($isConfigured)
    {{-- Live Chat Room --}}
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;background:linear-gradient(135deg,#6366f108,#fff)">
            <h3 style="font-size:14px;font-weight:700;color:#111">🟢 Chat Rooms</h3>
            <p style="font-size:12px;color:#6b7280;margin-top:2px">Join customer support rooms</p>
        </div>
        <div style="padding:20px">
            <div id="chat-container" style="height:300px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;margin-bottom:14px">
                <div style="text-align:center;color:#9ca3af">
                    <i class="fas fa-comments" style="font-size:36px;margin-bottom:10px"></i>
                    <p style="font-size:13px">Select a customer to start chatting</p>
                </div>
            </div>
            <div style="display:flex;gap:8px">
                <input id="room-name" placeholder="Customer ID or Room Name" style="flex:1;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
                <button onclick="joinRoom()" style="padding:9px 18px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Join</button>
            </div>
        </div>
    </div>
    @else
    <div style="background:#f9fafb;border-radius:12px;border:2px dashed #e5e7eb;padding:32px;text-align:center">
        <i class="fas fa-plug" style="font-size:36px;color:#d1d5db;margin-bottom:12px"></i>
        <p style="font-size:14px;color:#9ca3af;font-weight:600">Configure LiveKit to enable live chat</p>
    </div>
    @endif

    {{-- Info --}}
    <div style="background:#eff6ff;border-radius:12px;border:1px solid #bfdbfe;padding:20px">
        <h3 style="font-size:13px;font-weight:700;color:#1d4ed8;margin-bottom:10px">ℹ️ What is LiveKit?</h3>
        <div style="font-size:12px;color:#374151;line-height:1.7">
            <p style="margin-bottom:6px">• Open-source real-time video/audio/data infrastructure</p>
            <p style="margin-bottom:6px">• Supports voice calls, video calls, text chat</p>
            <p style="margin-bottom:6px">• Self-hosted (free) or LiveKit Cloud ($free/$25/mo)</p>
            <p>• Same tech used by Twitch, Discord-like apps</p>
        </div>
    </div>
</div>

</div>

<script>
function joinRoom() {
    const room = document.getElementById('room-name').value;
    if (!room) return alert('Enter a room name');
    // LiveKit Web SDK integration goes here
    // For now, show placeholder
    document.getElementById('chat-container').innerHTML = `
        <div style="text-align:center;padding:20px">
            <i class="fas fa-spinner fa-spin" style="font-size:24px;color:#6366f1;margin-bottom:10px"></i>
            <p style="font-size:13px;color:#374151">Connecting to room: <strong>${room}</strong></p>
            <p style="font-size:11px;color:#9ca3af;margin-top:6px">Install @livekit/client SDK in Flutter/Web to enable full chat</p>
        </div>
    `;
}
</script>
@endsection
