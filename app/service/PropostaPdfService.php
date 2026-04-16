<?php
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;

class PropostaPdfService
{
    public static function gerarPDF(array $data)
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new Exception("Dompdf não instalado. Rode: composer require dompdf/dompdf");
        }

        $cot = $data['Cotacao_ID'] ?? '(sem nº)';

        $data = self::enrichRouteData($data);
        $html = self::template($data);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdf = $dompdf->output();

        $file = "app/output/Proposta_{$cot}.pdf";
        file_put_contents($file, $pdf);

        $msg = "<div style='text-align:center'>
                  <a class='btn btn-danger btn-lg' target='_blank' href='{$file}'>
                    <i class='far fa-file-pdf'></i> Abrir PDF
                  </a>
                </div>";
        new TMessage('info', $msg);
    }

    private static function template(array $d)
    {
        // helper
        $v = fn($k) => htmlspecialchars((string)($d[$k] ?? ''), ENT_QUOTES, 'UTF-8');

        return "
        <html><head>
            <meta charset='utf-8'>
            <style>
                body{ font-family: DejaVu Sans, Arial; font-size:12px; }
                .h1{ font-size:18px; font-weight:bold; margin-bottom:8px; }
                .box{ border:1px solid #ddd; padding:10px; margin-bottom:10px; border-radius:6px; }
                table{ width:100%; border-collapse:collapse; }
                td{ padding:6px; border-bottom:1px solid #eee; vertical-align:top; }
                .k{ color:#666; width:30%; }
                .tot{ font-size:14px; font-weight:bold; }
            </style>
        </head><body>
            <div class='h1'>PROPOSTA DE FRETE INTERNACIONAL</div>

            <div class='box'>
                <table>
                    <tr><td class='k'>Cotação</td><td>{$v('Cotacao_ID')}</td></tr>
                    <tr><td class='k'>Cliente</td><td>{$v('Cliente_Embarcador')}</td></tr>
                    <tr><td class='k'>Situação</td><td>{$v('Situacao')}</td></tr>
                    <tr><td class='k'>Emissão / Validade</td><td>{$v('Data_Cotacao')} - {$v('Data_Validade_Cotacao')}</td></tr>
                </table>
            </div>

            <div class='box'>
                <table>
                    <tr><td class='k'>Mercadoria</td><td>{$v('Descricao_Mercadoria')}</td></tr>
                    <tr><td class='k'>FOB (USD)</td><td>{$v('FOB_Mercadoria_Valor')}</td></tr>
                    <tr><td class='k'>Origem</td><td>{$v('Local_Coleta')}</td></tr>
                    <tr><td class='k'>Destino</td><td>{$v('Local_Entrega')}</td></tr>
                    <tr><td class='k'>Aduana</td><td>{$v('Aduana_Fronteira')}</td></tr>
                    <tr><td class='k'>Equipamento</td><td>{$v('Tipo_Equipamento')}</td></tr>
                    <tr><td class='k'>Transit Time</td><td>{$v('Tempo_Transito')}</td></tr>
                </table>
            </div>

            <div class='box'>
                <div class='tot'>Resumo Financeiro</div>
                <table>
                    <tr><td class='k'>Custos Totais</td><td>R$ {$v('Custo_Total_Operacao_Valor')}</td></tr>
                    <tr><td class='k'>Faturamento</td><td>R$ {$v('Faturamento_Valor_1')}</td></tr>
                    <tr><td class='k'>MBL (FAT - Custos)</td><td>R$ {$v('MBL_Valor')}</td></tr>
                    <tr><td class='k'>Fat. Líquido</td><td>R$ {$v('fat_liquido_reais')}</td></tr>
                    <tr><td class='k'>Resultado Final</td><td>R$ {$v('resultado_final')}</td></tr>
                    <tr><td class='k'>Margem %</td><td>{$v('margem_percentual')}%</td></tr>
                </table>
            </div>

        </body></html>";
    }

    private static function enrichRouteData(array $data): array
    {
        $freteOrigemId = (int) ($data['frete_origem_id'] ?? 0);
        $freteDestinoId = (int) ($data['frete_destino_id'] ?? 0);

        if ($freteOrigemId <= 0 && $freteDestinoId <= 0) {
            return $data;
        }

        $rota1Destino = '';

        try {
            TTransaction::open('sample');

            if ($freteOrigemId > 0) {
                $freteOrigem = new TabelaFrete($freteOrigemId);
                if (!empty($freteOrigem->id)) {
                    $rota1Destino = trim((string) ($freteOrigem->destino ?? ''));
                    $data['Local_Coleta'] = self::firstFilled([
                        $freteOrigem->origem ?? '',
                        $data['Local_Coleta'] ?? '',
                    ]);
                    $data['Aduana_Fronteira'] = self::firstFilled([
                        $freteOrigem->fronteira ?? '',
                        $rota1Destino,
                        $data['Aduana_Fronteira'] ?? '',
                    ]);
                    $data['frete_origem'] = $freteOrigem->valor_frete ?? ($data['frete_origem'] ?? null);
                }
            }

            if ($freteDestinoId > 0) {
                $freteDestino = new TabelaFrete($freteDestinoId);
                if (!empty($freteDestino->id)) {
                    $data['Local_Entrega'] = self::firstFilled([
                        $freteDestino->destino ?? '',
                        $data['Local_Entrega'] ?? '',
                        $rota1Destino,
                    ]);
                    $data['frete_destino'] = $freteDestino->valor_frete ?? ($data['frete_destino'] ?? null);
                }
            } elseif ($rota1Destino !== '') {
                $data['Local_Entrega'] = self::firstFilled([
                    $data['Local_Entrega'] ?? '',
                    $rota1Destino,
                ]);
            }

            TTransaction::close();
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $rollbackException) {
            }
        }

        return $data;
    }

    private static function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
