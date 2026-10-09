<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** A blog article, managed in Internal Admin → Blog and shown at /blog/{slug}. */
class BlogPost extends Model
{
    public const CATEGORIES = ['CRO', 'AOV', 'Checkout', 'Retention', 'Testing', 'Guides', 'News'];

    protected $fillable = ['slug', 'title', 'category', 'excerpt', 'body', 'feature', 'seo_title', 'seo_description', 'status', 'published_at', 'featured', 'updated_by'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'featured' => 'boolean'];
    }

    /** Live on the website: published, with a publish date that has arrived. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isLive(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function url(): string
    {
        return route('site.blog.post', $this->slug);
    }

    /** About 220 words a minute, rounded up. */
    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->body)) / 220));
    }

    /** The article as HTML (raw HTML in the Markdown is stripped) and a table of contents from its ## headings. */
    public function render(): array
    {
        $html = (string) Str::markdown((string) $this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $toc = [];
        $html = preg_replace_callback('/<h2>(.*?)<\/h2>/s', function ($m) use (&$toc) {
            $text = trim(html_entity_decode(strip_tags($m[1])));
            $id = Str::slug($text) ?: 'section-'.(count($toc) + 1);
            $toc[] = [$id, $text];

            return "<h2 id=\"{$id}\">{$m[1]}</h2>";
        }, $html);
        $html = str_replace(['<table>', '</table>'], ['<div class="legal-table"><table>', '</table></div>'], $html);

        return ['html' => new HtmlString($html), 'toc' => $toc];
    }
}
