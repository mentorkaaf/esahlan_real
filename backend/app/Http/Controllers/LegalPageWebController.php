<?php

namespace App\Http\Controllers;

use App\Models\LegalPage;
use Illuminate\Http\Request;

class LegalPageWebController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $page = LegalPage::findBySlug($slug);

        if (!$page) {
            abort(404);
        }

        $lang = in_array($request->query('lang'), ['so', 'en']) ? $request->query('lang') : 'en';

        $title   = $lang === 'so' ? $page->title_so   : $page->title_en;
        $content = $lang === 'so' ? $page->content_so : $page->content_en;

        return view('legal.show', compact('page', 'title', 'content', 'lang'));
    }
}
