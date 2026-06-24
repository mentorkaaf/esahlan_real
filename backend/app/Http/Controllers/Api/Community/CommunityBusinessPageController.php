<?php
namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityBusinessPage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Api\Community\CommunityFeedController;

class CommunityBusinessPageController extends Controller
{
    public function index(Request $request)
    {
        $pages = CommunityBusinessPage::where('is_active', true)
            ->withCount('followers')
            ->orderByDesc('followers_count')
            ->paginate(20);

        $userId = auth()->id();
        $data = $pages->map(fn($p) => $this->transform($p, $userId));

        return response()->json(['status' => 'success', 'data' => $data, 'meta' => [
            'current_page' => $pages->currentPage(), 'last_page' => $pages->lastPage(),
        ]]);
    }

    public function myPages()
    {
        $pages = CommunityBusinessPage::where('user_id', auth()->id())->withCount('followers')->latest()->get();
        return response()->json(['status' => 'success', 'data' => $pages->map(fn($p) => $this->transform($p, auth()->id()))]);
    }

    public function show($id)
    {
        $page = CommunityBusinessPage::withCount('followers')->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $this->transform($page, auth()->id())]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'category'    => 'nullable|string|max:50',
            'phone'       => 'nullable|string|max:20',
            'email'       => 'nullable|email|max:100',
            'website'     => 'nullable|string|max:200',
            'address'     => 'nullable|string|max:200',
            'avatar'      => 'nullable|image|max:2048',
            'cover_photo' => 'nullable|image|max:5120',
        ]);

        $page = CommunityBusinessPage::create([
            'user_id'     => auth()->id(),
            'name'        => $data['name'],
            'slug'        => Str::slug($data['name']) . '-' . Str::random(5),
            'description' => $data['description'] ?? null,
            'category'    => $data['category'] ?? null,
            'phone'       => $data['phone'] ?? null,
            'email'       => $data['email'] ?? null,
            'website'     => $data['website'] ?? null,
            'address'     => $data['address'] ?? null,
        ]);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('business-pages', 'public');
            $page->update(['avatar' => url('/api/v1/img/' . $path)]);
        }
        if ($request->hasFile('cover_photo')) {
            $path = $request->file('cover_photo')->store('business-pages', 'public');
            $page->update(['cover_photo' => url('/api/v1/img/' . $path)]);
        }

        // Mark user profile as business
        $profile = \App\Models\CommunityProfile::firstOrCreate(['user_id' => auth()->id()]);
        $profile->update(['is_business' => true, 'business_category' => $data['category'] ?? $profile->business_category]);

        return response()->json(['status' => 'success', 'data' => $this->transform($page->fresh(), auth()->id())], 201);
    }

    public function update(Request $request, $id)
    {
        $page = CommunityBusinessPage::where('user_id', auth()->id())->findOrFail($id);
        $data = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'category'    => 'nullable|string|max:50',
            'phone'       => 'nullable|string|max:20',
            'email'       => 'nullable|email|max:100',
            'website'     => 'nullable|string|max:200',
            'address'     => 'nullable|string|max:200',
        ]);

        $page->update($data);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('business-pages', 'public');
            $page->update(['avatar' => url('/api/v1/img/' . $path)]);
        }
        if ($request->hasFile('cover_photo')) {
            $path = $request->file('cover_photo')->store('business-pages', 'public');
            $page->update(['cover_photo' => url('/api/v1/img/' . $path)]);
        }

        return response()->json(['status' => 'success', 'data' => $this->transform($page->fresh(), auth()->id())]);
    }

    public function toggleFollow($id)
    {
        $page = CommunityBusinessPage::findOrFail($id);
        $userId = auth()->id();
        $exists = $page->followers()->where('user_id', $userId)->exists();

        if ($exists) {
            $page->followers()->detach($userId);
            $page->decrement('followers_count');
        } else {
            $page->followers()->attach($userId);
            $page->increment('followers_count');
        }

        return response()->json(['status' => 'success', 'is_following' => !$exists]);
    }

    public function posts($id)
    {
        $page = CommunityBusinessPage::findOrFail($id);
        $userId = auth()->id();

        $posts = \App\Models\CommunityPost::with(['user.communityProfile', 'media', 'userReaction'])
            ->where('user_id', $page->user_id)
            ->whereNull('group_id')
            ->where('privacy', '!=', 'private')
            ->latest()
            ->paginate(15);

        $feedCtrl = new CommunityFeedController();
        $data = $posts->map(fn($p) => $feedCtrl->transformPost($p, $userId))->toArray();

        return response()->json(['status' => 'success', 'data' => $data, 'meta' => [
            'current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage(),
        ]]);
    }

    private function transform($page, $userId): array
    {
        return [
            'id'              => $page->id,
            'name'            => $page->name,
            'slug'            => $page->slug,
            'description'     => $page->description,
            'avatar'          => cdn_url($page->avatar),
            'cover_photo'     => cdn_url($page->cover_photo),
            'category'        => $page->category,
            'phone'           => $page->phone,
            'email'           => $page->email,
            'website'         => $page->website,
            'address'         => $page->address,
            'followers_count' => $page->followers_count,
            'posts_count'     => $page->posts_count,
            'is_verified'     => $page->is_verified,
            'is_owner'        => $page->user_id === $userId,
            'is_following'    => $page->followers()->where('user_id', $userId)->exists(),
            'owner'           => ['id' => $page->user_id, 'name' => $page->user?->name],
            'created_at'      => $page->created_at,
        ];
    }
}
