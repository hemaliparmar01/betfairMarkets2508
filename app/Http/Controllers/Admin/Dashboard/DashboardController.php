<?php

namespace App\Http\Controllers\Admin\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLoginSession;
use App\Models\Subscriptions;
use App\Repositories\Admin\UserManagementRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function index(UserManagementRepository $customers): View
    {
        return view('admin.dashboard.index', [
            'stats' => $customers->getStats() + [
                'activeSubscriptions' => Customer::query()->where('subscription_ends_at', '>', now())->count(),
                'activeTrials' => Customer::query()->where('trial_ends_at', '>', now())->count(),
                'subscriptionPlans' => Subscriptions::query()->count(),
                'activePlans' => Subscriptions::query()->where('is_active', true)->count(),
            ],
            'recentLogins' => CustomerLoginSession::query()->with('customer')->latest('logged_in_at')->limit(10)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = array_fill_keys(array_keys(config('app.branding_assets')), ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096']);
        $data = $request->validate($rules);
        $uploads = [];

        try {
            foreach ($data as $key => $image) {
                if ($image === null) {
                    continue;
                }
                $path = $image->store('branding', 'public');
                if ($path === false) {
                    throw new \RuntimeException('Unable to store branding image.');
                }
                $uploads[] = ['key' => $key, 'value' => $path];
            }
            if ($uploads !== []) {
                DB::table('site_settings')->upsert($uploads, ['key'], ['value']);
            }
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete(array_column($uploads, 'value'));
            report($exception);

            return back()->withErrors(['branding' => __('Unable to save images. Please try again.')]);
        }

        return redirect(route('admin.dashboard').'#branding-settings')->with('success', __('Changes saved successfully.'));
    }
}
