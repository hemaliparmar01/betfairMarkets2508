<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\{DB, Mail};

use App\Models\{ContactMessage, FeatureRequest};
use App\Repositories\Eloquent\Admin\PolicySettingRepository;

class PolicySettingService
{
    private const PAGE_META = [
        'privacy_policy' => [
            'title' => 'Privacy Policy',
        ],
        'refund_policy' => [
            'title' => 'Refund Policy',
        ],
        'terms_and_conditions' => [
            'title' => 'Terms and Conditions',
        ],
        'bf_markets_manifesto' => [
            'title' => 'BF Markets Manifesto',
        ],
        'disclaimer' => [
            'title' => 'Disclaimer',
        ],
        'privacy_policy_translations' => [
            'title' => 'Privacy Policy',
        ],
        'refund_policy_translations' => [
            'title' => 'Refund Policy',
        ],
        'terms_and_conditions_translations' => [
            'title' => 'Terms and Conditions',
        ],
        'bf_markets_manifesto_translations' => [
            'title' => 'BF Markets Manifesto',
        ],
        'disclaimer_translations' => [
            'title' => 'Disclaimer',
        ],
        'filter_guide' => [
            'title' => 'Filter Guide',
        ],
        'filter_guide_translations' => [
            'title' => 'Filter Guide',
        ],
    ];

    public function __construct(
        private readonly PolicySettingRepository $policySettingRepository
    ) {}

    public function getAll(): array
    {
        return $this->policySettingRepository->getAll(array_keys(self::PAGE_META));
    }

    public function update(array $data): void
    {
        DB::transaction(function () use ($data) {
            $this->policySettingRepository->update($data);
        });
    }

    public function sendFeatureRequest(array $data): FeatureRequest
    {
        return DB::transaction(function () use ($data): FeatureRequest {
            $featureRequest = $this->policySettingRepository->create([
                'email' => trim((string) $data['email']),
                'message' => trim((string) $data['message']),
                'status' => 'new',
            ]);

            $adminEmail = 'office@betfairmarkets.com';
            // $adminEmail = 'hemaliparmar01@gmail.com';

            if (filled($adminEmail)) {
                Mail::send('users.emails.feature-request', ['featureRequest' => $featureRequest,], function ($mailMessage) use ($adminEmail): void {
                    $mailMessage->to($adminEmail)->subject('New Feature Request Submitted');
                });
            }
            return $featureRequest;
        });
    }

    public function sendContactMessage(array $data): ContactMessage
    {
        return DB::transaction(function () use ($data): ContactMessage {
            $contactMessage = $this->policySettingRepository->createContactMessage([
                'customer_id' => auth('customer')->id(),
                'name' => trim((string) $data['name']),
                'email' => trim((string) $data['email']),
                'subject' => trim((string) $data['subject']),
                'message' => trim((string) $data['message']),
                'status' => 'new',
            ]);

            // $adminEmail = 'hemaliparmar01@gmail.com';
            $adminEmail = 'office@betfairmarkets.com';

            if (filled($adminEmail)) {
                Mail::send('users.emails.contact-message', ['contactMessage' => $contactMessage,], function ($mailMessage) use ($adminEmail, $contactMessage): void {
                    $mailMessage->to($adminEmail)->subject('New Contact Message: ' . $contactMessage->subject);
                });
            }

            return $contactMessage;
        });
    }

    public function getContactMessages(array $filters = [])
    {
        return $this->policySettingRepository->getContactMessages($filters);
    }

    public function getFeatureRequests(array $filters = [])
    {
        return $this->policySettingRepository->getFeatureRequests($filters);
    }

    public function getPageData(string $key): array
    {
        $currentLocale = current_site_locale();
        if($currentLocale != 'en') {
            $key = $key.'_translations';
        }
        $meta = self::PAGE_META[$key] ?? self::PAGE_META['privacy_policy'];
        $settings = $this->policySettingRepository->getAll([$key]);

        $pageContent = $settings[$key];
        if($currentLocale != 'en') {
            $pageContent = data_get(json_decode($settings[$key]), $currentLocale);
        }
        return [
            'pageTitle' => $meta['title'],
            'pageContent' => $pageContent,
        ];
    }
}
