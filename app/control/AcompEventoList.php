<?php

class AcompEventoList extends TPage
{
    private $datagrid;
    private $pageNavigation;
    private $processo_id;
    private $timelineContainer;
    private $infoCards;
    private $googleMapsUrl;

    public function __construct($param = null)
    {
        parent::__construct();

        TPage::include_css('app/resources/css/acomp_evento_list.css');

        $this->processo_id = $param['processo_id'] ?? null;
        $this->googleMapsUrl = '';
        $this->timelineContainer = new TElement('div');
        $this->timelineContainer->id = 'timeline-container';
        $this->infoCards = new TElement('div');
        $this->infoCards->class = 'row mb-4';

        // Datagrid setup
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width:100%';

        $col_data = new TDataGridColumn('data_evento', 'Data', 'left', '14%');
        $col_demora = new TDataGridColumn('demora', 'Evento', 'left', '11%');
        $col_status = new TDataGridColumn('status_texto', 'Status', 'left', '20%');
        $col_localizacao = new TDataGridColumn('localizacao', 'Localizacao', 'left', '15%');
        $col_franquia = new TDataGridColumn('franquia', 'Franquia', 'left', '12%');
        $col_imagem = new TDataGridColumn('imagem', 'Evidencia', 'center', '13%');

        $col_data->setTransformer(function ($value) {
            $ts = strtotime((string) $value);
            return $ts ? date('d/m/Y H:i', $ts) : $value;
        });

        $col_status->setTransformer(function ($value) {
            $value = (string) $value;
            $style = $this->getStatusBadgeStyle($value);
            return '<span class="badge rounded-pill status-badge" style="' . $style . '">' . htmlspecialchars($value) . '</span>';
        });

        $col_imagem->setTransformer(function ($value) {
            if (empty($value)) {
                return '<span class="text-muted small">—</span>';
            }
            $path = 'app/images/acomp_evento/' . htmlspecialchars($value);
            $url  = htmlspecialchars($value);
            return '<a href="' . $path . '" target="_blank" title="Ver imagem">'
                 . '<img src="' . $path . '" style="max-width:60px;max-height:45px;border-radius:4px;border:1px solid #ccc;cursor:pointer;" onerror="this.parentElement.innerHTML=\'<span class=\\\'text-danger small\\\'>N/D</span>\'">'
                 . '</a>';
        });

        $this->datagrid->addColumn($col_data);
        $this->datagrid->addColumn($col_demora);
        $this->datagrid->addColumn($col_status);
        $this->datagrid->addColumn($col_localizacao);
        $this->datagrid->addColumn($col_franquia);
        $this->datagrid->addColumn($col_imagem);

        $act_edit = new TDataGridAction(['AcompEventoForm', 'onEdit']);
        $act_edit->setField('id');
        $act_edit->setParameter('processo_id', '{processo_id}');
        $this->datagrid->addAction($act_edit, 'Editar', 'fa:edit blue');

        $act_del = new TDataGridAction([$this, 'onDelete']);
        $act_del->setField('id');
        $act_del->setParameter('processo_id', '{processo_id}');
        $this->datagrid->addAction($act_del, 'Excluir', 'fa:trash red');

        $this->datagrid->createModel();
        $this->datagrid->disableDefaultClick();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        // Build layout
        $box = new TVBox;
        $box->style = 'width:100%; padding: 15px;';
        $box->add(new TXMLBreadCrumb('menu.xml', 'AcompProcessoKanban'));

        // Info cards
        $box->add($this->infoCards);

        // Timeline Panel
        $timelinePanel = new TPanelGroup('Historico de Eventos');
        $timelinePanel->addHeaderActionLink('Abrir no Google Maps', new TAction([$this, 'onOpenGoogleMaps'], ['processo_id' => $this->processo_id]), 'fa:map-marker-alt blue');
        $timelinePanel->add($this->timelineContainer);
        $box->add($timelinePanel);

        // Datagrid panel
        $panel = new TPanelGroup('Detalhes de Eventos');
        $panel->addHeaderActionLink('+ Novo evento', new TAction(['AcompEventoForm', 'onEdit'], ['processo_id' => $this->processo_id]), 'fa:plus green');
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);
        $box->add($panel);

