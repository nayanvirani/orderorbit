{{-- The public website's design (super admin → Website design): its fonts, then its settings as CSS variables. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="{{ \App\Support\SiteTheme::fontsUrl() }}" rel="stylesheet">
<style id="site-theme">{!! \App\Support\SiteTheme::css() !!}</style>
