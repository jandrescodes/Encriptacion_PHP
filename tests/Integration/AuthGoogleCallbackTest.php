<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controller\AuthController;
use App\Core\Auth;
use App\Model\User;
use App\Service\GoogleOAuthService;
use App\Service\OAuthAccountResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CapturingAuthController;
use Tests\Support\RedirectCaptured;
use Tests\TestCase;

final class AuthGoogleCallbackTest extends TestCase
{
    private User $users;
    private AuthController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users      = new User(self::$db);
        $this->controller = new AuthController(self::$db);
    }

    // --- OAuthAccountResolver (sin HTTP a Google) ---

    private function resolveOrCreateAccount(User $userModel, array $googleUser): ?array
    {
        return (new OAuthAccountResolver($userModel))->resolve($googleUser);
    }

    #[Test]
    public function it_creates_a_new_user_from_google_data(): void
    {
        $user = $this->resolveOrCreateAccount($this->users, [
            'oauth_id'    => 'sub-new',
            'email'       => 'new.user@gmail.com',
            'given_name'  => 'New',
            'family_name' => 'User',
        ]);

        $this->assertNotNull($user);
        $this->assertSame('newuser', $user['username']);
        $this->assertSame('new.user@gmail.com', $user['email']);
        $this->assertNull($user['password']);
        $this->assertSame('google', $user['oauth_provider']);
        $this->assertSame('sub-new', $user['oauth_id']);
        $this->assertCount(1, $this->users->getAll());
    }

    #[Test]
    public function it_returns_the_existing_user_when_already_linked(): void
    {
        $id = $this->users->createFromOAuth([
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane.doe@gmail.com',
            'username'       => 'janedoe',
            'oauth_provider' => User::OAUTH_PROVIDER_GOOGLE,
            'oauth_id'       => 'sub-existing',
        ]);

        $user = $this->resolveOrCreateAccount($this->users, [
            'oauth_id'    => 'sub-existing',
            'email'       => 'jane.doe@gmail.com',
            'given_name'  => 'Jane',
            'family_name' => 'Doe',
        ]);

        $this->assertNotNull($user);
        $this->assertSame($id, (int) $user['id']);
        $this->assertCount(1, $this->users->getAll());
    }

    #[Test]
    public function it_links_the_google_provider_to_an_account_with_the_same_email(): void
    {
        $u = $this->createUser(['email' => 'merge@gmail.com', 'username' => 'existinguser']);
        $user = $this->resolveOrCreateAccount($this->users, [
            'oauth_id'    => 'sub-merge',
            'email'       => 'merge@gmail.com',
            'given_name'  => 'Merge',
            'family_name' => 'User',
        ]);

        $this->assertNotNull($user);
        $this->assertSame($u['id'], (int) $user['id']);
        $this->assertCount(1, $this->users->getAll());

        $row = $this->users->getById($u['id']);
        $this->assertSame('google', $row['oauth_provider']);
        $this->assertSame('sub-merge', $row['oauth_id']);
        $this->assertNotNull($this->users->findByOAuth(User::OAUTH_PROVIDER_GOOGLE, 'sub-merge'));
    }

    #[Test]
    public function it_derives_a_unique_username_on_collision_during_resolution(): void
    {
        $this->createUser(['username' => 'newuser']);
        $user = $this->resolveOrCreateAccount($this->users, [
            'oauth_id'    => 'sub-new2',
            'email'       => 'new.user2@gmail.com',
            'given_name'  => 'New',
            'family_name' => 'User',
        ]);

        $this->assertNotNull($user);
        $this->assertSame('newuser1', $user['username']);
    }

    // --- feature flag + state (redirect captured via subclass — no exit) ---

    #[Test]
    public function google_login_when_disabled_redirects_to_login_with_flash(): void
    {
        $this->assertFalse(GoogleOAuthService::isEnabled());

        $controller = new CapturingAuthController(self::$db);
        try {
            $controller->googleLogin();
            $this->fail('Expected Google sign-in to be disabled before reaching Google.');
        } catch (RedirectCaptured) {
            $this->assertSame('/login', $controller->redirectedTo);
        }

        $this->assertSame('Google sign-in is currently disabled.', $_SESSION['message']);
        $this->assertSame('info', $_SESSION['icon']);
        $this->assertArrayNotHasKey('oauth_google_state', $_SESSION);
    }

    #[Test]
    public function google_login_builds_the_authorization_url_and_stores_state_once(): void
    {
        $_ENV['OAUTH_GOOGLE_ENABLED']      = 'true';
        $_ENV['OAUTH_GOOGLE_CLIENT_ID']    = 'test-client.apps.googleusercontent.com';
        $_ENV['OAUTH_GOOGLE_REDIRECT_URI'] = 'http://localhost/Encriptacion_PHP/public/auth/google/callback';

        try {
            $controller = new CapturingAuthController(self::$db);
            try {
                $controller->googleLogin();
                $this->fail('Expected redirect to Google authorization endpoint.');
            } catch (RedirectCaptured) {
                $state = $_SESSION['oauth_google_state'] ?? null;
                $this->assertNotNull($state);
                $this->assertSame(64, strlen($state));

                $url = $controller->redirectedTo;
                $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);

                parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
                $this->assertSame('code', $params['response_type']);
                $this->assertSame('openid email profile', $params['scope']);
                $this->assertSame('test-client.apps.googleusercontent.com', $params['client_id']);
                $this->assertSame(
                    'http://localhost/Encriptacion_PHP/public/auth/google/callback',
                    $params['redirect_uri']
                );
                $this->assertSame('select_account', $params['prompt']);
                $this->assertSame('online', $params['access_type']);
                $this->assertSame($state, $params['state']);
            }
        } finally {
            unset(
                $_ENV['OAUTH_GOOGLE_ENABLED'],
                $_ENV['OAUTH_GOOGLE_CLIENT_ID'],
                $_ENV['OAUTH_GOOGLE_REDIRECT_URI']
            );
        }
    }

    #[Test]
    public function it_builds_the_google_authorization_url_with_expected_params(): void
    {
        $_ENV['OAUTH_GOOGLE_CLIENT_ID']    = 'test-client.apps.googleusercontent.com';
        $_ENV['OAUTH_GOOGLE_REDIRECT_URI'] = 'http://localhost/callback';

        try {
            $url = (new GoogleOAuthService())->buildAuthorizationUrl('state-abc');
            $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);

            parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
            $this->assertSame('test-client.apps.googleusercontent.com', $params['client_id']);
            $this->assertSame('http://localhost/callback', $params['redirect_uri']);
            $this->assertSame('openid email profile', $params['scope']);
            $this->assertSame('state-abc', $params['state']);
        } finally {
            unset($_ENV['OAUTH_GOOGLE_CLIENT_ID'], $_ENV['OAUTH_GOOGLE_REDIRECT_URI']);
        }
    }

    #[Test]
    public function callback_rejects_a_bad_state_without_side_effects(): void
    {
        $_ENV['OAUTH_GOOGLE_ENABLED'] = 'true';
        $_GET['state']                = 'wrong-state';
        $_GET['code']                 = 'code-1';

        try {
            $controller = new CapturingAuthController(self::$db);
            try {
                $controller->googleCallback();
                $this->fail('Expected the callback to reject the state before hitting Google.');
            } catch (RedirectCaptured) {
                $this->assertSame('/login', $controller->redirectedTo);
            }

            $this->assertSame('Invalid OAuth state. Please try again.', $_SESSION['message']);
            $this->assertSame('error', $_SESSION['icon']);
            $this->assertArrayNotHasKey('user_id', $_SESSION);
            $this->assertArrayNotHasKey('oauth_google_state', $_SESSION);
            $this->assertCount(0, $this->users->getAll());
        } finally {
            unset($_ENV['OAUTH_GOOGLE_ENABLED']);
        }
    }

    // --- integration with the rest of the auth system ---

    #[Test]
    public function password_login_is_rejected_for_google_accounts(): void
    {
        $this->users->createFromOAuth([
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane.doe@gmail.com',
            'username'       => 'janedoe',
            'oauth_provider' => User::OAUTH_PROVIDER_GOOGLE,
            'oauth_id'       => 'sub-123456',
        ]);

        $auth = new Auth(self::$db);
        $this->assertNull($auth->verifyCredentials('janedoe', 'whatever'));
        $this->assertSame(
            'This account uses Google sign-in. Please use "Continue with Google".',
            $auth->passwordLoginErrorMessage('janedoe')
        );
    }

    #[Test]
    public function reset_token_is_consumed_only_for_non_oauth_accounts(): void
    {
        $id = $this->users->createFromOAuth([
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'reset.oauth@gmail.com',
            'username'       => 'resetoauth',
            'oauth_provider' => User::OAUTH_PROVIDER_GOOGLE,
            'oauth_id'       => 'sub-reset',
        ]);

        $auth  = new Auth(self::$db);
        $token = $auth->createPasswordResetToken('reset.oauth@gmail.com');

        $this->assertNotEmpty($token);
        $this->assertNull($auth->consumeResetToken($token, 'newpass123'));
        $this->assertNull($this->users->getById($id)['password']);
    }
}
