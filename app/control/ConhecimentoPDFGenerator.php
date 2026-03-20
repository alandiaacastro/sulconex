<?php

class ConhecimentoPDFGenerator
{
    private $object;
    private $pdf;

    private static function toPdfText($value): string
    {
        if ($value === null) {
            return '';
        }

        $text = (string) $value;

        if ($text === '') {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if (preg_match('//u', $text)) {
            $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
            if ($out !== false) {
                return $out;
            }

            $out = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $text);
            if ($out !== false) {
                return $out;
            }
        }

        // Fallback: treat input as Windows-1252/ISO-8859-1 (common in legacy DB/files)
        $out = @iconv('Windows-1252', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        if ($out !== false) {
            return $out;
        }

        $out = @iconv('ISO-8859-1', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        if ($out !== false) {
            return $out;
        }

        // Last resort: drop bytes outside Latin-1 range
        $out = preg_replace('/[^\x00-\xFF]/', '', $text);
        return $out ?? '';
    }

    private function getImportadorNome($object): string
    {
        $nome = trim((string) ($object->nome_destinatario ?? ''));
        if ($nome !== '') {
            return $nome;
        }

        if (!empty($object->destinatario_id)) {
            try {
                $destinatario = $object->get_destinatario();
                $nome = trim((string) ($destinatario->nome ?? ''));
                if ($nome !== '') {
                    return $nome;
                }
            } catch (Exception $e) {
            }
        }

        $nome = trim((string) ($object->nome_consignatario ?? ''));
        if ($nome !== '') {
            return $nome;
        }

        if (!empty($object->consignatario_id)) {
            try {
                $consignatario = $object->get_consignatario();
                $nome = trim((string) ($consignatario->nome ?? ''));
                if ($nome !== '') {
                    return $nome;
                }
            } catch (Exception $e) {
            }
        }

        return '';
    }

    private static function sanitizeFilePart($value, int $maxLen = 40): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s+/', '_', $text);
        $text = preg_replace('/[^A-Za-z0-9_\-]/', '', $text);
        $text = trim((string) $text, '_-');

        if ($text === '') {
            return '';
        }

        return substr($text, 0, $maxLen);
    }

    private static function parseNumber($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $text = preg_replace('/[^0-9,.\-]/', '', $text);
        if ($text === '' || $text === '-' || $text === ',' || $text === '.') {
            return null;
        }

        $lastComma = strrpos($text, ',');
        $lastDot = strrpos($text, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSep = $lastComma > $lastDot ? ',' : '.';
            $thousandSep = ($decimalSep === ',') ? '.' : ',';
            $text = str_replace($thousandSep, '', $text);
            $text = str_replace($decimalSep, '.', $text);
        } elseif ($lastComma !== false) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } else {
            $text = str_replace(',', '', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private static function formatMoney($value): string
    {
        $number = self::parseNumber($value);
        if ($number === null) {
            return trim((string) $value);
        }

        return number_format($number, 2, ',', '.');
    }
    /**
     * Construtor
     * @param mixed $key Chave para buscar o objeto Conhecimento
     */
    public function __construct($key)
    {
        try {
            TTransaction::open('sample');
            $this->object = new Conhecimento($key);

            // Instancia e configura o FPDF
            $this->pdf = new FPDF();
            $this->pdf->SetAutoPageBreak(true, 10);
            $this->pdf->SetFont('Helvetica', '', 10);
            $this->pdf->SetTopMargin(10);
            $this->pdf->SetLeftMargin(10);
            $this->pdf->SetRightMargin(10);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
            exit;
        }
    }

    /**
     * Monta o conteúdo da página com todos os textos e formatações
     * @param string $name Nome que será impresso no rodapé (ex.: "VIA ORIGINAL")
     */
    private function addPageContent($name)
    {
        $pdf    = $this->pdf;
        $object = $this->object;

        // Adiciona uma nova página
        $pdf->AddPage('P', 'A4');

        // Cabeçalho e imagens
        $pdf->SetFont('Helvetica', 'B', 20);
        if (file_exists('app/images/CRT.jpg')) {
            $pdf->Image('app/images/CRT.jpg', 6, 6, 18, 18);
        }
        if (file_exists('app/images/assinatura2.jpg')) {
            $pdf->Image('app/images/assinatura2.jpg', 56, 255, 35, 25);
        }
      //  $pdf->Image('app/images/assinatura1.png', 56, 255, 35, 25); // sem assinatura so carimbo
        $pdf->SetFont('Helvetica', '', 6);
      //  $pdf->Image('app/images/' . $object->logotransporte, 150, 48, 45, 20);

      $permisso = new Permisso($object->permisso_id);
      $caminhoLogo = 'app/images/logos/' . $permisso->logo;

      if ($permisso->logo && file_exists($caminhoLogo) && getimagesize($caminhoLogo)) {
          $pdf->Image($caminhoLogo, 160, 46, 45, 20);
      }


        // Texto do cabeçalho
        $texto = "El transporte realizado bajo esta carta de ponte Internacional está sujeto a las disposições del Convenio sobre el contrato de transporte y la responsabilidad Civil del porteador en el Transportes Terrestre Internacional de Mercancias.las cuales anulan toda estipulação que se aparte de ellas en prejuicio del remitente o del consignatário.O transporte realizado ao amparo deste Conheçimento de Transporte Internacional está sujeto às disposições del Convênio sobre o Contrato de Transporte e a Responsabilidade Civil do Transportador no transporte terrestre internacional.de mercadorias, as cuales anulam toda especulação contrária às mesmas en prejuicio del remitente o del consignatário";
        $pdf->SetXY(71, 8);
        $pdf->MultiCell(0, 2, self::toPdfText($texto), 0, 'J');

        $pdf->SetFont('Helvetica', '', 10);
        $texto2 = "Carta de Porte Internacional por carretera               Conhecimento de Transporte Internacional por Rodovia";
        $pdf->SetXY(23, 7);
        $pdf->MultiCell(50, 4, self::toPdfText($texto2), 0, 'L');
        $pdf->Rect(5, 5, 200, 20); // Cabeçalho CRT

        // Desenho dos ret�ngulos (campos e divis�es)
        $pdf->Rect(5, 25, 100, 25, 'D');
        $pdf->Rect(5, 50, 100, 22, 'D');
        $pdf->Rect(5, 72, 100, 22, 'D');
        $pdf->Rect(5, 94, 100, 22, 'D');
        $pdf->Rect(5, 116, 160, 58, 'D');
        $pdf->Rect(5, 174, 25, 40, 'D');
        $pdf->Rect(30, 174, 25, 40, 'D');
        $pdf->Rect(55, 174, 10, 40, 'D');
        $pdf->Line(5, 207, 105, 207);
        $pdf->Rect(65, 174, 30, 40, 'D');
        $pdf->Rect(5, 214, 100, 7, 'D');
        $pdf->Rect(5, 221, 100, 10, 'D');
        $pdf->Rect(5, 231, 100, 16, 'D');
        $pdf->Rect(5, 247, 100, 37, 'D');
        $pdf->Rect(105, 25, 100, 6, 'D');
        $pdf->Rect(105, 31, 100, 35, 'D');
        $pdf->Rect(105, 66, 100, 13, 'D');
        $pdf->Rect(105, 79, 100, 13, 'D');
        $pdf->Rect(105, 92, 100, 13, 'D');
        $pdf->Rect(105, 105, 100, 11, 'D');
        $pdf->Rect(165, 116, 40, 21, 'D');
        $pdf->Rect(165, 137, 40, 11, 'D');
        $pdf->Rect(165, 148, 40, 26, 'D');
        $pdf->Rect(105, 174, 100, 10, 'D');
        $pdf->Rect(105, 184, 100, 25, 'D');
        $pdf->Rect(105, 209, 100, 25, 'D');
        $pdf->Rect(105, 234, 100, 25, 'D');
        $pdf->Rect(105, 259, 100, 25, 'D');

        // Inserção de textos e dados
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(6, 27, self::toPdfText('1 Nombre y domicilio del remitente / Nome e endereço do remetente'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(6, 28);
        $pdf->MultiCell(90, 3, self::toPdfText((string)$object->endereco_remetente), 0, 'L');

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(6, 52, self::toPdfText('4 Nombre y domicilio del destinatário / Nome e endereço do destinatário'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(6, 53);
        $pdf->MultiCell(90, 3, self::toPdfText((string)$object->endereco_destinatario), 0, 'L');

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(6, 74, self::toPdfText('6 Nombre y domicilio del consignatário / Nome e endereço do consignatário'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(6, 76);
        $pdf->MultiCell(90, 3, self::toPdfText((string)$object->endereco_consignatario), 0, 'L');

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(6, 96, self::toPdfText('9 Notificar a / Notificar a '));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(6, 98);
        $pdf->MultiCell(90, 3, self::toPdfText((string)$object->notificar_endereco), 0, 'L');

        $textomercadoria = "11 cantidad y clase de bultos, marcas y números, tipo de mercancias, contenedores y accesórios Quantidade a categoria de volumes, marcas e números, tipo de mercadorias, conteineres e peças";
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(6, 116);
        $pdf->MultiCell(155, 2, self::toPdfText($textomercadoria));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(6, 120);
        $pdf->MultiCell(155, 3, self::toPdfText((string)$object->descricao_mercadoria), 0, 'L');

        // Dados do lado direito
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 27, self::toPdfText('2 Número / Número'));
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Text(156, 29, self::toPdfText((string)$object->numero));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 34, self::toPdfText('3 Nombre y domicilio del porteador / Nome e endereço do transportador'));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 68, self::toPdfText('5 Lugar y pais de emissão / Localidade e pais de emissão '));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(107, 73, self::toPdfText((string)$object->local_emissao));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(106, 80);
        $pdf->MultiCell(140, 2, self::toPdfText('7 Lugar pais y fecha en que el porteador se hace cargo de las mercancias / 
Localidade pais e data em que o transportador se responsabiliza para mercadoria '));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 94, self::toPdfText('8 Lugar pais y plazo de entrega / Localidade, pais e prazo de entrega'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(107, 99, self::toPdfText((string)$object->local_entrega));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 107, self::toPdfText('10 Porteadores sucesivos / Transportadores sucessivos '));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(107, 112, self::toPdfText((string)$object->transportadores_sucessivos));

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(166, 118, self::toPdfText('12 Peso Bruto kg./Peso bruto kg'));

        $pdf->Text(168, 123, self::toPdfText('PESO BRUTO KG'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(168, 127, (string)$object->peso_bruto_kg);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(168, 130, self::toPdfText('PESO LIQUIDO KG'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(168, 134, (string)$object->peso_liq_kg);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(166, 139, self::toPdfText('13 Volume en m.cu / Volume em m.cu.'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(172, 145, (string)$object->volume_m3);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(166, 150, self::toPdfText('14 Valor / Valor'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(168, 154, (string)$object->incoterm);
        $pdf->Text(168, 160, self::toPdfText(self::formatMoney($object->valor_mercadorias)));
        $pdf->Text(168, 166, self::toPdfText('Moneda / Moeda'));
        $pdf->Text(168, 170, self::toPdfText((string)$object->moeda_valor_mercadorias));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 176, self::toPdfText('16 Declaración del valor de las mercancias / Declaração do valor das mercadorias'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(107, 180, (string)$object->incoterm16);
        $pdf->Text(114, 180, (string)$object->moeda_valor_mercadorias);
        $pdf->Text(124, 180, self::toPdfText(self::formatMoney($object->valor_declarado)));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 186, self::toPdfText('17 Documentos anexos / Documentos anexos'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(107, 190, self::toPdfText('FATURA COMERCIAL Nº ' . $object->fatura_crt));
        $pdf->SetXY(106, 191);
        $pdf->MultiCell(140, 4, self::toPdfText((string)$object->documentos_anexos));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 211, self::toPdfText('18 instruccioner sobre formalidades de aduana / Instru��es sobre formalidades de alf�ndega'));
        $pdf->SetXY(106, 213);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->MultiCell(140, 4, self::toPdfText((string)$object->instrucoes_alfandega));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 236, self::toPdfText('22 Declaraciones y observaciones/ Declarações e observações'));
        $pdf->SetXY(106, 239);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->MultiCell(140, 4, self::toPdfText((string)$object->observacoes));
        $pdf->SetXY(105, 260);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->MultiCell(55, 2, self::toPdfText('24 Nombre y fima del destinatário o su representante Nome e assinatura do destinatário ou seu representante'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(106, 272, self::toPdfText($this->getImportadorNome($object)));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(6, 280, 0);
        $pdf->MultiCell(100, 2, self::toPdfText("Fecha/Data"));
        $pdf->SetFont('Helvetica', '', 8);

        $data_original1   = $object->data_transportador_assinatura;
        $ts_data1 = !empty($data_original1) ? strtotime($data_original1) : false;
        $data_brasileira1 = $ts_data1 ? date('d/m/Y', $ts_data1) : '';
        $pdf->Text(19, 280, self::toPdfText($data_brasileira1));
        $pdf->Text(119, 280, self::toPdfText($data_brasileira1));
        $pdf->Text(19, 244, self::toPdfText($data_brasileira1));
        $pdf->Text(107, 88, self::toPdfText((string)$object->local_responsabilidade.' '.$data_brasileira1));

        $pdf->SetXY(6, 174);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->MultiCell(20, 2, self::toPdfText("15 Gastos a pagar   Gastos a pagar"));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(6, 183, self::toPdfText((string)$object->textogasto1));
        $pdf->Text(6, 190, self::toPdfText((string)$object->textogasto2));
        $pdf->Text(6, 198, self::toPdfText((string)$object->textogasto3));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(32, 174);
        $pdf->MultiCell(19, 2, self::toPdfText("Monto remitente Monto remetente"));
        $pdf->SetFont('Helvetica', '', 8);

        $x_pos = 50;
        $pdf->SetFont('Helvetica', '', 8);
        $custoRem1 = self::formatMoney($object->custoremetente1);
        $custoRem2 = self::formatMoney($object->custoremetente2);
        $custoRem3 = self::formatMoney($object->custoremetente3);
        $totalRem = self::formatMoney($object->total_custo_remetente);
        $pdf->Text($x_pos - $pdf->GetStringWidth($custoRem1), 187, self::toPdfText($custoRem1));
        $pdf->Text($x_pos - $pdf->GetStringWidth($custoRem2), 195, self::toPdfText($custoRem2));
        $pdf->Text($x_pos - $pdf->GetStringWidth($custoRem3), 202, self::toPdfText($custoRem3));
        $pdf->Text($x_pos - $pdf->GetStringWidth($totalRem), 211, self::toPdfText($totalRem));
        $pdf->Text(56, 186, self::toPdfText((string)$object->gastosmoeda));
        $pdf->Text(56, 195, self::toPdfText((string)$object->gastosmoeda));
        $pdf->Text(56, 202, self::toPdfText((string)$object->gastosmoeda));

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(55, 174);
        $pdf->MultiCell(10, 2, self::toPdfText("Moneda moeda"));
        $pdf->SetXY(70, 174);
        $pdf->MultiCell(22, 2, self::toPdfText("Monto destinatario monto destinatario"));
        $pdf->SetFont('Helvetica', '', 8);

        $x_pos = 85;
        $pdf->SetFont('Helvetica', '', 8);
        $custoDest1 = self::formatMoney($object->custodestino1);
        $custoDest2 = self::formatMoney($object->custodestino2);
        $custoDest3 = self::formatMoney($object->custodestino3);
        $totalDest = self::formatMoney($object->total_custo_destinatario);
        $pdf->Text($x_pos - $pdf->GetStringWidth($custoDest1), 187, self::toPdfText($custoDest1));
        $pdf->Text($x_pos - $pdf->GetStringWidth($custoDest2), 195, self::toPdfText($custoDest2));
        $pdf->Text($x_pos - $pdf->GetStringWidth($custoDest3), 202, self::toPdfText($custoDest3));
        $pdf->Text($x_pos - $pdf->GetStringWidth($totalDest), 211, self::toPdfText($totalDest));

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(95, 174);
        $pdf->MultiCell(10, 2, self::toPdfText("Moneda moeda"));
        $pdf->Text(6, 216, self::toPdfText("19-monto del flete extermo/Vakor do frete externo"));
        $pdf->SetFont('Helvetica', '', 8);
        $valorFreteExterno = self::formatMoney($object->valor_frete_externo);
        $pdf->Text(6, 220, !empty($valorFreteExterno) ? self::toPdfText($object->gastosmoeda . ' ' . $valorFreteExterno) : '');
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(6, 223, self::toPdfText("20-monto de rembolso contra entrega/valor de rembolso contra entrega"));
        $pdf->SetFont('Helvetica', '', 8);
        $valorReembolso = self::formatMoney($object->valor_reembolso);
        $pdf->Text(6, 226, !empty($valorReembolso) ? self::toPdfText($object->gastosmoeda . ' ' . $valorReembolso) : '');
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(5, 232);
        $pdf->MultiCell(60, 2, self::toPdfText("21 Nombre y firma del remitente o su representante
Nome e assinatura do remetente ou seu representante"));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(6, 241, self::toPdfText((string)$object->nome_remetente));
        $pdf->SetXY(6, 262);
        $pdf->MultiCell(120, 3, self::toPdfText((string)$object->assinatura_nome));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(6, 244, self::toPdfText("Fecha/Data"));
        $pdf->SetFont('Helvetica', '', 8);

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(05,248);
        $pdf->MultiCell(100, 2, self::toPdfText("Las mercancias consignadas en esta carta de porte fueron recibidas por el porteador aparentemente en buen estado, bajo las condicioner generales que figuran al dorso.\nAs mercadarias consignadas neste conhecimento de transporte foram recebidas pelo transportador aparentemente em bom estado, sob as condições gerais que figuram no verso\n23 Nombre y firma del porteador o su representante\nNome e assinatura do transportador ou seu representante"));

        $pdf->SetFont('Helvetica', '', 8);

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(105,36);  
     //   $nome_transportadora = $permisso->transportadora;
        $pdf->MultiCell(85, 4, self::toPdfText($permisso->dados_documentos), 0, 'L'); 

       

        // Insere o nome no rodapé (ex.: VIA ORIGINAL)
        $pdf->Text(5, 289, self::toPdfText($name));
    }

    /**
     * Gera o PDF e salva o arquivo no diretório de saída
     */
    public function gerarPDFArquivo()
    {
        try {
            // Adiciona a primeira página com o nome "ORIGINAL"
            $this->addPageContent("ORIGINAL");

            // Adiciona a segunda página com o nome "COPIA"
            $this->addPageContent("COPIA");

            $outputDir = 'app/output';
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0775, true);
            }

            $numero            = self::sanitizeFilePart($this->object->numero ?? '', 24);
            $faturaCrt         = self::sanitizeFilePart($this->object->fatura_crt ?? '', 24);
            $nomeDestinatario  = self::sanitizeFilePart($this->getImportadorNome($this->object), 32);
            $localEmissao      = self::sanitizeFilePart($this->object->local_emissao ?? '', 16);
            $paisDestino       = self::sanitizeFilePart($this->object->pais_destino ?? '', 12);

            $parts = array_filter([$numero, $faturaCrt, $nomeDestinatario, $localEmissao, $paisDestino]);
            $baseName = 'CRT_' . implode('_', $parts);
            if ($baseName === 'CRT_') {
                $baseName .= date('Ymd_His');
            }
            $baseName = substr($baseName, 0, 120);
            $nomeArquivo = $baseName . '.pdf';
            $caminhoArquivo = $outputDir . '/' . $nomeArquivo;

            // Gera o arquivo PDF e salva
            $this->pdf->Output('F', $caminhoArquivo);
            TTransaction::close();
            TPage::openFile($caminhoArquivo);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
?>
