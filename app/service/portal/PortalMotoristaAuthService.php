<?php

class PortalMotoristaAuthService
{
    private const MIN_PASSWORD_LENGTH = 6;

    public static function login(string $phone, string $password): array
    {
        $phone = PortalMotoristaSupportService::normalizePhone($phone);
        $password = (string) $password;

        if ($phone === '' || $password === '') {
            throw new PortalMotoristaApiException('Informe o telefone e a senha.', 422, 'validation_error');
        }

        return PortalMotoristaSupportService::withSampleTransaction(function (PDO $connection) use ($phone, $password) {
            Motorista::ensureTables();

            $row = PortalMotoristaSupportService::findMotoristaRowByPhone($connection, $phone);
            if (!$row) {
                throw new PortalMotoristaApiException('Telefone nao encontrado.', 401, 'invalid_credentials');
            }

            $storedPassword = (string) ($row['senha_portal'] ?? '');
            if ($storedPassword === '') {
                throw new PortalMotoristaApiException('Motorista sem senha cadastrada. Solicite ao administrador.', 403, 'password_not_set');
            }

            $passwordValid = false;
            $shouldUpgradeHash = false;

            if (PortalMotoristaSupportService::isPasswordHash($storedPassword)) {
                $passwordValid = password_verify($password, $storedPassword);
                $shouldUpgradeHash = $passwordValid && password_needs_rehash($storedPassword, PASSWORD_DEFAULT);
            } else {
                $passwordValid = hash_equals($storedPassword, $password);
                $shouldUpgradeHash = $passwordValid;
            }

            if (!$passwordValid) {
                throw new PortalMotoristaApiException('Senha incorreta.', 401, 'invalid_credentials');
            }

            if ($shouldUpgradeHash) {
                $stmt = $connection->prepare('UPDATE motorista SET senha_portal = ? WHERE id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int) $row['id']]);
            }

            $motorista = new Motorista((int) $row['id']);
            PortalMotoristaSupportService::setPortalSession($motorista);

            return array_merge(
                PortalMotoristaTokenService::issue($motorista),
                ['driver' => PortalMotoristaSupportService::buildDriverProfile($motorista)]
            );
        });
    }

    public static function generateTemporaryAccess(int $motoristaId): array
    {
        if ($motoristaId <= 0) {
            throw new InvalidArgumentException('Motorista invalido para gerar acesso.');
        }

        return PortalMotoristaSupportService::withSampleTransaction(function () use ($motoristaId) {
            Motorista::ensureTables();

            $motorista = new Motorista($motoristaId);
            if (empty($motorista->id)) {
                throw new RuntimeException('Motorista nao encontrado.');
            }

            $temporaryPassword = self::generateTemporaryPassword();
            $motorista->senha_portal = password_hash($temporaryPassword, PASSWORD_DEFAULT);
            $motorista->senha_portal_temporaria = 1;
            $motorista->store();

            $portalUrl = PortalMotoristaSupportService::buildAbsoluteApplicationUrl('portal-motorista/');
            $message = self::buildTemporaryAccessMessage($motorista, $temporaryPassword, $portalUrl);

            return [
                'driver' => PortalMotoristaSupportService::buildDriverProfile($motorista),
                'temporary_password' => $temporaryPassword,
                'portal_url' => $portalUrl,
                'message' => $message,
                'whatsapp_url' => PortalMotoristaSupportService::buildWhatsAppUrl((string) ($motorista->telefone ?? ''), $message),
            ];
        });
    }

    public static function changePassword(Motorista $motorista, string $newPassword, string $passwordConfirmation): array
    {
        $newPassword = (string) $newPassword;
        $passwordConfirmation = (string) $passwordConfirmation;

        if (trim($newPassword) === '' || trim($passwordConfirmation) === '') {
            throw new PortalMotoristaApiException('Informe e confirme a nova senha.', 422, 'validation_error');
        }

        if ($newPassword !== $passwordConfirmation) {
            throw new PortalMotoristaApiException('As senhas informadas nao conferem.', 422, 'validation_error');
        }

        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new PortalMotoristaApiException('Use pelo menos 6 caracteres na nova senha.', 422, 'validation_error');
        }

        return PortalMotoristaSupportService::withSampleTransaction(function () use ($motorista, $newPassword) {
            Motorista::ensureTables();

            $record = new Motorista((int) $motorista->id);
            if (empty($record->id)) {
                PortalMotoristaSupportService::clearPortalSession();
                throw new PortalMotoristaApiException('Motorista nao encontrado.', 404, 'driver_not_found');
            }

            $record->senha_portal = password_hash($newPassword, PASSWORD_DEFAULT);
            $record->senha_portal_temporaria = 0;
            $record->store();

            PortalMotoristaSupportService::setPortalSession($record);

            return [
                'message' => 'Senha atualizada com sucesso.',
                'driver' => PortalMotoristaSupportService::buildDriverProfile($record),
            ];
        });
    }

