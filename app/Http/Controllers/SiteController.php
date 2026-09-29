<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View
    {
        return view('site.home', ['plans' => config('shopify.billing.plans')]);
    }

    public function pricing(): View
    {
        return view('site.pricing', ['plans' => config('shopify.billing.plans')]);
    }

    public function privacy(): View
    {
        return view('site.privacy');
    }

    public function terms(): View
    {
        return view('site.terms');
    }
}
