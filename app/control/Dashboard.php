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
        $box->add(self::renderQuickAccessSection());

        // ── Coleta de dados ──────────────────────────────────────────────
        TTransaction::open(self::$database);
        $conn = TTransaction::get();

        $hoje   = date('Y-m-d');

        // KPI: Faturas a vencer nos proximos 7 dias
        $prox7 = date('Y-m-d', strtotime('+7 days'));

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

        // ── HTML / CSS ───────────────────────────────────────────────────

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
                    <a href='index.php?class=ConhecimentoForm&method=onEdit&key={$r['id']}' class='btn btn-xs btn-primary py-0 px-1'>
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
                    <a href='index.php?class=ContratoForm&method=onEdit&key={$r['id']}' class='btn btn-xs btn-success py-0 px-1'>
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
                    <a href='index.php?class=FaturaForm&method=onEdit&key={$r['id']}' class='btn btn-xs btn-warning py-0 px-1'>
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
        <a href="index.php?class=ConhecimentoList" class="btn btn-sm btn-outline-primary w-100">Ver todos os CRTs →</a>
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
        <a href="index.php?class=FaturaList" class="btn btn-sm btn-outline-warning w-100">Ver todas as faturas →</a>
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
    <a href="index.php?class=ContratoList" class="btn btn-sm btn-outline-success w-100">Ver todos os contratos →</a>
  </div>
</div>

