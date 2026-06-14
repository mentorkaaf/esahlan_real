<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Pending — eSahlan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --brand: #FF8A00; --navy: #07003B; --text: #1a1a2e; --text-muted: #7b7fa8; --border: #e8eaf0; --warning: #f59e0b; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, var(--navy) 0%, #1a0874 50%, #0c0148 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .card {
            background: #fff; border-radius: 24px; padding: 48px 40px;
            width: 100%; max-width: 480px; text-align: center;
            box-shadow: 0 30px 80px rgba(0,0,0,0.35);
        }
        .pending-icon {
            width: 84px; height: 84px; border-radius: 50%;
            background: rgba(245,158,11,0.1); border: 3px solid rgba(245,158,11,0.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 36px; color: var(--warning);
            margin: 0 auto 24px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(245,158,11,0.3); }
            50% { box-shadow: 0 0 0 16px rgba(245,158,11,0); }
        }
        h1 { font-size: 24px; font-weight: 800; color: var(--text); margin-bottom: 10px; }
        .sub { font-size: 14px; color: var(--text-muted); line-height: 1.7; margin-bottom: 28px; }
        .info-box {
            background: #fafbff; border: 1.5px solid var(--border); border-radius: 14px;
            padding: 18px 20px; text-align: left; margin-bottom: 28px;
        }
        .info-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 13.5px; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-muted); font-weight: 600; width: 100px; flex-shrink: 0; }
        .info-value { font-weight: 700; color: var(--text); }
        .steps-list { display: flex; flex-direction: column; gap: 10px; text-align: left; margin-bottom: 28px; }
        .step-item { display: flex; align-items: center; gap: 12px; font-size: 13.5px; }
        .step-dot { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }
        .step-dot.done { background: rgba(16,185,129,0.1); color: #10b981; }
        .step-dot.pending { background: rgba(245,158,11,0.1); color: var(--warning); }
        .step-dot.wait { background: var(--border); color: var(--text-muted); }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 24px; border-radius: 10px; font-size: 14px; font-weight: 700;
            cursor: pointer; text-decoration: none; transition: all .15s;
        }
        .btn-logout {
            background: #fff5f5; color: #ef4444; border: 1.5px solid #fecaca;
        }
        .btn-logout:hover { background: #fee2e2; }
        .logo { display: flex; align-items: center; gap: 10px; justify-content: center; margin-bottom: 32px; }
        .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg,var(--brand),#ff6200); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 17px; color: #fff; }
        .logo-name { font-size: 18px; font-weight: 800; color: var(--text); }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-icon"><i class="fa-solid fa-store"></i></div>
        <div class="logo-name">eSahlan</div>
    </div>

    <div class="pending-icon"><i class="fa-solid fa-clock"></i></div>
    <h1>Application Under Review</h1>
    <p class="sub">
        Thank you for registering! Your vendor application has been submitted and is being reviewed by our team. You will be able to access the vendor panel once approved.
    </p>

    <div class="info-box">
        <div class="info-row">
            <span class="info-label"><i class="fa-solid fa-store" style="width:16px;"></i> Store</span>
            <span class="info-value">{{ $vendor->name }}</span>
        </div>
        <div class="info-row">
            <span class="info-label"><i class="fa-solid fa-layer-group" style="width:16px;"></i> Module</span>
            <span class="info-value">{{ $vendor->module?->name ?? ucfirst($vendor->module_slug ?? '—') }}</span>
        </div>
        <div class="info-row">
            <span class="info-label"><i class="fa-solid fa-circle-dot" style="width:16px;"></i> Status</span>
            <span class="info-value" style="color:var(--warning);">Pending Approval</span>
        </div>
    </div>

    <div class="steps-list">
        <div class="step-item">
            <div class="step-dot done"><i class="fa-solid fa-check"></i></div>
            <span><strong>Registration submitted</strong> — Your application is in the queue</span>
        </div>
        <div class="step-item">
            <div class="step-dot pending"><i class="fa-solid fa-clock"></i></div>
            <span><strong>Admin review</strong> — Usually within 24 hours</span>
        </div>
        <div class="step-item">
            <div class="step-dot wait">3</div>
            <span style="color:var(--text-muted);"><strong>Approved</strong> — Start managing your store</span>
        </div>
    </div>

    <form action="{{ route('vendor.logout') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-logout" style="width:100%;justify-content:center;">
            <i class="fa-solid fa-right-from-bracket"></i> Sign Out
        </button>
    </form>
</div>
</body>
</html>
