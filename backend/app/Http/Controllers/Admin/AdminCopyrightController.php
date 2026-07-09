<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCopyrightController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('copyright_claims')
            ->leftJoin('users as claimants', 'copyright_claims.claimant_id', '=', 'claimants.id')
            ->select('copyright_claims.*', 'claimants.name as claimant_user_name', 'claimants.avatar as claimant_avatar')
            ->orderByDesc('copyright_claims.created_at');

        if ($request->status) $q->where('copyright_claims.status', $request->status);

        $claims = $q->paginate(20);

        $stats = [
            'total'        => DB::table('copyright_claims')->count(),
            'pending'      => DB::table('copyright_claims')->where('status', 'pending')->count(),
            'under_review' => DB::table('copyright_claims')->where('status', 'under_review')->count(),
            'upheld'       => DB::table('copyright_claims')->where('status', 'upheld')->count(),
            'dismissed'    => DB::table('copyright_claims')->where('status', 'dismissed')->count(),
            'counter'      => DB::table('copyright_claims')->where('status', 'counter_notice')->count(),
        ];

        return view('admin.trust_safety.copyright', compact('claims', 'stats'));
    }

    public function show(int $id)
    {
        $claim = DB::table('copyright_claims')
            ->leftJoin('users as claimants', 'copyright_claims.claimant_id', '=', 'claimants.id')
            ->where('copyright_claims.id', $id)
            ->select('copyright_claims.*', 'claimants.name as claimant_user_name', 'claimants.email as claimant_user_email')
            ->first();

        if (!$claim) abort(404);

        $counter = DB::table('copyright_counter_notices')
            ->leftJoin('users', 'copyright_counter_notices.user_id', '=', 'users.id')
            ->where('claim_id', $id)
            ->select('copyright_counter_notices.*', 'users.name as user_name')
            ->first();

        $content = $this->getContent($claim->reported_type, $claim->reported_id);

        return view('admin.trust_safety.copyright_show', compact('claim', 'counter', 'content'));
    }

    public function resolve(Request $request, int $id)
    {
        $request->validate([
            'decision'   => 'required|in:under_review,upheld,dismissed',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $claim = DB::table('copyright_claims')->where('id', $id)->first();
        if (!$claim) abort(404);

        DB::table('copyright_claims')->where('id', $id)->update([
            'status'      => $request->decision,
            'admin_note'  => $request->admin_note,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'updated_at'  => now(),
        ]);

        // Upheld → disable the content
        if ($request->decision === 'upheld') {
            $this->disableContent($claim->reported_type, $claim->reported_id);
            DB::table('copyright_claims')->where('id', $id)->update(['content_disabled' => true]);
        }

        // Dismissed → re-enable content if it was disabled
        if ($request->decision === 'dismissed') {
            $this->enableContent($claim->reported_type, $claim->reported_id);
            DB::table('copyright_claims')->where('id', $id)->update(['content_disabled' => false]);
        }

        DB::table('ts_moderator_actions')->insert([
            'moderator_id' => auth()->id(),
            'target_type'  => 'App\\Models\\CopyrightClaim',
            'target_id'    => $id,
            'action'       => 'copyright_' . $request->decision,
            'note'         => $request->admin_note,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return back()->with('success', 'Claim ' . $request->decision . ' successfully.');
    }

    public function resolveCounter(Request $request, int $counterId)
    {
        $request->validate(['decision' => 'required|in:accepted,rejected']);

        $counter = DB::table('copyright_counter_notices')->where('id', $counterId)->first();
        if (!$counter) abort(404);

        DB::table('copyright_counter_notices')->where('id', $counterId)->update([
            'status'     => $request->decision,
            'updated_at' => now(),
        ]);

        $claim = DB::table('copyright_claims')->where('id', $counter->claim_id)->first();
        if ($claim) {
            if ($request->decision === 'accepted') {
                // Counter accepted → restore content, dismiss claim
                $this->enableContent($claim->reported_type, $claim->reported_id);
                DB::table('copyright_claims')->where('id', $counter->claim_id)->update([
                    'status' => 'dismissed', 'content_disabled' => false, 'updated_at' => now(),
                ]);
            } else {
                // Counter rejected → keep content disabled, uphold claim
                DB::table('copyright_claims')->where('id', $counter->claim_id)->update([
                    'status' => 'upheld', 'updated_at' => now(),
                ]);
            }
        }

        return back()->with('success', 'Counter-notice ' . $request->decision . '.');
    }

    private function getContent(string $type, int $id): ?object
    {
        return match($type) {
            'post'  => DB::table('community_posts')
                ->leftJoin('users', 'community_posts.user_id', '=', 'users.id')
                ->where('community_posts.id', $id)
                ->select('community_posts.*', 'users.name as author')
                ->first(),
            'story' => DB::table('community_stories')
                ->leftJoin('users', 'community_stories.user_id', '=', 'users.id')
                ->where('community_stories.id', $id)
                ->select('community_stories.*', 'users.name as author')
                ->first(),
            default => null,
        };
    }

    private function disableContent(string $type, int $id): void
    {
        match($type) {
            'post'  => DB::table('community_posts')->where('id', $id)->update(['moderation_status' => 'blocked', 'updated_at' => now()]),
            'story' => DB::table('community_stories')->where('id', $id)->update(['is_active' => false, 'updated_at' => now()]),
            default => null,
        };
    }

    private function enableContent(string $type, int $id): void
    {
        match($type) {
            'post'  => DB::table('community_posts')->where('id', $id)->update(['moderation_status' => 'approved', 'updated_at' => now()]),
            'story' => DB::table('community_stories')->where('id', $id)->update(['is_active' => true, 'updated_at' => now()]),
            default => null,
        };
    }
}
