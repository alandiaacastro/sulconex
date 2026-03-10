<?php

use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;

/**
 * PropostaRelatorio
 * Gera PDF da proposta com FPDF
 * Compatível com Adianti Framework 8.1+ / PHP 8.3+
 */
class PropostaRelatorio extends TPage
{
    private static string $database = 'sample';

    public function __construct($param = null)
    {
        parent::__construct();
    }

    public static function onImprimir($param): void
    {
        $id = $param['key'] ?? null;

        if (empty($id)) {
            new TMessage('error', 'Nenhuma proposta selecionada para imprimir.');
            return;
        }

        try {
            self::loadFpdf();

            TTransaction::open(self::$database);

            $p = new Proposta($id);

            if (!empty($p->cliente_id)) {
                try {
                    new Clientes($p->cliente_id);
                } catch (Exception $e) {
                }
            }

            TTransaction::close();

            $cotacao = (string) ($p->Cotacao_ID ?? $p->id);

            if (!is_dir('tmp')) {
                mkdir('tmp', 0777, true);
            }

            $safe    = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $cotacao);
            $arquivo = 'tmp/Proposta_' . ($safe ?: $id) . '.pdf';

            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->SetMargins(8, 8, 8);
            $pdf->AddPage();

            self::renderCabecalho($pdf, $cotacao);

            $y = 30;

            self::renderLogistica($pdf, $p, $y);
            self::renderCustosOperacionais($pdf, $p, $y);
            self::renderAnaliseFinanceira($pdf, $p, $y);

            self::renderRodape($pdf, $cotacao);

            $pdf->Output('F', $arquivo);
            TPage::openFile($arquivo);
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $ee) {
            }

