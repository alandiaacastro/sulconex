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
                <td><span class='badge' style='background-color:{$cor}'>{$st}</span></td>
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
<!-- ── KPIs ─────────────────────────────────────────────────────────── -->
<p class="border-start border-4 border-primary ps-2 fw-bold mb-3 mt-2"><i class="bi bi-speedometer2"></i> Indicadores Operacionais</p>
<div class="row row-cols-2 row-cols-md-3 g-3 mb-4">

  <div class="col">
    <div class="card h-100 bg-primary text-white border-0 shadow-sm">
      <div class="card-body">
        <i class="bi bi-file-earmark-text fs-1 opacity-25 float-end mt-n1"></i>
        <div class="text-uppercase small opacity-75">CRTs no Mês</div>
        <div class="fs-2 fw-bold lh-1">$crts_mes</div>
        <div class="small opacity-75 mt-1">Hoje: <strong>$crts_hoje</strong></div>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card h-100 bg-success text-white border-0 shadow-sm">
      <div class="card-body">
        <i class="bi bi-arrow-repeat fs-1 opacity-25 float-end mt-n1"></i>
        <div class="text-uppercase small opacity-75">CRTs Ativos</div>
        <div class="fs-2 fw-bold lh-1">$crts_ativos</div>
        <div class="small opacity-75 mt-1">Em andamento</div>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card h-100 bg-secondary text-white border-0 shadow-sm">
      <div class="card-body">
        <i class="bi bi-cash-stack fs-1 opacity-25 float-end mt-n1"></i>
        <div class="text-uppercase small opacity-75">Frete Mês</div>
        <div class="fs-4 fw-bold lh-1">$frete_mes_fmt</div>
        <div class="small opacity-75 mt-1">Contratos emitidos</div>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card h-100 bg-danger text-white border-0 shadow-sm">
      <div class="card-body">
        <i class="bi bi-people fs-1 opacity-25 float-end mt-n1"></i>
        <div class="text-uppercase small opacity-75">Saldo Motoristas</div>
        <div class="fs-4 fw-bold lh-1">$saldo_motor_fmt</div>
        <div class="small opacity-75 mt-1">$contratos_nao_pagos contrato(s) pendente(s)</div>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card h-100 bg-warning text-dark border-0 shadow-sm">
      <div class="card-body">
        <i class="bi bi-receipt fs-1 opacity-25 float-end mt-n1"></i>
        <div class="text-uppercase small opacity-75">Faturas em Aberto</div>
        <div class="fs-2 fw-bold lh-1">$faturas_abertas</div>
        <div class="small opacity-75 mt-1">Aguardando pagamento</div>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card h-100 bg-danger text-white border-0 shadow-sm">
      <div class="card-body">
        <i class="bi bi-alarm fs-1 opacity-25 float-end mt-n1"></i>
        <div class="text-uppercase small opacity-75">Faturas Vencendo</div>
        <div class="fs-2 fw-bold lh-1">$faturas_vencendo</div>
        <div class="small opacity-75 mt-1">Próximos 7 dias</div>
      </div>
    </div>
  </div>

</div>

<!-- ── Ações Rápidas ─────────────────────────────────────────────────── -->
<p class="border-start border-4 border-primary ps-2 fw-bold mb-3"><i class="bi bi-lightning-charge"></i> Ações Rápidas</p>
<div class="d-flex flex-wrap gap-2 mb-4">
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
<p class="border-start border-4 border-primary ps-2 fw-bold mb-3"><i class="bi bi-bar-chart-line"></i> Análise Gráfica</p>
<div class="row g-3 mb-4">

  <div class="col-md-8">
    <div class="card shadow-sm">
      <div class="card-header fw-bold"><i class="bi bi-graph-up me-1"></i> CRTs Emitidos — Últimos 30 dias</div>
      <div class="card-body">
        <canvas id="chartDiario" height="80"></canvas>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-header fw-bold"><i class="bi bi-pie-chart me-1"></i> Distribuição por Status</div>
      <div class="card-body">
        <canvas id="chartStatus" height="200"></canvas>
      </div>
    </div>
  </div>

</div>

<div class="card shadow-sm mb-4">
  <div class="card-header fw-bold"><i class="bi bi-currency-dollar me-1"></i> Frete Total por Mês (últimos 6 meses)</div>
  <div class="card-body">
    <canvas id="chartFreteMensal" height="60"></canvas>
  </div>
</div>

<!-- ── Tabelas ───────────────────────────────────────────────────────── -->
<p class="border-start border-4 border-primary ps-2 fw-bold mb-3"><i class="bi bi-table"></i> Monitoramento Operacional</p>
<div class="row g-3 mb-4">

  <div class="col-md-6">
    <div class="card shadow-sm h-100">
      <div class="card-header fw-bold"><i class="bi bi-file-text me-1"></i> Últimos CRTs</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr><th>ID</th><th>Número</th><th>Data</th><th>Status</th><th>Remetente</th><th></th></tr>
            </thead>
            <tbody>$rows_crts</tbody>
          </table>
        </div>
      </div>
      <div class="card-footer p-1">
        <a href="?class=ConhecimentoList" class="btn btn-sm btn-outline-primary w-100">Ver todos os CRTs →</a>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card shadow-sm h-100">
      <div class="card-header fw-bold"><i class="bi bi-exclamation-triangle text-warning me-1"></i> Faturas Vencendo em 7 dias</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr><th>Fatura</th><th>CRT</th><th>Cliente</th><th>Vencimento</th><th>Valor</th><th></th></tr>
            </thead>
            <tbody>$rows_faturas</tbody>
          </table>
        </div>
      </div>
      <div class="card-footer p-1">
        <a href="?class=FaturaList" class="btn btn-sm btn-outline-warning w-100">Ver todas as faturas →</a>
      </div>
    </div>
  </div>

</div>

<div class="card shadow-sm mb-4">
  <div class="card-header fw-bold"><i class="bi bi-truck me-1"></i> Contratos de Frete Pendentes de Pagamento</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr><th>ID</th><th>CRT</th><th>Motorista</th><th>Emissão</th><th>Vencimento</th><th class="text-end">Saldo</th><th></th></tr>
        </thead>
        <tbody>$rows_contratos</tbody>
      </table>
    </div>
  </div>
  <div class="card-footer p-1">
    <a href="?class=ContratoList" class="btn btn-sm btn-outline-success w-100">Ver todos os contratos →</a>
  </div>
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
