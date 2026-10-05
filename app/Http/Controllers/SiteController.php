<?php

namespace App\Http\Controllers;

use App\Models\ContactSubmission;
use App\Support\Content;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

class SiteController extends Controller
{
    public const SURFACES = [
        'product' => 'Product page',
        'cart' => 'Cart',
        'checkout' => 'Checkout',
        'thank-you' => 'Thank You',
        'account' => 'Customer Account',
        'automation' => 'Automation',
    ];

    public const TEMPLATE_TYPES = [
        'bundle' => 'Bundles',
        'qty' => 'Quantity & variant bundles',
        'gift' => 'Progressive Gifts',
        'shipping' => 'Checkout shipping',
        'upsell' => 'Upsells',
        'countdown' => 'Countdown',
        'sticky' => 'Sticky ATC',
        'trust' => 'Trust & Reviews',
        'promo' => 'Promotion',
        'thankyou' => 'Thank You',
        'account' => 'Customer Account',
        'flow' => 'Automation',
    ];

    public function home(): View
    {
        $templates = collect(Content::templates());

        return view('site.home', [
            'plans' => \App\Support\Plans::public(),
            'groups' => Content::featureGroups(),
            'solutions' => Content::solutions(),
            'templateTeaser' => $templates->unique('type')->take(8)->values()->all(),
        ]);
    }

    public function features(): View
    {
        return view('site.features', ['groups' => Content::featureGroups()]);
    }

    // Pages merged into Bundles, Progressive Gifts and Cart Upsells.
    private const MOVED = ['free-gift' => 'progressive-gifts', 'free-shipping-bar' => 'progressive-gifts', 'quantity-breaks' => 'bundles', 'upsell-cross-sell' => 'cart-upsells'];

    public function feature(string $slug): View|\Illuminate\Http\RedirectResponse
    {
        if (isset(self::MOVED[$slug])) {
            return redirect()->route('site.feature', self::MOVED[$slug], 301);
        }
        $feature = Content::feature($slug) ?? abort(404);

        // Related: live features first, same group first.
        $siblings = array_filter(Content::features(), fn ($f, $s) => $s !== $slug, ARRAY_FILTER_USE_BOTH);
        $live = array_filter($siblings, fn ($f) => ($f['status'] ?? 'live') === 'live');
        $sameGroup = array_filter($live, fn ($f) => $f['group'] === $feature['group']);
        $related = array_slice($sameGroup + $live + $siblings, 0, 3, true);

        return view('site.feature', [
            'feature' => $feature,
            'templates' => array_values(array_filter(Content::templates(), fn ($t) => $t['feature'] === $slug)),
            'surfaces' => self::SURFACES,
            'related' => $related,
        ]);
    }

    public function solutions(): View
    {
        return view('site.solutions', ['solutions' => Content::solutions()]);
    }

    public function solution(string $slug): View
    {
        $solution = Content::solution($slug) ?? abort(404);

        return view('site.solution', [
            'solution' => $solution,
            'features' => array_intersect_key(Content::features(), array_flip($solution['features'])),
        ]);
    }

    public function how(): View
    {
        return view('site.how');
    }

    public function templates(): View
    {
        return view('site.templates', [
            'templates' => Content::templates(),
            'surfaces' => self::SURFACES,
            'types' => self::TEMPLATE_TYPES,
        ]);
    }

    public function pricing(): View
    {
        return view('site.pricing', ['plans' => \App\Support\Plans::public(), 'pricing' => Content::pricing()]);
    }

    public function resources(): View
    {
        return view('site.resources', [
            'posts' => Content::posts(),
            'helpCategories' => Content::helpCategories(),
            'templateCount' => count(Content::templates()),
        ]);
    }

    public function blog(): View
    {
        return view('site.blog', ['posts' => Content::posts()]);
    }

    /** Documentation guides, linked from the app ("View documentation"). */
    public const DOCS = ['getting-started', 'bundles', 'progressive-gifts', 'storefront-widgets', 'checkout-blocks', 'customer-accounts', 'automation', 'analytics', 'personalization', 'ab-testing', 'developers'];

