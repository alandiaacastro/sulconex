<?php

use Adianti\Database\TTransaction;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;

class CartaCorrecaoPDF
{
    public static array $fieldLabels = [
        'permisso_id'                    => 'Permissao',
        'numero'                         => 'Numero CRT',
        'data_transportador_assinatura'  => 'Data CRT',
        'fatura_crt'                     => 'Fatura CRT',
        'tipo_cobranca'                  => 'Tipo de Cobranca',
        'toneladas_carga'                => 'Toneladas de Carga',
        'valor_por_ton'                  => 'Valor por Tonelada',
        'status_crt_id'                  => 'Situacao',
        'remetente_id'                   => 'Remetente (ID)',
        'nome_remetente'                 => 'Nome Remetente',
        'endereco_remetente'             => 'Endereco Remetente',
        'destinatario_id'                => 'Destinatario (ID)',
        'nome_destinatario'              => 'Nome Destinatario',
        'endereco_destinatario'          => 'Endereco Destinatario',
        'consignatario_id'               => 'Consignatario (ID)',
        'nome_consignatario'             => 'Nome Consignatario',
        'endereco_consignatario'         => 'Endereco Consignatario',
        'notificar_id'                   => 'Notificar (ID)',
        'notificar_nome'                 => 'Nome Notificar',
        'notificar_endereco'             => 'Endereco Notificar',
        'pagador_id'                     => 'Pagador (ID)',
        'nome_pagador'                   => 'Nome Pagador',
        'local_emissao'                  => 'Local de Emissao',
        'local_responsabilidade'         => 'Local de Responsabilidade',
        'local_entrega'                  => 'Local de Entrega',
        'descricao_mercadoria'           => 'Descricao da Mercadoria',
        'peso_bruto_kg'                  => 'Peso Bruto (kg)',
        'peso_liq_kg'                    => 'Peso Liquido (kg)',
        'volume_m3'                      => 'Volume (m3)',
        'incoterm'                       => 'Incoterm',
        'incoterm16'                     => 'Incoterm 2016',
        'moeda_valor_mercadorias'        => 'Moeda das Mercadorias',
        'valor_mercadorias'              => 'Valor das Mercadorias',
        'valor_declarado'                => 'Valor Declarado',
        'textogasto1'                    => 'Descricao Gasto 1',
        'textogasto2'                    => 'Descricao Gasto 2',
        'textogasto3'                    => 'Descricao Gasto 3',
        'custoremetente1'                => 'Custo Remetente 1',
        'custoremetente2'                => 'Custo Remetente 2',
        'custoremetente3'                => 'Custo Remetente 3',
        'custodestino1'                  => 'Custo Destino 1',
        'custodestino2'                  => 'Custo Destino 2',
        'custodestino3'                  => 'Custo Destino 3',
        'total_custo_remetente'          => 'Total Custo Remetente',
        'total_custo_destinatario'       => 'Total Custo Destinatario',
        'copiacrt'                       => 'Copia CRT',
        'assinatura_nome'                => 'Nome do Assinante',
        'permisso'                       => 'Permissao',
        'descricao_mercadoria'           => 'Descricao da Mercadoria',
    ];

    public static function gerar(int $conhecimentoId): void
    {
        TTransaction::open('sample');
        $crt      = new Conhecimento($conhecimentoId);
        $permisso = $crt->permisso_id ? new Permisso($crt->permisso_id) : null;
        TTransaction::close();

        $changes = self::getLastChanges($conhecimentoId);

        if (empty($changes)) {
            throw new Exception('Nenhuma alteracao encontrada para este CRT.');
        }

        self::buildPdf($crt, $permisso, $changes);
    }

    /**
     * Gera a carta com um conjunto especifico de registros de log (selecionados pelo usuario)
     */
    public static function gerarComChanges(Conhecimento $crt, ?Permisso $permisso, array $changes, string $assinaturaNome = '', string $item11 = ''): void
    {
        if (empty($changes)) {
            throw new Exception('Nenhum campo selecionado para a Carta de Correcao.');
        }
        self::buildPdf($crt, $permisso, $changes, $assinaturaNome, $item11);
    }

