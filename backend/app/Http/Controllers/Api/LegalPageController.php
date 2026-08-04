<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use Illuminate\Http\Request;

class LegalPageController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $page = LegalPage::findBySlug($slug);

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $lang = in_array($request->query('lang'), ['en', 'so']) ? $request->query('lang') : 'en';

        return response()->json([
            'slug'    => $page->slug,
            'title'   => $lang === 'so' ? $page->title_so : $page->title_en,
            'content' => $lang === 'so' ? $page->content_so : $page->content_en,
        ]);
    }
}
