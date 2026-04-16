<?php

class PortalMotoristaSupportService
{
    public static function withSampleTransaction(callable $callback)
    {
        TTransaction::open('sample');

        try {
            $result = $callback(TTransaction::get());
            TTransaction::close();
            return $result;
        } catch (Throwable $e) {
            TTransaction::rollback();
            throw $e;
        }
    }

    public static function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone);
    }

    public static function findMotoristaRowByPhone(PDO $connection, string $phone): ?array
    {
        $normalizedPhone = self::normalizePhone($phone);
        if ($normalizedPhone === '') {
            return null;
        }

        $stmt = $connection->query('SELECT id, nome, telefone, email, cpf, senha_portal, cnh_numero FROM motorista WHERE telefone IS NOT NULL');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($rows as $row) {
            if (self::normalizePhone((string) ($row['telefone'] ?? '')) === $normalizedPhone) {
                return $row;
            }
        }

        return null;
    }

    public static function isPasswordHash(string $value): bool
    {
        $info = password_get_info($value);
        return !empty($info['algo']);
    }

    public static function setPortalSession(Motorista $motorista): void
    {
        TSession::setValue('portal_motorista_logged', true);
        TSession::setValue('portal_motorista_id', (int) $motorista->id);
        TSession::setValue('portal_motorista_nome', (string) ($motorista->nome ?? 'Motorista'));
    }

    public static function clearPortalSession(): void
    {
        TSession::setValue('portal_motorista_logged', false);
        TSession::setValue('portal_motorista_id', null);
        TSession::setValue('portal_motorista_nome', null);
    }

    public static function buildDriverProfile(Motorista $motorista): array
    {
        return [
            'id' => (int) $motorista->id,
            'nome' => (string) ($motorista->nome ?? ''),
            'primeiro_nome' => self::extractFirstName((string) ($motorista->nome ?? 'Motorista')),
            'telefone' => (string) ($motorista->telefone ?? ''),
            'email' => (string) ($motorista->email ?? ''),
            'cpf' => (string) ($motorista->cpf ?? ''),
            'cnh_numero' => (string) ($motorista->cnh_numero ?? ''),
            'must_change_password' => (int) ($motorista->senha_portal_temporaria ?? 0) === 1,
        ];
    }

    public static function extractFirstName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        return $parts && !empty($parts[0]) ? $parts[0] : 'Motorista';
    }

    public static function buildVehicleOptions(int $motoristaId): array
    {
        $repo = new TRepository('Veiculo');
        $criteria = new TCriteria;
        $criteria->add(new TFilter('motorista_id', '=', $motoristaId));
        $criteria->setProperty('order', 'id desc');

        $items = $repo->load($criteria, false) ?: [];
        $options = [];

        foreach ($items as $veiculo) {
            $semiPlate = (string) ($veiculo->antt_consulta_semi_reboque->placa ?? '');
            $label = trim((string) ($veiculo->placa_trator ?: 'Sem placa'));
            if ($semiPlate !== '') {
                $label .= ' / ' . $semiPlate;
            }

            $options[] = [
                'id' => (int) $veiculo->id,
                'label' => $label,
                'placa_trator' => (string) ($veiculo->placa_trator ?? ''),
                'placa_semi' => $semiPlate,
            ];
        }

        return $options;
    }

    public static function assertVehicleBelongsToMotorista(PDO $connection, int $veiculoId, int $motoristaId): void
    {
        $stmt = $connection->prepare('SELECT id FROM veiculo WHERE id = ? AND motorista_id = ?');
        $stmt->execute([$veiculoId, $motoristaId]);

        if (!$stmt->fetchColumn()) {
            throw new PortalMotoristaApiException('Veiculo nao localizado para este motorista.', 403, 'vehicle_forbidden');
        }
    }

    public static function assertContratoBelongsToMotorista(PDO $connection, int $contratoId, int $motoristaId): void
    {
        $stmt = $connection->prepare('
            SELECT c.id
            FROM contrato c
            INNER JOIN veiculo v ON v.id = c.veiculo_id
            WHERE c.id = ? AND v.motorista_id = ?
            LIMIT 1
        ');
        $stmt->execute([$contratoId, $motoristaId]);

        if (!$stmt->fetchColumn()) {
            throw new PortalMotoristaApiException('Contrato nao localizado para este motorista.', 403, 'contract_forbidden');
        }
    }

    public static function getApplicationBasePath(): string
    {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $suffixes = [
            '/api/portal-motorista/index.php',
            '/portal-motorista/index.php',
            '/index.php',
            '/download.php',
        ];

        foreach ($suffixes as $suffix) {
            if ($suffix !== '' && str_ends_with($scriptName, $suffix)) {
                $basePath = substr($scriptName, 0, -strlen($suffix));
                return $basePath === '/' ? '' : rtrim($basePath, '/');
            }
        }

        $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');
        return $dir === '/' ? '' : $dir;
    }

    public static function buildApplicationUrl(string $path, array $query = []): string
    {
        $basePath = self::getApplicationBasePath();
        $url = ($basePath !== '' ? $basePath : '') . '/' . ltrim($path, '/');

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    public static function buildAbsoluteApplicationUrl(string $path, array $query = []): string
    {
        $url = self::buildApplicationUrl($path, $query);

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        if ($host === '') {
            return $url;
        }

        return self::detectRequestScheme() . '://' . $host . '/' . ltrim($url, '/');
    }

    public static function buildWhatsAppUrl(?string $phone, string $message): ?string
    {
        $normalizedPhone = self::normalizePhone($phone);
        if ($normalizedPhone === '') {
            return null;
        }

        if (strlen($normalizedPhone) <= 11) {
            $normalizedPhone = '55' . $normalizedPhone;
        }

        return 'https://wa.me/' . $normalizedPhone . '?text=' . rawurlencode($message);
    }

    public static function buildDocumentDownloadUrl(PortalMotoristaDocumento $document): string
    {
        return self::buildApplicationUrl('download.php', [
            'file' => (string) $document->arquivo,
            'basename' => (string) ($document->arquivo_original ?: basename((string) $document->arquivo)),
        ]);
    }

    public static function buildContratoPrintUrl(int $contratoId): string
    {
        return self::buildApplicationUrl('index.php', [
            'class' => 'PortalMotoristaContratos',
            'method' => 'onPrint',
            'key' => $contratoId,
        ]);
    }

    public static function ensureDirectory(string $path): string
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    public static function formatDate(?string $value, string $mask = 'd/m/Y'): ?string
    {
        if (empty($value)) {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date($mask, $timestamp);
    }

    public static function formatCurrency($value): string
    {
        if (!is_numeric($value)) {
            return 'A combinar';
        }

        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    public static function buildPagination(int $page, int $pageSize, int $total): array
    {
        $totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 1;

        return [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => max(1, $totalPages),
            'has_previous' => $page > 1,
            'has_next' => ($page * $pageSize) < $total,
        ];
    }

    private static function detectRequestScheme(): string
    {
        $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if (in_array($forwardedProto, ['http', 'https'], true)) {
            return $forwardedProto;
        }

        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return 'https';
        }

        $scheme = strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? ''));
        if (in_array($scheme, ['http', 'https'], true)) {
            return $scheme;
        }

        return ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http';
    }
}
