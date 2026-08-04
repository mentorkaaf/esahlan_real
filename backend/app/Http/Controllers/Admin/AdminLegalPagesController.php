<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use Illuminate\Http\Request;

class AdminLegalPagesController extends Controller
{
    public function index()
    {
        $pages = LegalPage::orderBy('id')->get();
        return view('admin.legal_pages.index', compact('pages'));
    }

    public function edit(LegalPage $page)
    {
        return view('admin.legal_pages.edit', compact('page'));
    }

    public function update(Request $request, LegalPage $page)
    {
        $data = $request->validate([
            'title_en'   => 'required|string|max:255',
            'title_so'   => 'required|string|max:255',
            'content_en' => 'required|string',
            'content_so' => 'required|string',
            'is_published' => 'boolean',
        ]);

        $data['is_published'] = $request->boolean('is_published', true);
        $page->update($data);

        return redirect()->route('admin.legal-pages.index')
            ->with('success', 'Page updated successfully.');
    }
}
