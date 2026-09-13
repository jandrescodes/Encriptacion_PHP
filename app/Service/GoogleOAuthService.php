<?php

namespace App\Service;

use RuntimeException;

class GoogleOAuthService
{
    private const AUTH_URL        = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL       = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL    = 'https://www.googleapis.com/oauth2/v3/userinfo';
    private const CONNECT_TIMEOUT = 5;
    private const TIMEOUT         = 10;

    public static function isEnabled(): bool
    {
        return filter_var(env('OAUTH_GOOGLE_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function buildAuthorizationUrl(string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => env('OAUTH_GOOGLE_CLIENT_ID'),
            'redirect_uri'  => env('OAUTH_GOOGLE_REDIRECT_URI'),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'online',
            'prompt'        => 'select_account',
            'state'         => $state,
        ]);
    }

    public function exchangeCodeForTokens(string $code): array
    {
        $response = $this->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => env('OAUTH_GOOGLE_CLIENT_ID'),
            'client_secret' => env('OAUTH_GOOGLE_CLIENT_SECRET'),
            'redirect_uri'  => env('OAUTH_GOOGLE_REDIRECT_URI'),
            'grant_type'    => 'authorization_code',
        ]);

        if (!isset($response['access_token'], $response['id_token'])) {
            throw new RuntimeException('Google token response is missing required fields.');
        }

        return $response;
    }

    public function fetchUserInfo(string $accessToken): array
    {
        $info = $this->get(self::USERINFO_URL, $accessToken);

        if (empty($info['sub']) || empty($info['email']) || ($info['email_verified'] ?? false) !== true) {
            throw new RuntimeException('Google user info is invalid or email is not verified.');
        }

        return [
            'oauth_id'    => (string) $info['sub'],
            'email'       => (string) $info['email'],
            'given_name'  => isset($info['given_name']) ? (string) $info['given_name'] : '',
            'family_name' => isset($info['family_name']) ? (string) $info['family_name'] : '',
            'picture'     => isset($info['picture']) ? (string) $info['picture'] : '',
        ];
    }

    protected function post(string $url, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        return $this->curlResult($ch);
    }

    protected function get(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET        => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken, 'Accept: application/json'],
        ]);

        return $this->curlResult($ch);
    }

    private function curlResult(\CurlHandle $ch): array
    {
        $raw       = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $curlError !== '') {
            throw new RuntimeException('Google request failed: ' . $curlError);
        }

        if ($httpCode !== 200) {
            throw new RuntimeException("Google request failed with HTTP {$httpCode}.");
        }

        $response = json_decode((string) $raw, true);

        if (!is_array($response)) {
            throw new RuntimeException('Google returned an invalid JSON response.');
        }

        return $response;
    }
}
