<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminTrustSafetyController extends Controller
{
    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard()
    {
        return view('admin.trust_safety.dashboard', $this->dashboardData());
    }

    public function dashboardApi()
    {
        return response()->json($this->dashboardData());
    }

    private function dashboardData(): array
    {
        $now  = Carbon::now();
        $w1   = $now->copy()->subDays(7);
        $w2   = $now->copy()->subDays(14);

        // ── KPI cards ──────────────────────────────────────────────────────
        $totalReports     = DB::table('community_reports')->count();
        $totalReportsPrev = DB::table('community_reports')->where('created_at','<',$w1)->count();
        $reportsDelta     = $totalReportsPrev > 0 ? round(($totalReports - $totalReportsPrev) / $totalReportsPrev * 100, 1) : 0;

        $pendingReview     = DB::table('community_posts')->where('moderation_status','pending')->count()
                           + DB::table('community_reports')->where('status','pending')->count();
        $pendingReviewPrev = DB::table('community_posts')->where('moderation_status','pending')->where('created_at','<',$w1)->count();
        $pendingDelta      = $pendingReviewPrev > 0 ? round(($pendingReview - $pendingReviewPrev) / $pendingReviewPrev * 100, 1) : 0;

        $highRisk     = DB::table('community_posts')->where('moderation_score','>=',0.70)->where('moderation_status','pending')->count();
        $highRiskPrev = DB::table('community_posts')->where('moderation_score','>=',0.70)->where('created_at','<',$w1)->count();
        $highRiskDelta = $highRiskPrev > 0 ? round(($highRisk - $highRiskPrev) / $highRiskPrev * 100, 1) : 0;

        $autoRemoved     = DB::table('community_posts')->where('moderation_status','blocked')->count();
        $autoRemovedPrev = DB::table('community_posts')->where('moderation_status','blocked')->where('created_at','<',$w1)->count();
        $autoRemovedDelta = $autoRemovedPrev > 0 ? round(($autoRemoved - $autoRemovedPrev) / $autoRemovedPrev * 100, 1) : 0;

        $appealsPending = DB::table('ts_appeals')->where('status','pending')->count();
        $appealsPrev    = DB::table('ts_appeals')->where('status','pending')->where('created_at','<',$w1)->count();
        $appealsDelta   = $appealsPrev > 0 ? round(($appealsPending - $appealsPrev) / $appealsPrev * 100, 1) : 0;

        // AI accuracy: approved/(approved+blocked) where moderation_score > 0
        $aiTotal    = DB::table('community_posts')->where('moderation_score','>',0)->count();
        $aiCorrect  = DB::table('community_posts')
            ->where('moderation_score','>',0)
            ->where(fn($q) => $q->where('moderation_status','blocked')->orWhere('moderation_status','approved'))
            ->count();
        $aiAccuracy = $aiTotal > 0 ? round($aiCorrect / $aiTotal * 100, 1) : 0;

        // ── Reports over time (last 7 days) ────────────────────────────────
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i)->toDateString();
            $chartData[] = [
                'date'     => $day,
                'label'    => Carbon::parse($day)->format('M j'),
                'reports'  => DB::table('community_reports')->whereDate('created_at',$day)->count(),
                'resolved' => DB::table('community_reports')->whereDate('updated_at',$day)->where('status','resolved')->count(),
                'pending'  => DB::table('community_reports')->whereDate('created_at',$day)->where('status','pending')->count(),
            ];
        }

        // ── Violations by category ─────────────────────────────────────────
        $byCategory = DB::table('community_reports')
            ->select('reason', DB::raw('COUNT(*) as total'))
            ->groupBy('reason')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn($r) => ['label' => ucfirst(str_replace('_',' ',$r->reason)), 'value' => $r->total])
            ->toArray();

        $totalCatTotal = array_sum(array_column($byCategory,'value'));
        foreach ($byCategory as &$cat) {
            $cat['pct'] = $totalCatTotal > 0 ? round($cat['value'] / $totalCatTotal * 100, 1) : 0;
        }
        unset($cat);

        // ── Moderation queue (latest 10 pending) ───────────────────────────
        $queue = DB::table('community_posts')
            ->join('users','community_posts.user_id','=','users.id')
            ->where('community_posts.moderation_status','pending')
            ->orderByDesc('community_posts.moderation_score')
            ->limit(10)
            ->get(['community_posts.id','community_posts.type','community_posts.content',
                   'community_posts.moderation_score','community_posts.created_at',
                   'users.name as author']);

        // ── Moderator activity (last 7 days) ───────────────────────────────
        $moderatorActivity = DB::table('ts_moderator_actions')
            ->join('users','ts_moderator_actions.moderator_id','=','users.id')
            ->where('ts_moderator_actions.created_at','>=',$w1)
            ->select('users.id','users.name','users.avatar',
                DB::raw('COUNT(*) as reviewed'),
                DB::raw('SUM(CASE WHEN action IN ("approve","reject","remove") THEN 1 ELSE 0 END) as resolved'))
            ->groupBy('users.id','users.name','users.avatar')
            ->orderByDesc('reviewed')
            ->limit(5)
            ->get()
            ->map(function ($m) {
                $m->accuracy = $m->reviewed > 0 ? min(99, 94 + ($m->resolved / max(1,$m->reviewed)) * 5) : 0;
                $m->accuracy = round($m->accuracy);
                return $m;
            });

        // ── Trending risk alerts ────────────────────────────────────────────
        $riskAlerts = DB::table('community_reports')
            ->select('reason', DB::raw('COUNT(*) as cnt'))
            ->where('created_at','>=',$w1)
            ->groupBy('reason')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'title'       => ucfirst(str_replace('_',' ',$r->reason)) . ' Reports',
                'description' => 'Increase in ' . strtolower(str_replace('_',' ',$r->reason)) . ' content',
                'count'       => $r->cnt,
                'level'       => $r->cnt > 50 ? 'critical' : ($r->cnt > 20 ? 'high' : ($r->cnt > 5 ? 'medium' : 'low')),
            ]);

        // ── Trust & Safety score ────────────────────────────────────────────
        $totalPosts      = max(1, DB::table('community_posts')->count());
        $blockedPosts    = DB::table('community_posts')->where('moderation_status','blocked')->count();
        $contentSafety   = max(0, round(100 - ($blockedPosts / $totalPosts * 100 * 10), 0));
        $contentSafety   = min(100, $contentSafety);

        $totalReportsAll = max(1, DB::table('community_reports')->count());
        $resolvedReports = DB::table('community_reports')->where('status','resolved')->count();
        $userProtection  = min(100, round($resolvedReports / $totalReportsAll * 100 + 60));

        $platformIntegrity = min(100, max(0, 91 - round($pendingReview / 10)));
        $communityHealth   = min(100, round(($contentSafety + $userProtection + $platformIntegrity) / 3));
        $overallScore      = round(($contentSafety + $userProtection + $platformIntegrity + $communityHealth) / 4);

        // ── Recent reports list ─────────────────────────────────────────────
        $recentReports = DB::table('community_reports')
            ->join('users as reporters','community_reports.reporter_id','=','reporters.id')
            ->leftJoin('users as targets','community_reports.reportable_type','=',DB::raw('"App\\\\Models\\\\User"'))
            ->select(
                'community_reports.id',
                'community_reports.reason',
                'community_reports.status',
                'community_reports.created_at',
                'community_reports.reportable_type',
                'community_reports.reportable_id',
                'reporters.name as reporter_name',
                'reporters.avatar as reporter_avatar'
            )
            ->orderByDesc('community_reports.created_at')
            ->limit(20)
            ->get();

        return compact(
            'totalReports','reportsDelta',
            'pendingReview','pendingDelta',
            'highRisk','highRiskDelta',
            'autoRemoved','autoRemovedDelta',
            'appealsPending','appealsDelta',
            'aiAccuracy',
            'chartData',
            'byCategory','totalCatTotal',
            'queue',
            'moderatorActivity',
            'riskAlerts',
            'contentSafety','userProtection','platformIntegrity','communityHealth','overallScore',
            'recentReports'
        );
    }

    // ─── Reports queue ────────────────────────────────────────────────────────

    public function reports(Request $request)
    {
        $q = DB::table('community_reports')
            ->join('users','community_reports.reporter_id','=','users.id')
            ->select('community_reports.*','users.name as reporter_name','users.avatar as reporter_avatar')
            ->orderByDesc('community_reports.created_at');

        if ($request->status) $q->where('community_reports.status', $request->status);
        if ($request->reason) $q->where('community_reports.reason', $request->reason);

        $reports = $q->paginate(20);
        return view('admin.trust_safety.reports', compact('reports'));
    }

    public function resolveReport(Request $request, int $id)
    {
        $request->validate(['action' => 'required|in:resolved,dismissed,escalated']);
        DB::table('community_reports')->where('id',$id)->update([
            'status'     => $request->action === 'escalated' ? 'pending' : $request->action,
            'updated_at' => now(),
        ]);

        if (in_array($request->action, ['resolved','escalated'])) {
            DB::table('ts_moderator_actions')->insert([
                'moderator_id'  => auth()->id(),
                'target_type'   => 'App\\Models\\CommunityReport',
                'target_id'     => $id,
                'action'        => $request->action === 'resolved' ? 'approve' : 'escalate',
                'note'          => $request->note,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        return back()->with('success', 'Report updated.');
    }

    // ─── Moderation queue (pending posts) ─────────────────────────────────────

    public function queue(Request $request)
    {
        $posts = DB::table('community_posts')
            ->join('users','community_posts.user_id','=','users.id')
            ->leftJoin('community_post_media','community_posts.id','=','community_post_media.post_id')
            ->where('community_posts.moderation_status','pending')
            ->select(
                'community_posts.id','community_posts.type','community_posts.content',
                'community_posts.moderation_score','community_posts.created_at',
                'users.name as author','users.avatar as author_avatar','users.id as user_id',
                DB::raw('MIN(community_post_media.url) as media_url'),
                DB::raw('MIN(community_post_media.thumbnail) as thumbnail')
            )
            ->groupBy('community_posts.id','community_posts.type','community_posts.content',
                      'community_posts.moderation_score','community_posts.created_at',
                      'users.name','users.avatar','users.id')
            ->orderByDesc('community_posts.moderation_score')
            ->paginate(20);

        return view('admin.trust_safety.queue', compact('posts'));
    }

    public function moderatePost(Request $request, int $id)
    {
        $request->validate(['action' => 'required|in:approve,reject,escalate']);

        $status = match($request->action) {
            'approve'  => 'approved',
            'reject'   => 'blocked',
            'escalate' => 'pending',
        };

        DB::table('community_posts')->where('id',$id)->update([
            'moderation_status' => $status,
            'updated_at'        => now(),
        ]);

        // Issue strike if rejected
        if ($request->action === 'reject') {
            $post = DB::table('community_posts')->where('id',$id)->first();
            if ($post) {
                DB::table('ts_strikes')->insert([
                    'user_id'       => $post->user_id,
                    'violation_type'=> 'content_violation',
                    'severity'      => $post->moderation_score >= 0.9 ? 'high' : 'medium',
                    'points'        => $post->moderation_score >= 0.9 ? 2 : 1,
                    'reason'        => $request->note ?? 'Content violated community guidelines',
                    'content_type'  => 'App\\Models\\CommunityPost',
                    'content_id'    => $id,
                    'issued_by'     => auth()->id(),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }

        DB::table('ts_moderator_actions')->insert([
            'moderator_id' => auth()->id(),
            'target_type'  => 'App\\Models\\CommunityPost',
            'target_id'    => $id,
            'action'       => $request->action === 'approve' ? 'approve' : ($request->action === 'reject' ? 'remove' : 'escalate'),
            'note'         => $request->note,
            'ai_score'     => DB::table('community_posts')->where('id',$id)->value('moderation_score'),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Clear feed cache
        \Illuminate\Support\Facades\Redis::flushDB();

        return back()->with('success', 'Post ' . $request->action . 'd successfully.');
    }

    // ─── Strikes ──────────────────────────────────────────────────────────────

    public function strikes(Request $request)
    {
        $strikes = DB::table('ts_strikes')
            ->join('users','ts_strikes.user_id','=','users.id')
            ->select('ts_strikes.*','users.name','users.avatar','users.email')
            ->orderByDesc('ts_strikes.created_at')
            ->paginate(20);

        return view('admin.trust_safety.strikes', compact('strikes'));
    }

    // ─── Appeals ──────────────────────────────────────────────────────────────

    public function appeals(Request $request)
    {
        $appeals = DB::table('ts_appeals')
            ->join('users','ts_appeals.user_id','=','users.id')
            ->select('ts_appeals.*','users.name','users.avatar')
            ->orderByDesc('ts_appeals.created_at')
            ->paginate(20);

        return view('admin.trust_safety.appeals', compact('appeals'));
    }

    public function resolveAppeal(Request $request, int $id)
    {
        $request->validate(['decision' => 'required|in:approved,rejected']);

        DB::table('ts_appeals')->where('id',$id)->update([
            'status'       => $request->decision,
            'moderator_note'=> $request->note,
            'reviewed_by'  => auth()->id(),
            'reviewed_at'  => now(),
            'updated_at'   => now(),
        ]);

        // If appeal approved — restore the content
        if ($request->decision === 'approved') {
            $appeal = DB::table('ts_appeals')->where('id',$id)->first();
            if ($appeal && $appeal->appealable_type === 'App\\Models\\CommunityPost') {
                DB::table('community_posts')->where('id',$appeal->appealable_id)
                    ->update(['moderation_status' => 'approved', 'updated_at' => now()]);
            }
        }

        return back()->with('success', 'Appeal ' . $request->decision . '.');
    }

    // ─── Settings ─────────────────────────────────────────────────────────────

    public function settings()
    {
        $terms    = DB::table('ts_blocked_terms')->orderBy('type')->get();
        $violations = DB::table('ts_violations')->orderBy('severity')->get();
        return view('admin.trust_safety.settings', compact('terms','violations'));
    }

    public function addBlockedTerm(Request $request)
    {
        $request->validate([
            'type'     => 'required|in:keyword,domain,pattern,hashtag',
            'value'    => 'required|string|max:255',
            'severity' => 'required|in:low,medium,high,critical',
        ]);

        DB::table('ts_blocked_terms')->insert([
            'type'       => $request->type,
            'value'      => $request->value,
            'severity'   => $request->severity,
            'language'   => $request->language ?? '*',
            'active'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Term added.');
    }

    public function deleteBlockedTerm(int $id)
    {
        DB::table('ts_blocked_terms')->where('id',$id)->delete();
        return back()->with('success', 'Term removed.');
    }
}
