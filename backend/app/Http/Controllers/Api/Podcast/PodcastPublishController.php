<?php
namespace App\Http\Controllers\Api\Podcast;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use App\Models\PodcastCategory;
use App\Models\PodcastEpisode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PodcastPublishController extends Controller
{
    // GET /api/v1/podcast/my-shows
    public function myShows(Request $request)
    {
        $shows = Podcast::where('user_id', auth()->id())
            ->withCount(['allEpisodes'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $shows]);
    }

    // POST /api/v1/podcast/shows
    public function createShow(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'category_id' => 'required|exists:podcast_categories,id',
            'language'    => 'nullable|string|max:10',
            'cover_image' => 'nullable|image|max:5120',
            'privacy'     => 'in:public,private',
        ]);

        $slug = $this->uniqueSlug($validated['title']);

        $coverPath = null;
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('podcasts/covers', 'public');
        }

        $podcast = Podcast::create([
            'user_id'     => auth()->id(),
            'category_id' => $validated['category_id'],
            'title'       => $validated['title'],
            'slug'        => $slug,
            'description' => $validated['description'] ?? null,
            'language'    => $validated['language'] ?? 'so',
            'cover_image' => $coverPath,
            'privacy'     => $validated['privacy'] ?? 'public',
            'status'      => 'active',
            'published_at'=> now(),
        ]);

        // Increment category podcast_count
        PodcastCategory::where('id', $validated['category_id'])->increment('podcast_count');

        return response()->json(['status' => 'success', 'podcast' => $podcast], 201);
    }

    // PUT /api/v1/podcast/shows/{id}
    public function updateShow(Request $request, int $id)
    {
        $podcast = Podcast::where('id', $id)->where('user_id', auth()->id())->firstOrFail();

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:200',
            'description' => 'nullable|string|max:5000',
            'category_id' => 'sometimes|exists:podcast_categories,id',
            'cover_image' => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($podcast->cover_image) Storage::disk('public')->delete($podcast->cover_image);
            $validated['cover_image'] = $request->file('cover_image')->store('podcasts/covers', 'public');
        }

        $podcast->update($validated);

        return response()->json(['status' => 'success', 'podcast' => $podcast]);
    }

    // POST /api/v1/podcast/shows/{id}/episodes
    public function publishEpisode(Request $request, int $podcastId)
    {
        $podcast = Podcast::where('id', $podcastId)->where('user_id', auth()->id())->firstOrFail();

        $validated = $request->validate([
            'title'          => 'required|string|max:300',
            'description'    => 'nullable|string|max:5000',
            'audio_file'     => 'required|file|mimes:mp3,m4a,ogg,wav,aac|max:204800', // 200 MB
            'cover_image'    => 'nullable|image|max:5120',
            'episode_number' => 'nullable|integer|min:1',
            'season'         => 'nullable|integer|min:1',
            'episode_type'   => 'in:full,trailer,bonus',
            'is_explicit'    => 'boolean',
            'tags'           => 'nullable|array',
            'tags.*'         => 'string|max:50',
            'publish_now'    => 'boolean',
            'scheduled_at'   => 'nullable|date|after:now',
        ]);

        $slug      = $this->uniqueEpisodeSlug($validated['title']);
        $audioPath = $request->file('audio_file')->store('podcasts/audio', 'public');
        $duration  = $this->getAudioDuration($request->file('audio_file')->getPathname());
        $fileSize  = $request->file('audio_file')->getSize();

        $coverPath = $podcast->cover_image;
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('podcasts/covers', 'public');
        }

        $publishNow = $validated['publish_now'] ?? true;
        $status     = $publishNow ? 'published' : ($validated['scheduled_at'] ? 'scheduled' : 'draft');

        $lastEp = $podcast->allEpisodes()->max('episode_number') ?? 0;

        $episode = PodcastEpisode::create([
            'podcast_id'     => $podcast->id,
            'user_id'        => auth()->id(),
            'title'          => $validated['title'],
            'slug'           => $slug,
            'description'    => $validated['description'] ?? null,
            'audio_url'      => $audioPath,
            'cover_image'    => $coverPath,
            'duration'       => $duration,
            'file_size'      => $fileSize,
            'episode_number' => $validated['episode_number'] ?? $lastEp + 1,
            'season'         => $validated['season'] ?? 1,
            'episode_type'   => $validated['episode_type'] ?? 'full',
            'is_explicit'    => $validated['is_explicit'] ?? false,
            'tags'           => $validated['tags'] ?? null,
            'status'         => $status,
            'published_at'   => $publishNow ? now() : null,
            'scheduled_at'   => $validated['scheduled_at'] ?? null,
        ]);

        if ($publishNow) {
            $podcast->increment('total_episodes');
        }

        return response()->json(['status' => 'success', 'episode' => $episode], 201);
    }

    // POST /api/v1/podcast/episodes  (quick publish — auto-picks or creates user's show)
    public function quickPublish(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:300',
            'description' => 'nullable|string|max:5000',
            'audio'       => 'required|file|mimes:mp3,m4a,ogg,wav,aac|max:204800',
            'cover_image' => 'nullable|image|max:5120',
            'category'    => 'nullable|string|max:100',
            'privacy'     => 'in:public,private',
        ]);

        // Reuse latest show or auto-create one
        $podcast = Podcast::where('user_id', auth()->id())->latest()->first();
        if (!$podcast) {
            $cat = PodcastCategory::where('is_active', true)->first();
            $podcast = Podcast::create([
                'user_id'     => auth()->id(),
                'category_id' => $cat?->id ?? 1,
                'title'       => auth()->user()->name . "'s Podcast",
                'slug'        => $this->uniqueSlug(auth()->user()->name . ' podcast'),
                'description' => null,
                'language'    => 'so',
                'cover_image' => null,
                'privacy'     => $request->input('privacy', 'public'),
                'status'      => 'active',
                'published_at'=> now(),
            ]);
        }

        $slug      = $this->uniqueEpisodeSlug($request->input('title'));
        $audioPath = $request->file('audio')->store('podcasts/audio', 'public');
        $duration  = $this->getAudioDuration($request->file('audio')->getPathname());
        $fileSize  = $request->file('audio')->getSize();

        $coverPath = $podcast->cover_image;
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('podcasts/covers', 'public');
        }

        $lastEp  = $podcast->allEpisodes()->max('episode_number') ?? 0;
        $episode = PodcastEpisode::create([
            'podcast_id'     => $podcast->id,
            'user_id'        => auth()->id(),
            'title'          => $request->input('title'),
            'slug'           => $slug,
            'description'    => $request->input('description'),
            'audio_url'      => $audioPath,
            'cover_image'    => $coverPath,
            'duration'       => $duration,
            'file_size'      => $fileSize,
            'episode_number' => $lastEp + 1,
            'season'         => 1,
            'episode_type'   => 'full',
            'is_explicit'    => false,
            'status'         => 'published',
            'published_at'   => now(),
        ]);

        $podcast->increment('total_episodes');

        return response()->json(['status' => 'success', 'episode' => $episode, 'podcast' => $podcast], 201);
    }

    // DELETE /api/v1/podcast/episodes/{id}
    public function deleteEpisode(int $id)
    {
        $episode = PodcastEpisode::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $episode->delete();
        $episode->podcast?->decrement('total_episodes');

        return response()->json(['status' => 'success']);
    }

    // POST /api/v1/podcast/rss-import
    public function rssImport(Request $request)
    {
        $request->validate(['url' => 'required|url']);

        $rssUrl = $request->input('url');

        try {
            $xml = @simplexml_load_file($rssUrl);
        } catch (\Exception) {
            return response()->json(['status' => 'error', 'message' => 'RSS URL-ka la furin karin'], 422);
        }

        if (!$xml || !isset($xml->channel)) {
            return response()->json(['status' => 'error', 'message' => 'RSS format-ku khalad buu yahay'], 422);
        }

        $channel  = $xml->channel;
        $ns       = $xml->getNamespaces(true);
        $itunes   = $channel->children($ns['itunes'] ?? '');

        $title    = (string)($channel->title ?? 'Untitled Podcast');
        $desc     = (string)($channel->description ?? '');
        $imgUrl   = (string)($channel->image->url ?? ($itunes->image->attributes()['href'] ?? ''));

        $episodes = [];
        foreach ($channel->item as $item) {
            $itemItunes = $item->children($ns['itunes'] ?? '');
            $enclosure  = $item->enclosure ?? null;
            if (!$enclosure) continue;

            $episodes[] = [
                'title'       => (string)$item->title,
                'description' => strip_tags((string)$item->description),
                'audio_url'   => (string)$enclosure->attributes()['url'],
                'duration'    => $this->parseDuration((string)($itemItunes->duration ?? '0')),
                'published_at'=> (string)$item->pubDate,
            ];
        }

        return response()->json([
            'status'   => 'success',
            'preview'  => [
                'title'       => $title,
                'description' => $desc,
                'cover_url'   => $imgUrl,
                'episode_count'=> count($episodes),
                'episodes'    => array_slice($episodes, 0, 5), // preview only
            ],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i    = 1;
        while (Podcast::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    private function uniqueEpisodeSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i    = 1;
        while (PodcastEpisode::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    private function getAudioDuration(string $path): int
    {
        // Fast mp3 header parse — no ffprobe needed for basic files
        try {
            $output = shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($path) . " 2>/dev/null");
            return (int) round((float)trim($output ?? '0'));
        } catch (\Exception) {
            return 0;
        }
    }

    private function parseDuration(string $raw): int
    {
        if (is_numeric($raw)) return (int)$raw;
        $parts = array_reverse(explode(':', $raw));
        $secs  = 0;
        foreach ($parts as $i => $p) {
            $secs += (int)$p * (60 ** $i);
        }
        return $secs;
    }
}
