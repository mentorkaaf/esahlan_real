<?php

namespace App\Http\Controllers\Api\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalProduct;
use App\Models\Global\GlobalCategory;
use Illuminate\Http\Request;

/**
 * SEO-friendly server-rendered pages for global.esahlan.com
 * Crawlers get full HTML; browsers get a redirect to the Flutter SPA.
 */
class GlobalSeoController extends Controller
{
    private const FLUTTER_BASE = 'https://global.esahlan.com';
    private const BOT_UA = '/googlebot|bingbot|slurp|duckduckbot|baiduspider|yandexbot|facebot|twitterbot|whatsapp|telegram|linkedinbot|applebot|crawler|spider|scraper|bot/i';

    private function isBot(Request $request): bool
    {
        return preg_match(self::BOT_UA, $request->userAgent() ?? '') === 1;
    }

    /** GET global.esahlan.com/seo/product/{id} */
    public function product(Request $request, $id)
    {
        $product = GlobalProduct::with(['category', 'images'])
            ->where('is_active', true)
            ->find($id);

        if (!$product) {
            return redirect(self::FLUTTER_BASE . '/global', 302);
        }

        $flutterUrl = self::FLUTTER_BASE . '/global/product/' . $product->id;

        // Real browser → redirect to Flutter SPA
        if (!$this->isBot($request)) {
            return redirect($flutterUrl, 302);
        }

        // Bot → serve full HTML for indexing
        $images = $product->images?->pluck('url')->toArray() ?? [];
        $mainImage = $images[0] ?? $product->thumbnail ?? '';
        $price = number_format($product->price, 2);
        $category = $product->category?->name ?? 'Product';
        $inStock = !$product->track_stock || $product->stock > 0;

        return view('global.seo.product', compact(
            'product', 'price', 'category', 'mainImage', 'images',
            'inStock', 'flutterUrl'
        ));
    }

    /** GET global.esahlan.com/seo/products */
    public function products(Request $request)
    {
        $flutterUrl = self::FLUTTER_BASE . '/global/products';

        if (!$this->isBot($request)) {
            return redirect($flutterUrl, 302);
        }

        $products = GlobalProduct::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(['id', 'name', 'price', 'thumbnail', 'category_id']);

        $categories = GlobalCategory::where('is_active', true)->get(['id', 'name']);

        return view('global.seo.products', compact('products', 'categories', 'flutterUrl'));
    }

    /** Sitemap XML */
    public function sitemap()
    {
        $products = GlobalProduct::where('is_active', true)
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'updated_at']);

        $categories = GlobalCategory::where('is_active', true)->get(['id', 'updated_at']);

        return response()->view('global.seo.sitemap', compact('products', 'categories'))
            ->header('Content-Type', 'application/xml');
    }
}
