<?php

class PortalMotoristaCargaService
{
    public static function listAvailable(Motorista $motorista, array $params): array
    {
        return PortalMotoristaSupportService::withSampleTransaction(function (PDO $connection) use ($motorista, $params) {
            CargaDisponivel::ensureTables();
            SolicitacaoCarga::ensureTables();

            $origem = trim((string) ($params['origem'] ?? ''));
            $destino = trim((string) ($params['destino'] ?? ''));
            $tipoVeiculo = trim((string) ($params['tipo_veiculo'] ?? ''));

            $page = max(1, (int) ($params['page'] ?? 1));
            $pageSize = max(1, min(30, (int) ($params['page_size'] ?? 9)));
            $offset = ($page - 1) * $pageSize;

            $where = ['status = ?'];
            $bindings = [CargaDisponivel::STATUS_DISPONIVEL];

            if ($origem !== '') {
                $where[] = 'origem LIKE ?';
                $bindings[] = '%' . $origem . '%';
            }

            if ($destino !== '') {
                $where[] = 'destino LIKE ?';
                $bindings[] = '%' . $destino . '%';
            }

            if ($tipoVeiculo !== '') {
                $where[] = 'tipo_veiculo = ?';
                $bindings[] = $tipoVeiculo;
            }

            $whereSql = implode(' AND ', $where);

            $countStmt = $connection->prepare("SELECT COUNT(*) FROM carga_disponivel WHERE {$whereSql}");
            $countStmt->execute($bindings);
            $total = (int) $countStmt->fetchColumn();

            $listStmt = $connection->prepare("SELECT id FROM carga_disponivel WHERE {$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?");
            $positionedBindings = array_merge($bindings, [$pageSize, $offset]);
            foreach ($positionedBindings as $index => $value) {
                $parameter = $index + 1;
                $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $listStmt->bindValue($parameter, $value, $type);
            }
            $listStmt->execute();
            $ids = array_map('intval', $listStmt->fetchAll(PDO::FETCH_COLUMN));

            $pendingMap = self::loadPendingSolicitacoesMap($connection, (int) $motorista->id, $ids);
            $items = [];

            foreach ($ids as $id) {
                $carga = new CargaDisponivel($id);

                $tipoCargaLabel = CargaDisponivel::getTipoCargaItems()[$carga->tipo_carga] ?? ($carga->tipo_carga ?: '-');
                $tipoVeiculoLabel = CargaDisponivel::getTipoVeiculoItems()[$carga->tipo_veiculo] ?? ($carga->tipo_veiculo ?: '-');
                $isUrgent = false;

                if (!empty($carga->data_coleta)) {
                    $diff = (strtotime((string) $carga->data_coleta) - time()) / 86400;
                    $isUrgent = $diff >= 0 && $diff <= 3;
                }

                $items[] = [
                    'id' => (int) $carga->id,
                    'titulo' => (string) ($carga->titulo ?? ''),
                    'origem' => (string) ($carga->origem ?? ''),
                    'destino' => (string) ($carga->destino ?? ''),
                    'tipo_carga' => (string) ($carga->tipo_carga ?? ''),
                    'tipo_carga_label' => $tipoCargaLabel,
                    'tipo_veiculo' => (string) ($carga->tipo_veiculo ?? ''),
                    'tipo_veiculo_label' => $tipoVeiculoLabel,
                    'aduana_destino' => (string) $carga->getAduanaDestinoDisplay(),
                    'peso_estimado_kg' => $carga->peso_estimado_kg !== null ? (float) $carga->peso_estimado_kg : null,
                    'peso_estimado_label' => is_numeric($carga->peso_estimado_kg) ? number_format((float) $carga->peso_estimado_kg, 0, ',', '.') . ' kg' : '-',
                    'valor_frete' => $carga->valor_frete !== null ? (float) $carga->valor_frete : null,
                    'valor_frete_label' => PortalMotoristaSupportService::formatCurrency($carga->valor_frete),
                    'data_coleta' => (string) ($carga->data_coleta ?? ''),
                    'data_coleta_label' => PortalMotoristaSupportService::formatDate((string) ($carga->data_coleta ?? '')) ?: '-',
                    'data_entrega_prevista' => (string) ($carga->data_entrega_prevista ?? ''),
                    'data_entrega_prevista_label' => PortalMotoristaSupportService::formatDate((string) ($carga->data_entrega_prevista ?? '')) ?: '-',
                    'descricao' => (string) ($carga->descricao ?? ''),
                    'localizacao_maps' => (string) ($carga->localizacao_maps ?? ''),
                    'observacoes' => (string) ($carga->observacoes ?? ''),
                    'is_urgent' => $isUrgent,
                    'has_pending_request' => !empty($pendingMap[(int) $carga->id]),
                ];
            }

            return [
                'filters' => [
                    'origem' => $origem,
                    'destino' => $destino,
                    'tipo_veiculo' => $tipoVeiculo,
                    'tipo_veiculo_options' => self::mapOptions(CargaDisponivel::getTipoVeiculoItems()),
                ],
                'vehicle_options' => PortalMotoristaSupportService::buildVehicleOptions((int) $motorista->id),
                'items' => $items,
                'pagination' => PortalMotoristaSupportService::buildPagination($page, $pageSize, $total),
            ];
        });
    }

