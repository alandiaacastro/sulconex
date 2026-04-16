<?php

class ConhecimentoList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;

    public function __construct()
    {
        parent::__construct();
        Conhecimento::ensureSchema();
        FaturaTonelada::ensureSchema();

        // FORMULARIO DE FILTRO
        $this->form = new BootstrapFormBuilder('form_search_conhecimento');
        $this->form->setFormTitle('CRTs - Conhecimentos de Transporte');
        $id             = new TEntry('id');
        $numero         = new TEntry('numero');
        $status_crt_id  = new TDBCombo('status_crt_id', 'sample', 'StatusCrt', 'id', 'nome');
        $nome_remetente = new TEntry('nome_remetente');

        foreach ([$id, $numero, $status_crt_id, $nome_remetente] as $field) {
            $field->setSize('100%');
        }

        $this->form->addFields([new TLabel('ID')], [$id],
                               [new TLabel('Numero CRT')], [$numero]);
        $this->form->addFields([new TLabel('Status')], [$status_crt_id],
                               [new TLabel('Remetente')], [$nome_remetente]);

        $this->form->addAction('Filtrar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addAction('Novo CRT', new TAction([$this, 'onNumerarCrt']), 'fa:plus green');

        $this->form->addAction('Recarregar', new TAction([$this, 'onReload']), 'fa:refresh');

        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        // DATAGRID
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';

        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center', '5%'));

        $this->datagrid->addColumn(new TDataGridColumn('numero', 'CRT', 'left', '10%'));

        $colData = new TDataGridColumn('data_transportador_assinatura', 'Data Transportador', 'center', '15%');
        $colData->setTransformer(function ($value) {
            return $value ? TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '';
        });
        $this->datagrid->addColumn($colData);

        $colStatus = new TDataGridColumn('status_crt_id', 'Status', 'left', '10%');
        $colStatus->setTransformer(function ($value) {
            try {
                $status = new StatusCrt($value);
                return $this->buildStatusBadge($status);
            } catch (Exception $e) {
                return '-';
            }
        });
        $this->datagrid->addColumn($colStatus);

        $colRemetente = new TDataGridColumn('remetente->nome', 'Remetente', 'left', '25%');
        $colRemetente->setTransformer(function ($val, $obj) {
            return $obj->remetente->nome ?? '-';
        });
        $this->datagrid->addColumn($colRemetente);

        $colDestinatario = new TDataGridColumn('destinatario->nome', 'Destinatario', 'left', '25%');
        $colDestinatario->setTransformer(function ($val, $obj) {
            return $obj->destinatario->nome ?? '-';
        });
        $this->datagrid->addColumn($colDestinatario);

        $colTipoCobranca = new TDataGridColumn('tipo_cobranca', 'Cobranca', 'center', '12%');
        $colTipoCobranca->setTransformer(function ($value) {
            $tipo = strtoupper(trim((string) $value));
            if ($tipo === 'POR_TONELADA') {
                return "<span class='badge' style='background:#0d6efd;color:#fff'>Por tonelada</span>";
            }
            return "<span class='badge' style='background:#6c757d;color:#fff'>Frete fixo</span>";
        });
        $this->datagrid->addColumn($colTipoCobranca);

        $colToneladas = new TDataGridColumn('toneladas_carga', 'Ton. CRT', 'right', '8%');
        $colToneladas->setTransformer(function ($value, $obj) {
            if (strtoupper(trim((string) ($obj->tipo_cobranca ?? ''))) !== 'POR_TONELADA') {
                return '-';
            }

            return number_format((float) $obj->getToneladasCalculadas(), 3, ',', '.');
        });
        $this->datagrid->addColumn($colToneladas);

        $colSaldoTon = new TDataGridColumn('id', 'Saldo Ton.', 'right', '8%');
        $colSaldoTon->setTransformer(function ($value, $obj) {
            if (strtoupper(trim((string) ($obj->tipo_cobranca ?? ''))) !== 'POR_TONELADA') {
                return '-';
            }

            try {
                $resumo = FaturaTonelada::getResumoToneladas((int) $obj->id);
                return number_format((float) $resumo['saldo'], 3, ',', '.');
            } catch (Exception $e) {
                return '0,000';
            }
        });
        $this->datagrid->addColumn($colSaldoTon);

        // ACOES
        $actionEdit = new TDataGridAction(['ConhecimentoForm', 'onEdit'], ['key' => '{id}']);
        $this->datagrid->addAction($actionEdit, 'Editar', 'fa:edit blue');

        $actionHistory = new TDataGridAction([$this, 'onShowHistory'], ['key' => '{id}']);
        $this->datagrid->addAction($actionHistory, 'Historico', 'fa:history blue');

        $actionPrint = new TDataGridAction([$this, 'onPrint'], ['key' => '{id}']);
        $this->datagrid->addAction($actionPrint, 'Imprimir', 'fa:print green');

        $colCopy = new TDataGridColumn('id', '', 'center', '35px');
        $colCopy->setTransformer(function ($id, $obj) {
            $statusNome = strtoupper(trim((string) ($obj->status_crt->nome ?? '')));
            $isEntregue = strpos($statusNome, 'ENTREG') !== false;
            $canCopy    = ($obj->copiacrt === '1') && !$isEntregue;

            if ($canCopy) {
                return '<a onclick="__adianti_post_data(\'main-window\','
                     . ' \'class=ConhecimentoList&method=onCopy&key=' . $id . '\')"'
                     . ' style="cursor:pointer" title="Copiar">'
                     . '<i class="fa fa-copy" style="color:#fd7e14"></i></a>';
            }
            return '<span title="Copiar (indispon&iacute;vel)" style="cursor:not-allowed;opacity:0.35">'
                 . '<i class="fa fa-copy" style="color:#fd7e14"></i></span>';
        });
        $this->datagrid->addColumn($colCopy);

        $colFaturar = new TDataGridColumn('id', '', 'center', '35px');
        $colFaturar->setTransformer(function ($id) {
            return '<a onclick="__adianti_post_data(\'main-window\','
                 . ' \'class=ConhecimentoList&method=onFaturar&key=' . $id . '\')"'
                 . ' style="cursor:pointer" title="Faturar">'
                 . '<i class="fa fa-file-text-o" style="color:#007bff"></i></a>';
        });
        $this->datagrid->addColumn($colFaturar);

        $actionDelete = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $actionDelete->setDisplayCondition(function ($obj) {
            return !self::isConhecimentoEntregue($obj);
        });
        $this->datagrid->addAction($actionDelete, 'Excluir', 'fa:trash red');

        $this->datagrid->createModel();

        // PAGINACAO
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth('100%');

        // CONTAINER FINAL
        $panel = new TPanelGroup('Listagem de CRTs');
        $panel->add($this->form);
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        // CARDS DE SITUAÇÃO (placeholder; preenchido em onReload)
        $cardsDiv = new TElement('div');
        $cardsDiv->id = 'crt-status-cards';
        $cardsDiv->style = 'width:100%;margin-bottom:12px';

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($cardsDiv);
        $container->add($panel);

        parent::add($container);

        TScript::create("
            (function() {
                var \$card   = \$('#form_search_conhecimento').closest('.card');
                var \$header = \$card.find('.card-header').first();
                var \$body   = \$card.find('.card-body').first();
                if (!\$header.length || !\$body.length) return;

                \$header.css('cursor','pointer');
                \$header.append('<span style=\"float:right;margin-left:8px\"><i class=\"fa fa-chevron-up\" id=\"crt-filter-icon\"></i></span>');

                \$header.on('click', function() {
                    \$body.slideToggle(180);
                    \$('#crt-filter-icon').toggleClass('fa-chevron-up fa-chevron-down');
                });
            })();
        ");

        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();

        TSession::setValue(__CLASS__.'_filter_id', $data->id ? new TFilter('id', '=', $data->id) : null);
        TSession::setValue(__CLASS__.'_filter_numero', $data->numero ? new TFilter('numero', 'like', "%{$data->numero}%") : null);
        TSession::setValue(__CLASS__.'_filter_status_crt_id', $data->status_crt_id ? new TFilter('status_crt_id', '=', $data->status_crt_id) : null);
        TSession::setValue(__CLASS__.'_filter_nome_remetente', $data->nome_remetente ? new TFilter('nome_remetente', 'like', "%{$data->nome_remetente}%") : null);

        TSession::setValue(__CLASS__.'_filter_data', $data);
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            $repo = new TRepository('Conhecimento');
            $limit = 10;
            $criteria = new TCriteria;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);
            if (!$criteria->getProperty('order')) {
                $criteria->setProperty('order', 'id');
                $criteria->setProperty('direction', 'desc');
            }

            foreach (['_filter_id','_filter_numero','_filter_status_crt_id','_filter_nome_remetente'] as $session_filter) {
                $filter = TSession::getValue(__CLASS__.$session_filter);
                if ($filter) {
                    $criteria->add($filter);
                }
            }

            $this->datagrid->clear();
            $objects = $repo->load($criteria, FALSE);
            if ($objects) {
                foreach ($objects as $object) {
                    $this->datagrid->addItem($object);
                }
            }

            $criteria->resetProperties();
            $count = $repo->count($criteria);
            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setLimit($limit);

            TTransaction::close();
            $this->loaded = true;

            // Renderizar cards de situação
            $cardsHtml = $this->buildStatusCards();
            $baseUrl   = "engine.php?class=ConhecimentoList&method=onFilterByStatus";
            TScript::create("
                \$('#crt-status-cards').html(" . json_encode($cardsHtml) . ");
                \$('#crt-status-cards').off('click.statuscard').on('click.statuscard', '[data-status-id]', function() {
                    var sid = \$(this).data('status-id');
                    __adianti_load_page__('{$baseUrl}&status_id=' + sid);
                });
            ");
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    private function buildStatusCards(): string
    {
        $icons = [
            'DRAFT'    => ['icon' => 'fa-pencil',         'bg' => '#6c757d'],
            'AGUARDA'  => ['icon' => 'fa-clock-o',        'bg' => '#fd7e14'],
            'APROVADO' => ['icon' => 'fa-check-circle',   'bg' => '#28a745'],
            'AVERBADO' => ['icon' => 'fa-stamp',          'bg' => '#009688'],
            'TRANSITO' => ['icon' => 'fa-truck',          'bg' => '#e6a817'],
            'ENTREGUE' => ['icon' => 'fa-flag-checkered', 'bg' => '#3F51B5'],
        ];

        try {
            TTransaction::open('sample');
            $result = TTransaction::get()->query(
                "SELECT s.id, s.nome, s.cor, COUNT(c.id) as total
                 FROM status_crt s
                 LEFT JOIN conhecimento c ON c.status_crt_id = s.id
                 WHERE s.deleted_at IS NULL
                 GROUP BY s.id
                 ORDER BY s.ordem, s.id"
            );

            $statuses = [];
            $grandTotal = 0;
            foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $statuses[] = $row;
                $grandTotal += (int) $row['total'];
            }
            TTransaction::close();
        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::close();
            return '';
        }

        // Detecta filtro de status ativo
        $filterData     = TSession::getValue(__CLASS__ . '_filter_data');
        $activeStatusId = $filterData->status_crt_id ?? null;

        // Monta HTML com data-status-id (click handler via jQuery no onReload)
        $parts = [];

        $isActive  = empty($activeStatusId);
        $ring      = $isActive ? 'box-shadow:0 0 0 3px #0d6efd;' : '';
        $parts[] = '<div class="col" style="flex:1;min-width:100px">'
            . '<div class="card border-0 shadow-sm" data-status-id="0"'
            . ' style="cursor:pointer;border-radius:12px;' . $ring . 'transition:box-shadow .15s">'
            . '<div class="card-body p-3 d-flex align-items-center gap-2">'
            . '<div style="background:#495057;border-radius:10px;width:40px;height:40px;min-width:40px;display:flex;align-items:center;justify-content:center">'
            . '<i class="fa fa-list text-white"></i></div>'
            . '<div>'
            . '<div style="font-size:1.5rem;font-weight:700;line-height:1;color:#212529">' . $grandTotal . '</div>'
            . '<div style="font-size:0.65rem;color:#6c757d;text-transform:uppercase;letter-spacing:.5px">TODOS</div>'
            . '</div></div></div></div>';

        foreach ($statuses as $status) {
            $nome      = $status['nome'];
            $total     = (int) $status['total'];
            $statusId  = $status['id'];
            $def       = $icons[$nome] ?? ['icon' => 'fa-circle', 'bg' => '#6c757d'];
            $bg        = !empty($status['cor']) ? $status['cor'] : $def['bg'];
            $iconClass = $def['icon'];
            $textColor = in_array($bg, ['#FFE821', '#FFEB3B', '#FFC107', '#e6a817']) ? '#333333' : '#ffffff';
            $isActive  = ((string) $activeStatusId === (string) $statusId);
            $ring      = $isActive ? 'box-shadow:0 0 0 3px #0d6efd;' : '';

            $parts[] = '<div class="col" style="flex:1;min-width:100px">'
                . '<div class="card border-0 shadow-sm" data-status-id="' . $statusId . '"'
                . ' style="cursor:pointer;border-radius:12px;' . $ring . 'transition:box-shadow .15s">'
                . '<div class="card-body p-3 d-flex align-items-center gap-2">'
                . '<div style="background:' . $bg . ';border-radius:10px;width:40px;height:40px;min-width:40px;display:flex;align-items:center;justify-content:center">'
                . '<i class="fa ' . $iconClass . '" style="color:' . $textColor . '"></i></div>'
                . '<div>'
                . '<div style="font-size:1.5rem;font-weight:700;line-height:1;color:#212529">' . $total . '</div>'
                . '<div style="font-size:0.65rem;color:#6c757d;text-transform:uppercase;letter-spacing:.5px">' . $nome . '</div>'
                . '</div></div></div></div>';
        }

        return '<div class="row g-2" style="margin:0">' . implode('', $parts) . '</div>';
    }

    private function buildStatusBadge($status): string
    {
        $label = htmlspecialchars((string) ($status->nome ?? '-'), ENT_QUOTES, 'UTF-8');
        $hexColor = $this->normalizeHexColor((string) ($status->cor ?? '#6c757d'));
        [$r, $g, $b] = $this->hexToRgb($hexColor);

        $icon = $this->resolveStatusIcon((string) ($status->nome ?? ''));
        $isLight = ((0.299 * $r) + (0.587 * $g) + (0.114 * $b)) > 180;
        $textColor = $isLight ? '#1f2937' : $hexColor;
        $borderColor = $isLight ? 'rgba(31,41,55,.22)' : "rgba({$r},{$g},{$b},.35)";
        $backgroundColor = "rgba({$r},{$g},{$b},.14)";

        return '<span style="display:inline-flex;align-items:center;gap:6px;'
            . 'padding:4px 10px;border-radius:999px;border:1px solid ' . $borderColor . ';'
            . 'background:' . $backgroundColor . ';color:' . $textColor . ';font-weight:700;'
            . 'font-size:11px;line-height:1;letter-spacing:.3px;text-transform:uppercase;white-space:nowrap">'
            . '<i class="fa ' . $icon . '" style="font-size:10px"></i>'
            . $label
            . '</span>';
    }

    private function resolveStatusIcon(string $statusName): string
    {
        $name = strtoupper(trim($statusName));
        if (strpos($name, 'ENTREG') !== false) {
            return 'fa-flag-checkered';
        }
        if (strpos($name, 'TRANSIT') !== false) {
            return 'fa-truck';
        }
        if (strpos($name, 'AVERB') !== false) {
            return 'fa-stamp';
        }
        if (strpos($name, 'APROV') !== false) {
            return 'fa-check-circle';
        }
        if (strpos($name, 'AGUARD') !== false || strpos($name, 'PEND') !== false) {
            return 'fa-clock-o';
        }
        if (strpos($name, 'RASCUNHO') !== false || strpos($name, 'DRAFT') !== false) {
            return 'fa-pencil';
        }
        return 'fa-circle';
    }

    private function normalizeHexColor(string $value): string
    {
        $hex = ltrim(trim($value), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return '#6c757d';
        }
        return '#' . strtoupper($hex);
    }

    private function hexToRgb(string $hexColor): array
    {
        $hex = ltrim($hexColor, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    public function onFilterByStatus($param)
    {
        $statusId = $param['status_id'] ?? 0;

        if ($statusId) {
            TSession::setValue(__CLASS__ . '_filter_status_crt_id', new TFilter('status_crt_id', '=', $statusId));
        } else {
            TSession::setValue(__CLASS__ . '_filter_status_crt_id', null);
        }

        $data = TSession::getValue(__CLASS__ . '_filter_data') ?? new stdClass;
        $data->status_crt_id = $statusId ?: null;
        TSession::setValue(__CLASS__ . '_filter_data', $data);

        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public static function onDelete($param)
    {
        try {
            TTransaction::open('sample');
            $object = new Conhecimento($param['key']);
            $isEntregue = self::isConhecimentoEntregue($object);
            TTransaction::close();

            if ($isEntregue) {
                new TMessage('warning', 'CRT com status ENTREGUE nao pode ser alterado ou excluido. Somente visualizacao.');
                return;
            }
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', 'Erro ao validar status do CRT: ' . $e->getMessage());
            return;
        }

        $action = new TAction([__CLASS__, 'delete'], $param);
        new TQuestion('Deseja realmente excluir este CRT?', $action);
    }

    public static function delete($param)
    {
        try {
            TTransaction::open('sample');
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            $object = new Conhecimento($param['key']);
            if (self::isConhecimentoEntregue($object)) {
                throw new Exception('CRT com status ENTREGUE nao pode ser alterado ou excluido.');
            }
            $object->delete();

            TTransaction::close();
            new TMessage('info', 'Registro excluido com sucesso!', new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao excluir: ' . $e->getMessage());
        }
    }

    public function onPrint($param)
    {
        try {
            $pdf = new ConhecimentoPDFGenerator($param['key']);
            $pdf->gerarPDFArquivo();
        } catch (Exception $e) {
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }

    public static function onFaturar($param)
    {
        try {
            TTransaction::open('sample');
            $conhecimento = new Conhecimento($param['key']);
            $tipo = strtoupper(trim((string) ($conhecimento->tipo_cobranca ?? 'FRETE_FIXO')));
            TTransaction::close();

            if ($tipo === 'POR_TONELADA') {
                TApplication::loadPage('FaturaToneladaForm', 'onEdit', ['conhecimento_id' => $conhecimento->id]);
                return;
            }

            TApplication::loadPage('FaturaForm', 'onEdit', ['conhecimento_id' => $conhecimento->id]);
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', 'Erro ao abrir faturamento: ' . $e->getMessage());
        }
    }

    public static function onCopy($param)
    {
        try {
            TTransaction::open('sample');
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            $original = new Conhecimento($param['key']);
            if (self::isConhecimentoEntregue($original)) {
                throw new Exception('CRT com status ENTREGUE nao pode ser copiado.');
            }
            $permissao = new Permisso($original->permisso_id);

            $novoNumero = (int)$permissao->numerocrt + 1;
            $permissao->numerocrt = $novoNumero;
            $permissao->store();

            $copy = new Conhecimento;
            foreach ($original->toArray() as $attr => $val) {
                if ($attr !== 'id') {
                    $copy->$attr = $val;
                }
            }
            $copy->numero = $permissao->permisso . str_pad($novoNumero, 5, '0', STR_PAD_LEFT);
            $copy->copiacrt = null;
            $copy->store();

            $original->copiacrt = null;
            $original->store();

            TTransaction::close();
            new TMessage('info', 'CRT copiado com sucesso!', new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao copiar: ' . $e->getMessage());
        }
    }

    private static function isConhecimentoEntregue($obj): bool
    {
        try {
            $statusNome = (string) ($obj->status_crt->nome ?? '');
        } catch (Exception $e) {
            $statusNome = '';
        }

        $statusNome = strtoupper(trim($statusNome));
        return strpos($statusNome, 'ENTREG') !== false;
    }

    public static function onNumerarCrt()
    {
        try {
            TTransaction::open('sample');

            $form = new BootstrapFormBuilder('form_novo_crt');
            $form->setFormTitle('');
            $form->setProperty('style', 'width: 100%');

            $permisso_id = new TDBCombo('permisso_id', 'sample', 'Permisso', 'id', 'permisso');
            $permisso_id->enableSearch();
            $permisso_id->setSize('100%');
            $permisso_id->setDefaultOption('Selecione uma permissão');

            $form->addFields([new TLabel('Permissão')], [$permisso_id]);

            $btnGenerate = $form->addAction('Gerar', new TAction([__CLASS__, 'gerarCrt']), 'fa:check');
            $btnGenerate->class = 'btn btn-success';
            $btnCancel = $form->addAction('Cancelar', new TAction([__CLASS__, 'closeWindow']), 'fa:times');
            $btnCancel->class = 'btn btn-outline-secondary';

            new TInputDialog('NOVO CRT', $form);
            TScript::create("
                (function() {
                    var styleId = 'crt-create-dialog-style';
                    if (!document.getElementById(styleId)) {
                        var css = ''
                            + '.modal.crt-create-dialog .modal-dialog{max-width:434px;}'
                            + '.modal.crt-create-dialog .modal-content{border:0;border-radius:16px;overflow:hidden;box-shadow:0 20px 48px rgba(15,23,42,.24);}'
                            + '.modal.crt-create-dialog .modal-header{padding:16px 22px;border-bottom:1px solid #e2e8f0;background:linear-gradient(135deg,#f8fbff,#eef4ff);}'
                            + '.modal.crt-create-dialog .modal-title{font-size:24px;font-weight:700;letter-spacing:.2px;color:#0f172a;}'
                            + '.modal.crt-create-dialog .modal-body{padding:20px 22px 18px;background:#ffffff;}'
                            + '.modal.crt-create-dialog .modal-footer{padding:14px 22px;background:#fbfdff;border-top:1px solid #e2e8f0;}'
                            + '.modal.crt-create-dialog .modal-footer .btn{border-radius:10px;padding:9px 16px;font-weight:600;}'
                            + '#form_novo_crt .control-label{font-size:14px;font-weight:600;color:#334155;margin-bottom:8px;}'
                            + '#form_novo_crt .form-control{height:44px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;color:#0f172a;}'
                            + '#form_novo_crt .form-control:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.16);background:#fff;}'
                            + '#form_novo_crt .select2-container .select2-selection--single{height:44px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;}'
                            + '#form_novo_crt .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:42px;padding-left:14px;color:#0f172a;}'
                            + '#form_novo_crt .select2-container--default .select2-selection--single .select2-selection__arrow{height:42px;right:8px;}'
                            + '#form_novo_crt .select2-container--default.select2-container--open .select2-selection--single{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.16);background:#fff;}'
                            + '@media (max-width: 767px){.modal.crt-create-dialog .modal-dialog{margin:12px;}.modal.crt-create-dialog .modal-title{font-size:20px;}}';

                        var style = document.createElement('style');
                        style.id = styleId;
                        style.type = 'text/css';
                        style.appendChild(document.createTextNode(css));
                        document.head.appendChild(style);
                    }

                    var modal = $('#form_novo_crt').closest('.modal');
                    if (!modal.length) return;

                    modal.addClass('crt-create-dialog');
                    modal.find('.modal-footer .btn').each(function() {
                        var text = ($(this).text() || '').trim().toLowerCase();
                        if (text === 'gerar') {
                            $(this).removeClass('btn-default btn-secondary btn-outline-secondary').addClass('btn-success');
                        }
                        if (text === 'cancelar') {
                            $(this).removeClass('btn-secondary btn-default').addClass('btn-outline-secondary');
                        }
                    });
                })();
            ");
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function gerarCrt($param)
    {
        try {
            TTransaction::open('sample');
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            $permissao = new Permisso($param['permisso_id']);
            $novoNumero = (int)$permissao->numerocrt + 1;
            $permissao->numerocrt = $novoNumero;
            $permissao->store();

            $crt = new Conhecimento;
            $crt->permisso_id = $permissao->id;
            $crt->numero = $permissao->permisso . str_pad($novoNumero, 5, '0', STR_PAD_LEFT);
            $crt->status_crt_id = 1;
            $crt->tipo_cobranca = 'FRETE_FIXO';
            $crt->store();

            TTransaction::close();
            TWindow::closeWindow('form_novo_crt');
            new TMessage('info', 'CRT criado com sucesso!', new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }

    public static function closeWindow()
    {
        TWindow::closeWindow('form_novo_crt');
    }

    public static function onShowHistory($param)
    {
        $id = $param['key'] ?? null;
        if ($id) {
            TScript::create("__adianti_load_page('index.php?class=ConhecimentoHistoricoView&pkvalue={$id}');");
        }
    }

    // ---------------------------------------------------------------
    // Importação de CRT via XML
    // ---------------------------------------------------------------

    public static function onImportXml($param = null)
    {
        $modelo = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<CartaPorteInternacional>
  <InformacoesGerais>
    <Numero></Numero>
    <DataEmissao></DataEmissao>
    <Permisso></Permisso>
    <FaturaCRT></FaturaCRT>
    <CopiarCRT>N</CopiarCRT>
    <Assinatura></Assinatura>
  </InformacoesGerais>
  <Remetente>
    <Nome></Nome>
    <Endereco></Endereco>
    <Cnpj></Cnpj>
    <Email></Email>
    <Telefone></Telefone>
    <Cidade></Cidade>
    <Estado></Estado>
    <Cep></Cep>
    <InscricaoEstadual></InscricaoEstadual>
    <Atividade></Atividade>
    <EmissaoCrt></EmissaoCrt>
    <Tipo>EXPORTADOR</Tipo>
  </Remetente>
  <Destinatario>
    <Nome></Nome>
    <Endereco></Endereco>
    <Cnpj></Cnpj>
    <Email></Email>
    <Telefone></Telefone>
    <Cidade></Cidade>
    <Estado></Estado>
    <Cep></Cep>
    <InscricaoEstadual></InscricaoEstadual>
    <Atividade></Atividade>
    <EmissaoCrt></EmissaoCrt>
    <Tipo>IMPORTADOR</Tipo>
  </Destinatario>
  <Consignatario>
    <Nome></Nome>
    <Endereco></Endereco>
    <Cnpj></Cnpj>
    <Email></Email>
    <Telefone></Telefone>
    <Cidade></Cidade>
    <Estado></Estado>
    <Cep></Cep>
    <InscricaoEstadual></InscricaoEstadual>
    <Atividade></Atividade>
    <EmissaoCrt></EmissaoCrt>
    <Tipo>CONSIGNATARIO</Tipo>
  </Consignatario>
  <Notificar>
    <Nome></Nome>
    <Endereco></Endereco>
    <Cnpj></Cnpj>
    <Email></Email>
    <Telefone></Telefone>
    <Cidade></Cidade>
    <Estado></Estado>
    <Cep></Cep>
    <InscricaoEstadual></InscricaoEstadual>
    <Atividade></Atividade>
    <EmissaoCrt></EmissaoCrt>
    <Tipo>NOTIFICAR</Tipo>
  </Notificar>
  <Locais>
    <Emissao></Emissao>
    <Responsabilidade></Responsabilidade>
    <Entrega></Entrega>
  </Locais>
  <Carga>
    <Descricao></Descricao>
    <PesoBrutoKg></PesoBrutoKg>
    <PesoLiquidoKg></PesoLiquidoKg>
    <VolumeM3></VolumeM3>
    <QuantidadeVolumes></QuantidadeVolumes>
    <EspecieVolume></EspecieVolume>
    <Incoterm></Incoterm>
    <Incoterm16></Incoterm16>
    <MoedaMercadoria></MoedaMercadoria>
    <ValorMercadoria></ValorMercadoria>
    <ValorDeclarado></ValorDeclarado>
    <ValorReembolso></ValorReembolso>
  </Carga>
  <Frete>
    <Moeda></Moeda>
    <ValorExterno></ValorExterno>
  </Frete>
  <Custos>
    <Moeda></Moeda>
    <Item1>
      <Descricao></Descricao>
      <CustoRemetente></CustoRemetente>
      <CustoDestinatario></CustoDestinatario>
    </Item1>
    <Item2>
      <Descricao></Descricao>
      <CustoRemetente></CustoRemetente>
      <CustoDestinatario></CustoDestinatario>
    </Item2>
    <Item3>
      <Descricao></Descricao>
      <CustoRemetente></CustoRemetente>
      <CustoDestinatario></CustoDestinatario>
    </Item3>
    <TotalRemetente></TotalRemetente>
    <TotalDestinatario></TotalDestinatario>
  </Custos>
  <Observacoes>
    <Observacoes></Observacoes>
    <InstrucoesAlfandega></InstrucoesAlfandega>
    <DocumentosAnexos></DocumentosAnexos>
  </Observacoes>
</CartaPorteInternacional>
XML;

        $win = TWindow::create('form_import_xml', 1, 1, 700, 560);
        $win->setTitle('Importar CRT via XML');

        $form = new BootstrapFormBuilder('form_import_xml');
        $form->setFormTitle('Cole o XML abaixo e clique em Importar');
        $form->style = 'padding:10px';

        $xml_content = new TText('xml_content');
        $xml_content->setSize('100%', 380);
        $xml_content->setValue($modelo);
        $xml_content->setProperty('style', 'font-family:monospace;font-size:12px;resize:vertical;');

        $form->addFields([$xml_content]);
        $form->addAction('Importar', new TAction([__CLASS__, 'processImportXml']), 'fa:upload green');
        $form->addAction('Cancelar', new TAction([__CLASS__, 'closeImportWindow']), 'fa:times red');

        $win->add($form);
        $win->show();
    }

    public static function closeImportWindow()
    {
        TWindow::closeWindow('form_import_xml');
    }

    public static function processImportXml($param)
    {
        try {
            $xmlContent = trim($param['xml_content'] ?? '');
            if (empty($xmlContent)) {
                throw new Exception('Nenhum conteúdo XML informado.');
            }

            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            if (!$dom->loadXML($xmlContent)) {
                $erros = libxml_get_errors();
                libxml_clear_errors();
                $msg = 'XML inválido';
                if (!empty($erros)) {
                    $msg .= ': ' . trim($erros[0]->message);
                }
                throw new Exception($msg);
            }
            libxml_clear_errors();

            $root = $dom->documentElement;
            if ($root->tagName !== 'CartaPorteInternacional') {
                throw new Exception('Formato inválido: elemento raiz esperado é <CartaPorteInternacional>.');
            }

            $xp = new DOMXPath($dom);

            $g = function (string $query) use ($xp): string {
                $nodes = $xp->query($query);
                return ($nodes && $nodes->length > 0) ? trim($nodes->item(0)->nodeValue) : '';
            };

            TTransaction::open('sample');
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            Clientes::ensureSchema();

            // ---------- Helper: encontra cliente por CNPJ ou cria novo ----------
            $findOrCreateClient = function (string $section, string $tipoDefault) use ($g): ?int {
                $nome = $g("//{$section}/Nome");
                if (empty($nome)) return null;

                $cnpj = $g("//{$section}/Cnpj");

                // Tentar encontrar por CNPJ se informado
                if (!empty($cnpj)) {
                    $repo = new TRepository('Clientes');
                    $crit = new TCriteria;
                    $crit->add(new TFilter('cnpj', '=', $cnpj));
                    $found = $repo->load($crit);
                    if (!empty($found)) {
                        return (int) $found[0]->id;
                    }
                }

                // Não encontrou: criar novo cliente
                $cli = new Clientes;
                $cli->nome               = strtoupper($nome);
                $cli->endereco           = strtoupper($g("//{$section}/Endereco"));
                $cli->cnpj               = $cnpj;
                $cli->email              = $g("//{$section}/Email");
                $cli->telefone           = $g("//{$section}/Telefone");
                $cli->cidade             = strtoupper($g("//{$section}/Cidade"));
                $cli->estado             = strtoupper($g("//{$section}/Estado"));
                $cli->cep                = $g("//{$section}/Cep");
                $cli->inscricao_estadual = $g("//{$section}/InscricaoEstadual");
                $cli->atividade          = strtoupper($g("//{$section}/Atividade"));
                $cli->emissao_crt        = $g("//{$section}/EmissaoCrt");
                $cli->tipo               = strtoupper($g("//{$section}/Tipo")) ?: $tipoDefault;
                $cli->store();
                return (int) $cli->id;
            };

            $remetente_id    = $findOrCreateClient('Remetente',    'EXPORTADOR');
            $destinatario_id = $findOrCreateClient('Destinatario', 'IMPORTADOR');
            $consig_id       = $findOrCreateClient('Consignatario','CONSIGNATARIO');
            $notif_id        = $findOrCreateClient('Notificar',    'NOTIFICAR');

            $crt = new Conhecimento;
            $crt->numero               = $g('//InformacoesGerais/Numero');
            $crt->fatura_crt           = $g('//InformacoesGerais/FaturaCRT');
            $crt->permisso             = $g('//InformacoesGerais/Permisso');
            $crt->assinatura_nome      = $g('//InformacoesGerais/Assinatura');
            $crt->copiacrt             = $g('//InformacoesGerais/CopiarCRT') === 'S' ? '1' : null;

            $dataEmissao = $g('//InformacoesGerais/DataEmissao');
            if ($dataEmissao) {
                $ts = strtotime($dataEmissao);
                $crt->data_transportador_assinatura = $ts ? date('Y-m-d', $ts) : null;
            }

            // Vincular clientes
            $crt->remetente_id          = $remetente_id;
            $crt->destinatario_id       = $destinatario_id;
            $crt->consignatario_id      = $consig_id;
            $crt->notificar_id          = $notif_id;

            $crt->nome_remetente          = $g('//Remetente/Nome');
            $crt->endereco_remetente      = $g('//Remetente/Endereco');
            $crt->nome_destinatario       = $g('//Destinatario/Nome');
            $crt->endereco_destinatario   = $g('//Destinatario/Endereco');
            $crt->nome_consignatario      = $g('//Consignatario/Nome');
            $crt->endereco_consignatario  = $g('//Consignatario/Endereco');
            $crt->notificar_nome          = $g('//Notificar/Nome');
            $crt->notificar_endereco      = $g('//Notificar/Endereco');

            $crt->local_emissao           = $g('//Locais/Emissao');
            $crt->local_responsabilidade  = $g('//Locais/Responsabilidade');
            $crt->local_entrega           = $g('//Locais/Entrega');

            $crt->descricao_mercadoria    = $g('//Carga/Descricao');
            $crt->peso_bruto_kg           = $g('//Carga/PesoBrutoKg');
            $crt->peso_liq_kg             = $g('//Carga/PesoLiquidoKg');
            $crt->volume_m3               = $g('//Carga/VolumeM3');
            $crt->quantidade_volumes      = $g('//Carga/QuantidadeVolumes');
            $crt->especie_vol             = $g('//Carga/EspecieVolume');
            $crt->incoterm                = $g('//Carga/Incoterm');
            $crt->incoterm16              = $g('//Carga/Incoterm16');
            $crt->moeda_valor_mercadorias = $g('//Carga/MoedaMercadoria');
            $crt->valor_mercadorias       = $g('//Carga/ValorMercadoria');
            $crt->valor_declarado         = $g('//Carga/ValorDeclarado');
            $crt->valor_reembolso         = $g('//Carga/ValorReembolso');

            $crt->moeda_frete_externo     = $g('//Frete/Moeda');
            $crt->valor_frete_externo     = $g('//Frete/ValorExterno');

            $crt->gastosmoeda             = $g('//Custos/Moeda');
            for ($i = 1; $i <= 3; $i++) {
                $crt->{"textogasto{$i}"}    = $g("//Custos/Item{$i}/Descricao");
                $crt->{"custoremetente{$i}"} = $g("//Custos/Item{$i}/CustoRemetente");
                $crt->{"custodestino{$i}"}   = $g("//Custos/Item{$i}/CustoDestinatario");
            }
            $crt->total_custo_remetente    = $g('//Custos/TotalRemetente');
            $crt->total_custo_destinatario = $g('//Custos/TotalDestinatario');

            $crt->observacoes             = $g('//Observacoes/Observacoes');
            $crt->instrucoes_alfandega    = $g('//Observacoes/InstrucoesAlfandega');
            $crt->documentos_anexos       = $g('//Observacoes/DocumentosAnexos');
            $crt->tipo_cobranca           = 'FRETE_FIXO';

            $crt->status_crt_id = 1; // status padrão: primeiro status

            // Tentar vincular permisso_id pelo campo permisso
            if (!empty($crt->permisso)) {
                $repo = new TRepository('Permisso');
                $criteria = new TCriteria;
                $criteria->add(new TFilter('permisso', '=', $crt->permisso));
                $results = $repo->load($criteria);
                if (!empty($results)) {
                    $crt->permisso_id = $results[0]->id;
                }
            }

            $crt->store();
            $newId = $crt->id;

            TTransaction::close();
            TWindow::closeWindow('form_import_xml');

            $clientMsg = array_filter([$remetente_id ? "Remetente ID:{$remetente_id}" : null,
                                       $destinatario_id ? "Destinatário ID:{$destinatario_id}" : null,
                                       $consig_id ? "Consignatário ID:{$consig_id}" : null]);
            $extra = $clientMsg ? ' | Clientes: ' . implode(', ', $clientMsg) : '';

            new TMessage('info', "CRT importado com sucesso! ID: {$newId}{$extra}",
                new TAction(['ConhecimentoForm', 'onEdit'], ['key' => $newId]));

        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', 'Erro ao importar XML: ' . $e->getMessage());
        }
    }
}


