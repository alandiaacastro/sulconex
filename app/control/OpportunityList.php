<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\THBox;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Util\TXMLBreadCrumb;

class OpportunityList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $dashboardContainer;
    private $loaded = false;

    private $database    = 'sample';
    private $activeRecord = 'Opportunity';

    public function __construct()
    {
        parent::__construct();

        // ---- Filtros ----
        $this->form = new TForm('form_search_Opportunity');

        $company_name    = new TEntry('company_name');
        $responsible     = new TEntry('responsible_name');
        $status          = new TCombo('status');
        $prioridade      = new TCombo('prioridade');
        $origem_lead     = new TCombo('origem_lead');
        $proximo_de      = new TDate('proximo_de');
        $proximo_ate     = new TDate('proximo_ate');

        foreach ([$company_name, $responsible, $status, $prioridade, $origem_lead] as $w) {
            $w->setSize('100%');
        }
        $company_name->setProperty('placeholder', 'Nome da empresa...');
        $responsible->setProperty('placeholder', 'Nome do responsável...');

        $status->addItems([
            '' => 'Todos os status',
            'QUALIFICACAO' => 'Qualificação',
            'PROPOSTA'     => 'Proposta',
            'NEGOCIACAO'   => 'Negociação',
            'FECHAMENTO'   => 'Fechamento',
            'PERDIDO'      => 'Perdido',
        ]);

        $prioridade->addItems([
            '' => 'Todas as prioridades',
            'Alta'  => '🔴 Alta',
            'Media' => '🟡 Média',
            'Baixa' => '🟢 Baixa',
        ]);

        $origem_lead->addItems([
            ''          => 'Todas as origens',
            'Site'      => 'Site / Landing Page',
            'Indicacao' => 'Indicação',
            'LinkedIn'  => 'LinkedIn',
            'Feira'     => 'Feira / Evento',
            'ColdCall'  => 'Cold Call',
            'WhatsApp'  => 'WhatsApp',
            'Parceiro'  => 'Parceiro',
            'Outro'     => 'Outro',
        ]);

        $proximo_de->setSize('100%');
        $proximo_de->setMask('dd/mm/yyyy');
        $proximo_de->setDatabaseMask('yyyy-mm-dd');
        $proximo_ate->setSize('100%');
        $proximo_ate->setMask('dd/mm/yyyy');
        $proximo_ate->setDatabaseMask('yyyy-mm-dd');

        // Layout do filtro em grid 4 colunas
        $filterGrid = new TElement('div');
        $filterGrid->style = 'display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:10px;';

        $fld = fn($lbl, $widget) => "<div><div style='font-size:12px;color:#64748b;margin-bottom:3px'>{$lbl}</div></div>";

        $addFld = function($label, $widget) use ($filterGrid) {
            $wrap = new TElement('div');
            $lEl = new TElement('div');
            $lEl->style = 'font-size:12px;color:#64748b;margin-bottom:3px;';
            $lEl->add($label);
            $wrap->add($lEl);
            $wrap->add($widget);
            $filterGrid->add($wrap);
        };

        $addFld('Empresa',          $company_name);
        $addFld('Responsável',      $responsible);
        $addFld('Status',           $status);
        $addFld('Prioridade',       $prioridade);
        $addFld('Origem do Lead',   $origem_lead);

        $wDates = new TElement('div');
        $wDates->style = 'display:grid;grid-template-columns:1fr 1fr;gap:6px;';
        $d1 = new TElement('div');
        $d1->add('<div style="font-size:11px;color:#94a3b8;margin-bottom:2px">De</div>');
        $d1->add($proximo_de);
        $d2 = new TElement('div');
        $d2->add('<div style="font-size:11px;color:#94a3b8;margin-bottom:2px">Até</div>');
        $d2->add($proximo_ate);
        $wDates->add($d1);
        $wDates->add($d2);
        $wDatesWrap = new TElement('div');
        $wDatesWrap->add('<div style="font-size:12px;color:#64748b;margin-bottom:3px">Próximo Contato</div>');
        $wDatesWrap->add($wDates);
        $filterGrid->add($wDatesWrap);

        $btnSearch  = new TButton('Buscar');
        $btnSearch->setLabel('Buscar');
        $btnSearch->setImage('fa:search blue');
        $btnSearch->setAction(new TAction([$this, 'onSearch']), 'Buscar');

        $btnClear = new TButton('Limpar');
        $btnClear->setLabel('Limpar Filtros');
        $btnClear->setImage('fa:eraser gray');
        $btnClear->setAction(new TAction([$this, 'onClear']), 'Limpar');

        $btnKanban = new TButton('AbrirKanban');
        $btnKanban->setLabel('Kanban');
        $btnKanban->setImage('fa:columns green');
        $btnKanban->setAction(new TAction(['OpportunityKanban', 'onReload']));

        $btnPropostas = new TButton('AbrirPropostas');
        $btnPropostas->setLabel('Propostas');
        $btnPropostas->setImage('fa:file-text purple');
        $btnPropostas->setAction(new TAction(['PropostaList', 'onReload']));

        $btnNovo = new TButton('Novo');
        $btnNovo->setLabel('Nova Oportunidade');
        $btnNovo->setImage('fa:plus green');
        $btnNovo->setAction(new TAction(['OpportunityForm', 'onEdit']));

        $btnDash = new TButton('Dashboard');
        $btnDash->setLabel('Dashboard CRM');
        $btnDash->setImage('fa:chart-bar indigo');
        $btnDash->setAction(new TAction(['CrmDashboard', 'onReload']));

        $btnRow = new THBox;
        $btnRow->style = 'display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;';
        $btnRow->add($btnSearch);
        $btnRow->add($btnClear);
        $btnRow->add($btnNovo);
        $btnRow->add($btnKanban);
        $btnRow->add($btnPropostas);
        $btnRow->add($btnDash);

        $this->form->add($filterGrid);
        $this->form->add($btnRow);
        $this->form->setFields([$company_name, $responsible, $status, $prioridade, $origem_lead, $proximo_de, $proximo_ate, $btnSearch, $btnClear, $btnKanban, $btnPropostas, $btnNovo, $btnDash]);

        // ---- DataGrid ----
        $this->datagrid = new TDataGrid;
        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center', '4%'));

        $colCompany = new TDataGridColumn('company_name', 'Empresa', 'left', '18%');
        $this->datagrid->addColumn($colCompany);

        $colResp = new TDataGridColumn('responsible_name', 'Responsável', 'left', '14%');
        $this->datagrid->addColumn($colResp);

        $colPhone = new TDataGridColumn('phone', 'Telefone', 'left', '11%');
        $this->datagrid->addColumn($colPhone);

        $colEmail = new TDataGridColumn('email', 'E-mail', 'left', '15%');
        $this->datagrid->addColumn($colEmail);

        $colValor = new TDataGridColumn('valor_estimado', 'Valor', 'right', '10%');
        $colValor->setTransformer(function ($v) {
            if (!$v) return '—';
            return 'R$ ' . number_format((float)$v, 2, ',', '.');
        });
        $this->datagrid->addColumn($colValor);

        $colPrio = new TDataGridColumn('prioridade', 'Prio.', 'center', '7%');
        $colPrio->setTransformer(function ($v) {
            if (!$v) return '—';
            $map = ['Alta' => '#fee2e2;color:#991b1b', 'Media' => '#fef9c3;color:#92400e', 'Baixa' => '#dcfce7;color:#15803d'];
            $icons = ['Alta' => '🔴', 'Media' => '🟡', 'Baixa' => '🟢'];
            $c = $map[$v] ?? '#f1f5f9;color:#475569';
            $i = $icons[$v] ?? '';
            return "<span style='font-size:10px;padding:2px 7px;border-radius:999px;background:{$c};font-weight:700'>{$i} {$v}</span>";
        });
        $this->datagrid->addColumn($colPrio);

        $colProx = new TDataGridColumn('proximo_contato', 'Próx. Contato', 'center', '9%');
        $colProx->setTransformer(function ($v) {
            if (!$v) return '—';
            $today = date('Y-m-d');
            $fmt = TDate::convertToMask($v, 'yyyy-mm-dd', 'dd/mm/yyyy');
            $color = ($v < $today) ? 'color:#991b1b;font-weight:700' : 'color:#0f172a';
            $icon  = ($v < $today) ? '⚠️ ' : '📅 ';
            return "<span style='{$color}'>{$icon}{$fmt}</span>";
        });
        $this->datagrid->addColumn($colProx);

        $statusColumn = new TDataGridColumn('status', 'Status', 'center', '11%');
        $statusColumn->setTransformer(function ($value) {
            $map = self::getStatusMap();
            $item = $map[$value] ?? ['label' => $value ?: 'Não definido', 'class' => 'bg-slate'];
            return '<span class="crm-status-badge ' . $item['class'] . '">' . htmlspecialchars($item['label']) . '</span>';
        });
        $this->datagrid->addColumn($statusColumn);

        // Ações
        $actionEdit = new TDataGridAction(['OpportunityForm', 'onEdit'], ['key' => '{id}']);
        $actionEdit->setLabel('Editar');
        $actionEdit->setImage('far:edit blue');
        $this->datagrid->addAction($actionEdit);

        $actionHistory = new TDataGridAction(['CrmActivityList', 'onLoad'], ['opportunity_id' => '{id}']);
        $actionHistory->setLabel('Histórico');
        $actionHistory->setImage('fa:history teal');
        $this->datagrid->addAction($actionHistory);

        $actionEmail = new TDataGridAction(['EmailComposerView', 'onLoadFromOpportunity'], ['opportunity_id' => '{id}']);
        $actionEmail->setLabel('Compor E-mail');
        $actionEmail->setImage('fas:envelope green');
        $this->datagrid->addAction($actionEmail);

        $actionProposal = new TDataGridAction([$this, 'onCreateProposal'], ['key' => '{id}']);
        $actionProposal->setLabel('Gerar Proposta');
        $actionProposal->setImage('fa:file-text purple');
        $this->datagrid->addAction($actionProposal);

        $actionDelete = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $actionDelete->setLabel('Excluir');
        $actionDelete->setImage('far:trash-alt red');
        $this->datagrid->addAction($actionDelete);

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setLimit(15);

        $this->dashboardContainer = new TElement('div');

        $box = new TVBox;
        $box->style = 'width: 100%';
        if (is_file('menu.xml')) {
            $box->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        }

        $dashboardPanel = new TPanelGroup('Painel Geral de Prospecção');
        $dashboardPanel->add($this->dashboardContainer);

        $filterPanel = new TPanelGroup('Filtros');
        $filterPanel->add($this->form);

        $listPanel = new TPanelGroup('Leads / Oportunidades');
        $listPanel->add($this->datagrid);
        $listPanel->addFooter($this->pageNavigation);

        $box->add($dashboardPanel);
        $box->add($filterPanel);
        $box->add($listPanel);

        parent::add($box);
    }

    public function onSearch($param = null)
    {
        TSession::setValue('opportunity_filter', $param);
        $this->onReload($param);
    }

    public static function onClear($param = null)
    {
        TSession::setValue('opportunity_filter', []);
        TApplication::loadPage(__CLASS__, 'onReload');
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open($this->database);
            $repository = new TRepository($this->activeRecord);

            $limit  = 15;
            $param  = $param ?? TSession::getValue('opportunity_filter') ?? [];
            $page   = isset($param['page']) ? (int)$param['page'] : 1;
            $offset = ($page - 1) * $limit;

            $data = $this->form->getData();
            // Restaura filtros da sessão no form
            if ($saved = TSession::getValue('opportunity_filter')) {
                $obj = (object)$saved;
                $this->form->setData($obj);
                $data = $obj;
            }

            $baseCriteria = new TCriteria;

            if (!empty($data->company_name)) {
                $baseCriteria->add(new TFilter('company_name', 'like', "%{$data->company_name}%"));
            }
            if (!empty($data->responsible_name)) {
                $baseCriteria->add(new TFilter('responsible_name', 'like', "%{$data->responsible_name}%"));
            }
            if (!empty($data->status)) {
                $baseCriteria->add(new TFilter('status', '=', $data->status));
            }
            if (!empty($data->prioridade)) {
                $baseCriteria->add(new TFilter('prioridade', '=', $data->prioridade));
            }
            if (!empty($data->origem_lead)) {
                $baseCriteria->add(new TFilter('origem_lead', '=', $data->origem_lead));
            }
            if (!empty($data->proximo_de)) {
                $de = TDate::convertToMask($data->proximo_de, 'dd/mm/yyyy', 'yyyy-mm-dd');
                $baseCriteria->add(new TFilter('proximo_contato', '>=', $de));
            }
            if (!empty($data->proximo_ate)) {
                $ate = TDate::convertToMask($data->proximo_ate, 'dd/mm/yyyy', 'yyyy-mm-dd');
                $baseCriteria->add(new TFilter('proximo_contato', '<=', $ate));
            }

            $listCriteria = clone $baseCriteria;
            $listCriteria->setProperty('limit',  $limit);
            $listCriteria->setProperty('offset', $offset);
            $listCriteria->setProperty('order',  'id desc');

            $this->datagrid->clear();

            $items = $repository->load($listCriteria);
            if ($items) {
                foreach ($items as $item) {
                    $this->datagrid->addItem($item);
                }
            }

            $total = $repository->count($baseCriteria);
            $allItems = $repository->load($baseCriteria) ?: [];

            $this->pageNavigation->setCount($total);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setPage($page);

            TTransaction::close();

            $this->renderDashboard($allItems);
            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private function renderDashboard(array $items)
    {
        $statusMap = self::getStatusMap();
        $statusCount = array_fill_keys(array_keys($statusMap), 0);
        $statusValue = array_fill_keys(array_keys($statusMap), 0.0);

        $responsibleCount = [];
        $total = count($items);
        $active = 0;
        $won = 0;
        $withEmail = 0;
        $followUpHoje = 0;
        $today = date('Y-m-d');

        foreach ($items as $item) {
            $status = strtoupper(trim((string)$item->status));
            if (!array_key_exists($status, $statusCount)) {
                $status = 'QUALIFICACAO';
            }
            $statusCount[$status]++;
            $statusValue[$status] += (float)($item->valor_estimado ?? 0);

            if ($status === 'FECHAMENTO') $won++;
            elseif ($status !== 'PERDIDO') $active++;

            if (!empty($item->email)) $withEmail++;

            if (!empty($item->proximo_contato) && $item->proximo_contato <= $today) {
                $followUpHoje++;
            }

            $responsible = trim((string)($item->responsible_name ?: 'Sem responsável'));
            $responsibleCount[$responsible] = ($responsibleCount[$responsible] ?? 0) + 1;
        }

        arsort($responsibleCount);
        $responsibleTop = array_slice($responsibleCount, 0, 5, true);

        $totalValue = array_sum($statusValue);
        $wonValue   = $statusValue['FECHAMENTO'] ?? 0;
        $taxaConv   = $total > 0 ? round(($won / $total) * 100, 1) : 0;

        $statusLabels = [];
        $statusValues = [];
        $statusColors = [];
        foreach ($statusMap as $key => $info) {
            $statusLabels[] = $info['label'];
            $statusValues[] = $statusCount[$key] ?? 0;
            $statusColors[] = $info['chart'];
        }

        $responsibleLabels = array_keys($responsibleTop);
        $responsibleValues = array_values($responsibleTop);

        $statusLabelsJs    = json_encode($statusLabels);
        $statusValuesJs    = json_encode($statusValues);
        $statusColorsJs    = json_encode($statusColors);
        $responsibleLabelsJs = json_encode($responsibleLabels);
        $responsibleValuesJs = json_encode($responsibleValues);

        $totalValFmt = $totalValue > 0 ? 'R$ ' . number_format($totalValue, 0, ',', '.') : '—';
        $wonValFmt   = $wonValue   > 0 ? 'R$ ' . number_format($wonValue, 0, ',', '.') : '—';

        $followAlert = $followUpHoje > 0
            ? "<div style='background:#fef9c3;border:1px solid #fde047;border-radius:10px;padding:8px 14px;margin-bottom:12px;font-size:13px;color:#92400e;'>⚠️ <strong>{$followUpHoje} oportunidade(s)</strong> com follow-up pendente hoje ou em atraso! <a href='#' style='color:#b45309;font-weight:700;'>Ver lista</a></div>"
            : '';

        $html = <<<HTML
<style>
  .crm-dashboard { color:#0f172a; }
  .crm-grid { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
  .crm-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; box-shadow:0 2px 8px rgba(15,23,42,.06); }
  .crm-card-title { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
  .crm-card-value { font-size:24px; font-weight:700; margin-top:4px; }
  .crm-card-sub { font-size:11px; color:#94a3b8; margin-top:3px; }
  .crm-chart-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .crm-chart-box { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; box-shadow:0 2px 8px rgba(15,23,42,.06); }
  .crm-chart-title { margin:0 0 10px; font-size:14px; font-weight:600; color:#0f172a; }
  .crm-status-badge { padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; display:inline-block; }
  .crm-status-badge.bg-blue { background:#dbeafe; color:#1d4ed8; }
  .crm-status-badge.bg-amber { background:#fef3c7; color:#b45309; }
  .crm-status-badge.bg-indigo { background:#e0e7ff; color:#4338ca; }
  .crm-status-badge.bg-green { background:#dcfce7; color:#15803d; }
  .crm-status-badge.bg-red { background:#fee2e2; color:#991b1b; }
  .crm-status-badge.bg-slate { background:#f1f5f9; color:#475569; }
  @media(max-width:1300px){ .crm-grid { grid-template-columns:repeat(3,minmax(0,1fr)); } }
  @media(max-width:900px){ .crm-chart-grid { grid-template-columns:1fr; } .crm-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
</style>
<div class="crm-dashboard">
  {$followAlert}
  <div class="crm-grid">
    <div class="crm-card"><div class="crm-card-title">Total Leads</div><div class="crm-card-value">{$total}</div><div class="crm-card-sub">No funil</div></div>
    <div class="crm-card"><div class="crm-card-title">Valor Total</div><div class="crm-card-value" style="font-size:18px;color:#1d4ed8">{$totalValFmt}</div><div class="crm-card-sub">Soma estimada</div></div>
    <div class="crm-card"><div class="crm-card-title">Ativos</div><div class="crm-card-value" style="color:#2563eb">{$active}</div><div class="crm-card-sub">Em andamento</div></div>
    <div class="crm-card"><div class="crm-card-title">Fechados</div><div class="crm-card-value" style="color:#15803d">{$won}</div><div class="crm-card-sub">{$wonValFmt}</div></div>
    <div class="crm-card"><div class="crm-card-title">Tx. Conversão</div><div class="crm-card-value" style="color:#7c3aed">{$taxaConv}%</div><div class="crm-card-sub">Lead → Fechado</div></div>
    <div class="crm-card"><div class="crm-card-title">Follow-up Hoje</div><div class="crm-card-value" style="color:#dc2626">{$followUpHoje}</div><div class="crm-card-sub">Pendentes / Atrasados</div></div>
  </div>
  <div class="crm-chart-grid">
    <div class="crm-chart-box"><p class="crm-chart-title">Distribuição do Funil</p><canvas id="opp-status-chart" height="180"></canvas></div>
    <div class="crm-chart-box"><p class="crm-chart-title">Leads por Responsável (Top 5)</p><canvas id="opp-resp-chart" height="180"></canvas></div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function(){
  if(typeof Chart==='undefined') return;
  var sc=document.getElementById('opp-status-chart');
  var rc=document.getElementById('opp-resp-chart');
  if(!sc||!rc) return;
  if(window.oppStatusChart) window.oppStatusChart.destroy();
  if(window.oppRespChart)   window.oppRespChart.destroy();
  window.oppStatusChart=new Chart(sc,{type:'doughnut',data:{labels:{$statusLabelsJs},datasets:[{data:{$statusValuesJs},backgroundColor:{$statusColorsJs},borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,plugins:{legend:{position:'bottom'}}}});
  window.oppRespChart=new Chart(rc,{type:'bar',data:{labels:{$responsibleLabelsJs},datasets:[{label:'Leads',data:{$responsibleValuesJs},backgroundColor:'#3b82f6',borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});
})();
</script>
HTML;
        $this->dashboardContainer->clearChildren();
        $this->dashboardContainer->add($html);
    }

    private static function getStatusMap()
    {
        return [
            'QUALIFICACAO' => ['label' => 'Qualificação', 'class' => 'bg-blue',   'chart' => '#60a5fa'],
            'PROPOSTA'     => ['label' => 'Proposta',     'class' => 'bg-indigo', 'chart' => '#818cf8'],
            'NEGOCIACAO'   => ['label' => 'Negociação',   'class' => 'bg-amber',  'chart' => '#fbbf24'],
            'FECHAMENTO'   => ['label' => 'Fechamento',   'class' => 'bg-green',  'chart' => '#34d399'],
            'PERDIDO'      => ['label' => 'Perdido',      'class' => 'bg-red',    'chart' => '#f87171'],
        ];
    }

    public function onDelete($param)
    {
        if (isset($param['key'])) {
            $actionYes = new TAction([$this, 'confirmDelete']);
            $actionYes->setParameters(['key' => $param['key']]);
            new TQuestion('Deseja excluir o registro?', $actionYes);
        }
    }

    public function onCreateProposal($param)
    {
        try {
            if (empty($param['key'])) throw new Exception('Oportunidade não informada');
            TTransaction::open($this->database);
            $opp = new Opportunity((int)$param['key']);
            $opp->status = 'PROPOSTA';
            $opp->store();
            TTransaction::close();
            TApplication::loadPage('PropostaForm', 'onEdit', [
                'opportunity_id'      => $opp->id,
                'opportunity_company' => $opp->company_name,
                'opportunity_contact' => $opp->responsible_name,
                'opportunity_email'   => $opp->email,
                'opportunity_phone'   => $opp->phone,
                'opportunity_notes'   => $opp->notes,
            ]);
        } catch (Exception $e) {
            try { TTransaction::rollback(); } catch (Exception $e2) {}
            new TMessage('error', 'Não foi possível gerar proposta: ' . $e->getMessage());
        }
    }

    public function confirmDelete($param)
    {
        try {
            TTransaction::open($this->database);
            $object = new $this->activeRecord($param['key']);
            $object->delete();
            TTransaction::close();
            $this->onReload();
            new TMessage('info', 'Registro excluído com sucesso');
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload($_GET);
        }
        parent::show();
    }
}