        parent::add($box);
    }

    public function onReload($param = null)
    {
        try {
            if (!empty($param['processo_id'])) {
                $this->processo_id = $param['processo_id'];
            }

            if (empty($this->processo_id)) {
                throw new Exception('Processo nao informado para listar status.');
            }

            TTransaction::open('sample');
            AcompProcesso::ensureTables();

            // Load process data
            $processo = new AcompProcesso($this->processo_id);
            if (empty($processo->id)) {
                throw new Exception('Processo nao encontrado.');
            }

            // Load events
            $repo = new TRepository('AcompEvento');
            $criteria = new TCriteria;
            $criteria->add(new TFilter('processo_id', '=', $this->processo_id));
            $criteria->setProperty('order', 'data_evento');
            $criteria->setProperty('direction', 'desc');

            $limit = 30;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);

            $this->datagrid->clear();
            $objs = $repo->load($criteria, false) ?: [];

            // Build datagrid
            foreach ($objs as $obj) {
                $this->datagrid->addItem($obj);
            }

            // Build info cards
            $this->buildInfoCards($processo);

            // Build timeline
            $this->buildTimeline($objs, $processo);

            // Build Google Maps URL
            $this->googleMapsUrl = GoogleMapsUrlBuilder::getRouteFromEvents($objs);

            // Pagination
            $criteria->resetProperties();
            $total = $repo->count($criteria);
            $this->pageNavigation->setCount($total);
            $this->pageNavigation->setProperties(array_merge((array) $param, ['processo_id' => $this->processo_id]));
            $this->pageNavigation->setLimit($limit);

            TTransaction::close();
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $ee) {
            }
            new TMessage('error', $e->getMessage());
        }
    }

    private function buildInfoCards($processo): void
    {
        $this->infoCards->clearChildren();

        $exportador = (string) ($processo->exportador ?? '-');
        $importador = (string) ($processo->importador ?? '-');
        $crt = (string) ($processo->crt ?? '-');
        $previsao = !empty($processo->previsao_entrega) ?
            date('d/m/Y', strtotime((string) $processo->previsao_entrega)) : '-';

        $html = '
        <div class="col-md-3 mb-3">
            <div class="card shadow h-100 py-2 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-uppercase text-primary small fw-bold"><i class="fa fa-cube"></i> Exportador</div>
                    <div class="h6 mb-0 fw-bold">' . htmlspecialchars(substr($exportador, 0, 30)) . '</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow h-100 py-2 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-uppercase text-success small fw-bold"><i class="fa fa-flag"></i> Importador</div>
                    <div class="h6 mb-0 fw-bold">' . htmlspecialchars(substr($importador, 0, 30)) . '</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow h-100 py-2 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="text-uppercase text-warning small fw-bold"><i class="fa fa-calendar"></i> ETA</div>
                    <div class="h6 mb-0 fw-bold">' . htmlspecialchars($previsao) . '</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow h-100 py-2 border-start border-4 border-info">
                <div class="card-body">
                    <div class="text-uppercase text-info small fw-bold"><i class="fa fa-barcode"></i> CRT</div>
                    <div class="h6 mb-0 fw-bold">' . htmlspecialchars($crt) . '</div>
                </div>
            </div>
        </div>';

        $this->infoCards->add($html);
    }

    private function buildTimeline(array $eventos, $processo): void
    {
        $this->timelineContainer->clearChildren();

        if (empty($eventos)) {
            $this->timelineContainer->add('<div class="alert alert-info m-0">Nenhum evento cadastrado.</div>');
            return;
        }

        $timeline = new TTimeline;
        foreach ($eventos as $idx => $evento) {
            $data = !empty($evento->data_evento) ?
                date('d/m/Y H:i', strtotime((string) $evento->data_evento)) : '-';
            $title = (string) ($evento->status_texto ?? 'Evento');
            $sub = self::formatEventSub($evento);
            $icon = $this->getEventIcon($title);
            $side = ($idx % 2 === 0) ? 'left' : 'right';

            $timeline->addItem($idx + 1, $title, $sub, $data, $icon, $side);
        }

        $this->timelineContainer->add($timeline);
    }

    private function getStatusBadgeStyle(string $status): string
    {
        $s = strtolower($status);

        if (strpos($s, 'entreg') !== false) {
            return 'background:#1f8b4c;color:#fff;font-weight:700;';
        }
        if (strpos($s, 'aduana') !== false || strpos($s, 'aguard') !== false) {
            return 'background:#b45309;color:#fff;font-weight:700;';
        }
        if (strpos($s, 'erro') !== false || strpos($s, 'atras') !== false) {
            return 'background:#dc2626;color:#fff;font-weight:700;';
        }

        return 'background:#0369a1;color:#fff;font-weight:700;';
    }
    private function getEventIcon(string $status): string
    {
        $s = strtolower($status);
        if (strpos($s, 'coleta') !== false) {
            return 'fa:cube bg-indigo';
        }
        if (strpos($s, 'transito') !== false) {
            return 'fa:truck bg-blue';
        }
        if (strpos($s, 'aduana') !== false) {
            return 'fa:flag bg-orange';
        }
        if (strpos($s, 'armazen') !== false) {
            return 'fa:warehouse bg-yellow';
        }
        if (strpos($s, 'entrega') !== false || strpos($s, 'entreg') !== false) {
            return 'fa:check-circle bg-green';
        }
        return 'fa:circle bg-gray';
    }

    private static function formatEventSub($evento): string
    {
        $localizacao = '';
        $demora = '';

        if (!empty($evento->localizacao)) {
            $localizacao = trim((string) $evento->localizacao);
        }

        if (!empty($evento->demora)) {
            $demora = trim((string) $evento->demora);
        }

        // Fallback legado: quando nao havia localizacao, tentava extrair do campo demora.
        if ($localizacao === '' && $demora !== '') {
            $parts = explode('|', $demora);
            if (!empty($parts[0])) {
                $localizacao = trim($parts[0]);
            }
        }

        $chunks = [];
        if ($localizacao !== '') {
            $chunks[] = '<strong>Localizacao:</strong> ' . htmlspecialchars($localizacao);
        }
        if ($demora !== '') {
            $chunks[] = '<strong>Evento:</strong> ' . htmlspecialchars($demora);
        }

        if (!empty($evento->imagem)) {
            $imgPath = 'app/images/acomp_evento/' . htmlspecialchars((string) $evento->imagem);
            $chunks[] = '<a href="' . $imgPath . '" target="_blank">'
                      . '<img src="' . $imgPath . '" style="max-width:120px;max-height:90px;margin-top:6px;border-radius:4px;border:1px solid #ccc;" onerror="this.style.display=\'none\'">'
                      . '</a>';
        }

        return $chunks ? implode('<br>', $chunks) : '-';
    }

    public static function onDelete($param)
    {
        $action = new TAction([__CLASS__, 'delete']);
        $action->setParameters($param);
        new TQuestion('Deseja excluir este evento?', $action);
    }

    public static function delete($param)
    {
        try {
            TTransaction::open('sample');
            AcompProcesso::ensureTables();

            $obj = new AcompEvento($param['key']);
            $processo_id = $obj->processo_id;
            $obj->delete();

            TTransaction::close();
            new TMessage('info', 'Evento excluido com sucesso.', new TAction([__CLASS__, 'onReload'], ['processo_id' => $processo_id]));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onShow($param = null)
    {
        $this->onReload($param);
    }

    public static function onOpenGoogleMaps($param)
    {
        try {
            TTransaction::open('sample');
            AcompProcesso::ensureTables();

            $processo_id = $param['processo_id'] ?? null;
            if (empty($processo_id)) {
                throw new Exception('Processo nao informado.');
            }

            // Load eventos
            $repo = new TRepository('AcompEvento');
            $criteria = new TCriteria;
            $criteria->add(new TFilter('processo_id', '=', $processo_id));
            $criteria->setProperty('order', 'data_evento');
            $criteria->setProperty('direction', 'asc');
            $eventos = $repo->load($criteria, false) ?: [];

            TTransaction::close();

            // Generate Google Maps URL
            $googleMapsUrl = GoogleMapsUrlBuilder::getRouteFromEvents($eventos);

            // Open in new tab
            TScript::create("window.open('$googleMapsUrl', '_blank');");
            new TMessage('info', 'Abrindo Google Maps...', new TAction([__CLASS__, 'onReload'], ['processo_id' => $processo_id]));
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', $e->getMessage());
        }
    }
}

