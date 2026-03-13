<?php

use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;

/**
 * PropostaRelatorio
 * Gera PDF da proposta com FPDF
 * Compativel com Adianti Framework 8.1+ / PHP 8.3+
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
            self::renderGraficoCustos($pdf, $p, $y);

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
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(8, 20, 202, 20);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetXY(8, 8);
        $pdf->Cell(150, 5, self::toLatin1('CALCULO PROPOSTA DE FRETE INTERNACIONAL'), 0, 0, 'L');

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetXY(8, 14);
        $pdf->Cell(150, 4, self::toLatin1('SULCONEX LOGISTICA INTERNACIONAL'), 0, 0, 'L');

        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetXY(162, 8);
        $pdf->Cell(40, 3, self::toLatin1('N. COTACAO'), 0, 0, 'L');

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetXY(162, 12);
        $pdf->Cell(40, 5, self::toLatin1($cotacao), 0, 0, 'L');
    }

    private static function renderSecao(FPDF $pdf, float &$y, string $titulo): void
    {
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->Line(8, $y + 4, 202, $y + 4);

        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetXY(8, $y);
        $pdf->Cell(190, 4, self::toLatin1($titulo), 0, 0, 'L');

        $y += 6;
    }

    private static function renderLogistica(FPDF $pdf, Proposta $p, float &$y): void
    {
        self::renderSecao($pdf, $y, 'LOGISTICA');

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
        $leftX = 10.0;
        $rightX = 108.0;
        $colW = 92.0;
        $labelH = 3.5;
        $valueH = 4.5;

        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetXY($leftX, $y);
        $pdf->Cell($colW, $labelH, self::toLatin1(mb_strtoupper($labelEsq)), 0, 0, 'L');
        $pdf->SetXY($rightX, $y);
        $pdf->Cell($colW, $labelH, self::toLatin1(mb_strtoupper($labelDir)), 0, 0, 'L');

        $pdf->SetTextColor(25, 25, 25);
        $pdf->SetFont('Arial', '', 8);

        $leftValue = self::truncateToWidth($pdf, $valorEsq, $colW - 2);
        $rightValue = self::truncateToWidth($pdf, $valorDir, $colW - 2);
        $rightAlign = stripos($labelDir, 'FOB') !== false || stripos($labelDir, 'USD') !== false;

        $pdf->SetXY($leftX, $y + $labelH);
        $pdf->Cell($colW, $valueH, self::toLatin1($leftValue), 0, 0, 'L');
        $pdf->SetXY($rightX, $y + $labelH);
        $pdf->Cell($colW, $valueH, self::toLatin1($rightValue), 0, 0, $rightAlign ? 'R' : 'L');

        $y += 8.0;
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
            $pdf->SetTextColor(90, 90, 90);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetXY(10, $y);
            $pdf->Cell(42, 4, self::toLatin1(mb_strtoupper($labelEsq)), 0, 0, 'L');

            $pdf->SetTextColor(25, 25, 25);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetXY(54, $y - 0.1);
            $pdf->Cell(48, 4.5, self::toLatin1($valorEsq), 0, 0, 'L');
        }

        if ($labelDir !== '') {
            $pdf->SetTextColor(90, 90, 90);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetXY(108, $y);
            $pdf->Cell(42, 4, self::toLatin1(mb_strtoupper($labelDir)), 0, 0, 'L');

            $pdf->SetTextColor(25, 25, 25);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetXY(152, $y - 0.1);
            $pdf->Cell(45, 4.5, self::toLatin1($valorDir), 0, 0, 'L');
        }

        $y += 6;
    }

    private static function getCustosOperacionais(Proposta $p): array
    {
        return [
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
            'Comissao de Venda'      => $p->comissao_venda ?? 0,
            'Gerenciadora de Risco'  => $p->gerenciadora_risco ?? 0,
            'Impostos'               => $p->Impostos_Operacao_Valor ?? 0,
            'Swift'                  => $p->valor_swift ?? 0,
            'Seguro'                 => $p->valor_seguro ?? 0,
        ];
    }

    private static function renderCustosOperacionais(FPDF $pdf, Proposta $p, float &$y): void
    {
        self::renderSecao($pdf, $y, 'CUSTOS OPERACIONAIS');

        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetXY(10, $y + 1.5);
        $pdf->Cell(130, 4, self::toLatin1('DESCRICAO'), 0, 0, 'L');
        $pdf->SetXY(150, $y + 1.5);
        $pdf->Cell(50, 4, self::toLatin1('VALOR (R$)'), 0, 0, 'R');
        $y += 7;

        $custos = self::getCustosOperacionais($p);

        foreach ($custos as $desc => $val) {
            // Impostos/Swift/Seguro devem aparecer no relatorio mesmo zerados
            $forceShow = in_array($desc, ['Impostos', 'Swift', 'Seguro'], true);
            if ((float) $val == 0.0 && !$forceShow) {
                continue;
            }

            if ($y > 270) {
                $pdf->AddPage();
                $y = 20;
            }

            $pdf->SetDrawColor(230, 230, 230);
            $pdf->SetLineWidth(0.2);
            $pdf->Line(8, $y + 6.5, 202, $y + 6.5);

            $pdf->SetTextColor(45, 45, 45);
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetXY(10, $y + 1.3);
            $pdf->Cell(130, 4, self::toLatin1($desc), 0, 0, 'L');

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetXY(150, $y + 1.3);
            $pdf->Cell(50, 4, self::toLatin1(self::fmt($val)), 0, 0, 'R');

            $y += 6.5;
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
        $pdf->Cell(130, 4, self::toLatin1('CUSTO TOTAL DA OPERACAO'), 0, 0, 'L');

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

        self::renderSecao($pdf, $y, 'ANALISE FINANCEIRA');

        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetXY(10, $y + 1.5);
        $pdf->Cell(120, 4, self::toLatin1('DESCRICAO'), 0, 0, 'L');
        $pdf->SetXY(145, $y + 1.5);
        $pdf->Cell(55, 4, self::toLatin1('VALOR'), 0, 0, 'R');
        $y += 7;

        $analise_financeira = [
            'Faturamento (R$)'  => self::fmt($p->Faturamento_Valor_1 ?? 0),
            'Taxa Dolar'        => number_format((float) ($p->Taxa_Dolar ?? 0), 4, ',', '.'),
            'Faturamento (USD)' => number_format((float) ($p->fat_dolar ?? 0), 2, ',', '.'),
            'Impostos %'        => number_format((float) ($p->Percentual_Impostos_FOB ?? 0), 2, ',', '.') . '%',
            'Vlr. Impostos'     => self::fmt($p->Impostos_Operacao_Valor ?? 0),
            'Swift %'           => number_format((float) ($p->taxa_swift ?? 0), 2, ',', '.') . '%',
            'Vlr. Swift'        => self::fmt($p->valor_swift ?? 0),
            'Seguro %'          => number_format((float) ($p->Percentual_Seguro_FOB ?? 0), 2, ',', '.') . '%',
            'Vlr. Seguro'       => self::fmt($p->valor_seguro ?? 0),
            'Fat. Liquido (R$)' => self::fmt($p->fat_liquido_reais ?? 0),
            'Resultado (USD)'   => self::fmtUsd($p->resultado_dolar ?? 0),
            'Margem %'          => number_format((float) ($p->margem_percentual ?? 0), 2, ',', '.') . '%',
        ];

        foreach ($analise_financeira as $desc => $val) {
            if ($y > 270) {
                $pdf->AddPage();
                $y = 20;

                self::renderSecao($pdf, $y, 'ANALISE FINANCEIRA');

                $pdf->SetTextColor(30, 30, 30);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->SetXY(10, $y + 1.5);
                $pdf->Cell(120, 4, self::toLatin1('DESCRICAO'), 0, 0, 'L');
                $pdf->SetXY(145, $y + 1.5);
                $pdf->Cell(55, 4, self::toLatin1('VALOR'), 0, 0, 'R');
                $y += 7;
            }

            $pdf->SetDrawColor(230, 230, 230);
            $pdf->SetLineWidth(0.2);
            $pdf->Line(8, $y + 6.5, 202, $y + 6.5);

            $pdf->SetTextColor(45, 45, 45);
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetXY(10, $y + 1.3);
            $pdf->Cell(120, 4, self::toLatin1($desc), 0, 0, 'L');

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetXY(145, $y + 1.3);
            $pdf->Cell(55, 4, self::toLatin1($val), 0, 0, 'R');

            $y += 6.5;
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

        $y += 12;
    }

    private static function renderGraficoCustos(FPDF $pdf, Proposta $p, float &$y): void
    {
        $custos = self::getCustosGrafico($p);
        $data = [];
        foreach ($custos as $label => $valor) {
            $v = (float) $valor;
            if ($v > 0) {
                $data[$label] = $v;
            }
        }

        if (empty($data)) {
            return;
        }

        // Pagina exclusiva para o grafico: donut em cima e legenda embaixo
        $pdf->AddPage();
        $y = 8;

        $imgPath = self::buildPieChart($data);
        if ($imgPath && file_exists($imgPath)) {
            $pdf->Image($imgPath, 8, $y, 194, 274);
            $y = 285;
        }
    }

    private static function getCustosGrafico(Proposta $p): array
    {
        $custos = self::getCustosOperacionais($p);

        // Complementa o donut com custos financeiros pedidos pelo usuario
        $custos['Impostos'] = (float) ($p->Impostos_Operacao_Valor ?? 0);
        $custos['Swift'] = (float) ($p->valor_swift ?? 0);
        $custos['Seguro'] = (float) ($p->valor_seguro ?? 0);

        return $custos;
    }

    private static function buildPieChart(array $data): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        arsort($data);

        $w = 1700;
        $h = 2400;
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 0);
        imagefill($img, 0, 0, $bg);
        if (function_exists('imageantialias')) {
            imageantialias($img, true);
        }

        $colors = [
            [44, 123, 182],
            [235, 120, 72],
            [102, 187, 106],
            [171, 71, 188],
            [255, 202, 40],
            [0, 172, 193],
            [126, 87, 194],
            [38, 198, 218],
            [255, 138, 101],
            [156, 204, 101],
            [66, 133, 244],
            [244, 180, 0],
        ];

        $total = array_sum($data);
        if ($total <= 0) {
            return null;
        }

        $centerX = 850;
        $centerY = 730;
        $diameter = 864;
        $donutHole = 420;

        $titleColor = imagecolorallocate($img, 33, 37, 41);
        $mutedColor = imagecolorallocate($img, 100, 110, 120);
        $white = imagecolorallocate($img, 255, 255, 255);
        $lineColor = imagecolorallocate($img, 220, 220, 220);
        self::drawChartText($img, 30, 70, 48, $titleColor, 'GRAFICO DE CUSTOS OPERACIONAIS', true);
        self::drawChartText($img, 24, 70, 92, $mutedColor, 'Distribuicao percentual por componente de custo');
        imageline($img, 60, 128, $w - 60, 128, $lineColor);

        $start = 0;
        $i = 0;
        foreach ($data as $label => $value) {
            $angle = ($value / $total) * 360.0;
            $c = $colors[$i % count($colors)];
            $col = imagecolorallocate($img, $c[0], $c[1], $c[2]);
            imagefilledarc(
                $img,
                (int) round($centerX),
                (int) round($centerY),
                (int) round($diameter),
                (int) round($diameter),
                (int) round($start),
                (int) round($start + $angle),
                $col,
                IMG_ARC_PIE
            );

            $mid = deg2rad($start + ($angle / 2));
            $labelX = $centerX + cos($mid) * ($diameter / 2.9);
            $labelY = $centerY + sin($mid) * ($diameter / 2.9);
            $pct = ($value / $total) * 100.0;
            if ($pct >= 3.0) {
                $text = number_format($pct, 1, ',', '.') . '%';
                self::drawChartText(
                    $img,
                    24,
                    (int) round($labelX) - 30,
                    (int) round($labelY) - 14,
                    $white,
                    $text,
                    true
                );
            }

            $start += $angle;
            $i++;
        }

        // Donut center
        imagefilledellipse(
            $img,
            (int) round($centerX),
            (int) round($centerY),
            $donutHole,
            $donutHole,
            $white
        );
        self::drawChartText($img, 24, $centerX - 150, $centerY - 54, $mutedColor, 'CUSTO TOTAL', true);
        self::drawChartText($img, 34, $centerX - 210, $centerY - 8, $titleColor, self::fmt($total), true);

        // Legend below (larger text)
        $legendX = 120;
        $legendY = 1300;
        $i = 0;
        $textColor = imagecolorallocate($img, 35, 35, 35);
        $valueColor = imagecolorallocate($img, 80, 80, 80);
        self::drawChartText($img, 30, $legendX, $legendY - 60, $titleColor, 'LEGENDA', true);
        imageline($img, $legendX, $legendY - 16, $w - 120, $legendY - 16, $lineColor);

        $fontH = 30;
        $xCursor = $legendX;
        $yCursor = $legendY;
        $itemGapX = 30;
        $itemGapY = 36;
        $maxX = $w - 120;

        foreach ($data as $label => $value) {
            $c = $colors[$i % count($colors)];
            $col = imagecolorallocate($img, $c[0], $c[1], $c[2]);
            $pct = ($value / $total) * 100.0;
            $legendLabel = self::truncateLegend($label, 24);
            $line1 = $legendLabel . '  (' . number_format($pct, 1, ',', '.') . '%)';
            $line2 = self::fmt($value);
            $line1W = self::chartTextWidth($line1, 24, true);
            $line2W = self::chartTextWidth($line2, 24, true);
            $textW = max($line1W, $line2W);
            $itemW = 30 + 14 + $textW;
            $itemH = ($fontH * 2) + 20;

            if (($xCursor + $itemW) > $maxX) {
                $xCursor = $legendX;
                $yCursor += $itemH + $itemGapY;
            }

            imagefilledellipse($img, $xCursor + 14, $yCursor + 12, 26, 26, $col);
            self::drawChartText($img, 24, $xCursor + 34, $yCursor - 2, $textColor, $line1, true);
            self::drawChartText($img, 24, $xCursor + 34, $yCursor + $fontH + 2, $valueColor, $line2, true);

            $xCursor += $itemW + $itemGapX;
            $i++;
            if (($yCursor + $itemH) > ($h - 80)) {
                break;
            }
        }

        $file = 'tmp/custos_pie_' . uniqid() . '.png';
        imagepng($img, $file);
        imagedestroy($img);
        return $file;
    }

    private static function getChartFontPath(bool $bold = false): ?string
    {
        $candidates = $bold
            ? ['C:\\Windows\\Fonts\\arialbd.ttf', 'C:\\Windows\\Fonts\\segoeuib.ttf']
            : ['C:\\Windows\\Fonts\\arial.ttf', 'C:\\Windows\\Fonts\\segoeui.ttf'];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return null;
    }

    private static function drawChartText($img, int $size, int $x, int $y, $color, string $text, bool $bold = false): void
    {
        $font = self::getChartFontPath($bold);
        if ($font && function_exists('imagettftext')) {
            imagettftext($img, $size, 0, $x, $y + $size, $color, $font, $text);
            return;
        }

        imagestring($img, 5, $x, $y, $text, $color);
    }

    private static function chartTextWidth(string $text, int $size, bool $bold = false): int
    {
        $font = self::getChartFontPath($bold);
        if ($font && function_exists('imagettfbbox')) {
            $box = imagettfbbox($size, 0, $font, $text);
            if (is_array($box)) {
                return (int) abs($box[2] - $box[0]);
            }
        }

        return strlen($text) * imagefontwidth(5);
    }

    private static function truncateToWidth(FPDF $pdf, string $text, float $maxWidth): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        if ($pdf->GetStringWidth(self::toLatin1($text)) <= $maxWidth) {
            return $text;
        }
        $ellipsis = '...';
        $len = strlen($text);
        while ($len > 1) {
            $candidate = substr($text, 0, $len) . $ellipsis;
            if ($pdf->GetStringWidth(self::toLatin1($candidate)) <= $maxWidth) {
                return $candidate;
            }
            $len--;
        }
        return $ellipsis;
    }

    private static function truncateLegend(string $text, int $maxChars): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }
        return mb_substr($text, 0, $maxChars - 3) . '...';
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
            throw new Exception('FPDF nao encontrado. Instale: composer require setasign/fpdf');
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
