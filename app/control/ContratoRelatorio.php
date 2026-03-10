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
                throw new Exception('Par�metro chave (key) do contrato n�o foi encontrado.');
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
                $pdf->MultiCell($w, $h, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) $text), 0, 'J');
            };
        
            $imprimirValorAlinhado = function($pdf, $margemDireita, $y, $texto) {
                $larguraTexto = $pdf->GetStringWidth($texto);
                $posX = $margemDireita - $larguraTexto;
                $pdf->Text($posX, $y, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) $texto));
            };

            // --- INÃCIO DO CABEÃ‡ALHO AJUSTADO ---
            if (file_exists('app/images/logobranco.png')) {
                $pdf->Image('app/images/logobranco.png',10,5,40,18);
            }
            
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(0, 0, 139);
            $pdf->Text(50, 14, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'COMPROVANTE DE PAGAMENTO DE FRETE'));
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->Text(50, 18, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'INSTRUMENTO DE SUBCONTRATAÃ‡ÃƒO DE TRANSPORTE RODOVIÃRIO'));
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetLineWidth(0.1);

            $pdf->Rect(150, 13, 50, 6, 'D');
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Text(152, 12, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'N° PAGAMENTO'));
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(154, 17, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'CT ' .($object->conhecimento_numero ?? '---').'-'.  str_pad($object->id, 3, '0', STR_PAD_LEFT) . '/' . date('Y')));
         


         // ðŸ”¹ Contratante
$pdf->Rect(10, 25, 70, 26, 'D'); 
$pdf->SetFont('Arial', '', 6);
$pdf->Text(11, 24, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','CONTRATANTE'));
$pdf->SetFont('Arial', '', 8);
$pdf->SetXY(11, 26); 
$pdf->MultiCell(68, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($permisso->dados_documentos ?? '')), 0, 'L');

