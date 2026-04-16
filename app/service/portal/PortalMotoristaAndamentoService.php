<?php

class PortalMotoristaAndamentoService
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];

    public static function listForMotorista(Motorista $motorista): array
    {
        return PortalMotoristaSupportService::withSampleTransaction(function (PDO $connection) use ($motorista) {
            Contrato::addColumnsIfNotExists($connection);
            self::ensureTables($connection);

            $stmt = $connection->prepare('
                SELECT
                    c.id,
                    c.conhecimento_numero,
                    c.origem1,
                    c.destino1,
                    c.emissao,
                    c.vencimento,
                    c.saldo1,
                    c.pago,
                    c.dta_efet_pg,
                    v.placa_trator
                FROM contrato c
                INNER JOIN veiculo v ON v.id = c.veiculo_id
                WHERE v.motorista_id = ?
                  AND (COALESCE(c.pago, "") <> "S" OR c.dta_efet_pg IS NULL OR c.dta_efet_pg = "")
                ORDER BY c.id DESC
            ');
            $stmt->execute([(int) $motorista->id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $locations = self::loadLatestLocations($connection, (int) $motorista->id);
            $comprovantes = self::loadLatestComprovantes($connection, (int) $motorista->id);

            $items = [];
            foreach ($rows as $row) {
                $contractId = (int) $row['id'];
                $location = $locations[$contractId] ?? null;
                $comprovante = $comprovantes[$contractId] ?? null;

                $items[] = [
                    'id' => $contractId,
                    'crt' => (string) ($row['conhecimento_numero'] ?: '-'),
                    'origem' => (string) ($row['origem1'] ?? ''),
                    'destino' => (string) ($row['destino1'] ?? ''),
                    'placa_trator' => (string) ($row['placa_trator'] ?? ''),
                    'saldo_previsto' => is_numeric($row['saldo1'] ?? null) ? (float) $row['saldo1'] : 0.0,
                    'saldo_previsto_label' => PortalMotoristaSupportService::formatCurrency($row['saldo1'] ?? 0),
                    'emissao' => (string) ($row['emissao'] ?? ''),
                    'emissao_label' => PortalMotoristaSupportService::formatDate((string) ($row['emissao'] ?? '')) ?: '-',
                    'vencimento' => (string) ($row['vencimento'] ?? ''),
                    'vencimento_label' => PortalMotoristaSupportService::formatDate((string) ($row['vencimento'] ?? '')) ?: '-',
                    'status' => 'em_andamento',
                    'status_label' => 'Em andamento',
                    'print_url' => PortalMotoristaSupportService::buildContratoPrintUrl($contractId),
                    'localizacao' => $location ? self::formatLocation($connection, $location) : null,
                    'comprovante' => $comprovante ? [
                        'arquivo_original' => (string) ($comprovante['arquivo_original'] ?? ''),
                        'created_at' => (string) ($comprovante['created_at'] ?? ''),
                        'created_at_label' => PortalMotoristaSupportService::formatDate((string) ($comprovante['created_at'] ?? ''), 'd/m H:i') ?: '-',
                    ] : null,
                ];
            }

            return ['items' => $items];
        });
    }

    public static function saveLocation(Motorista $motorista, array $data): array
    {
        return PortalMotoristaSupportService::withSampleTransaction(function (PDO $connection) use ($motorista, $data) {
            self::ensureTables($connection);

            $latitude = isset($data['latitude']) ? (float) $data['latitude'] : null;
            $longitude = isset($data['longitude']) ? (float) $data['longitude'] : null;
            $precisao = isset($data['precisao']) ? (float) $data['precisao'] : null;
            $contratoId = (int) ($data['contrato_id'] ?? 0);

            if ($contratoId <= 0 || $latitude === null || $longitude === null) {
                throw new PortalMotoristaApiException('Dados de localizacao invalidos.', 422, 'validation_error');
            }

            PortalMotoristaSupportService::assertContratoBelongsToMotorista($connection, $contratoId, (int) $motorista->id);

            $stmt = $connection->prepare('
                INSERT INTO portal_motorista_rastreio
                    (motorista_id, contrato_id, latitude, longitude, precisao, created_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $createdAt = date('Y-m-d H:i:s');
            $stmt->execute([(int) $motorista->id, $contratoId, $latitude, $longitude, $precisao, $createdAt]);

            return [
                'message' => 'Localizacao enviada com sucesso.',
                'localizacao' => self::formatLocation($connection, [
                    'contrato_id' => $contratoId,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'precisao' => $precisao,
                    'created_at' => $createdAt,
                ]),
            ];
        });
    }

    public static function uploadComprovante(Motorista $motorista, array $data, array $files): array
    {
        return PortalMotoristaSupportService::withSampleTransaction(function (PDO $connection) use ($motorista, $data, $files) {
            self::ensureTables($connection);

            $contratoId = (int) ($data['contrato_id'] ?? 0);
            $observacao = trim((string) ($data['observacao'] ?? ''));

            if ($contratoId <= 0) {
                throw new PortalMotoristaApiException('Selecione um contrato para enviar o comprovante.', 422, 'validation_error');
            }

            PortalMotoristaSupportService::assertContratoBelongsToMotorista($connection, $contratoId, (int) $motorista->id);

            $uploadedFile = $files['arquivo'] ?? null;
            if (!is_array($uploadedFile) || (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new PortalMotoristaApiException('Selecione um arquivo valido.', 422, 'validation_error');
            }

            $extension = strtolower((string) pathinfo((string) ($uploadedFile['name'] ?? ''), PATHINFO_EXTENSION));
            $tmpName = (string) ($uploadedFile['tmp_name'] ?? '');

            if ($tmpName === '' || !is_file($tmpName)) {
                throw new PortalMotoristaApiException('Arquivo temporario nao encontrado.', 422, 'upload_not_found');
            }

            if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                throw new PortalMotoristaApiException('Formato invalido. Use JPG, PNG, WEBP, GIF ou PDF.', 422, 'invalid_file_type');
            }

            $directory = PortalMotoristaSupportService::ensureDirectory('tmp/portal_motorista_comprovantes');
            $filename = sprintf('comp_%d_%d_%s.%s', (int) $motorista->id, $contratoId, date('YmdHis'), $extension);
            $destination = $directory . '/' . $filename;

            if (!@copy($tmpName, $destination)) {
                throw new PortalMotoristaApiException('Nao foi possivel salvar o comprovante.', 500, 'upload_failed');
            }

            $createdAt = date('Y-m-d H:i:s');
            $stmt = $connection->prepare('
                INSERT INTO portal_motorista_comprovante
                    (motorista_id, contrato_id, arquivo, arquivo_original, mime_type, observacao, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                (int) $motorista->id,
                $contratoId,
                $destination,
                basename((string) $uploadedFile['name']),
                function_exists('mime_content_type') ? mime_content_type($destination) : null,
                $observacao !== '' ? $observacao : null,
                $createdAt,
            ]);

            return [
                'message' => 'Comprovante enviado com sucesso.',
                'comprovante' => [
                    'contrato_id' => $contratoId,
                    'arquivo_original' => basename((string) $uploadedFile['name']),
                    'created_at' => $createdAt,
                    'created_at_label' => PortalMotoristaSupportService::formatDate($createdAt, 'd/m H:i') ?: '-',
                ],
            ];
        });
    }

    private static function loadLatestLocations(PDO $connection, int $motoristaId): array
    {
        $stmt = $connection->prepare('
            SELECT contrato_id, latitude, longitude, precisao, created_at
            FROM portal_motorista_rastreio
            WHERE motorista_id = ?
            ORDER BY id DESC
        ');
        $stmt->execute([$motoristaId]);

        $locations = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $contractId = (int) ($row['contrato_id'] ?? 0);
            if ($contractId > 0 && !isset($locations[$contractId])) {
                $locations[$contractId] = $row;
            }
        }

        return $locations;
    }

    private static function loadLatestComprovantes(PDO $connection, int $motoristaId): array
    {
        $stmt = $connection->prepare('
            SELECT contrato_id, arquivo_original, created_at
            FROM portal_motorista_comprovante
            WHERE motorista_id = ?
            ORDER BY id DESC
        ');
        $stmt->execute([$motoristaId]);

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $contractId = (int) ($row['contrato_id'] ?? 0);
            if ($contractId > 0 && !isset($items[$contractId])) {
                $items[$contractId] = $row;
            }
        }

        return $items;
    }

    private static function formatLocation(PDO $connection, array $location): array
    {
        $latitude = isset($location['latitude']) ? (float) $location['latitude'] : null;
        $longitude = isset($location['longitude']) ? (float) $location['longitude'] : null;
        $resolved = ($latitude !== null && $longitude !== null)
            ? PortalMotoristaLocationResolver::describe($connection, $latitude, $longitude)
            : [];

        return array_merge([
            'contrato_id' => isset($location['contrato_id']) ? (int) $location['contrato_id'] : null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'precisao' => isset($location['precisao']) ? (float) $location['precisao'] : null,
            'created_at' => (string) ($location['created_at'] ?? ''),
            'created_at_label' => PortalMotoristaSupportService::formatDate((string) ($location['created_at'] ?? ''), 'd/m H:i') ?: '-',
            'maps_url' => ($latitude !== null && $longitude !== null)
                ? 'https://www.google.com/maps?q=' . $latitude . ',' . $longitude
                : null,
        ], $resolved);
    }

    private static function ensureTables(PDO $connection): void
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

        $connection->exec('
            CREATE TABLE IF NOT EXISTS portal_motorista_comprovante (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                motorista_id INTEGER NOT NULL,
                contrato_id INTEGER NOT NULL,
                arquivo TEXT NOT NULL,
                arquivo_original TEXT,
                mime_type TEXT,
                observacao TEXT,
                created_at TEXT
            )
        ');

        $connection->exec('CREATE INDEX IF NOT EXISTS idx_pmc_motorista ON portal_motorista_comprovante(motorista_id)');
        $connection->exec('CREATE INDEX IF NOT EXISTS idx_pmc_contrato ON portal_motorista_comprovante(contrato_id)');
    }
}
