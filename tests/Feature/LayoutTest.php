<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    private DomainRecords $records;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->records = new DomainRecords;
    }

    #[DataProvider('publicPages')]
    public function test_public_pages_share_an_accessible_light_document(string $route): void
    {
        $response = $this->get(route($route))->assertOk();
        $document = $this->assertDocument($response);

        $this->assertSame(0, $document->query('//*[@id="portal-navigation"]')->length);
        $this->assertSame(1, $document->query('//header')->length);
        $this->assertSame(1, $document->query('//nav[@id="site-navigation" and @data-navigation]')->length);
        $this->assertSame(1, $document->query('//button[@data-nav-toggle and @aria-controls="site-navigation" and @aria-expanded="false"]')->length);
        $this->assertSame(1, $document->query('//nav[@id="site-navigation"]//a[@aria-current="page" and @href="'.route($route).'"]')->length);

        foreach (['home', 'login', 'register'] as $destination) {
            $this->assertSame(1, $document->query('//nav[@id="site-navigation"]//a[@href="'.route($destination).'"]')->length);
        }

        if ($route !== 'home') {
            $this->assertPrivate($response, $document);
        }
    }

    public static function publicPages(): array
    {
        return [
            'home' => ['home'],
            'login' => ['login'],
            'register' => ['register'],
        ];
    }

    #[DataProvider('memberPages')]
    public function test_client_pages_have_member_navigation_and_one_content_landmark(string $route, string $activeRoute): void
    {
        $response = $this->actingAs($this->client())->get(route($route))->assertOk();
        $document = $this->assertDocument($response);

        $this->assertPrivate($response, $document);
        $this->assertPortalNavigation($document, $activeRoute, ['home', 'member', 'member.profile', 'member.pesanan']);

        foreach (['admin', 'admin.clients', 'admin.reauth', 'admin.2fa.setup', 'admin.2fa.challenge'] as $adminRoute) {
            $this->assertSame(0, $document->query('//a[@href="'.route($adminRoute).'"]')->length);
        }
    }

    public static function memberPages(): array
    {
        return [
            'dashboard' => ['member', 'member'],
            'profile' => ['member.profile', 'member.profile'],
        ];
    }

    #[DataProvider('adminPages')]
    public function test_verified_admin_pages_share_the_admin_navigation_without_nested_layouts(string $route, string $activeRoute): void
    {
        $admin = $this->admin();
        $this->verifyAdmin($admin);
        $response = $this->get(route($route))->assertOk();
        $document = $this->assertDocument($response);

        $this->assertPrivate($response, $document);
        $this->assertPortalNavigation($document, $activeRoute, [
            'home', 'admin', 'admin.pesanan', 'admin.lisensi', 'admin.produk', 'admin.clients', 'admin.reauth', 'password.change',
        ]);
        $this->assertSame(0, $document->query('//a[@href="'.route('member').'"]')->length);
    }

    public static function adminPages(): array
    {
        return [
            'dashboard' => ['admin', 'admin'],
            'client management' => ['admin.clients', 'admin.clients'],
            'reauthentication' => ['admin.reauth', 'admin.reauth'],
            'password' => ['password.change', 'password.change'],
        ];
    }

    #[DataProvider('unverifiedAdminPages')]
    public function test_admin_without_second_factor_is_kept_out_of_the_portal(bool $enrolled, string $destination): void
    {
        $admin = $enrolled ? $this->admin() : $this->client(['role' => 'admin']);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp]);

        $this->get(route('admin'))->assertRedirect(route($destination));
        $this->get(route('admin.clients'))->assertRedirect(route($destination));

        $response = $this->get(route($destination))->assertOk();
        $document = $this->assertDocument($response);
        $this->assertPrivate($response, $document);
        $this->assertSame(0, $document->query('//*[@id="portal-navigation"]')->length);
        $this->assertSame(0, $document->query('//a[@href="'.route('admin.clients').'"]')->length);
    }

    public static function unverifiedAdminPages(): array
    {
        return [
            'not enrolled' => [false, 'admin.2fa.setup'],
            'enrolled but not verified' => [true, 'admin.2fa.challenge'],
        ];
    }

    public function test_forced_password_change_keeps_the_required_form_outside_the_portal(): void
    {
        $this->actingAs($this->client(['must_change_password' => true]));
        $this->get(route('member'))->assertRedirect(route('password.change'));

        $response = $this->get(route('password.change'))->assertOk();
        $document = $this->assertDocument($response);
        $this->assertPrivate($response, $document);
        $this->assertSame(0, $document->query('//*[@id="portal-navigation"]')->length);
        $this->assertLogoutForm($document);
    }

    public function test_disabled_client_cannot_render_the_member_portal(): void
    {
        $this->actingAs($this->client(['status' => 'disabled']));

        $this->get(route('member'))->assertForbidden()->assertDontSee('id="portal-navigation"', false);
        $this->assertGuest();
    }

    private function assertDocument(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $this->assertTrue($document->loadHTML($response->getContent()));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('/html[@lang="id"]')->length);
        $this->assertSame(1, $xpath->query('//meta[@name="viewport"]')->length);
        $this->assertSame(1, $xpath->query('//meta[@name="color-scheme" and @content="light"]')->length);
        $this->assertSame(1, $xpath->query('//main')->length, 'Each page needs one main landmark.');
        $this->assertSame(1, $xpath->query('//main[@id="konten" and @tabindex="-1"]')->length);
        $this->assertSame(1, $xpath->query('//a[@href="#konten"]')->length);
        $this->assertSame(1, $xpath->query('//script[@src and @data-update-uri and @data-csrf]')->length, 'Load Livewire once per document.');

        return $xpath;
    }

    private function assertPrivate(TestResponse $response, DOMXPath $document): void
    {
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
        $this->assertSame(1, $document->query('//meta[@name="robots" and @content="noindex, nofollow"]')->length);
    }

    private function assertPortalNavigation(DOMXPath $document, string $activeRoute, array $allowedRoutes): void
    {
        $this->assertSame(1, $document->query('//*[@id="portal-navigation" and @data-navigation]')->length);
        $this->assertSame(1, $document->query('//button[@data-nav-toggle and @aria-controls="portal-navigation" and @aria-expanded="false"]')->length);
        $this->assertSame(1, $document->query('//*[@id="portal-navigation"]//a[@aria-current="page"]')->length);
        $this->assertSame(1, $document->query('//*[@id="portal-navigation"]//a[@aria-current="page" and @href="'.route($activeRoute).'"]')->length);

        $allowedUrls = array_map(fn (string $route) => route($route), $allowedRoutes);
        $navigationUrls = [];
        foreach ($document->query('//*[@id="portal-navigation"]//a[@href]') as $link) {
            $this->assertInstanceOf(DOMElement::class, $link);
            $url = $link->getAttribute('href');
            $this->assertContains($url, $allowedUrls, 'Portal navigation must link only to implemented pages.');
            $navigationUrls[] = $url;
        }

        $this->assertEqualsCanonicalizing($allowedUrls, array_values(array_unique($navigationUrls)));
        $this->assertLogoutForm($document);
    }

    private function assertLogoutForm(DOMXPath $document): void
    {
        $forms = $document->query('//form[@action="'.route('logout').'" and translate(@method, "POST", "post")="post"]');
        $this->assertSame(1, $forms->length);
        $this->assertSame(0, $document->query('//a[@href="'.route('logout').'"]')->length);
        $tokens = $document->query('.//input[@type="hidden" and @name="_token"]', $forms->item(0));
        $this->assertSame(1, $tokens->length);
        $this->assertSame(csrf_token(), $tokens->item(0)->getAttribute('value'));
    }

    private function client(array $attributes = []): User
    {
        return User::findOrFail($this->records->user($attributes));
    }

    private function admin(): User
    {
        return $this->client([
            'role' => 'admin',
            'totp_secret_enc' => Crypt::encryptString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'),
            'totp_enabled_at' => now()->format('Y-m-d H:i:s.u'),
        ]);
    }

    private function verifyAdmin(User $admin): void
    {
        $this->actingAs($admin)->withSession([
            'auth.totp_user_id' => $admin->id,
            'auth.totp_verified_at' => now()->timestamp,
            'auth.admin_last_activity' => now()->timestamp,
            'auth.password_confirmed_at' => now()->timestamp,
            'auth.reauthenticated_at' => now()->timestamp,
        ]);
    }
}