    public static function requiresPasswordChange(Motorista $motorista): bool
    {
        return (int) ($motorista->senha_portal_temporaria ?? 0) === 1;
    }

    public static function logout(): void
    {
        PortalMotoristaSupportService::clearPortalSession();
    }

    public static function getAuthenticatedMotorista(): Motorista
    {
        $token = self::extractBearerToken();

        if ($token !== null && $token !== '') {
            return PortalMotoristaSupportService::withSampleTransaction(function () use ($token) {
                Motorista::ensureTables();

                try {
                    $payload = PortalMotoristaTokenService::decode($token);
                } catch (Throwable $e) {
                    throw new PortalMotoristaApiException('Token invalido ou expirado.', 401, 'invalid_token');
                }

                $motoristaId = (int) ($payload['sub'] ?? 0);
                if ($motoristaId <= 0) {
                    throw new PortalMotoristaApiException('Token invalido.', 401, 'invalid_token');
                }

                $motorista = new Motorista($motoristaId);
                if (empty($motorista->id)) {
                    throw new PortalMotoristaApiException('Motorista nao encontrado.', 401, 'driver_not_found');
                }

                PortalMotoristaSupportService::setPortalSession($motorista);
                return $motorista;
            });
        }

        $sessionMotoristaId = (int) (TSession::getValue('portal_motorista_id') ?: 0);
        if ($sessionMotoristaId <= 0) {
            throw new PortalMotoristaApiException('Nao autenticado.', 401, 'not_authenticated');
        }

        return PortalMotoristaSupportService::withSampleTransaction(function () use ($sessionMotoristaId) {
            Motorista::ensureTables();

            $motorista = new Motorista($sessionMotoristaId);
            if (empty($motorista->id)) {
                PortalMotoristaSupportService::clearPortalSession();
                throw new PortalMotoristaApiException('Motorista nao encontrado.', 401, 'driver_not_found');
            }

            PortalMotoristaSupportService::setPortalSession($motorista);
            return $motorista;
        });
    }

    public static function me(): array
    {
        $motorista = self::getAuthenticatedMotorista();

        return [
            'driver' => PortalMotoristaSupportService::buildDriverProfile($motorista),
            'legacy' => [
                'login_url' => PortalMotoristaSupportService::buildApplicationUrl('index.php', ['class' => 'LoginForm']),
                'portal_url' => PortalMotoristaSupportService::buildApplicationUrl('portal-motorista/'),
            ],
        ];
    }

    private static function buildTemporaryAccessMessage(Motorista $motorista, string $temporaryPassword, string $portalUrl): string
    {
        $lines = [
            'Ola ' . PortalMotoristaSupportService::extractFirstName((string) ($motorista->nome ?? 'Motorista')) . '! Seu cadastro no Portal do Motorista foi aprovado.',
            '',
            'Acesse por este link:',
            $portalUrl,
            '',
            'Telefone: ' . (string) ($motorista->telefone ?? ''),
            'Senha temporaria: ' . $temporaryPassword,
            '',
            'No primeiro acesso, cadastre sua nova senha para continuar usando o portal.',
            'Se o link nao abrir, copie e cole no navegador.',
        ];

        return implode("\n", $lines);
    }

    private static function generateTemporaryPassword(int $length = 8): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $maxIndex = strlen($alphabet) - 1;
        $password = '';

        for ($index = 0; $index < $length; $index++) {
            $password .= $alphabet[random_int(0, $maxIndex)];
        }

        return $password;
    }

    private static function extractBearerToken(): ?string
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        }

        $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);
        if (!$authorization || !preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
            return null;
        }

        return trim((string) $matches[1]);
    }
}
