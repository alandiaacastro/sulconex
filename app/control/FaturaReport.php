<?php

use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TDate;

class FaturaReport extends TPage
{
    public static function onGenerateReais($param)
    {
        self::generateByKey(Fatura::class, $param, 'reais');
    }

    public static function onGenerateDolar($param)
    {
        self::generateByKey(Fatura::class, $param, 'dolar');
    }

    public static function generateFromObject($object, string $moeda = 'reais', array $options = []): void
    {
        if ($moeda === 'reais') {
            self::gerarPDFReais($object, $options);
            return;
        }

        self::gerarPDFDolar($object, $options);
    }

    private static function generateByKey(string $recordClass, array $param, string $moeda, array $options = []): void
    {
        try {
            TTransaction::open('sample');
            $fatura = new $recordClass($param['key']);
            if (!$fatura) { throw new Exception('Fatura nao encontrada!'); }

            self::generateFromObject($fatura, $moeda, $options);

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    private static function gerarPDFReais($object, array $options = [])
    {
        self::gerarPDF($object, 'R$', 1.0, $options);
    }

    private static function gerarPDFDolar($object, array $options = [])
    {
        $taxa = (float) ($object->taxa ?? 0);
        if ($taxa <= 0) {
            new TMessage('error', 'Taxa de câmbio (BRL/USD) não informada nesta fatura. Preencha o campo "Taxa" antes de gerar o relatório em dólar.');
            return;
        }
        self::gerarPDF($object, 'US$', $taxa, $options);
    }

    private static function gerarPDF($object, string $moeda, float $taxa, array $options = [])
    {
        $converter = fn($valor) => (float) $valor / ($taxa > 0 ? $taxa : 1);
        $conv = function ($text) {
            return mb_convert_encoding((string) $text, 'ISO-8859-1', 'UTF-8');
        };
        $filePrefix = preg_replace('/[^a-z0-9_-]/i', '', (string) ($options['file_prefix'] ?? 'fatura_')) ?: 'fatura_';

        $cliente = null;
        try {
            $cliente = $object->clientekey ?? null;
        } catch (Exception $e) {
            $cliente = null;
        }

        $conhecimento = null;
        try {
            $conhecimento = $object->conhecimentokey ?? null;
        } catch (Exception $e) {
            $conhecimento = null;
        }

        $numeroFatura = (string) ($object->numero_fatura ?? '');
        $crt = (string) ($object->numero_crt ?? ($conhecimento->numero ?? ''));
        $faturaExterna = (string) ($object->fatura_cliente ?? ($conhecimento->fatura_crt ?? ''));

        $emissao = (string) ($object->emissao ?? '');
        $vencimento = (string) ($object->vencimento ?? '');

        $emissao = $emissao ? TDate::convertToMask($emissao, 'yyyy-mm-dd', 'dd/mm/yyyy') : '';
        $vencimento = $vencimento ? TDate::convertToMask($vencimento, 'yyyy-mm-dd', 'dd/mm/yyyy') : '';

        $total_brl = $object->valor_fatura;
        if ($total_brl === null || $total_brl === '') {
            $total_brl = (float) ($object->valor1 ?? 0) + (float) ($object->valor2 ?? 0) + (float) ($object->valor3 ?? 0);
        }
        $total   = $converter($total_brl);
        $valor1  = $converter((float) ($object->valor1 ?? 0));
        $valor2  = $converter((float) ($object->valor2 ?? 0));
        $valor3  = $converter((float) ($object->valor3 ?? 0));

        $valorExtenso = ExtensoReal::numeroPorExtenso($total);
        if ($moeda === 'US$') {
            $taxaFmt   = number_format($taxa, 4, ',', '.');
            $totalBrlFmt = number_format((float)$total_brl, 2, ',', '.');
            $valorExtenso = "USD " . $valorExtenso . " (Cambio R\$ {$taxaFmt} = R\$ {$totalBrlFmt})";
        }
        $descricao1 = (string) ($object->descricao1 ?? '');
        $descricao2 = (string) ($object->descricao2 ?? '');
        $descricao3 = (string) ($object->descricao3 ?? '');

        $notaFiscal = (string) ($object->nota_fiscal ?? '');

        $origem = (string) ($object->ORIGEM ?? ($conhecimento->local_responsabilidade ?? ''));
        $destino = (string) ($object->DESTINO ?? ($conhecimento->local_entrega ?? ''));

        $remetente = (string) ($object->REMETENTE ?? '');
        if ($remetente === '' && $conhecimento && !empty($conhecimento->remetente)) {
            $remetente = (string) ($conhecimento->remetente->nome ?? '');
        }

        $destinatario = (string) ($object->DESTINATARIO ?? '');
        if ($destinatario === '' && $conhecimento && !empty($conhecimento->destinatario)) {
            $destinatario = (string) ($conhecimento->destinatario->nome ?? '');
        }

        $pesoBruto = (string) ($object->PESO_BRUTO ?? '');
        if ($pesoBruto === '' && $conhecimento && !empty($conhecimento->peso_bruto_kg)) {
            $pesoBruto = (string) $conhecimento->peso_bruto_kg;
        }

        $produto = (string) ($object->PRODUTO ?? '');
        $observacao = (string) ($object->texto_observacao ?? '');

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();

        // CABECALHO
        $pdf->SetFont('Arial', '', 8);
        $textoCab = "COOPERATIVA DOS TRANSPORTADORES DE CARGAS E SERVICOS LOGISTICOS - SULCONEXLOG\nAVENIDA SANTOS DUMONT, 777\nRUI RAMOS URUGUAIANA-RS-BRASIL\nCNPJ 48.816.176/0001-42";
        $pdf->SetXY(56, 14);
        $pdf->MultiCell(142, 4, $conv($textoCab), 0, 'L');
        $pdf->SetLineWidth(0.2);
        $pdf->Line(28, 39, 100, 39);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Text(57, 14, $conv('COOP DOS TRANPORTADORES  -  4011268'));

        // QUADROS NUMERO/EMISSAO/VENCIMENTO
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Text(10, 40, $conv('FATURA'));
        $pdf->Rect(100, 34, 30, 10, 'D');
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(101, 36, $conv('NUMERO FATURA'));
        $pdf->SetFont('Arial', '', 10);
        $pdf->Text(108, 42, $conv($numeroFatura));

        $pdf->Rect(132, 34, 30, 10, 'D');
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(133, 36, $conv('EMISSAO'));
        $pdf->SetFont('Arial', '', 10);
        $pdf->Text(138, 42, $conv($emissao));

        $pdf->Rect(164, 34, 32, 10, 'D');
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(165, 36, $conv('VENCIMENTO'));
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Text(170, 42, $conv($vencimento));

        // DADOS DO CLIENTE
        $pdf->Rect(10, 46, 186, 30, 'D');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Text(12, 50, $conv('Cliente'));
        $pdf->Text(12, 57, $conv('Endereco'));
        $pdf->Text(120, 50, $conv('Insc.CNPJ/MF'));
        $pdf->Text(160, 50, $conv('Insc.Estadual'));

        $pdf->SetFont('Arial', '', 8);
        $pdf->Text(12, 53, $conv($cliente->nome ?? ''));
        $pdf->Text(12, 60, $conv($cliente->endereco ?? ''));
        $pdf->Text(12, 63, $conv(trim(($cliente->cidade ?? '') . ' - ' . ($cliente->estado ?? ''))));
        $pdf->Text(120, 53, $conv($cliente->cnpj ?? ''));
        $pdf->Text(160, 53, $conv($cliente->inscricao_estadual ?? ''));

        // QUADROS DE VALORES
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Rect(10, 78, 46, 12, 'D');
        $pdf->Text(11, 81, $conv('VALOR DA FATURA'));
        $pdf->SetFont('Arial', '', 12);
        $pdf->Text(12, 88, $moeda . ' ' . number_format((float) $total, 2, ',', '.'));

        $pdf->Rect(57, 78, 46, 12, 'D');
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(71, 81, $conv('DUPLICATA'));
        $pdf->Line(57, 82, 103, 82);
        $pdf->Line(78, 82, 78, 90);
        $pdf->Text(61, 85, $conv('VALOR'));
        $pdf->SetFont('Arial', '', 8);
        $pdf->Text(60, 88, $moeda . ' ' . number_format((float) $total, 2, ',', '.'));
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(81, 85, $conv('ORDEM'));

        $pdf->Rect(104, 78, 34, 12, 'D');
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(106, 81, $conv('CRT'));
        $pdf->SetFont('Arial', '', 11);
        $pdf->Text(108, 88, $conv($crt));

        $pdf->Rect(139, 78, 57, 12, 'D');
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(141, 81, $conv('Nº FATURA EXTERNA'));
        $pdf->SetFont('Arial', '', 10);
        $pdf->Text(148, 88, $conv($faturaExterna));

        // VALOR POR EXTENSO
        $pdf->Rect(10, 92, 186, 11, 'D');
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(11, 95, $conv('VALOR POR EXTENSO'));
        $pdf->SetFont('Arial', '', 8);
        $pdf->Text(11, 100, $conv($valorExtenso));
        $pdf->Text(11, 107, $conv('A DUPLICATA CORRESPONDENTE A ESTA FATURA DEVERA SER PAGA NO VENCIMENTO E PRACA ABAIXO CITADOS A'));
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Text(11, 110, $conv('COOPERATIVA DOS TRANSPORTADORES DE CARGAS E SERVICOS LOGISTICOS-SULCONEXLOG'));

        // DADOS BANCARIOS E ASSINATURA
        $pdf->Rect(10, 112, 94, 25, 'D');
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(11, 115, $conv('AGENTE FINANCEIRO - BANCO: '));
        $pdf->SetFont('Arial', '', 8);
        $pdf->Text(11, 119, $conv('BANCO ITAU S.A '));
        $pdf->Text(11, 122, $conv('AGENCIA 0324'));
        $pdf->Text(11, 125, $conv('CONTA CORRENTE 99432-6'));
        $pdf->Text(11, 128, $conv('CNPJ 48.816.176/0001-42 '));
        $pdf->Text(11, 131, $conv('BENEFICIARIO: COOP.TRANSP.CARGAS SULCONEXLOG'));

        $pdf->Rect(105, 112, 91, 25, 'D');
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Line(126, 126, 168, 126);
        $pdf->Text(126, 130, $conv('ASSINATURA DO PRESIDENTE OU VICE'));
        $pdf->SetFont('Arial', '', 8);
        $pdf->Text(126, 133, $conv('COOPERATIVA SULCONEXLOG '));

        // DETALHAMENTO
        $pdf->Rect(10, 139, 186, 120, 'D');
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(11, 142, $conv('CONHECIMENTO '));
        $pdf->SetFont('Arial', '', 8);
        $pdf->Text(11, 148, $conv($crt));

        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(34, 142, $conv('DESCRICAO DAS MERCADORIAS E SERVICOS'));
        $pdf->SetFont('Arial', '', 8);
        $pdf->Text(34, 148, $conv($descricao1));
        $pdf->Text(34, 152, $conv($descricao2));
        $pdf->Text(34, 156, $conv($descricao3));

        $detailTextWidth = 97;
        $detailLineHeight = 4;
        $notaFiscalLines = self::getPdfWrappedLines($pdf, $conv($notaFiscal), $detailTextWidth, 2);
        $pdf->Text(34, 164, $conv('NOTAS FISCAIS.:'));
        foreach ($notaFiscalLines as $index => $line) {
            $pdf->Text(60, 164 + ($index * $detailLineHeight), $line);
        }

        $pdf->Text(34, 174, $conv('ORIGEM.:'));
        $pdf->Text(60, 174, $conv($origem));

        $pdf->Text(34, 180, $conv('DESTINO.:'));
        $pdf->Text(60, 180, $conv($destino));

        $pdf->Text(34, 186, $conv('REMETENTE.:'));
        $pdf->Text(60, 186, $conv($remetente));

        $pdf->Text(34, 192, $conv('DESTINATARIO.:'));
        $pdf->Text(60, 192, $conv($destinatario));

        $pdf->Text(34, 198, $conv('FATURA.:'));
        $pdf->Text(60, 198, $conv($faturaExterna));

        if (empty($options['hide_peso_bruto'])) {
            $pdf->Text(34, 204, $conv('PESOBRUTO.:'));
            $pesoFormatado = $pesoBruto !== '' ? number_format((float) $pesoBruto, 2, ',', '.') . ' kg' : '';
            $pdf->Text(60, 204, $conv($pesoFormatado));
        }

        $produtoLines = self::getPdfWrappedLines($pdf, $conv($produto), $detailTextWidth, 2);
        $observacaoLines = self::getPdfWrappedLines($pdf, $conv($observacao), $detailTextWidth, 4);

        $pdf->Text(34, 210, $conv('PRODUTO.:'));
        foreach ($produtoLines as $index => $line) {
            $pdf->Text(60, 210 + ($index * $detailLineHeight), $line);
        }

        $pdf->Text(34, 220, $conv('OBSERVACAO.:'));
        foreach ($observacaoLines as $index => $line) {
            $pdf->Text(60, 220 + ($index * $detailLineHeight), $line);
        }

        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(162, 142, $conv('VALOR'));
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetXY(160, 142);
        $pdf->Cell(30, 10, $moeda . ' ' . number_format($valor1, 2, ',', '.'), 0, 0, 'R');
        $pdf->SetXY(160, 146);
        $pdf->Cell(30, 10, $moeda . ' ' . number_format($valor2, 2, ',', '.'), 0, 0, 'R');
        $pdf->SetXY(160, 150);
        $pdf->Cell(30, 10, $moeda . ' ' . number_format($valor3, 2, ',', '.'), 0, 0, 'R');

        $pdf->SetXY(160, 250);
        $pdf->Cell(30, 10, $moeda . ' ' . number_format((float) $total, 2, ',', '.'), 0, 0, 'R');
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(161, 252, $conv('TOTAL FATURA'));

        $pdf->Line(32, 139, 32, 259);
        $pdf->Line(160, 139, 160, 259);

        // RODAPE
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(10, 262, $conv('OBSERVACOES '));
        $pdf->Rect(10, 264, 186, 20, 'D');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Text(11, 268, $conv('DECLARO QUE RECEBI OS CONHECIMENTOS CONSTANTES NESTA FATURA.'));
        $pdf->Line(20, 278, 60, 278);
        $pdf->Text(34, 282, $conv('DATA'));
        $pdf->Line(130, 278, 180, 278);
        $pdf->Text(145, 282, $conv('ASSINATURA'));

        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Text(11, 288, $conv('USUARIO E DATA DE INCLUSAO '));
        $pdf->Text(119, 288, $conv('USUARIO E DATA DE ALTERACAO '));

        // Logo: tenta pelo permisso do conhecimento (mesma base do CRT), fallback COOPERATIVA.png
        $imgFile = null;
        try {
            if ($conhecimento && !empty($conhecimento->permisso_id)) {
                $permisso = new Permisso($conhecimento->permisso_id);
                if (!empty($permisso->logo)) {
                    $candidate = 'app/images/logos/' . $permisso->logo;
                    if (file_exists($candidate) && getimagesize($candidate)) {
                        $imgFile = $candidate;
                    }
                }
            }
        } catch (Exception $e) {}
        if (!$imgFile) {
            $imgFile = 'app/images/logos/COOPERATIVA.png';
        }
        if (file_exists($imgFile)) {
            $pdf->Image(realpath($imgFile), 12, 8, 0, 22);
        } else {
            $pdf->SetXY(12, 12);
            $pdf->SetFont('Arial', 'I', 7);
            $pdf->Cell(40, 10, $conv('Logo nao encontrada'), 0);
        }

        if (!is_dir('tmp')) {
            mkdir('tmp', 0775, true);
        }

        $file = 'tmp/' . $filePrefix . uniqid() . '.pdf';
        $pdf->Output($file, 'F');
        TPage::openFile($file);
    }

    private static function getPdfWrappedLines(FPDF $pdf, string $text, float $maxWidth, int $maxLines = 2): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            return [];
        }

        $wrappedLines = [];
        foreach (explode("\n", $text) as $paragraph) {
            $paragraph = trim((string) preg_replace('/\s+/', ' ', $paragraph));
            if ($paragraph === '') {
                continue;
            }

            $currentLine = '';
            foreach (preg_split('/\s+/', $paragraph) as $word) {
                foreach (self::splitPdfWord($pdf, $word, $maxWidth) as $segment) {
                    $candidate = $currentLine === '' ? $segment : $currentLine . ' ' . $segment;
                    if ($currentLine === '' || $pdf->GetStringWidth($candidate) <= $maxWidth) {
                        $currentLine = $candidate;
                        continue;
                    }

                    $wrappedLines[] = $currentLine;
                    $currentLine = $segment;
                }
            }

            if ($currentLine !== '') {
                $wrappedLines[] = $currentLine;
            }
        }

        if (count($wrappedLines) <= $maxLines) {
            return $wrappedLines;
        }

        $visibleLines = array_slice($wrappedLines, 0, $maxLines);
        $visibleLines[$maxLines - 1] = self::truncatePdfLine($pdf, $visibleLines[$maxLines - 1], $maxWidth);

        return $visibleLines;
    }

    private static function splitPdfWord(FPDF $pdf, string $word, float $maxWidth): array
    {
        if ($word === '' || $pdf->GetStringWidth($word) <= $maxWidth) {
            return [$word];
        }

        $segments = [];
        $current = '';

        foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $candidate = $current . $char;
            if ($current !== '' && $pdf->GetStringWidth($candidate) > $maxWidth) {
                $segments[] = $current;
                $current = $char;
                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $segments[] = $current;
        }

        return $segments;
    }

    private static function truncatePdfLine(FPDF $pdf, string $line, float $maxWidth): string
    {
        $suffix = '...';
        $line = rtrim($line);

        if ($pdf->GetStringWidth($line . $suffix) <= $maxWidth) {
            return $line . $suffix;
        }

        while ($line !== '' && $pdf->GetStringWidth($line . $suffix) > $maxWidth) {
            $line = rtrim(mb_substr($line, 0, mb_strlen($line) - 1));
        }

        return $line === '' ? $suffix : $line . $suffix;
    }
}
