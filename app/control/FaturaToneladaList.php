<?php

class FaturaToneladaList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;
    private static $caixaIndex = [];

    private static $database = 'sample';
    private static $activeRecord = 'FaturaTonelada';
    private static $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
        FaturaTonelada::ensureSchema();

        $this->form = new BootstrapFormBuilder('form_search_fatura_tonelada');
        $this->form->setFormTitle('Faturas por Tonelada');

        $id             = new TEntry('id');
        $numero_fatura  = new TEntry('numero_fatura');
        $numero_crt     = new TEntry('numero_crt');
        $fatura_cliente = new TEntry('fatura_cliente');
        $pessoa_id      = new TDBUniqueSearch('pessoa_id', self::$database, 'Clientes', 'id', 'nome');
        $emissao_de     = new TDate('emissao_de');
        $emissao_ate    = new TDate('emissao_ate');

        foreach ([$id, $numero_fatura, $numero_crt, $fatura_cliente, $pessoa_id, $emissao_de, $emissao_ate] as $field) {
            $field->setSize('100%');
        }

        $pessoa_id->setMinLength(0);
        $pessoa_id->setMask('{nome}');
        $emissao_de->setMask('dd/mm/yyyy');
        $emissao_de->setDatabaseMask('yyyy-mm-dd');
        $emissao_ate->setMask('dd/mm/yyyy');
        $emissao_ate->setDatabaseMask('yyyy-mm-dd');

        $this->form->addFields([new TLabel('ID')], [$id], [new TLabel('Numero Fatura')], [$numero_fatura]);
        $this->form->addFields([new TLabel('Numero CRT')], [$numero_crt], [new TLabel('Fat. Cliente')], [$fatura_cliente]);
        $this->form->addFields([new TLabel('Cliente')], [$pessoa_id]);
        $this->form->addFields([new TLabel('Emissao (de)')], [$emissao_de], [new TLabel('Emissao (ate)')], [$emissao_ate]);

        $this->form->addAction('Filtrar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addAction('Nova', new TAction(['FaturaToneladaForm', 'onEdit']), 'fa:plus green');
        $this->form->addAction('Recarregar', new TAction([$this, 'onReload']), 'fa:refresh');
        $this->form->addAction('Limpar Filtros', new TAction([$this, 'onClearFilters']), 'fa:times gray');
        $this->form->addAction('Vencidas', new TAction([$this, 'onFilterStatus'], ['status' => 'vencidas']), 'fa:exclamation-circle red');
        $this->form->addAction('A Vencer', new TAction([$this, 'onFilterStatus'], ['status' => 'a_vencer']), 'fa:clock-o orange');
        $this->form->addAction('Pagas', new TAction([$this, 'onFilterStatus'], ['status' => 'pagas']), 'fa:check-circle green');
        $this->form->addAction('Pendentes', new TAction([$this, 'onFilterStatus'], ['status' => 'pendentes']), 'fa:hourglass-half gray');

        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';
        $this->datagrid->disableDefaultClick();

        $colId = new TDataGridColumn('id', 'ID', 'center', '5%');
        $colFatura = new TDataGridColumn('numero_fatura', 'Fatura', 'left', '10%');
        $colCrt = new TDataGridColumn('numero_crt', 'CRT', 'left', '8%');
        $colFatCliente = new TDataGridColumn('fatura_cliente', 'Fat. Cliente', 'left', '12%');
        $colCliente = new TDataGridColumn('clientekey->nome', 'Cliente', 'left', '18%');
        $colVencimento = new TDataGridColumn('vencimento', 'Vencimento', 'center', '8%');
        $colValor = new TDataGridColumn('valor_fatura', 'Valor', 'right', '9%');
        $colStatus = new TDataGridColumn('pagamento', 'Status', 'center', '12%');

        $colId->setAction(new TAction([$this, 'onReload']), ['order' => 'id']);
        $colFatura->setAction(new TAction([$this, 'onReload']), ['order' => 'numero_fatura']);
        $colCrt->setAction(new TAction([$this, 'onReload']), ['order' => 'numero_crt']);
        $colFatCliente->setAction(new TAction([$this, 'onReload']), ['order' => 'fatura_cliente']);
        $colVencimento->setAction(new TAction([$this, 'onReload']), ['order' => 'vencimento']);
        $colValor->setAction(new TAction([$this, 'onReload']), ['order' => 'valor_fatura']);

        $colCliente->setTransformer(function ($value, $obj) {
            try {
                return $obj->clientekey->nome ?? '-';
            } catch (Exception $e) {
                return '-';
            }
        });

        $colVencimento->setTransformer(function ($value) {
            return $value ? TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '';
        });


        $colValor->setTransformer(function ($value) {
            if ($value === null || $value === '') {
                return '';
            }
            return 'R$ ' . number_format((float) $value, 2, ',', '.');
        });

        $colStatus->setTransformer(function ($pagamento, $obj) {
            $hoje = date('Y-m-d');
            $html = '';

            if (!empty($pagamento) && $pagamento !== '0000-00-00') {
                $dt = TDate::convertToMask($pagamento, 'yyyy-mm-dd', 'dd/mm/yyyy');
                if (!empty($obj->tipo_baixa) && $obj->tipo_baixa === 'BAIXA ANTECIPADO BANCO') {
                    $desc = $obj->desconto_banco > 0
                        ? ' (-R$ ' . number_format((float) $obj->desconto_banco, 2, ',', '.') . ')'
                        : '';
                    $html .= "<span class='badge' style='background:#6f42c1;color:#fff;font-size:.75rem;padding:3px 7px'>"
                           . "<i class='fa fa-university'></i> Antecipado {$dt}{$desc}</span>";
                } else {
                    $html .= "<span class='badge' style='background:#198754;color:#fff;font-size:.75rem;padding:3px 7px'>"
                           . "<i class='fa fa-check'></i> Recebida {$dt}</span>";
                }
            } else {
                $venc = $obj->vencimento ?? '';
                if (!empty($venc) && $venc < $hoje) {
                    $dias = (int) ceil((strtotime($hoje) - strtotime($venc)) / 86400);
                    $html .= "<span class='badge' style='background:#dc3545;color:#fff;font-size:.75rem;padding:3px 7px'>"
                           . "<i class='fa fa-exclamation-circle'></i> Atrasada {$dias}d</span>";
                } elseif (!empty($venc) && $venc >= $hoje) {
                    $dias = (int) ceil((strtotime($venc) - strtotime($hoje)) / 86400);
                    $cor = $dias <= 7 ? '#fd7e14' : '#0d6efd';
                    $html .= "<span class='badge' style='background:{$cor};color:#fff;font-size:.75rem;padding:3px 7px'>"
                           . "<i class='fa fa-clock-o'></i> Vence em {$dias}d</span>";
                } else {
                    $html .= "<span class='badge' style='background:#6c757d;color:#fff;font-size:.75rem;padding:3px 7px'>Pendente</span>";
                }
            }

            $fid = (int) $obj->id;
            if (!empty(self::$caixaIndex[$fid])) {
                $html .= "<br><span class='badge' style='background:#0dcaf0;color:#000;font-size:.65rem;padding:2px 5px;margin-top:2px'>"
                       . "<i class='fa fa-university'></i> No Caixa</span>";
            }

            return $html;
        });

        $this->datagrid->addColumn($colId);
        $this->datagrid->addColumn($colFatura);
        $this->datagrid->addColumn($colCrt);
        $this->datagrid->addColumn($colFatCliente);
        $this->datagrid->addColumn($colCliente);
        $this->datagrid->addColumn($colVencimento);
        $this->datagrid->addColumn($colValor);
        $this->datagrid->addColumn($colStatus);

        $actionEdit = new TDataGridAction(['FaturaToneladaForm', 'onEdit'], ['key' => '{id}']);
        $this->datagrid->addAction($actionEdit, 'Editar', 'fa:edit blue');

        $actionPrint = new TDataGridAction([__CLASS__, 'onImprimir'], ['key' => '{id}']);
        $this->datagrid->addAction($actionPrint, 'Imprimir', 'fa:print green');

        if (TSession::getValue('login') === 'admin') {
            $actionDelete = new TDataGridAction([__CLASS__, 'onDelete'], ['key' => '{id}']);
            $this->datagrid->addAction($actionDelete, 'Excluir', 'fa:trash red');
        }

        $group = new TDataGridActionGroup('Financeiro', 'fa:cog');

        $actionReceber = new TDataGridAction([__CLASS__, 'onConfirmReceber'], ['key' => '{id}']);
        $actionReceber->setLabel('Marcar Recebida');
        $actionReceber->setImage('fa:check-circle green');
        $group->addAction($actionReceber);

        $actionEstornar = new TDataGridAction([__CLASS__, 'onConfirmEstornar'], ['key' => '{id}']);
        $actionEstornar->setLabel('Estornar Recebimento');
        $actionEstornar->setImage('fa:undo orange');
        $group->addAction($actionEstornar);

        $group->addSeparator();

        $actionBaixa = new TDataGridAction([__CLASS__, 'onConfirmBaixaCaixa'], ['key' => '{id}']);
        $actionBaixa->setLabel('Baixar no Caixa');
        $actionBaixa->setImage('fa:university purple');
        $group->addAction($actionBaixa);

        $actionEstornarCaixa = new TDataGridAction([__CLASS__, 'onConfirmEstornarCaixa'], ['key' => '{id}']);
        $actionEstornarCaixa->setLabel('Estornar Caixa');
        $actionEstornarCaixa->setImage('fa:times-circle red');
        $group->addAction($actionEstornarCaixa);

        $this->datagrid->addActionGroup($group);

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->enableCounters();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth('100%');

        $panel = new TPanelGroup('Listagem de Faturas por Tonelada');
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        $container->add($this->buildKpiPanel());
        $container->add($panel);

        parent::add($container);

        TScript::create("
            (function() {
                var \$card   = \$('#form_search_fatura_tonelada').closest('.card');
                var \$header = \$card.find('.card-header').first();
                var \$body   = \$card.find('.card-body').first();
                if (!\$header.length || !\$body.length) return;

                \$header.css('cursor','pointer');
                \$header.append('<span style=\"float:right;margin-left:8px\"><i class=\"fa fa-chevron-down\" id=\"fat-ton-filter-icon\"></i></span>');
                \$body.hide();

                \$header.on('click', function() {
                    \$body.slideToggle(180);
                    \$('#fat-ton-filter-icon').toggleClass('fa-chevron-up fa-chevron-down');
                });
            })();
        ");
    }

    private function buildKpiPanel()
    {
        try {
            TTransaction::open(self::$database);
            $conn = TTransaction::get();
            $hoje = date('Y-m-d');
            $prox7 = date('Y-m-d', strtotime('+7 days'));
            $prox30 = date('Y-m-d', strtotime('+30 days'));

            $r = $conn->query("
                SELECT COUNT(*) AS qtd, COALESCE(SUM(CAST(valor_fatura AS REAL)), 0) AS total
                FROM fatura_tonelada
                WHERE (pagamento IS NULL OR pagamento = '' OR pagamento = '0000-00-00')
                  AND (vencimento IS NULL OR vencimento = '' OR vencimento >= '{$hoje}')
            ")->fetch(\PDO::FETCH_ASSOC);
            $qtd_receber = (int) $r['qtd'];
            $val_receber = (float) $r['total'];

            $r = $conn->query("
                SELECT COUNT(*) AS qtd, COALESCE(SUM(CAST(valor_fatura AS REAL)), 0) AS total
                FROM fatura_tonelada
                WHERE pagamento IS NOT NULL AND pagamento != '' AND pagamento != '0000-00-00'
            ")->fetch(\PDO::FETCH_ASSOC);
            $qtd_recebidas = (int) $r['qtd'];
            $val_recebidas = (float) $r['total'];

            $r = $conn->query("
                SELECT COUNT(*) AS qtd, COALESCE(SUM(CAST(valor_fatura AS REAL)), 0) AS total
                FROM fatura_tonelada
                WHERE (pagamento IS NULL OR pagamento = '' OR pagamento = '0000-00-00')
                  AND vencimento IS NOT NULL AND vencimento != '' AND vencimento < '{$hoje}'
            ")->fetch(\PDO::FETCH_ASSOC);
            $qtd_atrasadas = (int) $r['qtd'];
            $val_atrasadas = (float) $r['total'];

            $r = $conn->query("
                SELECT COUNT(*) AS qtd, COALESCE(SUM(CAST(valor_fatura AS REAL)), 0) AS total
                FROM fatura_tonelada
                WHERE (pagamento IS NULL OR pagamento = '' OR pagamento = '0000-00-00')
                  AND vencimento BETWEEN '{$hoje}' AND '{$prox7}'
            ")->fetch(\PDO::FETCH_ASSOC);
            $qtd_7d = (int) $r['qtd'];
            $val_7d = (float) $r['total'];

            $r = $conn->query("
                SELECT COUNT(*) AS qtd, COALESCE(SUM(CAST(valor_fatura AS REAL)), 0) AS total
                FROM fatura_tonelada
                WHERE (pagamento IS NULL OR pagamento = '' OR pagamento = '0000-00-00')
                  AND vencimento BETWEEN '{$hoje}' AND '{$prox30}'
            ")->fetch(\PDO::FETCH_ASSOC);
            $qtd_30d = (int) $r['qtd'];
            $val_30d = (float) $r['total'];

            $proximas = $conn->query("
                SELECT f.id, f.numero_fatura, f.vencimento, f.valor_fatura,
                       c.nome AS cliente_nome
                FROM fatura_tonelada f
                LEFT JOIN clientes c ON c.id = f.pessoa_id
                WHERE (f.pagamento IS NULL OR f.pagamento = '' OR f.pagamento = '0000-00-00')
                  AND f.vencimento IS NOT NULL AND f.vencimento != ''
                ORDER BY f.vencimento ASC
                LIMIT 6
            ")->fetchAll(\PDO::FETCH_ASSOC);

            TTransaction::close();

            $fmt = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

            $rows_prox = '';
            foreach ($proximas as $p) {
                $venc_dt = $p['vencimento'] ?? '';
                $dias = $venc_dt ? (int) ceil((strtotime($venc_dt) - strtotime($hoje)) / 86400) : null;
                $venc_fmt = $venc_dt ? date('d/m/Y', strtotime($venc_dt)) : '-';

                if ($dias === null) {
                    $badge = "<span class='badge bg-secondary'>-</span>";
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
    <div class="fk-title"><i class="fa fa-calendar-exclamation-o"></i> Prox. 7 dias</div>
    <div class="fk-val">{$fmt($val_7d)}</div>
    <div class="fk-sub">{$qtd_7d} fatura(s) a vencer</div>
  </div>
  <div class="fat-kpi" style="background:linear-gradient(135deg,#6f42c1,#3a1d6e)">
    <div class="fk-title"><i class="fa fa-calendar"></i> Prox. 30 dias</div>
    <div class="fk-val">{$fmt($val_30d)}</div>
    <div class="fk-sub">{$qtd_30d} fatura(s) a vencer</div>
  </div>
</div>
<div class="fat-prox">
  <h6><i class="fa fa-list-alt"></i> Proximas Faturas a Receber</h6>
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
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            return TElement::tag('div', '');
        }
    }

    public static function onFilterStatus($param = null)
    {
        TSession::setValue(__CLASS__ . '_filter_data', null);
        TSession::setValue(__CLASS__ . '_filter_status', $param['status'] ?? '');
        TForm::sendData('form_search_fatura_tonelada', (object) []);
        AdiantiCoreApplication::loadPage(__CLASS__, 'onReload', ['offset' => 0, 'first_page' => 1]);
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();
        TSession::setValue(__CLASS__ . '_filter_data', $data);
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onClearFilters($param = null)
    {
        TSession::setValue(__CLASS__ . '_filter_data', null);
        TSession::setValue(__CLASS__ . '_filter_status', null);
        TForm::sendData('form_search_fatura_tonelada', (object) []);
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open(self::$database);

            $repo = new TRepository(self::$activeRecord);
            $criteria = new TCriteria;
            $limit = 10;
            $param = is_array($param) ? $param : [];

            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);
            if (empty($param['order'])) {
                $criteria->setProperty('order', self::$primaryKey);
                $criteria->setProperty('direction', 'desc');
            }

            $filterData = TSession::getValue(__CLASS__ . '_filter_data');
            if ($filterData) {
                $this->form->setData($filterData);

                if (!empty($filterData->id)) {
                    $criteria->add(new TFilter('id', '=', $filterData->id));
                }
                if (!empty($filterData->numero_fatura)) {
                    $criteria->add(new TFilter('numero_fatura', 'like', '%' . $filterData->numero_fatura . '%'));
                }
                if (!empty($filterData->numero_crt)) {
                    $criteria->add(new TFilter('numero_crt', 'like', '%' . $filterData->numero_crt . '%'));
                }
                if (!empty($filterData->fatura_cliente)) {
                    $criteria->add(new TFilter('fatura_cliente', 'like', '%' . $filterData->fatura_cliente . '%'));
                }
                if (!empty($filterData->pessoa_id)) {
                    $criteria->add(new TFilter('pessoa_id', '=', $filterData->pessoa_id));
                }
                if (!empty($filterData->emissao_de)) {
                    $criteria->add(new TFilter('emissao', '>=', TDate::convertToMask($filterData->emissao_de, 'dd/mm/yyyy', 'yyyy-mm-dd')));
                }
                if (!empty($filterData->emissao_ate)) {
                    $criteria->add(new TFilter('emissao', '<=', TDate::convertToMask($filterData->emissao_ate, 'dd/mm/yyyy', 'yyyy-mm-dd')));
                }
            }

            $status = TSession::getValue(__CLASS__ . '_filter_status');
            $hoje = date('Y-m-d');
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

            // Cache caixa ids para uso no transformer (evita N queries)
            $conn = TTransaction::get();
            self::$caixaIndex = [];
            $ids = $conn->query("SELECT referencia_id FROM caixa WHERE referencia_tipo='fatura_tonelada'")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($ids as $cid) {
                self::$caixaIndex[(int)$cid] = true;
            }

            $this->datagrid->clear();
            $objects = $repo->load($criteria, false);
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
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function onImprimir($param)
    {
        FaturaToneladaReport::onGenerateReais($param);
    }

    public static function onDelete($param)
    {
        if (TSession::getValue('login') !== 'admin') {
            new TMessage('error', 'Acesso restrito. Somente administradores podem excluir faturas.');
            return;
        }
        $action = new TAction([__CLASS__, 'delete'], $param);
        new TQuestion('Deseja realmente excluir esta fatura por tonelada?', $action);
    }

    public static function delete($param)
    {
        if (TSession::getValue('login') !== 'admin') {
            new TMessage('error', 'Acesso restrito. Somente administradores podem excluir faturas.');
            return;
        }
        try {
            TTransaction::open(self::$database);
            $conn = TTransaction::get();

            $fatura         = new FaturaTonelada($param['key']);
            $conhecimentoId = (int) ($fatura->conhecimento_id ?? 0);
            $faturaId       = (int) $fatura->id;

            // 1. Remove lançamento do caixa vinculado (evita caixa órfão)
            $conn->exec(
                "DELETE FROM caixa WHERE referencia_tipo='fatura_tonelada' AND referencia_id={$faturaId}"
            );

            // 2. Deleta a fatura_tonelada
            $fatura->delete();

            // 3. Recalcula snapshots nas demais faturas do mesmo CRT
            if ($conhecimentoId > 0) {
                $stmt = $conn->prepare(
                    "SELECT id FROM fatura_tonelada WHERE conhecimento_id = :cid ORDER BY id ASC"
                );
                $stmt->execute([':cid' => $conhecimentoId]);
                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($ids as $fid) {
                    $f      = new FaturaTonelada((int) $fid);
                    $resumo = FaturaTonelada::getResumoToneladas($conhecimentoId, (int) $fid);
                    $f->toneladas_ja_faturadas = $resumo['faturadas'];
                    $f->toneladas_saldo        = max(0, $resumo['saldo'] - (float) $f->toneladas_faturadas);
                    $f->store();
                }
            }

            TTransaction::close();
            new TMessage('info', 'Registro excluido com sucesso!', new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function onConfirmReceber($param)
    {
        try {
            TTransaction::open(self::$database);
            $fatura = new FaturaTonelada($param['key']);

            if (!empty($fatura->pagamento) && $fatura->pagamento !== '0000-00-00') {
                $dt = TDate::convertToMask($fatura->pagamento, 'yyyy-mm-dd', 'dd/mm/yyyy');
                new TMessage('info', "Esta fatura ja foi recebida em {$dt}.");
                TTransaction::close();
                return;
            }

            $num = $fatura->numero_fatura ?? $fatura->id;
            $valor = 'R$ ' . number_format((float) ($fatura->valor_fatura ?? 0), 2, ',', '.');
            TTransaction::close();

            $action = new TAction([__CLASS__, 'onReceber'], $param);
            new TQuestion("Confirma recebimento da Fatura #{$num} ({$valor}) com data de hoje?", $action);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function onReceber($param)
    {
        try {
            TTransaction::open(self::$database);
            $fatura = new FaturaTonelada($param['key']);
            $fatura->pagamento = date('Y-m-d');
            $fatura->store();
            $num = $fatura->numero_fatura ?? $fatura->id;
            TTransaction::close();

            new TMessage('info', "Fatura #{$num} marcada como recebida!", new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function onConfirmEstornar($param)
    {
        try {
            TTransaction::open(self::$database);
            $fatura = new FaturaTonelada($param['key']);

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
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function onEstornar($param)
    {
        try {
            TTransaction::open(self::$database);
            $fatura = new FaturaTonelada($param['key']);
            $fatura->pagamento = null;
            $fatura->store();
            $num = $fatura->numero_fatura ?? $fatura->id;
            TTransaction::close();

            new TMessage('info', "Recebimento da Fatura #{$num} estornado!", new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function onConfirmBaixaCaixa($param)
    {
        try {
            TTransaction::open(self::$database);
            Caixa::createTableIfNotExists();
            $conn = TTransaction::get();
            $fatura = new FaturaTonelada($param['key']);

            if (empty($fatura->pagamento) || $fatura->pagamento === '0000-00-00') {
                new TMessage('warning', 'Esta fatura ainda nao foi recebida. Marque como recebida antes de baixar no Caixa.');
                TTransaction::close();
                return;
            }

            $fatura_id = (int) $fatura->id;
            $caixa_existente = $conn->query(
                "SELECT id FROM caixa WHERE referencia_tipo='fatura_tonelada' AND referencia_id={$fatura_id}"
            )->fetchColumn();

            if ($caixa_existente) {
                new TMessage('warning', 'Esta fatura ja possui lancamento no Caixa.');
                TTransaction::close();
                return;
            }

            $num = $fatura->numero_fatura ?? $fatura->id;
            $valor = 'R$ ' . number_format((float) ($fatura->valor_fatura ?? 0), 2, ',', '.');
            $is_antecipada = !empty($fatura->vencimento) && !empty($fatura->pagamento) && $fatura->pagamento < $fatura->vencimento;
            if (!empty($fatura->tipo_baixa) && $fatura->tipo_baixa === 'BAIXA ANTECIPADO BANCO') {
                $is_antecipada = true;
            }
            TTransaction::close();

            if ($is_antecipada) {
                $actionSim = new TAction([__CLASS__, 'onAbrirFormDesconto'], $param);
                $actionNao = new TAction([__CLASS__, 'onBaixaCaixaDireto'], $param);
                new TQuestion("A Fatura #{$num} ({$valor}) possui desconto bancario? Sim abre o formulario; nao baixa direto no Caixa.", $actionSim, $actionNao);
                return;
            }

            $action = new TAction([__CLASS__, 'onBaixaCaixaDireto'], $param);
            new TQuestion("Confirma baixar a Fatura #{$num} ({$valor}) no Caixa?", $action);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function onAbrirFormDesconto($param)
    {
        AdiantiCoreApplication::loadPage('FaturaToneladaForm', 'onEdit', ['key' => $param['key']]);
    }

    public static function onBaixaCaixaDireto($param)
    {
        try {
            TTransaction::open(self::$database);
            Caixa::createTableIfNotExists();
            $conn = TTransaction::get();

            $fatura = new FaturaTonelada($param['key']);
            $valor_original = (float) ($fatura->valor_fatura ?? 0);
            if ($valor_original <= 0) {
                throw new Exception('Fatura sem valor definido.');
            }

            $is_antecipada = ($fatura->tipo_baixa === 'BAIXA ANTECIPADO BANCO');
            $desconto = $is_antecipada ? (float) ($fatura->desconto_banco ?? 0) : 0.0;
            $valor_liquido = max(0.0, $valor_original - $desconto);
            $data = !empty($fatura->pagamento) ? $fatura->pagamento
                  : (!empty($fatura->vencimento) ? $fatura->vencimento
                  : (!empty($fatura->emissao) ? $fatura->emissao : date('Y-m-d')));

            $cliente = '';
            try {
                $cliente = $fatura->clientekey->nome ?? '';
            } catch (Exception $e) {
            }

            $num = $fatura->numero_fatura ?? $fatura->id;
            $descricao = "Fatura Tonelada #{$num}" . ($cliente ? " - {$cliente}" : '');

            $caixa = new Caixa;
            $caixa->tipo = 'ENTRADA';
            $caixa->categoria = 'FATURA';
            $caixa->referencia_id = $fatura->id;
            $caixa->referencia_tipo = 'fatura_tonelada';
            $caixa->data_lancamento = $data;
            $caixa->descricao = $descricao;
            $caixa->valor = $is_antecipada ? $valor_liquido : $valor_original;
            $caixa->tipo_baixa = $is_antecipada ? $fatura->tipo_baixa : null;
            $caixa->desconto_banco = $desconto;
            $caixa->status = 'CONCILIADO';
            $caixa->store();

            TTransaction::close();
            new TMessage('info', "Fatura #{$num} baixada no Caixa com sucesso!", new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function onConfirmEstornarCaixa($param)
    {
        try {
            TTransaction::open(self::$database);
            Caixa::createTableIfNotExists();
            $conn = TTransaction::get();

            $fatura_id = (int) ($param['key'] ?? 0);
            $caixa_row = $conn->query(
                "SELECT id, valor FROM caixa WHERE referencia_tipo='fatura_tonelada' AND referencia_id={$fatura_id}"
            )->fetch(PDO::FETCH_ASSOC);

            if (!$caixa_row) {
                new TMessage('info', 'Nao existe lancamento no Caixa para esta fatura.');
                TTransaction::close();
                return;
            }

            TTransaction::close();
            $action = new TAction([__CLASS__, 'onEstornarCaixa'], $param);
            new TQuestion('Deseja remover o lancamento desta fatura no Caixa?', $action);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function onEstornarCaixa($param)
    {
        try {
            TTransaction::open(self::$database);
            Caixa::createTableIfNotExists();
            $conn = TTransaction::get();

            $fatura_id = (int) ($param['key'] ?? 0);
            $caixa_id = $conn->query(
                "SELECT id FROM caixa WHERE referencia_tipo='fatura_tonelada' AND referencia_id={$fatura_id}"
            )->fetchColumn();

            if ($caixa_id) {
                $caixa = new Caixa($caixa_id);
                $caixa->delete();
            }

            TTransaction::close();
            new TMessage('info', 'Lancamento no Caixa removido com sucesso!', new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
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
