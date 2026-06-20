{{-- ═══════════════════════════════════════════════════════════
     Real-time order notification popup + sound
     Include once per page that needs it.
     Requires: meta[name=csrf-token] in <head>
════════════════════════════════════════════════════════════ --}}

{{-- Notification tray --}}
<div id="onTray" style="
    position:fixed; top:18px; right:18px; z-index:99999;
    display:flex; flex-direction:column; gap:10px; pointer-events:none;
    max-width:380px; width:calc(100vw - 36px);
"></div>

<style>
.on-card {
    background:#fff; border-radius:14px; padding:16px 18px;
    box-shadow:0 8px 40px rgba(7,0,59,0.22); border-left:5px solid #FF8A00;
    pointer-events:all; animation:onSlide .35s cubic-bezier(.22,1,.36,1);
    display:flex; gap:14px; align-items:flex-start;
}
@keyframes onSlide {
    from { opacity:0; transform:translateX(60px); }
    to   { opacity:1; transform:translateX(0); }
}
.on-icon {
    width:44px; height:44px; border-radius:11px; flex-shrink:0;
    background:linear-gradient(135deg,#FF8A00,#ff6200);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:20px;
}
.on-body { flex:1; min-width:0; }
.on-title { font-weight:800; font-size:14px; color:#07003B; margin-bottom:3px; }
.on-sub   { font-size:12.5px; color:#6b7280; margin-bottom:10px; }
.on-actions { display:flex; gap:8px; }
.on-btn {
    padding:7px 14px; border-radius:8px; font-size:12px; font-weight:700;
    cursor:pointer; border:none; text-decoration:none; display:inline-flex;
    align-items:center; gap:5px; transition:all .2s;
}
.on-btn-primary { background:#FF8A00; color:#fff; }
.on-btn-primary:hover { background:#e07500; color:#fff; }
.on-btn-mute { background:#f3f4f6; color:#374151; }
.on-btn-mute:hover { background:#e5e7eb; }
.on-close {
    background:none; border:none; color:#9ca3af; cursor:pointer;
    font-size:16px; padding:2px; flex-shrink:0; line-height:1;
}
.on-close:hover { color:#374151; }
</style>

<script>
(function () {
    const POLL_URL  = '{{ route("admin.orders.poll") }}';
    const POLL_MS   = 8000;   // check every 8 s
    const TS_KEY    = 'on_last_ts';
    const SEEN_KEY  = 'on_seen_ids';

    // ── Init timestamp ─────────────────────────────────────────────────
    const nowTs = Math.floor(Date.now() / 1000);
    const stored = parseInt(localStorage.getItem(TS_KEY) || '0', 10);
    if (!stored || nowTs - stored > 3600) {
        localStorage.setItem(TS_KEY, nowTs - 300);
    }

    // ── Seen/dismissed orders persist across page navigations ─────────
    function getSeenIds() {
        try { return new Set(JSON.parse(localStorage.getItem(SEEN_KEY) || '[]')); } catch(e) { return new Set(); }
    }
    function addSeenId(id) {
        var seen = getSeenIds();
        seen.add(id);
        // Keep only last 200 to avoid growing forever
        var arr = [...seen].slice(-200);
        localStorage.setItem(SEEN_KEY, JSON.stringify(arr));
    }

    // ── Audio: generate alarm beep via Web Audio API ───────────────────
    let audioCtx = null;
    let alarmIntervalId = null;
    const mutedOrders = getSeenIds();

    function getAudioCtx() {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        return audioCtx;
    }

    // Play one rich "ding" note: sine fundamental + harmonics + fast decay
    function ding(freq, startTime) {
        try {
            const ctx  = getAudioCtx();
            const t    = startTime ?? ctx.currentTime;
            const gain = ctx.createGain();
            gain.connect(ctx.destination);

            // Fundamental + 2nd harmonic for richness
            [1, 2].forEach((mult, i) => {
                const osc = ctx.createOscillator();
                const g   = ctx.createGain();
                osc.connect(g);
                g.connect(gain);
                osc.type = 'sine';
                osc.frequency.value = freq * mult;
                // Harmonic gets lower gain
                g.gain.setValueAtTime(i === 0 ? 1.0 : 0.35, t);
                osc.start(t);
                osc.stop(t + 1.6);
            });

            // Master envelope: punch attack, long natural decay
            gain.gain.setValueAtTime(0, t);
            gain.gain.linearRampToValueAtTime(1.0, t + 0.008);  // sharp attack
            gain.gain.exponentialRampToValueAtTime(0.001, t + 1.5); // slow bell decay
        } catch (e) {}
    }

    function playAlarm() {
        // Three-note ascending chime: E5 → G#5 → B5  (major triad)
        try {
            const ctx = getAudioCtx();
            const t   = ctx.currentTime;
            ding(659.25, t);           // E5
            ding(830.61, t + 0.22);    // G#5
            ding(987.77, t + 0.44);    // B5
        } catch (e) {}
    }

    function startAlarm(orderId) {
        if (alarmIntervalId) return; // already ringing
        playAlarm();
        alarmIntervalId = setInterval(() => {
            // Keep ringing as long as there are un-muted notifications
            if (!document.querySelector('.on-card[data-order]')) {
                stopAlarm();
                return;
            }
            playAlarm();
        }, 2500);
    }

    function stopAlarm() {
        if (alarmIntervalId) { clearInterval(alarmIntervalId); alarmIntervalId = null; }
    }

    // ── Show notification card ─────────────────────────────────────────
    const tray = document.getElementById('onTray');

    function moduleLabel(slug) {
        const map = {
            efood:'eFood', eshop:'eShop', eparcel:'eParcel', elaundry:'eLaundry',
            emoving:'eMoving', eticket:'eTicket', ehealth:'eHealth', edata:'eData',
            erent:'eRent', egrocery:'eGrocery', ewholesale:'eWholesale', eexchange:'eExchange',
        };
        return map[slug] || slug;
    }

    function showCard(order) {
        if (document.querySelector(`.on-card[data-order="${order.id}"]`)) return;

        const card = document.createElement('div');
        card.className = 'on-card';
        card.dataset.order = order.id;
        card.innerHTML = `
            <div class="on-icon"><i class="fas fa-shopping-bag"></i></div>
            <div class="on-body">
                <div class="on-title">New Order — ${moduleLabel(order.module)}</div>
                <div class="on-sub">
                    <b>${order.order_number}</b> &nbsp;·&nbsp; ${order.customer}
                    &nbsp;·&nbsp; $${order.total}
                </div>
                <div class="on-actions">
                    <a class="on-btn on-btn-primary" href="${order.url}">
                        <i class="fas fa-eye"></i> View Order
                    </a>
                    <button class="on-btn on-btn-mute" onclick="onMute(${order.id})">
                        <i class="fas fa-bell-slash"></i> Mute
                    </button>
                </div>
            </div>
            <button class="on-close" onclick="onDismiss(${order.id})" title="Dismiss">
                <i class="fas fa-times"></i>
            </button>
        `;

        // Clicking "View Order" dismisses card and stops alarm if no more cards
        card.querySelector('.on-btn-primary').addEventListener('click', () => {
            onDismiss(order.id);
        });

        tray.appendChild(card);
        startAlarm(order.id);
    }

    window.onMute = function(orderId) {
        mutedOrders.add(orderId);
        addSeenId(orderId);
        const card = document.querySelector(`.on-card[data-order="${orderId}"]`);
        if (card) card.remove();
        if (!document.querySelector('.on-card[data-order]')) stopAlarm();
    };

    window.onDismiss = function(orderId) {
        const card = document.querySelector(`.on-card[data-order="${orderId}"]`);
        if (card) card.remove();
        mutedOrders.add(orderId);
        addSeenId(orderId);
        if (!document.querySelector('.on-card[data-order]')) stopAlarm();
    };

    // ── Polling loop ───────────────────────────────────────────────────
    async function poll() {
        try {
            const since = localStorage.getItem(TS_KEY) || Math.floor(Date.now() / 1000);
            const res   = await fetch(`${POLL_URL}?since=${since}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) return;
            const data = await res.json();

            if (data.ts) localStorage.setItem(TS_KEY, data.ts);

            (data.orders || []).forEach(order => {
                if (!mutedOrders.has(order.id)) showCard(order);
            });
        } catch (e) {}
    }

    // Warm up AudioContext on first user gesture (browser policy)
    document.addEventListener('click', () => { try { getAudioCtx().resume(); } catch(e){} }, { once: true });

    // Start polling after a short delay so the page settles
    setTimeout(() => { poll(); setInterval(poll, POLL_MS); }, 3000);
})();
</script>