    private static function getLastChanges(int $conhecimentoId): array
    {
        TTransaction::open('log');

        $criteria = new TCriteria;
        $criteria->add(new TFilter('tablename', '=', 'conhecimento'));
        $criteria->add(new TFilter('pkvalue',   '=', (string) $conhecimentoId));
        $criteria->add(new TFilter('operation', '=', 'changed'));
        $criteria->setProperty('order', 'logdate DESC');
        $criteria->setProperty('limit', 200);

        $logs = SystemChangeLog::getObjects($criteria);
        TTransaction::close();

        if (empty($logs)) {
            return [];
        }

        $lastTransactionId = $logs[0]->transaction_id;
        return array_values(array_filter($logs, fn($l) => $l->transaction_id === $lastTransactionId));
    }

    private static function buildPdf(Conhecimento $crt, ?Permisso $permisso, array $changes, string $assinaturaNome = '', string $item11 = ''): void
    {
        self::loadFpdf();

        $marginL = 20;
        $marginR = 20;
        $marginT = 35; // espaço para os logos no topo

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 30);
        $pdf->SetMargins($marginL, $marginT, $marginR);
        $pdf->AddPage();

        $pageW = $pdf->GetPageWidth() - $marginL - $marginR; // ~170mm

        // ── LOGO DA TRANSPORTADORA (canto superior esquerdo) ───────────────
        if ($permisso && $permisso->logo) {
            $caminhoLogo = 'app/images/logos/' . $permisso->logo;
            if (file_exists($caminhoLogo) && getimagesize($caminhoLogo)) {
                $pdf->Image($caminhoLogo, 6, 6, 45, 23);
            }
        }

        // ── TITULO ──────────────────────────────────────────────────────────
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->Cell($pageW, 8, self::t('CARTA DE CORRECAO'), 0, 1, 'C');
        $pdf->Ln(10);

        // ── PARAGRAFO FORMAL ────────────────────────────────────────────────
        $transportadora = self::t(strtoupper((string)($permisso->transportadora ?? '')));
        $cnpj           = self::t((string)($permisso->cnpj ?? ''));
        $numeroCrt      = self::t((string)($crt->numero ?? ''));
        $sede           = self::t((string)($permisso->dados_documentos ?? ''));

        $pdf->SetFont('Helvetica', '', 10);

        // Parte 1: nome da empresa (negrito)
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Write(6, $transportadora);

        // Parte 2: texto normal ate o numero do CRT
        $pdf->SetFont('Helvetica', '', 10);
        $textoParte2 = ', pessoa juridica de direito privado';
        if ($cnpj !== '') {
            $textoParte2 .= ', inscrita no CNPJ. ' . $cnpj . ',';
        }
        $textoParte2 .= ' vem respeitosamente apresentar a devida correcao a seu CRT, amparado pelo numero ';
        $pdf->Write(6, self::t($textoParte2));

        // Numero do CRT em negrito e sublinhado
        $pdf->SetFont('Helvetica', 'BU', 10);
        $pdf->Write(6, $numeroCrt);

        // Parte final normal
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Write(6, self::t(', o qual contem as seguintes irregularidades que abaixo apontamos, cuja correcao seja providenciada.'));

        $pdf->Ln(12);

        // ── SECAO: DADO(S) ALTERADO(S) ──────────────────────────────────────
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell($pageW, 6, self::t('2 - DADO(S) ALTERADO(S)'), 0, 1, 'L');
        $pdf->Ln(1);

        // Cabeçalho da tabela
        $col1 = 28;  // 1.1 - campo (metade do anterior)
        $col2 = ($pageW - $col1) / 2; // 1.2 Onde se le
        $col3 = ($pageW - $col1) / 2; // 1.3 Leia-se

        $lineH = 5; // altura de linha sem espacos em branco extras

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell($col1, 8, '1.1', 1, 0, 'C');
        $pdf->Cell($col2, 8, self::t('1.2 Onde se le'), 1, 0, 'C');
        $pdf->Cell($col3, 8, self::t('1.3 Leia-se'), 1, 1, 'C');

