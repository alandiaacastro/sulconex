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

    private $database = 'sample';
    private $activeRecord = 'Opportunity';

    public function __construct()
    {
        parent::__construct();

        $this->form = new TForm('form_search_Opportunity');

        $companyName = new TEntry('company_name');
        $companyName->setSize('100%');
        $companyName->setProperty('placeholder', 'Nome da empresa');

        $row = new THBox;
        $row->add(new TLabel('Empresa:'));
        $row->add($companyName);
        $this->form->add($row);

        $btnSearch = new TButton('Buscar');
        $btnSearch->setLabel('Buscar');
        $btnSearch->setImage('fa:search blue');
        $btnSearch->setAction(new TAction([$this, 'onReload']), 'Buscar');

        $btnKanban = new TButton('AbrirKanban');
        $btnKanban->setLabel('Ver Funil (Kanban)');
        $btnKanban->setImage('fa:columns green');
        $btnKanban->setAction(new TAction(['OpportunityKanban', 'onReload']));

        $btnPropostas = new TButton('AbrirPropostas');
        $btnPropostas->setLabel('Abrir Propostas');
        $btnPropostas->setImage('fa:file-text purple');
        $btnPropostas->setAction(new TAction(['PropostaList', 'onReload']));

        $buttons = new THBox;
        $buttons->style = 'display:flex; gap:10px; margin-top:8px;';
        $buttons->add($btnSearch);
        $buttons->add($btnKanban);
        $buttons->add($btnPropostas);

        $this->form->addField($companyName);
        $this->form->addField($btnSearch);
        $this->form->addField($btnKanban);
        $this->form->addField($btnPropostas);
        $this->form->add($buttons);

        $this->datagrid = new TDataGrid;
        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center', '6%'));
        $this->datagrid->addColumn(new TDataGridColumn('company_name', 'Empresa', 'left', '25%'));
        $this->datagrid->addColumn(new TDataGridColumn('responsible_name', 'Responsavel', 'left', '20%'));
        $this->datagrid->addColumn(new TDataGridColumn('phone', 'Telefone', 'left', '15%'));
        $this->datagrid->addColumn(new TDataGridColumn('email', 'E-mail', 'left', '20%'));

        $statusColumn = new TDataGridColumn('status', 'Status', 'center', '14%');
        $statusColumn->setTransformer(function ($value) {
            $map = self::getStatusMap();
            $item = $map[$value] ?? ['label' => $value ?: 'Nao definido', 'class' => 'bg-slate'];
            return '<span class="crm-status-badge ' . $item['class'] . '">' . htmlspecialchars($item['label']) . '</span>';
        });
        $this->datagrid->addColumn($statusColumn);

        $actionEdit = new TDataGridAction(['OpportunityForm', 'onEdit'], ['key' => '{id}']);
        $actionEdit->setLabel(_t('Edit'));
        $actionEdit->setImage('far:edit blue');
        $this->datagrid->addAction($actionEdit);

        $actionEmail = new TDataGridAction(['EmailComposerView', 'onLoadFromOpportunity'], ['opportunity_id' => '{id}']);
        $actionEmail->setLabel('Compor E-mail');
        $actionEmail->setImage('fas:envelope green');
        $this->datagrid->addAction($actionEmail);

        $actionProposal = new TDataGridAction([$this, 'onCreateProposal'], ['key' => '{id}']);
        $actionProposal->setLabel('Gerar Proposta');
        $actionProposal->setImage('fa:file-text purple');
        $this->datagrid->addAction($actionProposal);

        $actionDelete = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $actionDelete->setLabel(_t('Delete'));
        $actionDelete->setImage('far:trash-alt red');
        $this->datagrid->addAction($actionDelete);

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setLimit(10);

        $this->dashboardContainer = new TElement('div');

        $box = new TVBox;
        $box->style = 'width: 100%';

        if (is_file('menu.xml')) {
            $box->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        }

        $dashboardPanel = new TPanelGroup('Painel Geral de Prospeccao');
        $dashboardPanel->add($this->dashboardContainer);

        $filterPanel = new TPanelGroup('Filtro de Leads');
        $filterPanel->add($this->form);

        $listPanel = new TPanelGroup('Leads / Clientes');
        $listPanel->add($this->datagrid);
        $listPanel->addFooter($this->pageNavigation);

        $box->add($dashboardPanel);
        $box->add($filterPanel);
        $box->add($listPanel);

        parent::add($box);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open($this->database);
            $repository = new TRepository($this->activeRecord);

            $limit = 10;
            $param = $param ?? [];
            $page = isset($param['page']) ? (int) $param['page'] : 1;
            $offset = ($page - 1) * $limit;

            $data = $this->form->getData();

            $baseCriteria = new TCriteria;
            if (!empty($data->company_name)) {
                $baseCriteria->add(new TFilter('company_name', 'like', "%{$data->company_name}%"));
            }

            $listCriteria = clone $baseCriteria;
            $listCriteria->setProperty('limit', $limit);
            $listCriteria->setProperty('offset', $offset);
            $listCriteria->setProperty('order', 'id desc');

            $this->form->setData($data);
            $this->datagrid->clear();

            $items = $repository->load($listCriteria);
            if ($items) {
                foreach ($items as $item) {
                    $this->datagrid->addItem($item);
                }
            }

            $allItems = $repository->load($baseCriteria) ?: [];
            $total = $repository->count($baseCriteria);

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
        $statusCount = [
            'QUALIFICACAO' => 0,
            'PROPOSTA' => 0,
            'NEGOCIACAO' => 0,
            'FECHAMENTO' => 0,
        ];

        $responsibleCount = [];
        $total = count($items);
        $active = 0;
        $won = 0;
        $withEmail = 0;

        foreach ($items as $item) {
            $status = strtoupper(trim((string) $item->status));
            if (!isset($statusCount[$status])) {
                $status = 'QUALIFICACAO';
            }
            $statusCount[$status]++;

            if ($status === 'FECHAMENTO') {
                $won++;
            } else {
                $active++;
            }

            if (!empty($item->email)) {
                $withEmail++;
            }

            $responsible = trim((string) ($item->responsible_name ?: 'Sem responsavel'));
            $responsibleCount[$responsible] = ($responsibleCount[$responsible] ?? 0) + 1;
        }

        arsort($responsibleCount);
        $responsibleTop = array_slice($responsibleCount, 0, 5, true);

        $statusLabels = [];
        $statusValues = [];
        $statusColors = [];
        foreach ($statusCount as $key => $value) {
            $statusLabels[] = $statusMap[$key]['label'];
            $statusValues[] = $value;
            $statusColors[] = $statusMap[$key]['chart'];
        }

        $responsibleLabels = array_keys($responsibleTop);
        $responsibleValues = array_values($responsibleTop);

        $statusLabelsJs = json_encode($statusLabels);
        $statusValuesJs = json_encode($statusValues);
        $statusColorsJs = json_encode($statusColors);
        $responsibleLabelsJs = json_encode($responsibleLabels);
        $responsibleValuesJs = json_encode($responsibleValues);

        $html = <<<HTML
<style>
  .crm-dashboard { color: #0f172a; }
  .crm-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 14px; margin-bottom: 16px; }
  .crm-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; box-shadow: 0 2px 8px rgba(15,23,42,.06); }
  .crm-card-title { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
  .crm-card-value { font-size: 28px; font-weight: 700; margin-top: 4px; }
  .crm-card-sub { font-size: 12px; color: #64748b; margin-top: 3px; }
  .crm-chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .crm-chart-box { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; box-shadow: 0 2px 8px rgba(15,23,42,.06); }
  .crm-chart-title { margin:0 0 10px; font-size:15px; font-weight:600; color:#0f172a; }
  .crm-status-badge { padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; display: inline-block; }
  .crm-status-badge.bg-blue { background:#dbeafe; color:#1d4ed8; }
  .crm-status-badge.bg-amber { background:#fef3c7; color:#b45309; }
  .crm-status-badge.bg-indigo { background:#e0e7ff; color:#4338ca; }
  .crm-status-badge.bg-green { background:#dcfce7; color:#15803d; }
  .crm-status-badge.bg-slate { background:#f1f5f9; color:#475569; }
  @media (max-width: 1200px){ .crm-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
  @media (max-width: 900px){ .crm-chart-grid { grid-template-columns: 1fr; } }
  @media (max-width: 640px){ .crm-grid { grid-template-columns: 1fr; } }
</style>

<div class="crm-dashboard">
  <div class="crm-grid">
    <div class="crm-card">
      <div class="crm-card-title">Total de Leads</div>
      <div class="crm-card-value">{$total}</div>
      <div class="crm-card-sub">Empresas no funil</div>
    </div>
    <div class="crm-card">
      <div class="crm-card-title">Prospeccoes Ativas</div>
      <div class="crm-card-value" style="color:#1d4ed8">{$active}</div>
      <div class="crm-card-sub">Qualificacao, proposta e negociacao</div>
    </div>
    <div class="crm-card">
      <div class="crm-card-title">Contratos Fechados</div>
      <div class="crm-card-value" style="color:#15803d">{$won}</div>
      <div class="crm-card-sub">Status de fechamento</div>
    </div>
    <div class="crm-card">
      <div class="crm-card-title">Com E-mail Cadastrado</div>
      <div class="crm-card-value">{$withEmail}</div>
      <div class="crm-card-sub">Prontos para automacao</div>
    </div>
  </div>

  <div class="crm-chart-grid">
    <div class="crm-chart-box">
      <p class="crm-chart-title">Distribuicao do Funil</p>
      <canvas id="opp-status-chart" height="190"></canvas>
    </div>
    <div class="crm-chart-box">
      <p class="crm-chart-title">Leads por Responsavel (Top 5)</p>
      <canvas id="opp-resp-chart" height="190"></canvas>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
  if (typeof Chart === 'undefined') return;

  var statusCanvas = document.getElementById('opp-status-chart');
  var respCanvas = document.getElementById('opp-resp-chart');
  if (!statusCanvas || !respCanvas) return;

  if (window.oppStatusChart) { window.oppStatusChart.destroy(); }
  if (window.oppRespChart) { window.oppRespChart.destroy(); }

  window.oppStatusChart = new Chart(statusCanvas, {
    type: 'doughnut',
    data: {
      labels: {$statusLabelsJs},
      datasets: [{
        data: {$statusValuesJs},
        backgroundColor: {$statusColorsJs},
        borderWidth: 2,
        borderColor: '#fff'
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'bottom' } }
    }
  });

  window.oppRespChart = new Chart(respCanvas, {
    type: 'bar',
    data: {
      labels: {$responsibleLabelsJs},
      datasets: [{
        label: 'Leads',
        data: {$responsibleValuesJs},
        backgroundColor: '#3b82f6',
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
  });
})();
</script>
HTML;

        $this->dashboardContainer->clearChildren();
        $this->dashboardContainer->add($html);
    }

    private static function getStatusMap()
    {
        return [
            'QUALIFICACAO' => ['label' => 'Qualificacao', 'class' => 'bg-blue', 'chart' => '#60a5fa'],
            'PROPOSTA' => ['label' => 'Proposta', 'class' => 'bg-indigo', 'chart' => '#818cf8'],
            'NEGOCIACAO' => ['label' => 'Negociacao', 'class' => 'bg-amber', 'chart' => '#fbbf24'],
            'FECHAMENTO' => ['label' => 'Fechamento', 'class' => 'bg-green', 'chart' => '#34d399'],
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
            if (empty($param['key'])) {
                throw new Exception('Oportunidade nao informada');
            }

            TTransaction::open($this->database);
            $opp = new Opportunity((int) $param['key']);
            $opp->status = 'PROPOSTA';
            $opp->store();
            TTransaction::close();

            $proposalParam = [
                'opportunity_id' => $opp->id,
                'opportunity_company' => $opp->company_name,
                'opportunity_contact' => $opp->responsible_name,
                'opportunity_email' => $opp->email,
                'opportunity_phone' => $opp->phone,
                'opportunity_notes' => $opp->notes,
            ];

            TApplication::loadPage('PropostaForm', 'onEdit', $proposalParam);
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $rollbackException) {
            }
            new TMessage('error', 'Nao foi possivel gerar proposta: ' . $e->getMessage());
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
            new TMessage('info', 'Registro excluido com sucesso');
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
