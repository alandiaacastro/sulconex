
<?php

class EnlastrePDF
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
            $this->object = new Enlastre($key);

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








// Transacao ja foi aberta no construtor.
// 2) Carrega o registro
// Carrega o objeto Enlastre
$enlastre = $object;
if (!$enlastre || !$enlastre->id) {
    throw new Exception('Enlastre não encontrado ou inválido.');
}

$permisso = new Permisso($object->permisso_id);
// Se quiser buscar registros relacionados ao enlastre, primeiro verifique se a tabela possui essa relação
    $criteria = new TCriteria();
    $criteria->add(new TFilter('id', '=', $enlastre->registro_id)); // ou outro campo relacionado válido

    $repository = new TRepository('registro');
    $registros = $repository->load($criteria);

    foreach ($registros as $registro) {
        // faça algo com os registros relacionados
    }

try {
    // Inicia o objeto FPDF
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 5);
    $pdf->SetMargins(5, 5, 5);
        // Cabeçalho
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Text(12, 14, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'MIC/DTA'));    
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->Text(45, 12, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'MIC DTA - Manifiesto Internacional de Carga por Carretera / Declara��o de Tr�nsito Aduaneiro'));
    $pdf->SetFont('Helvetica', '', 8);   
    $pdf->Text(45, 15, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'MIC DTA - Manifiesto Internacional de Carga por Carretera / Declara��o de Tr�nsito Aduaneiro'));
    // RetÃ¢ngulo geral
    //  $pdf->Rect(10, 30, 190, 250); // borda externa
    // RetÃ¢ngulos de campos conforme layout da imagem
    $pdf->Rect(5, 5, 200, 15); // RETANGULO TITULO
    $pdf->Rect(5, 5, 200, 280); // retangulo folha
    // AJUSTADO
    $pdf->Rect(8, 8, 25, 8); //quadradinho do logo
    // ESQUERDA
   //$pdf->Rect(5, 20, 100, 35);  //1 TRANSPORTADOR
    $pdf->SetXY(5, 20);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '1 Nome e endereço do transportador ');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Nombre y domicilio del porteado');
    $pdf->Write(4, $textoNormal);
    $pdf->SetFont('Helvetica', '', 8);
     $pdf->SetXY(6, 23);
    $pdf->MultiCell(100, 4, mb_convert_encoding($enlastre->transportadora ?? '', 'ISO-8859-1', 'UTF-8'), 0, 'L');
  
    $pdf->SetXY(5, 55);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '2 Cadastro geral de contribuintes');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Rol de contribuyente');
    $pdf->Write(4, $textoNormal);
    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(106, 22, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '3 TrÃ¢nsito aduaneiro'));
    $pdf->SetFont('Helvetica', '', 6);   
    $pdf->Text(106, 24, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '  Tránsito aduanero'));  
    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(151, 22, iconv('UTF-8', 'ISO-8859-1//TRANSLIT',     '4 Nº'));
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Text(162, 29, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($enlastre->numeroenlastre ?? '')));

    $pdf->SetXY(105, 35); 
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '5 Folha');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Hoja');
    $pdf->Write(4, $textoNormal);
     $pdf->SetFont('Helvetica', '', 8);
    $pdf->Text(123, 42, iconv('UTF-8', 'ISO-8859-1//TRANSLIT',     '1 / 1'));
 
    $pdf->SetXY(150, 35);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '6 Data de emissão');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Fecha de emisión');
    $pdf->Write(4, $textoNormal);
    
    $pdf->SetXY(105, 45);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '7 Alf�ndega, cidade e pa�s de partida ');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Aduana, ciudad y país de partida');
    $pdf->Write(4, $textoNormal);
   
    $pdf->SetXY(105, 55);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '8 Cidade e país de destino final');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Ciudad y país de destino final');
    $pdf->Write(4, $textoNormal);
  
    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(6, 68, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '9 CAMINHÃƒO ORIGINAL: Nome e endereÃ§o do proprietÃ¡rio'));
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(6, 70, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '  CAMIÃ“N ORIGINAL: Nombre y domicilio del propietario'));
 
    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(6, 98, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '10 Cadastro geral de contribuintes'));
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(6, 100, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '     Rol del contribuyente'));
    
    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(56, 98, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '11 Placa do caminhão'));
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(56, 100, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '   Placa del camión'));

    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(106, 98, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '17 Cadastro geral de contribuintes'));
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(106, 100, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '     Rol del contribuyente'));
    
    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(151, 98, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '18 Placa do caminhão'));
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(151, 100, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '   Placa del camión'));

    $pdf->SetXY(5, 110);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '12 Marca e número');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Marca y número');
    $pdf->Write(4, $textoNormal);

    $pdf->SetXY(105, 110);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '19 Marca e número');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Marca y número');
    $pdf->Write(4, $textoNormal);

    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(56, 113, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '13 Capacidade de tração (t)'));
    $pdf->SetFont('Helvetica', '', 6);   
    $pdf->Text(56, 115, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '   Capacidad de arrastre (t)'));
    
    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(151, 113, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '20 Capacidade de tração (t)'));
    $pdf->SetFont('Helvetica', '', 6);   
    $pdf->Text(151, 115, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '   Capacidad de arrastre (t)'));

    $pdf->SetXY(05, 125);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '14 Ano');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Año');
    $pdf->Write(4, $textoNormal);

     $pdf->SetXY(105, 125);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '21 Ano');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Año');
    $pdf->Write(4, $textoNormal);

    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(56, 128, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '15'));
    $pdf->SetFont('Helvetica', 'B', 6);   
    $pdf->Text(151, 128, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '22'));

    $pdf->Rect(60, 127, 4, 4); // QUADRINHO SEMI REBOQUE
    $pdf->Rect(85, 127, 4, 4); // QUADRINHO SEMI REBOQUE
    $pdf->Rect(155, 127, 4, 4); // QUADRINHO SEMI REBOQUE
    $pdf->Rect(180, 127, 4, 4); // QUADRINHO SEMI REBOQUE
  
    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(65, 129, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Semi-reboque'));
    $pdf->SetFont('Helvetica', 'B', 6);   
    $pdf->Text(65, 131, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Semiremolque'));
    
    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(90, 129, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Reboque'));
    $pdf->SetFont('Helvetica', 'B', 6);   
    $pdf->Text(90, 131, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Remolque'));

    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(160, 129, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Semi-reboque'));
    $pdf->SetFont('Helvetica', 'B', 6);   
    $pdf->Text(160, 131, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Semiremolque'));

    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(185, 129, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Reboque'));
    $pdf->SetFont('Helvetica', 'B', 6);   
    $pdf->Text(185, 131, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Remolque'));

    $pdf->SetFont('Helvetica', '', 6); 
    $pdf->Text(57, 137, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Placa:'));
    $pdf->SetFont('Helvetica', '', 6); 
    $pdf->Text(152, 137, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Placa:'));

     $pdf->SetFont('Helvetica', 'B', 6); 
    $pdf->Text(06, 143, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '23 Nº do conhecimento'));
    $pdf->SetFont('Helvetica', '', 6); 
    $pdf->Text(07, 145, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '   Nº carta de porte'));
    
    $pdf->SetXY(35, 140);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '24 AlfÃ¢ndega de destino');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Aduana de destino');
    $pdf->Write(4, $textoNormal);

     $pdf->SetXY(105, 140);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '33 Remetente');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Remitente');
    $pdf->Write(4, $textoNormal);

    $pdf->SetXY(05, 155);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '25 Moeda');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Moneda');
    $pdf->Write(4, $textoNormal);

    $pdf->SetXY(35, 155);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '26 Origem das mercadorias');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Origen de las mercancías');
    $pdf->Write(4, $textoNormal);

     $pdf->SetXY(105, 155);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '34 Destinatário');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Destinatario');
    $pdf->Write(4, $textoNormal);
   
    $pdf->SetXY(05, 170);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '27 Valor FOT');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Valor FOT');
    $pdf->Write(4, $textoNormal);

    
    $pdf->SetXY(35, 170);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '28 Frete');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Frete');
    $pdf->Write(4, $textoNormal);


    $pdf->SetXY(70, 170);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '29 Seguro');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Seguro');
    $pdf->Write(4, $textoNormal);

    $pdf->SetXY(105, 170);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '35 Consignatário');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Consignatario');
    $pdf->Write(4, $textoNormal);

   $pdf->SetXY(105, 185);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '36 Documentos anexos ');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Documentos anexos');
    $pdf->Write(4, $textoNormal);

    $pdf->SetFont('Helvetica', 'B', 6); 
    $pdf->Text(06, 188, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '30 Tipo de Volumes'));
    $pdf->SetFont('Helvetica', '', 6); 
    $pdf->Text(07, 190, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '     Tipo de bultos'));

    $pdf->SetFont('Helvetica', 'B', 6); 
    $pdf->Text(36, 188, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '31 Quantidade de volumes'));
    $pdf->SetFont('Helvetica', '', 6); 
    $pdf->Text(36, 190, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '      Cantidad de bultos'));


   $pdf->SetFont('Helvetica', 'B', 6); 
    $pdf->Text(71, 188, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '32 Peso bruto (kg)'));
    $pdf->SetFont('Helvetica', '', 6); 
    $pdf->Text(71, 190, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '     Peso bruto (kg)'));

   
    $pdf->SetXY(05, 200);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '37 Número dos lacres ');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Números de los precintos');
    $pdf->Write(4, $textoNormal);

    $pdf->SetXY(05, 210);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '38 Marcas e número dos volumes, descrição das mercadorias');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Marcas y números de los bultos, descripción de las mercancías');
    $pdf->Write(4, $textoNormal);


