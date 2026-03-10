<?php
/**
 * Dashboard Operacional - Transportadora Rodoviária Internacional
 *
 * Exibe KPIs, gráficos e status em tempo real das operações.
 */

use Adianti\Control\TPage;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Container\THBox;
use Adianti\Widget\Util\TBreadCrumb;
use Adianti\Database\TTransaction;

class Dashboard extends TPage
{
    private static $database = 'sample';

    public function __construct($param = [])
    {
        parent::__construct();

        $box = new TVBox;
        $box->style = 'width: 100%';
        $box->add(TBreadCrumb::create(['Dashboard', 'Operacional']));

        // ── Coleta de dados ──────────────────────────────────────────────
        TTransaction::open(self::$database);
        $conn = TTransaction::get();

        $hoje   = date('Y-m-d');
        $mes_ini = date('Y-m-01');
        $mes_fim = date('Y-m-t');

        // KPI: CRTs emitidos no mês
        $crts_mes = (int)$conn->query(
            "SELECT COUNT(*) FROM conhecimento
             WHERE data_transportador_assinatura BETWEEN '$mes_ini' AND '$mes_fim'"
        )->fetchColumn();

        // KPI: CRTs emitidos hoje
        $crts_hoje = (int)$conn->query(
            "SELECT COUNT(*) FROM conhecimento
             WHERE data_transportador_assinatura = '$hoje'"
        )->fetchColumn();

        // KPI: Total de CRTs ativos (não concluídos)
        $crts_ativos = (int)$conn->query(
            "SELECT COUNT(*) FROM conhecimento c
             INNER JOIN status_crt s ON s.id = c.status_crt_id
             WHERE (s.status_final IS NULL OR s.status_final = '0')
               AND (s.deleted_at IS NULL OR s.deleted_at = '')"
        )->fetchColumn();

        // KPI: Faturas em aberto (sem data de pagamento)
        $faturas_abertas = (int)$conn->query(
            "SELECT COUNT(*) FROM fatura
             WHERE (pagamento IS NULL OR pagamento = '')"
        )->fetchColumn();

        // KPI: Contratos não pagos
        $contratos_nao_pagos = (int)$conn->query(
            "SELECT COUNT(*) FROM contrato
             WHERE (pago IS NULL OR pago = '' OR pago = '0')"
        )->fetchColumn();

        // KPI: Valor total fretes do mês (contratos)
        $frete_mes = (float)$conn->query(
            "SELECT COALESCE(SUM(CAST(frete1 AS REAL)), 0) FROM contrato
             WHERE emissao BETWEEN '$mes_ini' AND '$mes_fim'"
        )->fetchColumn();

        // KPI: Saldo a pagar motoristas (contratos não pagos)
        $saldo_motoristas = (float)$conn->query(
            "SELECT COALESCE(SUM(CAST(saldo1 AS REAL)), 0) FROM contrato
             WHERE (pago IS NULL OR pago = '' OR pago = '0')"
        )->fetchColumn();

        // KPI: Faturas a vencer nos próximos 7 dias
        $prox7 = date('Y-m-d', strtotime('+7 days'));
        $faturas_vencendo = (int)$conn->query(
            "SELECT COUNT(*) FROM fatura
             WHERE (pagamento IS NULL OR pagamento = '')
               AND vencimento BETWEEN '$hoje' AND '$prox7'"
        )->fetchColumn();

        // Gráfico: CRTs por status
        $status_rows = $conn->query(
            "SELECT s.nome, s.cor, COUNT(c.id) as total
             FROM status_crt s
             LEFT JOIN conhecimento c ON c.status_crt_id = s.id
             WHERE (s.deleted_at IS NULL OR s.deleted_at = '')
             GROUP BY s.id, s.nome, s.cor
             ORDER BY s.ordem"
        )->fetchAll(\PDO::FETCH_ASSOC);

        // Gráfico: CRTs por dia (últimos 30 dias)
        $dt30 = date('Y-m-d', strtotime('-29 days'));
        $crts_diario = $conn->query(
            "SELECT data_transportador_assinatura as dia, COUNT(*) as total
             FROM conhecimento
             WHERE data_transportador_assinatura >= '$dt30'
             GROUP BY data_transportador_assinatura
             ORDER BY data_transportador_assinatura"
        )->fetchAll(\PDO::FETCH_ASSOC);

        // Gráfico: Frete mensal (últimos 6 meses)
        $frete_mensal = $conn->query(
            "SELECT substr(emissao,1,7) as mes,
                    COALESCE(SUM(CAST(frete1 AS REAL)), 0) as total
             FROM contrato
             WHERE emissao >= date('now','-5 months','start of month')
             GROUP BY mes
             ORDER BY mes"
        )->fetchAll(\PDO::FETCH_ASSOC);

        // Tabela: Últimos 8 CRTs
        $ultimos_crts = $conn->query(
            "SELECT c.id, c.numero, c.data_transportador_assinatura,
                    s.nome as status_nome, s.cor as status_cor,
                    cl.nome as remetente
             FROM conhecimento c
             LEFT JOIN status_crt s ON s.id = c.status_crt_id
             LEFT JOIN clientes cl  ON cl.id = c.remetente_id
             ORDER BY c.id DESC LIMIT 8"
        )->fetchAll(\PDO::FETCH_ASSOC);

        // Tabela: Contratos não pagos mais antigos
        $contratos_pendentes = $conn->query(
            "SELECT ct.id, ct.conhecimento_numero, ct.emissao, ct.vencimento,
                    ct.saldo1, ct.frete1,
                    m.nome as motorista
             FROM contrato ct
             LEFT JOIN veiculo v   ON v.id = ct.veiculo_id
             LEFT JOIN motorista m ON m.id = v.motorista_id
             WHERE (ct.pago IS NULL OR ct.pago = '' OR ct.pago = '0')
             ORDER BY ct.vencimento ASC LIMIT 6"
        )->fetchAll(\PDO::FETCH_ASSOC);

        // Tabela: Faturas vencendo em 7 dias
        $faturas_alerta = $conn->query(
            "SELECT f.id, f.numero_fatura, f.numero_crt,
                    f.vencimento, f.valor_fatura,
                    cl.nome as cliente
             FROM fatura f
             LEFT JOIN clientes cl ON cl.id = f.pessoa_id
             WHERE (f.pagamento IS NULL OR f.pagamento = '')
               AND f.vencimento BETWEEN '$hoje' AND '$prox7'
             ORDER BY f.vencimento ASC LIMIT 6"
        )->fetchAll(\PDO::FETCH_ASSOC);

        TTransaction::close();

        // ── Serializa dados para JS ──────────────────────────────────────
        $status_labels  = json_encode(array_column($status_rows, 'nome'));
        $status_totais  = json_encode(array_map('intval', array_column($status_rows, 'total')));
        $status_cores   = json_encode(array_column($status_rows, 'cor'));

        // Preenche dias sem CRT com zero (últimos 30 dias)
        $mapa_diario = [];
        foreach ($crts_diario as $row) {
            $mapa_diario[$row['dia']] = (int)$row['total'];
        }
        $dias_labels = [];
        $dias_vals   = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $dias_labels[] = date('d/m', strtotime($d));
            $dias_vals[]   = $mapa_diario[$d] ?? 0;
        }
        $dias_labels_js = json_encode($dias_labels);
        $dias_vals_js   = json_encode($dias_vals);

