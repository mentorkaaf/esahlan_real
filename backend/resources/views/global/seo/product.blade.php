<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $product->name }} — eSahlan Global Store</title>
<meta name="description" content="{{ Str::limit(strip_tags($product->description ?? ''), 155) ?: 'Buy '.$product->name.' at eSahlan Global. Fast worldwide shipping, Stripe & PayPal accepted.' }}">
<link rel="canonical" href="{{ $flutterUrl }}">

<!-- Open Graph -->
<meta property="og:type" content="product">
<meta property="og:title" content="{{ $product->name }} — eSahlan Global">
<meta property="og:description" content="Buy {{ $product->name }} for ${{ $price }}. Fast worldwide shipping.">
<meta property="og:image" content="{{ $mainImage }}">
<meta property="og:url" content="{{ $flutterUrl }}">
<meta property="og:site_name" content="eSahlan Global Store">
<meta property="product:price:amount" content="{{ $product->price }}">
<meta property="product:price:currency" content="USD">
<meta property="product:availability" content="{{ $inStock ? 'in stock' : 'out of stock' }}">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $product->name }} — eSahlan Global">
<meta name="twitter:description" content="Buy {{ $product->name }} for ${{ $price }}.">
<meta name="twitter:image" content="{{ $mainImage }}">

<!-- Structured Data: Product -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "{{ addslashes($product->name) }}",
  "description": "{{ addslashes(Str::limit(strip_tags($product->description ?? ''), 300)) }}",
  "image": @json($images ?: [$mainImage]),
  "sku": "{{ $product->sku ?? 'ESG-'.$product->id }}",
  "brand": { "@type": "Brand", "name": "eSahlan Global" },
  "category": "{{ $category }}",
  "offers": {
    "@type": "Offer",
    "url": "{{ $flutterUrl }}",
    "priceCurrency": "USD",
    "price": "{{ $product->price }}",
    "availability": "https://schema.org/{{ $inStock ? 'InStock' : 'OutOfStock' }}",
    "seller": { "@type": "Organization", "name": "eSahlan Global" }
  }
  @if($product->rating_avg)
  ,"aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "{{ $product->rating_avg }}",
    "reviewCount": "{{ $product->reviews_count ?? 0 }}"
  }
  @endif
}
</script>

<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f5f5;color:#111}
.container{max-width:960px;margin:0 auto;padding:20px}
header{background:#1A1A2E;padding:14px 20px;display:flex;align-items:center;gap:12px}
header a{color:#F59E0B;font-size:20px;font-weight:800;text-decoration:none}
header span{color:rgba(255,255,255,.5);font-size:13px}
.card{background:#fff;border-radius:12px;padding:24px;margin-top:20px;display:flex;gap:24px;flex-wrap:wrap}
.img-wrap{flex:0 0 280px;max-width:280px}
.img-wrap img{width:100%;border-radius:8px;object-fit:contain;background:#f9f9f9}
.info{flex:1;min-width:220px}
.category{font-size:11px;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px}
h1{font-size:22px;font-weight:800;margin-bottom:12px;line-height:1.3}
.price{font-size:28px;font-weight:900;color:#1A1A2E;margin-bottom:8px}
.compare{font-size:15px;color:#9ca3af;text-decoration:line-through;margin-left:8px}
.badge{display:inline-block;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700}
.in-stock{background:#d1fae5;color:#065f46}
.out-stock{background:#fee2e2;color:#991b1b}
.desc{font-size:14px;color:#4b5563;line-height:1.7;margin:16px 0}
.cta{display:inline-block;background:#F59E0B;color:#1A1A2E;padding:14px 28px;border-radius:12px;font-weight:800;font-size:15px;text-decoration:none;margin-top:12px}
.breadcrumb{font-size:12px;color:#9ca3af;margin-top:16px}
.breadcrumb a{color:#6366f1;text-decoration:none}
</style>
</head>
<body>
<header>
  <a href="https://global.esahlan.com">🌍 eSahlan Global</a>
  <span>/ {{ $category }} / {{ Str::limit($product->name, 40) }}</span>
</header>

<div class="container">
  <div class="breadcrumb">
    <a href="https://global.esahlan.com">Home</a> ›
    <a href="https://global.esahlan.com/global/products">Products</a> ›
    {{ $category }} ›
    {{ Str::limit($product->name, 50) }}
  </div>

  <div class="card">
    <div class="img-wrap">
      @if($mainImage)
        <img src="{{ $mainImage }}" alt="{{ $product->name }}" loading="lazy">
      @endif
    </div>
    <div class="info">
      <div class="category">{{ $category }}</div>
      <h1>{{ $product->name }}</h1>

      <div>
        <span class="price">${{ $price }}</span>
        @if($product->compare_price)
          <span class="compare">${{ number_format($product->compare_price, 2) }}</span>
        @endif
      </div>

      <div style="margin-top:10px">
        <span class="badge {{ $inStock ? 'in-stock' : 'out-stock' }}">
          {{ $inStock ? '✓ In Stock' : '✕ Out of Stock' }}
        </span>
      </div>

      @if($product->description)
        <div class="desc">{{ Str::limit(strip_tags($product->description), 400) }}</div>
      @endif

      <a href="{{ $flutterUrl }}" class="cta">🛒 Buy Now — ${{ $price }}</a>
      <p style="font-size:11px;color:#9ca3af;margin-top:10px">Stripe &amp; PayPal · Worldwide Shipping</p>
    </div>
  </div>
</div>

<!-- Redirect browsers to Flutter SPA after 0ms (crawlers execute JS slowly) -->
<script>
  if(!/googlebot|bingbot|slurp|baiduspider|yandexbot|facebot|twitterbot|whatsapp|telegram|linkedinbot|applebot|crawler|spider|bot/i.test(navigator.userAgent)){
    location.replace('{{ $flutterUrl }}');
  }
</script>
</body>
</html>
