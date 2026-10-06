{{-- /robots.txt (SiteController::robots). AI, SEO and scraping bots are always kept out; search engines
     only when SITE_SEARCH_ENGINES is on. The website also enforces this (BlockCrawlers). --}}
# OrderOrbit Space - https://orderorbit.space
@if ($search)
# Search engines may index the public website. AI crawlers, AI agents, SEO and scraping bots may not
# (the website also refuses them).
@else
# This website isn't open to crawlers: no search engines, AI crawlers, AI agents, SEO or scraping bots
# (the website also refuses them). Link previews are allowed.
@endif

# ---------------------------------------------------------------- AI, SEO and scraping bots: blocked
# OpenAI
User-agent: GPTBot
User-agent: ChatGPT-User
User-agent: OAI-SearchBot
User-agent: Operator
# Anthropic
User-agent: ClaudeBot
User-agent: Claude-User
User-agent: Claude-SearchBot
User-agent: Claude-Web
User-agent: anthropic-ai
# Google AI training (does not affect Google Search)
User-agent: Google-Extended
User-agent: GoogleOther
User-agent: GoogleOther-Image
User-agent: GoogleOther-Video
User-agent: Google-CloudVertexBot
# Apple AI training (does not affect Applebot search)
User-agent: Applebot-Extended
# Meta
User-agent: meta-externalagent
User-agent: meta-externalfetcher
User-agent: FacebookBot
# Perplexity
User-agent: PerplexityBot
User-agent: Perplexity-User
# Amazon
User-agent: Amazonbot
# Microsoft / Common Crawl datasets
User-agent: CCBot
# ByteDance
User-agent: Bytespider
# Others
User-agent: cohere-ai
User-agent: cohere-training-data-crawler
User-agent: Diffbot
User-agent: DuckAssistBot
User-agent: YouBot
User-agent: MistralAI-User
User-agent: AI2Bot
User-agent: Ai2Bot-Dolma
User-agent: PetalBot
User-agent: Timpibot
User-agent: omgili
User-agent: omgilibot
User-agent: ImagesiftBot
User-agent: img2dataset
User-agent: Kangaroo Bot
User-agent: PanguBot
User-agent: Sidetrade indexer bot
User-agent: VelenPublicWebCrawler
User-agent: Webzio-Extended
User-agent: iaskspider/2.0
User-agent: ISSCyberRiskCrawler
User-agent: Scrapy
User-agent: FirecrawlAgent
User-agent: Brightbot
# SEO, marketing and data-mining crawlers
User-agent: AhrefsBot
User-agent: AhrefsSiteAudit
User-agent: SemrushBot
User-agent: SiteAuditBot
User-agent: MJ12bot
User-agent: DotBot
User-agent: rogerbot
User-agent: BLEXBot
User-agent: serpstatbot
User-agent: DataForSeoBot
User-agent: Barkrowler
User-agent: SEOkicks
User-agent: SeznamBot
User-agent: MegaIndex
User-agent: ZoominfoBot
User-agent: Screaming Frog SEO Spider
User-agent: Sitebulb
User-agent: magpie-crawler
User-agent: MauiBot
User-agent: Awario
User-agent: BUbiNG
User-agent: archive.org_bot
User-agent: ia_archiver
User-agent: HTTrack
User-agent: Nutch
Disallow: /

@if ($search)
# ---------------------------------------------------------------- Search engines
User-agent: *
Disallow: /app
Disallow: /admin
Disallow: /webhooks
Disallow: /templates/previews.json

Sitemap: {{ route('site.sitemap') }}
@else
# ---------------------------------------------------------------- Link previews (Slack, WhatsApp, social networks)
User-agent: facebookexternalhit
User-agent: Twitterbot
User-agent: LinkedInBot
User-agent: Slackbot
User-agent: Slackbot-LinkExpanding
User-agent: WhatsApp
User-agent: TelegramBot
User-agent: Discordbot
Allow: /
Disallow: /app
Disallow: /admin

# ---------------------------------------------------------------- Everyone else, search engines included
User-agent: *
Disallow: /
@endif
