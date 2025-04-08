<?php

class ConhecimentoPDFGenerator
{
    private $object;
    private $pdf;

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
        $pdf->Image('app/images/CRT.jpg', 6, 6, 18, 18);
        $pdf->Image('app/images/assinatura2.jpg', 56, 255, 35, 25);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Image('app/images/' . $object->logotransporte, 150, 48, 45, 20);

        // Texto do cabeçalho
        $texto = "El transporte realizado bajo esta carta de ponte Internacional está sujeto a las disposições del Convenio sobre el contrato de transporte y la responsabilidad Civil del porteador en el Transportes Terrestre Internacional de Mercancias.las cuales anulan toda estipulação que se aparte de ellas en prejuicio del remitente o del consignatário.O transporte realizado ao amparo deste Conheçimento de Transporte Internacional está sujeto às disposições del Convênio sobre o Contrato de Transporte e a Responsabilidade Civil do Transportador no transporte terrestre internacional.de mercadorias, as cuales anulam toda especulação contrária às mesmas en prejuicio del remitente o del consignatário";
        $pdf->SetXY(71, 8);
        $pdf->MultiCell(0, 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto), 0, 'J');

        $pdf->SetFont('Helvetica', '', 10);
        $texto2 = "Carta de Porte Internacional por carretera               Conhecimento de Transporte Internacional por Rodovia";
        $pdf->SetXY(23, 7);
        $pdf->MultiCell(50, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto2), 0, 'L');
        $pdf->Rect(5, 5, 200, 20); // Cabeçalho CRT

        // Desenho dos retângulos (campos e divisões)
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
        $pdf->Text(6, 27, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '1 Nombre y domicilio del remitente / Nome e endereço do remetente'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(6, 28);
        $pdf->MultiCell(90, 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->endereco_remetente), 0, 'L');

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(6, 52, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '4 Nombre y domicilio del destinatário / Nome e endereço do destinatário'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(6, 53);
        $pdf->MultiCell(90, 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->endereco_destinatario), 0, 'L');

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(6, 74, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '6 Nombre y domicilio del consignatário / Nome e endereço do consignatário'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(6, 76);
        $pdf->MultiCell(90, 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->endereco_consignatario), 0, 'L');

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(6, 96, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '9 Notificar a / Notificar a '));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(6, 98);
        $pdf->MultiCell(90, 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->notificar_endereco), 0, 'L');

        $textomercadoria = "11 cantidad y clase de bultos, marcas y números, tipo de mercancias, contenedores y accesórios Quantidade a categoria de volumes, marcas e números, tipo de mercadorias, conteineres e peças";
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(6, 116);
        $pdf->MultiCell(155, 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $textomercadoria));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(6, 120);
        $pdf->MultiCell(155, 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->descricao_mercadoria), 0, 'L');

        // Dados do lado direito
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 27, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '2 Número / Número'));
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Text(156, 29, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->numero));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 34, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '3 Nombre y domicilio del porteador / Nome e endereço do transportador'));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 68, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '5 Lugar y pais de emissão / Localidade e pais de emissão '));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(107, 73, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->local_emissao));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(106, 80);
        $pdf->MultiCell(140, 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '7 Lugar pais y fecha en que el porteador se hace cargo de las mercancias / 
Localidade pais e data em que o transportador se responsabiliza para mercadoria '));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 94, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '8 Lugar pais y plazo de entrega / Localidade, pais e prazo de entrega'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(107, 99, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->local_entrega));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 107, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '10 Porteadores sucesivos / Transportadores sucessivos '));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(107, 112, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->transportadores_sucessivos));

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(166, 118, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '12 Peso Bruto kg./Peso bruto kg'));

        $pdf->Text(168, 123, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'PESO BRUTO KG'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(168, 127, (string)$object->peso_bruto_kg);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(168, 130, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'PESO LIQUIDO KG'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(168, 134, (string)$object->peso_liq_kg);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(166, 139, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '13 Volume en m.cu / Volume em m.cu.'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(180, 145, (string)$object->volume_m3);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(166, 150, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '14 Valor / Valor'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(168, 154, (string)$object->incoterm);
        $pdf->Text(168, 160, (string)$object->valor_mercadorias);
        $pdf->Text(168, 166, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Moneda / Moeda'));
        $pdf->Text(168, 170, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->moeda_valor_mercadorias));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 176, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '16 Declaración del valor de las mercancias / Declaração do valor das mercadorias'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(107, 180, (string)$object->incoterm16);
        $pdf->Text(114, 180, (string)$object->moeda_valor_mercadorias);
        $pdf->Text(124, 180, (string)$object->valor_declarado);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 186, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '17 Documentos anexos / Documentos anexos'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(107, 190, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'FATURA COMERCIAL Nº ' . $object->fatura_crt));
        $pdf->SetXY(106, 191);
        $pdf->MultiCell(140, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->documentos_anexos));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 211, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '18 instruccioner sobre formalidades de aduana / Instruções sobre formalidades de alfândega'));
        $pdf->SetXY(106, 213);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->MultiCell(140, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->instrucoes_alfandega));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(106, 236, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '22 Declaraciones y observaciones/ Declarações e observações'));
        $pdf->SetXY(106, 239);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->MultiCell(140, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->observacoes));
        $pdf->SetXY(105, 260);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->MultiCell(55, 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '24 Nombre y fima del destinatário o su representante Nome e assinatura do destinatário ou seu representante'));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(106, 272, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->nome_destinatario));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(6, 280, 0);
        $pdf->MultiCell(100, 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "Fecha/Data"));
        $pdf->SetFont('Helvetica', '', 8);

        $data_original1   = $object->data_transportador_assinatura;
        $data_brasileira1 = date('d/m/Y', strtotime($data_original1));
        $pdf->Text(19, 280, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $data_brasileira1));
        $pdf->Text(119, 280, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $data_brasileira1));
        $pdf->Text(19, 244, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $data_brasileira1));
        $pdf->Text(107, 88, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->local_responsabilidade.' '.$data_brasileira1));

        $pdf->SetXY(6, 174);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->MultiCell(20, 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "15 Gastos a pagar   Gastos a pagar"));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(6, 183, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->textogasto1));
        $pdf->Text(6, 190, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->textogasto2));
        $pdf->Text(6, 198, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->textogasto3));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(32, 174);
        $pdf->MultiCell(19, 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "Monto remitente Monto remetente"));
        $pdf->SetFont('Helvetica', '', 8);

        $x_pos = 50;
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text($x_pos - $pdf->GetStringWidth((string)$object->custoremetente1), 187, 
            !empty($object->custoremetente1) ? (string)$object->custoremetente1 : '');
        $pdf->Text($x_pos - $pdf->GetStringWidth((string)$object->custoremetente2), 195, 
            !empty($object->custoremetente2) ? (string)$object->custoremetente2 : '');
        $pdf->Text($x_pos - $pdf->GetStringWidth((string)$object->custoremetente3), 202, 
            !empty($object->custoremetente3) ? (string)$object->custoremetente3 : '');
        $pdf->Text($x_pos - $pdf->GetStringWidth((string)$object->total_custo_remetente), 211, 
            !empty($object->total_custo_remetente) ? (string)$object->total_custo_remetente : '');
        $pdf->Text(56, 186, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->gastosmoeda));
        $pdf->Text(56, 195, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->gastosmoeda));
        $pdf->Text(56, 202, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->gastosmoeda));

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(55, 174);
        $pdf->MultiCell(10, 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "Moneda moeda"));
        $pdf->SetXY(70, 174);
        $pdf->MultiCell(22, 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "Monto destinatario monto destinatario"));
        $pdf->SetFont('Helvetica', '', 8);

        $x_pos = 85;
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text($x_pos - $pdf->GetStringWidth((string)$object->custodestino1), 187, 
            !empty($object->custodestino1) ? (string)$object->custodestino1 : '');
        $pdf->Text($x_pos - $pdf->GetStringWidth((string)$object->custodestino2), 195, 
            !empty($object->custodestino2) ? (string)$object->custodestino2 : '');
        $pdf->Text($x_pos - $pdf->GetStringWidth((string)$object->custodestino3), 202, 
            !empty($object->custodestino3) ? (string)$object->custodestino3 : '');
        $pdf->Text($x_pos - $pdf->GetStringWidth((string)$object->total_custo_destinatario), 211, 
            !empty($object->total_custo_destinatario) ? (string)$object->total_custo_destinatario : '');

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(95, 174);
        $pdf->MultiCell(10, 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "Moneda moeda"));
        $pdf->Text(6, 216, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "19-monto del flete extermo/Vakor do frete externo"));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(6, 220, !empty($object->valor_frete_externo) ? $object->gastosmoeda . (string)$object->valor_frete_externo : '');
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(6, 223, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "20-monto de rembolso contra entrega/valor de rembolso contra entrega"));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(6, 226, !empty($object->valor_reembolso) ? $object->gastosmoeda . (string)$object->valor_reembolso : '');
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(5, 232);
        $pdf->MultiCell(60, 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "21 Nombre y firma del remitente o su representante
Nome e assinatura do remetente ou seu representante"));
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(6, 241, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->nome_remetente));
        $pdf->SetXY(6, 262);
        $pdf->MultiCell(120, 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$object->assinatura_nome));
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->Text(6, 244, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "Fecha/Data"));
        $pdf->SetFont('Helvetica', '', 8);

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetXY(05,248);
        $pdf->MultiCell(100, 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "Las mercancias consignadas en esta carta de porte fueron recibidas por el porteador aparentemente en buen estado, bajo las condicioner generales que figuran al dorso.\nAs mercadarias consignadas neste conhecimento de transporte foram recebidas pelo transportador aparentemente em bom estado, sob as condições gerais que figuram no verso\n23 Nombre y firma del porteador o su representante\nNome e assinatura do transportador ou seu representante"));

        $pdf->SetFont('Helvetica', '', 8);

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(105,36);  
        $pdf->MultiCell(85, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $object->nome_transportador), 0, 'L'); 

        if (!empty($object->permisso->logo)) {
            // Debug: exibe o conteúdo do campo logotransporte
            TTransaction::log("Valor de logotransporte: " . $object->logotransporte);
            
            // Decodifica o JSON contido em logotransporte
            $logoData = json_decode($object->logotransporte, true);
            
            if (isset($logoData['fileName']) && !empty($logoData['fileName'])) {
                $caminhoLogo = 'app/images/' . $logoData['fileName'];
                TTransaction::log("Caminho da imagem: " . $caminhoLogo);
                
                // Verifica se o arquivo existe no caminho especificado
                if (file_exists($caminhoLogo)) {
                    $pdf->Image($caminhoLogo, 140, 15, 80);
                } else {
                    TTransaction::log("Imagem não encontrada: " . $caminhoLogo);
                    echo "Imagem não encontrada: " . $caminhoLogo;
                }
            } else {
                TTransaction::log("Chave 'fileName' não encontrada no JSON.");
                echo "Chave 'fileName' não encontrada no JSON.";
            }
        } else {
            TTransaction::log("Campo logotransporte vazio.");
            echo "Campo logotransporte vazio.";
        }

        // Insere o nome no rodapé (ex.: VIA ORIGINAL)
        $pdf->Text(5, 289, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $name));
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

            // Sanitiza os componentes para o nome do arquivo
            $invalidChars      = ['/', '\\', ':', '*', '?', '"', '<', '>', '|'];
            $numero            = str_replace($invalidChars, '_', $this->object->numero);
            $fatura_crt        = str_replace($invalidChars, '_', $this->object->fatura_crt);
            $nome_destinatario = str_replace($invalidChars, '_', $this->object->nome_destinatario);
            $local_emissao     = str_replace($invalidChars, '_', substr($this->object->local_emissao, 0, 4));
            $pais_destino      = str_replace($invalidChars, '_', $this->object->pais_destino);

            $nomeArquivo = "CRT_{$numero}_{$fatura_crt}_{$nome_destinatario}_{$local_emissao}_{$pais_destino}.pdf";

            // Gera o arquivo PDF e salva
            $this->pdf->Output('F', 'app/output/' . $nomeArquivo);
            TPage::openFile('app/output/' . $nomeArquivo);
            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
?>
