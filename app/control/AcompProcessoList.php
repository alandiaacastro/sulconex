<?php

class AcompProcessoList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private static $estoqueSnapshotByCrt = [];
    private static $lastEventByProcessId = [];

    use Adianti\Base\AdiantiStandardListTrait {
        onReload as traitOnReload;
    }

    public function __construct()
    {
        parent::__construct();

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

        $this->form = new BootstrapFormBuilder('form_search_acomp_processo');
        $this->form->setFormTitle('Acompanhamento Manual - Processos e Rastreio');

        $numero_processo = new TEntry('numero_processo');
        $exportador = new TEntry('exportador');
        $importador = new TEntry('importador');
        $crt = new TEntry('crt');

        $numero_processo->setSize('100%');
        $exportador->setSize('100%');
        $importador->setSize('100%');
        $crt->setSize('100%');

        $this->form->addFields([new TLabel('No processo')], [$numero_processo], [new TLabel('CRT')], [$crt]);
        $this->form->addFields([new TLabel('Exportador')], [$exportador], [new TLabel('Importador')], [$importador]);

        $this->form->setData(TSession::getValue($this->activeRecord . '_filter_data'));
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');

        $this->datagrid = new BootstrapDatagridWrapper(new TQuickGrid);
        $this->datagrid->style = 'width:100%';

        $this->datagrid->addQuickColumn('ID', 'id', 'center', '5%');
        $this->datagrid->addQuickColumn('No BR/AR', 'numero_processo', 'left', '14%');
        $this->datagrid->addQuickColumn('Exportador', 'exportador', 'left', '19%');
        $this->datagrid->addQuickColumn('Importador', 'importador', 'left', '19%');
        $this->datagrid->addQuickColumn('CRT', 'crt', 'left', '11%');
        $col_estoque = $this->datagrid->addQuickColumn('Posicao estoque', 'crt', 'left', '18%');
        $col_estoque->setTransformer(function ($value) {
            return self::renderEstoquePositionByCrt((string) $value);
        });

        $col_data = $this->datagrid->addQuickColumn('Data coleta', 'data_coleta', 'center', '10%');
        $col_data->setTransformer(function ($value, $object) {
            $processId = isset($object->id) ? (int) $object->id : 0;
            $last = self::$lastEventByProcessId[$processId]['data_evento'] ?? '';
            $raw = $last !== '' ? $last : (string) $value;

            $ts = strtotime($raw);
            if ($ts) {
                return date('d/m/Y', $ts);
            }

            if ($raw && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                return TDate::convertToMask($raw, 'yyyy-mm-dd', 'dd/mm/yyyy');
            }

            return $raw ?: '-';
        });

        $col_etapa = $this->datagrid->addQuickColumn('Etapa', 'etapa', 'center', '12%');
        $col_etapa->setTransformer(function ($value, $object) {
            $processId = isset($object->id) ? (int) $object->id : 0;
            $stageRaw = self::$lastEventByProcessId[$processId]['status_texto'] ?? (string) $value;
            $stage = AcompProcesso::normalizeStageCode((string) $stageRaw);
            if ($stage === '') {
                $stage = AcompProcesso::STAGE_COLETA;
            }
            $label = AcompProcesso::stageLabel($stage);

            $class = 'info';
            if ($stage === AcompProcesso::STAGE_ENTREGA) {
                $class = 'success';
            } elseif (AcompProcesso::isTransitStage($stage)) {
                $class = 'warning';
            }

            $badge = '<span class="label label-' . $class . '">' . strtoupper(htmlspecialchars($label)) . '</span>';

            $processId = isset($object->id) ? (int) $object->id : 0;
            $alertInfo = self::build24hAlertInfo($processId, $stage);
            if ($alertInfo !== '') {
                $badge .= $alertInfo;
            }

            return $badge;
        });

        $act_view = new TDataGridAction(['AcompProcessoView', 'onShow']);

        $act_track = new TDataGridAction(['AcompEventoList', 'onReload']);
        $act_track->setParameter('processo_id', '{id}');
        $act_stock = new TDataGridAction(['EstoqueView', 'onReload']);
        $act_stock->setParameter('busca', '{crt}');
        $act_stock->setParameter('sentido', 'todos');
        $act_pickup = new TDataGridAction(['AcompOrdemColetaReport', 'onGenerate']);
        $act_pickup->setParameter('processo_id', '{id}');

        $this->datagrid->addQuickAction('Visualizar', $act_view, 'id', 'fa:eye');
        $this->datagrid->addQuickAction('Rastreio', $act_track, 'id', 'fa:crosshairs green');
        $this->datagrid->addQuickAction('Estoque', $act_stock, 'id', 'fa:warehouse blue');
        $this->datagrid->addQuickAction('Ordem coleta', $act_pickup, 'id', 'fa:file-alt black');

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        $panel = new TPanelGroup;
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $box = new TVBox;
        $box->style = 'width:100%';
        $box->add(new TXMLBreadCrumb('menu.xml', get_class($this)));
        $box->add($this->form);
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
            TTransaction::close();
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $ee) {
            }
        }

        $this->traitOnReload($param);
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
