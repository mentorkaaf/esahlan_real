<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrDepartment;
use App\Models\HR\HrJobPosting;
use App\Models\HR\HrPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrJobPostingController extends Controller
{
    public function index()
    {
        $postings = HrJobPosting::with(['department', 'position'])
            ->withCount('applicants')
            ->orderByDesc('created_at')
            ->get();

        return view('hr.recruitment.postings', compact('postings'));
    }

    public function create()
    {
        $departments = HrDepartment::orderBy('name')->get();
        $positions   = HrPosition::orderBy('title')->get();

        return view('hr.recruitment.posting_form', compact('departments', 'positions'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = Auth::guard('hr')->id();

        if ($data['status'] === 'open' && !isset($data['posted_at'])) {
            $data['posted_at'] = now();
        }

        HrJobPosting::create($data);

        return redirect()->route('hr.recruitment.postings.index')
            ->with('success', "Job posting '{$data['title']}' created.");
    }

    public function show(HrJobPosting $posting)
    {
        $posting->load(['department', 'position', 'applicants']);

        $byStage = collect(\App\Models\HR\HrApplicant::STAGES)
            ->mapWithKeys(fn($s) => [$s => $posting->applicants->where('stage', $s)->values()]);

        return view('hr.recruitment.kanban', compact('posting', 'byStage'));
    }

    public function edit(HrJobPosting $posting)
    {
        $departments = HrDepartment::orderBy('name')->get();
        $positions   = HrPosition::orderBy('title')->get();

        return view('hr.recruitment.posting_form', compact('posting', 'departments', 'positions'));
    }

    public function update(Request $request, HrJobPosting $posting)
    {
        $data = $this->validated($request);

        if ($data['status'] === 'open' && !$posting->posted_at) {
            $data['posted_at'] = now();
        }

        $posting->update($data);

        return redirect()->route('hr.recruitment.postings.index')
            ->with('success', 'Posting updated.');
    }

    public function destroy(HrJobPosting $posting)
    {
        $posting->delete();
        return redirect()->route('hr.recruitment.postings.index')->with('success', 'Posting deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'        => 'required|string|max:150',
            'department_id'=> 'nullable|exists:hr_departments,id',
            'position_id'  => 'nullable|exists:hr_positions,id',
            'description'  => 'nullable|string',
            'requirements' => 'nullable|string',
            'location'     => 'nullable|string|max:100',
            'type'         => 'required|in:full_time,part_time,contract',
            'openings'     => 'required|integer|min:1|max:999',
            'status'       => 'required|in:draft,open,paused,closed',
            'closes_at'    => 'nullable|date',
        ]);
    }
}