$pdf->SetXY(5, 241);
$pdf->SetFont('Helvetica', 'B', 6);
$pdf->MultiCell(0, 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT',
    'Declaramos que as informações prestadas neste Documento são a expressão da verdade, que' . "\n" .
    'os dados referentes às mercadorias foram transcritos exatamente conforme a declaração do,' . "\n" .
    'remetente,os quais são de sua exclusiva responsabilidade, e que esta operação obedece ao ' . "\n" .
    'disposto no Convênio sobre Transporte Internacional Terrestre dos Países do Cone Sul.'
));

$pdf->SetXY(5, 253);
$pdf->SetFont('Helvetica', 'I', 6); // Itálico para diferenciar do português
$pdf->MultiCell(0, 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT',
    'Declaramos que las informaciones prestadas en este Documento son expresión de verdad,que los' . "\n" .
    'datos referentes a las mercancías fueron transcriptos exactamente conforme a la declaración' . "\n" .
    'del remitente, los cuales son de su exclusiva responsabilidad,y que esta operación obedece' . "\n" .
    'a lo dispuesto en el Convenio sobre Transporte Internacional Terrestre de los Países del Cono Sur'));


    $pdf->SetFont('Helvetica', 'B', 6); 
    $pdf->Text(106, 243, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '40 Nº DTA, rota e prazo de transporte'));
    $pdf->SetFont('Helvetica', 'I', 6); 
    $pdf->Text(106, 245, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '   Nº DTA, ruta y plazo de transporte'));

    $pdf->SetXY(5, 268);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '39 Assinatura e carimbo do transportador ');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Firma y sello del porteador');
    $pdf->Write(4, $textoNormal);

    $pdf->SetXY(105, 265);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '41 Assinatura e carimbo da AlfÃ¢ndega de Partida');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Firma y sello de la Aduana de Partida');
    $pdf->Write(4, $textoNormal);

    $pdf->SetXY(105, 66);
    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(106, 68, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '16 CAMINHÃƒO SUBSTITUTO: Nome e endereÃ§o do proprietÃ¡rio'));
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(106, 70, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '  CAMIÃ“N SUBSTITUTO: Nombre y domicilio del propietario'));
    $pdf->SetXY(5, 55);
    $pdf->SetFont('Helvetica', 'B', 6);
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '2 Cadastro geral de contribuintes');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Rol de contribuyente');
    $pdf->Write(4, $textoNormal);

     // DIREITA
    $pdf->Rect(110 ,27 , 4, 4); // QUADRADINHO
    $pdf->Rect(130 ,27 , 4, 4); // QUADRADINHO
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(117, 29, iconv('UTF-8', 'ISO-8859-1//TRANSLIT',     'Sim'));
    $pdf->Text(117, 31, iconv('UTF-8', 'ISO-8859-1//TRANSLIT',     'Si'));
    $pdf->Text(135, 29, iconv('UTF-8', 'ISO-8859-1//TRANSLIT',     'Não'));
    $pdf->Text(135, 31, iconv('UTF-8', 'ISO-8859-1//TRANSLIT',     'No'));
    $pdf->SetFont('Helvetica', '', 6);
    // linhas 
    //verticais 
    $pdf->Line(105, 20, 105, 210); // linha central vertical
    $pdf->Line(55, 95, 55, 140); // linha central vertical
    $pdf->Line(150, 95, 150, 140); // linha central vertical
  
    $pdf->Line(35, 140, 35, 200); // linha central vertical
    $pdf->Line(150, 20, 150, 45);
     $pdf->Line(70, 170, 70, 200); // LINHA VERTICAL
     $pdf->Line(105, 240, 105, 285); // linha central vertical


    //horizontais
 $pdf->Line(105, 35, 205, 35);
    $pdf->Line(5, 55, 205, 55);
    $pdf->Line(5, 65, 205, 65);   // linha horizontal
    $pdf->Line(105, 45, 205, 45);   // linha horizontal
    $pdf->Line(5, 95, 205, 95);  // HORIZONTAL
    $pdf->Line(5, 110, 205, 110);  // HORIZONTAL
    $pdf->Line(5, 125, 205, 125);  // HORIZONTAL
    $pdf->Line(5, 140, 205, 140);  // HORIZONTAL
    $pdf->Line(5, 155, 205, 155);  // HORIZONTAL
    $pdf->Line(5, 170, 205, 170);  // HORIZONTAL
    $pdf->Line(5, 185, 205, 185);  // HORIZONTAL
    $pdf->Line(5, 200, 105, 200);  // HORIZONTAL
    $pdf->Line(5, 210, 205, 210);  // HORIZONTAL
    $pdf->Line(5, 240, 205, 240);  // HORIZONTAL
    $pdf->Line(105, 265, 205, 265);  // HORIZONTAL


    // Defina o diretório onde o PDF será salvo
    $diretorio = 'tmp/';
    
    // Verifica se o diretório existe, e se não, cria com permissões 0775
    if (!file_exists($diretorio)) {
        if (!mkdir($diretorio, 0775, true)) {
            throw new Exception("Falha ao criar o diretório '$diretorio'. Verifique as permissões.");
        }
    }

    // Defina o nome do arquivo de saída
    $baseNome = 'ENLASTRE';  // Ajuste de acordo com sua necessidade
    $caminhoSaida = $diretorio . $baseNome . '.pdf';

    // Se o arquivo já existe, modifica o nome com data e hora para evitar sobrescrita
    if (file_exists($caminhoSaida)) {
        $caminhoSaida = $diretorio . $baseNome . 'ENLASTRE-' . date('dmY-His') . '.pdf';
    }

    // Gera o PDF no servidor
    $pdf->Output('F', $caminhoSaida);

    // Abre o arquivo gerado (exclusivo para Adianti ou outra plataforma)
    TPage::openFile($caminhoSaida);

    // Fecha a transação
    TTransaction::close();

} catch (Exception $e) {
    // Se ocorrer um erro, faz rollback da transação e exibe a mensagem de erro
    TTransaction::rollback();
    new TMessage('error', $e->getMessage());
}
    }
}
