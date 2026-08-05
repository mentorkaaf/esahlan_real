<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

  <!-- Home -->
  <url>
    <loc>https://global.esahlan.com</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

  <!-- Products listing -->
  <url>
    <loc>https://global.esahlan.com/global/products</loc>
    <changefreq>hourly</changefreq>
    <priority>0.9</priority>
  </url>

  <!-- Categories -->
  @foreach($categories as $cat)
  <url>
    <loc>https://global.esahlan.com/global/products?category_id={{ $cat->id }}</loc>
    <lastmod>{{ \Carbon\Carbon::parse($cat->updated_at)->toAtomString() }}</lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
  @endforeach

  <!-- Products -->
  @foreach($products as $p)
  <url>
    <loc>https://global.esahlan.com/seo/product/{{ $p->id }}</loc>
    <lastmod>{{ \Carbon\Carbon::parse($p->updated_at)->toAtomString() }}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
  @endforeach

</urlset>
