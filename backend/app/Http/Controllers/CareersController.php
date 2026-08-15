<?php

namespace App\Http\Controllers;

use App\Models\HR\HrApplicant;
use App\Models\HR\HrJobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CareersController extends Controller
{
    /** Public listing */
    public function index()
    {
        $postings = HrJobPosting::where('status', 'open')
            ->with(['department', 'position'])
            ->orderByDesc('posted_at')
            ->get();

        return view('careers.index', compact('postings'));
    }

    /** Public job detail */
    public function show(HrJobPosting $posting)
    {
        if ($posting->status !== 'open') {
            return redirect()->route('careers.index')
                ->with('error', 'This position is no longer open.');
        }

        return view('careers.show', compact('posting'));
    }

    /** Submit application — rate-limited via middleware */
    public function apply(Request $request, HrJobPosting $posting)
    {
        if ($posting->status !== 'open') {
            return back()->with('error', 'This position is closed.');
        }

        // Honeypot — if filled, silently discard
        if ($request->filled('_trap')) {
            return redirect()->route('careers.show', $posting)
                ->with('success', 'Application submitted!'); // fake success
        }

        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:30',
            'cv'    => 'required|file|mimes:pdf,doc,docx|max:5120',
        ]);

        $cvPath = $request->file('cv')->store('hr/cvs', 'private');

        HrApplicant::create([
            'job_posting_id'  => $posting->id,
            'name'            => $data['name'],
            'email'           => $data['email'],
            'phone'           => $data['phone'] ?? null,
            'cv_path'         => $cvPath,
            'stage'           => 'applied',
            'stage_changed_at'=> now(),
            'source'          => 'careers_page',
        ]);

        return redirect()->route('careers.index')
            ->with('success', 'Your application has been submitted! We will contact you soon.');
    }
}
