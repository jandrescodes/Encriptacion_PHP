<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Model\User;
use App\Model\UserSession;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CapturingAuthController;
use Tests\Support\RedirectCaptured;
use Tests\TestCase;

// Caracterización de AuthController::login()/logout(): fija el comportamiento actual antes de refactorizarlo.
final class AuthLoginTest extends TestCase
{
    /** @var array<string, ?string> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        // .env.testing puede desactivar el bloqueo; estos tests lo necesitan activo y con un umbral conocido.
        $this->envBackup = [
            'LOGIN_LOCKOUT_ENABLED' => $_ENV['LOGIN_LOCKOUT_ENABLED'] ?? null,
            'LOGIN_MAX_ATTEMPTS'    => $_ENV['LOGIN_MAX_ATTEMPTS'] ?? null,
            'LOGIN_IP_THROTTLE_ENABLED' => $_ENV['LOGIN_IP_THROTTLE_ENABLED'] ?? null,
        ];
        // El límite por IP vive en Redis y acumula entre ejecuciones; aquí solo se prueba el bloqueo por usuario.
        $_ENV['LOGIN_IP_THROTTLE_ENABLED'] = 'false';
        $_ENV['LOGIN_LOCKOUT_ENABLED'] = 'true';
        $_ENV['LOGIN_MAX_ATTEMPTS']    = '5';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR']    = '203.0.113.7';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        $_POST = [];
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        parent::tearDown();
    }

    /** Envía el formulario de login y devuelve la ruta a la que redirige. */
    private function submitLogin(string $username, string $password, bool $validCsrf = true): ?string
    {
        $_SESSION['csrf_token'] = 'tok';
        $_POST = [
            'btningresar' => '',
            '_csrf'       => $validCsrf ? 'tok' : 'otro',
            'usuario'     => $username,
            'password'    => $password,
        ];

        $controller = new CapturingAuthController(self::$db);
        try {
            $controller->login();
        } catch (RedirectCaptured) {
        }

        return $controller->redirectedTo;
    }

    private function activityCount(string $event): int
    {
        $stmt = self::$db->prepare('SELECT COUNT(*) FROM activity_logs WHERE event = ?');
        $stmt->bind_param('s', $event);
        $stmt->execute();
        $count = (int) $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        return $count;
    }

    private function attemptsFor(string $username): ?int
    {
        $stmt = self::$db->prepare('SELECT attempts FROM login_attempts WHERE identifier = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int) $row['attempts'] : null;
    }

    #[Test]
    public function it_rejects_an_invalid_csrf_token(): void
    {
        $this->createUser();

        $this->assertSame('/login', $this->submitLogin('testuser', 'secret123', validCsrf: false));
        $this->assertSame('Invalid request. Please try again.', $_SESSION['message']);
        $this->assertSame('error', $_SESSION['icon']);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    #[Test]
    public function it_warns_when_fields_are_empty(): void
    {
        $this->assertSame('/login', $this->submitLogin('', ''));
        $this->assertSame('Please fill in all fields', $_SESSION['message']);
        $this->assertSame('warning', $_SESSION['icon']);
    }

    #[Test]
    public function it_rejects_a_wrong_password_and_records_the_failure(): void
    {
        $this->createUser();

        $this->assertSame('/login', $this->submitLogin('testuser', 'incorrecta'));
        $this->assertSame('Incorrect username or password', $_SESSION['message']);
        $this->assertSame('error', $_SESSION['icon']);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertSame(1, $this->attemptsFor('testuser'));
        $this->assertSame(1, $this->activityCount('login_failed'));
    }

    #[Test]
    public function it_does_not_track_attempts_for_unknown_usernames(): void
    {
        $this->assertSame('/login', $this->submitLogin('fantasma', 'incorrecta'));
        $this->assertSame('Incorrect username or password', $_SESSION['message']);
        $this->assertNull($this->attemptsFor('fantasma'));
        $this->assertSame(1, $this->activityCount('login_failed'));
    }

    #[Test]
    public function it_locks_the_account_after_the_maximum_failed_attempts(): void
    {
        $this->createUser();

        for ($i = 0; $i < 5; $i++) {
            $this->submitLogin('testuser', 'incorrecta');
        }

        $this->assertSame('/login', $this->submitLogin('testuser', 'secret123'));
        $this->assertStringStartsWith('Account temporarily locked', $_SESSION['message']);
        $this->assertSame('warning', $_SESSION['icon']);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    #[Test]
    public function it_refuses_password_login_for_google_only_accounts(): void
    {
        (new User(self::$db))->createFromOAuth([
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane.doe@gmail.com',
            'username'       => 'janedoe',
            'oauth_provider' => User::OAUTH_PROVIDER_GOOGLE,
            'oauth_id'       => 'sub-login',
        ]);

        $this->assertSame('/login', $this->submitLogin('janedoe', 'loquesea'));
        $this->assertSame('warning', $_SESSION['icon']);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertNull($this->attemptsFor('janedoe'));
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_opens_a_session_on_valid_credentials(): void
    {
        $this->startFileSession();
        $user = $this->createUser(['is_admin' => 1]);
        // Un intento previo debe borrarse con el login correcto.
        $this->submitLogin('testuser', 'incorrecta');

        $this->assertSame('/', $this->submitLogin('testuser', 'secret123'));

        $this->assertSame($user['id'], (int) $_SESSION['user_id']);
        $this->assertSame('Test', $_SESSION['name']);
        $this->assertEquals(1, $_SESSION['is_admin']);
        $this->assertSame('Welcome, Test!', $_SESSION['message']);
        $this->assertSame('success', $_SESSION['icon']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $_SESSION['session_token']);
        $this->assertNull($this->attemptsFor('testuser'));
        $this->assertSame(1, $this->activityCount('login_success'));

        $stmt = self::$db->prepare('SELECT user_id, ip_address, via_remember FROM user_sessions WHERE token_hash = ?');
        $hash = hash('sha256', $_SESSION['session_token']);
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $this->assertSame($user['id'], (int) $row['user_id']);
        $this->assertSame('203.0.113.7', $row['ip_address']);
        $this->assertSame(0, (int) $row['via_remember']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_closes_the_session_row_and_audits_on_logout(): void
    {
        $this->startFileSession();
        // logout() reabre la sesión con el helper de autoload.php, que los tests no cargan.
        if (!function_exists('session_start_secure')) {
            eval('function session_start_secure(): void { if (session_status() === PHP_SESSION_NONE) { session_start(); } }');
        }

        $user  = $this->createUser();
        $token = bin2hex(random_bytes(32));
        (new UserSession(self::$db))->create($user['id'], $token, '203.0.113.7', 'PHPUnit');
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['session_token'] = $token;
        $_SESSION['csrf_token']    = 'tok';
        $_POST['_csrf']            = 'tok';

        $controller = new CapturingAuthController(self::$db);
        try {
            $controller->logout();
        } catch (RedirectCaptured) {
        }

        $this->assertSame('/login', $controller->redirectedTo);
        $this->assertSame('Logged out successfully', $_SESSION['message']);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertSame(1, $this->activityCount('logout'));
        $this->assertSame(0, (int) self::$db->query('SELECT COUNT(*) FROM user_sessions')->fetch_row()[0]);
    }

    /** Sesión real en CLI sin cookies ni cabeceras, para que session_regenerate_id() no avise. */
    private function startFileSession(): void
    {
        ini_set('session.use_cookies', '0');
        ini_set('session.use_only_cookies', '0');
        ini_set('session.cache_limiter', '');
        ini_set('session.save_path', sys_get_temp_dir());
        session_start();
    }
}