    public static function createSolicitacao(Motorista $motorista, array $data): array
    {
        return PortalMotoristaSupportService::withSampleTransaction(function (PDO $connection) use ($motorista, $data) {
            SolicitacaoCarga::ensureTables();
            CargaDisponivel::ensureTables();

            $cargaId = (int) ($data['carga_id'] ?? 0);
            $veiculoId = !empty($data['veiculo_id']) ? (int) $data['veiculo_id'] : null;
            $mensagem = trim((string) ($data['mensagem'] ?? ''));
            $dataDisponibilidade = trim((string) ($data['data_disponibilidade'] ?? ''));

            if ($cargaId <= 0) {
                throw new PortalMotoristaApiException('Selecione uma carga para solicitar.', 422, 'validation_error');
            }

            if ($veiculoId) {
                PortalMotoristaSupportService::assertVehicleBelongsToMotorista($connection, $veiculoId, (int) $motorista->id);
            }

            $carga = new CargaDisponivel($cargaId);
            if (empty($carga->id) || (string) $carga->status !== CargaDisponivel::STATUS_DISPONIVEL) {
                throw new PortalMotoristaApiException('Carga indisponivel para solicitacao.', 409, 'cargo_not_available');
            }

            $stmt = $connection->prepare('
                SELECT COUNT(*)
                FROM solicitacao_carga
                WHERE carga_disponivel_id = ?
                  AND motorista_id = ?
                  AND status IN (?, ?)
            ');
            $stmt->execute([
                $cargaId,
                (int) $motorista->id,
                SolicitacaoCarga::STATUS_PENDENTE,
                SolicitacaoCarga::STATUS_EM_ANALISE,
            ]);

            if ((int) $stmt->fetchColumn() > 0) {
                throw new PortalMotoristaApiException('Voce ja possui uma solicitacao pendente para esta carga.', 409, 'request_exists');
            }

            $solicitacao = new SolicitacaoCarga;
            $solicitacao->carga_disponivel_id = $cargaId;
            $solicitacao->motorista_id = (int) $motorista->id;
            $solicitacao->veiculo_id = $veiculoId;
            $solicitacao->mensagem = $mensagem;
            $solicitacao->data_disponibilidade = $dataDisponibilidade !== '' ? $dataDisponibilidade : null;
            $solicitacao->store();

            return [
                'id' => (int) $solicitacao->id,
                'status' => (string) $solicitacao->status,
                'status_label' => SolicitacaoCarga::getStatusLabels()[$solicitacao->status] ?? (string) $solicitacao->status,
                'message' => 'Solicitacao enviada com sucesso.',
            ];
        });
    }

    private static function loadPendingSolicitacoesMap(PDO $connection, int $motoristaId, array $cargaIds): array
    {
        if (empty($cargaIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($cargaIds), '?'));
        $bindings = array_merge(
            [$motoristaId, SolicitacaoCarga::STATUS_PENDENTE, SolicitacaoCarga::STATUS_EM_ANALISE],
            $cargaIds
        );

        $stmt = $connection->prepare("
            SELECT carga_disponivel_id
            FROM solicitacao_carga
            WHERE motorista_id = ?
              AND status IN (?, ?)
              AND carga_disponivel_id IN ({$placeholders})
        ");
        $stmt->execute($bindings);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $cargaId) {
            $map[(int) $cargaId] = true;
        }

        return $map;
    }

    private static function mapOptions(array $options): array
    {
        $mapped = [];

        foreach ($options as $value => $label) {
            $mapped[] = [
                'value' => (string) $value,
                'label' => (string) $label,
            ];
        }

        return $mapped;
    }
}