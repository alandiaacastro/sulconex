<?php

class PortalMotoristaRastreioAdminService
{
    public static function fetchSnapshot(array $filters = []): array
    {
        $connection = TTransaction::get();
        if (!$connection instanceof PDO) {
            throw new RuntimeException('Transacao nao inicializada para carregar o rastreio do portal.');
        }

        Contrato::addColumnsIfNotExists($connection);
        Motorista::ensureTables();
        self::ensureTables($connection);

        $rows = $connection->query('
            SELECT
                r.id,
                r.motorista_id,
                r.contrato_id,
                r.latitude,
                r.longitude,
                r.precisao,
                r.created_at,
                c.conhecimento_numero,
                c.origem1,
                c.destino1,
                c.emissao,
                c.vencimento,
                c.saldo1,
                v.placa_trator,
                m.nome AS motorista_nome,
                m.telefone AS motorista_telefone
            FROM portal_motorista_rastreio r
            INNER JOIN (
                SELECT MAX(id) AS id
                FROM portal_motorista_rastreio
                GROUP BY contrato_id
            ) latest ON latest.id = r.id
            LEFT JOIN contrato c ON c.id = r.contrato_id
            LEFT JOIN veiculo v ON v.id = c.veiculo_id
            LEFT JOIN motorista m ON m.id = r.motorista_id
            ORDER BY datetime(r.created_at) DESC, r.id DESC
        ')->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $sanitizedFilters = self::sanitizeFilters($filters);
        $items = [];
        $latestTimestamp = null;

        foreach ($rows as $row) {
            $item = self::buildItem($row);
            if (!self::matchesFilters($item, $sanitizedFilters)) {
                continue;
            }

            $items[] = $item;

            $timestamp = strtotime((string) ($item['created_at'] ?? ''));
            if ($timestamp !== false && ($latestTimestamp === null || $timestamp > $latestTimestamp)) {
                $latestTimestamp = $timestamp;
            }
        }

        return [
            'filters' => $sanitizedFilters,
            'summary' => self::buildSummary($items, $latestTimestamp),
            'items' => $items,
            'markers' => array_values(array_map([self::class, 'buildMarker'], $items)),
        ];
    }

    public static function ensureTables(PDO $connection): void
    {
        $connection->exec('
            CREATE TABLE IF NOT EXISTS portal_motorista_rastreio (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                motorista_id INTEGER NOT NULL,
                contrato_id INTEGER NOT NULL,
                latitude REAL NOT NULL,
                longitude REAL NOT NULL,
                precisao REAL,
                created_at TEXT
            )
        ');

        $connection->exec('CREATE INDEX IF NOT EXISTS idx_pmr_motorista ON portal_motorista_rastreio(motorista_id)');
        $connection->exec('CREATE INDEX IF NOT EXISTS idx_pmr_contrato ON portal_motorista_rastreio(contrato_id)');
    }

    private static function sanitizeFilters(array $filters): array
    {
        $status = (string) ($filters['status_atualizacao'] ?? 'all');
        if (!in_array($status, ['all', 'recent_24h', 'stale_24h'], true)) {
            $status = 'all';
        }

        return [
            'contrato_id' => max(0, (int) ($filters['contrato_id'] ?? $filters['key'] ?? 0)),
            'busca' => trim((string) ($filters['busca'] ?? '')),
            'status_atualizacao' => $status,
        ];
    }

    private static function buildItem(array $row): array
    {
        $contratoId = (int) ($row['contrato_id'] ?? 0);
        $latitude = isset($row['latitude']) ? (float) $row['latitude'] : 0.0;
        $longitude = isset($row['longitude']) ? (float) $row['longitude'] : 0.0;
        $precisao = isset($row['precisao']) && $row['precisao'] !== null ? (float) $row['precisao'] : null;
        $createdAt = (string) ($row['created_at'] ?? '');
        $ageHours = self::calculateAgeHours($createdAt);
        $isRecent = $ageHours !== null && $ageHours <= 24;
        $crt = trim((string) ($row['conhecimento_numero'] ?? ''));
        $origem = trim((string) ($row['origem1'] ?? ''));
        $destino = trim((string) ($row['destino1'] ?? ''));
        $motoristaNome = trim((string) ($row['motorista_nome'] ?? ''));

        $trackingUrl = null;
        if ($crt !== '') {
            $trackingUrl = PortalMotoristaSupportService::buildApplicationUrl('index.php', [
                'class' => 'RastreioCompletoView',
                'numero' => $crt,
                'lat' => $latitude,
                'lon' => $longitude,
            ]);
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'contrato_id' => $contratoId,
            'motorista_id' => (int) ($row['motorista_id'] ?? 0),
            'motorista_nome' => $motoristaNome !== '' ? $motoristaNome : 'Motorista nao identificado',
            'motorista_telefone' => trim((string) ($row['motorista_telefone'] ?? '')),
            'crt' => $crt !== '' ? $crt : '-',
            'placa_trator' => trim((string) ($row['placa_trator'] ?? '')) ?: '-',
            'origem' => $origem !== '' ? $origem : '-',
            'destino' => $destino !== '' ? $destino : '-',
            'rota' => trim(($origem !== '' ? $origem : '-') . ' -> ' . ($destino !== '' ? $destino : '-')),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'precisao' => $precisao,
            'precisao_label' => $precisao !== null ? number_format($precisao, 0, ',', '.') . ' m' : 'Nao informada',
            'created_at' => $createdAt,
            'created_at_label' => PortalMotoristaSupportService::formatDate($createdAt, 'd/m/Y H:i') ?: '-',
            'age_hours' => $ageHours,
            'update_label' => self::buildUpdateLabel($ageHours),
            'is_recent' => $isRecent,
            'status_class' => $isRecent ? 'is-fresh' : 'is-stale',
            'saldo_label' => PortalMotoristaSupportService::formatCurrency($row['saldo1'] ?? 0),
            'emissao_label' => PortalMotoristaSupportService::formatDate((string) ($row['emissao'] ?? ''), 'd/m/Y') ?: '-',
            'vencimento_label' => PortalMotoristaSupportService::formatDate((string) ($row['vencimento'] ?? ''), 'd/m/Y') ?: '-',
            'maps_url' => 'https://www.google.com/maps?q=' . $latitude . ',' . $longitude,
            'contrato_edit_url' => PortalMotoristaSupportService::buildApplicationUrl('index.php', [
                'class' => 'ContratoForm',
                'method' => 'onEdit',
                'key' => $contratoId,
            ]),
            'contrato_pdf_url' => PortalMotoristaSupportService::buildApplicationUrl('index.php', [
                'class' => 'ContratoRelatorio',
                'method' => 'onGenerate',
                'key' => $contratoId,
            ]),
            'tracking_url' => $trackingUrl,
        ];
    }

    private static function matchesFilters(array $item, array $filters): bool
    {
        if ((int) $filters['contrato_id'] > 0 && (int) $item['contrato_id'] !== (int) $filters['contrato_id']) {
            return false;
        }

        $status = (string) ($filters['status_atualizacao'] ?? 'all');
        if ($status === 'recent_24h' && !$item['is_recent']) {
            return false;
        }
        if ($status === 'stale_24h' && $item['is_recent']) {
            return false;
        }

        $search = trim((string) ($filters['busca'] ?? ''));
        if ($search === '') {
            return true;
        }

        $haystack = self::normalizeSearchValue(implode(' ', [
            (string) $item['contrato_id'],
            (string) $item['crt'],
            (string) $item['motorista_nome'],
            (string) $item['motorista_telefone'],
            (string) $item['placa_trator'],
            (string) $item['origem'],
            (string) $item['destino'],
        ]));

        return strpos($haystack, self::normalizeSearchValue($search)) !== false;
    }

    private static function buildSummary(array $items, ?int $latestTimestamp): array
    {
        $drivers = [];
        $freshCount = 0;
        foreach ($items as $item) {
            if ((int) $item['motorista_id'] > 0) {
                $drivers[(int) $item['motorista_id']] = true;
            }
            if (!empty($item['is_recent'])) {
                $freshCount++;
            }
        }

        $total = count($items);
        $staleCount = max(0, $total - $freshCount);

        return [
            'total_contratos' => $total,
            'motoristas_monitorados' => count($drivers),
            'atualizados_24h' => $freshCount,
            'sem_atualizacao_24h' => $staleCount,
            'latest_update_label' => $latestTimestamp ? date('d/m/Y H:i', $latestTimestamp) : '-',
            'summary_text' => $total > 0
                ? $total . ' contrato(s) com ultima posicao disponivel no portal.'
                : 'Nenhuma posicao recebida do portal do motorista ate o momento.',
        ];
    }

    private static function buildMarker(array $item): array
    {
        $popupHtml = sprintf(
            '<div style="min-width:220px"><div style="font-weight:700;color:#0f172a;margin-bottom:6px">%s</div><div style="font-size:12px;color:#334155;line-height:1.5"><div><strong>Contrato:</strong> #%d</div><div><strong>CRT:</strong> %s</div><div><strong>Veiculo:</strong> %s</div><div><strong>Rota:</strong> %s</div><div><strong>Atualizacao:</strong> %s</div></div></div>',
            htmlspecialchars((string) $item['motorista_nome'], ENT_QUOTES, 'UTF-8'),
            (int) $item['contrato_id'],
            htmlspecialchars((string) $item['crt'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $item['placa_trator'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $item['rota'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $item['created_at_label'], ENT_QUOTES, 'UTF-8')
        );

        return [
            'lat' => (float) $item['latitude'],
            'lon' => (float) $item['longitude'],
            'title' => $item['motorista_nome'],
            'subtitle' => 'Contrato #' . (int) $item['contrato_id'] . ' - ' . (string) $item['placa_trator'],
            'is_recent' => !empty($item['is_recent']),
            'popup_html' => $popupHtml,
        ];
    }

    private static function calculateAgeHours(string $createdAt): ?float
    {
        if ($createdAt === '') {
            return null;
        }

        $timestamp = strtotime($createdAt);
        if ($timestamp === false) {
            return null;
        }

        return max(0, (time() - $timestamp) / 3600);
    }

    private static function buildUpdateLabel(?float $ageHours): string
    {
        if ($ageHours === null) {
            return 'Horario nao informado';
        }

        if ($ageHours < 1) {
            return 'Atualizado agora';
        }

        $roundedHours = (int) floor($ageHours);
        if ($roundedHours < 24) {
            return 'Atualizado ha ' . max(1, $roundedHours) . 'h';
        }

        $days = (int) floor($ageHours / 24);
        if ($days < 2) {
            return 'Sem atualizacao ha 1 dia';
        }

        return 'Sem atualizacao ha ' . $days . ' dias';
    }

    private static function normalizeSearchValue(string $value): string
    {
        $value = trim($value);
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}