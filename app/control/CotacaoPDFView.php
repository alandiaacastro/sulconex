<?php

use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;

class CotacaoPdfDoc extends FPDF
{
    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Helvetica', 'B', 9);
        $w = 40;
        $x = ($this->GetPageWidth() - $w) / 2;
        $this->Rect($x, $this->GetY(), $w, 8, 'D');
        $this->SetXY($x, $this->GetY() + 1.5);
        $this->Cell($w, 5, $this->toLatin1('Página ' . $this->PageNo()), 0, 0, 'C');
    }

    private function toLatin1(string $text): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
    }
}

/**
 * Geracao de Cotacao em PDF (FPDF) no layout comercial (modelo da imagem com barras azuis e caixas).
 */
class CotacaoPDFView extends TPage
{
    private static string $database = 'sample';

    private const PAGE_MARGIN = 8;

    public function __construct($param = null)
    {
        parent::__construct();
    }

    public function onGenerate($param)
    {
        try {
            $id = $param['key'] ?? $param['id'] ?? null;

            if (empty($id)) {
                throw new Exception('ID da proposta nao informado.');
            }

            self::loadFpdf();

            TTransaction::open(self::$database);
            $proposta = new Proposta($id);
            $cliente  = new Clientes($proposta->cliente_id);

            $rota1_origem    = '';
            $rota1_destino   = '';
            $rota1_fronteira = '';
            $rota2_destino   = '';
            if (!empty($proposta->frete_origem_id)) {
                try {
                    $f1 = new TabelaFrete($proposta->frete_origem_id);
                    $rota1_origem    = trim((string) ($f1->origem ?? ''));
                    $rota1_destino   = trim((string) ($f1->destino ?? ''));
                    $rota1_fronteira = self::firstFilled([$f1->fronteira ?? '', $rota1_destino], '');
                } catch (Exception $e) {}
            }
            if (!empty($proposta->frete_destino_id)) {
                try {
                    $f2 = new TabelaFrete($proposta->frete_destino_id);
                    $rota2_destino = trim((string) ($f2->destino ?? ''));
                } catch (Exception $e) {}
            }

            TTransaction::close();

            if (!is_dir('tmp')) {
                mkdir('tmp', 0775, true);
            }

            $dataOferta = self::fmtDate((string) ($proposta->Data_Cotacao ?? ''));
            $validade = self::fmtDate((string) ($proposta->Data_Validade_Cotacao ?? ''));
            $localColeta = self::firstFilled([$rota1_origem, $proposta->Local_Coleta ?? ''], '');
            $localEntrega = self::firstFilled([$rota2_destino, $proposta->Local_Entrega ?? '', $rota1_destino], '');
            $aduana = self::firstFilled([$rota1_fronteira, $proposta->Aduana_Fronteira ?? ''], '');

            $seguroPct = (float) ($proposta->Percentual_Seguro_FOB ?? 0);
            $taxaMin = (float) ($proposta->Taxa_Dolar ?? 0);

            $dados = [
                'cotacao' => (string) ($proposta->Cotacao_ID ?? $proposta->id),
                'cliente_nome' => (string) ($cliente->nome ?? 'Cliente nao identificado'),
                'data_oferta' => $dataOferta,
                'validade' => $validade,
                'local_coleta' => $localColeta !== '' ? $localColeta : '---',
                'local_entrega' => $localEntrega !== '' ? $localEntrega : '---',
                'aduana' => $aduana !== '' ? $aduana : '---',
                'equipamento' => (string) ($proposta->Tipo_Equipamento ?? '---'),
                'transit_time' => (string) ($proposta->Tempo_Transito ?? '---'),
                'mercadoria' => (string) ($proposta->Descricao_Mercadoria ?? '---'),
                'pagamento' => (string) ($proposta->observacoes ?? '---'),
                'seguro_pct' => $seguroPct,
                'taxa_min' => $taxaMin,
                'valor_brl' => (float) ($proposta->Faturamento_Valor_1 ?? 0),
                'valor_usd' => (float) ($proposta->fat_dolar ?? 0),
            ];

            $pdf = new CotacaoPdfDoc('P', 'mm', 'A4');
            $pdf->SetMargins(self::PAGE_MARGIN, self::PAGE_MARGIN, self::PAGE_MARGIN);
            $pdf->SetAutoPageBreak(true, 18);
            $pdf->AddPage();

            $this->renderTop($pdf, $dados);
            $this->renderHeaderBox($pdf, $dados);
            $this->renderLogisticaBox($pdf, $dados);
            $this->renderPagamentoBox($pdf, $dados);
            $this->renderCustos($pdf, $dados);
            $this->renderFranquias($pdf);
            $this->renderCondicoes($pdf);

            $file = 'tmp/cotacao_' . $id . '.pdf';
            $pdf->Output('F', $file);
            TPage::openFile($file);
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $ee) {
            }
            new TMessage('error', $e->getMessage());
        }
    }

    private static function firstFilled(array $values, string $fallback = ''): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    private function renderTop(CotacaoPdfDoc $pdf, array $d): void
    {
        $logo = self::findHeaderLogo();
        if ($logo) {
            $pdf->Image($logo, 150, 10, 50);
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(self::PAGE_MARGIN, 16);
        $pdf->Cell(0, 5, self::toLatin1('À'), 0, 1, 'L');

        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetX(self::PAGE_MARGIN);
        $pdf->Cell(0, 5, self::toLatin1($d['cliente_nome']), 0, 1, 'L');

        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetX(self::PAGE_MARGIN);
        $pdf->MultiCell(120, 4.2, self::toLatin1('Agradecemos a oportunidade e apresentamos abaixo nossa oferta com base nos dados informados:'), 0, 'L');

        $pdf->Ln(2);
    }
    private function renderHeaderBox(CotacaoPdfDoc $pdf, array $d): void
    {
        $x = self::PAGE_MARGIN;
        $w = $pdf->GetPageWidth() - (self::PAGE_MARGIN * 2);

        $h = 12;
        $w1 = 78;
        $w2 = 62;
        $w3 = $w - $w1 - $w2;

        $y = $pdf->GetY();

        $pdf->SetDrawColor(40, 40, 40);
        $pdf->Rect($x, $y, $w, $h);
        $pdf->Line($x + $w1, $y, $x + $w1, $y + $h);
        $pdf->Line($x + $w1 + $w2, $y, $x + $w1 + $w2, $y + $h);

        $this->cellTitleValue($pdf, $x, $y, $w1, $h, 'Cotação de frete', (string) $d['cotacao']);
        $this->cellTitleValue($pdf, $x + $w1, $y, $w2, $h, 'Data da Oferta', (string) $d['data_oferta']);
        $this->cellTitleValue($pdf, $x + $w1 + $w2, $y, $w3, $h, 'Validade', (string) $d['validade']);

        $pdf->SetY($y + $h);
    }
    private function renderLogisticaBox(CotacaoPdfDoc $pdf, array $d): void
    {
        $x = self::PAGE_MARGIN;
        $y = $pdf->GetY();
        $w = $pdf->GetPageWidth() - (self::PAGE_MARGIN * 2);

        $rowH = 11;
        $h = $rowH * 3;

        $leftW = 98;
        $rightW = $w - $leftW;

        $pdf->SetDrawColor(40, 40, 40);
        $pdf->Rect($x, $y, $w, $h);
        $pdf->Line($x + $leftW, $y, $x + $leftW, $y + $h);

        // linhas internas
        $pdf->Line($x, $y + $rowH, $x + $w, $y + $rowH);
        $pdf->Line($x, $y + ($rowH * 2), $x + $w, $y + ($rowH * 2));

        // Esquerda
        $this->cellTitleValue($pdf, $x, $y, $leftW, $rowH, 'Local coleta', (string) $d['local_coleta']);
        $this->cellTitleValue($pdf, $x, $y + $rowH, $leftW, $rowH, 'Local entrega', (string) $d['local_entrega']);
        $this->cellTitleValue($pdf, $x, $y + ($rowH * 2), $leftW, $rowH, 'Aduana fronteira', (string) $d['aduana']);

        // Direita
        $this->cellTitleValue($pdf, $x + $leftW, $y, $rightW, $rowH, 'Equipamento', (string) $d['equipamento']);
        $this->cellTitleValue($pdf, $x + $leftW, $y + $rowH, $rightW, $rowH, 'Transit time', (string) $d['transit_time']);
        $this->cellTitleValue($pdf, $x + $leftW, $y + ($rowH * 2), $rightW, $rowH, 'Mercadoria', (string) $d['mercadoria']);

        $pdf->SetY($y + $h);
    }

    private function cellTitleValue(CotacaoPdfDoc $pdf, float $x, float $y, float $w, float $h, string $title, string $value): void
    {
        $padX = 2.2;
        $titleY = 1.8;
        $valueY = 6.2;

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY($x + $padX, $y + $titleY);
        $pdf->Cell($w - ($padX * 2), 3.6, self::toLatin1($title), 0, 0, 'L');

        $pdf->SetFont('Helvetica', 'B', 8.4);
        $pdf->SetXY($x + $padX, $y + $valueY);

        $val = trim((string) $value);
        if ($val === '') {
            $val = '-';
        }

        // permite quebrar em ate 2 linhas dentro da celula
        $x0 = $pdf->GetX();
        $y0 = $pdf->GetY();
        $pdf->MultiCell($w - ($padX * 2), 3.6, self::toLatin1($val), 0, 'L');
        $pdf->SetXY($x0, $y0);
    }

    private function renderPagamentoBox(CotacaoPdfDoc $pdf, array $d): void
    {
        $x = self::PAGE_MARGIN;
        $y = $pdf->GetY();
        $w = $pdf->GetPageWidth() - (self::PAGE_MARGIN * 2);
        $h = 13;

        $w1 = 98;
        $w2 = 50;
        $w3 = $w - $w1 - $w2;

        $pdf->SetDrawColor(40, 40, 40);
        $pdf->Rect($x, $y, $w, $h);
        $pdf->Line($x + $w1, $y, $x + $w1, $y + $h);
        $pdf->Line($x + $w1 + $w2, $y, $x + $w1 + $w2, $y + $h);

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetXY($x + 2, $y + 2.2);
        $pdf->Cell($w1 - 4, 4, self::toLatin1('Forma de pagamento'), 0, 0, 'L');

        $pdf->SetXY($x + $w1 + 2, $y + 2.2);
        $pdf->Cell($w2 - 4, 4, self::toLatin1('Seguro %'), 0, 0, 'L');

        $pdf->SetXY($x + $w1 + $w2 + 2, $y + 2.2);
        $pdf->Cell($w3 - 4, 4, self::toLatin1('Taxa de dólar mínima'), 0, 0, 'L');

        // valores
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(220, 0, 0);
        $pdf->SetXY($x + 2, $y + 7.4);
        $pdf->Cell($w1 - 4, 4.5, self::toLatin1($d['pagamento']), 0, 0, 'L');

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x + $w1 + 2, $y + 7.4);
        $pdf->Cell($w2 - 4, 4.5, self::toLatin1(number_format((float) $d['seguro_pct'], 2, ',', '.')), 0, 0, 'C');

        $pdf->SetXY($x + $w1 + $w2 + 2, $y + 7.4);
        $pdf->Cell($w3 - 4, 4.5, self::toLatin1(number_format((float) $d['taxa_min'], 4, ',', '.')), 0, 0, 'C');

        $pdf->SetY($y + $h + 4);
    }

    private function renderCustos(CotacaoPdfDoc $pdf, array $d): void
    {
        $x = self::PAGE_MARGIN;
        $w = $pdf->GetPageWidth() - (self::PAGE_MARGIN * 2);

        $this->sectionBar($pdf, $pdf->GetY(), 'FRETE POR UNIDADE');
        $pdf->Ln(7);

        $descW = 120;
        $brlW = 37;
        $usdW = $w - $descW - $brlW;

        $y = $pdf->GetY();
        $pdf->SetDrawColor(40, 40, 40);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetX($x);
        $pdf->Cell($descW, 8, self::toLatin1('FRETE ROTA INTERNACIONAL FTL - FULL TRUCKLOAD'), 1, 0, 'L');

        $pdf->SetFont('Helvetica', 'B', 9.2);
        $pdf->Cell($brlW, 8, self::toLatin1('R$ ' . number_format((float) $d['valor_brl'], 2, ',', '.')), 1, 0, 'R');
        $pdf->Cell($usdW, 8, self::toLatin1('USD ' . number_format((float) $d['valor_usd'], 2, ',', '.')), 1, 1, 'R');

        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 5, self::toLatin1('FATURAMENTO EM MOEDA NACIONAL'), 0, 1, 'L');
        $pdf->Ln(2);
    }

    private function renderFranquias(CotacaoPdfDoc $pdf): void
    {
        $this->sectionBar($pdf, $pdf->GetY(), 'FRANQUIAS LIVRES');
        $pdf->Ln(7);

        $x = self::PAGE_MARGIN;
        $w = $pdf->GetPageWidth() - (self::PAGE_MARGIN * 2);
        $colW = $w / 5;

        $pdf->SetDrawColor(40, 40, 40);
        $pdf->SetFont('Helvetica', '', 8.2);
        $pdf->SetTextColor(0, 0, 0);

        $headers = ['embarque', 'aduana de fronteira', 'aduana de destino', 'descarga', 'valor estadia'];
        $values = ['24 H', '48 H', '48 H', '24 H', 'USD 250,00'];

        $pdf->SetX($x);
        foreach ($headers as $h) {
            $pdf->Cell($colW, 7, self::toLatin1($h), 1, 0, 'C');
        }
        $pdf->Ln(7);

        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetX($x);
        foreach ($values as $v) {
            $pdf->Cell($colW, 7, self::toLatin1($v), 1, 0, 'C');
        }
        $pdf->Ln(10);
    }

    private function renderCondicoes(CotacaoPdfDoc $pdf): void
    {
        $this->sectionBar($pdf, $pdf->GetY(), 'CONDIÇÕES GERAIS PARA EMBARQUES/ CONDICIONES GENERALES PARA EMBARQUES');
        $pdf->Ln(7);

        $pdf->SetFont('Helvetica', '', 7.6);
        $pdf->SetTextColor(0, 0, 0);

        $itens = [
            'a) Taxa do dólar da data da emissão do CRT, a qual nunca poderá ser inferior à taxa mínima especificada na cotação./ La tasa del dólar de la fecha de emisión del CRT, la cual nunca podrá ser inferior a la tasa mínima especificada en la cotización.',
            'b) Carga e Descarga: por conta do importador e/ou exportador./ Carga y Descarga: por cuenta del importador y/o exportador;',
            'c) Não estão incluídas despesas com Aduanas, SENASA, MULTILOG./ No están incluidos los gastos con Aduanas, SENASA, MULTILOG;',
            'd) Seguro RCTR-VI incluso, cobrindo perdas por acidentes durante o transporte e desaparecimento da carga com o veículo transportador, comprovados por vistoria de nossa companhia seguradora. A responsabilidade do transportador está limitada ao valor constante da FATURA COMERCIAL que acompanha a mercadoria em caso de ressarcimento de sinistro. Demais coberturas estarão a cargo do embarcador/destinatário conforme INCOTERM que norteia a operação. Avarias devem ser apontadas ainda sobre rodas na descarga para cobertura./ Seguro RCTR-VI cubriendo siniestros originados por accidentes en viaje, debido a pérdidas y daños sufridos por los bienes o mercancías, incluyendo el desaparecimiento de la carga simultáneamente con el vehículo transportador, comprobados mediante inspección de nuestra compañía aseguradora.',
            'e) Infrações, mesmo que aplicadas a nós, por preenchimento incorreto ou imperfeições em nota fiscal de exportação ou importação, são responsabilidade do cliente./ Infracciones, aunque se nos apliquen, por el llenado incorrecto o imperfección en la factura de exportación o importación, serán responsabilidad del cliente.',
            'f) Havendo necessidade, o transbordo de carga é facultado a nossa companhia, por motivo estratégico ou para aliviar ao contratante./ Si es necesario, la transferencia de carga está facultada a nuestra compañía, por motivo estratégico o para aliviar al contratante.',
            'g) Programação de embarque: por escrito, com 48h de antecedência, por e-mail./ Programación de embarque: por escrito, con 48 horas de anticipación al correo electrónico.',
        ];

        foreach ($itens as $text) {
            $pdf->MultiCell(0, 4.0, self::toLatin1($text), 0, 'L');
        }
    }

    private function sectionBar(CotacaoPdfDoc $pdf, float $y, string $title): void
    {
        $x = self::PAGE_MARGIN;
        $w = $pdf->GetPageWidth() - (self::PAGE_MARGIN * 2);

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect($x, $y, $w, 6.5, 'D');

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY($x, $y + 1.8);
        $pdf->Cell($w, 3, self::toLatin1($title), 0, 0, 'C');
    }

    private static function findHeaderLogo(): ?string
    {
        $paths = [
            'app/images/logos/logosulconex2025.png',
            'app/images/icon.png',
            'app/images/logobranco.png',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
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

    private static function fmtDate(string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return date('d/m/Y');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            $ts = strtotime(substr($value, 0, 10));
            return $ts ? date('d/m/Y', $ts) : $value;
        }

        $ts = strtotime($value);
        return $ts ? date('d/m/Y', $ts) : $value;
    }

    private static function fmtLongPtBr(string $dateDdMmYyyy): string
    {
        $dateDdMmYyyy = trim($dateDdMmYyyy);
        $ts = strtotime(str_replace('/', '-', $dateDdMmYyyy));
        if (!$ts) {
            return $dateDdMmYyyy;
        }

        $meses = [
            1 => 'janeiro',
            2 => 'fevereiro',
            3 => 'marco',
            4 => 'abril',
            5 => 'maio',
            6 => 'junho',
            7 => 'julho',
            8 => 'agosto',
            9 => 'setembro',
            10 => 'outubro',
            11 => 'novembro',
            12 => 'dezembro',
        ];

        $d = (int) date('d', $ts);
        $m = (int) date('n', $ts);
        $y = (int) date('Y', $ts);

        return sprintf('%d de %s de %d', $d, $meses[$m] ?? (string) $m, $y);
    }

    private static function toLatin1(?string $text): string
    {
        $text = (string) $text;
        return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
    }
}





