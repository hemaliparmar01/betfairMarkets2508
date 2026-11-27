<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class StaticPageController extends Controller
{
    public function home(): View
    {
        return view('users.home');
    }

    public function disclaimer()
    {
        return view('users.staticPages.disclaimer');
    }

    public function privacyPolicy()
    {
        return view('users.staticPages.privacyPolicy');
    }

    public function termsOfService()
    {
        return view('users.staticPages.termsOfService');
    }

    public function refundPolicy()
    {
        return view('users.staticPages.refundPolicy');
    }

    public function faq()
    {
        return view('users.staticPages.faq');
    }

    public function manifesto()
    {
        return view('users.staticPages.manifesto');
    }

    public function filterGuide()
    {
        return view('users.staticPages.filterGuide');
    }

    public function marketActivityGuide()
    {
        return view('users.staticPages.marketActivityGuide');
    }

    public function pinnacleOddsDropsGuide()
    {
        return view('users.staticPages.pinnacleOddsDropsGuide');
    }
}
