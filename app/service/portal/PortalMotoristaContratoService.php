<?php

class PortalMotoristaContratoService
{
    public static function listForMotorista(Motorista $motorista): array
    {
        return PortalMotoristaSupportService::withSampleTransaction(function (PDO $connection) use ($motorista) {
            Contrato::addColumnsIfNotExists($connection);

            $stmt = $connection->prepare('
                SELECT
                    c.id,
                    c.conhecimento_numero,
                    c.origem1,
                    c.destino1,
                    c.emissao,
                    c.vencimento,
                    c.frete1,
                    c.saldo1,
                    c.pago,
                    c.dta_efet_pg,
                    v.placa_trator
                FROM contrato c
                INNER JOIN veiculo v ON v.id = c.veiculo_id
                WHERE v.motorista_id = ?
                ORDER BY c.id DESC
            ');
            $stmt->execute([(int) $motorista->id]);

            $items = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $isPaid = ((string) ($row['pago'] ?? '') === 'S') || !empty($row['dta_efet_pg']);

                $items[] = [
                    'id' => (int) $row['id'],
                    'conhecimento_numero' => (string) ($row['conhecimento_numero'] ?? ''),
                    'origem' => (string) ($row['origem1'] ?? ''),
                    'destino' => (string) ($row['destino1'] ?? ''),
                    'rota' => trim((string) (($row['origem1'] ?? '-') . ' -> ' . ($row['destino1'] ?? '-'))),
                    'placa_trator' => (string) ($row['placa_trator'] ?? ''),
                    'emissao' => (string) ($row['emissao'] ?? ''),
                    'emissao_label' => PortalMotoristaSupportService::formatDate((string) ($row['emissao'] ?? '')) ?: '-',
                    'vencimento' => (string) ($row['vencimento'] ?? ''),
                    'vencimento_label' => PortalMotoristaSupportService::formatDate((string) ($row['vencimento'] ?? '')) ?: '-',
                    'frete' => is_numeric($row['frete1'] ?? null) ? (float) $row['frete1'] : 0.0,
                    'frete_label' => PortalMotoristaSupportService::formatCurrency($row['frete1'] ?? 0),
                    'saldo' => is_numeric($row['saldo1'] ?? null) ? (float) $row['saldo1'] : 0.0,
                    'saldo_label' => PortalMotoristaSupportService::formatCurrency($row['saldo1'] ?? 0),
                    'status' => $isPaid ? 'pago' : 'aberto',
                    'status_label' => $isPaid ? 'Pago' : 'Aberto',
                    'print_url' => PortalMotoristaSupportService::buildContratoPrintUrl((int) $row['id']),
                ];
            }

            return ['items' => $items];
        });
    }
}