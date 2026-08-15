<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrApplicant;
use App\Models\HR\HrJobPosting;
use App\Models\HR\HrStaff;
use App\Services\HR\RecruitmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HrApplicantController extends Controller
{
    /** Applicant profile */
    public function show(HrApplicant $applicant)
    {
        $applicant->load(['jobPosting.department', 'interviews.interviewer', 'employee']);
        $staff = HrStaff::where('is_active', true)->orderBy('name')->get();

        return view('hr.recruitment.applicant', compact('applicant', 'staff'));
    }

    /** Update notes/rating */
    public function update(Request $request, HrApplicant $applicant)
    {
        $data = $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'notes'  => 'nullable|string|max:2000',
        ]);

        $applicant->update($data);

        return back()->with('success', 'Applicant updated.');
    }

    /** Move to a stage */
    public function moveStage(Request $request, HrApplicant $applicant)
    {
        $data = $request->validate([
            'stage' => 'required|in:applied,screening,interview,offer,hired,rejected',
        ]);

        if ($data['stage'] === 'hired') {
            $employee = RecruitmentService::hire($applicant);
            return redirect()->route('hr.employees.show', $employee)
                ->with('success', "Applicant hired — employee record #{$employee->employee_no} created. Complete the profile.");
        }

        RecruitmentService::moveStage($applicant, $data['stage']);

        return back()->with('success', "Moved to stage: {$data['stage']}.");
    }

    /** Schedule interview */
    public function scheduleInterview(Request $request, HrApplicant $applicant)
    {
        $data = $request->validate([
            'interviewer_id'  => 'nullable|exists:hr_staff,id',
            'scheduled_at'    => 'required|date|after:now',
            'mode'            => 'required|in:in_person,video,phone',
            'location_or_link'=> 'nullable|string|max:200',
        ]);

        $data['applicant_id'] = $applicant->id;
        $data['created_by']   = Auth::guard('hr')->id();

        \App\Models\HR\HrInterview::create($data);

        // Auto-move to interview stage if still in applied/screening
        if (in_array($applicant->stage, ['applied', 'screening'])) {
            RecruitmentService::moveStage($applicant, 'interview');
        }

        return back()->with('success', 'Interview scheduled.');
    }

    /** Submit interview feedback */
    public function interviewFeedback(Request $request, \App\Models\HR\HrInterview $interview)
    {
        $data = $request->validate([
            'feedback' => 'required|string|max:2000',
            'result'   => 'required|in:passed,failed,on_hold',
        ]);

        $interview->update(array_merge($data, ['status' => 'completed']));

        return back()->with('success', 'Interview feedback saved.');
    }

    /** Download CV */
    public function downloadCv(HrApplicant $applicant)
    {
        if (!$applicant->cv_path || !Storage::exists($applicant->cv_path)) {
            abort(404, 'CV not found.');
        }

        return Storage::download($applicant->cv_path, $applicant->name . '_CV.pdf');
    }
}
