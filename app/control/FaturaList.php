<?php

class FaturaList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;

    private static $database = 'sample';
    private static $activeRecord = 'Fatura';
    private static $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();

        // Formulario de filtros
        $this->form = new BootstrapFormBuilder('form_search_fatura');
        $this->form->setFormTitle('Faturas');

        $id            = new TEntry('id');
        $numero_fatura = new TEntry('numero_fatura');
        $numero_crt    = new TEntry('numero_crt');
        $fatura_cliente = new TEntry('fatura_cliente');
        $pessoa_id     = new TDBUniqueSearch('pessoa_id', self::$database, 'Clientes', 'id', 'nome');

        $emissao_de    = new TDate('emissao_de');
        $emissao_ate   = new TDate('emissao_ate');

        foreach ([$id, $numero_fatura, $numero_crt, $fatura_cliente, $pessoa_id, $emissao_de, $emissao_ate] as $f) {
            $f->setSize('100%');
        }

        $pessoa_id->setMinLength(0);
        $pessoa_id->setMask('{nome}');

        $emissao_de->setMask('dd/mm/yyyy');
        $emissao_de->setDatabaseMask('yyyy-mm-dd');
        $emissao_ate->setMask('dd/mm/yyyy');
        $emissao_ate->setDatabaseMask('yyyy-mm-dd');

        $this->form->addFields([new TLabel('ID')], [$id], [new TLabel('Numero fatura')], [$numero_fatura]);
        $this->form->addFields([new TLabel('Numero CRT')], [$numero_crt], [new TLabel('Fatura cliente')], [$fatura_cliente]);
        $this->form->addFields([new TLabel('Cliente')], [$pessoa_id]);
        $this->form->addFields([new TLabel('Emissao (de)')], [$emissao_de], [new TLabel('Emissao (ate)')], [$emissao_ate]);

        $this->form->addAction('Filtrar',    new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addAction('Nova',       new TAction(['FaturaForm', 'onEdit']), 'fa:plus green');
        $this->form->addAction('Recarregar', new TAction([$this, 'onReload']), 'fa:refresh');
        $this->form->addAction('Limpar Filtros', new TAction([$this, 'onClearFilters']), 'fa:times gray');
        $this->form->addAction('Vencidas',       new TAction([$this, 'onFilterStatus'], ['status' => 'vencidas']), 'fa:exclamation-circle red');
        $this->form->addAction('A Vencer',       new TAction([$this, 'onFilterStatus'], ['status' => 'a_vencer']), 'fa:clock-o orange');
        $this->form->addAction('Pagas',          new TAction([$this, 'onFilterStatus'], ['status' => 'pagas']), 'fa:check-circle green');
        $this->form->addAction('Pendentes',      new TAction([$this, 'onFilterStatus'], ['status' => 'pendentes']), 'fa:hourglass-half gray');

        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        // Atualiza session se vier quick param na URL
        if (!empty($_REQUEST['quick'])) {
            TSession::setValue(__CLASS__ . '_quick_filter', $_REQUEST['quick']);
        }

        // Datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';

        $colId = new TDataGridColumn('id', 'ID', 'center', '5%');
        $colId->setAction(new TAction([$this, 'onReload']), ['order' => 'id']);
        $this->datagrid->addColumn($colId);

        $colNumFatura = new TDataGridColumn('numero_fatura', 'Fatura', 'left', '10%');
        $colNumFatura->setAction(new TAction([$this, 'onReload']), ['order' => 'numero_fatura']);
        $this->datagrid->addColumn($colNumFatura);

        $colNumCrt = new TDataGridColumn('numero_crt', 'CRT', 'left', '8%');
        $colNumCrt->setAction(new TAction([$this, 'onReload']), ['order' => 'numero_crt']);
        $this->datagrid->addColumn($colNumCrt);

        $colFatCliente = new TDataGridColumn('fatura_cliente', 'Fat. Cliente', 'left', '12%');
        $colFatCliente->setAction(new TAction([$this, 'onReload']), ['order' => 'fatura_cliente']);
        $this->datagrid->addColumn($colFatCliente);

        $colCliente = new TDataGridColumn('clientekey->nome', 'Cliente', 'left', '20%');
        $colCliente->setTransformer(function ($val, $obj) {
            try {
                return $obj->clientekey->nome ?? '-';
            } catch (Exception $e) {
                return '-';
            }
        });
        $this->datagrid->addColumn($colCliente);

        $colEmissao = new TDataGridColumn('emissao', 'Emissão', 'center', '8%');
        $colEmissao->setAction(new TAction([$this, 'onReload']), ['order' => 'emissao']);
        $colEmissao->setTransformer(function ($value) {
            return $value ? TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '';
        });
        $this->datagrid->addColumn($colEmissao);

        $colVenc = new TDataGridColumn('vencimento', 'Vencimento', 'center', '8%');
        $colVenc->setAction(new TAction([$this, 'onReload']), ['order' => 'vencimento']);
        $colVenc->setTransformer(function ($value) {
            return $value ? TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '';
        });
        $this->datagrid->addColumn($colVenc);

        $colValor = new TDataGridColumn('valor_fatura', 'Valor', 'right', '9%');
        $colValor->setAction(new TAction([$this, 'onReload']), ['order' => 'valor_fatura']);
        $colValor->setTransformer(function ($value) {
            if ($value === null || $value === '') return '';
            return 'R$ ' . number_format((float) $value, 2, ',', '.');
        });
        $this->datagrid->addColumn($colValor);

        $colStatus = new TDataGridColumn('pagamento', 'Status', 'center', '12%');
        $colStatus->setTransformer(function ($pagamento, $obj) {
            $hoje = date('Y-m-d');
            $html = '';

            // Badge de status de pagamento
            if (!empty($pagamento) && $pagamento !== '0000-00-00') {
                $dt = TDate::convertToMask($pagamento, 'yyyy-mm-dd', 'dd/mm/yyyy');
                if (!empty($obj->tipo_baixa) && $obj->tipo_baixa === 'BAIXA ANTECIPADO BANCO') {
                    $desc = $obj->desconto_banco > 0
                        ? ' (-R$ ' . number_format((float)$obj->desconto_banco, 2, ',', '.') . ')'
                        : '';
                    $html .= "<span class='badge' style='background:#6f42c1;color:#fff;font-size:.75rem;padding:3px 7px'>"
                           . "<i class='fa fa-university'></i> Antecipado {$dt}{$desc}</span>";
                } else {
                    $html .= "<span class='badge' style='background:#198754;color:#fff;font-size:.75rem;padding:3px 7px'><i class='fa fa-check'></i> Recebida {$dt}</span>";
                }
            } else {
                $venc = $obj->vencimento ?? '';
                if (!empty($venc) && $venc < $hoje) {
                    $dias = (int) ceil((strtotime($hoje) - strtotime($venc)) / 86400);
                    $html .= "<span class='badge' style='background:#dc3545;color:#fff;font-size:.75rem;padding:3px 7px'><i class='fa fa-exclamation-circle'></i> Atrasada {$dias}d</span>";
                } elseif (!empty($venc) && $venc >= $hoje) {
                    $dias = (int) ceil((strtotime($venc) - strtotime($hoje)) / 86400);
                    $cor  = $dias <= 7 ? '#fd7e14' : '#0d6efd';
                    $html .= "<span class='badge' style='background:{$cor};color:#fff;font-size:.75rem;padding:3px 7px'><i class='fa fa-clock-o'></i> Vence em {$dias}d</span>";
                } else {
                    $html .= "<span class='badge' style='background:#6c757d;color:#fff;font-size:.75rem;padding:3px 7px'>Pendente</span>";
                }
            }

            // Indicador de baixa no Caixa
            try {
                $conn = TTransaction::get();
                $fid = (int) $obj->id;
                $no_caixa = $conn->query(
                    "SELECT id FROM caixa WHERE referencia_tipo='fatura' AND referencia_id={$fid} LIMIT 1"
                )->fetchColumn();
                if ($no_caixa) {
                    $html .= "<br><span class='badge' style='background:#0dcaf0;color:#000;font-size:.65rem;padding:2px 5px;margin-top:2px'>"
                           . "<i class='fa fa-university'></i> No Caixa</span>";
                }
            } catch (Exception $e) {}

            return $html;
        });
        $this->datagrid->addColumn($colStatus);

        // Acoes
        $actionEdit = new TDataGridAction(['FaturaForm', 'onEdit'], ['key' => '{id}']);
        $this->datagrid->addAction($actionEdit, 'Editar', 'fa:edit blue');

        $actionDelete = new TDataGridAction([__CLASS__, 'onDelete'], ['key' => '{id}']);
        $this->datagrid->addAction($actionDelete, 'Excluir', 'fa:trash red');

        $actionReceber = new TDataGridAction([__CLASS__, 'onConfirmReceber'], ['key' => '{id}']);
        $this->datagrid->addAction($actionReceber, 'Marcar Recebida', 'fa:check-circle green');

        $actionEstornar = new TDataGridAction([__CLASS__, 'onConfirmEstornar'], ['key' => '{id}']);
        $this->datagrid->addAction($actionEstornar, 'Estornar Recebimento', 'fa:undo orange');

        $actionBaixaCaixa = new TDataGridAction([__CLASS__, 'onConfirmBaixaCaixa'], ['key' => '{id}']);
        $this->datagrid->addAction($actionBaixaCaixa, 'Baixar no Caixa', 'fa:university purple');

        $actionEstornarCaixa = new TDataGridAction([__CLASS__, 'onConfirmEstornarCaixa'], ['key' => '{id}']);
        $this->datagrid->addAction($actionEstornarCaixa, 'Estornar Caixa', 'fa:times-circle red');

        $actionReais = new TDataGridAction(['FaturaReport', 'onGenerateReais'], ['key' => '{id}']);
        $this->datagrid->addAction($actionReais, 'Relatório (R$)', 'fa:file-pdf green');

        $actionDolar = new TDataGridAction(['FaturaReport', 'onGenerateDolar'], ['key' => '{id}']);
        $this->datagrid->addAction($actionDolar, 'Relatório (US$)', 'fa:file-pdf orange');

        $this->datagrid->createModel();

        // Paginacao
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->enableCounters();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth('100%');

        $panel = new TPanelGroup('Listagem de Faturas');
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        $container->add($this->buildKpiPanel());
        $container->add($panel);

        parent::add($container);

        // Colapso do filtro
        TScript::create("
            (function() {
                var \$card   = \$('#form_search_fatura').closest('.card');
                var \$header = \$card.find('.card-header').first();
                var \$body   = \$card.find('.card-body').first();
                if (!\$header.length || !\$body.length) return;

                \$header.css('cursor','pointer');
                \$header.append('<span style=\"float:right;margin-left:8px\"><i class=\"fa fa-chevron-up\" id=\"fat-filter-icon\"></i></span>');

                \$header.on('click', function() {
                    \$body.slideToggle(180);
                    \$('#fat-filter-icon').toggleClass('fa-chevron-up fa-chevron-down');
                });
            })();
        ");
    }

    // ── KPI PANEL ─────────────────────────────────────────────────────────

    private function buildKpiPanel()
    {
        try {
            TTransaction::open(self::$database);
            $conn = TTransaction::get();
            $hoje = date('Y-m-d');
            $prox7 = date('Y-m-d', strtotime('+7 days'));
            $prox30 = date('Y-m-d', strtotime('+30 days'));

            // A Receber (não pagas e não atrasadas)
            $r = $conn->query("
                SELECT COUNT(*) as qtd, COALESCE(SUM(CAST(valor_fatura AS REAL)),0) as total
                FROM fatura
                WHERE (pagamento IS NULL OR pagamento = '' OR pagamento = '0000-00-00')
                  AND (vencimento IS NULL OR vencimento = '' OR vencimento >= '{$hoje}')
            ")->fetch(\PDO::FETCH_ASSOC);
            $qtd_receber  = (int)   $r['qtd'];
            $val_receber  = (float) $r['total'];

            // Recebidas (pagas)
            $r = $conn->query("
                SELECT COUNT(*) as qtd, COALESCE(SUM(CAST(valor_fatura AS REAL)),0) as total
                FROM fatura
                WHERE pagamento IS NOT NULL AND pagamento != '' AND pagamento != '0000-00-00'
            ")->fetch(\PDO::FETCH_ASSOC);
            $qtd_recebidas = (int)   $r['qtd'];
            $val_recebidas = (float) $r['total'];

            // Atrasadas (não pagas e vencimento < hoje)
            $r = $conn->query("
                SELECT COUNT(*) as qtd, COALESCE(SUM(CAST(valor_fatura AS REAL)),0) as total
                FROM fatura
                WHERE (pagamento IS NULL OR pagamento = '' OR pagamento = '0000-00-00')
                  AND vencimento IS NOT NULL AND vencimento != '' AND vencimento < '{$hoje}'
            ")->fetch(\PDO::FETCH_ASSOC);
            $qtd_atrasadas = (int)   $r['qtd'];
            $val_atrasadas = (float) $r['total'];

            // Próximos 7 dias
            $r = $conn->query("
                SELECT COUNT(*) as qtd, COALESCE(SUM(CAST(valor_fatura AS REAL)),0) as total
                FROM fatura
                WHERE (pagamento IS NULL OR pagamento = '' OR pagamento = '0000-00-00')
                  AND vencimento BETWEEN '{$hoje}' AND '{$prox7}'
            ")->fetch(\PDO::FETCH_ASSOC);
            $qtd_7d = (int)   $r['qtd'];
            $val_7d = (float) $r['total'];

            // Próximos 30 dias
            $r = $conn->query("
                SELECT COUNT(*) as qtd, COALESCE(SUM(CAST(valor_fatura AS REAL)),0) as total
                FROM fatura
                WHERE (pagamento IS NULL OR pagamento = '' OR pagamento = '0000-00-00')
                  AND vencimento BETWEEN '{$hoje}' AND '{$prox30}'
            ")->fetch(\PDO::FETCH_ASSOC);
            $qtd_30d = (int)   $r['qtd'];
            $val_30d = (float) $r['total'];

            // Próximas 6 faturas a receber (para tabela)
            $proximas = $conn->query("
                SELECT f.id, f.numero_fatura, f.vencimento, f.valor_fatura,
                       c.nome AS cliente_nome
                FROM fatura f
                LEFT JOIN clientes c ON c.id = f.pessoa_id
                WHERE (f.pagamento IS NULL OR f.pagamento = '' OR f.pagamento = '0000-00-00')
                  AND f.vencimento IS NOT NULL AND f.vencimento != ''
                ORDER BY f.vencimento ASC
                LIMIT 6
            ")->fetchAll(\PDO::FETCH_ASSOC);

            TTransaction::close();

            $fmt = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

            // Rows da tabela de próximas faturas
            $rows_prox = '';
            foreach ($proximas as $p) {
                $venc_dt  = $p['vencimento'] ?? '';
                $dias     = $venc_dt ? (int)ceil((strtotime($venc_dt) - strtotime($hoje)) / 86400) : null;
                $venc_fmt = $venc_dt ? date('d/m/Y', strtotime($venc_dt)) : '-';

                if ($dias === null) {
                    $badge = "<span class='badge bg-secondary'>—</span>";
                } elseif ($dias < 0) {
                    $badge = "<span class='badge' style='background:#dc3545'>{$dias}d</span>";
                } elseif ($dias <= 7) {
                    $badge = "<span class='badge' style='background:#fd7e14'>{$dias}d</span>";
                } else {
                    $badge = "<span class='badge' style='background:#0d6efd'>{$dias}d</span>";
                }

                $rows_prox .= "<tr>
                    <td style='font-size:.8rem'>" . htmlspecialchars($p['numero_fatura'] ?? $p['id']) . "</td>
                    <td style='font-size:.8rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap'>" . htmlspecialchars($p['cliente_nome'] ?? '-') . "</td>
                    <td style='font-size:.8rem;text-align:center'>{$venc_fmt}</td>
                    <td style='font-size:.8rem;text-align:center'>{$badge}</td>
                    <td style='font-size:.8rem;text-align:right;font-weight:600'>" . $fmt($p['valor_fatura']) . "</td>
                </tr>";
            }
            if (!$rows_prox) {
                $rows_prox = '<tr><td colspan="5" class="text-center text-muted" style="font-size:.8rem">Nenhuma fatura pendente</td></tr>';
            }

            $html = <<<HTML
<style>
.fat-kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:18px;}
.fat-kpi{border-radius:10px;padding:16px 18px;color:#fff;box-shadow:0 3px 10px rgba(0,0,0,.13);}
.fat-kpi .fk-title{font-size:.72rem;opacity:.88;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;}
.fat-kpi .fk-val{font-size:1.3rem;font-weight:700;line-height:1.1;word-break:break-word;}
.fat-kpi .fk-sub{font-size:.7rem;opacity:.78;margin-top:4px;}
.fat-prox{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.09);padding:14px 16px;margin-bottom:18px;}
.fat-prox h6{font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;color:#555;margin-bottom:10px;}
</style>
<div class="fat-kpi-grid">
  <div class="fat-kpi" style="background:linear-gradient(135deg,#0d6efd,#084298)">
    <div class="fk-title"><i class="fa fa-file-invoice-dollar"></i> A Receber</div>
    <div class="fk-val">{$fmt($val_receber)}</div>
    <div class="fk-sub">{$qtd_receber} fatura(s) pendente(s)</div>
  </div>
  <div class="fat-kpi" style="background:linear-gradient(135deg,#198754,#0f5132)">
    <div class="fk-title"><i class="fa fa-check-circle"></i> Recebidas</div>
    <div class="fk-val">{$fmt($val_recebidas)}</div>
    <div class="fk-sub">{$qtd_recebidas} fatura(s) pagas</div>
  </div>
  <div class="fat-kpi" style="background:linear-gradient(135deg,#dc3545,#7b0012)">
    <div class="fk-title"><i class="fa fa-exclamation-circle"></i> Atrasadas</div>
    <div class="fk-val">{$fmt($val_atrasadas)}</div>
    <div class="fk-sub">{$qtd_atrasadas} fatura(s) vencida(s)</div>
  </div>
  <div class="fat-kpi" style="background:linear-gradient(135deg,#fd7e14,#7d3a00)">
    <div class="fk-title"><i class="fa fa-calendar-exclamation-o"></i> Próx. 7 dias</div>
    <div class="fk-val">{$fmt($val_7d)}</div>
    <div class="fk-sub">{$qtd_7d} fatura(s) a vencer</div>
  </div>
  <div class="fat-kpi" style="background:linear-gradient(135deg,#6f42c1,#3a1d6e)">
    <div class="fk-title"><i class="fa fa-calendar"></i> Próx. 30 dias</div>
    <div class="fk-val">{$fmt($val_30d)}</div>
    <div class="fk-sub">{$qtd_30d} fatura(s) a vencer</div>
  </div>
</div>
<div class="fat-prox">
  <h6><i class="fa fa-list-alt"></i> Próximas Faturas a Receber</h6>
  <table class="table table-sm table-hover mb-0" style="font-size:.82rem;">
    <thead class="table-light">
      <tr>
        <th>Fatura</th>
        <th>Cliente</th>
        <th class="text-center">Vencimento</th>
        <th class="text-center">Prazo</th>
        <th class="text-end">Valor</th>
      </tr>
    </thead>
    <tbody>{$rows_prox}</tbody>
  </table>
</div>
HTML;
            return TElement::tag('div', $html);

        } catch (Exception $e) {
            return TElement::tag('div', '');
        }
    }

    // ── ACOES ─────────────────────────────────────────────────────────────

    public function onClearFilters($param = null)
    {
        foreach (['_filter_id','_filter_numero_fatura','_filter_numero_crt','_filter_fatura_cliente','_filter_pessoa_id','_filter_emissao_de','_filter_emissao_ate','_filter_status'] as $sf) {
            TSession::setValue(__CLASS__ . $sf, null);
        }
        TSession::setValue(__CLASS__ . '_filter_data', null);
        TForm::sendData('form_search_fatura', (object)[]);
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public static function onFilterStatus($param = null)
    {
        $status = $param['status'] ?? '';
        foreach (['_filter_id','_filter_numero_fatura','_filter_numero_crt','_filter_fatura_cliente','_filter_pessoa_id','_filter_emissao_de','_filter_emissao_ate'] as $sf) {
            TSession::setValue(__CLASS__ . $sf, null);
        }
        TSession::setValue(__CLASS__ . '_filter_data', null);
        TSession::setValue(__CLASS__ . '_filter_status', $status);
        TForm::sendData('form_search_fatura', (object)[]);
        AdiantiCoreApplication::loadPage(__CLASS__, 'onReload', ['offset' => 0, 'first_page' => 1]);
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();

        TSession::setValue(__CLASS__ . '_filter_id', $data->id ? new TFilter('id', '=', $data->id) : null);
        TSession::setValue(__CLASS__ . '_filter_numero_fatura', $data->numero_fatura ? new TFilter('numero_fatura', 'like', "%{$data->numero_fatura}%") : null);
        TSession::setValue(__CLASS__ . '_filter_numero_crt', $data->numero_crt ? new TFilter('numero_crt', 'like', "%{$data->numero_crt}%") : null);
        TSession::setValue(__CLASS__ . '_filter_fatura_cliente', $data->fatura_cliente ? new TFilter('fatura_cliente', 'like', "%{$data->fatura_cliente}%") : null);
        TSession::setValue(__CLASS__ . '_filter_pessoa_id', $data->pessoa_id ? new TFilter('pessoa_id', '=', $data->pessoa_id) : null);

        TSession::setValue(__CLASS__ . '_filter_emissao_de', $data->emissao_de ? new TFilter('emissao', '>=', $data->emissao_de) : null);
        TSession::setValue(__CLASS__ . '_filter_emissao_ate', $data->emissao_ate ? new TFilter('emissao', '<=', $data->emissao_ate) : null);

        TSession::setValue(__CLASS__ . '_filter_data', $data);

        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open(self::$database);
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            $repo = new TRepository(self::$activeRecord);
            $limit = 10;
            $criteria = new TCriteria;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);
            if (empty($param['order'])) {
                $criteria->setProperty('order', self::$primaryKey);
                $criteria->setProperty('direction', 'desc');
            }

            foreach (['_filter_id', '_filter_numero_fatura', '_filter_numero_crt', '_filter_fatura_cliente', '_filter_pessoa_id', '_filter_emissao_de', '_filter_emissao_ate'] as $sf) {
                $filter = TSession::getValue(__CLASS__ . $sf);
                if ($filter) {
                    $criteria->add($filter);
                }
            }

            // Filtro rápido de status
            $status = TSession::getValue(__CLASS__ . '_filter_status');
            $hoje   = date('Y-m-d');
            if ($status === 'vencidas') {
                $criteria->add(new TFilter('pagamento', 'IS NULL', 'NOESC:'));
                $criteria->add(new TFilter('vencimento', '<', $hoje));
            } elseif ($status === 'a_vencer') {
                $criteria->add(new TFilter('pagamento', 'IS NULL', 'NOESC:'));
                $criteria->add(new TFilter('vencimento', '>=', $hoje));
            } elseif ($status === 'pagas') {
                $criteria->add(new TFilter('pagamento', 'IS NOT NULL', 'NOESC:'));
            } elseif ($status === 'pendentes') {
                $criteria->add(new TFilter('pagamento', 'IS NULL', 'NOESC:'));
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
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public static function onEnviarCaixa($param)
    {
        try {
            TTransaction::open(self::$database);
            Caixa::createTableIfNotExists();
            $conn = TTransaction::get();

            $fatura_id = (int) ($param['key'] ?? 0);
            $fatura    = new Fatura($fatura_id);

            $valor_original = (float)($fatura->valor_fatura ?? 0);
            if ($valor_original <= 0) {
                throw new Exception('Fatura sem valor definido.');
            }

            $is_antecipada = ($fatura->tipo_baixa === 'BAIXA ANTECIPADO BANCO');
            $desconto      = $is_antecipada ? (float)($fatura->desconto_banco ?? 0) : 0.0;
            $valor_liquido = max(0.0, $valor_original - $desconto);

            $data = !empty($fatura->pagamento)   ? $fatura->pagamento
                  : (!empty($fatura->vencimento) ? $fatura->vencimento
                  : (!empty($fatura->emissao)    ? $fatura->emissao : date('Y-m-d')));

            $cliente = '';
            try { $cliente = $fatura->clientekey->nome ?? ''; } catch (Exception $e) {}

            $num       = $fatura->numero_fatura ?? $fatura->id;
            $descricao = "Fatura #{$num}" . ($cliente ? " - {$cliente}" : '');
            if ($is_antecipada) {
                $descricao .= ' [BAIXA ANTECIPADO BANCO]';
            }

            // Verifica se já existe no caixa
            $caixa_id = $conn->query(
                "SELECT id FROM caixa WHERE referencia_tipo='fatura' AND referencia_id={$fatura_id}"
            )->fetchColumn();

            $caixa = $caixa_id ? new Caixa($caixa_id) : new Caixa;

            if (!$caixa_id) {
                $caixa->tipo            = 'ENTRADA';
                $caixa->categoria       = 'FATURA';
                $caixa->referencia_id   = $fatura->id;
                $caixa->referencia_tipo = 'fatura';
            }

            $caixa->data_lancamento = $data;
            $caixa->descricao       = $descricao;
            $caixa->valor           = $is_antecipada ? $valor_liquido : $valor_original;
            $caixa->tipo_baixa      = $is_antecipada ? $fatura->tipo_baixa : null;
            $caixa->desconto_banco  = $desconto;
            $caixa->status          = !empty($fatura->pagamento) ? 'CONCILIADO' : 'PENDENTE';
            $caixa->store();

            TTransaction::close();

            $msg = $caixa_id
                ? 'Lançamento no Caixa atualizado com sucesso.'
                : 'Fatura enviada ao Caixa com sucesso.';
            new TMessage('info', $msg);
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onDelete($param)
    {
        $action = new TAction([__CLASS__, 'delete'], $param);
        new TQuestion('Deseja realmente excluir esta fatura?', $action);
    }

    public static function delete($param)
    {
        try {
            TTransaction::open(self::$database);
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            $object = new Fatura($param['key']);
            $object->delete();

            TTransaction::close();
            new TMessage('info', 'Registro excluído com sucesso!', new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao excluir: ' . $e->getMessage());
        }
    }

    // ── RECEBER / ESTORNAR ──────────────────────────────────────────────

    public static function onConfirmReceber($param)
    {
        try {
            TTransaction::open(self::$database);
            $fatura = new Fatura($param['key']);

            if (!empty($fatura->pagamento) && $fatura->pagamento !== '0000-00-00') {
                $dt = TDate::convertToMask($fatura->pagamento, 'yyyy-mm-dd', 'dd/mm/yyyy');
                new TMessage('info', "Esta fatura ja foi recebida em {$dt}.");
                TTransaction::close();
                return;
            }

            $num = $fatura->numero_fatura ?? $fatura->id;
            $valor = 'R$ ' . number_format((float)($fatura->valor_fatura ?? 0), 2, ',', '.');
            TTransaction::close();

            $action = new TAction([__CLASS__, 'onReceber'], $param);
            new TQuestion("Confirma recebimento da Fatura #{$num} ({$valor}) com data de hoje?", $action);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public static function onReceber($param)
    {
        try {
            TTransaction::open(self::$database);
            $fatura = new Fatura($param['key']);
            $fatura->pagamento = date('Y-m-d');
            $fatura->store();

            $num = $fatura->numero_fatura ?? $fatura->id;
            TTransaction::close();

            new TMessage('info', "Fatura #{$num} marcada como recebida!", new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public static function onConfirmEstornar($param)
    {
        try {
            TTransaction::open(self::$database);
            $fatura = new Fatura($param['key']);

            if (empty($fatura->pagamento) || $fatura->pagamento === '0000-00-00') {
                new TMessage('info', 'Esta fatura ainda nao foi recebida.');
                TTransaction::close();
                return;
            }

            $num = $fatura->numero_fatura ?? $fatura->id;
            $dt  = TDate::convertToMask($fatura->pagamento, 'yyyy-mm-dd', 'dd/mm/yyyy');
            TTransaction::close();

            $action = new TAction([__CLASS__, 'onEstornar'], $param);
            new TQuestion("Deseja estornar o recebimento da Fatura #{$num} (recebida em {$dt})?", $action);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public static function onEstornar($param)
    {
        try {
            TTransaction::open(self::$database);
            $fatura = new Fatura($param['key']);
            $fatura->pagamento = null;
            $fatura->store();

            $num = $fatura->numero_fatura ?? $fatura->id;
            TTransaction::close();

            new TMessage('info', "Recebimento da Fatura #{$num} estornado!", new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    // ── BAIXAR NO CAIXA ──────────────────────────────────────────────

    /**
     * Verifica se a fatura esta recebida e se e antecipada.
     * Se antecipada, pergunta se ha desconto; se sim, abre FaturaForm.
     */
    public static function onConfirmBaixaCaixa($param)
    {
        try {
            TTransaction::open(self::$database);
            Caixa::createTableIfNotExists();
            $conn = TTransaction::get();
            $fatura = new Fatura($param['key']);

            // Verifica se ja foi recebida
            if (empty($fatura->pagamento) || $fatura->pagamento === '0000-00-00') {
                new TMessage('warning', 'Esta fatura ainda nao foi recebida. Marque como recebida antes de baixar no Caixa.');
                TTransaction::close();
                return;
            }

            // ── BLOQUEIO DE DUPLA BAIXA ──
            $fatura_id = (int) $fatura->id;
            $caixa_existente = $conn->query(
                "SELECT id, valor, data_lancamento FROM caixa WHERE referencia_tipo='fatura' AND referencia_id={$fatura_id}"
            )->fetch(\PDO::FETCH_ASSOC);

            if ($caixa_existente) {
                $num = $fatura->numero_fatura ?? $fatura->id;
                $valorCx = 'R$ ' . number_format((float)$caixa_existente['valor'], 2, ',', '.');
                $dtCx = !empty($caixa_existente['data_lancamento'])
                    ? date('d/m/Y', strtotime($caixa_existente['data_lancamento']))
                    : '—';
                new TMessage('warning',
                    "<b>Fatura #{$num} ja foi baixada no Caixa!</b><br><br>"
                    . "<i class='fa fa-university' style='color:#6f42c1'></i> "
                    . "Lancamento #<b>{$caixa_existente['id']}</b><br>"
                    . "Valor: <b>{$valorCx}</b> | Data: <b>{$dtCx}</b><br><br>"
                    . "<small class='text-muted'>Para refazer a baixa, primeiro estorne o lancamento usando o botao "
                    . "<i class='fa fa-times-circle' style='color:#dc3545'></i> Estornar Caixa.</small>"
                );
                TTransaction::close();
                return;
            }

            $num   = $fatura->numero_fatura ?? $fatura->id;
            $valor = 'R$ ' . number_format((float)($fatura->valor_fatura ?? 0), 2, ',', '.');
            $dtPag = TDate::convertToMask($fatura->pagamento, 'yyyy-mm-dd', 'dd/mm/yyyy');

            // Verifica se e baixa antecipada (pagamento antes do vencimento)
            $is_antecipada = false;
            if (!empty($fatura->vencimento) && $fatura->vencimento !== '0000-00-00') {
                if ($fatura->pagamento < $fatura->vencimento) {
                    $is_antecipada = true;
                }
            }

            // Se ja tem tipo_baixa antecipado com desconto definido, tambem e antecipada
            if (!empty($fatura->tipo_baixa) && $fatura->tipo_baixa === 'BAIXA ANTECIPADO BANCO') {
                $is_antecipada = true;
            }

            TTransaction::close();

            if ($is_antecipada) {
                // Fatura antecipada: perguntar se ha desconto bancario
                $dtVenc = !empty($fatura->vencimento) ? TDate::convertToMask($fatura->vencimento, 'yyyy-mm-dd', 'dd/mm/yyyy') : '—';
                $msg = "<b>Fatura #{$num}</b> ({$valor})<br><br>"
                     . "<i class='fa fa-exclamation-triangle' style='color:#fd7e14'></i> "
                     . "<b>Pagamento antecipado detectado!</b><br>"
                     . "Recebida em: <b>{$dtPag}</b> | Vencimento: <b>{$dtVenc}</b><br><br>"
                     . "Esta fatura possui <b>desconto bancario</b> a informar?<br>"
                     . "<small class='text-muted'>Sim = abre a fatura para editar desconto | Nao = baixa direto no Caixa</small>";

                $actionSim = new TAction([__CLASS__, 'onAbrirFormDesconto'], $param);
                $actionNao = new TAction([__CLASS__, 'onBaixaCaixaDireto'], $param);
                new TQuestion($msg, $actionSim, $actionNao);
            } else {
                // Fatura normal: confirmacao simples para baixar direto
                $msg = "Confirma baixar a Fatura #{$num} ({$valor}) no Caixa?";
                $action = new TAction([__CLASS__, 'onBaixaCaixaDireto'], $param);
                new TQuestion($msg, $action);
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Abre FaturaForm em modo edicao para o usuario informar o desconto
     */
    public static function onAbrirFormDesconto($param)
    {
        AdiantiCoreApplication::loadPage('FaturaForm', 'onEdit', ['key' => $param['key']]);
    }

    /**
     * Baixa a fatura direto no Caixa (sem abrir form)
     */
    public static function onBaixaCaixaDireto($param)
    {
        try {
            TTransaction::open(self::$database);
            Caixa::createTableIfNotExists();
            $conn = TTransaction::get();

            $fatura_id = (int) ($param['key'] ?? 0);
            $fatura    = new Fatura($fatura_id);

            $valor_original = (float) ($fatura->valor_fatura ?? 0);
            if ($valor_original <= 0) {
                throw new Exception('Fatura sem valor definido.');
            }

            $is_antecipada = ($fatura->tipo_baixa === 'BAIXA ANTECIPADO BANCO');
            $desconto      = $is_antecipada ? (float) ($fatura->desconto_banco ?? 0) : 0.0;
            $valor_liquido = max(0.0, $valor_original - $desconto);

            $data = !empty($fatura->pagamento)   ? $fatura->pagamento
                  : (!empty($fatura->vencimento) ? $fatura->vencimento
                  : (!empty($fatura->emissao)    ? $fatura->emissao : date('Y-m-d')));

            $cliente = '';
            try { $cliente = $fatura->clientekey->nome ?? ''; } catch (Exception $e) {}

            $num       = $fatura->numero_fatura ?? $fatura->id;
            $descricao = "Fatura #{$num}" . ($cliente ? " - {$cliente}" : '');
            if ($is_antecipada && $desconto > 0) {
                $descricao .= ' [BAIXA ANTECIPADO BANCO]';
            }

            // Verifica se ja existe no caixa
            $caixa_id = $conn->query(
                "SELECT id FROM caixa WHERE referencia_tipo='fatura' AND referencia_id={$fatura_id}"
            )->fetchColumn();

            $caixa = $caixa_id ? new Caixa($caixa_id) : new Caixa;

            if (!$caixa_id) {
                $caixa->tipo            = 'ENTRADA';
                $caixa->categoria       = 'FATURA';
                $caixa->referencia_id   = $fatura->id;
                $caixa->referencia_tipo = 'fatura';
            }

            $caixa->data_lancamento = $data;
            $caixa->descricao       = $descricao;
            $caixa->valor           = $is_antecipada ? $valor_liquido : $valor_original;
            $caixa->tipo_baixa      = $is_antecipada ? $fatura->tipo_baixa : null;
            $caixa->desconto_banco  = $desconto;
            $caixa->status          = 'CONCILIADO';
            $caixa->store();

            TTransaction::close();

            $valorFmt  = 'R$ ' . number_format((float) $caixa->valor, 2, ',', '.');
            $msg = $caixa_id
                ? "Lancamento no Caixa atualizado! Valor: {$valorFmt}"
                : "Fatura #{$num} baixada no Caixa com sucesso! Valor: {$valorFmt}";

            if ($is_antecipada && $desconto > 0) {
                $descontoFmt = 'R$ ' . number_format($desconto, 2, ',', '.');
                $msg .= " (Desconto: {$descontoFmt})";
            }

            new TMessage('info', $msg, new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    // ── ESTORNAR CAIXA ──────────────────────────────────────────────

    /**
     * Confirmacao para estornar (remover) o lancamento do Caixa
     */
    public static function onConfirmEstornarCaixa($param)
    {
        try {
            TTransaction::open(self::$database);
            Caixa::createTableIfNotExists();
            $conn = TTransaction::get();

            $fatura_id = (int) ($param['key'] ?? 0);
            $fatura = new Fatura($fatura_id);
            $num = $fatura->numero_fatura ?? $fatura->id;

            // Verifica se existe lancamento no Caixa
            $caixa_row = $conn->query(
                "SELECT id, valor, data_lancamento FROM caixa WHERE referencia_tipo='fatura' AND referencia_id={$fatura_id}"
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$caixa_row) {
                new TMessage('info', "Fatura #{$num} nao possui lancamento no Caixa para estornar.");
                TTransaction::close();
                return;
            }

            $valorCx = 'R$ ' . number_format((float)$caixa_row['valor'], 2, ',', '.');
            $dtCx = !empty($caixa_row['data_lancamento'])
                ? date('d/m/Y', strtotime($caixa_row['data_lancamento']))
                : '—';

            TTransaction::close();

            $msg = "<b>Estornar lancamento do Caixa</b><br><br>"
                 . "Fatura: <b>#{$num}</b><br>"
                 . "Lancamento #<b>{$caixa_row['id']}</b> | Valor: <b>{$valorCx}</b> | Data: <b>{$dtCx}</b><br><br>"
                 . "<i class='fa fa-exclamation-triangle' style='color:#dc3545'></i> "
                 . "O lancamento sera <b>removido permanentemente</b> do Caixa.<br>"
                 . "Deseja continuar?";

            $action = new TAction([__CLASS__, 'onEstornarCaixa'], $param);
            new TQuestion($msg, $action);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Remove o lancamento do Caixa vinculado a fatura
     */
    public static function onEstornarCaixa($param)
    {
        try {
            TTransaction::open(self::$database);
            Caixa::createTableIfNotExists();
            $conn = TTransaction::get();

            $fatura_id = (int) ($param['key'] ?? 0);
            $fatura = new Fatura($fatura_id);
            $num = $fatura->numero_fatura ?? $fatura->id;

            // Busca e remove o lancamento
            $caixa_id = $conn->query(
                "SELECT id FROM caixa WHERE referencia_tipo='fatura' AND referencia_id={$fatura_id}"
            )->fetchColumn();

            if ($caixa_id) {
                $caixa = new Caixa($caixa_id);
                $caixa->delete();
            }

            TTransaction::close();

            new TMessage('info', "Lancamento da Fatura #{$num} removido do Caixa com sucesso!", new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }
        parent::show();
    }
}
