<?php

namespace App\Model;

use App\Core\Model;
use App\Lib\Cache\CacheInterface;

class User extends Model
{
    public const OAUTH_PROVIDER_GOOGLE = 'google';

    private const CACHE_KEY_ALL_USERS = 'users.all';

    private CacheInterface $cache;

    public function __construct(\mysqli $connection, ?CacheInterface $cache = null)
    {
        parent::__construct($connection);
        $this->cache = $cache ?? appCache();
    }

    public function getAll(): array
    {
        $ttl = (int) env('CACHE_TTL_USERS', 60);

        return $this->cache->remember(self::CACHE_KEY_ALL_USERS, $ttl, function (): array {
            $result = $this->db->query("SELECT * FROM users");
            $users  = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            return $users;
        });
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }

    public function getByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }

    public function getByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }

    public function findByOAuth(string $provider, string $oauthId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE oauth_provider = ? AND oauth_id = ? LIMIT 1"
        );
        $stmt->bind_param("ss", $provider, $oauthId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }

    public function generateUniqueUsername(string $base, string $fallbackEmail = ''): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $base));

        if ($slug === '' && $fallbackEmail !== '') {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $fallbackEmail)[0] ?? ''));
        }

        $slug = substr($slug, 0, 50);
        $candidate = $slug !== '' ? $slug : 'user';
        $suffix = 0;

        while ($this->getByUsername($candidate) !== null) {
            $suffix++;
            $suffixStr = (string) $suffix;
            $candidate = substr($slug, 0, 50 - strlen($suffixStr)) . $suffixStr;

            if ($suffix >= 100) {
                throw new \RuntimeException('Unable to generate a unique username.');
            }
        }

        return $candidate;
    }

    public function createFromOAuth(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (first_name, last_name, email, username, oauth_provider, oauth_id, is_admin)
             VALUES (?, ?, ?, ?, ?, ?, 0)"
        );
        $stmt->bind_param(
            "ssssss",
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['username'],
            $data['oauth_provider'],
            $data['oauth_id']
        );
        $success = $stmt->execute();
        $id = $success ? (int) $stmt->insert_id : 0;
        $stmt->close();

        if ($id > 0) {
            $this->cache->forget(self::CACHE_KEY_ALL_USERS);
        }

        return $id;
    }

    public function linkOAuthProvider(int $userId, string $provider, string $oauthId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET oauth_provider = ?, oauth_id = ? WHERE id = ?"
        );
        $stmt->bind_param("ssi", $provider, $oauthId, $userId);
        $success = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();

        if ($success) {
            $this->cache->forget(self::CACHE_KEY_ALL_USERS);
        }

        return $success;
    }

    public static function isOAuthAccount(array $user): bool
    {
        return isset($user['oauth_provider']) && $user['oauth_provider'] !== null;
    }

    public static function rejectPasswordLogin(array $user): ?string
    {
        if (self::isOAuthAccount($user) && ($user['password'] ?? null) === null) {
            return 'This account uses Google sign-in. Please use "Continue with Google".';
        }

        return null;
    }

    public function create(array $data): bool
    {
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            "INSERT INTO users (first_name, last_name, email, username, password, is_admin)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "sssssi",
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['username'],
            $hashedPassword,
            $data['is_admin']
        );
        $stmt->execute();
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        if ($success) {
            $this->cache->forget(self::CACHE_KEY_ALL_USERS);
        }
        return $success;
    }

    public function update(int $id, array $data): bool
    {
        $existing = $this->getById($id);
        if (!$existing) {
            return false;
        }

        $hashedPassword = !empty($data['password'])
            ? password_hash($data['password'], PASSWORD_DEFAULT)
            : $existing['password'];

        $stmt = $this->db->prepare(
            "UPDATE users
             SET first_name=?, last_name=?, email=?, username=?, password=?, is_admin=?
             WHERE id=?"
        );
        $stmt->bind_param(
            "sssssii",
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['username'],
            $hashedPassword,
            $data['is_admin'],
            $id
        );
        $stmt->execute();
        $success = $stmt->affected_rows !== -1;
        $stmt->close();
        if ($success) {
            $this->cache->forget(self::CACHE_KEY_ALL_USERS);
        }
        return $success;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        if ($success) {
            $this->cache->forget(self::CACHE_KEY_ALL_USERS);
        }
        return $success;
    }

    public function setRememberToken(int $userId, string $tokenHash, string $expiresAt): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET remember_token = ?, remember_token_expires = ? WHERE id = ?"
        );
        $stmt->bind_param("ssi", $tokenHash, $expiresAt, $userId);
        $stmt->execute();
        $success = $stmt->affected_rows >= 0;
        $stmt->close();
        return $success;
    }

    public function getByRememberToken(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE remember_token = ? AND remember_token_expires > NOW() LIMIT 1"
        );
        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }

    public function clearRememberToken(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $success = $stmt->affected_rows >= 0;
        $stmt->close();
        return $success;
    }

    public function updateProfile(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET first_name=?, last_name=?, email=?, username=? WHERE id=?"
        );
        $stmt->bind_param(
            "ssssi",
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['username'],
            $id
        );
        $stmt->execute();
        $success = $stmt->affected_rows !== -1;
        $stmt->close();
        if ($success) {
            $this->cache->forget(self::CACHE_KEY_ALL_USERS);
        }
        return $success;
    }

    public function getPasswordById(int $id): ?string
    {
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['password'] ?? null;
    }

    public function updatePasswordProfile(int $id, string $newPassword): bool
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashedPassword, $id);
        $stmt->execute();
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        if ($success) {
            $this->cache->forget(self::CACHE_KEY_ALL_USERS);
        }
        return $success;
    }

    public function getTotalCount(): int
    {
        $result = $this->db->query("SELECT COUNT(*) AS total FROM users");
        return (int) $result->fetch_assoc()['total'];
    }

    public function updatePassword(string $email, string $newPassword): bool
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);
        $stmt->execute();
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        if ($success) {
            $this->cache->forget(self::CACHE_KEY_ALL_USERS);
        }
        return $success;
    }
}
