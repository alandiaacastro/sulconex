<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class PortalMotoristaTokenService
{
    private const EXPIRATION_SECONDS = 43200;

    public static function issue(Motorista $motorista): array
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + self::EXPIRATION_SECONDS;

        $payload = [
            'typ' => 'portal_motorista',
            'sub' => (int) $motorista->id,
            'nome' => (string) ($motorista->nome ?? ''),
            'telefone' => (string) ($motorista->telefone ?? ''),
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ];

        return [
            'token' => JWT::encode($payload, self::buildKey(), 'HS256'),
            'expires_at' => date(DATE_ATOM, $expiresAt),
            'expires_in' => self::EXPIRATION_SECONDS,
        ];
    }

    public static function decode(string $token): array
    {
        $decoded = JWT::decode($token, new Key(self::buildKey(), 'HS256'));
        return (array) $decoded;
    }

    private static function buildKey(): string
    {
        $config = AdiantiApplicationConfig::get();
        $seed = (string) ($config['general']['seed'] ?? '');

        if ($seed === '') {
            throw new PortalMotoristaApiException('Application seed not defined.', 500, 'seed_not_defined');
        }

        return APPLICATION_NAME . $seed . ':portal_motorista';
    }
}