// ðŸ”¹ Contratado
// Este quadro já define a altura de referência (26).
$pdf->Rect(80, 25, 120, 26, 'D');
$pdf->SetFont('Arial', '', 6);
$pdf->Text(82, 24, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','CONTRATADO'));
$pdf->Text(82, 28, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','MOTORISTA'));
$pdf->SetFont('Arial', 'B', 8);
$pdf->Text(82, 31, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', ($object->veiculo->motorista->nome ?? '') . ' CPF: ' . ($object->veiculo->motorista->cpf ?? '')));

$pdf->SetFont('Arial', '', 6);
$pdf->Text(82, 34, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','PROPRIETARIO'));
$pdf->SetFont('Arial', 'B', 8);
$pdf->Text(82, 37, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', ($object->veiculo->proprietario->razao_social ?? '')));
$pdf->Text(82, 40, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', ( 'CNPJ: ' . ($object->veiculo->proprietario->cnpj ?? ''))));

$pdf->SetFont('Arial', '', 6);
$pdf->Text(82, 44, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'VEICULO'));
$pdf->SetFont('Arial', 'B', 8);
$pdf->Text(82, 47, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', ($object->veiculo->placa_trator ?? '').' / '.($object->veiculo->antt_consulta_semi_reboque->placa ?? '')));

$pdf->SetFont('Arial', '', 6);
$pdf->Text(115, 44, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'CHASSIS TRATOR'));
$pdf->SetFont('Arial', 'B', 8);
$pdf->Text(115, 47, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($object->veiculo->antt_consulta_trator->chassi_motor ?? '')));

$pdf->SetFont('Arial', '', 6);
$pdf->Text(151, 44, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'MARCA/MODELO/ANO'));
$pdf->SetFont('Arial', 'B', 8);
$marcaModeloAno = ($object->veiculo->antt_consulta_trator->marca ?? '') . ' / '  . ($object->veiculo->ano_fabricacao ?? '');
$pdf->Text(151, 47, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $marcaModeloAno));
            // --- FIM DA ALTERAÃ‡ÃƒO ---

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetXY(10, 54);
            $pdf->Cell(0, 0, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '2 - CLÁUSULAS DO CONTRATO'));
            
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetX(10);
            $pdf->Ln(5);

            $clausulas = [];
            $clausulas[] = "ClÃ¡usula Primeira: A CONTRATANTE NÃƒO se responsabiliza em pagar estadias provenientes de greves, paradas operacionais padrÃµes e/ou quaisquer outras paradas ocasionadas por casos fortuitos e forÃ§a maior. NÃ£o serÃ£o devidas as diÃ¡rias ocasionadas por quebra ou qualquer outro motivo que impossibilite o trÃ¡fego do veÃ­culo ora contratado e que seja comprovada a responsabilidade do CONTRATADO, como manutenÃ§Ã£o de equipamentos de seguranÃ§a exigidos por lei, equipamentos para amarraÃ§Ã£o e acondicionamento da carga especificados ou nÃ£o neste contrato, licenÃ§as, ausÃªncia, atraso ou falta de qualquer documentaÃ§Ã£o da carga.";
            $clausulas[] = "Cláusula Segunda: O CONTRATADO fica ciente da tabela abaixo das franquias livres (sem cobrança de estadias). Considerar os seguintes itens: 1º Ingresso nos portos/terminais/cliente deverá ocorrer até as 12h, para ser considerado dia útil ou comercial; 2º Sábados e domingos não serão considerados como dias úteis, exceto se o veículo estiver no porto com 24h de antecedência ao último dia útil.";
            $clausulas[] = "Cláusula Terceira: O CONTRATADO declara expressamente que recebeu a carga e documentação correspondente a este contrato em perfeito estado e quantidade, comprometendo-se a entregar nas mesmas condições em que recebeu, assumindo plena responsabilidade por qualquer dano, avarias ou faltas.";
            $clausulas[] = "Cláusula Quarta: O pagamento do saldo de frete estará condicionado à apresentação do comprovante de entrega da mercadoria devidamente assinado pelo cliente. Em caso de extravio do comprovante, o pagamento ficará suspenso até a obtenção da confirmação de recebimento por parte do destinatário da mercadoria.";
            $clausulas[] = "Cláusula Quinta: Em caso de avaria por molhadura ou outras situações atribuídas à responsabilidade do transportador, é obrigatória, após o retorno da viagem, a apresentação do veículo para a realização de vistoria pela nossa companhia de seguros. A vistoria tem como objetivo verificar se os equipamentos do veículo estão em condições adequadas, sem furos, e se a carroceria encontra-se em perfeito estado de conservação.";
            $clausulas[] = "Cláusula Sexta: Em caso de paradas em postos fiscais ou balanças, caso sejam identificadas irregularidades na documentação ou na mercadoria transportada, o transportador deverá comunicar imediatamente à transportadora, permitindo que esta tome as medidas necessárias junto ao posto fiscal competente. O não cumprimento dessa obrigação, nos termos do art. 663 do Código Civil, exime a transportadora de qualquer responsabilidade pelos prejuízos ocasionados ao transportador em decorrência da omissão na comunicação.";
            $clausulas[] = "Cláusula Sétima: Fica eleito o foro da Comarca de Uruguaiana/RS para dirimir quaisquer dúvidas ou contestações oriundas deste contrato, com renúncia expressa a qualquer outro, por mais privilegiado que seja.";

            foreach ($clausulas as $texto_clausula)
            {
                $pdf->MultiCell(190, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto_clausula), 0, 'J');
                $pdf->Ln(3); // Espaçamento entre as cláusulas
            }
          
              // Tabela de Franquias
            $pdf->SetY(175); // Posição Y ajustada para depois das cláusulas
            $pdf->SetFont('Arial', 'B', 9); $pdf->Text(10, $pdf->GetY(), iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'TABELA DE FRANQUIAS LIVRES (SEM ESTADIAS)'));
            $pdf->Rect(10, $pdf->GetY()+2, 190, 10, 'D');
            $pdf->SetFont('Arial', '', 6); $pdf->Text(11, $pdf->GetY()+4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'MULTILOG')); $pdf->Text(42, $pdf->GetY()+4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'ADUANA ARGENTINA')); $pdf->Text(73, $pdf->GetY()+4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'ADUANA CHILE')); $pdf->Text(104, $pdf->GetY()+4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','DESCARGA')); $pdf->Text(135, $pdf->GetY()+4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','DIARIAS ARGENTINA')); $pdf->Text(166, $pdf->GetY()+4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','DIARIAS CHILE'));
            $pdf->SetFont('Arial', 'B', 10); $pdf->Text(16, $pdf->GetY()+9, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '48H')); $pdf->Text(48, $pdf->GetY()+9, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '48H')); $pdf->Text(79, $pdf->GetY()+9, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','48H')); $pdf->Text(110, $pdf->GetY()+9, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','24H')); $pdf->Text(145, $pdf->GetY()+9, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','R$ 450,00')); $pdf->Text(176, $pdf->GetY()+9, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','R$ 600,00'));

            // Rota e Documentos
            $pdf->SetY($pdf->GetY() + 16);
            $pdf->SetFont('Arial', 'B', 9); $pdf->Text(10, $pdf->GetY(), iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '3 -ROTA PERCURSO E DOCUMENTOS DO EMBARQUE'));
            $pdf->Rect(10, $pdf->GetY()+2, 30, 10, 'D'); $pdf->Rect(41, $pdf->GetY()+2, 50, 10, 'D'); $pdf->Rect(92, $pdf->GetY()+2, 108, 10, 'D');
            $pdf->SetFont('Arial', '', 6); $pdf->Text(16, $pdf->GetY()+4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','CRT')); $pdf->Text(46, $pdf->GetY()+4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','NOTA FISCAL OU MIC/DTA')); $pdf->Text(97, $pdf->GetY()+4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','ROTA PERCURSO'));
            $pdf->SetFont('Arial', 'B', 9); $pdf->Text(14, $pdf->GetY()+9, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($object->conhecimento_numero ?? ''))); $pdf->Text(46, $pdf->GetY()+9, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($object->danfeoumic ?? '')));
            $pdf->SetFont('Arial', '', 9); $pdf->Text(97, $pdf->GetY()+9, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', ' De '.($object->origem1 ?? '').' Até ' .($object->destino1 ?? '')));

            // Recibo
            $pdf->SetY($pdf->GetY() + 16);
            $pdf->SetFont('Arial', 'B', 9); $pdf->Text(10, $pdf->GetY(), iconv('UTF-8', 'ISO-8859-1//TRANSLIT','RECIBO DE PAGAMENTO DE FRETE - CONTRATO '. str_pad($object->id, 3, '0', STR_PAD_LEFT) . '/' . date('Y')));
            $pdf->Rect(10, $pdf->GetY()+2, 60, 31, 'D'); $pdf->Rect(71, $pdf->GetY()+2, 129, 8, 'D'); $pdf->Rect(71, $pdf->GetY()+2, 129, 31, 'D');

            $pdf->SetFont('Arial', '', 9);
            $pdf->Text(11, $pdf->GetY()+6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','(+) VALOR FRETE')); $imprimirValorAlinhado($pdf, 66, $pdf->GetY()+6, 'R$ ' . number_format((float)($object->frete1 ?? 0), 2, ',', '.'));
            $pdf->Text(11, $pdf->GetY()+10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','(-) ADTO FRETE')); $imprimirValorAlinhado($pdf, 66, $pdf->GetY()+10, 'R$ ' . number_format((float)($object->adt1 ?? 0), 2, ',', '.'));
            $pdf->Text(11, $pdf->GetY()+14, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','(-) INSS')); $imprimirValorAlinhado($pdf, 66, $pdf->GetY()+14, 'R$ ' . number_format((float)($object->inss1 ?? 0), 2, ',', '.'));
            $pdf->Text(11, $pdf->GetY()+18, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','(-) IRRF')); $imprimirValorAlinhado($pdf, 66, $pdf->GetY()+18, 'R$ ' . number_format((float)($object->irrf1 ?? 0), 2, ',', '.'));
            $pdf->Text(11, $pdf->GetY()+22, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','(-) SEST/SENAT')); $imprimirValorAlinhado($pdf, 66, $pdf->GetY()+22, 'R$ ' . number_format((float)($object->sest1 ?? 0), 2, ',', '.'));
            $pdf->Text(11, $pdf->GetY()+26, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','(-) DESCONTOS')); $imprimirValorAlinhado($pdf, 66, $pdf->GetY()+26, 'R$ ' . number_format((float)($object->descontos1 ?? 0), 2, ',', '.'));
            $pdf->Text(11, $pdf->GetY()+30, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','(=) SALDO')); $imprimirValorAlinhado($pdf, 66, $pdf->GetY()+30, 'R$ ' . number_format((float)($object->saldo1 ?? 0), 2, ',', '.'));

            $pdf->SetFont('Arial', '',6); $pdf->Text(73, $pdf->GetY()+4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Valor por extenso'));
            $pdf->SetFont('Arial', '',8); $pdf->Text(73, $pdf->GetY()+8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($object->extenso1 ?? '')));
            $pdf->SetFont('Arial', '',6); $pdf->Text(73, $pdf->GetY()+12, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Forma de pagamento'));
            $pdf->SetFont('Arial', '',8); $pdf->SetXY(73, $pdf->GetY()+14); $pdf->MultiCell(120, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($object->pagamento ?? '')), 0, 'L'); 
            
           // Declaração, Data e Assinatura (bloco final)
            $pdf->SetY(250); 
        
        // Texto "Declaro por livre e espontanea vontade..."
        $pdf->SetFont('Arial', '', 9);
        $textocontrato ="Declaro por livre e espontanea vontade que li os termos do contrato e aceito as condições para transporte ref. a este CONTRATO.";
        $pdf->SetX(10);
        $pdf->MultiCell(190, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $textocontrato), 0, 'L');
        
        // Pula uma linha para dar espaço
        $pdf->Ln(15);
        
        // Pega a posição Y atual para alinhar a data e a assinatura
        $y_assinatura = $pdf->GetY();
        
        // Data à esquerda
        $pdf->SetFont('Arial', '', 9);
        $dataFormatada = TDate::convertToMask($object->emissao, 'yyyy-mm-dd', 'dd/m/Y');
        $pdf->Text(14, $y_assinatura, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Uruguaiana, RS ' . $dataFormatada));

        // Bloco da Assinatura à direita
        $pdf->Line(110, $y_assinatura - 2, 190, $y_assinatura - 2);
        $pdf->SetFont('Arial', '', 6);
        $pdf->Text(112, $y_assinatura, iconv('UTF-8', 'ISO-8859-1//TRANSLIT','Assinatura do motorista'));
        $pdf->SetFont('Arial', '', 9);
        $pdf->Text(112, $y_assinatura + 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($object->veiculo->motorista->nome ?? '')));
        $pdf->Text(112, $y_assinatura + 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'CPF: ' . (string) ($object->veiculo->motorista->cpf ?? '')));

            
            // --- FIM DA SEÃ‡ÃƒO AJUSTADA ---

           // Rodapé com o nome da via (agora fixo)
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Text(10, 290, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'ORIGINAL'));
        }

        // Salva e abre o arquivo
       $nomeArquivo = 'app/output/CONTRATO_' . str_pad($object->id, 5, '0', STR_PAD_LEFT) . '_' . $object->veiculo->motorista->nome . '.pdf';   if (!is_dir('app/output') || !is_writable('app/output')) {
            throw new Exception('O diretório app/output não existe ou não tem permissão de escrita.');
        }
        $pdf->Output('F', $nomeArquivo);
        TScript::create("window.open('{$nomeArquivo}', '_blank');");
    }
}