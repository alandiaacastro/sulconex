<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Dialog\TToast;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

class PropostaList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $totalsContainer;
    private $kpiContainer;
    private $loaded = false;

    private $database    = 'sample';
    private $activeRecord = 'Proposta';

    public function __construct($param = null)
    {
        parent::__construct();

        // ---- Filtros ----
        $this->form = new BootstrapFormBuilder('form_search_proposta');
        $this->form->setFormTitle('PROPOSTAS DE FRETE INTERNACIONAL');

        $cotacao_id = new TEntry('Cotacao_ID');
        $situacao   = new TCombo('Situacao');
        $cliente_id = new TDBUniqueSearch('cliente_id', 'sample', 'Clientes', 'id', 'nome');
        $emissao_de  = new TDate('emissao_de');
        $emissao_ate = new TDate('emissao_ate');

        $cotacao_id->setSize('100%');
        $situacao->setSize('100%');
        $cliente_id->setSize('100%');
        $cliente_id->setMinLength(2);
        $cliente_id->setMask('{nome}');
        $emissao_de->setSize('100%');
        $emissao_de->setMask('dd/mm/yyyy');
        $emissao_de->setDatabaseMask('yyyy-mm-dd');
        $emissao_ate->setSize('100%');
        $emissao_ate->setMask('dd/mm/yyyy');
        $emissao_ate->setDatabaseMask('yyyy-mm-dd');

        $situacao->addItems([
            ''           => 'Todos',
            'Em Analise' => 'Em Análise',
            'Aprovada'   => 'Aprovada',
            'Rejeitada'  => 'Rejeitada',
        ]);

        $this->form->addFields(
            [new TLabel('Nº Cotação')], [$cotacao_id],
            [new TLabel('Situação')], [$situacao]
        );
        $this->form->addFields(
            [new TLabel('Cliente')], [$cliente_id],
            [new TLabel('Emissão de')], [$emissao_de],
            [new TLabel('até')], [$emissao_ate]
        );

        $this->form->setData(TSession::getValue('Proposta_filter_data'));

        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser gray');
        $this->form->addActionLink('Nova Proposta', new TAction(['PropostaForm', 'onEdit']), 'fa:plus green');
        $this->form->addActionLink('Kanban CRM', new TAction(['OpportunityKanban', 'onReload']), 'fa:columns orange');

        // ---- KPI container ----
        $this->kpiContainer = new TElement('div');

        // ---- DataGrid ----
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width:100%';

        $col_id       = new TDataGridColumn('id', 'ID', 'right', '55');
        $col_cotacao  = new TDataGridColumn('Cotacao_ID', 'Cotação', 'left', '110');
        $col_cliente  = new TDataGridColumn('cliente_id', 'Cliente', 'left', '230');
        $col_emissao  = new TDataGridColumn('Data_Cotacao', 'Emissão', 'center', '100');
        $col_validade = new TDataGridColumn('Data_Validade_Cotacao', 'Validade', 'center', '100');
        $col_situacao = new TDataGridColumn('Situacao', 'Situação', 'center', '120');
        $col_fat      = new TDataGridColumn('Faturamento_Valor_1', 'Faturamento', 'right', '120');
        $col_res      = new TDataGridColumn('resultado_final', 'Resultado', 'right', '120');
        $col_margem   = new TDataGridColumn('margem_percentual', 'Margem%', 'right', '80');

        $col_cliente->setTransformer(function ($value) {
            static $cache = [];
            if (empty($value)) return '';
            if (isset($cache[$value])) return $cache[$value];
            try {
                TTransaction::open('sample');
                $c = new Clientes($value);
                $nome = (string)($c->nome ?? '');
                TTransaction::close();
                $cache[$value] = $nome;
                return $nome;
            } catch (Exception $e) {
                try { TTransaction::rollback(); } catch (Exception $ee) {}
                return '';
            }
        });

        $fmtDate = function ($value) {
            if ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $fmt = TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy');
                // Alerta validade vencida
                return $fmt;
            }
            return $value ?? '';
        };

        $fmtDateValidade = function ($value) {
            if ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $fmt = TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy');
                $today = date('Y-m-d');
                if ($value < $today) {
                    return "<span style='color:#991b1b;font-weight:600' title='Vencida'>⚠️ {$fmt}</span>";
                }
                return "<span style='color:#065f46'>{$fmt}</span>";
            }
            return $value ?? '';
        };

        $col_emissao->setTransformer($fmtDate);
        $col_validade->setTransformer($fmtDateValidade);

        $col_situacao->setTransformer(function ($value) {
            if (empty($value)) return '';
            $colors = [
                'Em Analise' => ['bg' => '#fef3c7', 'bd' => '#fde68a', 'fg' => '#92400e'],
                'Aprovada'   => ['bg' => '#d1fae5', 'bd' => '#6ee7b7', 'fg' => '#065f46'],
                'Rejeitada'  => ['bg' => '#fee2e2', 'bd' => '#fca5a5', 'fg' => '#991b1b'],
            ];
            $c = $colors[$value] ?? ['bg' => '#f3f4f6', 'bd' => '#d1d5db', 'fg' => '#374151'];
            $label = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
            return "<span style='display:inline-block;padding:3px 10px;border-radius:999px;border:1px solid {$c['bd']};background:{$c['bg']};color:{$c['fg']};font-weight:600;font-size:11px'>{$label}</span>";
        });

        $fmtMoney = function ($value) {
            if ($value === null || $value === '') return '—';
            return 'R$ ' . number_format((float)$value, 2, ',', '.');
        };

        $col_fat->setTransformer($fmtMoney);
        $col_res->setTransformer(function ($value) {
            if ($value === null || $value === '') return '—';
            $v = (float)$value;
            $fmt = 'R$ ' . number_format($v, 2, ',', '.');
            $color = $v >= 0 ? 'color:#065f46;font-weight:700' : 'color:#991b1b;font-weight:700';
            return "<span style='{$color}'>{$fmt}</span>";
        });

        $col_margem->setTransformer(function ($value) {
            if ($value === null || $value === '') return '—';
            $v = (float)$value;
            $fmt = number_format($v, 1) . '%';
            $color = $v >= 10 ? '#065f46' : ($v >= 0 ? '#92400e' : '#991b1b');
            return "<span style='color:{$color};font-weight:600'>{$fmt}</span>";
        });

        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_cotacao);
        $this->datagrid->addColumn($col_cliente);
        $this->datagrid->addColumn($col_emissao);
        $this->datagrid->addColumn($col_validade);
        $this->datagrid->addColumn($col_situacao);
        $this->datagrid->addColumn($col_fat);
        $this->datagrid->addColumn($col_res);
        $this->datagrid->addColumn($col_margem);

        // Ações
        $act_edit = new TDataGridAction(['PropostaForm', 'onEdit'], ['key' => '{id}']);
        $this->datagrid->addAction($act_edit, 'Editar', 'fa:edit blue');

        $act_aprovar = new TDataGridAction([$this, 'onAprovar'], ['key' => '{id}']);
        $this->datagrid->addAction($act_aprovar, 'Aprovar', 'fa:check-circle green');

        $act_rejeitar = new TDataGridAction([$this, 'onRejeitar'], ['key' => '{id}']);
        $this->datagrid->addAction($act_rejeitar, 'Rejeitar', 'fa:times-circle red');

        $act_print_proposta = new TDataGridAction(['PropostaRelatorio', 'onImprimir'], ['key' => '{id}']);
        $this->datagrid->addAction($act_print_proposta, 'Imprimir proposta', 'fa:file-pdf red');

        $act_print_cotacao = new TDataGridAction(['CotacaoPDFView', 'onGenerate'], ['key' => '{id}']);
        $this->datagrid->addAction($act_print_cotacao, 'Imprimir cotação', 'fa:file-pdf blue');

        $act_del = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $this->datagrid->addAction($act_del, 'Excluir', 'fa:trash red');

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth('100%');

        $this->totalsContainer = new TElement('div');

        $footerWrap = new TElement('div');
        $footerWrap->add($this->totalsContainer);
        $footerWrap->add($this->pageNavigation);

        $panel = new TPanelGroup;
        $panel->add($this->kpiContainer);
        $panel->add($this->datagrid);
        $panel->addFooter($footerWrap);

        $box = new TVBox;
        $box->style = 'width:100%';
        $box->add(new TXMLBreadCrumb('menu.xml', get_class($this)));
        $box->add($this->form);
        $box->add($panel);

        parent::add($box);
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();
        TSession::setValue('Proposta_filter_data', $data);
        $this->onReload($param);
    }

    public static function onClear($param = null)
    {
        TSession::setValue('Proposta_filter_data', null);
        TApplication::loadPage(__CLASS__, 'onReload');
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open($this->database);

            $limit  = 20;
            $param  = $param ?? [];
            $page   = isset($param['page']) ? (int)$param['page'] : 1;
            $offset = ($page - 1) * $limit;

            $filterData = TSession::getValue('Proposta_filter_data');
            if ($filterData) {
                $this->form->setData($filterData);
            }

            $baseCriteria = new TCriteria;
            if (!empty($filterData->Cotacao_ID)) {
                $baseCriteria->add(new TFilter('Cotacao_ID', 'like', "%{$filterData->Cotacao_ID}%"));
            }
            if (!empty($filterData->Situacao)) {
                $baseCriteria->add(new TFilter('Situacao', '=', $filterData->Situacao));
            }
            if (!empty($filterData->cliente_id)) {
                $baseCriteria->add(new TFilter('cliente_id', '=', $filterData->cliente_id));
            }
            if (!empty($filterData->emissao_de)) {
                $de = TDate::convertToMask($filterData->emissao_de, 'dd/mm/yyyy', 'yyyy-mm-dd');
                $baseCriteria->add(new TFilter('Data_Cotacao', '>=', $de));
            }
            if (!empty($filterData->emissao_ate)) {
                $ate = TDate::convertToMask($filterData->emissao_ate, 'dd/mm/yyyy', 'yyyy-mm-dd');
                $baseCriteria->add(new TFilter('Data_Cotacao', '<=', $ate));
            }

            // Totais (sem paginação)
            $repo = new TRepository($this->activeRecord);
            $allItems = $repo->load($baseCriteria) ?: [];
            $totalFat  = 0;
            $totalRes  = 0;
            $countAprov = 0;
            $countAnal  = 0;
            $countRej   = 0;
            $vencidas   = 0;
            $today = date('Y-m-d');

            foreach ($allItems as $item) {
                $totalFat += (float)($item->Faturamento_Valor_1 ?? 0);
                $totalRes += (float)($item->resultado_final     ?? 0);
                if ($item->Situacao === 'Aprovada')   $countAprov++;
                if ($item->Situacao === 'Em Analise') $countAnal++;
                if ($item->Situacao === 'Rejeitada')  $countRej++;
                if (!empty($item->Data_Validade_Cotacao) && $item->Data_Validade_Cotacao < $today && $item->Situacao === 'Em Analise') {
                    $vencidas++;
                }
            }

            // Paginação
            $listCriteria = clone $baseCriteria;
            $listCriteria->setProperty('limit',  $limit);
            $listCriteria->setProperty('offset', $offset);
            $listCriteria->setProperty('order',  'id desc');

            $this->datagrid->clear();
            $items = $repo->load($listCriteria);
            if ($items) {
                foreach ($items as $item) {
                    $this->datagrid->addItem($item);
                }
            }

            $total = count($allItems);
            $this->pageNavigation->setCount($total);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setPage($page);

            TTransaction::close();

            // KPIs
            $this->renderKpis($total, $countAprov, $countAnal, $countRej, $vencidas, $totalFat, $totalRes);

            // Totalizadores no rodapé
            $fatFmt = 'R$ ' . number_format($totalFat, 2, ',', '.');
            $resFmt = 'R$ ' . number_format($totalRes, 2, ',', '.');
            $resColor = $totalRes >= 0 ? '#065f46' : '#991b1b';
            $margFmt = $totalFat > 0 ? number_format(($totalRes / $totalFat) * 100, 1) . '%' : '—';

            $this->totalsContainer->clearChildren();
            $this->totalsContainer->add(
                "<div style='display:flex;gap:20px;justify-content:flex-end;padding:8px 4px;font-size:13px;flex-wrap:wrap;'>
                    <span>📊 <strong>{$total}</strong> propostas encontradas</span>
                    <span>💰 Faturamento total: <strong>{$fatFmt}</strong></span>
                    <span style='color:{$resColor}'>📈 Resultado total: <strong>{$resFmt}</strong></span>
                    <span>📉 Margem média: <strong>{$margFmt}</strong></span>
                </div>"
            );

            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private function renderKpis($total, $aprovadas, $analise, $rejeitadas, $vencidas, $totalFat, $totalRes)
    {
        $fatFmt = $totalFat > 0 ? 'R$ ' . number_format($totalFat, 0, ',', '.') : '—';
        $resFmt = $totalRes != 0 ? 'R$ ' . number_format($totalRes, 0, ',', '.') : '—';
        $resColor = $totalRes >= 0 ? '#065f46' : '#991b1b';
        $txAprov = $total > 0 ? number_format(($aprovadas / $total) * 100, 0) . '%' : '—';

        $vencidasAlert = $vencidas > 0
            ? "<div style='background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:7px 14px;margin-bottom:10px;font-size:12px;color:#92400e;'>⚠️ <strong>{$vencidas} proposta(s)</strong> com validade vencida aguardando aprovação.</div>"
            : '';

        $html = <<<HTML
{$vencidasAlert}
<div style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-bottom:12px;">
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;box-shadow:0 2px 6px rgba(15,23,42,.05)">
    <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em">Total</div>
    <div style="font-size:22px;font-weight:700;color:#0f172a;margin-top:2px">{$total}</div>
  </div>
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;box-shadow:0 2px 6px rgba(15,23,42,.05)">
    <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em">Em Análise</div>
    <div style="font-size:22px;font-weight:700;color:#b45309;margin-top:2px">{$analise}</div>
  </div>
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;box-shadow:0 2px 6px rgba(15,23,42,.05)">
    <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em">Aprovadas</div>
    <div style="font-size:22px;font-weight:700;color:#065f46;margin-top:2px">{$aprovadas}</div>
  </div>
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;box-shadow:0 2px 6px rgba(15,23,42,.05)">
    <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em">Tx. Aprovação</div>
    <div style="font-size:22px;font-weight:700;color:#1d4ed8;margin-top:2px">{$txAprov}</div>
  </div>
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;box-shadow:0 2px 6px rgba(15,23,42,.05)">
    <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em">Faturamento</div>
    <div style="font-size:16px;font-weight:700;color:#1d4ed8;margin-top:2px">{$fatFmt}</div>
  </div>
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;box-shadow:0 2px 6px rgba(15,23,42,.05)">
    <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em">Resultado</div>
    <div style="font-size:16px;font-weight:700;color:{$resColor};margin-top:2px">{$resFmt}</div>
  </div>
</div>
HTML;
        $this->kpiContainer->clearChildren();
        $this->kpiContainer->add($html);
    }

    public static function onAprovar($param)
    {
        $action = new TAction([__CLASS__, 'doAprovar']);
        $action->setParameters($param);
        new TQuestion('Aprovar esta proposta?', $action);
    }

    public static function doAprovar($param)
    {
        try {
            TTransaction::open('sample');
            $p = new Proposta((int)$param['key']);
            $p->Situacao = 'Aprovada';
            $p->store();
            TTransaction::close();
            TToast::show('success', 'Proposta aprovada!', 'bottom right');
            TApplication::loadPage(__CLASS__, 'onReload');
        } catch (Exception $e) {
            TTransaction::rollback();
            TToast::show('error', $e->getMessage(), 'bottom right');
        }
    }

    public static function onRejeitar($param)
    {
        $action = new TAction([__CLASS__, 'doRejeitar']);
        $action->setParameters($param);
        new TQuestion('Rejeitar esta proposta?', $action);
    }

    public static function doRejeitar($param)
    {
        try {
            TTransaction::open('sample');
            $p = new Proposta((int)$param['key']);
            $p->Situacao = 'Rejeitada';
            $p->store();
            TTransaction::close();
            TToast::show('info', 'Proposta rejeitada.', 'bottom right');
            TApplication::loadPage(__CLASS__, 'onReload');
        } catch (Exception $e) {
            TTransaction::rollback();
            TToast::show('error', $e->getMessage(), 'bottom right');
        }
    }

    public static function onDelete($param)
    {
        $action = new TAction([__CLASS__, 'doDelete']);
        $action->setParameters($param);
        new TQuestion('Confirma exclusão desta proposta?', $action);
    }

    public static function doDelete($param)
    {
        try {
            TTransaction::open('sample');
            $p = new Proposta((int)$param['key']);
            $p->delete();
            TTransaction::close();
            TToast::show('success', 'Proposta excluída.', 'bottom right');
            TApplication::loadPage(__CLASS__, 'onReload');
        } catch (Exception $e) {
            TTransaction::rollback();
            TToast::show('error', $e->getMessage(), 'bottom right');
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload($_GET ?? []);
        }
        parent::show();
    }
}