HTML;

        $box->add($html);

        // ── KPIs Portal Motorista ────────────────────────────────────────
        try {
            TTransaction::open(self::$database);
            $conn2 = TTransaction::get();

            // Verifica se tabelas existem
            $tblCheck = $conn2->query("SELECT name FROM sqlite_master WHERE type='table' AND name='carga_disponivel'")->fetchColumn();
            if ($tblCheck) {
                $cargasDisp = (int) $conn2->query("SELECT COUNT(*) FROM carga_disponivel WHERE status='disponivel'")->fetchColumn();
                $tblSolic = $conn2->query("SELECT name FROM sqlite_master WHERE type='table' AND name='solicitacao_carga'")->fetchColumn();
                $solicPend = 0;
                $aprovMes = 0;
                if ($tblSolic) {
                    $solicPend = (int) $conn2->query("SELECT COUNT(*) FROM solicitacao_carga WHERE status='pendente'")->fetchColumn();
                    $primDiaMes = date('Y-m-01');
                    $stmtAprov = $conn2->prepare("SELECT COUNT(*) FROM solicitacao_carga WHERE status='aprovado' AND created_at >= :primDia");
                    $stmtAprov->execute([':primDia' => $primDiaMes]);
                    $aprovMes = (int) $stmtAprov->fetchColumn();
                }
                $totalMotoristas = (int) $conn2->query("SELECT COUNT(*) FROM motorista")->fetchColumn();

                $alertSolic = $solicPend > 5 ? ' text-danger fw-bold' : '';

                $kpiHtml = <<<KPIHTML
<p class="border-start border-4 border-success ps-2 fw-bold mb-3"><i class="bi bi-truck"></i> Portal Motorista</p>
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card shadow-sm text-center">
      <div class="card-body py-3">
        <div style="font-size:2rem;font-weight:700;color:#198754">{$cargasDisp}</div>
        <div class="text-muted" style="font-size:.85rem">Cargas Disponiveis</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm text-center">
      <div class="card-body py-3">
        <div style="font-size:2rem;font-weight:700;color:#fd7e14" class="{$alertSolic}">{$solicPend}</div>
        <div class="text-muted" style="font-size:.85rem">Solicitacoes Pendentes</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm text-center">
      <div class="card-body py-3">
        <div style="font-size:2rem;font-weight:700;color:#0d6efd">{$aprovMes}</div>
        <div class="text-muted" style="font-size:.85rem">Aprovadas no Mes</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm text-center">
      <div class="card-body py-3">
        <div style="font-size:2rem;font-weight:700;color:#6c757d">{$totalMotoristas}</div>
        <div class="text-muted" style="font-size:.85rem">Motoristas Cadastrados</div>
      </div>
    </div>
  </div>
</div>
KPIHTML;

                $box->add($kpiHtml);
            }

            TTransaction::close();
        } catch (Exception $e) {
            // Portal Motorista tables may not exist yet — skip KPIs silently
        }

        parent::add($box);
    }

    private static function renderQuickAccessSection(): string
    {
        $grupos = [
            'Opcoes' => [
                ['Conhecimento(CRT)', 'index.php?class=ConhecimentoList', 'fa fa-file-text'],
                ['Tracking Processos', 'index.php?class=AcompProcessoKanban', 'fa fa-sitemap'],
                ['Controle de Estoque', 'index.php?class=EstoqueView', 'fa fa-archive'],
                ['Caixa', 'index.php?class=CaixaList', 'fa fa-briefcase'],
            ],
            'Acoes' => [
                ['Motorista', 'index.php?class=MotoristaList', 'fa fa-id-card'],
                ['Clientes', 'index.php?class=ClientesList', 'fa fa-users'],
                ['Contratos', 'index.php?class=ContratoList', 'fa fa-file-text'],
                ['Faturas', 'index.php?class=FaturaList', 'fa fa-file-invoice-dollar'],
            ],
            'Favoritos' => [
                ['Faturas', 'index.php?class=FaturaList', 'fa fa-credit-card'],
                ['Consulta ANTT', 'index.php?class=ANTTForm', 'fa fa-balance-scale'],
                ['Tabela de Fretes', 'index.php?class=TabelaFreteList', 'fa fa-road'],
                ['Veiculo - Cadastro', 'index.php?class=VeiculoList', 'fa fa-truck'],
            ],
        ];

        $cols = '';
        foreach ($grupos as $titulo => $itens) {
            $cards = '';
            foreach ($itens as $item) {
                $label = htmlspecialchars($item[0]);
                $href  = htmlspecialchars($item[1]);
                $icon  = htmlspecialchars($item[2]);
                $cards .= "
                    <a class='dash-quick-card' href='{$href}' generator='adianti'>
                        <i class='{$icon}'></i>
                        <span>{$label}</span>
                    </a>";
            }

            $cols .= "
                <div class='dash-quick-col'>
                    <div class='dash-quick-title'>{$titulo}</div>
                    <div class='dash-quick-list'>{$cards}</div>
                </div>";
        }

        return <<<HTML
<style>
  .dash-quick-wrap { background:#e9edf1; border:1px solid #d5dbe2; border-radius:10px; padding:12px; margin:10px 0 14px 0; }
  .dash-quick-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
  .dash-quick-col { padding-right:12px; border-right:1px solid #cfd5dc; }
  .dash-quick-col:last-child { border-right:0; padding-right:0; }
  .dash-quick-title { font-size:13px; line-height:1; font-weight:700; color:#5c6672; letter-spacing:.04em; text-transform:uppercase; margin:2px 0 10px 4px; }
  .dash-quick-list { display:grid; gap:8px; }
  .dash-quick-card { display:flex; align-items:center; gap:14px; text-decoration:none; border:1px solid #c3c9d2; background:#dde3e9; border-radius:5px; padding:12px 14px; color:#2f343a; font-weight:700; transition:.15s ease; min-height:68px; }
  .dash-quick-card i { color:#ef7f2d; width:44px; text-align:center; font-size:34px; line-height:1; }
  .dash-quick-card span { font-size:18px; }
  .dash-quick-card:hover { background:#d6dde4; border-color:#b7bec9; color:#1f2937; }
  @media (max-width:1200px){ .dash-quick-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:1200px){ .dash-quick-col { border-right:0; padding-right:0; } }
  @media (max-width:760px){ .dash-quick-grid { grid-template-columns:1fr; } .dash-quick-card span { font-size:18px; } .dash-quick-card i { font-size:28px; width:36px; } }
</style>
<div class="dash-quick-wrap">
  <div class="dash-quick-grid">
    {$cols}
  </div>
</div>
HTML;
    }
}


