<?php

class ChequeList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_search_cheque');
        $this->form->setFormTitle('Cheques');

        $id            = new TEntry('id');
        $numero_cheque = new TEntry('numero_cheque');
        $recebedor     = new TEntry('recebedor');
        $status        = new TCombo('status');
        $venc_de       = new TDate('venc_de');
        $venc_ate      = new TDate('venc_ate');
        $selected_ids  = new THidden('selected_ids');

        foreach ([$id, $numero_cheque, $recebedor, $status, $venc_de, $venc_ate] as $field) {
            $field->setSize('100%');
        }

        $venc_de->setMask('dd/mm/yyyy');
        $venc_de->setDatabaseMask('yyyy-mm-dd');
        $venc_ate->setMask('dd/mm/yyyy');
        $venc_ate->setDatabaseMask('yyyy-mm-dd');

        $status->addItems([
            ''           => '(Todos)',
            'PENDENTE'   => 'Pendente',
            'COMPENSADO' => 'Compensado',
            'BAIXADO'    => 'Baixado no Caixa',
            'DEVOLVIDO'  => 'Devolvido',
        ]);

        $this->form->addFields([new TLabel('ID')], [$id], [new TLabel('Numero')], [$numero_cheque]);
        $this->form->addFields([new TLabel('Recebedor')], [$recebedor], [new TLabel('Status')], [$status]);
        $this->form->addFields([new TLabel('Venc. de')], [$venc_de], [new TLabel('Venc. ate')], [$venc_ate]);
        $this->form->addFields([$selected_ids]);

        $this->form->addAction('Filtrar',        new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addAction('Novo',           new TAction(['ChequeForm', 'onEdit']), 'fa:plus green');
        $this->form->addAction('Relatorio Pendentes', new TAction([$this, 'onRelatorioPendentes']), 'fa:file-text-o purple');
        $this->form->addAction('Recarregar',     new TAction([$this, 'onReload']), 'fa:refresh');
        $this->form->addAction('Limpar Filtros', new TAction([$this, 'onClearFilters']), 'fa:times gray');

        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';
        $this->datagrid->disableDefaultClick();

        $colSel = new TDataGridColumn('id', '<input type="checkbox" id="chq-select-all" title="Selecionar todos desta pagina">', 'center', '3%');
        $colSel->setTransformer(function ($value) {
            $id = (int) $value;
            return "<input type='checkbox' class='chq-row-select' value='{$id}'>";
        });
        $this->datagrid->addColumn($colSel);

        $colId = new TDataGridColumn('id', 'ID', 'center', '6%');
        $colId->setAction(new TAction([$this, 'onReload']), ['order' => 'id']);
        $this->datagrid->addColumn($colId);

        $colNumero = new TDataGridColumn('numero_cheque', 'Numero Cheque', 'left', '12%');
        $colNumero->setAction(new TAction([$this, 'onReload']), ['order' => 'numero_cheque']);
        $this->datagrid->addColumn($colNumero);

        $colRecebedor = new TDataGridColumn('recebedor', 'Recebedor', 'left', '24%');
        $colRecebedor->setAction(new TAction([$this, 'onReload']), ['order' => 'recebedor']);
        $this->datagrid->addColumn($colRecebedor);

        $colValor = new TDataGridColumn('valor', 'Valor', 'right', '10%');
        $colValor->setTransformer(function ($value) {
            return 'R$ ' . number_format((float) $value, 2, ',', '.');
        });
        $this->datagrid->addColumn($colValor);

        $colEmissao = new TDataGridColumn('data_emissao', 'Emissao', 'center', '10%');
        $colEmissao->setTransformer(function ($value) {
            return $value ? TDate::convertToMask((string) $value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '-';
        });
        $this->datagrid->addColumn($colEmissao);

        $colVenc = new TDataGridColumn('data_vencimento', 'Vencimento', 'center', '10%');
        $colVenc->setTransformer(function ($value) {
            return $value ? TDate::convertToMask((string) $value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '-';
        });
        $this->datagrid->addColumn($colVenc);

        $colComp = new TDataGridColumn('data_compensacao', 'Compensacao', 'center', '10%');
        $colComp->setTransformer(function ($value) {
            return $value ? TDate::convertToMask((string) $value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '-';
        });
        $this->datagrid->addColumn($colComp);

        $colStatus = new TDataGridColumn('status', 'Status', 'center', '18%');
        $colStatus->setTransformer(function ($value, $obj) {
            $labels = [
                'PENDENTE'   => ['Pendente', '#f0ad4e', '#111'],
                'COMPENSADO' => ['Compensado', '#198754', '#fff'],
                'BAIXADO'    => ['Baixado no Caixa', '#6f42c1', '#fff'],
                'DEVOLVIDO'  => ['Devolvido', '#dc3545', '#fff'],
            ];
            $meta = $labels[$value] ?? [$value, '#6c757d', '#fff'];

            $emCaixa = self::hasLancamentoCaixa((int) $obj->id);
            $caixaBadge = $emCaixa
                ? "<br><span class='badge' style='margin-top:3px;background:#0dcaf0;color:#000'><i class='fa fa-university'></i> No Caixa</span>"
                : '';

            return "<span class='badge' style='background:{$meta[1]};color:{$meta[2]}'>{$meta[0]}</span>{$caixaBadge}";
        });
        $this->datagrid->addColumn($colStatus);

        $actionEdit = new TDataGridAction(['ChequeForm', 'onEdit'], ['key' => '{id}']);
        $this->datagrid->addAction($actionEdit, 'Editar', 'fa:edit blue');

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->enableCounters();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth('100%');

        $panel = new TPanelGroup('Listagem de Cheques');
        $batchWidget = new TElement('div');
        $batchWidget->add("
            <div class='btn-group' style='margin-right:8px'>
                <button type='button' class='btn btn-default btn-sm' onclick=\"return ChequeListBatch.call('onBatchConfirmCompensar')\"><i class='fa fa-check-circle text-success'></i> Compensar Selecionados</button>
                <button type='button' class='btn btn-default btn-sm' onclick=\"return ChequeListBatch.call('onBatchConfirmBaixarCaixa')\"><i class='fa fa-university text-purple'></i> Baixar no Caixa</button>
                <button type='button' class='btn btn-default btn-sm' onclick=\"return ChequeListBatch.call('onBatchConfirmEstornarCaixa')\"><i class='fa fa-times-circle text-warning'></i> Estornar Caixa</button>
                <button type='button' class='btn btn-default btn-sm' onclick=\"return ChequeListBatch.call('onBatchConfirmDevolver')\"><i class='fa fa-undo text-danger'></i> Devolver</button>
                <button type='button' class='btn btn-default btn-sm' onclick=\"return ChequeListBatch.call('onBatchConfirmExcluir')\"><i class='fa fa-trash text-danger'></i> Excluir Selecionados</button>
            </div>
        ");
        $panel->addHeaderWidget($batchWidget);
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);
        $container->add($this->buildKpiPanel());
        $container->add($panel);
        parent::add($container);

        // Colapso do filtro (expandir/recolher)
        TScript::create("
            (function() {
                var \$card   = \$('#form_search_cheque').closest('.card');
                var \$header = \$card.find('.card-header').first();
                var \$body   = \$card.find('.card-body').first();
                if (!\$header.length || !\$body.length) return;

                if (!document.getElementById('cheque-filter-toggle')) {
                    \$header.css('cursor','pointer');
                    \$header.append('<span style=\"float:right;margin-left:8px\"><i class=\"fa fa-chevron-up\" id=\"cheque-filter-toggle\"></i></span>');
                }

                \$header.off('click.chequefilter').on('click.chequefilter', function() {
                    \$body.slideToggle(180);
                    \$('#cheque-filter-toggle').toggleClass('fa-chevron-up fa-chevron-down');
                });
            })();
        ");

        TScript::create("
            (function() {
                window.ChequeListBatch = window.ChequeListBatch || {};

                window.ChequeListBatch.getVisibleRows = function() {
                    return \$('.chq-row-select').filter(':visible');
                };

                window.ChequeListBatch.syncSelected = function() {
                    var ids = [];
                    window.ChequeListBatch.getVisibleRows().each(function() {
                        if (this.checked) {
                            ids.push(this.value);
                        }
                    });
                    \$('#form_search_cheque input[name=\"selected_ids\"]').val(ids.join(','));
                    return ids;
                };

                window.ChequeListBatch.call = function(method) {
                    var ids = window.ChequeListBatch.syncSelected();
                    var url = 'engine.php?class=ChequeList&method=' + method + '&selected_ids=' + encodeURIComponent(ids.join(','));
                    if (typeof __adianti_load_page === 'function') {
                        __adianti_load_page(url);
                    } else if (typeof __adianti_load_page__ === 'function') {
                        __adianti_load_page__(url);
                    } else {
                        window.location.href = url;
                    }
                    return false;
                };

                \$(document).off('change.chqselectall', '#chq-select-all').on('change.chqselectall', '#chq-select-all', function() {
                    window.ChequeListBatch.getVisibleRows().prop('checked', this.checked);
                    window.ChequeListBatch.syncSelected();
                });

                \$(document).off('change.chqselectrow', '.chq-row-select').on('change.chqselectrow', '.chq-row-select', function() {
                    var rows = window.ChequeListBatch.getVisibleRows();
                    var allChecked = rows.length > 0 && rows.filter(':checked').length === rows.length;
                    \$('#chq-select-all').prop('checked', allChecked);
                    window.ChequeListBatch.syncSelected();
                });

                \$(document).off('draw.dt.chq').on('draw.dt.chq', function() {
                    \$('#chq-select-all').prop('checked', false);
                    window.ChequeListBatch.syncSelected();
                });

                window.ChequeListBatch.syncSelected();
            })();
        ");
    }

    private function buildKpiPanel()
    {
        try {
            TTransaction::open('sample');
            $conn = TTransaction::get();

            $hoje = date('Y-m-d');
            $prox7 = date('Y-m-d', strtotime('+7 days'));

            $aVencer = $conn->query("
                SELECT COUNT(*) qtd, COALESCE(SUM(CAST(valor AS REAL)), 0) total
                FROM cheque
                WHERE status = 'PENDENTE'
                  AND data_vencimento >= '{$hoje}'
            ")->fetch(\PDO::FETCH_ASSOC);

            $pagos = $conn->query("
                SELECT COUNT(*) qtd, COALESCE(SUM(CAST(valor AS REAL)), 0) total
                FROM cheque
                WHERE status IN ('COMPENSADO', 'BAIXADO')
            ")->fetch(\PDO::FETCH_ASSOC);

            $prox7dias = $conn->query("
                SELECT COUNT(*) qtd, COALESCE(SUM(CAST(valor AS REAL)), 0) total
                FROM cheque
                WHERE status = 'PENDENTE'
                  AND data_vencimento BETWEEN '{$hoje}' AND '{$prox7}'
            ")->fetch(\PDO::FETCH_ASSOC);

            TTransaction::close();

            $qtdAVencer = (int) ($aVencer['qtd'] ?? 0);
            $totalAVencer = (float) ($aVencer['total'] ?? 0);
            $qtdPagos = (int) ($pagos['qtd'] ?? 0);
            $totalPagos = (float) ($pagos['total'] ?? 0);
            $qtdProx7 = (int) ($prox7dias['qtd'] ?? 0);
            $totalProx7 = (float) ($prox7dias['total'] ?? 0);

            $fmtMoney = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

            $html = "
            <style>
                .chq-kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;margin-bottom:16px}
                .chq-kpi{border-radius:10px;padding:14px 16px;color:#fff;box-shadow:0 3px 10px rgba(0,0,0,.12)}
                .chq-kpi .k-title{font-size:.75rem;opacity:.9;text-transform:uppercase;letter-spacing:.04em}
                .chq-kpi .k-value{font-size:1.45rem;font-weight:700;line-height:1.2}
                .chq-kpi .k-sub{font-size:.82rem;opacity:.92}
                .chq-kpi.avencer{background:linear-gradient(135deg,#f59f00,#f08c00)}
                .chq-kpi.total{background:linear-gradient(135deg,#228be6,#1864ab)}
                .chq-kpi.pagos{background:linear-gradient(135deg,#2f9e44,#2b8a3e)}
                .chq-kpi.prox7{background:linear-gradient(135deg,#7b2cbf,#5a189a)}
            </style>
            <div class='chq-kpi-grid'>
                <div class='chq-kpi avencer'>
                    <div class='k-title'>Cheques a Vencer</div>
                    <div class='k-value'>{$qtdAVencer}</div>
                    <div class='k-sub'>Status pendente com vencimento a partir de hoje</div>
                </div>
                <div class='chq-kpi total'>
                    <div class='k-title'>Total a Vencer</div>
                    <div class='k-value'>{$fmtMoney($totalAVencer)}</div>
                    <div class='k-sub'>Valor financeiro dos cheques pendentes</div>
                </div>
                <div class='chq-kpi pagos'>
                    <div class='k-title'>Pagos</div>
                    <div class='k-value'>{$qtdPagos}</div>
                    <div class='k-sub'>Total pago: {$fmtMoney($totalPagos)}</div>
                </div>
                <div class='chq-kpi prox7'>
                    <div class='k-title'>Proximos 7 Dias</div>
                    <div class='k-value'>{$qtdProx7}</div>
                    <div class='k-sub'>Total: {$fmtMoney($totalProx7)}</div>
                </div>
            </div>";

            $wrapper = new TElement('div');
            $wrapper->add($html);
            return $wrapper;
        } catch (Exception $e) {
            TTransaction::rollback();
            return new TElement('div');
        }
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();
        $statusValue = trim((string) ($data->status ?? ''));

        TSession::setValue(__CLASS__ . '_filter_id', !empty($data->id) ? new TFilter('id', '=', (int) $data->id) : null);
        TSession::setValue(__CLASS__ . '_filter_numero', !empty($data->numero_cheque) ? new TFilter('numero_cheque', 'like', "%{$data->numero_cheque}%") : null);
        TSession::setValue(__CLASS__ . '_filter_recebedor', !empty($data->recebedor) ? new TFilter('recebedor', 'like', "%{$data->recebedor}%") : null);
        TSession::setValue(__CLASS__ . '_filter_status_value', $statusValue !== '' ? $statusValue : null);
        TSession::setValue(__CLASS__ . '_filter_venc_de', !empty($data->venc_de) ? new TFilter('data_vencimento', '>=', $data->venc_de) : null);
        TSession::setValue(__CLASS__ . '_filter_venc_ate', !empty($data->venc_ate) ? new TFilter('data_vencimento', '<=', $data->venc_ate) : null);

        TSession::setValue(__CLASS__ . '_filter_data', $data);
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public static function onFilterStatus($param = null)
    {
        $status = $param['status'] ?? '';
        TSession::setValue(__CLASS__ . '_filter_status_value', !empty($status) ? (string) $status : null);

        $data = TSession::getValue(__CLASS__ . '_filter_data') ?: new stdClass;
        $data->status = $status ?: '';
        TSession::setValue(__CLASS__ . '_filter_data', $data);

        AdiantiCoreApplication::loadPage(__CLASS__, 'onReload', ['offset' => 0, 'first_page' => 1]);
    }

    public static function onRelatorioPendentes($param = null)
    {
        ChequeRelatorio::onGeneratePendentes((array) $param);
    }

    private static function parseSelectedIds($raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[,\s;]+/', $raw);
        $ids = [];
        foreach ($parts as $part) {
            $id = (int) $part;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private static function summarizeBatch(string $label, int $selected, int $success, array $reasonMap): string
    {
        $failed = $selected - $success;
        $msg = "<b>{$label}</b><br>";
        $msg .= "Selecionados: {$selected} | Sucesso: {$success} | Ignorados/Falhas: {$failed}";

        if ($failed > 0 && !empty($reasonMap)) {
            arsort($reasonMap);
            $topReason = array_key_first($reasonMap);
            $topCount = $reasonMap[$topReason];
            $msg .= "<br>Motivo principal: {$topReason} ({$topCount})";
        }

        return $msg;
    }

    private static function executeBatchAction(array $param, string $label, callable $callback): void
    {
        $ids = self::parseSelectedIds($param['selected_ids'] ?? '');
        if (empty($ids)) {
            new TMessage('warning', 'Selecione ao menos um registro na pagina atual.');
            return;
        }

        $success = 0;
        $reasons = [];

        foreach ($ids as $id) {
            try {
                TTransaction::open('sample');
                $callback($id);
                TTransaction::close();
                $success++;
            } catch (Exception $e) {
                try {
                    TTransaction::rollback();
                } catch (Exception $ignored) {
                }
                $reason = trim((string) $e->getMessage()) ?: 'Falha nao identificada';
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            }
        }

        $summary = self::summarizeBatch($label, count($ids), $success, $reasons);
        $posAction = new TAction([__CLASS__, 'onReload']);
        new TMessage('info', $summary, $posAction);
    }

    public static function onBatchConfirmCompensar($param)
    {
        $action = new TAction([__CLASS__, 'onBatchCompensar']);
        $action->setParameter('selected_ids', $param['selected_ids'] ?? '');
        new TQuestion('Confirma compensar os cheques selecionados?', $action);
    }

    public static function onBatchCompensar($param)
    {
        self::executeBatchAction($param, 'Compensacao em lote', function ($id) {
            self::compensarById((int) $id);
        });
    }

    public static function onBatchConfirmBaixarCaixa($param)
    {
        $action = new TAction([__CLASS__, 'onBatchBaixarCaixa']);
        $action->setParameter('selected_ids', $param['selected_ids'] ?? '');
        new TQuestion('Confirma baixar no Caixa os cheques selecionados?', $action);
    }

    public static function onBatchBaixarCaixa($param)
    {
        self::executeBatchAction($param, 'Baixa no Caixa em lote', function ($id) {
            self::baixarCaixaById((int) $id);
        });
    }

    public static function onBatchConfirmEstornarCaixa($param)
    {
        $action = new TAction([__CLASS__, 'onBatchEstornarCaixa']);
        $action->setParameter('selected_ids', $param['selected_ids'] ?? '');
        new TQuestion('Confirma estornar no Caixa os cheques selecionados?', $action);
    }

    public static function onBatchEstornarCaixa($param)
    {
        self::executeBatchAction($param, 'Estorno de Caixa em lote', function ($id) {
            self::estornarCaixaById((int) $id);
        });
    }

    public static function onBatchConfirmDevolver($param)
    {
        $action = new TAction([__CLASS__, 'onBatchDevolver']);
        $action->setParameter('selected_ids', $param['selected_ids'] ?? '');
        new TQuestion('Confirma devolver os cheques selecionados?', $action);
    }

    public static function onBatchDevolver($param)
    {
        self::executeBatchAction($param, 'Devolucao em lote', function ($id) {
            self::devolverById((int) $id);
        });
    }

    public static function onBatchConfirmExcluir($param)
    {
        $action = new TAction([__CLASS__, 'onBatchExcluir']);
        $action->setParameter('selected_ids', $param['selected_ids'] ?? '');
        new TQuestion('Confirma excluir os cheques selecionados?', $action);
    }

    public static function onBatchExcluir($param)
    {
        self::executeBatchAction($param, 'Exclusao em lote', function ($id) {
            self::deleteById((int) $id);
        });
    }

    public function onClearFilters($param = null)
    {
        foreach (['_filter_id', '_filter_numero', '_filter_recebedor', '_filter_venc_de', '_filter_venc_ate'] as $suffix) {
            TSession::setValue(__CLASS__ . $suffix, null);
        }
        TSession::setValue(__CLASS__ . '_filter_status_value', null);
        TSession::setValue(__CLASS__ . '_filter_data', null);
        $this->form->clear(true);
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            Cheque::createTableIfNotExists();

            $repository = new TRepository('Cheque');
            $criteria = new TCriteria;

            $limit = 20;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);

            if (empty($param['order'])) {
                $criteria->setProperty('order', 'id');
                $criteria->setProperty('direction', 'desc');
            }

            foreach (['_filter_id', '_filter_numero', '_filter_recebedor', '_filter_venc_de', '_filter_venc_ate'] as $suffix) {
                if ($filter = TSession::getValue(__CLASS__ . $suffix)) {
                    $criteria->add($filter);
                }
            }

            $statusValue = TSession::getValue(__CLASS__ . '_filter_status_value');
            if (!empty($statusValue)) {
                $criteria->add(new TFilter('status', '=', (string) $statusValue));
            }

            $objects = $repository->load($criteria, false);
            $this->datagrid->clear();

            if ($objects) {
                foreach ($objects as $object) {
                    $this->datagrid->addItem($object);
                }
            }

            $countCriteria = clone $criteria;
            $countCriteria->resetProperties();
            $count = $repository->count($countCriteria);

            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setLimit($limit);

            TTransaction::close();
            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onDelete($param = null)
    {
        $action = new TAction([__CLASS__, 'Delete']);
        $action->setParameters($param);
        new TQuestion('Deseja realmente excluir este cheque?', $action);
    }

    public static function Delete($param = null)
    {
        try {
            TTransaction::open('sample');
            self::deleteById((int) ($param['key'] ?? 0));
            TTransaction::close();

            $posAction = new TAction([__CLASS__, 'onReload']);
            new TMessage('info', 'Cheque excluido com sucesso.', $posAction);
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onConfirmCompensar($param)
    {
        $action = new TAction([__CLASS__, 'onCompensar']);
        $action->setParameters($param);
        new TQuestion('Confirma marcar este cheque como compensado?', $action);
    }

    public static function onCompensar($param)
    {
        try {
            TTransaction::open('sample');
            self::compensarById((int) ($param['key'] ?? 0));

            TTransaction::close();
            $posAction = new TAction([__CLASS__, 'onReload']);
            new TMessage('info', 'Cheque marcado como compensado.', $posAction);
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onConfirmBaixarCaixa($param)
    {
        $action = new TAction([__CLASS__, 'onBaixarCaixa']);
        $action->setParameters($param);
        new TQuestion('Confirma baixar este cheque no Caixa?', $action);
    }

    public static function onBaixarCaixa($param)
    {
        try {
            TTransaction::open('sample');
            self::baixarCaixaById((int) ($param['key'] ?? 0));

            TTransaction::close();
            $posAction = new TAction([__CLASS__, 'onReload']);
            new TMessage('info', 'Cheque baixado no Caixa com sucesso.', $posAction);
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onConfirmEstornarCaixa($param)
    {
        $action = new TAction([__CLASS__, 'onEstornarCaixa']);
        $action->setParameters($param);
        new TQuestion('Confirma estornar o lancamento deste cheque no Caixa?', $action);
    }

    public static function onEstornarCaixa($param)
    {
        try {
            TTransaction::open('sample');
            self::estornarCaixaById((int) ($param['key'] ?? 0));

            TTransaction::close();
            $posAction = new TAction([__CLASS__, 'onReload']);
            new TMessage('info', 'Estorno de Caixa realizado para o cheque.', $posAction);
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onConfirmDevolver($param)
    {
        $action = new TAction([__CLASS__, 'onDevolver']);
        $action->setParameters($param);
        new TQuestion('Confirma marcar este cheque como devolvido?', $action);
    }

    public static function onDevolver($param)
    {
        try {
            TTransaction::open('sample');
            self::devolverById((int) ($param['key'] ?? 0));

            TTransaction::close();
            $posAction = new TAction([__CLASS__, 'onReload']);
            new TMessage('info', 'Cheque marcado como devolvido.', $posAction);
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private static function deleteById(int $chequeId): void
    {
        Cheque::createTableIfNotExists();
        if ($chequeId <= 0) {
            throw new Exception('Cheque invalido para exclusao.');
        }

        $cheque = new Cheque($chequeId);
        if (!$cheque->id) {
            throw new Exception('Cheque nao encontrado para exclusao.');
        }

        if (self::hasLancamentoCaixa((int) $cheque->id)) {
            throw new Exception('Cheque baixado no Caixa. Estorne o Caixa antes de excluir.');
        }

        $cheque->delete();
    }

    private static function compensarById(int $chequeId): void
    {
        Cheque::createTableIfNotExists();
        if ($chequeId <= 0) {
            throw new Exception('Cheque invalido para compensacao.');
        }

        $cheque = new Cheque($chequeId);
        if (!$cheque->id) {
            throw new Exception('Cheque nao encontrado para compensacao.');
        }
        if ($cheque->status === 'DEVOLVIDO') {
            throw new Exception('Cheque devolvido nao pode ser compensado.');
        }

        $cheque->status = 'COMPENSADO';
        if (empty($cheque->data_compensacao)) {
            $cheque->data_compensacao = date('Y-m-d');
        }
        $cheque->store();
    }

    private static function baixarCaixaById(int $chequeId): void
    {
        Cheque::createTableIfNotExists();
        Caixa::createTableIfNotExists();
        if ($chequeId <= 0) {
            throw new Exception('Cheque invalido para baixa no Caixa.');
        }

        $cheque = new Cheque($chequeId);
        if (!$cheque->id) {
            throw new Exception('Cheque nao encontrado para baixa no Caixa.');
        }
        if ($cheque->status === 'DEVOLVIDO') {
            throw new Exception('Cheque devolvido nao pode ser baixado no Caixa.');
        }

        $caixaIds = self::getLancamentoCaixaChequeIds($chequeId);
        $caixaId = $caixaIds[0] ?? null;

        if (count($caixaIds) > 1) {
            $idsDuplicados = array_slice($caixaIds, 1);
            foreach ($idsDuplicados as $dupId) {
                $dup = new Caixa((int) $dupId);
                $dup->delete();
            }
        }

        $caixa = $caixaId ? new Caixa($caixaId) : new Caixa;
        if (!$caixaId) {
            $caixa->tipo            = 'SAIDA';
            $caixa->categoria       = 'CHEQUE';
            $caixa->referencia_id   = $chequeId;
            $caixa->referencia_tipo = 'cheque';
        }

        $caixa->data_lancamento = !empty($cheque->data_compensacao) ? $cheque->data_compensacao : date('Y-m-d');
        $caixa->descricao       = 'Cheque #' . $cheque->numero_cheque . ' - ' . $cheque->recebedor;
        $caixa->valor           = (float) $cheque->valor;
        $caixa->status          = 'CONCILIADO';
        $caixa->observacao      = 'Baixa automatica de cheque ID ' . $chequeId;
        $caixa->store();

        $cheque->status = 'BAIXADO';
        if (empty($cheque->data_compensacao)) {
            $cheque->data_compensacao = date('Y-m-d');
        }
        $cheque->store();
    }

    private static function estornarCaixaById(int $chequeId): void
    {
        Cheque::createTableIfNotExists();
        Caixa::createTableIfNotExists();
        if ($chequeId <= 0) {
            throw new Exception('Cheque invalido para estorno de Caixa.');
        }

        $cheque = new Cheque($chequeId);
        if (!$cheque->id) {
            throw new Exception('Cheque nao encontrado para estorno de Caixa.');
        }

        $caixaIds = self::getLancamentoCaixaChequeIds($chequeId);
        if ($caixaIds) {
            foreach ($caixaIds as $caixaId) {
                $caixa = new Caixa((int) $caixaId);
                $caixa->delete();
            }
        }

        $cheque->status = !empty($cheque->data_compensacao) ? 'COMPENSADO' : 'PENDENTE';
        $cheque->store();
    }

    private static function devolverById(int $chequeId): void
    {
        Cheque::createTableIfNotExists();
        if ($chequeId <= 0) {
            throw new Exception('Cheque invalido para devolucao.');
        }

        $cheque = new Cheque($chequeId);
        if (!$cheque->id) {
            throw new Exception('Cheque nao encontrado para devolucao.');
        }
        if (self::hasLancamentoCaixa((int) $cheque->id)) {
            throw new Exception('Cheque ja baixado no Caixa. Estorne o Caixa antes de devolver.');
        }

        $cheque->status = 'DEVOLVIDO';
        $cheque->store();
    }

    private static function hasLancamentoCaixa(int $chequeId): bool
    {
        $ids = self::getLancamentoCaixaChequeIds($chequeId);
        return !empty($ids);
    }

    private static function getLancamentoCaixaChequeIds(int $chequeId): array
    {
        $conn = TTransaction::get();
        $legacyObs = "Baixa automatica de cheque ID {$chequeId}";
        $stmt = $conn->prepare(
            "SELECT id
               FROM caixa
              WHERE (referencia_tipo='cheque' AND referencia_id = :id)
                 OR observacao = :obs
              ORDER BY id"
        );
        $stmt->execute([':id' => $chequeId, ':obs' => $legacyObs]);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return array_map('intval', $ids ?: []);
    }

    public function show()
    {
        if (!$this->loaded && (!isset($_GET['method']) || $_GET['method'] !== 'onReload')) {
            $this->onReload();
        }
        parent::show();
    }
}
