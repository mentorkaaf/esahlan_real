@php
use App\Models\Setting;
$hero_badge    = Setting::get('landing_hero_badge',    'Smart Services Platform');
$hero_title    = Setting::get('landing_hero_title',    'All Services in One Place');
$hero_subtitle = Setting::get('landing_hero_subtitle', 'eSahlan is an all-in-one platform combining delivery, shopping, and daily services in a single easy-to-use app — for individuals and businesses alike.');
$hero_btn1     = Setting::get('landing_hero_btn1',     'Explore Services');
$hero_btn2     = Setting::get('landing_hero_btn2',     'Learn More');
$stat1_num     = Setting::get('landing_stat1_num',     '12+');
$stat1_label   = Setting::get('landing_stat1_label',   'Integrated Services');
$stat2_num     = Setting::get('landing_stat2_num',     '24/7');
$stat2_label   = Setting::get('landing_stat2_label',   'Continuous Support');
$stat3_num     = Setting::get('landing_stat3_num',     '100%');
$stat3_label   = Setting::get('landing_stat3_label',   'Safe & Reliable');
$stat4_num     = Setting::get('landing_stat4_num',     '∞');
$stat4_label   = Setting::get('landing_stat4_label',   'Unlimited Potential');
$cta_title     = Setting::get('landing_cta_title',     'Join the eSahlan Network');
$cta_subtitle  = Setting::get('landing_cta_subtitle',  'Are you a vendor, driver, or property agent? Register now and start earning.');
$vendor_label  = Setting::get('landing_vendor_label',  'Vendor');
$vendor_sub    = Setting::get('landing_vendor_sub',    'List your store & start selling');
$driver_label  = Setting::get('landing_driver_label',  'Delivery Driver');
$driver_sub    = Setting::get('landing_driver_sub',    'Deliver orders & earn daily');
$agent_label   = Setting::get('landing_agent_label',   'Property Agent');
$agent_sub     = Setting::get('landing_agent_sub',     'List properties on eRent');
$vendor_url    = Setting::get('landing_vendor_url',    '');
$driver_url    = Setting::get('landing_driver_url',    '');
$agent_url     = Setting::get('landing_agent_url',     '');
$show_vendor   = Setting::get('landing_show_vendor',   '1');
$show_driver   = Setting::get('landing_show_driver',   '1');
$show_agent    = Setting::get('landing_show_agent',    '1');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eSahlan — All Services in One Place</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand:      #FF8A00;
            --brand-dark: #e07500;
            --navy:       #07003B;
            --navy-light: #0c0148;
            --white:      #ffffff;
            --light:      #f8f9ff;
            --text:       #1a1a2e;
            --text-muted: #6b7280;
        }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--white);
            color: var(--text);
            overflow-x: hidden;
        }

        /* Navbar */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 5%;
            height: 70px;
            background: rgba(7,0,59,0.95);
            backdrop-filter: blur(12px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.3);
        }
        .nav-logo {
            font-size: 1.8rem; font-weight: 900;
            color: var(--white); text-decoration: none;
        }
        .nav-logo span { color: var(--brand); }
        .nav-btn {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--brand); color: var(--white);
            padding: 10px 24px; border-radius: 50px;
            text-decoration: none; font-weight: 700; font-size: 0.95rem;
            transition: all 0.3s; box-shadow: 0 4px 15px rgba(255,138,0,0.4);
        }
        .nav-btn:hover { background: var(--brand-dark); transform: translateY(-2px); }

        /* Hero */
        .hero {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 50%, #1a0080 100%);
            display: flex; align-items: center; justify-content: center;
            text-align: center; padding: 100px 5% 60px;
            position: relative; overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse at 50% 50%, rgba(255,138,0,0.15) 0%, transparent 70%);
        }
        .hero-badge {
            display: inline-block;
            background: rgba(255,138,0,0.15); border: 1px solid rgba(255,138,0,0.4);
            color: var(--brand); padding: 6px 20px; border-radius: 50px;
            font-size: 0.85rem; font-weight: 600; margin-bottom: 24px;
        }
        .hero h1 {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            font-weight: 900; color: var(--white);
            line-height: 1.15; margin-bottom: 24px;
        }
        .hero h1 span { color: var(--brand); }
        .hero p {
            font-size: clamp(1rem, 2vw, 1.25rem);
            color: rgba(255,255,255,0.75); max-width: 600px; margin: 0 auto 40px;
            line-height: 1.8;
        }
        .hero-btns { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
        .btn-primary {
            display: inline-flex; align-items: center; gap: 10px;
            background: var(--brand); color: var(--white);
            padding: 16px 36px; border-radius: 50px;
            text-decoration: none; font-weight: 700; font-size: 1.05rem;
            transition: all 0.3s; box-shadow: 0 6px 25px rgba(255,138,0,0.45);
        }
        .btn-primary:hover { background: var(--brand-dark); transform: translateY(-3px); }
        .btn-outline {
            display: inline-flex; align-items: center; gap: 10px;
            background: transparent; color: var(--white);
            border: 2px solid rgba(255,255,255,0.35);
            padding: 16px 36px; border-radius: 50px;
            text-decoration: none; font-weight: 600; font-size: 1.05rem;
            transition: all 0.3s;
        }
        .btn-outline:hover { border-color: var(--white); background: rgba(255,255,255,0.08); }

        .circle {
            position: absolute; border-radius: 50%;
            background: rgba(255,138,0,0.08); pointer-events: none;
        }
        .circle-1 { width: 500px; height: 500px; top: -150px; right: -150px; }
        .circle-2 { width: 300px; height: 300px; bottom: -80px; left: -80px; }
        .circle-3 { width: 200px; height: 200px; top: 40%; left: 10%; background: rgba(255,255,255,0.04); }

        /* Stats */
        .stats {
            background: var(--navy);
            padding: 50px 5%;
            display: flex; justify-content: center; flex-wrap: wrap;
        }
        .stat-item {
            text-align: center; padding: 20px 50px;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .stat-item:last-child { border-right: none; }
        .stat-num { font-size: 2.5rem; font-weight: 900; color: var(--brand); }
        .stat-label { color: rgba(255,255,255,0.6); font-size: 0.9rem; margin-top: 4px; }

        /* Services */
        .services { padding: 100px 5%; background: var(--light); }
        .section-header { text-align: center; margin-bottom: 60px; }
        .section-tag {
            display: inline-block;
            background: rgba(255,138,0,0.1); color: var(--brand);
            padding: 6px 18px; border-radius: 50px;
            font-size: 0.85rem; font-weight: 700; margin-bottom: 16px;
        }
        .section-header h2 {
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 900; color: var(--navy); margin-bottom: 16px;
        }
        .section-header p { color: var(--text-muted); font-size: 1.05rem; max-width: 550px; margin: 0 auto; }
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 24px; max-width: 1200px; margin: 0 auto;
        }
        .service-card {
            background: var(--white); border-radius: 20px;
            padding: 36px 28px; text-align: center;
            box-shadow: 0 4px 20px rgba(7,0,59,0.07);
            transition: all 0.35s; border: 2px solid transparent;
        }
        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 40px rgba(7,0,59,0.14);
            border-color: var(--brand);
        }
        .service-icon {
            width: 70px; height: 70px; border-radius: 18px;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; font-size: 1.8rem; color: var(--white);
            box-shadow: 0 6px 20px rgba(255,138,0,0.35);
        }
        .service-card h3 { font-size: 1.1rem; font-weight: 700; color: var(--navy); margin-bottom: 10px; }
        .service-card p { color: var(--text-muted); font-size: 0.9rem; line-height: 1.7; }

        /* Features */
        .features { padding: 100px 5%; background: var(--white); }
        .features-inner {
            max-width: 1100px; margin: 0 auto;
            display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center;
        }
        .features-text h2 {
            font-size: clamp(1.8rem, 3.5vw, 2.5rem);
            font-weight: 900; color: var(--navy); margin-bottom: 20px; line-height: 1.3;
        }
        .features-text h2 span { color: var(--brand); }
        .features-text p { color: var(--text-muted); line-height: 1.9; margin-bottom: 32px; font-size: 1.02rem; }
        .feature-list { list-style: none; display: flex; flex-direction: column; gap: 16px; }
        .feature-list li {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 16px 20px; border-radius: 12px;
            background: var(--light); border-right: 4px solid var(--brand);
        }
        .feature-list li i { color: var(--brand); font-size: 1.1rem; margin-top: 2px; flex-shrink: 0; }
        .feature-list li span { font-size: 0.95rem; color: var(--text); font-weight: 600; }
        .features-visual { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .feat-box {
            background: var(--light); border-radius: 16px;
            padding: 28px 20px; text-align: center;
            border: 1px solid rgba(7,0,59,0.06); transition: all 0.3s;
        }
        .feat-box:hover { background: var(--navy); }
        .feat-box:hover .feat-box-icon,
        .feat-box:hover .feat-box-label { color: var(--white); }
        .feat-box-icon { font-size: 2rem; color: var(--brand); margin-bottom: 12px; display: block; }
        .feat-box-label { font-size: 0.9rem; font-weight: 700; color: var(--navy); }
        .feat-box.tall {
            grid-row: span 2; display: flex; flex-direction: column; justify-content: center;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
        }
        .feat-box.tall .feat-box-icon,
        .feat-box.tall .feat-box-label { color: var(--white); }
        .feat-box.tall .feat-box-num { font-size: 3rem; font-weight: 900; color: var(--brand); display: block; }

        /* CTA */
        .cta {
            padding: 100px 5%; text-align: center;
            background: linear-gradient(135deg, var(--navy), #1a0080);
            position: relative; overflow: hidden;
        }
        .cta::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(ellipse at 50% 50%, rgba(255,138,0,0.12) 0%, transparent 70%);
        }
        .cta h2 {
            font-size: clamp(1.8rem, 4vw, 3rem);
            font-weight: 900; color: var(--white); margin-bottom: 20px; position: relative;
        }
        .cta p { color: rgba(255,255,255,0.7); font-size: 1.1rem; margin-bottom: 40px; position: relative; }
        .cta-admin-link {
            display: inline-flex; align-items: center; gap: 12px;
            background: rgba(255,255,255,0.08); border: 2px solid rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.7); padding: 12px 28px; border-radius: 50px;
            text-decoration: none; font-size: 0.9rem; font-weight: 600;
            transition: all 0.3s; margin-top: 16px; position: relative;
        }
        .cta-admin-link:hover { background: rgba(255,255,255,0.15); color: var(--white); }

        /* Register Grid */
        .register-grid {
            display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;
            margin-top: 10px;
        }
        .register-card {
            display: flex; align-items: center; gap: 18px;
            background: rgba(255,255,255,0.07);
            border: 1.5px solid rgba(255,255,255,0.15);
            border-radius: 18px; padding: 22px 28px;
            text-decoration: none; color: var(--white);
            transition: all 0.3s; min-width: 280px;
        }
        .register-card:hover {
            background: rgba(255,138,0,0.15);
            border-color: var(--brand);
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(255,138,0,0.25);
        }
        .register-icon {
            width: 54px; height: 54px; border-radius: 14px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; color: var(--white);
            box-shadow: 0 4px 14px rgba(255,138,0,0.4);
        }
        .register-info { display: flex; flex-direction: column; flex: 1; text-align: left; }
        .register-title { font-size: 1.05rem; font-weight: 700; }
        .register-sub { font-size: 0.82rem; color: rgba(255,255,255,0.55); margin-top: 3px; }
        .register-arrow { color: rgba(255,255,255,0.3); font-size: 0.85rem; transition: all 0.3s; transform: rotate(180deg); }
        .register-card:hover .register-arrow { color: var(--brand); transform: rotate(180deg) translateX(-4px); }

        /* Footer */
        footer {
            background: #030022; padding: 40px 5%;
            text-align: center; color: rgba(255,255,255,0.4); font-size: 0.88rem;
        }
        footer span { color: var(--brand); }

        @media (max-width: 768px) {
            .stat-item { padding: 16px 24px; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.1); }
            .stat-item:last-child { border-bottom: none; }
            .features-inner { grid-template-columns: 1fr; gap: 40px; }
            .features-visual { display: none; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav>
    <a href="/" class="nav-logo">e<span>Sahlan</span></a>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="circle circle-1"></div>
    <div class="circle circle-2"></div>
    <div class="circle circle-3"></div>
    <div style="position:relative; z-index:1;">
        <div class="hero-badge">🚀 {{ $hero_badge }}</div>
        <h1>{!! nl2br(e($hero_title)) !!}</h1>
        <p>{{ $hero_subtitle }}</p>
        <div class="hero-btns">
            <a href="#services" class="btn-primary"><i class="fas fa-th-large"></i> {{ $hero_btn1 }}</a>
            <a href="#features" class="btn-outline"><i class="fas fa-info-circle"></i> {{ $hero_btn2 }}</a>
        </div>
    </div>
</section>

<!-- Stats -->
<div class="stats">
    <div class="stat-item">
        <div class="stat-num">{{ $stat1_num }}</div>
        <div class="stat-label">{{ $stat1_label }}</div>
    </div>
    <div class="stat-item">
        <div class="stat-num">{{ $stat2_num }}</div>
        <div class="stat-label">{{ $stat2_label }}</div>
    </div>
    <div class="stat-item">
        <div class="stat-num">{{ $stat3_num }}</div>
        <div class="stat-label">{{ $stat3_label }}</div>
    </div>
    <div class="stat-item">
        <div class="stat-num">{{ $stat4_num }}</div>
        <div class="stat-label">{{ $stat4_label }}</div>
    </div>
</div>

<!-- Services -->
<section class="services" id="services">
    <div class="section-header">
        <div class="section-tag">Our Services</div>
        <h2>What does eSahlan offer?</h2>
        <p>A comprehensive platform covering your daily needs with a single tap</p>
    </div>
    <div class="services-grid">
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-utensils"></i></div>
            <h3>eFood</h3>
            <p>Order your favorite meals from top restaurants and get them delivered fast.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-shopping-bag"></i></div>
            <h3>eShop</h3>
            <p>Shop online from multiple stores at competitive prices.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-store-alt"></i></div>
            <h3>eWholesale</h3>
            <p>Buy in bulk directly from wholesale suppliers at the best rates.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-carrot"></i></div>
            <h3>eGrocery</h3>
            <p>Fresh groceries delivered to your door quickly and conveniently.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-shipping-fast"></i></div>
            <h3>eParcel</h3>
            <p>Send and receive parcels easily with real-time shipment tracking.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-tshirt"></i></div>
            <h3>eLaundry</h3>
            <p>Professional laundry and ironing service delivered to your doorstep.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-truck-moving"></i></div>
            <h3>eMoving</h3>
            <p>Move your furniture and belongings safely with a professional team.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-heartbeat"></i></div>
            <h3>eHealth</h3>
            <p>Book doctor appointments and consult health specialists anytime.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-home"></i></div>
            <h3>eRent</h3>
            <p>Find your ideal rental property with ease and convenience.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-plane"></i></div>
            <h3>eTicket</h3>
            <p>Book flight tickets and enjoy the best prices and deals.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-exchange-alt"></i></div>
            <h3>eExchange</h3>
            <p>Exchange currency at the best rates, safely and transparently.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-wifi"></i></div>
            <h3>eData</h3>
            <p>Top up internet and data packages for all mobile networks.</p>
        </div>
    </div>
</section>

<!-- Features -->
<section class="features" id="features">
    <div class="features-inner">
        <div class="features-text">
            <div class="section-tag">Why eSahlan?</div>
            <h2>A <span>Smart</span> Platform Built for Efficiency</h2>
            <p>We provide an integrated system connecting customers, service providers, and drivers through a professional admin panel and reliable apps.</p>
            <ul class="feature-list">
                <li><i class="fas fa-check-circle"></i><span>Real-time order tracking via GPS</span></li>
                <li><i class="fas fa-check-circle"></i><span>Secure multi-method payment system</span></li>
                <li><i class="fas fa-check-circle"></i><span>Instant push notifications for customer & driver</span></li>
                <li><i class="fas fa-check-circle"></i><span>Comprehensive admin panel to manage everything</span></li>
                <li><i class="fas fa-check-circle"></i><span>Multi-language and multi-currency support</span></li>
            </ul>
        </div>
        <div class="features-visual">
            <div class="feat-box tall">
                <span class="feat-box-num">12+</span>
                <span class="feat-box-icon"><i class="fas fa-layer-group"></i></span>
                <span class="feat-box-label">Integrated Service Modules</span>
            </div>
            <div class="feat-box">
                <span class="feat-box-icon"><i class="fas fa-mobile-alt"></i></span>
                <span class="feat-box-label">Customer App</span>
            </div>
            <div class="feat-box">
                <span class="feat-box-icon"><i class="fas fa-motorcycle"></i></span>
                <span class="feat-box-label">Driver App</span>
            </div>
            <div class="feat-box">
                <span class="feat-box-icon"><i class="fas fa-store"></i></span>
                <span class="feat-box-label">Vendor App</span>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <h2>{{ $cta_title }}</h2>
    <p>{{ $cta_subtitle }}</p>
    <div class="register-grid" style="position:relative;">
        @if($show_vendor)
        <a href="{{ $vendor_url ?: '#' }}" class="register-card" {{ $vendor_url ? 'target="_blank" rel="noopener noreferrer"' : '' }}>
            <div class="register-icon"><i class="fas fa-store"></i></div>
            <div class="register-info">
                <span class="register-title">{{ $vendor_label }}</span>
                <span class="register-sub">{{ $vendor_sub }}</span>
            </div>
            <i class="fas fa-arrow-right register-arrow"></i>
        </a>
        @endif
        @if($show_driver)
        <a href="{{ $driver_url ?: '#' }}" class="register-card" {{ $driver_url ? 'target="_blank" rel="noopener noreferrer"' : '' }}>
            <div class="register-icon"><i class="fas fa-motorcycle"></i></div>
            <div class="register-info">
                <span class="register-title">{{ $driver_label }}</span>
                <span class="register-sub">{{ $driver_sub }}</span>
            </div>
            <i class="fas fa-arrow-right register-arrow"></i>
        </a>
        @endif
        @if($show_agent)
        <a href="{{ $agent_url ?: '#' }}" class="register-card" {{ $agent_url ? 'target="_blank" rel="noopener noreferrer"' : '' }}>
            <div class="register-icon"><i class="fas fa-building"></i></div>
            <div class="register-info">
                <span class="register-title">{{ $agent_label }}</span>
                <span class="register-sub">{{ $agent_sub }}</span>
            </div>
            <i class="fas fa-arrow-right register-arrow"></i>
        </a>
        @endif
    </div>
</section>

<!-- Footer -->
<footer>
    <p>&copy; {{ date('Y') }} <span>eSahlan</span> — All Rights Reserved</p>
</footer>

</body>
</html>
