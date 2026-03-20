<?php

use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TDate;

class ChequeRelatorio extends TPage
{
    public static function onGeneratePendentes($param): void
    {
        try {
            TTransaction::open('sample');

            $filterData = TSession::getValue('ChequeList_filter_data');
            $reportData = self::buildReportData($filterData);

            TTransaction::close();
            self::renderHtml($reportData);
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $rollbackException) {
            }
            new TMessage('error', $e->getMessage());
        }
    }

    private static function buildReportData($filterData): array
    {
        $criteria = new TCriteria;
        $criteria->setProperty('order', 'data_vencimento, id');
        $criteria->setProperty('direction', 'asc');
        $vencDe = null;
        $vencAte = null;

        // Sempre pendentes neste relatorio.
        $criteria->add(new TFilter('status', '=', 'PENDENTE'));

        if (!empty($filterData->id)) {
            $criteria->add(new TFilter('id', '=', (int) $filterData->id));
        }

        if (!empty($filterData->numero_cheque)) {
            $criteria->add(new TFilter('numero_cheque', 'like', '%' . $filterData->numero_cheque . '%'));
        }

        if (!empty($filterData->recebedor)) {
            $criteria->add(new TFilter('recebedor', 'like', '%' . $filterData->recebedor . '%'));
        }

        if (!empty($filterData->venc_de)) {
            $vencDe = TDate::convertToMask($filterData->venc_de, 'dd/mm/yyyy', 'yyyy-mm-dd');
            $criteria->add(new TFilter('data_vencimento', '>=', $vencDe));
        }

        if (!empty($filterData->venc_ate)) {
            $vencAte = TDate::convertToMask($filterData->venc_ate, 'dd/mm/yyyy', 'yyyy-mm-dd');
            $criteria->add(new TFilter('data_vencimento', '<=', $vencAte));
        }

        $repo = new TRepository('Cheque');
        $items = $repo->load($criteria, false) ?? [];

        $rows = [];
        $total = 0.0;

        foreach ($items as $item) {
            $valor = (float) ($item->valor ?? 0);
            $total += $valor;

            $rows[] = [
                'id' => (int) ($item->id ?? 0),
                'numero' => (string) ($item->numero_cheque ?? ''),
                'recebedor' => (string) ($item->recebedor ?? ''),
                'vencimento' => self::toBrDate((string) ($item->data_vencimento ?? '')),
                'valor' => $valor,
                'status' => (string) ($item->status ?? ''),
            ];
        }

        return [
            'gerado_em' => date('d/m/Y H:i'),
            'quantidade' => count($rows),
            'total' => $total,
            'saldo_periodo' => $total,
            'periodo_de' => $vencDe ? self::toBrDate($vencDe) : null,
            'periodo_ate' => $vencAte ? self::toBrDate($vencAte) : null,
            'rows' => $rows,
        ];
    }

    private static function renderHtml(array $reportData): void
    {
        if (!is_dir('tmp')) {
            mkdir('tmp', 0777, true);
        }

        $rowsHtml = '';
        foreach ($reportData['rows'] as $row) {
            $rowsHtml .= '<tr>'
                . '<td>' . self::esc((string) $row['id']) . '</td>'
                . '<td>' . self::esc((string) $row['numero']) . '</td>'
                . '<td>' . self::esc((string) $row['recebedor']) . '</td>'
                . '<td>' . self::esc((string) $row['vencimento']) . '</td>'
                . '<td class="num">R$ ' . self::fmtMoney((float) $row['valor']) . '</td>'
                . '<td><span class="badge">PENDENTE</span></td>'
                . '</tr>';
        }

        if (empty($reportData['rows'])) {
            $rowsHtml = '<tr><td colspan="6" class="empty">Nenhum cheque pendente encontrado.</td></tr>';
        }

        $totalFmt = self::fmtMoney((float) $reportData['total']);
        $saldoPeriodoFmt = self::fmtMoney((float) ($reportData['saldo_periodo'] ?? 0));
        $periodoLabel = 'Todos os vencimentos';
        if (!empty($reportData['periodo_de']) || !empty($reportData['periodo_ate'])) {
            $de = $reportData['periodo_de'] ?? '--';
            $ate = $reportData['periodo_ate'] ?? '--';
            $periodoLabel = $de . ' ate ' . $ate;
        }

        $html = <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Relatorio de Cheques Pendentes</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; margin: 14px; color: #1f2937; }
    h1 { font-size: 24px; margin: 0 0 8px 0; }
    .meta { margin-bottom: 14px; color: #475569; font-size: 13px; }
    .totais { margin-bottom: 14px; font-size: 14px; }
    table { width: 100%; border-collapse: collapse; }
    thead th { text-align: left; font-size: 13px; border-bottom: 2px solid #cbd5e1; padding: 8px 6px; }
    tbody td { font-size: 12px; border-bottom: 1px solid #e2e8f0; padding: 7px 6px; }
    .num { text-align: right; white-space: nowrap; }
    .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; background:#f59e0b; color:#111827; }
    .empty { text-align:center; color:#64748b; padding:22px 6px; }
    @media print { body { margin: 6mm; } }
  </style>
</head>
<body>
  <h1>Relatorio de Cheques Pendentes</h1>
  <div class="meta">Gerado em {$reportData['gerado_em']}</div>
  <div class="meta"><b>Periodo:</b> {$periodoLabel}</div>
  <div class="totais"><b>Quantidade:</b> {$reportData['quantidade']} &nbsp;&nbsp; <b>Total:</b> R$ {$totalFmt} &nbsp;&nbsp; <b>Saldo no periodo:</b> R$ {$saldoPeriodoFmt}</div>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Numero</th>
        <th>Recebedor</th>
        <th>Vencimento</th>
        <th class="num">Valor</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      {$rowsHtml}
    </tbody>
  </table>
  <script>
    window.onload = function() {
      if (window.location.search.indexOf('print=1') !== -1) {
        window.print();
      }
    };
  </script>
</body>
</html>
HTML;

        $file = 'tmp/relatorio_cheques_pendentes_' . date('Ymd_His') . '.html';
        file_put_contents($file, $html);
        self::openFile($file);
    }

    private static function fmtMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private static function toBrDate(?string $date): string
    {
        if (empty($date)) {
            return '-';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return TDate::convertToMask($date, 'yyyy-mm-dd', 'dd/mm/yyyy');
        }

        return $date;
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
