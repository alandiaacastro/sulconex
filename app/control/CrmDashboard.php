<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\THBox;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Util\TXMLBreadCrumb;

class CrmDashboard extends TPage
{
    private $container;
    private $loaded = false;

    public function __construct()
    {
        parent::__construct();

        $box = new TVBox;
        $box->style = 'width:100%';

        if (is_file('menu.xml')) {
            $box->add(new TXMLBreadCrumb('menu.xml', 'OpportunityList'));
        }

        // Toolbar
        $actionForm = new TForm('form_crm_dash_actions');

        $btnKanban = new TButton('btn_kanban');
        $btnKanban->setLabel('Kanban');
        $btnKanban->setImage('fa:columns green');
        $btnKanban->setAction(new TAction(['OpportunityKanban', 'onReload']));

        $btnList = new TButton('btn_list');
        $btnList->setLabel('Lista de Leads');
        $btnList->setImage('fa:list blue');
        $btnList->setAction(new TAction(['OpportunityList', 'onReload']));

        $btnPropostas = new TButton('btn_propostas');
        $btnPropostas->setLabel('Propostas');
        $btnPropostas->setImage('fa:file-text purple');
        $btnPropostas->setAction(new TAction(['PropostaList', 'onReload']));

        $btnNovo = new TButton('btn_novo');
        $btnNovo->setLabel('Nova Oportunidade');
        $btnNovo->setImage('fa:plus orange');
        $btnNovo->setAction(new TAction(['OpportunityForm', 'onEdit']));

        $btnReload = new TButton('btn_reload');
        $btnReload->setLabel('Atualizar');
        $btnReload->setImage('fa:sync gray');
        $btnReload->setAction(new TAction([$this, 'onReload']));

        $toolbar = new THBox;
        $toolbar->style = 'display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;';
        $toolbar->add($btnNovo);
        $toolbar->add($btnKanban);
        $toolbar->add($btnList);
        $toolbar->add($btnPropostas);
        $toolbar->add($btnReload);

        $actionForm->add($toolbar);
        $actionForm->setFields([$btnKanban, $btnList, $btnPropostas, $btnNovo, $btnReload]);

        $this->container = new TElement('div');

        $box->add($actionForm);
        $box->add($this->container);

        parent::add($box);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');

            $repoOpp  = new TRepository('Opportunity');
            $repoProp = new TRepository('Proposta');

            // Oportunidades ativas (excluindo perdidos)
            $critAtivo = new TCriteria;
            $critAtivo->add(new TFilter('status', '!=', 'PERDIDO'));
            $opps = $repoOpp->load($critAtivo) ?: [];

            // Propostas
            $props = $repoProp->load(new TCriteria) ?: [];

            $today = date('Y-m-d');
            $thisMonth = date('Y-m');

            // --- Métricas de Oportunidades ---
            $totalOpps   = count($opps);
            $statusCount = ['QUALIFICACAO' => 0, 'PROPOSTA' => 0, 'NEGOCIACAO' => 0, 'FECHAMENTO' => 0];
            $statusValue = ['QUALIFICACAO' => 0.0, 'PROPOSTA' => 0.0, 'NEGOCIACAO' => 0.0, 'FECHAMENTO' => 0.0];
            $prioCount   = ['Alta' => 0, 'Media' => 0, 'Baixa' => 0];
            $origemCount = [];
            $followUpVencidos = [];
            $fechandoEm30 = [];

            foreach ($opps as $opp) {
                $st = strtoupper(trim((string)$opp->status));
                if (array_key_exists($st, $statusCount)) {
                    $statusCount[$st]++;
                    $statusValue[$st] += (float)($opp->valor_estimado ?? 0);
                }

                if (!empty($opp->prioridade) && array_key_exists($opp->prioridade, $prioCount)) {
                    $prioCount[$opp->prioridade]++;
                }

                $origem = $opp->origem_lead ?: 'Não informado';
                $origemCount[$origem] = ($origemCount[$origem] ?? 0) + 1;

                // Follow-up vencido
                if (!empty($opp->proximo_contato) && $opp->proximo_contato <= $today && $st !== 'FECHAMENTO') {
                    $followUpVencidos[] = $opp;
                }

                // Fechando em 30 dias
                if (!empty($opp->closing_date) && $opp->closing_date >= $today) {
                    $diff = (strtotime($opp->closing_date) - strtotime($today)) / 86400;
                    if ($diff <= 30) {
                        $fechandoEm30[] = $opp;
                    }
                }
            }

            $totalValue = array_sum($statusValue);
            $won = $statusCount['FECHAMENTO'];
            $wonValue = $statusValue['FECHAMENTO'];
            $active = $totalOpps - $won;
            $taxaConv = $totalOpps > 0 ? round(($won / $totalOpps) * 100, 1) : 0;

            // --- Métricas de Propostas ---
            $totalProps = count($props);
            $propAprov = 0; $propAnal = 0; $propRej = 0;
            $propFat = 0.0; $propRes = 0.0;
            $propMes = 0;
            $propVencidas = 0;

            // Evolução mensal de propostas (últimos 6 meses)
            $monthLabels = [];
            $monthCounts = [];
            for ($i = 5; $i >= 0; $i--) {
                $m = date('Y-m', strtotime("-{$i} months"));
                $monthLabels[] = date('M/y', strtotime("-{$i} months"));
                $monthCounts[$m] = 0;
            }

            foreach ($props as $p) {
                if ($p->Situacao === 'Aprovada')   $propAprov++;
                if ($p->Situacao === 'Em Analise') $propAnal++;
                if ($p->Situacao === 'Rejeitada')  $propRej++;
                $propFat += (float)($p->Faturamento_Valor_1 ?? 0);
                $propRes += (float)($p->resultado_final ?? 0);

                $emMes = substr((string)($p->Data_Cotacao ?? ''), 0, 7);
                if ($emMes === $thisMonth) $propMes++;
                if (isset($monthCounts[$emMes])) $monthCounts[$emMes]++;

                if (!empty($p->Data_Validade_Cotacao) && $p->Data_Validade_Cotacao < $today && $p->Situacao === 'Em Analise') {
                    $propVencidas++;
                }
            }

            $txAprovProp = $totalProps > 0 ? round(($propAprov / $totalProps) * 100, 1) : 0;

            arsort($origemCount);
            $origemTop = array_slice($origemCount, 0, 6, true);

            TTransaction::close();

            // Construir HTML do dashboard
            $html = $this->buildDashboardHtml(
                $totalOpps, $active, $won, $wonValue, $totalValue, $taxaConv,
                $statusCount, $statusValue, $prioCount, $origemTop,
                $totalProps, $propAprov, $propAnal, $propRej, $propMes,
                $propFat, $propRes, $txAprovProp, $propVencidas,
                $followUpVencidos, $fechandoEm30,
                array_values($monthCounts), $monthLabels
            );

            $this->container->clearChildren();
            $this->container->add($html);
            $this->loaded = true;

        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao carregar dashboard: ' . $e->getMessage());
        }
    }

    private function buildDashboardHtml(
        $totalOpps, $active, $won, $wonValue, $totalValue, $taxaConv,
        array $statusCount, array $statusValue, array $prioCount, array $origemTop,
        $totalProps, $propAprov, $propAnal, $propRej, $propMes,
        $propFat, $propRes, $txAprovProp, $propVencidas,
        array $followUpVencidos, array $fechandoEm30,
        array $monthCounts, array $monthLabels
    ) {
        $fmtMoney  = fn($v) => $v > 0 ? 'R$ ' . number_format($v, 0, ',', '.') : '—';
        $fmtMoney2 = fn($v) => 'R$ ' . number_format($v, 2, ',', '.');

        $totalValFmt = $fmtMoney($totalValue);
        $wonValFmt   = $fmtMoney($wonValue);
        $negoValFmt  = $fmtMoney($statusValue['NEGOCIACAO'] ?? 0);
        $propFatFmt  = $fmtMoney($propFat);
        $propResFmt  = $fmtMoney($propRes);
        $propResColor = $propRes >= 0 ? '#065f46' : '#991b1b';

        // Alertas
        $alertas = '';
        if (!empty($followUpVencidos)) {
            $cnt = count($followUpVencidos);
            $alertas .= "<div style='background:#fef9c3;border:1px solid #fde047;border-radius:10px;padding:10px 16px;margin-bottom:10px;font-size:13px;color:#92400e;'>";
            $alertas .= "⚠️ <strong>{$cnt} oportunidade(s)</strong> com follow-up pendente ou atrasado:";
            $alertas .= "<ul style='margin:6px 0 0 16px;padding:0'>";
            foreach (array_slice($followUpVencidos, 0, 5) as $o) {
                $dt = TDate::convertToMask($o->proximo_contato, 'yyyy-mm-dd', 'dd/mm/yyyy');
                $alertas .= "<li><strong>" . htmlspecialchars($o->company_name) . "</strong> — Contato em {$dt} (Resp: " . htmlspecialchars($o->responsible_name ?? '—') . ")</li>";
            }
            $alertas .= "</ul></div>";
        }
        if (!empty($fechandoEm30)) {
            $cnt = count($fechandoEm30);
            $alertas .= "<div style='background:#dbeafe;border:1px solid #93c5fd;border-radius:10px;padding:10px 16px;margin-bottom:10px;font-size:13px;color:#1e40af;'>";
            $alertas .= "🏁 <strong>{$cnt} oportunidade(s)</strong> com previsão de fechamento nos próximos 30 dias:";
            $alertas .= "<ul style='margin:6px 0 0 16px;padding:0'>";
            foreach (array_slice($fechandoEm30, 0, 5) as $o) {
                $dt = TDate::convertToMask($o->closing_date, 'yyyy-mm-dd', 'dd/mm/yyyy');
                $val = (float)($o->valor_estimado ?? 0);
                $valStr = $val > 0 ? ' — R$ ' . number_format($val, 0, ',', '.') : '';
                $alertas .= "<li><strong>" . htmlspecialchars($o->company_name) . "</strong>{$valStr} (Fechar: {$dt})</li>";
            }
            $alertas .= "</ul></div>";
        }
        if ($propVencidas > 0) {
            $alertas .= "<div style='background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:10px 16px;margin-bottom:10px;font-size:13px;color:#991b1b;'>📋 <strong>{$propVencidas} proposta(s)</strong> com validade vencida aguardando aprovação.</div>";
        }

        // JSON para gráficos
        $statusLabels = json_encode(['Qualificação', 'Proposta', 'Negociação', 'Fechamento']);
        $statusVals   = json_encode(array_values($statusCount));
        $statusColors = json_encode(['#60a5fa', '#818cf8', '#fbbf24', '#34d399']);

        $prioLabels = json_encode(['Alta', 'Média', 'Baixa']);
        $prioVals   = json_encode([$prioCount['Alta'], $prioCount['Media'], $prioCount['Baixa']]);
        $prioColors = json_encode(['#f87171', '#fbbf24', '#34d399']);

        $origemLabels = json_encode(array_keys($origemTop));
        $origemVals   = json_encode(array_values($origemTop));

        $monthLabelsJs = json_encode($monthLabels);
        $monthCountsJs = json_encode($monthCounts);

        $propStatusLabels = json_encode(['Em Análise', 'Aprovadas', 'Rejeitadas']);
        $propStatusVals   = json_encode([$propAnal, $propAprov, $propRej]);
        $propStatusColors = json_encode(['#fbbf24', '#34d399', '#f87171']);

        return <<<HTML
<style>
  .crm-dash-title { font-size:22px; font-weight:800; color:#0f172a; margin-bottom:4px; }
  .crm-dash-sub { font-size:13px; color:#64748b; margin-bottom:16px; }
  .crm-section-title { font-size:14px; font-weight:700; color:#1e40af; text-transform:uppercase; letter-spacing:.04em; border-bottom:2px solid #bfdbfe; padding-bottom:5px; margin:16px 0 12px; }
  .crm-kpi-grid { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
  .crm-kpi { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; box-shadow:0 2px 8px rgba(15,23,42,.06); }
  .crm-kpi .label { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
  .crm-kpi .value { font-size:24px; font-weight:700; color:#0f172a; margin-top:3px; }
  .crm-kpi .sub { font-size:11px; color:#94a3b8; margin-top:2px; }
  .crm-charts { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
  .crm-charts-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; margin-bottom:14px; }
  .crm-chart-box { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; box-shadow:0 2px 8px rgba(15,23,42,.06); }
  .crm-chart-box h4 { margin:0 0 10px; font-size:13px; font-weight:600; color:#0f172a; }
  @media(max-width:1300px){ .crm-kpi-grid{grid-template-columns:repeat(3,1fr);} .crm-charts-3{grid-template-columns:1fr 1fr;} }
  @media(max-width:900px){ .crm-kpi-grid{grid-template-columns:repeat(2,1fr);} .crm-charts,.crm-charts-3{grid-template-columns:1fr;} }
</style>

<div>
  <div class="crm-dash-title">📊 Dashboard CRM</div>
  <div class="crm-dash-sub">Visão consolidada de Oportunidades e Propostas</div>

  {$alertas}

  <div class="crm-section-title">🎯 Oportunidades no Funil</div>
  <div class="crm-kpi-grid">
    <div class="crm-kpi"><div class="label">Total Leads</div><div class="value">{$totalOpps}</div><div class="sub">No funil ativo</div></div>
    <div class="crm-kpi"><div class="label">Valor Funil</div><div class="value" style="font-size:18px;color:#1d4ed8">{$totalValFmt}</div><div class="sub">Potencial total</div></div>
    <div class="crm-kpi"><div class="label">Ativos</div><div class="value" style="color:#2563eb">{$active}</div><div class="sub">Em andamento</div></div>
    <div class="crm-kpi"><div class="label">Fechados</div><div class="value" style="color:#15803d">{$won}</div><div class="sub">{$wonValFmt}</div></div>
    <div class="crm-kpi"><div class="label">Tx. Conversão</div><div class="value" style="color:#7c3aed">{$taxaConv}%</div><div class="sub">Lead → Fechado</div></div>
  </div>

  <div class="crm-charts-3">
    <div class="crm-chart-box"><h4>Funil por Etapa</h4><canvas id="dash-funnel-chart" height="200"></canvas></div>
    <div class="crm-chart-box"><h4>Distribuição por Prioridade</h4><canvas id="dash-prio-chart" height="200"></canvas></div>
    <div class="crm-chart-box"><h4>Origem dos Leads</h4><canvas id="dash-origem-chart" height="200"></canvas></div>
  </div>

  <div class="crm-section-title">📋 Propostas Comerciais</div>
  <div class="crm-kpi-grid">
    <div class="crm-kpi"><div class="label">Total Propostas</div><div class="value">{$totalProps}</div><div class="sub">Geradas</div></div>
    <div class="crm-kpi"><div class="label">Aprovadas</div><div class="value" style="color:#15803d">{$propAprov}</div><div class="sub">Tx: {$txAprovProp}%</div></div>
    <div class="crm-kpi"><div class="label">Em Análise</div><div class="value" style="color:#b45309">{$propAnal}</div><div class="sub">Aguardando</div></div>
    <div class="crm-kpi"><div class="label">Faturamento</div><div class="value" style="font-size:16px;color:#1d4ed8">{$propFatFmt}</div><div class="sub">Aprovadas</div></div>
    <div class="crm-kpi"><div class="label">Resultado</div><div class="value" style="font-size:16px;color:{$propResColor}">{$propResFmt}</div><div class="sub">Lucro estimado</div></div>
  </div>

  <div class="crm-charts">
    <div class="crm-chart-box"><h4>Status das Propostas</h4><canvas id="dash-prop-status-chart" height="200"></canvas></div>
    <div class="crm-chart-box"><h4>Propostas por Mês (últimos 6 meses)</h4><canvas id="dash-prop-month-chart" height="200"></canvas></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function(){
  if(typeof Chart === 'undefined') return;

  var charts = ['dash-funnel-chart','dash-prio-chart','dash-origem-chart','dash-prop-status-chart','dash-prop-month-chart'];
  charts.forEach(function(id){
    var el = document.getElementById(id);
    if(el && el._chart) el._chart.destroy();
  });

  function mkChart(id, type, labels, data, colors, opts) {
    var el = document.getElementById(id);
    if(!el) return;
    var c = new Chart(el, {
      type: type,
      data: { labels: labels, datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff', borderRadius: type==='bar'?6:0 }] },
      options: Object.assign({ responsive: true, plugins: { legend: { position: 'bottom' } } }, opts||{})
    });
    el._chart = c;
    return c;
  }

  mkChart('dash-funnel-chart', 'doughnut', {$statusLabels}, {$statusVals}, {$statusColors});
  mkChart('dash-prio-chart', 'pie', {$prioLabels}, {$prioVals}, {$prioColors});
  mkChart('dash-origem-chart', 'bar', {$origemLabels}, {$origemVals},
    Array({$origemLabels}.length).fill('#3b82f6'),
    {plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}
  );
  mkChart('dash-prop-status-chart', 'doughnut', {$propStatusLabels}, {$propStatusVals}, {$propStatusColors});

  var pmEl = document.getElementById('dash-prop-month-chart');
  if(pmEl) {
    if(pmEl._chart) pmEl._chart.destroy();
    pmEl._chart = new Chart(pmEl, {
      type: 'bar',
      data: { labels: {$monthLabelsJs}, datasets: [{ label: 'Propostas', data: {$monthCountsJs}, backgroundColor: '#6366f1', borderRadius: 6 }] },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
  }
})();
</script>
HTML;
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }
        parent::show();
    }
}
