<?php

use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TDate;

/**
 * CaixaRelatorio
 * Relatorio de extrato de caixa em HTML.
 */
class CaixaRelatorio extends TPage
{
    public static function onGenerate($param): void
    {
        try {
            TTransaction::open('sample');

            $filterData = TSession::getValue('CaixaList_filter_data');
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
        $criteria->setProperty('order', 'data_lancamento, id');
        $criteria->setProperty('direction', 'asc');

        $startDate = null;
        $endDate = null;

        if (!empty($filterData->data_de)) {
            $startDate = TDate::convertToMask($filterData->data_de, 'dd/mm/yyyy', 'yyyy-mm-dd');
            $criteria->add(new TFilter('data_lancamento', '>=', $startDate));
        }

        if (!empty($filterData->data_ate)) {
            $endDate = TDate::convertToMask($filterData->data_ate, 'dd/mm/yyyy', 'yyyy-mm-dd');
            $criteria->add(new TFilter('data_lancamento', '<=', $endDate));
        }

        if (!empty($filterData->tipo)) {
            $criteria->add(new TFilter('tipo', '=', $filterData->tipo));
        }

        if (!empty($filterData->categoria)) {
            $criteria->add(new TFilter('categoria', '=', $filterData->categoria));
        }

        if (!empty($filterData->status)) {
            $criteria->add(new TFilter('status', '=', $filterData->status));
        }

        $repo = new TRepository('Caixa');
        $items = $repo->load($criteria, false) ?? [];

        if (empty($startDate) && !empty($items)) {
            $startDate = $items[0]->data_lancamento ?? null;
        }
        if (empty($endDate) && !empty($items)) {
            $lastItem = end($items);
            $endDate = $lastItem->data_lancamento ?? null;
        }

        $openingBalance = self::calculateOpeningBalance($filterData, $startDate);

        $rows = [];
        $running = $openingBalance;

        foreach ($items as $item) {
            $signed = self::signedValue((float) $item->valor, (string) $item->tipo);
            $running += $signed;

            [$razao, $documento] = self::resolvePartyData($item);

            $rows[] = [
                'data'        => self::toBrDate($item->data_lancamento),
                'lancamento'  => (string) ($item->descricao ?? ''),
                'razao'       => $razao,
                'documento'   => $documento,
                'valor'       => (float) $item->valor,
                'tipo'        => (string) ($item->tipo ?? ''),
                'saldo'       => $running,
            ];
        }

        $closingBalance = $running;
        $rowsDesc = array_reverse($rows);

        return [
            'periodo_de' => self::toBrDate($startDate),
            'periodo_ate' => self::toBrDate($endDate),
            'rows' => $rowsDesc,
            'closing_balance' => $closingBalance,
            'closing_date' => self::toBrDate($endDate ?: date('Y-m-d')),
        ];
    }

    private static function calculateOpeningBalance($filterData, ?string $startDate): float
    {
        if (empty($startDate)) {
            return 0.0;
        }

        $criteria = new TCriteria;
        $criteria->add(new TFilter('data_lancamento', '<', $startDate));
        $criteria->setProperty('order', 'data_lancamento, id');
        $criteria->setProperty('direction', 'asc');

        if (!empty($filterData->tipo)) {
            $criteria->add(new TFilter('tipo', '=', $filterData->tipo));
        }
        if (!empty($filterData->categoria)) {
            $criteria->add(new TFilter('categoria', '=', $filterData->categoria));
        }
        if (!empty($filterData->status)) {
            $criteria->add(new TFilter('status', '=', $filterData->status));
        }

        $repo = new TRepository('Caixa');
        $previousItems = $repo->load($criteria, false) ?? [];

        $opening = 0.0;
        foreach ($previousItems as $item) {
            $opening += self::signedValue((float) $item->valor, (string) $item->tipo);
        }

        return $opening;
    }

    private static function signedValue(float $value, string $tipo): float
    {
        return $tipo === 'ENTRADA' ? abs($value) : -abs($value);
    }

    private static function resolvePartyData($caixa): array
    {
        $razao = '';
        $documento = '';

        try {
            if ($caixa->referencia_tipo === 'fatura' && !empty($caixa->referencia_id)) {
                $fatura = new Fatura($caixa->referencia_id);
                $cliente = $fatura->clientekey ?? null;
                $razao = (string) ($cliente->nome ?? '');
                $documento = (string) ($cliente->cnpj ?? '');
            } elseif ($caixa->referencia_tipo === 'contrato' && !empty($caixa->referencia_id)) {
                $contrato = new Contrato($caixa->referencia_id);
                $permisso = $contrato->get_permisso();
                $razao = (string) ($permisso->transportadora ?? '');
                $documento = (string) ($permisso->cnpj ?? '');
            }
        } catch (Exception $e) {
        }

        if (empty($documento)) {
            $source = (string) ($caixa->descricao ?? '') . ' ' . (string) ($caixa->observacao ?? '');
            if (preg_match('/\b(\d{2}\.?\d{3}\.?\d{3}\/?\d{4}\-?\d{2}|\d{3}\.?\d{3}\.?\d{3}\-?\d{2})\b/', $source, $m)) {
                $documento = $m[1];
            }
        }

        return [trim($razao), trim($documento)];
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

    private static function fmtMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function renderHtml(array $reportData): void
    {
        if (!is_dir('tmp')) {
            mkdir('tmp', 0777, true);
        }

        $linhas = '';

        $linhas .= '<tr class="saldo-row">'
                 . '<td>' . self::esc($reportData['closing_date']) . '</td>'
                 . '<td>SALDO EM CONTA CORRENTE</td>'
                 . '<td></td>'
                 . '<td></td>'
                 . '<td class="num"></td>'
                 . '<td class="num">' . self::fmtMoney((float) $reportData['closing_balance']) . '</td>'
                 . '</tr>';

        foreach ($reportData['rows'] as $row) {
            $valorClass = $row['tipo'] === 'ENTRADA' ? 'valor-entrada' : 'valor-saida';
            $valor = ($row['tipo'] === 'ENTRADA' ? '' : '-') . self::fmtMoney((float) $row['valor']);

            $linhas .= '<tr>'
                     . '<td>' . self::esc($row['data']) . '</td>'
                     . '<td>' . self::esc($row['lancamento']) . '</td>'
                     . '<td>' . self::esc($row['razao']) . '</td>'
                     . '<td>' . self::esc($row['documento']) . '</td>'
                     . '<td class="num ' . $valorClass . '">' . $valor . '</td>'
                     . '<td class="num">' . self::fmtMoney((float) $row['saldo']) . '</td>'
                     . '</tr>';
        }

        if (empty($reportData['rows'])) {
            $linhas .= '<tr><td colspan="6" class="vazio">Nenhum lancamento encontrado para o periodo informado.</td></tr>';
        }

        $periodo = self::esc($reportData['periodo_de']) . ' ate ' . self::esc($reportData['periodo_ate']);

        $html = <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Relatorio de Caixa</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; margin: 14px; color: #1f2937; }
    .periodo { font-size: 24px; font-weight: 700; margin: 6px 0 18px 0; }
    .periodo small { font-size: 24px; font-weight: 400; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    thead th { text-align: left; font-size: 23px; border-bottom: 2px solid #b8c0cc; padding: 8px 6px; }
    tbody td { font-size: 22px; border-bottom: 1px solid #c7cfda; padding: 8px 6px; vertical-align: middle; }
    .num { text-align: right; white-space: nowrap; }
    .valor-entrada { color: #16823b; font-weight: 600; }
    .valor-saida { color: #c3312f; font-weight: 600; }
    .saldo-row td { font-weight: 600; background: #f4f6f8; }
    .vazio { text-align: center; color: #6b7280; padding: 24px 8px; }
    .col-data { width: 10%; }
    .col-lanc { width: 28%; }
    .col-razao { width: 27%; }
    .col-doc { width: 20%; }
    .col-valor { width: 8%; }
    .col-saldo { width: 7%; }
    @media print { body { margin: 6mm; } }
  </style>
</head>
<body>
  <div class="periodo">Lancamentos do periodo: <small>{$periodo}</small></div>
  <table>
    <thead>
      <tr>
        <th class="col-data">Data</th>
        <th class="col-lanc">Lancamentos</th>
        <th class="col-razao">Razao Social</th>
        <th class="col-doc">CNPJ/CPF</th>
        <th class="col-valor num">Valor (R$)</th>
        <th class="col-saldo num">Saldo (R$)</th>
      </tr>
    </thead>
    <tbody>
      {$linhas}
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

        $file = 'tmp/relatorio_caixa_' . date('Ymd_His') . '.html';
        file_put_contents($file, $html);
        self::openFile($file);
    }
}
