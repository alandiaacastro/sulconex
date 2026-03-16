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

        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        // Datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';

        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center', '5%'));
        $this->datagrid->addColumn(new TDataGridColumn('numero_fatura', 'Fatura', 'left', '10%'));
        $this->datagrid->addColumn(new TDataGridColumn('numero_crt', 'CRT', 'left', '8%'));
        $this->datagrid->addColumn(new TDataGridColumn('fatura_cliente', 'Fat. Cliente', 'left', '12%'));

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
        $colEmissao->setTransformer(function ($value) {
            return $value ? TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '';
        });
        $this->datagrid->addColumn($colEmissao);

        $colVenc = new TDataGridColumn('vencimento', 'Vencimento', 'center', '8%');
        $colVenc->setTransformer(function ($value) {
            return $value ? TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '';
        });
        $this->datagrid->addColumn($colVenc);

        $colValor = new TDataGridColumn('valor_fatura', 'Valor', 'right', '9%');
        $colValor->setTransformer(function ($value) {
            if ($value === null || $value === '') return '';
            return 'R$ ' . number_format((float) $value, 2, ',', '.');
        });
        $this->datagrid->addColumn($colValor);

        $colStatus = new TDataGridColumn('pagamento', 'Status', 'center', '10%');
        $colStatus->setTransformer(function ($pagamento, $obj) {
            $hoje = date('Y-m-d');
            if (!empty($pagamento) && $pagamento !== '0000-00-00') {
                $dt = TDate::convertToMask($pagamento, 'yyyy-mm-dd', 'dd/mm/yyyy');
                return "<span class='badge' style='background:#198754;color:#fff;font-size:.75rem;padding:3px 7px'><i class='fa fa-check'></i> Recebida {$dt}</span>";
            }
            $venc = $obj->vencimento ?? '';
            if (!empty($venc) && $venc < $hoje) {
                $dias = (int) ceil((strtotime($hoje) - strtotime($venc)) / 86400);
                return "<span class='badge' style='background:#dc3545;color:#fff;font-size:.75rem;padding:3px 7px'><i class='fa fa-exclamation-circle'></i> Atrasada {$dias}d</span>";
            }
            if (!empty($venc) && $venc >= $hoje) {
                $dias = (int) ceil((strtotime($venc) - strtotime($hoje)) / 86400);
                $cor  = $dias <= 7 ? '#fd7e14' : '#0d6efd';
                return "<span class='badge' style='background:{$cor};color:#fff;font-size:.75rem;padding:3px 7px'><i class='fa fa-clock-o'></i> Vence em {$dias}d</span>";
            }
            return "<span class='badge' style='background:#6c757d;color:#fff;font-size:.75rem;padding:3px 7px'>Pendente</span>";
        });
        $this->datagrid->addColumn($colStatus);

        // Acoes
        $actionEdit = new TDataGridAction(['FaturaForm', 'onEdit'], ['key' => '{id}']);
        $this->datagrid->addAction($actionEdit, 'Editar', 'fa:edit blue');

        $actionDelete = new TDataGridAction([__CLASS__, 'onDelete'], ['key' => '{id}']);
        $this->datagrid->addAction($actionDelete, 'Excluir', 'fa:trash red');

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
            $criteria->setProperty('order', self::$primaryKey);
            $criteria->setProperty('direction', 'desc');

            foreach (['_filter_id', '_filter_numero_fatura', '_filter_numero_crt', '_filter_fatura_cliente', '_filter_pessoa_id', '_filter_emissao_de', '_filter_emissao_ate'] as $sf) {
                $filter = TSession::getValue(__CLASS__ . $sf);
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
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
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

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }
        parent::show();
    }
}
