<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;

// Enlaza por email solo porque GoogleOAuthService ya descarta cuentas con email_verified = false.
final class OAuthAccountResolver
{
    public function __construct(private User $users)
    {
    }

    /**
     * @param array<string, mixed> $googleUser
     * @return array<string, mixed>|null
     */
    public function resolve(array $googleUser): ?array
    {
        $existing = $this->users->findByOAuth(User::OAUTH_PROVIDER_GOOGLE, $googleUser['oauth_id']);
        if ($existing) {
            return $existing;
        }

        $byEmail = $this->users->getByEmail($googleUser['email']);
        if ($byEmail) {
            $linked = $this->users->linkOAuthProvider(
                (int) $byEmail['id'],
                User::OAUTH_PROVIDER_GOOGLE,
                $googleUser['oauth_id']
            );

            return $linked ? $this->users->findByOAuth(User::OAUTH_PROVIDER_GOOGLE, $googleUser['oauth_id']) : null;
        }

        $username = $this->users->generateUniqueUsername(
            ($googleUser['given_name'] ?? '') . ($googleUser['family_name'] ?? ''),
            $googleUser['email']
        );
        $userId = $this->users->createFromOAuth([
            'first_name'     => $googleUser['given_name'] ?? '',
            'last_name'      => $googleUser['family_name'] ?? '',
            'email'          => $googleUser['email'],
            'username'       => $username,
            'oauth_provider' => User::OAUTH_PROVIDER_GOOGLE,
            'oauth_id'       => $googleUser['oauth_id'],
        ]);

        return $userId > 0 ? $this->users->getById($userId) : null;
    }
}
