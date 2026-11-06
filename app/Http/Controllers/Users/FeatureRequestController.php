<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Services\Admin\ContactMessageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class FeatureRequestController extends Controller
{
    public function featureRequest(Request $request, ContactMessageService $contactMessageService): View|RedirectResponse
    {
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'email' => ['required', 'string', 'email', 'max:150'],
                'message' => ['required', 'string'],
            ]);

            try {
                $contactMessageService->sendContactMessage([
                    'name' => 'Feature Request',
                    'subject' => 'Feature Request',
                    ...$data,
                ]);
            } catch (Throwable $exception) {
                report($exception);

                return to_route('feature-request')->withInput()->with('error', __('Unable to send your message. Please try again.'));
            }

            return to_route('feature-request')->with('success', __('Your request has been submitted successfully! Thank you.'));
        }

        return view('users.featureRequest');
    }
}
