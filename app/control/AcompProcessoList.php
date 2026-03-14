<?php

class AcompProcessoList extends TPage
{
    private $form;
    private $kanbanContainer;
    private static $estoqueSnapshotByCrt = [];
    private static $lastEventByProcessId = [];

    use Adianti\Base\AdiantiStandardListTrait {
        onReload as traitOnReload;
    }

    public function __construct()
    {
        parent::__construct();
        TPage::include_css('app/resources/css/acomp_processo_cards.css');

        $this->setDatabase('sample');
        $this->setActiveRecord('AcompProcesso');
        $this->setDefaultOrder('id', 'desc');
        $criteria = new TCriteria;
        $criteria->add(new TFilter('crt', 'is not', null));
        $criteria->add(new TFilter('crt', '<>', ''));
        $this->setCriteria($criteria);

        $this->addFilterField('numero_processo', 'like', 'numero_processo');
        $this->addFilterField('exportador', 'like', 'exportador');
        $this->addFilterField('importador', 'like', 'importador');
        $this->addFilterField('crt', 'like', 'crt');

        $this->kanbanContainer = new TElement('div');
        $this->kanbanContainer->class = 'acomp-kanban';

        $panel = new TPanelGroup;
        $panel->class = 'acomp-process-card-panel';
        $panel->add($this->kanbanContainer);

        $box = new TVBox;
        $box->style = 'width:100%';
        $box->add(new TXMLBreadCrumb('menu.xml', get_class($this)));
        $box->add($panel);

        parent::add($box);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            AcompProcesso::ensureTables();
            EstoqueManifesto::ensureTables();
            self::syncProcessosFromConhecimento();
            self::loadEstoqueSnapshot();
            self::loadLastUpdateSnapshot();

            $repo = new TRepository('AcompProcesso');
            $criteria = new TCriteria;
            $criteria->add(new TFilter('crt', 'is not', null));
            $criteria->add(new TFilter('crt', '<>', ''));
            $criteria->setProperty('order', 'id');
            $criteria->setProperty('direction', 'desc');
            $processos = $repo->load($criteria, false) ?: [];

            $this->kanbanContainer->clearChildren();
            $this->kanbanContainer->add(self::getKanbanCss());
            $this->kanbanContainer->add(self::buildKanbanWidget($processos));
            TTransaction::close();
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $ee) {
            }
            new TMessage('error', $e->getMessage());
        }
    }

    private static function loadEstoqueSnapshot(): void
    {
        self::$estoqueSnapshotByCrt = [];

        $sql = "
            SELECT
                man.crt_codigo,
                man.crt_normalizado,
                COALESCE(SUM(CASE WHEN mov.tipo = 'entrada' THEN mov.peso_kg ELSE -mov.peso_kg END), 0) AS saldo_peso,
                COALESCE(SUM(CASE WHEN mov.tipo = 'entrada' THEN mov.bobinas ELSE -mov.bobinas END), 0) AS saldo_bobinas,
                MAX(mov.data_movimento) AS ultima_mov
            FROM estoque_manifesto man
            LEFT JOIN estoque_movimento mov ON mov.manifesto_id = man.id
            GROUP BY man.id, man.crt_codigo, man.crt_normalizado
        ";

        $rows = TTransaction::get()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            self::$estoqueSnapshotByCrt[$row['crt_normalizado']] = [
                'crt' => (string) ($row['crt_codigo'] ?? ''),
                'saldo_peso' => (float) ($row['saldo_peso'] ?? 0),
                'saldo_bobinas' => (int) ($row['saldo_bobinas'] ?? 0),
                'ultima_mov' => (string) ($row['ultima_mov'] ?? ''),
            ];
        }
    }

    private static function syncProcessosFromConhecimento(): void
    {
        $conn = TTransaction::get();

        $known = [];
        $rows = $conn->query("
            SELECT
                id,
                numero,
                nome_remetente,
                remetente_id,
                nome_destinatario,
                destinatario_id,
                descricao_mercadoria,
                fatura_crt,
                peso_bruto_kg,
                local_emissao,
                local_entrega,
                local_responsabilidade,
                data_transportador_assinatura
            FROM conhecimento
            WHERE TRIM(COALESCE(numero, '')) <> ''
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $crt = (string) ($row['numero'] ?? '');
            $crtNorm = self::normalizeCrt($crt);
            if ($crtNorm === '') {
                continue;
            }
            $known[$crtNorm] = $row;
        }

        $procRows = $conn->query("SELECT id, crt FROM acomp_processo")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $procByNorm = [];

        foreach ($procRows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $crtNorm = self::normalizeCrt((string) ($row['crt'] ?? ''));
            if ($crtNorm === '' || !isset($known[$crtNorm])) {
                // Registro legado sem CRT valido: oculta da torre sem perder historico.
                $up = $conn->prepare("UPDATE acomp_processo SET crt = '' WHERE id = :id");
                $up->execute([':id' => $id]);
                continue;
            }

            if (!isset($procByNorm[$crtNorm])) {
                $procByNorm[$crtNorm] = $id;
            }
        }

        foreach ($known as $crtNorm => $krow) {
            $pid = $procByNorm[$crtNorm] ?? null;
            $payload = self::buildProcessoPayloadFromConhecimento($krow);

            if ($pid) {
                $sql = "
                    UPDATE acomp_processo SET
                        numero_processo = :numero_processo,
                        local_coleta = :local_coleta,
                        local_entrega = :local_entrega,
                        data_coleta = :data_coleta,
                        aduana_origem = :aduana_origem,
                        aduana_destino = :aduana_destino,
                        exportador = :exportador,
                        importador = :importador,
                        produto = :produto,
                        crt = :crt,
                        fatura = :fatura,
                        peso_bruto = :peso_bruto,
                        etapa = COALESCE(NULLIF(etapa, ''), :etapa),
                        updated_at = :updated_at
                    WHERE id = :id
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute($payload + [':id' => $pid]);
            } else {
                $insertPayload = $payload;
                $insertPayload[':created_at'] = date('Y-m-d H:i:s');

                $sql = "
                    INSERT INTO acomp_processo (
                        numero_processo, local_coleta, local_entrega, data_coleta, previsao_entrega,
                        transit_time_dias, aduana_origem, aduana_destino, exportador, importador,
                        produto, crt, fatura, etapa, peso_bruto, cubagem, mapa_url, created_at, updated_at
                    ) VALUES (
                        :numero_processo, :local_coleta, :local_entrega, :data_coleta, '',
                        '', :aduana_origem, :aduana_destino, :exportador, :importador,
                        :produto, :crt, :fatura, :etapa, :peso_bruto, '', '', :created_at, :updated_at
                    )
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute($insertPayload);
            }
        }
    }

    private static function buildProcessoPayloadFromConhecimento(array $krow): array
    {
        $crt = (string) ($krow['numero'] ?? '');
        $exportador = self::pickClienteNome((string) ($krow['nome_remetente'] ?? ''), (int) ($krow['remetente_id'] ?? 0));
        $importador = self::pickClienteNome((string) ($krow['nome_destinatario'] ?? ''), (int) ($krow['destinatario_id'] ?? 0));
        $dataColeta = trim((string) ($krow['data_transportador_assinatura'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataColeta)) {
            $dataColeta = '';
        }

        $peso = self::toFloat($krow['peso_bruto_kg'] ?? 0);
        $now = date('Y-m-d H:i:s');

        return [
            ':numero_processo' => $crt,
            ':local_coleta' => (string) ($krow['local_emissao'] ?? ''),
            ':local_entrega' => (string) ($krow['local_entrega'] ?? ''),
            ':data_coleta' => $dataColeta,
            ':aduana_origem' => (string) ($krow['local_responsabilidade'] ?? ''),
            ':aduana_destino' => (string) ($krow['local_entrega'] ?? ''),
            ':exportador' => $exportador,
            ':importador' => $importador,
            ':produto' => (string) ($krow['descricao_mercadoria'] ?? ''),
            ':crt' => $crt,
            ':fatura' => (string) ($krow['fatura_crt'] ?? ''),
            ':peso_bruto' => $peso,
            ':etapa' => AcompProcesso::STAGE_COLETA,
            ':updated_at' => $now,
        ];
    }

    private static function pickClienteNome(string $nomeDireto, int $id): string
    {
        $nome = trim($nomeDireto);
        if ($nome !== '') {
            return $nome;
        }
        if ($id <= 0) {
            return '';
        }
        try {
            $cli = new Clientes($id);
            return (string) ($cli->nome ?? '');
        } catch (Exception $e) {
            return '';
        }
    }

    private static function toFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $clean = str_replace('.', '', (string) $value);
        $clean = str_replace(',', '.', $clean);
        return (float) $clean;
    }

    private static function loadLastUpdateSnapshot(): void
    {
        self::$lastEventByProcessId = [];

        $sql = "
            SELECT processo_id, data_evento, status_texto
            FROM acomp_evento
            ORDER BY processo_id, datetime(data_evento) DESC, id DESC
        ";

        $rows = TTransaction::get()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $processId = (int) ($row['processo_id'] ?? 0);
            if ($processId <= 0 || isset(self::$lastEventByProcessId[$processId])) {
                continue;
            }

            self::$lastEventByProcessId[$processId] = [
                'data_evento' => (string) ($row['data_evento'] ?? ''),
                'status_texto' => (string) ($row['status_texto'] ?? ''),
            ];
        }
    }

    private static function build24hAlertInfo(int $processId, string $stage): string
    {
        if ($processId <= 0 || !AcompProcesso::isTransitStage($stage)) {
            return '';
        }

        $lastEvent = self::$lastEventByProcessId[$processId]['data_evento'] ?? '';
        if ($lastEvent === '') {
            return '<div style="margin-top:4px;"><span class="label label-danger">ALERTA 24H: sem atualizacao</span></div>';
        }

        $ts = strtotime($lastEvent);
        if ($ts === false) {
            return '';
        }

        $ageHours = floor((time() - $ts) / 3600);
        if ($ageHours < 24) {
            return '';
        }

        return '<div style="margin-top:4px;">' .
            '<span class="label label-danger">ALERTA 24H (' . (int) $ageHours . 'h)</span>' .
            '</div>';
    }

    private static function resolveCurrentStage($object): string
    {
        $stageRaw = (string) ($object->etapa ?? '');
        if (trim($stageRaw) === '') {
            $id = (int) ($object->id ?? 0);
            $stageRaw = self::$lastEventByProcessId[$id]['status_texto'] ?? '';
        }
        $stage = AcompProcesso::normalizeStageCode((string) $stageRaw);
        if ($stage === '') {
            $stage = AcompProcesso::STAGE_COLETA;
        }

        return $stage;
    }

    private static function buildKanbanWidget(array $processos)
    {
        $stageOrder = [
            AcompProcesso::STAGE_COLETA,
            AcompProcesso::STAGE_TRANSITO_BRASIL,
            AcompProcesso::STAGE_ARMAZENAGEM,
            AcompProcesso::STAGE_ADUANA_BRASIL,
            AcompProcesso::STAGE_TRANSITO_EXT,
            AcompProcesso::STAGE_ADUANA_DESTINO,
            AcompProcesso::STAGE_ENTREGA,
        ];

        $kanban = new TKanban;
        $kanban->setStageHeight('74vh');
        $kanban->setItemDropAction(new TAction([__CLASS__, 'onUpdateItemDrop']));

        // Conta processos por estágio para exibir no cabeçalho da coluna.
        $stageCounts = array_fill_keys($stageOrder, 0);
        foreach ($processos as $obj) {
            $stage = self::resolveCurrentStage($obj);
            if (!isset($stageCounts[$stage])) {
                $stageCounts[$stage] = 0;
            }
            $stageCounts[$stage]++;
        }

        foreach ($stageOrder as $stage) {
            $label = strtoupper(AcompProcesso::stageLabel($stage));
            $count = (int) ($stageCounts[$stage] ?? 0);
            $kanban->addStage($stage, "{$label} ({$count})");
        }

        foreach ($processos as $obj) {
            $stage = self::resolveCurrentStage($obj);
            $card = self::renderProcessCard($obj);
            $kanban->addItem((int) $obj->id, $stage, '', $card, '#f59e0b');
        }

        return $kanban;
    }

    public static function renderProcessCard($object): string
    {
        $id = (int) ($object->id ?? 0);
        $numero = (string) ($object->numero_processo ?? '-');
        $exportador = (string) ($object->exportador ?? '-');
        $importador = (string) ($object->importador ?? '-');
        $crt = (string) ($object->crt ?? '-');

        $stage = self::resolveCurrentStage($object);
        $stageClass = 'stage-' . preg_replace('/[^a-z0-9_]/', '', (string) $stage);

        $data = self::renderDataColeta($object);
        $estoque = self::renderEstoquePositionByCrt($crt);
        $alert24 = self::build24hAlertInfo($id, $stage);

        $viewUrl = 'index.php?class=AcompProcessoView&method=onShow&key=' . $id . '&id=' . $id;
        $trackUrl = 'index.php?class=AcompEventoList&method=onReload&processo_id=' . $id . '&key=' . $id . '&id=' . $id;
        $stockUrl = 'index.php?class=EstoqueView&method=onReload&crt=' . rawurlencode($crt) . '&busca=' . rawurlencode($crt) . '&sentido=todos';
        $pickupUrl = 'index.php?class=AcompOrdemColetaReport&method=onGenerate&processo_id=' . $id;

        return '
            <div class="acomp-card ' . htmlspecialchars($stageClass) . '">
                <div class="acomp-card-head">
                    <div class="acomp-card-title">Processo #' . $id . ' - ' . htmlspecialchars($numero) . '</div>
                </div>
                <div class="acomp-card-grid">
                    <div class="full"><span class="lbl">Exportador</span><span class="val">' . htmlspecialchars($exportador) . '</span></div>
                    <div class="full"><span class="lbl">Importador</span><span class="val">' . htmlspecialchars($importador) . '</span></div>
                    <div><span class="lbl">CRT</span><span class="val">' . htmlspecialchars($crt) . '</span></div>
                    <div><span class="lbl">Data coleta</span><span class="val">' . htmlspecialchars($data) . '</span></div>
                </div>
                <div class="acomp-card-stock">' . $estoque . '</div>
                ' . $alert24 . '
                <div class="acomp-card-actions">
                    <a href="' . htmlspecialchars($viewUrl) . '" class="btn-act btn-view" generator="adianti"><i class="fa fa-eye"></i> Visualizar</a>
                    <a href="' . htmlspecialchars($trackUrl) . '" class="btn-act btn-track" generator="adianti"><i class="fa fa-crosshairs"></i> Rastreio</a>
                    <a href="' . htmlspecialchars($stockUrl) . '" class="btn-act btn-stock" generator="adianti"><i class="fa fa-building"></i> Estoque</a>
                    <a href="' . htmlspecialchars($pickupUrl) . '" class="btn-act btn-doc" generator="adianti"><i class="fa fa-file-alt"></i> Ordem coleta</a>
                </div>
            </div>';
    }

    private static function getKanbanCss(): string
    {
        return <<<HTML
<style>
  .kanban-stage { background:#eef2f7 !important; border:1px solid #dbe1ea; border-radius:10px; padding:8px !important; }
  .kanban-stage-header { border-radius:8px; text-align:center; text-transform:uppercase; font-size:12px !important; font-weight:800 !important; padding:8px 12px !important; border:1px solid transparent; color:#fff !important; }
  .kanban-stage:nth-child(1) .kanban-stage-header { background:#f2c318 !important; border-color:#e0b100 !important; color:#4a3a00 !important; }
  .kanban-stage:nth-child(2) .kanban-stage-header { background:#1d9bf0 !important; border-color:#1889d4 !important; }
  .kanban-stage:nth-child(3) .kanban-stage-header { background:#10b981 !important; border-color:#0ea371 !important; }
  .kanban-stage:nth-child(4) .kanban-stage-header { background:#8b5cf6 !important; border-color:#7c4ee4 !important; }
  .kanban-stage:nth-child(5) .kanban-stage-header { background:#14b8a6 !important; border-color:#0ea294 !important; }
  .kanban-stage:nth-child(6) .kanban-stage-header { background:#e11d48 !important; border-color:#c8143d !important; }
  .kanban-stage:nth-child(7) .kanban-stage-header { background:#22c55e !important; border-color:#1cab50 !important; }
  .kanban-item { background:transparent !important; border:0 !important; box-shadow:none !important; padding:0 !important; margin-bottom:9px !important; }
  .kanban-item > .kanban-item-content { padding:0 !important; background:transparent !important; }
</style>
HTML;
    }

    public static function onUpdateItemDrop($param)
    {
        try {
            TTransaction::open('sample');
            AcompProcesso::ensureTables();

            $id = (int) ($param['id'] ?? 0);
            $stage = AcompProcesso::normalizeStageCode((string) ($param['stage_id'] ?? ''));
            if ($id <= 0 || $stage === '') {
                throw new Exception('Movimentacao invalida.');
            }

            $proc = new AcompProcesso($id);
            $proc->etapa = $stage;
            $proc->updated_at = date('Y-m-d H:i:s');
            $proc->store();

            TTransaction::close();
            TToast::show('success', 'Etapa atualizada!', 'bottom right');
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $ee) {
            }
            TToast::show('error', $e->getMessage(), 'bottom right');
        }
    }

    private static function renderDataColeta($object): string
    {
        $processId = isset($object->id) ? (int) $object->id : 0;
        $last = self::$lastEventByProcessId[$processId]['data_evento'] ?? '';
        $raw = $last !== '' ? $last : (string) ($object->data_coleta ?? '');
        if ($raw === '') {
            return '-';
        }

        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('d/m/Y', $ts);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return TDate::convertToMask($raw, 'yyyy-mm-dd', 'dd/mm/yyyy');
        }

        return $raw;
    }

    private static function renderEstoquePositionByCrt(string $crt): string
    {
        $key = self::normalizeCrt($crt);
        if ($key === '' || !isset(self::$estoqueSnapshotByCrt[$key])) {
            return '<span class="label label-default">SEM RASTREIO</span>';
        }

        $row = self::$estoqueSnapshotByCrt[$key];
        $peso = (float) ($row['saldo_peso'] ?? 0);
        $bobinas = (int) ($row['saldo_bobinas'] ?? 0);
        $ultima = self::formatDate((string) ($row['ultima_mov'] ?? ''));

        if ($peso < 0 || $bobinas < 0) {
            $label = '<span class="label label-danger">DIVERGENCIA</span>';
        } elseif ($peso > 0 || $bobinas > 0) {
            $label = '<span class="label label-success">EM PATIO</span>';
        } else {
            $label = '<span class="label label-primary">BAIXADO</span>';
        }

        $pesoFmt = number_format(max($peso, 0), 0, ',', '.');
        $bobFmt = number_format(max($bobinas, 0), 0, ',', '.');

        return $label .
            '<div style="margin-top:4px;color:#475569;font-size:11px;font-weight:700;">' .
            $pesoFmt . ' kg / ' . $bobFmt . ' bobinas' .
            '</div>' .
            '<div style="margin-top:2px;color:#94a3b8;font-size:10px;">Ultima mov.: ' . htmlspecialchars($ultima) . '</div>';
    }

    private static function normalizeCrt(string $value): string
    {
        $value = strtoupper(trim($value));
        return preg_replace('/[^A-Z0-9]/', '', $value);
    }

    private static function formatDate(string $value): string
    {
        if ($value === '') {
            return '-';
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }

        return date('d/m/Y', $ts);
    }
}