    public function docsIndex(): View
    {
        return view('site.docs.index', ['guides' => Content::docs()]);
    }

    public function docs(string $slug): View
    {
        // A guide with its own view (A/B testing), or one rendered from resources/content/docs.php.
        if (view()->exists('site.docs.'.$slug)) {
            return view('site.docs.'.$slug);
        }

        return view('site.docs.guide', ['slug' => $slug, 'guide' => Content::docs()[$slug], 'guides' => Content::docs()]);
    }

    public function help(): View
    {
        return view('site.help', ['helpCategories' => Content::helpCategories()]);
    }

    public function contact(): View
    {
        return view('site.contact');
    }

    public function submitContact(Request $request): RedirectResponse
    {
        // Honeypot: bots fill every field.
        if ($request->filled('website')) {
            return back()->with('contact_sent', true);
        }

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|max:190',
            'company' => 'nullable|string|max:160',
            'store_url' => 'nullable|string|max:190',
            'topic' => 'required|in:Sales,Support,Partnership,Other',
            'message' => 'required|string|max:5000',
        ], [
            'email.required' => 'Enter a valid email address.',
            'email.email' => 'Enter a valid email address.',
            'message.required' => 'Please add a message.',
        ]);

        try {
            ContactSubmission::create($data + ['ip' => $request->ip()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('contact_failed', true);
        }

        return back()->with('contact_sent', true);
    }

    public function about(): View
    {
        return view('site.about');
    }

    public function security(): View
    {
        return view('site.security');
    }

    public function privacy(): View
    {
        return $this->legal('privacy');
    }

    public function terms(): View
    {
        return $this->legal('terms');
    }

    public function dpa(): View
    {
        return $this->legal('dpa');
    }

    /** Legal & policy pages, edited in the Internal Admin. */
    public function legalIndex(): View
    {
        return view('site.legal-index', ['pages' => \App\Support\Legal::pages()]);
    }

    public function legal(string $slug): View
    {
        $page = \App\Models\LegalPage::where('slug', $slug)->where('is_published', true)->firstOrFail();
        $effective = $page->effective_at?->toFormattedDateString();

        return view('site.legal', ['page' => $page, 'effective' => $effective, 'pages' => \App\Support\Legal::pages()] + \App\Support\Legal::render($page->body, $effective));
    }

    public function sitemap(): Response
    {
        $urls = [
            route('site.home'), route('site.how'), route('site.features'), route('site.templates'), route('site.solutions'),
            route('site.pricing'), route('site.resources'), route('site.blog'), route('site.help'), route('site.contact'),
            route('site.about'), route('site.security'), route('site.legal.index'),
        ];
        foreach (\App\Support\Legal::pages() as $page) {
            $urls[] = \App\Support\Legal::url($page['slug']);
        }
        foreach (array_keys(Content::features()) as $slug) {
            $urls[] = route('site.feature', $slug);
        }
        $urls[] = route('site.docs.index');
        foreach (self::DOCS as $slug) {
            $urls[] = route('site.docs', $slug);
        }
        foreach (array_keys(Content::solutions()) as $slug) {
            $urls[] = route('site.solution', $slug);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $xml .= '<url><loc>'.e($url).'</loc></url>';
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Unlocks the "coming soon" site for the owner (30-day cookie).
     */
    public function unlock(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $password = (string) config('site.preview_password');
        if ($password === '' || ! hash_equals($password, (string) $request->input('password'))) {
            return response()->view('site.coming-soon', ['failed' => true], 403)->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return redirect()->route('site.home')->withCookie(cookie(
            \App\Http\Middleware\SitePreviewGate::COOKIE, \App\Http\Middleware\SitePreviewGate::token(), 60 * 24 * 30, secure: $request->isSecure(), httpOnly: true, sameSite: 'lax'
        ));
    }

    public function lock(): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('site.home')->withCookie(cookie()->forget(\App\Http\Middleware\SitePreviewGate::COOKIE));
    }
}
