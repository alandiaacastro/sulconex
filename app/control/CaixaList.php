<?php
/**
 * CaixaList - Caixa Financeiro
 * Lancamentos de entrada/sa�da, contas a receber (faturas) e a pagar (contratos)
 * com importa��o de extrato OFX.
 */
class CaixaList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;

    public function __construct()
    {
        parent::__construct();

        Caixa::createTableIfNotExists();

        // ── FORMUL�RIO DE FILTRO ───────────────────────────────────────────
        $this->form = new BootstrapFormBuilder('form_search_caixa');
        $this->form->setFormTitle('Caixa Financeiro');

        $data_de   = new TDate('data_de');
        $data_ate  = new TDate('data_ate');
        $categoria = new TCombo('categoria');
        $status    = new TCombo('status');

        $data_de->setMask('dd/mm/yyyy');
        $data_de->setDatabaseMask('yyyy-mm-dd');
        $data_ate->setMask('dd/mm/yyyy');
        $data_ate->setDatabaseMask('yyyy-mm-dd');

        $categoria->addItems([
            ''         => '(Todas)',
            'MANUAL'   => 'Manual',
            'FATURA'   => 'Fatura',
            'CONTRATO' => 'Contrato',
            'EXTRATO'  => 'Extrato',
        ]);
        $status->addItems(['' => '(Todos)', 'PENDENTE' => 'Pendente', 'CONCILIADO' => 'Conciliado']);

        foreach ([$data_de, $data_ate, $categoria, $status] as $f) {
            $f->setSize('100%');
        }

        // 8 slots → label(1) + field(2) × 4 = 12 colunas Bootstrap
        $this->form->setColumnClasses(8, [
            'col-sm-1', 'col-sm-2',
            'col-sm-1', 'col-sm-2',
            'col-sm-1', 'col-sm-2',
            'col-sm-1', 'col-sm-2',
        ]);

        $this->form->addFields(
            [new TLabel('Periodo De')], [$data_de],
            [new TLabel('Ate')],        [$data_ate],
            [new TLabel('Categoria')],  [$categoria],
            [new TLabel('Status')],     [$status]
        );

        $this->form->addAction('Filtrar',            new TAction([$this, 'onSearch']),           'fa:search blue');
        $this->form->addAction('Novo Lancamento',    new TAction(['CaixaForm', 'onEdit']),        'fa:plus green');
        $this->form->addAction('Entradas',           new TAction([$this, 'onFiltrarEntrada']),     'fa:arrow-up green');
        $this->form->addAction('Saidas',             new TAction([$this, 'onFiltrarSaida']),       'fa:arrow-down red');
        $this->form->addAction('Importar OFX',       new TAction(['CaixaImportOFX', 'onShow']),   'fa:university orange');
        $this->form->addAction('Importar Faturas',   new TAction([$this, 'onImportarFaturas']),  'fa:file-invoice-dollar teal');
        $this->form->addAction('Importar Contratos', new TAction([$this, 'onImportarContratos']),'fa:truck purple');
        $this->form->addAction('Relatorio Caixa',    new TAction([$this, 'onRelatorio']),            'fa:file-text-o blue');
        $this->form->addAction('Recarregar',         new TAction([$this, 'onReload']),           'fa:refresh');

        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        // ── DATAGRID ──────────────────────────────────────────────────────
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';

        $colData = new TDataGridColumn('data_lancamento', 'Data', 'center', '9%');
        $colData->setTransformer(function ($v) {
            return $v ? TDate::convertToMask((string)$v, 'yyyy-mm-dd', 'dd/mm/yyyy') : '-';
        });

        $colDesc = new TDataGridColumn('descricao', 'Descricao', 'left', '35%');

        $colTipo = new TDataGridColumn('tipo', 'Tipo', 'center', '9%');
        $colTipo->setTransformer(function ($v) {
            if ($v === 'ENTRADA') {
                return "<span class='badge' style='background:#198754;color:#fff;font-size:.78rem;'>&#8679; ENTRADA</span>";
            }
            return "<span class='badge' style='background:#dc3545;color:#fff;font-size:.78rem;'>&#8681; SAIDA</span>";
        });

        $colCat = new TDataGridColumn('categoria', 'Categoria', 'center', '10%');
        $colCat->setTransformer(function ($v) {
            $cores = [
                'FATURA'   => '#0d6efd',
                'CONTRATO' => '#6f42c1',
                'EXTRATO'  => '#0dcaf0',
                'MANUAL'   => '#6c757d',
            ];
            $cor = $cores[$v] ?? '#6c757d';
            return "<span class='badge' style='background:{$cor};color:#fff;font-size:.78rem;'>" . htmlspecialchars($v ?? '-') . "</span>";
        });

        $colValor = new TDataGridColumn('valor', 'Valor R$', 'right', '12%');
        $colValor->setTransformer(function ($v, $obj) {
            $fmt = 'R$ ' . number_format((float)$v, 2, ',', '.');
            $cor = $obj->tipo === 'ENTRADA' ? '#198754' : '#dc3545';
            return "<span style='color:{$cor};font-weight:600;'>{$fmt}</span>";
        });

        $colStatus = new TDataGridColumn('status', 'Status', 'center', '10%');
        $colStatus->setTransformer(function ($v) {
            if ($v === 'CONCILIADO') {
                return "<span class='badge' style='background:#198754;color:#fff;font-size:.78rem;'>&#10003; Conciliado</span>";
            }
            return "<span class='badge' style='background:#ffc107;color:#333;font-size:.78rem;'>Pendente</span>";
        });

        $this->datagrid->addColumn($colData);
        $this->datagrid->addColumn($colDesc);
        $this->datagrid->addColumn($colTipo);
        $this->datagrid->addColumn($colCat);
        $this->datagrid->addColumn($colValor);
        $this->datagrid->addColumn($colStatus);

        // A��es por linha
        $actionEdit = new TDataGridAction(['CaixaForm', 'onEdit'], ['key' => '{id}']);
        $this->datagrid->addAction($actionEdit, 'Editar', 'fa:edit blue');

        $actionConciliar = new TDataGridAction([$this, 'onConciliar'], ['key' => '{id}']);
        $this->datagrid->addAction($actionConciliar, 'Conciliar', 'fa:check-circle green');

        $actionDelete = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $this->datagrid->addAction($actionDelete, 'Excluir', 'fa:trash red');

        $this->datagrid->createModel();

        // Pagina��o
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());

        // ── LAYOUT ────────────────────────────────────────────────────────
        $panel = new TPanelGroup('');
        $panel->add($this->datagrid);
        $panel->addFooter('<div id="caixa_totais_grid" style="padding:10px 14px;border-top:1px solid #e5e7eb;background:#f8fafc;color:#334155;font-size:.86rem;">Totais do filtro: carregando...</div>');
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);
        $container->add($this->buildKpiPanel());
        $container->add($panel);
        parent::add($container);
    }

    /**
     * Constr�i os cards de KPI financeiro no topo
     */
    private function buildKpiPanel()
    {
        try {
            TTransaction::open('sample');
            $conn = TTransaction::get();

            $mes_ini = date('Y-m-01');
            $mes_fim = date('Y-m-t');

            $saldo = (float)$conn->query(
                "SELECT COALESCE(
                    SUM(CASE WHEN tipo='ENTRADA' THEN CAST(valor AS REAL) ELSE -CAST(valor AS REAL) END)
                ,0) FROM caixa"
            )->fetchColumn();

            $entradas_mes = (float)$conn->query(
                "SELECT COALESCE(SUM(CAST(valor AS REAL)),0) FROM caixa
                 WHERE tipo='ENTRADA' AND data_lancamento BETWEEN '$mes_ini' AND '$mes_fim'"
            )->fetchColumn();

            $saidas_mes = (float)$conn->query(
                "SELECT COALESCE(SUM(CAST(valor AS REAL)),0) FROM caixa
                 WHERE tipo='SAIDA' AND data_lancamento BETWEEN '$mes_ini' AND '$mes_fim'"
            )->fetchColumn();

            $a_receber = (float)$conn->query(
                "SELECT COALESCE(SUM(CAST(valor AS REAL)),0) FROM caixa
                 WHERE tipo='ENTRADA' AND status='PENDENTE'"
            )->fetchColumn();

            $a_pagar = (float)$conn->query(
                "SELECT COALESCE(SUM(CAST(valor AS REAL)),0) FROM caixa
                 WHERE tipo='SAIDA' AND status='PENDENTE'"
            )->fetchColumn();

            TTransaction::close();

            $fmt = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
            $saldo_cor = $saldo >= 0 ? '#198754' : '#dc3545';

            $html = <<<HTML
<style>
.cx-kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:14px;margin-bottom:18px;}
.cx-kpi{border-radius:10px;padding:16px 18px;color:#fff;box-shadow:0 3px 10px rgba(0,0,0,.13);}
.cx-kpi .ck-title{font-size:.75rem;opacity:.85;text-transform:uppercase;letter-spacing:.05em;}
.cx-kpi .ck-val{font-size:1.45rem;font-weight:700;line-height:1.2;word-break:break-word;}
.cx-kpi .ck-sub{font-size:.72rem;opacity:.75;margin-top:3px;}
</style>
<div class="cx-kpi-grid">
  <div class="cx-kpi" style="background:linear-gradient(135deg,{$saldo_cor},#14532d)">
    <div class="ck-title"><i class="fa fa-balance-scale"></i> Saldo Caixa</div>
    <div class="ck-val">{$fmt($saldo)}</div>
    <div class="ck-sub">Posicao atual</div>
  </div>
  <div class="cx-kpi" style="background:linear-gradient(135deg,#198754,#0f5132)">
    <div class="ck-title"><i class="fa fa-arrow-up"></i> Entradas Mes</div>
    <div class="ck-val">{$fmt($entradas_mes)}</div>
    <div class="ck-sub">{$this->mesAtual()}</div>
  </div>
  <div class="cx-kpi" style="background:linear-gradient(135deg,#dc3545,#7b0012)">
    <div class="ck-title"><i class="fa fa-arrow-down"></i> Saidas Mes</div>
    <div class="ck-val">{$fmt($saidas_mes)}</div>
    <div class="ck-sub">{$this->mesAtual()}</div>
  </div>
  <div class="cx-kpi" style="background:linear-gradient(135deg,#0d6efd,#084298)">
    <div class="ck-title"><i class="fa fa-file-invoice"></i> A Receber</div>
    <div class="ck-val">{$fmt($a_receber)}</div>
    <div class="ck-sub">Faturas pendentes</div>
  </div>
  <div class="cx-kpi" style="background:linear-gradient(135deg,#6f42c1,#3d0d6e)">
    <div class="ck-title"><i class="fa fa-truck"></i> A Pagar</div>
    <div class="ck-val">{$fmt($a_pagar)}</div>
    <div class="ck-sub">Contratos pendentes</div>
  </div>
</div>
HTML;
            return TElement::tag('div', $html);
        } catch (Exception $e) {
            return TElement::tag('div', '');
        }
    }

    private function mesAtual()
    {
        $meses = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
        return $meses[(int)date('n') - 1] . '/' . date('Y');
    }

    /**
     * Normaliza valor monetario vindo como float/int/string (pt-BR ou en-US)
     */
    private function parseMoney($value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        $value = str_replace(['R$', ' '], '', $value);

        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (strpos($value, ',') !== false) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }

    // ── ACOES ─────────────────────────────────────────────────────────────

    public function onSearch($param)
    {
        $data = $this->form->getData();
        TSession::setValue(__CLASS__ . '_filter_data', $data);
        $this->onReload();
    }

    public function onFiltrarEntrada($param)
    {
        $data = $this->form->getData();
        $data = $data ?: (object) [];
        $data->tipo = 'ENTRADA';
        $this->form->setData($data);
        TSession::setValue(__CLASS__ . '_filter_data', $data);
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onFiltrarSaida($param)
    {
        $data = $this->form->getData();
        $data = $data ?: (object) [];
        $data->tipo = 'SAIDA';
        $this->form->setData($data);
        TSession::setValue(__CLASS__ . '_filter_data', $data);
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onRelatorio($param)
    {
        CaixaRelatorio::onGenerate($param);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            $repo     = new TRepository('Caixa');
            $criteria = new TCriteria;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', 20);
            $criteria->setProperty('order', 'data_lancamento');
            $criteria->setProperty('direction', 'desc');

            $data = TSession::getValue(__CLASS__ . '_filter_data');

            if (!empty($data->data_de)) {
                $de = TDate::convertToMask($data->data_de, 'dd/mm/yyyy', 'yyyy-mm-dd');
                TSession::setValue(__CLASS__ . '_filter_data_de', new TFilter('data_lancamento', '>=', $de));
            } else {
                TSession::setValue(__CLASS__ . '_filter_data_de', null);
            }
            if (!empty($data->data_ate)) {
                $ate = TDate::convertToMask($data->data_ate, 'dd/mm/yyyy', 'yyyy-mm-dd');
                TSession::setValue(__CLASS__ . '_filter_data_ate', new TFilter('data_lancamento', '<=', $ate));
            } else {
                TSession::setValue(__CLASS__ . '_filter_data_ate', null);
            }
            if (!empty($data->tipo)) {
                TSession::setValue(__CLASS__ . '_filter_tipo', new TFilter('tipo', '=', $data->tipo));
            } else {
                TSession::setValue(__CLASS__ . '_filter_tipo', null);
            }
            if (!empty($data->categoria)) {
                TSession::setValue(__CLASS__ . '_filter_categoria', new TFilter('categoria', '=', $data->categoria));
            } else {
                TSession::setValue(__CLASS__ . '_filter_categoria', null);
            }
            if (!empty($data->status)) {
                TSession::setValue(__CLASS__ . '_filter_status', new TFilter('status', '=', $data->status));
            } else {
                TSession::setValue(__CLASS__ . '_filter_status', null);
            }

            foreach (['_filter_data_de','_filter_data_ate','_filter_tipo','_filter_categoria','_filter_status'] as $k) {
                $f = TSession::getValue(__CLASS__ . $k);
                if ($f) { $criteria->add($f); }
            }

            $objects = $repo->load($criteria, FALSE);
            $this->datagrid->clear();
            if ($objects) {
                foreach ($objects as $obj) {
                    $this->datagrid->addItem($obj);
                }
            }

            $count_criteria = clone $criteria;
            $count_criteria->resetProperties();
            $count = $repo->count($count_criteria);
            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setLimit(20);

            $all_objects = $repo->load($count_criteria, FALSE) ?: [];
            $total_entradas = 0.0;
            $total_saidas = 0.0;

            foreach ($all_objects as $item) {
                $valor = (float) ($item->valor ?? 0);
                if (($item->tipo ?? '') === 'ENTRADA') {
                    $total_entradas += $valor;
                } else {
                    $total_saidas += $valor;
                }
            }

            $saldo_filtro = $total_entradas - $total_saidas;
            $fmt = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
            $totais_html = "<strong>Totais do filtro:</strong> "
                         . "<span style='color:#198754;font-weight:600'>Entradas " . $fmt($total_entradas) . "</span> | "
                         . "<span style='color:#dc3545;font-weight:600'>Saidas " . $fmt($total_saidas) . "</span> | "
                         . "<span style='color:" . ($saldo_filtro >= 0 ? '#0f5132' : '#842029') . ";font-weight:700'>Saldo " . $fmt($saldo_filtro) . "</span>";
            $totais_json = json_encode($totais_html, JSON_UNESCAPED_UNICODE);
            TScript::create("if (document.getElementById('caixa_totais_grid')) { document.getElementById('caixa_totais_grid').innerHTML = " . $totais_json . "; }");

            TTransaction::close();
            $this->loaded = true;
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onDelete($param)
    {
        try {
            TTransaction::open('sample');
            $caixa = new Caixa($param['key']);
            $caixa->delete();
            TTransaction::close();
            $this->onReload($param);
            new TMessage('info', 'Lancamento exclu�do.');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onConciliar($param)
    {
        try {
            TTransaction::open('sample');
            $caixa = new Caixa($param['key']);
            $caixa->status = 'CONCILIADO';
            $caixa->store();
            TTransaction::close();
            $this->onReload($param);
            new TMessage('info', 'Lancamento marcado como conciliado.');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Importa faturas como contas a receber
     */
    public function onImportarFaturas($param)
    {
        try {
            TTransaction::open('sample');
            $conn = TTransaction::get();

            // IDs de faturas j� importadas
            $importadas = $conn->query(
                "SELECT referencia_id FROM caixa WHERE referencia_tipo='fatura'"
            )->fetchAll(\PDO::FETCH_COLUMN);

            $faturas = (new TRepository('Fatura'))->load(new TCriteria, FALSE);
            $count = 0;

            foreach ($faturas as $fatura) {
                if (in_array($fatura->id, $importadas)) {
                    continue;
                }

                $valor = $this->parseMoney($fatura->valor_fatura ?? 0);
                if ($valor <= 0) {
                    continue;
                }

                $data = !empty($fatura->vencimento) ? $fatura->vencimento
                      : (!empty($fatura->emissao)   ? $fatura->emissao : date('Y-m-d'));

                $cliente = '';
                try { $cliente = $fatura->clientekey->nome ?? ''; } catch (Exception $e) {}

                $num = $fatura->numero_fatura ?? $fatura->id;
                $descricao = "Fatura #{$num}" . ($cliente ? " - {$cliente}" : '');

                $caixa = new Caixa;
                $caixa->data_lancamento = $data;
                $caixa->descricao      = $descricao;
                $caixa->tipo           = 'ENTRADA';
                $caixa->valor          = $valor;
                $caixa->categoria      = 'FATURA';
                $caixa->referencia_id  = $fatura->id;
                $caixa->referencia_tipo = 'fatura';
                $caixa->status         = !empty($fatura->pagamento) ? 'CONCILIADO' : 'PENDENTE';
                $caixa->store();
                $count++;
            }

            TTransaction::close();
            $this->onReload();
            new TMessage('info', "{$count} fatura(s) importada(s) como contas a receber.");
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Importa contratos (cartas frete) como contas a pagar
     */
    public function onImportarContratos($param)
    {
        try {
            TTransaction::open('sample');
            $conn = TTransaction::get();

            $importados = $conn->query(
                "SELECT referencia_id FROM caixa WHERE referencia_tipo='contrato'"
            )->fetchAll(\PDO::FETCH_COLUMN);

            $contratos = (new TRepository('Contrato'))->load(new TCriteria, FALSE);
            $count = 0;

            foreach ($contratos as $contrato) {
                if (in_array($contrato->id, $importados)) {
                    continue;
                }

                $valor = $this->parseMoney($contrato->saldo1 ?? 0);
                if ($valor <= 0) {
                    // tenta frete1 se saldo1 for zero
                    $valor = $this->parseMoney($contrato->frete1 ?? 0);
                }
                if ($valor <= 0) {
                    continue;
                }

                $data = !empty($contrato->vencimento) ? $contrato->vencimento
                      : (!empty($contrato->emissao)   ? $contrato->emissao : date('Y-m-d'));

                $transportadora = '';
                try { $transportadora = $contrato->get_permisso()->transportadora ?? ''; } catch (Exception $e) {}

                $crt = $contrato->conhecimento_numero ?? '';
                $descricao = "Carta Frete #{$contrato->id}"
                           . ($crt           ? " - CRT {$crt}" : '')
                           . ($transportadora ? " - {$transportadora}" : '');

                $caixa = new Caixa;
                $caixa->data_lancamento = $data;
                $caixa->descricao       = $descricao;
                $caixa->tipo            = 'SAIDA';
                $caixa->valor           = $valor;
                $caixa->categoria       = 'CONTRATO';
                $caixa->referencia_id   = $contrato->id;
                $caixa->referencia_tipo = 'contrato';
                $caixa->status          = ($contrato->pago === 'S') ? 'CONCILIADO' : 'PENDENTE';
                $caixa->store();
                $count++;
            }

            TTransaction::close();
            $this->onReload();
            new TMessage('info', "{$count} contrato(s) importado(s) como contas a pagar.");
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

















