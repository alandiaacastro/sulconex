<?php

class PortalMotoristaSolicitacaoService
{
    public static function listForMotorista(Motorista $motorista, array $params): array
    {
        return PortalMotoristaSupportService::withSampleTransaction(function (PDO $connection) use ($motorista, $params) {
            SolicitacaoCarga::ensureTables();

            $status = trim((string) ($params['status'] ?? ''));
            $page = max(1, (int) ($params['page'] ?? 1));
            $pageSize = max(1, min(50, (int) ($params['page_size'] ?? 15)));
            $offset = ($page - 1) * $pageSize;

            $where = ['s.motorista_id = ?'];
            $bindings = [(int) $motorista->id];

            if ($status !== '') {
                $where[] = 's.status = ?';
                $bindings[] = $status;
            }

            $whereSql = implode(' AND ', $where);

            $countStmt = $connection->prepare("SELECT COUNT(*) FROM solicitacao_carga s WHERE {$whereSql}");
            $countStmt->execute($bindings);
            $total = (int) $countStmt->fetchColumn();

            $sql = "
                SELECT
                    s.id,
                    s.carga_disponivel_id,
                    s.veiculo_id,
                    s.mensagem,
                    s.data_disponibilidade,
                    s.status,
                    s.resposta_admin,
                    s.created_at,
                    c.origem,
                    c.destino,
                    c.tipo_carga,
                    c.tipo_veiculo,
                    v.placa_trator
                FROM solicitacao_carga s
                LEFT JOIN carga_disponivel c ON c.id = s.carga_disponivel_id
                LEFT JOIN veiculo v ON v.id = s.veiculo_id
                WHERE {$whereSql}
                ORDER BY s.id DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $connection->prepare($sql);
            $positionedBindings = array_merge($bindings, [$pageSize, $offset]);
            foreach ($positionedBindings as $index => $value) {
                $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();

            $items = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $statusValue = (string) ($row['status'] ?? '');
                $items[] = [
                    'id' => (int) $row['id'],
                    'carga_id' => (int) ($row['carga_disponivel_id'] ?? 0),
                    'rota' => trim((string) (($row['origem'] ?? '-') . ' -> ' . ($row['destino'] ?? '-'))),
                    'origem' => (string) ($row['origem'] ?? ''),
                    'destino' => (string) ($row['destino'] ?? ''),
                    'tipo_carga_label' => CargaDisponivel::getTipoCargaItems()[$row['tipo_carga'] ?? ''] ?? ((string) ($row['tipo_carga'] ?? '-')),
                    'tipo_veiculo_label' => CargaDisponivel::getTipoVeiculoItems()[$row['tipo_veiculo'] ?? ''] ?? ((string) ($row['tipo_veiculo'] ?? '-')),
                    'veiculo_id' => !empty($row['veiculo_id']) ? (int) $row['veiculo_id'] : null,
                    'placa_trator' => (string) ($row['placa_trator'] ?? ''),
                    'mensagem' => (string) ($row['mensagem'] ?? ''),
                    'data_disponibilidade' => (string) ($row['data_disponibilidade'] ?? ''),
                    'data_disponibilidade_label' => PortalMotoristaSupportService::formatDate((string) ($row['data_disponibilidade'] ?? '')) ?: '-',
                    'status' => $statusValue,
                    'status_label' => SolicitacaoCarga::getStatusLabels()[$statusValue] ?? $statusValue,
                    'resposta_admin' => (string) ($row['resposta_admin'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'created_at_label' => PortalMotoristaSupportService::formatDate((string) ($row['created_at'] ?? ''), 'd/m/Y H:i') ?: '-',
                    'can_cancel' => in_array($statusValue, [SolicitacaoCarga::STATUS_PENDENTE, SolicitacaoCarga::STATUS_EM_ANALISE], true),
                ];
            }

            return [
                'items' => $items,
                'stats' => self::buildStats($connection, (int) $motorista->id),
                'status_filter' => $status,
                'status_options' => self::mapStatusOptions(),
                'pagination' => PortalMotoristaSupportService::buildPagination($page, $pageSize, $total),
            ];
        });
    }

    public static function cancel(Motorista $motorista, int $solicitacaoId): array
    {
        if ($solicitacaoId <= 0) {
            throw new PortalMotoristaApiException('Solicitacao invalida.', 422, 'validation_error');
        }

        return PortalMotoristaSupportService::withSampleTransaction(function () use ($motorista, $solicitacaoId) {
            SolicitacaoCarga::ensureTables();

            $solicitacao = new SolicitacaoCarga($solicitacaoId);
            if (empty($solicitacao->id) || (int) $solicitacao->motorista_id !== (int) $motorista->id) {
                throw new PortalMotoristaApiException('Solicitacao nao localizada.', 404, 'request_not_found');
            }

            if (!in_array((string) $solicitacao->status, [SolicitacaoCarga::STATUS_PENDENTE, SolicitacaoCarga::STATUS_EM_ANALISE], true)) {
                throw new PortalMotoristaApiException('Esta solicitacao nao pode mais ser cancelada.', 409, 'request_cannot_cancel');
            }

            $solicitacao->status = SolicitacaoCarga::STATUS_CANCELADO;
            $solicitacao->store();

            return [
                'id' => (int) $solicitacao->id,
                'status' => (string) $solicitacao->status,
                'status_label' => SolicitacaoCarga::getStatusLabels()[$solicitacao->status] ?? (string) $solicitacao->status,
                'message' => 'Solicitacao cancelada com sucesso.',
            ];
        });
    }

    private static function buildStats(PDO $connection, int $motoristaId): array
    {
        $stmt = $connection->prepare('SELECT status, COUNT(*) AS total FROM solicitacao_carga WHERE motorista_id = ? GROUP BY status');
        $stmt->execute([$motoristaId]);

        $counts = [
            SolicitacaoCarga::STATUS_PENDENTE => 0,
            SolicitacaoCarga::STATUS_EM_ANALISE => 0,
            SolicitacaoCarga::STATUS_APROVADO => 0,
            SolicitacaoCarga::STATUS_RECUSADO => 0,
        ];
        $total = 0;

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $statusValue = (string) ($row['status'] ?? '');
            $count = (int) ($row['total'] ?? 0);
            $counts[$statusValue] = $count;
            $total += $count;
        }

        return [
            'total' => $total,
            'pendentes' => $counts[SolicitacaoCarga::STATUS_PENDENTE] + $counts[SolicitacaoCarga::STATUS_EM_ANALISE],
            'aprovadas' => $counts[SolicitacaoCarga::STATUS_APROVADO],
            'recusadas' => $counts[SolicitacaoCarga::STATUS_RECUSADO],
        ];
    }

    private static function mapStatusOptions(): array
    {
        $options = [];

        foreach (SolicitacaoCarga::getStatusLabels() as $value => $label) {
            $options[] = [
                'value' => (string) $value,
                'label' => (string) $label,
            ];
        }

        return $options;
    }
}