        // Linhas da tabela
        $firstRow = true;
        foreach ($changes as $i => $change) {
            $fieldLabel = $firstRow && $item11 !== '' ? $item11 : ($item11 !== '' ? '' : ($change->columnname));
            $firstRow   = false;

            // Remove linhas em branco extras
            $oldVal = trim(preg_replace('/(\r?\n){2,}/', "\n", (string)($change->oldvalue ?? '')));
            $newVal = trim(preg_replace('/(\r?\n){2,}/', "\n", (string)($change->newvalue ?? '')));

            $t1 = self::t($fieldLabel);
            $t2 = self::t($oldVal);
            $t3 = self::t($newVal);

            // Calcula quantas linhas cada celula precisara (para igualar altura)
            $n1 = self::calcLines($pdf, $t1, $col1, $lineH);
            $n2 = self::calcLines($pdf, $t2, $col2, $lineH);
            $n3 = self::calcLines($pdf, $t3, $col3, $lineH);
            $rowH = max($n1, $n2, $n3) * $lineH;

            $x = $pdf->GetX();
            $y = $pdf->GetY();

            // Bordas com altura uniforme
            $pdf->Rect($x,              $y, $col1, $rowH);
            $pdf->Rect($x + $col1,      $y, $col2, $rowH);
            $pdf->Rect($x + $col1 + $col2, $y, $col3, $rowH);

            // Conteudo sem borda (a borda ja foi desenhada)
            $pdf->SetXY($x + 1, $y + 1);
            $pdf->MultiCell($col1 - 2, $lineH, $t1, 0, 'L');
            $pdf->SetXY($x + $col1 + 1, $y + 1);
            $pdf->MultiCell($col2 - 2, $lineH, $t2, 0, 'L');
            $pdf->SetXY($x + $col1 + $col2 + 1, $y + 1);
            $pdf->MultiCell($col3 - 2, $lineH, $t3, 0, 'L');

            $pdf->SetXY($x, $y + $rowH);
        }

        $pdf->Ln(10);

        // ── FECHAMENTO ──────────────────────────────────────────────────────
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell($pageW, 6, self::t('Sem mais,'), 0, 1, 'L');

        $pdf->Ln(10);

        // ── RODAPE: DATA ─────────────────────────────────────────────────────
        $meses = ['janeiro','fevereiro','marco','abril','maio','junho',
                  'julho','agosto','setembro','outubro','novembro','dezembro'];
        $dataFormatada = 'Uruguaiana/RS, ' . date('d') . ' de ' . $meses[(int)date('m') - 1] . ' de ' . date('Y');

        $pdf->Ln(4);

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell($pageW, 5, self::t('Local e Data: ' . $dataFormatada), 0, 1, 'L');

        $pdf->Ln(6);

        // ── ASSINATURA (imagem, sem caixas) ──────────────────────────────────
        $assinaturaY = $pdf->GetY();
        if (file_exists('app/images/assinatura2.jpg')) {
            $pdf->Image('app/images/assinatura2.jpg', $marginL, $assinaturaY, 35, 25);
        }

        // Nome do responsavel abaixo da assinatura
        if ($assinaturaNome !== '') {
            $pdf->SetXY($marginL, $assinaturaY + 27);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(70, 5, self::t($assinaturaNome), 0, 1, 'L');
        }

        // ── OUTPUT ──────────────────────────────────────────────────────────
        $outputDir = 'app/output';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $fileName = 'CartaCorrecao_' . preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($crt->numero ?? $crt->id)) . '_' . date('Ymd_His') . '.pdf';
        $filePath = $outputDir . '/' . $fileName;

        $pdf->Output('F', $filePath);
        \Adianti\Control\TPage::openFile($filePath);
    }

    /**
     * Estima quantas linhas o texto vai ocupar em uma celula de largura $colW
     */
    private static function calcLines(FPDF $pdf, string $text, float $colW, float $lineH): int
    {
        $innerW = $colW - 2; // margem interna ~1mm de cada lado
        $total  = 0;

        foreach (explode("\n", $text) as $para) {
            $para = trim($para);
            if ($para === '') {
                $total++;
                continue;
            }
            $words    = preg_split('/\s+/', $para);
            $lineW    = 0;
            $lineCount = 1;
            foreach ($words as $word) {
                $ww = $pdf->GetStringWidth($word . ' ');
                if ($lineW > 0 && $lineW + $ww > $innerW) {
                    $lineCount++;
                    $lineW = $ww;
                } else {
                    $lineW += $ww;
                }
            }
            $total += $lineCount;
        }

        return max(1, $total);
    }

    private static function t(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value);
        return $out !== false ? $out : preg_replace('/[^\x00-\xFF]/', '', $value);
    }

    private static function loadFpdf(): void
    {
        if (class_exists('FPDF')) {
            return;
        }

        foreach (['vendor/setasign/fpdf/fpdf.php', 'lib/fpdf/fpdf.php'] as $path) {
            if (file_exists($path)) {
                require_once $path;
                return;
            }
        }

        throw new Exception('FPDF nao encontrado. Verifique o vendor.');
    }
}
