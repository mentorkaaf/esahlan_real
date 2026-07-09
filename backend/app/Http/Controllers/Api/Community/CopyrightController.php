<?php
namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CopyrightController extends Controller
{
    // POST /community/copyright/claims
    public function submitClaim(Request $request)
    {
        $request->validate([
            'reported_type'    => 'required|in:post,story,comment',
            'reported_id'      => 'required|integer',
            'claimant_name'    => 'required|string|max:255',
            'claimant_email'   => 'required|email|max:255',
            'work_description' => 'required|string|min:20|max:1000',
            'original_url'     => 'nullable|url|max:500',
        ]);

        $typeMap = [
            'post'    => 'App\\Models\\CommunityPost',
            'story'   => 'App\\Models\\CommunityStory',
            'comment' => 'App\\Models\\CommunityComment',
        ];

        // Prevent duplicate pending claim from same user for same content
        $exists = DB::table('copyright_claims')
            ->where('claimant_id', auth()->id())
            ->where('reported_type', $request->reported_type)
            ->where('reported_id', $request->reported_id)
            ->whereIn('status', ['pending', 'under_review'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You already have a pending claim for this content.'], 422);
        }

        $id = DB::table('copyright_claims')->insertGetId([
            'claimant_id'      => auth()->id(),
            'claimant_name'    => $request->claimant_name,
            'claimant_email'   => $request->claimant_email,
            'work_description' => $request->work_description,
            'original_url'     => $request->original_url,
            'reported_type'    => $request->reported_type,
            'reported_id'      => $request->reported_id,
            'status'           => 'pending',
            'content_disabled' => false,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return response()->json([
            'message' => 'Copyright claim submitted. We will review it within 3-5 business days.',
            'claim_id' => $id,
        ], 201);
    }

    // GET /community/my/copyright-claims
    public function myClaims()
    {
        $claims = DB::table('copyright_claims')
            ->where('claimant_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get([
                'id', 'reported_type', 'reported_id', 'work_description',
                'status', 'admin_note', 'content_disabled', 'created_at', 'reviewed_at',
            ])
            ->toArray();

        return response()->json(['data' => $claims]);
    }

    // POST /community/copyright/claims/{id}/counter
    public function submitCounter(Request $request, int $claimId)
    {
        $request->validate([
            'statement'    => 'required|string|min:20|max:2000',
            'jurisdiction' => 'nullable|string|max:100',
        ]);

        $claim = DB::table('copyright_claims')
            ->where('id', $claimId)
            ->first();

        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        // Verify that the counter-notice is from the content owner
        $contentOwner = $this->getContentOwner($claim->reported_type, $claim->reported_id);
        if ($contentOwner !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Only allow counter on upheld claims
        if (!in_array($claim->status, ['upheld', 'under_review', 'pending'])) {
            return response()->json(['message' => 'Counter-notice not allowed for this claim status.'], 422);
        }

        $existing = DB::table('copyright_counter_notices')
            ->where('claim_id', $claimId)
            ->where('user_id', auth()->id())
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'You already submitted a counter-notice for this claim.'], 422);
        }

        DB::table('copyright_counter_notices')->insert([
            'claim_id'     => $claimId,
            'user_id'      => auth()->id(),
            'statement'    => $request->statement,
            'jurisdiction' => $request->jurisdiction,
            'status'       => 'pending',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Update claim to counter_notice status
        DB::table('copyright_claims')->where('id', $claimId)->update([
            'status'     => 'counter_notice',
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Counter-notice submitted successfully. Content will be reviewed again.']);
    }

    // GET /community/copyright/claims/{id}/against-me
    // Shows claims filed against the authenticated user's content
    public function claimsAgainstMe()
    {
        $userId = auth()->id();

        $postIds = DB::table('community_posts')->where('user_id', $userId)->pluck('id');
        $storyIds = DB::table('community_stories')->where('user_id', $userId)->pluck('id');

        $claims = DB::table('copyright_claims')
            ->where(function ($q) use ($postIds, $storyIds) {
                $q->where(function ($q2) use ($postIds) {
                    $q2->where('reported_type', 'post')->whereIn('reported_id', $postIds);
                })->orWhere(function ($q2) use ($storyIds) {
                    $q2->where('reported_type', 'story')->whereIn('reported_id', $storyIds);
                });
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get([
                'id', 'claimant_name', 'reported_type', 'reported_id',
                'work_description', 'status', 'content_disabled',
                'admin_note', 'created_at',
            ])
            ->toArray();

        return response()->json(['data' => $claims]);
    }

    private function getContentOwner(string $type, int $id): ?int
    {
        return match($type) {
            'post'  => DB::table('community_posts')->where('id', $id)->value('user_id'),
            'story' => DB::table('community_stories')->where('id', $id)->value('user_id'),
            default => null,
        };
    }
}