        $mapa_mensal = [];
        foreach ($frete_mensal as $row) {
            $mapa_mensal[$row['mes']] = round((float)$row['total'], 2);
        }
        $meses_labels = [];
        $meses_vals   = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = date('Y-m', strtotime("-$i months"));
            $meses_labels[] = date('m/Y', strtotime("$m-01"));
            $meses_vals[]   = $mapa_mensal[$m] ?? 0;
        }
        $meses_labels_js = json_encode($meses_labels);
        $meses_vals_js   = json_encode($meses_vals);

        // ── HTML / CSS ───────────────────────────────────────────────────
        $frete_mes_fmt       = 'R$ ' . number_format($frete_mes, 2, ',', '.');
        $saldo_motor_fmt     = 'R$ ' . number_format($saldo_motoristas, 2, ',', '.');
        $alerta_venc_class   = $faturas_vencendo > 0 ? 'bg-warning' : 'bg-success';
        $contratos_pend_class = $contratos_nao_pagos > 0 ? 'bg-danger' : 'bg-success';

        // Linhas da tabela de últimos CRTs
        $rows_crts = '';
        foreach ($ultimos_crts as $r) {
            $dt  = $r['data_transportador_assinatura']
                 ? date('d/m/Y', strtotime($r['data_transportador_assinatura']))
                 : '-';
            $cor = htmlspecialchars($r['status_cor'] ?? '#aaa');
            $st  = htmlspecialchars($r['status_nome'] ?? '-');
            $rem = htmlspecialchars($r['remetente'] ?? '-');
            $num = htmlspecialchars($r['numero'] ?? '-');
            $rows_crts .= "<tr>
                <td>{$r['id']}</td>
                <td><strong>{$num}</strong></td>
                <td>{$dt}</td>
                <td><span style='color:{$cor};font-weight:600;'>{$st}</span></td>
                <td>{$rem}</td>
                <td>
                    <a href='?class=ConhecimentoForm&method=onEdit&key={$r['id']}' class='btn btn-xs btn-primary py-0 px-1'>
                        <i class='fa fa-edit'></i>
                    </a>
                </td>
            </tr>";
        }

        // Linhas da tabela de contratos pendentes
        $rows_contratos = '';
        foreach ($contratos_pendentes as $r) {
            $emissao  = $r['emissao']   ? date('d/m/Y', strtotime($r['emissao']))   : '-';
            $venc     = $r['vencimento']? date('d/m/Y', strtotime($r['vencimento'])): '-';
            $saldo    = 'R$ ' . number_format((float)$r['saldo1'], 2, ',', '.');
            $venc_class = ($r['vencimento'] && $r['vencimento'] < $hoje) ? 'text-danger fw-bold' : '';
            $rows_contratos .= "<tr>
                <td>{$r['id']}</td>
                <td>" . htmlspecialchars($r['conhecimento_numero'] ?? '-') . "</td>
                <td>" . htmlspecialchars($r['motorista'] ?? '-') . "</td>
                <td>{$emissao}</td>
                <td class='{$venc_class}'>{$venc}</td>
                <td class='text-end'>{$saldo}</td>
                <td>
                    <a href='?class=ContratoForm&method=onEdit&key={$r['id']}' class='btn btn-xs btn-success py-0 px-1'>
                        <i class='fa fa-check'></i>
                    </a>
                </td>
            </tr>";
        }

        // Linhas da tabela de faturas com alerta
        $rows_faturas = '';
        foreach ($faturas_alerta as $r) {
            $venc  = $r['vencimento'] ? date('d/m/Y', strtotime($r['vencimento'])) : '-';
            $valor = 'R$ ' . number_format((float)$r['valor_fatura'], 2, ',', '.');
            $venc_class = ($r['vencimento'] && $r['vencimento'] < $hoje) ? 'text-danger fw-bold' : 'text-warning fw-bold';
            $rows_faturas .= "<tr>
                <td>" . htmlspecialchars($r['numero_fatura'] ?? $r['id']) . "</td>
                <td>" . htmlspecialchars($r['numero_crt'] ?? '-') . "</td>
                <td>" . htmlspecialchars($r['cliente'] ?? '-') . "</td>
                <td class='{$venc_class}'>{$venc}</td>
                <td class='text-end'>{$valor}</td>
                <td>
                    <a href='?class=FaturaForm&method=onEdit&key={$r['id']}' class='btn btn-xs btn-warning py-0 px-1'>
                        <i class='fa fa-edit'></i>
                    </a>
                </td>
            </tr>";
        }

        if (empty($rows_crts)) {
            $rows_crts = '<tr><td colspan="6" class="text-center text-muted">Nenhum CRT encontrado</td></tr>';
        }
        if (empty($rows_contratos)) {
            $rows_contratos = '<tr><td colspan="7" class="text-center text-muted">Sem contratos pendentes</td></tr>';
        }
        if (empty($rows_faturas)) {
            $rows_faturas = '<tr><td colspan="6" class="text-center text-muted">Nenhuma fatura vencendo em breve</td></tr>';
        }

        $html = <<<HTML
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  .dash-kpi { border-radius: 12px; padding: 18px 22px; color: #fff; box-shadow: 0 3px 10px rgba(0,0,0,.15); transition: transform .15s; }
  .dash-kpi:hover { transform: translateY(-3px); }
  .dash-kpi .kpi-title { font-size: .82rem; opacity: .85; text-transform: uppercase; letter-spacing: .05em; }
  .dash-kpi .kpi-value { font-size: 2.1rem; font-weight: 700; line-height: 1.1; }
  .dash-kpi .kpi-sub   { font-size: .78rem; opacity: .75; margin-top: 4px; }
  .dash-kpi .kpi-icon  { font-size: 2.4rem; opacity: .3; float: right; margin-top: -8px; }
  .dash-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.08); padding: 20px; margin-bottom: 20px; }
  .dash-card h5 { font-size: .95rem; font-weight: 700; color: #344; margin-bottom: 14px; border-bottom: 2px solid #f0f0f0; padding-bottom: 8px; }
  .table-dash { font-size: .82rem; }
  .table-dash th { background: #f8f9fa; font-weight: 600; }
  .section-title { font-size: 1.05rem; font-weight: 700; color: #2c4a6e; margin: 22px 0 12px; border-left: 4px solid #2c4a6e; padding-left: 10px; }
  .kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
  .chart-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 24px; }
  .tables-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
  .tables-grid-3 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  @media(max-width: 992px) {
    .chart-grid { grid-template-columns: 1fr; }
    .tables-grid, .tables-grid-3 { grid-template-columns: 1fr; }
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  }
</style>

<!-- ── KPIs ─────────────────────────────────────────────────────────── -->
<p class="section-title"><i class="bi bi-speedometer2"></i> Indicadores Operacionais</p>
<div class="kpi-grid">

  <div class="dash-kpi" style="background:linear-gradient(135deg,#1d3a6e,#2563eb)">
    <i class="bi bi-file-earmark-text kpi-icon"></i>
    <div class="kpi-title">CRTs no Mês</div>
    <div class="kpi-value">$crts_mes</div>
    <div class="kpi-sub">Hoje: <strong>$crts_hoje</strong></div>
  </div>

  <div class="dash-kpi" style="background:linear-gradient(135deg,#065f46,#10b981)">
    <i class="bi bi-arrow-repeat kpi-icon"></i>
    <div class="kpi-title">CRTs Ativos</div>
    <div class="kpi-value">$crts_ativos</div>
    <div class="kpi-sub">Em andamento</div>
  </div>

  <div class="dash-kpi" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)">
    <i class="bi bi-cash-stack kpi-icon"></i>
    <div class="kpi-title">Frete Mês</div>
    <div class="kpi-value" style="font-size:1.4rem;">$frete_mes_fmt</div>
    <div class="kpi-sub">Contratos emitidos</div>
  </div>

  <div class="dash-kpi $contratos_pend_class" style="background:linear-gradient(135deg,#b91c1c,#ef4444)">
    <i class="bi bi-people kpi-icon"></i>
    <div class="kpi-title">Saldo Motoristas</div>
    <div class="kpi-value" style="font-size:1.35rem;">$saldo_motor_fmt</div>
    <div class="kpi-sub">$contratos_nao_pagos contrato(s) pendente(s)</div>
  </div>

  <div class="dash-kpi" style="background:linear-gradient(135deg,#92400e,#f59e0b)">
    <i class="bi bi-receipt kpi-icon"></i>
    <div class="kpi-title">Faturas em Aberto</div>
    <div class="kpi-value">$faturas_abertas</div>
    <div class="kpi-sub">Aguardando pagamento</div>
  </div>

  <div class="dash-kpi $alerta_venc_class" style="background:linear-gradient(135deg,#be185d,#f472b6)">
    <i class="bi bi-alarm kpi-icon"></i>
    <div class="kpi-title">Faturas Vencendo</div>
    <div class="kpi-value">$faturas_vencendo</div>
    <div class="kpi-sub">Próximos 7 dias</div>
  </div>

</div>

<!-- ── Ações Rápidas ─────────────────────────────────────────────────── -->
<p class="section-title"><i class="bi bi-lightning-charge"></i> Ações Rápidas</p>
<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px;">
  <a href="?class=ConhecimentoList" class="btn btn-primary btn-sm">
    <i class="bi bi-file-earmark-plus me-1"></i> Novo CRT
  </a>
  <a href="?class=ContratoList" class="btn btn-success btn-sm">
    <i class="bi bi-pen me-1"></i> Contratos
  </a>
  <a href="?class=FaturaList" class="btn btn-warning btn-sm">
    <i class="bi bi-receipt me-1"></i> Faturas
  </a>
  <a href="?class=AcompProcessoKanban" class="btn btn-info btn-sm text-white">
    <i class="bi bi-kanban me-1"></i> Kanban Processos
  </a>
  <a href="?class=VeiculoList" class="btn btn-secondary btn-sm">
    <i class="bi bi-truck me-1"></i> Veículos
  </a>
  <a href="?class=MotoristaList" class="btn btn-dark btn-sm">
    <i class="bi bi-person-badge me-1"></i> Motoristas
  </a>
  <a href="?class=EnlastreList" class="btn btn-outline-primary btn-sm">
    <i class="bi bi-layers me-1"></i> Enlastres
  </a>
</div>

<!-- ── Gráficos ──────────────────────────────────────────────────────── -->
<p class="section-title"><i class="bi bi-bar-chart-line"></i> Análise Gráfica</p>
<div class="chart-grid">

  <div class="dash-card" style="grid-column: span 2;">
    <h5><i class="bi bi-graph-up me-1"></i> CRTs Emitidos — Últimos 30 dias</h5>
    <canvas id="chartDiario" height="80"></canvas>
  </div>

  <div class="dash-card">
    <h5><i class="bi bi-pie-chart me-1"></i> Distribuição por Status</h5>
    <canvas id="chartStatus" height="200"></canvas>
  </div>

</div>

<div class="dash-card">
  <h5><i class="bi bi-currency-dollar me-1"></i> Frete Total por Mês (últimos 6 meses)</h5>
  <canvas id="chartFreteMensal" height="60"></canvas>
</div>

<!-- ── Tabelas ───────────────────────────────────────────────────────── -->
<p class="section-title"><i class="bi bi-table"></i> Monitoramento Operacional</p>
<div class="tables-grid">

  <div class="dash-card">
    <h5><i class="bi bi-file-text me-1"></i> Últimos CRTs</h5>
    <div style="overflow-x:auto">
      <table class="table table-sm table-hover table-dash">
        <thead>
          <tr><th>ID</th><th>Número</th><th>Data</th><th>Status</th><th>Remetente</th><th></th></tr>
        </thead>
        <tbody>$rows_crts</tbody>
      </table>
    </div>
    <a href="?class=ConhecimentoList" class="btn btn-sm btn-outline-primary w-100 mt-1">Ver todos os CRTs →</a>
  </div>

  <div class="dash-card">
    <h5><i class="bi bi-exclamation-triangle text-warning me-1"></i> Faturas Vencendo em 7 dias</h5>
    <div style="overflow-x:auto">
      <table class="table table-sm table-hover table-dash">
        <thead>
          <tr><th>Fatura</th><th>CRT</th><th>Cliente</th><th>Vencimento</th><th>Valor</th><th></th></tr>
        </thead>
        <tbody>$rows_faturas</tbody>
      </table>
    </div>
    <a href="?class=FaturaList" class="btn btn-sm btn-outline-warning w-100 mt-1">Ver todas as faturas →</a>
  </div>

</div>

<div class="dash-card">
  <h5><i class="bi bi-truck me-1"></i> Contratos de Frete Pendentes de Pagamento</h5>
  <div style="overflow-x:auto">
    <table class="table table-sm table-hover table-dash">
      <thead>
        <tr><th>ID</th><th>CRT</th><th>Motorista</th><th>Emissão</th><th>Vencimento</th><th class="text-end">Saldo</th><th></th></tr>
      </thead>
      <tbody>$rows_contratos</tbody>
    </table>
  </div>
  <a href="?class=ContratoList" class="btn btn-sm btn-outline-success w-100 mt-1">Ver todos os contratos →</a>
</div>

<!-- ── Scripts ───────────────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    Chart.defaults.font.family = "'Segoe UI', sans-serif";
    Chart.defaults.font.size   = 12;

    // Cores padrão da transportadora
    const blue = '#2563eb', green = '#10b981', amber = '#f59e0b', red = '#ef4444';

    // Gráfico: CRTs diários
    new Chart(document.getElementById('chartDiario'), {
        type: 'bar',
        data: {
            labels: $dias_labels_js,
            datasets: [{
                label: 'CRTs emitidos',
                data: $dias_vals_js,
                backgroundColor: blue + 'cc',
                borderColor: blue,
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Gráfico: Status (donut)
    var statusLabels = $status_labels;
    var statusTotais = $status_totais;
    var statusCores  = $status_cores;
    new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusTotais,
                backgroundColor: statusCores.map(function(c){ return c || '#adb5bd'; }),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } }
            }
        }
    });

    // Gráfico: Frete mensal (barras)
    new Chart(document.getElementById('chartFreteMensal'), {
        type: 'bar',
        data: {
            labels: $meses_labels_js,
            datasets: [{
                label: 'Frete Total (R$)',
                data: $meses_vals_js,
                backgroundColor: green + 'cc',
                borderColor: green,
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(v){ return 'R$ ' + v.toLocaleString('pt-BR'); }
                    }
                }
            }
        }
    });
})();
</script>
HTML;

        $box->add($html);
        parent::add($box);
    }
}
