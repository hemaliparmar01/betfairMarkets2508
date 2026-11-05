<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\CustomerLoginSession;
use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{code: string, recipient: string, html: ?string, sender: string, subject: ?string}> */
    private array $messages = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.url' => 'https://bfmarkets.example.com', 'services.infobip.key' => null, 'services.infobip.base_url' => null, 'mail.default' => 'array', 'mail.from.name' => 'Laravel']);
        Http::preventStrayRequests();
        Event::listen(MessageSent::class, function (MessageSent $event): void {
            $message = $event->message;
            preg_match('/\b([0-9]{6})\b/', $message->getTextBody(), $match);
            $this->messages[] = [
                'code' => $match[1] ?? '', 'recipient' => $message->getTo()[0]->getAddress(),
                'html' => $message->getHtmlBody(), 'sender' => $message->getFrom()[0]->getName(),
                'subject' => $message->getSubject(),
            ];
        });
    }

    public function test_imported_forms_render_in_existing_layout(): void
    {
        $this->get('/login')->assertOk()->assertSee('Forgot Password?')->assertSee('OTP login');
        $this->get('/register')->assertOk()->assertSee('name="country_code"', false);
        $this->get('/forgot-password')->assertOk()->assertSee('Send reset link');
    }

    #[TestWith(['GE', 'Asia/Tbilisi'])]
    #[TestWith(['IN', 'Asia/Kolkata'])]
    public function test_registration_and_verification_use_existing_customer_schema(string $countryCode, string $timezone): void
    {
        $this->post('/register', array_replace($this->registration(), ['country_code' => $countryCode]))
            ->assertSessionHasNoErrors()->assertRedirect(route('customers.verification'));
        $customer = Customer::query()->sole();
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'timezone' => $timezone]);
        $this->assertFalse($customer->is_verified);
        $this->assertTrue(Hash::check('Secret@123', $customer->password));
        $this->assertSame('newcustomer@example.com', $this->messages[0]['recipient']);
        $this->assertSame('BF Markets', $this->messages[0]['sender']);
        $this->assertSame('Verify your BF Markets account', $this->messages[0]['subject']);
        $this->assertStringContainsString($this->messages[0]['code'], $this->messages[0]['html'] ?? '');
        $this->assertStringContainsString('10 minutes', $this->messages[0]['html'] ?? '');
        $this->assertStringNotContainsString('Reset Password', $this->messages[0]['html'] ?? '');
        $this->assertStringNotContainsString($this->messages[0]['code'], $customer->code);
        $this->get('/verify-email')->assertOk()->assertSee('maxlength="6"', false);
        $previousSessionId = session()->getId();
        $this->post('/verify-email', ['code' => $this->messages[0]['code']])->assertSessionHasNoErrors()->assertRedirect(route('football.home'))
            ->assertSessionMissing('customer_verification');
        $this->assertTrue($customer->fresh()->is_verified);
        $this->assertAuthenticatedAs($customer, 'customers');
        $this->assertGuest('web');
        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertNull($customer->fresh()->code);
        $this->assertSame(1, $customer->fresh()->login_count);
        $this->assertSame('email', $customer->fresh()->login_channel_via);
        $this->assertDatabaseHas('customers_login_sessions', ['customer_id' => $customer->id, 'session_id' => session()->getId(), 'logged_out_at' => null]);
        $this->app['auth']->forgetGuards();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get('/my-profile')->assertOk();
    }

    public function test_invalid_registration_does_not_create_a_customer(): void
    {
        $this->post('/register', array_replace($this->registration(), ['country_code' => 'ZZ', 'password' => 'weak', 'terms' => 0]))
            ->assertSessionHasErrors(['country_code', 'password', 'terms']);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_password_login_preserves_session_tracking_and_guard_isolation(): void
    {
        $customer = CustomerFactory::new()->create(['username' => 'customer', 'password' => 'Secret@123']);
        $this->post('/login', ['identity' => 'CUSTOMER', 'password' => 'Secret@123'])->assertRedirect(route('football.home'));
        $this->assertAuthenticatedAs($customer, 'customers');
        $this->assertGuest('web');
        $this->assertDatabaseHas('customers_login_sessions', ['customer_id' => $customer->id, 'logged_out_at' => null]);
        $this->assertSame(1, $customer->fresh()->login_count);
    }

    public function test_customer_logout_closes_the_tracked_session(): void
    {
        $customer = CustomerFactory::new()->create(['password' => 'Secret@123']);
        $this->post('/login', ['identity' => $customer->email, 'password' => 'Secret@123'])
            ->assertRedirect(route('football.home'));
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest('customers');
        $this->assertNull($customer->fresh()->active_session_id);
        $this->assertNotNull(CustomerLoginSession::query()->sole()->logged_out_at);
    }

    public function test_password_login_routes_unverified_customers_to_verification(): void
    {
        $customer = CustomerFactory::new()->create(['is_verified' => false, 'password' => 'Secret@123']);
        $this->post('/login', ['identity' => $customer->email, 'password' => 'Secret@123'])
            ->assertRedirect(route('customers.verification'));
        $this->assertGuest('customers');
        $this->assertSame($customer->email, $this->messages[0]['recipient']);
        $this->post('/verify-email', ['code' => $this->messages[0]['code']])->assertSessionHasNoErrors()->assertRedirect(route('football.home'));
        $this->assertAuthenticatedAs($customer, 'customers');
        $this->assertSame(1, $customer->fresh()->login_count);
    }

    #[TestWith(['incorrect'])]
    #[TestWith(['expired'])]
    #[TestWith(['blocked'])]
    #[TestWith(['suspended'])]
    #[TestWith(['already_verified'])]
    public function test_invalid_account_verification_does_not_automatically_log_in(string $condition): void
    {
        $customer = CustomerFactory::new()->create(['is_verified' => false]);
        $this->withSession(['customer_verification' => ['customer_id' => $customer->id, 'expires_at' => now()->timestamp + 86400]])
            ->post('/verify-email/resend')->assertSessionHasNoErrors();
        if ($condition === 'expired') {
            $this->travel(601)->seconds();
        } elseif ($condition === 'blocked') {
            $customer->forceFill(['is_blocked' => true])->save();
        } elseif ($condition === 'suspended') {
            $customer->forceFill(['is_suspended' => true])->save();
        } elseif ($condition === 'already_verified') {
            $customer->forceFill(['is_verified' => true])->save();
        }

        $this->post('/verify-email', ['code' => $condition === 'incorrect' ? '000000' : $this->messages[0]['code']])->assertSessionHasErrors('code');

        $this->assertGuest('customers');
        $this->assertDatabaseCount('customers_login_sessions', 0);
        $this->assertSame(0, $customer->fresh()->login_count);
    }

    public function test_password_login_requires_explicit_device_switch_confirmation(): void
    {
        $customer = CustomerFactory::new()->create(['active_session_id' => 'old-device', 'password' => 'Secret@123']);
        $this->recordSession($customer, 'old-device');
        $this->post('/login', ['identity' => $customer->email, 'password' => 'Secret@123'])->assertRedirect(route('login'));
        $this->assertGuest('customers');
        $this->get('/login')->assertOk()->assertSee('Log out previous device and continue');
        $this->post('/login/logout-previous-sessions', ['confirm' => 1])->assertRedirect(route('football.home'));
        $this->assertAuthenticatedAs($customer, 'customers');
        $this->assertNotNull(CustomerLoginSession::query()->where('session_id', 'old-device')->value('logged_out_at'));
    }

    public function test_email_fallback_otp_is_single_use_and_records_login(): void
    {
        $customer = CustomerFactory::new()->create();
        $this->sendOtp($customer)->assertRedirect(route('login.mobile.verification'));
        $this->assertSame($customer->email, $this->messages[0]['recipient']);
        $this->assertSame('email', $customer->fresh()->login_channel_via);
        $this->assertSame('BF Markets', $this->messages[0]['sender']);
        $this->assertSame('Your BF Markets login code', $this->messages[0]['subject']);
        $this->assertStringContainsString($this->messages[0]['code'], $this->messages[0]['html'] ?? '');
        $this->assertStringContainsString('10 minutes', $this->messages[0]['html'] ?? '');
        $this->assertStringNotContainsString($this->messages[0]['code'], $customer->fresh()->login_otp);
        $this->get('/login/mobile/verification')->assertOk()->assertSee('maxlength="6"', false);
        $this->post('/login/mobile/verify-code', ['code' => $this->messages[0]['code']])->assertRedirect(route('football.home'));
        $this->assertAuthenticatedAs($customer, 'customers');
        $this->assertNull($customer->fresh()->login_otp);
        $this->assertSame(1, $customer->fresh()->login_count);
    }

    public function test_otp_wrong_attempts_are_limited_and_expired_codes_fail(): void
    {
        $customer = CustomerFactory::new()->create();
        $this->sendOtp($customer);
        $code = $this->messages[0]['code'];
        $wrong = $code === '111111' ? '222222' : '111111';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login/mobile/verify-code', ['code' => $wrong])->assertSessionHasErrors('code');
        }
        $this->post('/login/mobile/verify-code', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest('customers');
        $this->travel(11)->minutes();
        $this->get('/login/mobile/verification')->assertRedirect(route('login'));
    }

    public function test_otp_resend_has_cooldown_and_invalidates_the_previous_code(): void
    {
        $customer = CustomerFactory::new()->create();
        $this->sendOtp($customer);
        $oldCode = $this->messages[0]['code'];
        $this->post('/login/mobile/resend-code')->assertSessionHasErrors('mobile');
        $this->assertNotNull(session('customer_login_otp'));
        $this->travel(61)->seconds();
        $this->post('/login/mobile/resend-code')->assertRedirect(route('login.mobile.verification'));
        $this->assertCount(2, $this->messages);
        if ($oldCode !== $this->messages[1]['code']) {
            $this->post('/login/mobile/verify-code', ['code' => $oldCode])->assertSessionHasErrors('code');
        }
        $this->post('/login/mobile/verify-code', ['code' => $this->messages[1]['code']])->assertRedirect(route('football.home'));
    }

    public function test_otp_login_keeps_device_switch_confirmation(): void
    {
        $customer = CustomerFactory::new()->create(['active_session_id' => 'old-device']);
        $this->recordSession($customer, 'old-device');
        $this->sendOtp($customer);
        $this->post('/login/mobile/verify-code', ['code' => $this->messages[0]['code']])->assertRedirect(route('login'));
        $this->assertGuest('customers');
        $this->post('/login/logout-previous-sessions', ['confirm' => 1])->assertRedirect(route('football.home'));
        $this->assertAuthenticatedAs($customer, 'customers');
        $this->assertSame('email', $customer->fresh()->login_channel_via);
    }

    public function test_sms_delivery_uses_configuration_and_enforces_sms_limit(): void
    {
        config(['services.infobip.key' => 'test-key', 'services.infobip.base_url' => 'https://sms.example.test']);
        Http::fake(['sms.example.test/*' => Http::response(['messages' => [['status' => ['groupId' => 1]]]])]);
        $customer = CustomerFactory::new()->create();
        $this->sendOtp($customer)->assertRedirect(route('login.mobile.verification'));
        $this->assertSame('mobile', $customer->fresh()->login_channel_via);
        $this->assertSame(1, $customer->fresh()->login_sms_count);
        $this->assertCount(0, $this->messages);
        Http::assertSentCount(1);
        $customer->forceFill(['login_sms_count' => 3])->save();
        $this->travel(61)->seconds();
        $this->sendOtp($customer)->assertRedirect(route('login.mobile.verification'));
        Http::assertSentCount(1);
        $this->assertSame('email', $customer->fresh()->login_channel_via);
        $this->assertSame($customer->email, $this->messages[0]['recipient']);
    }

    #[TestWith(['http://localhost:8000', false])]
    #[TestWith(['http://localhost:8000', true])]
    #[TestWith(['https://localhost', true])]
    #[TestWith(['https://127.0.0.1', false])]
    #[TestWith(['https://192.168.1.1', true])]
    #[TestWith(['https://[::1]', false])]
    #[TestWith(['http://bfmarkets.example.com', true])]
    #[TestWith(['https://bfmarkets.test', false])]
    #[TestWith(['', false])]
    public function test_unusable_public_callback_sends_email_directly_without_consuming_sms_allowance(string $appUrl, bool $registration): void
    {
        config(['app.url' => $appUrl, 'services.infobip.key' => 'test-key', 'services.infobip.base_url' => 'https://sms.example.test']);
        Http::fake(['sms.example.test/*' => Http::response(['messages' => [['status' => ['groupId' => 1, 'id' => 26]]]])]);
        $customer = CustomerFactory::new()->create(['country_code' => 'IN', 'mobile_code' => '91', 'is_verified' => ! $registration, 'register_sms_count' => 2, 'login_sms_count' => 2]);

        if ($registration) {
            $this->withSession(['customer_verification' => ['customer_id' => $customer->id, 'expires_at' => now()->timestamp + 86400]])
                ->post('/verify-email/resend')->assertSessionHasNoErrors()->assertRedirect(route('customers.verification'));
        } else {
            $this->sendOtp($customer)->assertSessionHasNoErrors()->assertRedirect(route('login.mobile.verification'));
        }

        Http::assertNothingSent();
        $this->assertCount(1, $this->messages);
        $this->assertSame($customer->email, $this->messages[0]['recipient']);
        $this->assertSame('email', $customer->fresh()->{$registration ? 'verification_via' : 'login_channel_via'});
        $this->assertSame(2, $customer->fresh()->register_sms_count);
        $this->assertSame(2, $customer->fresh()->login_sms_count);
        $this->get($registration ? '/verify-email' : '/login/mobile/verification')->assertOk()->assertSee('email');
        $this->post($registration ? '/verify-email' : '/login/mobile/verify-code', ['code' => $this->messages[0]['code']])->assertSessionHasNoErrors();
        if ($registration) {
            $this->assertTrue($customer->fresh()->is_verified);
        } else {
            $this->assertAuthenticatedAs($customer, 'customers');
        }
    }

    #[TestWith(['IN', '91', 2, false])]
    #[TestWith(['GE', '995', 5, false])]
    #[TestWith(['US', '1', 4, false])]
    #[TestWith(['IN', '91', 5, true])]
    #[TestWith(['GE', '995', 4, true])]
    #[TestWith(['US', '1', 2, true])]
    public function test_failed_sms_delivery_emails_the_same_otp_once_for_any_country(string $country, string $dialCode, int $group, bool $registration): void
    {
        config(['services.infobip.key' => 'test-key', 'services.infobip.base_url' => 'https://sms.example.test']);
        Http::fake(['sms.example.test/*' => Http::response(['messages' => [['status' => ['groupId' => 1, 'id' => 26]]]])]);
        $customer = CustomerFactory::new()->create(['country_code' => $country, 'mobile_code' => $dialCode, 'is_verified' => ! $registration]);
        if ($registration) {
            $this->withSession(['customer_verification' => ['customer_id' => $customer->id, 'expires_at' => now()->timestamp + 86400]])
                ->post('/verify-email/resend')->assertRedirect(route('customers.verification'));
        } else {
            $this->sendOtp($customer)->assertRedirect(route('login.mobile.verification'));
        }
        $sms = Http::recorded()[0][0]['messages'][0];
        $this->assertArrayHasKey('notifyUrl', $sms);
        $this->assertCount(0, $this->messages);
        $report = ['results' => [['messageId' => $sms['destinations'][0]['messageId'], 'to' => $dialCode.$customer->mobile, 'status' => ['groupId' => $group, 'id' => 9]]]];
        $this->postJson($sms['notifyUrl'], $report)->assertOk();
        $this->postJson($sms['notifyUrl'], $report)->assertOk();
        $this->assertCount(1, $this->messages);
        preg_match('/\b([0-9]{6})\b/', $sms['text'], $match);
        $this->assertSame($match[1], $this->messages[0]['code']);
        $this->assertSame($customer->email, $this->messages[0]['recipient']);
        $this->assertSame('email', $customer->fresh()->{$registration ? 'verification_via' : 'login_channel_via'});
        $this->assertSame(1, $customer->fresh()->{$registration ? 'register_sms_count' : 'login_sms_count'});
        Http::assertSentCount(1);
        $this->post($registration ? '/verify-email' : '/login/mobile/verify-code', ['code' => $match[1]])->assertSessionHasNoErrors();
        if (! $registration) {
            $this->assertAuthenticatedAs($customer, 'customers');
            $this->assertSame('email', $customer->fresh()->login_channel_via);
        } else {
            $this->assertTrue($customer->fresh()->is_verified);
        }
    }

    #[TestWith([200, 5])]
    #[TestWith([200, 2])]
    #[TestWith([200, 4])]
    #[TestWith([200, 0])]
    #[TestWith([400, 5])]
    #[TestWith([401, 0])]
    #[TestWith([429, 0])]
    #[TestWith([500, 0])]
    #[TestWith([0, 0])]
    public function test_sms_rejection_invalid_response_and_connection_failure_fall_back_to_email(int $httpStatus, int $group): void
    {
        config(['services.infobip.key' => 'test-key', 'services.infobip.base_url' => 'https://sms.example.test']);
        Http::fake(['sms.example.test/*' => $httpStatus === 0 ? Http::failedConnection() : Http::response($group === 0 ? [] : ['messages' => [['status' => ['groupId' => $group]]]], $httpStatus)]);
        $customer = CustomerFactory::new()->create();

        $this->sendOtp($customer)->assertRedirect(route('login.mobile.verification'));

        $this->assertCount(1, $this->messages);
        $this->assertSame('email', $customer->fresh()->login_channel_via);
        $this->assertSame(0, $customer->fresh()->login_sms_count);
        $this->post('/login/mobile/verify-code', ['code' => $this->messages[0]['code']])->assertRedirect(route('football.home'));
        $this->assertAuthenticatedAs($customer, 'customers');
    }

    public function test_sms_reports_are_signed_validated_correlated_and_keep_pending_and_delivered_messages_out_of_email(): void
    {
        $this->freezeTime();
        config(['services.infobip.key' => 'test-key', 'services.infobip.base_url' => 'https://sms.example.test']);
        Http::fake(['sms.example.test/*' => Http::response(['messages' => [['status' => ['groupId' => 1, 'id' => 26]]]])]);
        $customer = CustomerFactory::new()->create();
        $this->sendOtp($customer);
        $sms = Http::recorded()[0][0]['messages'][0];
        $id = $sms['destinations'][0]['messageId'];
        $cacheKey = 'customer-otp-sms:'.$id;
        preg_match('/\b([0-9]{6})\b/', $sms['text'], $match);
        $this->assertNotSame($match[1], Cache::get($cacheKey)['code']);
        $this->assertSame(1, $sms['validityPeriod']);
        parse_str(parse_url($sms['notifyUrl'], PHP_URL_QUERY), $query);
        $this->assertSame(now()->timestamp + 600, (int) $query['expires']);
        $report = ['results' => [['messageId' => $id, 'to' => $customer->mobile_code.$customer->mobile, 'status' => ['groupId' => 5, 'id' => 52]]]];
        $this->app->instance('env', 'local');

        $this->postJson(strtok($sms['notifyUrl'], '?'), $report)->assertForbidden();
        $this->postJson($sms['notifyUrl'].'tampered', $report)->assertForbidden();
        $this->postJson($sms['notifyUrl'], ['results' => [['status' => []]]])->assertUnprocessable();
        $this->postJson($sms['notifyUrl'], array_replace_recursive($report, ['results' => [['messageId' => 'another-message']]]))->assertOk();
        $this->postJson($sms['notifyUrl'], array_replace_recursive($report, ['results' => [['to' => '99999999999']]]))->assertOk();
        $this->postJson($sms['notifyUrl'], array_replace_recursive($report, ['results' => [['status' => ['groupId' => 1, 'id' => 26]]]]))->assertOk();
        $this->postJson($sms['notifyUrl'], array_replace_recursive($report, ['results' => [['status' => ['groupId' => 3, 'id' => 2]]]]))->assertOk();
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertCount(0, $this->messages);
        $this->postJson($sms['notifyUrl'], array_replace_recursive($report, ['results' => [['status' => ['groupId' => 3, 'id' => 5]]]]))->assertOk();
        $this->assertFalse(Cache::has($cacheKey));
        $this->postJson($sms['notifyUrl'], $report)->assertOk();
        $this->assertCount(0, $this->messages);
        $this->assertSame('mobile', $customer->fresh()->login_channel_via);
    }

    #[TestWith(['expired'])]
    #[TestWith(['resent'])]
    #[TestWith(['used'])]
    #[TestWith(['blocked'])]
    #[TestWith(['password_changed'])]
    #[TestWith(['attempt_limit'])]
    public function test_sms_failure_does_not_email_expired_superseded_or_invalidated_codes(string $condition): void
    {
        config(['services.infobip.key' => 'test-key', 'services.infobip.base_url' => 'https://sms.example.test']);
        Http::fake(['sms.example.test/*' => Http::response(['messages' => [['status' => ['groupId' => 1, 'id' => 26]]]])]);
        $customer = CustomerFactory::new()->create();
        $this->sendOtp($customer);
        $sms = Http::recorded()[0][0]['messages'][0];
        $report = ['results' => [['messageId' => $sms['destinations'][0]['messageId'], 'to' => $customer->mobile_code.$customer->mobile, 'status' => ['groupId' => 2, 'id' => 9]]]];
        if ($condition === 'expired') {
            $this->travel(601)->seconds();
        } elseif ($condition === 'resent') {
            $this->travel(61)->seconds();
            $this->sendOtp($customer)->assertSessionHasNoErrors();
        } elseif ($condition === 'used') {
            preg_match('/\b([0-9]{6})\b/', $sms['text'], $match);
            $this->post('/login/mobile/verify-code', ['code' => $match[1]])->assertRedirect(route('football.home'));
        } elseif ($condition === 'blocked') {
            Customer::query()->whereKey($customer->id)->update(['is_blocked' => true]);
        } elseif ($condition === 'password_changed') {
            $customer->update(['password' => 'NewPassword@123']);
        } else {
            $payload = json_decode($customer->fresh()->login_otp, true);
            $payload['attempts'] = 5;
            Customer::query()->whereKey($customer->id)->update(['login_otp' => json_encode($payload)]);
        }

        $response = $this->postJson($sms['notifyUrl'], $report);

        if ($condition === 'expired') {
            $response->assertForbidden();
        } else {
            $response->assertOk();
        }
        $this->assertCount(0, $this->messages);
    }

    public function test_registration_sms_limit_of_three_still_sends_email_without_an_sms_request(): void
    {
        config(['services.infobip.key' => 'test-key', 'services.infobip.base_url' => 'https://sms.example.test']);
        $customer = CustomerFactory::new()->create(['is_verified' => false, 'register_sms_count' => 3]);

        $this->withSession(['customer_verification' => ['customer_id' => $customer->id, 'expires_at' => now()->timestamp + 86400]])
            ->post('/verify-email/resend')->assertRedirect(route('customers.verification'));

        Http::assertNothingSent();
        $this->assertCount(1, $this->messages);
        $this->assertSame('email', $customer->fresh()->verification_via);
        $this->assertSame(3, $customer->fresh()->register_sms_count);
    }

    public function test_blocked_and_unverified_customers_cannot_bypass_verification(): void
    {
        $customer = CustomerFactory::new()->create(['is_blocked' => true]);
        $this->sendOtp($customer)->assertSessionHasErrors('mobile');
        $customer->forceFill(['is_blocked' => false, 'is_verified' => false])->save();
        $this->sendOtp($customer)->assertRedirect(route('customers.verification'));
        $this->assertGuest('customers');
        $this->assertNull($customer->fresh()->login_otp);
    }

    public function test_password_change_invalidates_outstanding_login_codes(): void
    {
        $customer = CustomerFactory::new()->create();
        $this->sendOtp($customer);
        $customer->update(['password' => 'Different@123']);
        $this->post('/login/mobile/verify-code', ['code' => $this->messages[0]['code']])->assertSessionHasErrors('code');
        $this->assertGuest('customers');
    }

    /** @return array<string, string|int> */
    private function registration(): array
    {
        return ['name' => 'New', 'surname' => 'Customer', 'username' => 'newcustomer', 'email' => 'newcustomer@example.com',
            'password' => 'Secret@123', 'country_code' => 'GE', 'mobile' => '555123456', 'terms' => 1];
    }

    private function sendOtp(Customer $customer): TestResponse
    {
        return $this->post('/login/mobile/send-code', ['country_code' => $customer->country_code, 'mobile' => $customer->mobile]);
    }

    private function recordSession(Customer $customer, string $sessionId): void
    {
        CustomerLoginSession::query()->create(['customer_id' => $customer->id, 'session_id' => $sessionId, 'device_type' => 'desktop', 'logged_in_at' => now()->subHour()]);
    }
}