            new TMessage('error', $e->getMessage());
        }
    }

    private static function renderCabecalho(FPDF $pdf, string $cotacao): void
    {
        $pdf->SetFillColor(26, 58, 92);
        $pdf->Rect(0, 0, 210, 25, 'F');

        $pdf->SetFillColor(46, 109, 164);
        $pdf->Rect(0, 24, 210, 3, 'F');

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(8, 5);
        $pdf->Cell(150, 5, self::toLatin1('CALCULO PROPOSTA DE FRETE INTERNACIONAL'), 0, 0, 'L');

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(200, 221, 240);
        $pdf->SetXY(8, 13);
        $pdf->Cell(150, 4, self::toLatin1('SULCONEX LOGÍSTICA INTERNACIONAL'), 0, 0, 'L');

        $pdf->SetFillColor(46, 109, 164);
        $pdf->Rect(160, 3, 45, 18, 'F');

        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetTextColor(200, 221, 240);
        $pdf->SetXY(162, 5);
        $pdf->Cell(40, 3, self::toLatin1('N. COTAÃ‡ÃƒO'), 0, 0, 'L');

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(162, 10);
        $pdf->Cell(40, 5, self::toLatin1($cotacao), 0, 0, 'L');
    }

    private static function renderSecao(FPDF $pdf, float &$y, string $titulo): void
    {
        $pdf->SetFillColor(26, 58, 92);
        $pdf->SetDrawColor(46, 109, 164);
        $pdf->Rect(8, $y, 194, 5, 'DF');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetXY(11, $y + 0.5);
        $pdf->Cell(190, 4, self::toLatin1($titulo), 0, 0, 'L');

        $y += 6;
    }

    private static function renderLogistica(FPDF $pdf, Proposta $p, float &$y): void
    {
        self::renderSecao($pdf, $y, 'LOGÍSTICA');

        self::renderLogisticaLinhaDupla(
            $pdf,
            $y,
            'Mercadoria',
            (string) ($p->Descricao_Mercadoria ?? ''),
            'FOB (USD)',
            self::fmtUsd($p->FOB_Mercadoria_Valor ?? 0)
        );

        self::renderLogisticaLinhaDupla(
            $pdf,
            $y,
            'Origem',
            (string) ($p->Local_Coleta ?? ''),
            'Aduana / Fronteira',
            (string) ($p->Aduana_Fronteira ?? '')
        );

        self::renderLogisticaLinhaDupla(
            $pdf,
            $y,
            'Destino',
            (string) ($p->Local_Entrega ?? ''),
            'Equipamento',
            (string) ($p->Tipo_Equipamento ?? '')
        );

        self::renderLogisticaLinhaSimples(
            $pdf,
            $y,
            '',
            '',
            'Transit Time',
            (string) ($p->Tempo_Transito ?? '')
        );

        $y += 2;
    }

    private static function renderLogisticaLinhaDupla(
        FPDF $pdf,
        float &$y,
        string $labelEsq,
        string $valorEsq,
        string $labelDir,
        string $valorDir
    ): void {
        $pdf->SetTextColor(113, 128, 150);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetXY(10, $y);
        $pdf->Cell(42, 4, self::toLatin1(mb_strtoupper($labelEsq)), 0, 0, 'L');

        $pdf->SetTextColor(26, 32, 44);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetXY(54, $y - 0.1);
        $pdf->Cell(48, 4.5, self::toLatin1($valorEsq), 0, 0, 'L');

        $pdf->SetTextColor(113, 128, 150);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetXY(108, $y);
        $pdf->Cell(42, 4, self::toLatin1(mb_strtoupper($labelDir)), 0, 0, 'L');

        $pdf->SetTextColor(26, 32, 44);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetXY(152, $y - 0.1);
        $pdf->Cell(45, 4.5, self::toLatin1($valorDir), 0, 0, 'L');

        $y += 7;
    }

    private static function renderLogisticaLinhaSimples(
        FPDF $pdf,
        float &$y,
        string $labelEsq,
        string $valorEsq,
        string $labelDir = '',
        string $valorDir = ''
    ): void {
        if ($labelEsq !== '') {
            $pdf->SetTextColor(113, 128, 150);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetXY(10, $y);
            $pdf->Cell(42, 4, self::toLatin1(mb_strtoupper($labelEsq)), 0, 0, 'L');

            $pdf->SetTextColor(26, 32, 44);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetXY(54, $y - 0.1);
            $pdf->Cell(48, 4.5, self::toLatin1($valorEsq), 0, 0, 'L');
        }

        if ($labelDir !== '') {
            $pdf->SetTextColor(113, 128, 150);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetXY(108, $y);
            $pdf->Cell(42, 4, self::toLatin1(mb_strtoupper($labelDir)), 0, 0, 'L');

            $pdf->SetTextColor(26, 32, 44);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetXY(152, $y - 0.1);
            $pdf->Cell(45, 4.5, self::toLatin1($valorDir), 0, 0, 'L');
        }

        $y += 6;
    }

    private static function renderCustosOperacionais(FPDF $pdf, Proposta $p, float &$y): void
    {
        self::renderSecao($pdf, $y, 'CUSTOS OPERACIONAIS');

        $pdf->SetFillColor(30, 58, 95);
        $pdf->Rect(8, $y, 194, 7, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetXY(10, $y + 1.5);
        $pdf->Cell(130, 4, self::toLatin1('DESCRIÃ‡ÃƒO'), 0, 0, 'L');
        $pdf->SetXY(150, $y + 1.5);
        $pdf->Cell(50, 4, self::toLatin1('VALOR (R$)'), 0, 0, 'R');
        $y += 7;

        $custos = [
            'Frete Origem'           => $p->frete_origem ?? 0,
            'Frete Destino'          => $p->frete_destino ?? 0,
            'Enlonamento'            => $p->enlonamento ?? 0,
            'Estadia Multilog'       => $p->estadia_multilog ?? 0,
            'Repres. Multilog'       => $p->repres_multilog ?? 0,
            'Repres. Uruguaiana'     => $p->repres_uruguaiana ?? 0,
            'Repres. Libres'         => $p->repres_libres ?? 0,
            'Repres. Uspallata'      => $p->repres_uspallata ?? 0,
            'Repres. Chile'          => $p->repres_chile ?? 0,
            'Armazenagem Transbordo' => $p->armazenagem_transbordo ?? 0,
            'Comissão de Venda'      => $p->comissao_venda ?? 0,
            'Gerenciadora de Risco'  => $p->gerenciadora_risco ?? 0,
        ];

        $alt = false;
        foreach ($custos as $desc => $val) {
            if ((float) $val == 0.0) {
                continue;
            }

            if ($y > 270) {
                $pdf->AddPage();
                $y = 20;
            }

            if ($alt) {
                $pdf->SetFillColor(248, 250, 252);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }

            $pdf->Rect(8, $y, 194, 6.5, 'F');

            $pdf->SetDrawColor(226, 232, 240);
            $pdf->SetLineWidth(0.2);
            $pdf->Line(8, $y + 6.5, 202, $y + 6.5);

            $pdf->SetTextColor(55, 65, 81);
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetXY(10, $y + 1.3);
            $pdf->Cell(130, 4, self::toLatin1($desc), 0, 0, 'L');

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetXY(150, $y + 1.3);
            $pdf->Cell(50, 4, self::toLatin1(self::fmt($val)), 0, 0, 'R');

            $y += 6.5;
            $alt = !$alt;
        }

        if ($y > 275) {
            $pdf->AddPage();
            $y = 20;
        }

        $custo_total = (float) ($p->Custo_Total_Operacao_Valor ?? 0);

        $pdf->SetFillColor(255, 240, 240);
        $pdf->SetDrawColor(229, 62, 62);
        $pdf->Rect(8, $y, 194, 8, 'FD');

        $pdf->SetTextColor(197, 48, 48);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetXY(10, $y + 2);
        $pdf->Cell(130, 4, self::toLatin1('CUSTO TOTAL DA OPERAÃ‡ÃƒO'), 0, 0, 'L');

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetXY(150, $y + 2);
        $pdf->Cell(50, 4, self::toLatin1(self::fmt($custo_total)), 0, 0, 'R');

        $y += 12;
    }

    private static function renderAnaliseFinanceira(FPDF $pdf, Proposta $p, float &$y): void
    {
        if ($y > 220) {
            $pdf->AddPage();
            $y = 20;
        }

        self::renderSecao($pdf, $y, 'ANÁLISE FINANCEIRA');

        $pdf->SetFillColor(30, 58, 95);
        $pdf->Rect(8, $y, 194, 7, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetXY(10, $y + 1.5);
        $pdf->Cell(120, 4, self::toLatin1('DESCRIÃ‡ÃƒO'), 0, 0, 'L');
        $pdf->SetXY(145, $y + 1.5);
        $pdf->Cell(55, 4, self::toLatin1('VALOR'), 0, 0, 'R');
        $y += 7;

        $analise_financeira = [
            'Faturamento (R$)'  => self::fmt($p->Faturamento_Valor_1 ?? 0),
            'Taxa Dólar'        => number_format((float) ($p->Taxa_Dolar ?? 0), 4, ',', '.'),
            'Faturamento (USD)' => number_format((float) ($p->fat_dolar ?? 0), 2, ',', '.'),
            'Impostos %'        => number_format((float) ($p->Percentual_Impostos_FOB ?? 0), 2, ',', '.') . '%',
            'Vlr. Impostos'     => self::fmt($p->Impostos_Operacao_Valor ?? 0),
            'Swift %'           => number_format((float) ($p->taxa_swift ?? 0), 2, ',', '.') . '%',
            'Vlr. Swift'        => self::fmt($p->valor_swift ?? 0),
            'Seguro %'          => number_format((float) ($p->Percentual_Seguro_FOB ?? 0), 2, ',', '.') . '%',
            'Vlr. Seguro'       => self::fmt($p->valor_seguro ?? 0),
            'Fat. Líquido (R$)' => self::fmt($p->fat_liquido_reais ?? 0),
            'Resultado (USD)'   => self::fmtUsd($p->resultado_dolar ?? 0),
            'Margem %'          => number_format((float) ($p->margem_percentual ?? 0), 2, ',', '.') . '%',
        ];

        $alt = false;
        foreach ($analise_financeira as $desc => $val) {
            if ($y > 270) {
                $pdf->AddPage();
                $y = 20;

                self::renderSecao($pdf, $y, 'ANÁLISE FINANCEIRA');

                $pdf->SetFillColor(30, 58, 95);
                $pdf->Rect(8, $y, 194, 7, 'F');
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->SetXY(10, $y + 1.5);
                $pdf->Cell(120, 4, self::toLatin1('DESCRIÃ‡ÃƒO'), 0, 0, 'L');
                $pdf->SetXY(145, $y + 1.5);
                $pdf->Cell(55, 4, self::toLatin1('VALOR'), 0, 0, 'R');
                $y += 7;
            }

            if ($alt) {
                $pdf->SetFillColor(248, 250, 252);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }

            $pdf->Rect(8, $y, 194, 6.5, 'F');

            $pdf->SetDrawColor(226, 232, 240);
            $pdf->SetLineWidth(0.2);
            $pdf->Line(8, $y + 6.5, 202, $y + 6.5);

            $pdf->SetTextColor(55, 65, 81);
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetXY(10, $y + 1.3);
            $pdf->Cell(120, 4, self::toLatin1($desc), 0, 0, 'L');

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetXY(145, $y + 1.3);
            $pdf->Cell(55, 4, self::toLatin1($val), 0, 0, 'R');

            $y += 6.5;
            $alt = !$alt;
        }

        $margem_reais = (float) ($p->resultado_final ?? 0);

        if ($y > 275) {
            $pdf->AddPage();
            $y = 20;
        }

        $pdf->SetFillColor(240, 255, 244);
        $pdf->SetDrawColor(21, 128, 61);
        $pdf->Rect(8, $y, 194, 8, 'FD');

        $pdf->SetTextColor(21, 128, 61);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetXY(10, $y + 2);
        $pdf->Cell(120, 4, self::toLatin1('MARGEM (R$)'), 0, 0, 'L');

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetXY(145, $y + 2);
        $pdf->Cell(55, 4, self::toLatin1(self::fmt($margem_reais)), 0, 0, 'R');
    }

    private static function renderRodape(FPDF $pdf, string $cotacao): void
    {
        $pdf->SetTextColor(160, 174, 192);
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetXY(8, 285);
        $pdf->Cell(
            194,
            4,
            self::toLatin1('Gerado em ' . date('d/m/Y H:i') . ' | Proposta N. ' . $cotacao),
            0,
            0,
            'C'
        );
    }

    private static function loadFpdf(): void
    {
        if (class_exists('FPDF')) {
            return;
        }

        $paths = [
            'vendor/setasign/fpdf/fpdf.php',
            'lib/fpdf/fpdf.php',
            __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php',
            __DIR__ . '/../../../vendor/setasign/fpdf/fpdf.php',
            __DIR__ . '/../../lib/fpdf/fpdf.php',
            __DIR__ . '/../../../lib/fpdf/fpdf.php',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                break;
            }
        }

        if (!class_exists('FPDF')) {
            throw new Exception('FPDF não encontrado. Instale: composer require setasign/fpdf');
        }
    }

    private static function toLatin1(?string $text): string
    {
        $text = (string) $text;
        return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
    }

    private static function fmt(mixed $v, int $c = 2): string
    {
        return 'R$ ' . number_format((float) ($v ?? 0), $c, ',', '.');
    }

    private static function fmtUsd(mixed $v): string
    {
        return 'USD ' . number_format((float) ($v ?? 0), 2, ',', '.');
    }

    private static function fmtDateBr(?string $data): string
    {
        if (empty($data)) {
            return '';
        }

        $data = trim((string) $data);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $dt = DateTime::createFromFormat('Y-m-d', $data);
            return $dt ? $dt->format('d/m/Y') : $data;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $data)) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $data);
            return $dt ? $dt->format('d/m/Y') : $data;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $data)) {
            $dt = DateTime::createFromFormat('Y-m-d H:i', $data);
            return $dt ? $dt->format('d/m/Y') : $data;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data)) {
            return $data;
        }

        return $data;
    }
}