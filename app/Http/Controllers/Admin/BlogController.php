<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SiteController;
use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Support\Content;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Blog: write, edit, schedule and publish articles for /blog. Drafts stay hidden; a published
 * post with a future date goes live on that date.
 */
class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['published', 'scheduled', 'draft'], true) ? $request->query('status') : null;
        $all = BlogPost::orderByDesc('published_at')->orderByDesc('id')->get();
        $state = fn (BlogPost $p) => $p->status !== 'published' ? 'draft' : ($p->isLive() ? 'published' : 'scheduled');

        return view('admin.blog.index', [
            'posts' => $status ? $all->filter(fn ($p) => $state($p) === $status) : $all,
            'counts' => $all->countBy($state),
            'total' => $all->count(),
            'status' => $status,
            'state' => $state,
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.edit', ['post' => new BlogPost(['status' => 'draft']), 'features' => $this->features()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $post = BlogPost::create($this->apply($request, $data, new BlogPost));
        AuditLog::record('admin.blog_post_created', null, ['post' => $post->slug, 'status' => $post->status, 'by' => $request->user()->email]);

        return redirect()->route('admin.blog.edit', $post->id)->with('status', $this->message($post));
    }

    public function edit(int $post): View
    {
        return view('admin.blog.edit', ['post' => BlogPost::findOrFail($post), 'features' => $this->features()]);
    }

    public function update(Request $request, int $post): RedirectResponse
    {
        $post = BlogPost::findOrFail($post);
        $data = $this->validated($request, $post);
        $post->update($this->apply($request, $data, $post));
        AuditLog::record('admin.blog_post_saved', null, ['post' => $post->slug, 'status' => $post->status, 'by' => $request->user()->email]);

        return redirect()->route('admin.blog.edit', $post->id)->with('status', $this->message($post));
    }

    /** The article as it will look on the website, from the unsaved form. */
    public function preview(Request $request): View
    {
        $post = new BlogPost([
            'title' => (string) $request->input('title', 'Untitled'),
            'category' => $request->input('category'),
            'excerpt' => $request->input('excerpt'),
            'body' => (string) $request->input('body', ''),
            'feature' => array_key_exists((string) $request->input('feature'), Content::features()) ? $request->input('feature') : null,
        ]);
        $post->published_at = $request->date('published_at') ?? now();

        return SiteController::postView($post, true);
    }

    public function destroy(Request $request, int $post): RedirectResponse
    {
        $post = BlogPost::findOrFail($post);
        $post->delete();
        AuditLog::record('admin.blog_post_deleted', null, ['post' => $post->slug, 'by' => $request->user()->email]);

        return redirect()->route('admin.blog')->with('status', "\"{$post->title}\" was deleted.");
    }

    private function validated(Request $request, ?BlogPost $post = null): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('title')))]);

        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', 'max:120', Rule::unique('blog_posts', 'slug')->ignore($post?->id)],
            'category' => ['nullable', 'string', 'max:40'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:200000'],
            'feature' => ['nullable', Rule::in(array_keys(Content::features()))],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'published_at' => ['nullable', 'date'],
        ], ['slug.unique' => 'Another article already uses this address.']);
    }

    /** Fields to save; the button pressed decides the status (publish, unpublish or keep). */
    private function apply(Request $request, array $data, BlogPost $post): array
    {
        $status = match ($request->input('intent')) {
            'publish' => 'published',
            'unpublish' => 'draft',
            default => $post->status ?: 'draft', // "Save" keeps the current status
        };
        $publishedAt = $request->filled('published_at') ? $request->date('published_at') : $post->published_at;
        if ($status === 'published' && ! $publishedAt) {
            $publishedAt = now();
        }

        return array_merge($data, [
            'status' => $status,
            'published_at' => $publishedAt,
            'featured' => $request->boolean('featured'),
            'updated_by' => $request->user()->email,
        ]);
    }

    private function message(BlogPost $post): string
    {
        return match (true) {
            $post->isLive() => 'Saved. The article is live on the blog.',
            $post->status === 'published' => 'Saved. The article goes live on '.$post->published_at->toFormattedDateString().'.',
            default => 'Saved as a draft. It stays hidden until you publish it.',
        };
    }

    private function features(): array
    {
        return collect(Content::features())->map(fn ($f, $slug) => $f['name'] ?? Str::headline($slug))->all();
    }
}
