<?php

use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TDate;



/**
 * Classe de Geração do Relatório de Contrato em PDF
 */
class ContratoRelatorio extends TPage
{
    /**
     * Ponto de entrada chamado pela ação da DataGrid.
     */
    public static function onGenerate($param)
    {
        try 
        {
            TTransaction::open('sample');

            if (empty($param['key'])) {
                throw new Exception('Parâmetro chave (key) do contrato não foi encontrado.');
            }

            $contrato = new Contrato($param['key']);

            if (!$contrato) {
                throw new Exception('Contrato não encontrado!');
            }
            
            self::gerarPDF($contrato);

            TTransaction::close();
        }
        catch (Exception $e) 
        {
            new TMessage('error', '<b>Erro ao gerar relatório:</b> ' . $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Constrói e exibe o PDF com base no objeto Contrato.
     */
    private static function gerarPDF($object)
    {
        $pdf = new FPDF();
        
        
        {
            $pdf->AddPage('P', 'A4');
            $pdf->SetAutoPageBreak(true, 10);
            $pdf->SetMargins(10, 10, 10);
            
            $permisso = $object->permisso; // Lazy loading do contratante


            // --- Funções Helper ---
            $justifyText = function($pdf, $text, $x, $y, $w, $h) {
                $pdf->SetXY($x, $y);
                $pdf->MultiCell($w, $h, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) $text), 0, 'J');
            };
        
            $imprimirValorAlinhado = function($pdf, $margemDireita, $y, $texto) {
                $larguraTexto = $pdf->GetStringWidth($texto);
                $posX = $margemDireita - $larguraTexto;
                $pdf->Text($posX, $y, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) $texto));
            };

            $tituloSecao = function($pdf, $y, $texto) {
                $pdf->SetFillColor(235, 238, 242);
                $pdf->Rect(10, $y - 4, 190, 6, 'F');
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetTextColor(30, 30, 30);
                $pdf->Text(12, $y, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) $texto));
                $pdf->SetTextColor(0, 0, 0);
            };

            // --- CABEÇALHO LIMPO ---
            $logoFile = 'app/images/logos/logosulconex2025.png';
            if (!file_exists($logoFile)) {
                $logoFile = 'app/images/logos/logo.png';
            }
            if (file_exists($logoFile)) {
                $pdf->Image($logoFile, 12, 9, 28, 14);
            }

            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->Text(44, 13, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) ($permisso->transportadora ?? 'CONTRATANTE')));

            // Remove a primeira linha de dados_documentos (nome da empresa, já exibido acima)
            $dadosDoc = (string) ($permisso->dados_documentos ?? '');
            $linhasDados = explode("\n", $dadosDoc);
            if (count($linhasDados) > 1) { array_shift($linhasDados); }
            $dadosDocSemNome = implode("\n", $linhasDados);

            $pdf->SetFont('Arial', '', 6.5);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetXY(44, 15);
            $pdf->MultiCell(105, 3, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $dadosDocSemNome), 0, 'L');

            $pdf->SetTextColor(0, 0, 0);

            // Título do documento
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->Text(12, 30, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'COMPROVANTE DE PAGAMENTO DE FRETE'));
            $pdf->SetFont('Helvetica', '', 6);
            $pdf->Text(12, 35, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'INSTRUMENTO DE SUBCONTRATAÇÃO DE TRANSPORTE RODOVIÁRIO'));
            $pdf->SetTextColor(0, 0, 0);

            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(156, 30, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'N° PAGAMENTO'));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Text(156, 35, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'CT ' . ($object->conhecimento_numero ?? '---') . '-' . str_pad($object->id, 3, '0', STR_PAD_LEFT) . '/' . date('Y')));
            $pdf->SetFont('Arial', '', 7);
            $dataFormatada = TDate::convertToMask($object->emissao, 'yyyy-mm-dd', 'dd/mm/Y');
            $pdf->Text(156, 40, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'Uruguaiana, ' . $dataFormatada));

            // Contratante
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(11, 42, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'CONTRATANTE'));
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetXY(11, 44);
            $pdf->MultiCell(73, 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) ($permisso->dados_documentos ?? '')), 0, 'L');

            // Contratado
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(87, 42, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'CONTRATADO'));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Text(87, 47, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', ($object->veiculo->motorista->nome ?? '') . '  CPF: ' . ($object->veiculo->motorista->cpf ?? '')));

            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(87, 51, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'PROPRIETARIO'));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Text(87, 55, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', ($object->veiculo->proprietario->razao_social ?? '')));
            $pdf->Text(87, 59, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'CNPJ: ' . ($object->veiculo->proprietario->cnpj ?? '')));

            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(87, 63, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'VEICULO'));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Text(87, 67, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', ($object->veiculo->placa_trator ?? '') . ' / ' . ($object->veiculo->antt_consulta_semi_reboque->placa ?? '')));

            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(122, 63, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'CHASSIS TRATOR'));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Text(122, 67, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) ($object->veiculo->antt_consulta_trator->chassi_motor ?? '')));

            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(155, 63, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'MARCA/MODELO/ANO'));
            $pdf->SetFont('Arial', 'B', 8);
            $marcaModeloAno = ($object->veiculo->antt_consulta_trator->marca ?? '') . ' / ' . ($object->veiculo->ano_fabricacao ?? '');
            $pdf->Text(155, 67, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $marcaModeloAno));

            $tituloSecao($pdf, 73, '2 - CLÁUSULAS DO CONTRATO');

            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetY(76);
            $pdf->SetX(10);

            $clausulas = [];
            $clausulas[] = "Cláusula Primeira: A CONTRATANTE NÃO se responsabiliza em pagar estadias provenientes de greves, paradas operacionais padrões e/ou quaisquer outras paradas ocasionadas por casos fortuitos e força maior. Não serão devidas as diárias ocasionadas por quebra ou qualquer outro motivo que impossibilite o tráfego do veículo ora contratado e que seja comprovada a responsabilidade do CONTRATADO, como manutenção de equipamentos de segurança exigidos por lei, equipamentos para amarração e acondicionamento da carga especificados ou não neste contrato, licenças, ausência, atraso ou falta de qualquer documentação da carga.";
            $clausulas[] = "Cláusula Segunda: O CONTRATADO fica ciente da tabela abaixo das franquias livres (sem cobrança de estadias). Considerar os seguintes itens: 1º Ingresso nos portos/terminais/cliente deverá ocorrer até as 12h, para ser considerado dia útil ou comercial; 2º Sábados e domingos não serão considerados como dias úteis, exceto se o veículo estiver no porto com 24h de antecedência ao último dia útil.";
            $clausulas[] = "Cláusula Terceira: O CONTRATADO declara expressamente que recebeu a carga e documentação correspondente a este contrato em perfeito estado e quantidade, comprometendo-se a entregar nas mesmas condições em que recebeu, assumindo plena responsabilidade por qualquer dano, avarias ou faltas.";
            $clausulas[] = "Cláusula Quarta: O pagamento do saldo de frete estará condicionado à apresentação do comprovante de entrega da mercadoria devidamente assinado pelo cliente. Em caso de extravio do comprovante, o pagamento ficará suspenso até a obtenção da confirmação de recebimento por parte do destinatário da mercadoria.";
            $clausulas[] = "Cláusula Quinta: Em caso de avaria por molhadura ou outras situações atribuídas à responsabilidade do transportador, é obrigatória, após o retorno da viagem, a apresentação do veículo para a realização de vistoria pela nossa companhia de seguros. A vistoria tem como objetivo verificar se os equipamentos do veículo estão em condições adequadas, sem furos, e se a carroceria encontra-se em perfeito estado de conservação.";
            $clausulas[] = "Cláusula Sexta: Em caso de paradas em postos fiscais ou balanças, caso sejam identificadas irregularidades na documentação ou na mercadoria transportada, o transportador deverá comunicar imediatamente à transportadora, permitindo que esta tome as medidas necessárias junto ao posto fiscal competente. O não cumprimento dessa obrigação, nos termos do art. 663 do Código Civil, exime a transportadora de qualquer responsabilidade pelos prejuízos ocasionados ao transportador em decorrência da omissão na comunicação.";
            $clausulas[] = "Cláusula Sétima: Fica eleito o foro da Comarca de Uruguaiana/RS para dirimir quaisquer dúvidas ou contestações oriundas deste contrato, com renúncia expressa a qualquer outro, por mais privilegiado que seja.";

            foreach ($clausulas as $texto_clausula)
            {
                $pdf->MultiCell(190, 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $texto_clausula), 0, 'J');
                $pdf->Ln(3);
            }

            // Tabela de Franquias
            $y = $pdf->GetY() + 4;
            $tituloSecao($pdf, $y, 'TABELA DE FRANQUIAS LIVRES (SEM ESTADIAS)');
            $y = $y + 3;
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(11,  $y + 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'MULTILOG'));
            $pdf->Text(42,  $y + 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'ADUANA ARGENTINA'));
            $pdf->Text(73,  $y + 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'ADUANA CHILE'));
            $pdf->Text(104, $y + 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE','DESCARGA'));
            $pdf->Text(135, $y + 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE','DIARIAS ARGENTINA'));
            $pdf->Text(166, $y + 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE','DIARIAS CHILE'));
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Text(16,  $y + 9, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', '48H'));
            $pdf->Text(48,  $y + 9, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', '48H'));
            $pdf->Text(79,  $y + 9, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE','48H'));
            $pdf->Text(110, $y + 9, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE','24H'));
            $pdf->Text(145, $y + 9, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE','R$ 450,00'));
            $pdf->Text(176, $y + 9, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE','R$ 600,00'));

            // Rota e Documentos
            $y = $y + 16;
            $tituloSecao($pdf, $y, '3 - ROTA PERCURSO E DOCUMENTOS DO EMBARQUE');
            $y = $y + 3;
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(16, $y + 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE','CRT'));
            $pdf->Text(46, $y + 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE','NOTA FISCAL OU MIC/DTA'));
            $pdf->Text(97, $y + 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE','ROTA PERCURSO'));
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(14, $y + 9, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) ($object->conhecimento_numero ?? '')));
            $pdf->Text(46, $y + 9, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) ($object->danfeoumic ?? '')));
            $pdf->SetFont('Arial', '', 9);
            $pdf->Text(97, $y + 9, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', ' De '.($object->origem1 ?? '').' Até ' .($object->destino1 ?? '')));

            // Recibo
            $y = $y + 16;
            $tituloSecao($pdf, $y, 'RECIBO DE PAGAMENTO DE FRETE - CONTRATO '. str_pad($object->id, 3, '0', STR_PAD_LEFT) . '/' . date('Y'));
            $y = $y + 3;
            // Coluna esquerda: labels X=11, valores alinhados à direita em X=100
            $pdf->SetFont('Arial', '', 9);
            if (!empty($object->frete_tonelada) && $object->frete_tonelada === 'S' && (float)($object->peso_tonelada ?? 0) > 0) {
                $pesoFmt = number_format((float) ($object->peso_tonelada ?? 0), 3, ',', '.');
                $vptFmt  = number_format((float) ($object->valor_por_ton  ?? 0), 2, ',', '.');
                $freteLabel = '(+) FRETE ' . $pesoFmt . 't x R$' . $vptFmt . '/t';
            } else {
                $freteLabel = '(+) VALOR FRETE';
            }
            $pdf->Text(11, $y + 6,  @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $freteLabel));       $imprimirValorAlinhado($pdf, 100, $y + 6,  'R$ ' . number_format((float)($object->frete1 ?? 0), 2, ',', '.'));
            $pdf->Text(11, $y + 11, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', '(-) ADTO FRETE'));  $imprimirValorAlinhado($pdf, 100, $y + 11, 'R$ ' . number_format((float)($object->adt1 ?? 0), 2, ',', '.'));
            $pdf->Text(11, $y + 16, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', '(-) INSS'));        $imprimirValorAlinhado($pdf, 100, $y + 16, 'R$ ' . number_format((float)($object->inss1 ?? 0), 2, ',', '.'));
            $pdf->Text(11, $y + 21, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', '(-) IRRF'));        $imprimirValorAlinhado($pdf, 100, $y + 21, 'R$ ' . number_format((float)($object->irrf1 ?? 0), 2, ',', '.'));
            $pdf->Text(11, $y + 26, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', '(-) SEST/SENAT')); $imprimirValorAlinhado($pdf, 100, $y + 26, 'R$ ' . number_format((float)($object->sest1 ?? 0), 2, ',', '.'));
            $pdf->Text(11, $y + 31, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', '(-) DESCONTOS'));  $imprimirValorAlinhado($pdf, 100, $y + 31, 'R$ ' . number_format((float)($object->descontos1 ?? 0), 2, ',', '.'));
            $pdf->Text(11, $y + 36, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', '(=) SALDO'));      $imprimirValorAlinhado($pdf, 100, $y + 36, 'R$ ' . number_format((float)($object->saldo1 ?? 0), 2, ',', '.'));

            // Coluna direita: Valor por extenso + Forma de pagamento (X=103)
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(103, $y + 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'Valor por extenso'));
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetXY(103, $y + 6);
            $pdf->MultiCell(97, 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) ($object->extenso1 ?? '')), 0, 'L');
            $pdf->SetFont('Arial', '', 6);
            $pdf->SetX(103);
            $pdf->Cell(97, 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'Forma de pagamento'), 0, 1, 'L');
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetX(103);
            $pdf->MultiCell(97, 5, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) ($object->pagamento ?? '')), 0, 'L');
            
           // Declaração, Data e Assinatura (bloco final)
            $pdf->SetY(250); 
        
        // Pula uma linha para dar espaço
        $pdf->Ln(15);
        
        // Pega a posição Y atual para alinhar a data e a assinatura
        $y_assinatura = $pdf->GetY();
        
        // Bloco da Assinatura à direita
        $pdf->Line(110, $y_assinatura - 2, 190, $y_assinatura - 2);
        $pdf->SetFont('Arial', '', 6);
        $pdf->Text(112, $y_assinatura, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE','Assinatura do motorista'));
        $pdf->SetFont('Arial', '', 9);
        $pdf->Text(112, $y_assinatura + 3, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) ($object->veiculo->motorista->nome ?? '')));
        $pdf->Text(112, $y_assinatura + 6, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'CPF: ' . (string) ($object->veiculo->motorista->cpf ?? '')));

            
            // --- FIM DA SEÇÃO AJUSTADA ---

           // Rodapé - via ORIGINAL
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Text(10, 290, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'SALDO DE FRETE'));
        }

        // =====================================================================
        // VIA ADTO DE FRETE
        // =====================================================================
        {
            $pdf->AddPage('P', 'A4');

            // --- CABEÇALHO ---
            $logoFile = 'app/images/logos/logosulconex2025.png';
            if (!file_exists($logoFile)) { $logoFile = 'app/images/logos/logo.png'; }
            if (file_exists($logoFile)) { $pdf->Image($logoFile, 12, 9, 28, 14); }

            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->Text(44, 13, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) ($permisso->transportadora ?? 'CONTRATANTE')));

            $dadosDoc2 = (string) ($permisso->dados_documentos ?? '');
            $linhasDados2 = explode("\n", $dadosDoc2);
            if (count($linhasDados2) > 1) { array_shift($linhasDados2); }
            $pdf->SetFont('Arial', '', 6.5);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetXY(44, 15);
            $pdf->MultiCell(105, 3, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', implode("\n", $linhasDados2)), 0, 'L');
            $pdf->SetTextColor(0, 0, 0);

            // Título - destaque ADTO
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->Text(12, 30, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'COMPROVANTE DE PAGAMENTO DE FRETE'));
            $pdf->SetFont('Helvetica', '', 6);
            $pdf->Text(12, 35, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'INSTRUMENTO DE SUBCONTRATAÇÃO DE TRANSPORTE RODOVIÁRIO'));
            $pdf->SetTextColor(0, 0, 0);

            // N° PAGAMENTO
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(156, 30, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'N° PAGAMENTO'));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Text(156, 35, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'CT ' . ($object->conhecimento_numero ?? '---') . '-' . str_pad($object->id, 3, '0', STR_PAD_LEFT) . '/' . date('Y')));
            $pdf->SetFont('Arial', '', 7);
            $dataFormatada2 = TDate::convertToMask($object->emissao, 'yyyy-mm-dd', 'dd/mm/Y');
            $pdf->Text(156, 40, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'Uruguaiana, ' . $dataFormatada2));

            // CONTRATANTE
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(11, 42, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'CONTRATANTE'));
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetXY(11, 44);
            $pdf->MultiCell(73, 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) ($permisso->dados_documentos ?? '')), 0, 'L');

            // CONTRATADO
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(87, 42, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'CONTRATADO'));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Text(87, 47, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', ($object->veiculo->motorista->nome ?? '') . '  CPF: ' . ($object->veiculo->motorista->cpf ?? '')));
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(87, 51, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'PROPRIETARIO'));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Text(87, 55, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', ($object->veiculo->proprietario->razao_social ?? '')));
            $pdf->Text(87, 59, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'CNPJ: ' . ($object->veiculo->proprietario->cnpj ?? '')));
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(87, 63, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'VEICULO'));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Text(87, 67, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', ($object->veiculo->placa_trator ?? '') . ' / ' . ($object->veiculo->antt_consulta_semi_reboque->placa ?? '')));
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(122, 63, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'CHASSIS TRATOR'));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Text(122, 67, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) ($object->veiculo->antt_consulta_trator->chassi_motor ?? '')));
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(155, 63, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', 'MARCA/MODELO/ANO'));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Text(155, 67, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', ($object->veiculo->antt_consulta_trator->marca ?? '') . ' / ' . ($object->veiculo->ano_fabricacao ?? '')));

            $tituloSecao($pdf, 73, '2 - CLÁUSULAS DO CONTRATO');
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetY(76); $pdf->SetX(10);
            foreach ($clausulas as $texto_clausula) {
                $pdf->MultiCell(190, 4, @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $texto_clausula), 0, 'J');
                $pdf->Ln(3);
            }

            // Tabela Franquias
            $y2 = $pdf->GetY() + 4;
            $tituloSecao($pdf, $y2, 'TABELA DE FRANQUIAS LIVRES (SEM ESTADIAS)');
            $y2 = $y2 + 3;
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(11, $y2+4, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','MULTILOG'));
            $pdf->Text(42, $y2+4, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','ADUANA ARGENTINA'));
            $pdf->Text(73, $y2+4, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','ADUANA CHILE'));
            $pdf->Text(104,$y2+4, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','DESCARGA'));
            $pdf->Text(135,$y2+4, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','DIARIAS ARGENTINA'));
            $pdf->Text(166,$y2+4, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','DIARIAS CHILE'));
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Text(16, $y2+9, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','48H'));
            $pdf->Text(48, $y2+9, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','48H'));
            $pdf->Text(79, $y2+9, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','48H'));
            $pdf->Text(110,$y2+9, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','24H'));
            $pdf->Text(145,$y2+9, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','R$ 450,00'));
            $pdf->Text(176,$y2+9, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','R$ 600,00'));

            // Rota
            $y2 = $y2 + 16;
            $tituloSecao($pdf, $y2, '3 - ROTA PERCURSO E DOCUMENTOS DO EMBARQUE');
            $y2 = $y2 + 3;
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(16, $y2+4, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','CRT'));
            $pdf->Text(46, $y2+4, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','NOTA FISCAL OU MIC/DTA'));
            $pdf->Text(97, $y2+4, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','ROTA PERCURSO'));
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(14, $y2+9, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE', (string)($object->conhecimento_numero ?? '')));
            $pdf->Text(46, $y2+9, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE', (string)($object->danfeoumic ?? '')));
            $pdf->SetFont('Arial', '', 9);
            $pdf->Text(97, $y2+9, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE', ' De '.($object->origem1 ?? '').' Até '.($object->destino1 ?? '')));

            // Recibo ADTO - apenas ADTO e SALDO
            $y2 = $y2 + 16;
            $tituloSecao($pdf, $y2, 'RECIBO DE PAGAMENTO DE FRETE - CONTRATO '. str_pad($object->id, 3, '0', STR_PAD_LEFT) . '/' . date('Y'));
            $y2 = $y2 + 3;
            $pdf->SetFont('Arial', '', 9);
            $pdf->Text(11, $y2+8,  @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE', '(-) ADTO FRETE')); $imprimirValorAlinhado($pdf, 100, $y2+8,  'R$ ' . number_format((float)($object->adt1 ?? 0), 2, ',', '.'));
            $pdf->Text(11, $y2+14, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE', '(=) SALDO'));       $imprimirValorAlinhado($pdf, 100, $y2+14, 'R$ ' . number_format((float)($object->saldo1 ?? 0), 2, ',', '.'));

            // Extenso e pagamento à direita
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(103, $y2+4, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE', 'Valor por extenso'));
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetXY(103, $y2+6);
            $pdf->MultiCell(97, 4, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE', (string)($object->extenso1 ?? '')), 0, 'L');
            $pdf->SetFont('Arial', '', 6); $pdf->SetX(103);
            $pdf->Cell(97, 4, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE', 'Forma de pagamento'), 0, 1, 'L');
            $pdf->SetFont('Arial', '', 8); $pdf->SetX(103);
            $pdf->MultiCell(97, 5, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE', (string)($object->pagamento ?? '')), 0, 'L');

            // Assinatura
            $pdf->SetY(250);
            $pdf->Ln(15);
            $y_ass2 = $pdf->GetY();
            $pdf->Line(110, $y_ass2 - 2, 190, $y_ass2 - 2);
            $pdf->SetFont('Arial', '', 6);
            $pdf->Text(112, $y_ass2, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE','Assinatura do motorista'));
            $pdf->SetFont('Arial', '', 9);
            $pdf->Text(112, $y_ass2+3, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE', (string)($object->veiculo->motorista->nome ?? '')));
            $pdf->Text(112, $y_ass2+6, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE', 'CPF: '.(string)($object->veiculo->motorista->cpf ?? '')));

            // Rodapé - via ADTO com valores
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Text(10, 286, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE', 'VIA ADTO DE FRETE'));
            $pdf->SetFont('Arial', '', 8);
            $pdf->Text(10, 291, @iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE',
                'ADTO: R$ ' . number_format((float)($object->adt1 ?? 0), 2, ',', '.') .
                '     SALDO: R$ ' . number_format((float)($object->saldo1 ?? 0), 2, ',', '.')
            ));
        }

        // Salva e abre o arquivo
        $outputDir = 'tmp';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $motoristaNome = (string) ($object->veiculo->motorista->nome ?? 'MOTORISTA');
        $motoristaNome = preg_replace('/[^A-Za-z0-9 _-]+/', '', $motoristaNome);
        $motoristaNome = trim(preg_replace('/\s+/', ' ', $motoristaNome));
        $motoristaNome = str_replace(' ', '_', $motoristaNome);

        $nomeArquivo = $outputDir . '/CONTRATO_' . str_pad($object->id, 5, '0', STR_PAD_LEFT) . '_' . $motoristaNome . '.pdf';
        $pdf->Output('F', $nomeArquivo);
        TPage::openFile($nomeArquivo);
    }
}
