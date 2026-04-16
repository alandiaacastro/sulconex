<?php

class PortalMotoristaDashboardService
{
    public static function fetch(Motorista $motorista): array
    {
        return PortalMotoristaSupportService::withSampleTransaction(function (PDO $connection) use ($motorista) {
            CargaDisponivel::ensureTables();
            SolicitacaoCarga::ensureTables();
            PortalMotoristaDocumento::ensureTables();
            Contrato::addColumnsIfNotExists($connection);
            Motorista::ensureTables();

            $cargasDisponiveis = (int) $connection->query(
                "SELECT COUNT(*) FROM carga_disponivel WHERE status = 'disponivel'"
            )->fetchColumn();

            $stmt = $connection->prepare('SELECT COUNT(*) FROM solicitacao_carga WHERE motorista_id = ?');
            $stmt->execute([(int) $motorista->id]);
            $minhasSolicitacoes = (int) $stmt->fetchColumn();

            $stmt = $connection->prepare('
                SELECT COUNT(*)
                FROM contrato c
                INNER JOIN veiculo v ON v.id = c.veiculo_id
                WHERE v.motorista_id = ?
                  AND (COALESCE(c.pago, "") <> "S" OR c.dta_efet_pg IS NULL OR c.dta_efet_pg = "")
            ');
            $stmt->execute([(int) $motorista->id]);
            $emAndamento = (int) $stmt->fetchColumn();

            $stmt = $connection->prepare('SELECT COUNT(*) FROM portal_motorista_documento WHERE motorista_id = ?');
            $stmt->execute([(int) $motorista->id]);
            $documentos = (int) $stmt->fetchColumn();

            return [
                'driver' => PortalMotoristaSupportService::buildDriverProfile($motorista),
                'kpis' => [
                    'cargas_disponiveis' => $cargasDisponiveis,
                    'em_andamento' => $emAndamento,
                    'solicitacoes' => $minhasSolicitacoes,
                    'documentos' => $documentos,
                ],
                'alerts' => self::buildDocumentAlerts((int) $motorista->id),
            ];
        });
    }

    public static function buildDocumentAlerts(int $motoristaId): array
    {
        $alerts = [];

        if (!PortalMotoristaDocumento::findByContext($motoristaId, PortalMotoristaDocumento::TIPO_CNH)) {
            $alerts[] = 'CNH ainda nao enviada.';
        }

        $repo = new TRepository('Veiculo');
        $criteria = new TCriteria;
        $criteria->add(new TFilter('motorista_id', '=', $motoristaId));
        $criteria->setProperty('order', 'id desc');
        $veiculos = $repo->load($criteria, false) ?: [];

        foreach ($veiculos as $veiculo) {
            $placa = (string) ($veiculo->placa_trator ?: ('#' . $veiculo->id));

            if (!PortalMotoristaDocumento::findByContext($motoristaId, PortalMotoristaDocumento::TIPO_CAVALO, (int) $veiculo->id)) {
                $alerts[] = "Documento do cavalo faltando para o veiculo {$placa}.";
            }

            $semiPlate = (string) ($veiculo->antt_consulta_semi_reboque->placa ?? '');
            if ($semiPlate !== '' && !PortalMotoristaDocumento::findByContext($motoristaId, PortalMotoristaDocumento::TIPO_SEMI_REBOQUE, (int) $veiculo->id)) {
                $alerts[] = "Documento do semi-reboque faltando para o veiculo {$placa}.";
            }
        }

        return $alerts;
    }
}