<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrGoal;
use App\Models\HR\HrPerformanceCycle;
use App\Models\HR\HrReview;
use App\Services\HR\AuditService;
use App\Services\HR\PerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrPerformanceCycleController extends Controller
{
    public function index()
    {
        $cycles = HrPerformanceCycle::withCount(['goals', 'reviews'])
            ->orderByDesc('period_start')
            ->get();

        return view('hr.performance.cycles', compact('cycles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after:period_start',
            'status'       => 'required|in:draft,active,closed',
        ]);
        $data['created_by'] = Auth::guard('hr')->id();

        HrPerformanceCycle::create($data);

        return back()->with('success', "Cycle \"{$data['name']}\" created.");
    }

    public function update(Request $request, HrPerformanceCycle $cycle)
    {
        $data = $request->validate([
            'status' => 'required|in:draft,active,closed',
        ]);
        $cycle->update($data);
        AuditService::log('cycle.status_changed', $cycle);

        return back()->with('success', 'Cycle updated.');
    }

    public function destroy(HrPerformanceCycle $cycle)
    {
        $cycle->delete();
        return back()->with('success', 'Cycle deleted.');
    }

    /** Goals management for a cycle */
    public function goals(HrPerformanceCycle $cycle)
    {
        $employees = HrEmployee::whereIn('status', ['active', 'probation'])
            ->with(['goals' => fn($q) => $q->where('cycle_id', $cycle->id)])
            ->orderBy('first_name')
            ->get();

        return view('hr.performance.goals', compact('cycle', 'employees'));
    }

    /** Store a goal */
    public function storeGoal(Request $request, HrPerformanceCycle $cycle)
    {
        $data = $request->validate([
            'employee_id'   => 'required|exists:hr_employees,id',
            'title'         => 'required|string|max:150',
            'description'   => 'nullable|string|max:500',
            'weight'        => 'required|numeric|min:0|max:100',
            'target_value'  => 'nullable|string|max:100',
        ]);

        if (!PerformanceService::validateWeights($cycle->id, $data['employee_id'])) {
            return back()->with('error', 'Total goal weight for this employee exceeds 100%.');
        }

        $data['cycle_id'] = $cycle->id;
        HrGoal::create($data);

        return back()->with('success', 'Goal added.');
    }

    /** Delete a goal */
    public function destroyGoal(HrGoal $goal)
    {
        $cycleId = $goal->cycle_id;
        $goal->delete();
        return back()->with('success', 'Goal deleted.');
    }

    /** Reviews for a cycle */
    public function reviews(HrPerformanceCycle $cycle)
    {
        $employees = HrEmployee::whereIn('status', ['active', 'probation'])
            ->with([
                'goals'   => fn($q) => $q->where('cycle_id', $cycle->id),
                'reviews' => fn($q) => $q->where('cycle_id', $cycle->id),
            ])
            ->orderBy('first_name')
            ->get();

        $staff = \App\Models\HR\HrStaff::where('is_active', true)->orderBy('name')->get();

        return view('hr.performance.reviews', compact('cycle', 'employees', 'staff'));
    }

    /** Save/update a review */
    public function storeReview(Request $request, HrPerformanceCycle $cycle, HrEmployee $employee)
    {
        $goals = HrGoal::where('cycle_id', $cycle->id)
            ->where('employee_id', $employee->id)
            ->get();

        // Build validation rules for goal scores
        $rules = [
            'reviewer_id'  => 'nullable|exists:hr_staff,id',
            'notes'        => 'nullable|string|max:1000',
            'submit'       => 'nullable|boolean',
            // competency scores
            'competency_communication' => 'nullable|numeric|min:1|max:5',
            'competency_teamwork'      => 'nullable|numeric|min:1|max:5',
            'competency_punctuality'   => 'nullable|numeric|min:1|max:5',
            'competency_initiative'    => 'nullable|numeric|min:1|max:5',
        ];
        foreach ($goals as $goal) {
            $rules["score_{$goal->id}"] = 'nullable|numeric|min:1|max:5';
        }

        $data = $request->validate($rules);

        $scores = [];
        foreach ($goals as $goal) {
            if (isset($data["score_{$goal->id}"])) {
                $scores[$goal->id] = (float) $data["score_{$goal->id}"];
            }
        }

        $compScores = [];
        foreach (['communication', 'teamwork', 'punctuality', 'initiative'] as $comp) {
            if (!empty($data["competency_{$comp}"])) {
                $compScores[$comp] = (float) $data["competency_{$comp}"];
            }
        }

        $isSubmit = $request->boolean('submit');

        $review = HrReview::updateOrCreate(
            ['cycle_id' => $cycle->id, 'employee_id' => $employee->id],
            [
                'reviewer_id'       => $data['reviewer_id'] ?? Auth::guard('hr')->id(),
                'scores'            => $scores,
                'competency_scores' => $compScores,
                'notes'             => $data['notes'] ?? null,
                'status'            => $isSubmit ? 'submitted' : 'draft',
                'submitted_at'      => $isSubmit ? now() : null,
            ]
        );

        PerformanceService::computeAndSaveScore($review);

        return back()->with('success', $isSubmit ? 'Review submitted.' : 'Review saved as draft.');
    }

    /** Cycle ranking report */
    public function report(HrPerformanceCycle $cycle)
    {
        $ranking = PerformanceService::cycleRanking($cycle);

        $deptAverages = $ranking->groupBy('employee.department.name')
            ->map(fn($g) => round($g->avg('overall_score'), 2));

        return view('hr.performance.report', compact('cycle', 'ranking', 'deptAverages'));
    }

    /** Import commission achieved into goals */
    public function importCommissions(HrPerformanceCycle $cycle)
    {
        $count = PerformanceService::importFromCommissions($cycle);

        return back()->with('success', "{$count} goal(s) updated from commissions data.");
    }
}
