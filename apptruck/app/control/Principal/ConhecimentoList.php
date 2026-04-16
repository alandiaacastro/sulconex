<?php
// [class-head]

// [/class-head]

/**
 * ConhecimentoList
 * Conhecimento
 */
class ConhecimentoList extends TPage
{
    private AdiantiXMLFormRender $ui;
    private TDataGrid $datagrid;
    private TPageNavigation $pageNavigation;
    private TForm $search;
    private static $form_name = 'form_ConhecimentoList';
    
    // import traits
    use AdiantiCreatorListTraits;
    
    // [class-body]

    // [/class-body]
    
    /**
     * Constructor
     * @author Creator
     */
    public function __construct($param)
    {
        parent::__construct();
        
        $this->setDatabase('Principal'); // defines the database
        $this->setActiveRecord('Conhecimento'); // defines the active record
        $this->setDefaultOrder('id', 'desc');  // defines the default order
        $this->setLimit(20);
        
        
        try
        {
            $this->ui = new AdiantiXMLFormRender;
            $this->ui->setController(__CLASS__);
            $this->ui->setPageName('Conhecimento');
            $this->ui->enableForm();
            
            TTransaction::open('Principal');
            $this->ui->parseFile('app/forms/Principal/ConhecimentoList.xml');
            TTransaction::close();
            
            $this->datagrid = $this->ui->getDatagrid();
            $this->setExportedObject($this->datagrid);
            $this->setLoaderObject($this->datagrid);
            
            if ($this->datagrid->getPageNavigation())
            {
                $this->pageNavigation = $this->datagrid->getPageNavigation();
                $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
            }
            if ($this->datagrid->getSearchForm())
            {
                $this->search = $this->datagrid->getSearchForm();
                $this->search->getField('search_button')->setAction(new TAction([__CLASS__, 'onSearch']));
                $this->search->setData( TSession::getValue(__CLASS__.'_filter_data') );
            }
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
        
        parent::add( $this->packUI( true ) );
        
        parent::callIfExists('onAfterConstruct', $param);
    }//end-of-__construct()
    
    /**
     * onShowFilters()
     * @author Creator
     */
    public function onShowFilters($param)
    {
        self::showInRightPanel($this->search);
    }//end-of-onShowFilters()
    
    /**
     * onSelectColumns()
     * @author Creator
     */
    public function onSelectColumns($param)
    {
        $this->selectColumns($param);
    }//end-of-onSelectColumns()
    
    /**
     * onChangeLimit()
     * @author Creator
     */
    public static function onChangeLimit($param)
    {
        self::changeLimit($param);
    }//end-of-onChangeLimit()
    
    /**
     * onQuickSearch()
     * @author Creator
     */
    public static function onQuickSearch($param)
    {
        self::quickSearch($param);
    }//end-of-onQuickSearch()
    
    /**
     * onExportPDF()
     * @author Creator
     */
    public function onExportPDF($param)
    {
        $output = $this->exportToPDF($param);
        self::showInWindow(self::embedPDFObject($output), 'Conhecimento');
    }//end-of-onExportPDF()
    
    /**
     * onExportXLS()
     * @author Creator
     */
    public function onExportXLS($param)
    {
        $output = $this->exportToXLS($param);
        self::downloadFile($output);
    }//end-of-onExportXLS()
    
    /**
     * reload()
     * @author Creator
     */
    private function reload($param)
    {
        try
        {
            TTransaction::open('Principal');
            
            $objects = $this->loadObjectsFromFilters($param);
            $this->datagrid->clear();
            if ($objects)
            {
                foreach ($objects as $object)
                {
                    $row = $this->datagrid->addItem($object);
                    $row->{'data-key'} = $object->getPrimaryKeyValue();
                }
            }
            
            $this->configurePageNavigation($param);
            
            TTransaction::close();
            return $objects;
        }
        catch (Exception $e) // in case of exception
        {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }//end-of-reload()
    
    /**
     * onLoad()
     * @author Creator
     */
    public function onLoad($param)
    {
    
    }//end-of-onLoad()
    
    
    /**
     * onDelete()
     */
    public function onDelete($param)
    {
        $this->confirmDeletion($param);
    }//end-of-onDelete()
    
    /**
     * onSearch()
     */
    public function onSearch($param)
    {
        $this->buildSessionFilters($param);
        $this->onReload( ['offset'=>0, 'first_page'=>1] );
    }//end-of-onSearch()
    
    /**
     * onReload()
     */
    public function onReload($param)
    {
        $this->reload($param);
    }//end-of-onReload()
    
    /**
     * onprint_crt()
     */
    public function onprint_crt($param)
    {
try {
// 1) Abre a transação
TTransaction::open('Principal');

// 2) Carrega o Conhecimento
$conhecimento = new Conhecimento($param['key'] ?? null);
if (!$conhecimento || !$conhecimento->id) {
    throw new Exception('CRT não encontrado ou inválido.');
}

// 3) Inicializa PDF
$pdf = new FPDF();
$pdf->AliasNbPages();
$pdf->SetMargins(15, 15, 15); // Margens: esquerda, topo, direita
$pdf->SetAutoPageBreak(true, 20); // 20mm de margem inferior (297-20=277)
$pdf->SetFont('Helvetica', '', 10);
   


// 4) Busca logo do Registro
$caminhoLogoRegistro = null;
if (!empty($conhecimento->permisso)) {
    $criteria = new TCriteria();
    $criteria->add(new TFilter('codigo', '=', $conhecimento->permisso));
    $repository = new TRepository('registro');
    $lista = $repository->load($criteria);
    $registro = $lista[0] ?? null;

    if ($registro && trim($registro->logo) !== '') {
        $logoRelativo = ltrim($registro->logo, '/');
        $raizProjeto = realpath(__DIR__ . '/../../..');
        $caminhoLogoRegistro = $raizProjeto . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $logoRelativo);

        if (!file_exists($caminhoLogoRegistro)) {
            throw new Exception("Logo não encontrado em: {$caminhoLogoRegistro}");
        }
    }
}

// Função para gerar o conteúdo da página
function generatePageContent($pdf, $caminhoLogoRegistro, $registro, $conhecimento) {
    // 5) Cabeçalho: logo e retângulo superior
    if ($caminhoLogoRegistro && file_exists($caminhoLogoRegistro)) {
        $pdf->Image($caminhoLogoRegistro, 155, 46, 40, 18); // Logotipo no canto superior direito
    }

    // 6) Nome da transportadora no canto superior esquerdo
    if ($registro && !empty($registro->transportadora)) {
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(105, 35); // Ajuste para não colidir com o logo
        $pdf->MultiCell(145, 4, mb_convert_encoding($registro->transportadora, 'ISO-8859-1', 'UTF-8'), 0, 'L');
    }

    // 7) Retângulo no topo da página
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->Rect(5, 5, 195, 20, 'D');

    // 8) Texto legal multilinha no cabeçalho
    $pdf->SetFont('Helvetica', '', 6);
    $textoLegal = 
        "El transporte realizado bajo esta carta de porte Internacional está sujeto a las disposiciones del Convenio sobre el contrato de transporte "
        . "y la responsabilidad Civil del porteador en el Transporte Terrestre Internacional de Mercancías, las cuales anulan toda estipulación que se aparte "
        . "de ellas en perjuicio del remitente o del consignatário.\n"
        . "O transporte realizado ao amparo deste Conhecimento de Transporte Internacional está sujeito às disposições do Convênio sobre o Contrato de Transporte "
        . "e a Responsabilidade Civil do Transportador no transporte terrestre internacional de mercadorias, as quais anulam toda estipulação contrária às mesmas "
        . "em prejuízo do remetente ou do consignatário.";
    $pdf->SetXY(84, 7);
    $pdf->MultiCell(110, 2, mb_convert_encoding($textoLegal, 'ISO-8859-1', 'UTF-8'), 0, 'L');

    // 9) Título do CRT
    $pdf->SetFont('Helvetica', '', 10);
    $textoTitulo = 
        "Carta de Porte Internacional por carretera\n"
        . "Conhecimento de Transporte Internacional por Rodovia";
    $pdf->SetXY(30, 7);
    $pdf->MultiCell(50, 4, mb_convert_encoding($textoTitulo, 'ISO-8859-1', 'UTF-8'), 0, 'L');

    // 10) Desenha os retângulos principais
    // Esquerda
    $pdf->Rect(5, 25, 100, 20, 'D');
    $pdf->Rect(5, 45, 100, 20, 'D');
    $pdf->Rect(5, 65, 100, 20, 'D');
    $pdf->Rect(5, 85, 100, 20, 'D');
    $pdf->Rect(5, 105, 150, 58, 'D');  // linha grande
    $pdf->Rect(5, 163, 25, 40, 'D');
    $pdf->Rect(30, 163, 25, 40, 'D');
    $pdf->Rect(55, 163, 10, 40, 'D');
    $pdf->Rect(65, 163, 30, 40, 'D');
    $pdf->Line(5, 198, 105, 198);
    
    $pdf->Rect(5, 203, 100, 10, 'D');
    $pdf->Rect(5, 213, 100, 10, 'D');
    $pdf->Rect(5, 223, 100, 16, 'D');
    $pdf->Rect(5, 239, 100, 44, 'D');
    // Direita (ajustado para 95 mm)
    $pdf->Rect(105, 25, 95, 7, 'D');
    $pdf->Rect(105, 32, 95, 35, 'D');
    $pdf->Rect(105, 67, 95,  8, 'D');
    $pdf->Rect(105, 75, 95, 11, 'D');
    $pdf->Rect(105, 86, 95, 10, 'D');
    $pdf->Rect(105, 96, 95, 9, 'D'); //xx
    $pdf->Rect(155, 105, 45, 21, 'D');
    $pdf->Rect(155, 126, 45, 11, 'D');
    $pdf->Rect(155, 137, 45, 26, 'D');
    $pdf->Rect(105, 163, 95, 10, 'D');
    $pdf->Rect(105, 173, 95, 30, 'D');//ok
    $pdf->Rect(105, 203, 95, 30, 'D');
    $pdf->Rect(105, 233, 95, 30, 'D');
    $pdf->Rect(105, 263, 95, 20, 'D');


// esquerda 
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(6, 27, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '1 Nombre y domicilio del remitente / Nome e endereço do remetente'));
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetXY(6, 28);
    $pdf->MultiCell(90, 3, mb_convert_encoding($conhecimento->endereco_remetente ?? '', 'ISO-8859-1', 'UTF-8'), 0, 'L');
  
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(6, 47, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '4 Nombre y domicilio del destinatário / Nome e endereço do destinatário'));   
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetXY(6, 48);
    $pdf->MultiCell(90, 3, mb_convert_encoding($conhecimento->endereco_destinatario ?? '', 'ISO-8859-1', 'UTF-8'), 0, 'L');
   
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(6, 67, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '6 Nombre y domicilio del consignatário / Nome e endereço do consignatário'));
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetXY(6, 68);
    $pdf->MultiCell(90, 3, mb_convert_encoding($conhecimento->endereco_consignatario ?? '', 'ISO-8859-1', 'UTF-8'), 0, 'L');

    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(6, 87, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '9 Notificar a / Notificar a '));
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetXY(6, 88);
    $pdf->MultiCell(90, 3, mb_convert_encoding($conhecimento->notificar_endereco ?? '', 'ISO-8859-1', 'UTF-8'), 0, 'L');

    $pdf->SetFont('Helvetica', '', 6);
    $pdf->SetXY(5, 106);
    $pdf->MultiCell(155, 2, mb_convert_encoding(
        "11 cantidad y clase de bultos, marcas y números, tipo de mercancias, contenedores y accesórios \n" .
        "Quantidade a categoria de volumes, marcas e números, tipo de mercadorias, conteineres e peças",
        'ISO-8859-1', 'UTF-8'
    ));
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetXY(6, 110);
    $pdf->MultiCell(150, 3, mb_convert_encoding($conhecimento->descricao_mercadoria ?? '', 'ISO-8859-1', 'UTF-8'), 0, 'L');

    // 12) Cabeçalho direito fixo (Transportadora)
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetXY(105, 36);

    // 13) Preenche campos (direita)
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(106, 27, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '2 Número / Número'));
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Text(156, 29, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->numero ?? '')));

    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(106, 34, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '3 Nombre y domicilio del porteador / Nome e endereço do transportador'));

    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(106, 69, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '5 Lugar y pais de emissão / Localidade e pais de emissão '));
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->Text(106, 73, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->local_emissao ?? '')));   

    $pdf->SetFont('Helvetica', '', 8);
    $dataOriginal = $conhecimento->data_emissao ?? null;
    $dataBrasileira = $dataOriginal ? date('d/m/Y', strtotime($dataOriginal)) : '';

    $pdf->SetFont('Helvetica', '', 6);
    $pdf->SetXY(105, 75);
    $pdf->MultiCell(90, 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 
        "7 Lugar pais y fecha en que el porteador se hace cargo de las mercancias \n ".
         "Localidade pais e data em que o transportador se responsabiliza para mercadoria "));
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->Text(106, 85, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) (($conhecimento->local_responsabilidade ?? '') . ' ' . $dataBrasileira)));

    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(106, 89, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '8 Lugar, país y plazo de entrega / Localidade, país e prazo de entrega '));
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->Text(106, 93, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->local_entrega ?? '')));   

    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(106, 98, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '10 Porteadores sucesivos / Transportadores Sucessivos'));
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->Text(106, 102, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->transportadores_sucessivos ?? '')));  

    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(156, 107, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '12 Peso bruto en Kg/Peso bruto em Kg.'));
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->Text(158, 112, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Peso Bruto'));
    $pdf->Text(158, 120, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Peso liquido'));
   $pdf->SetFont('Helvetica', '', 8); // Define a fonte usada
   $textoBruto = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($conhecimento->peso_bruto_kg ?? 0, 3, ',', '.') . ' KGS');
   $textoLiquido = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($conhecimento->peso_liq_kg ?? 0, 3, ',', '.') . ' KGS');
   // Calcula largura do texto
   $larguraBruto = $pdf->GetStringWidth($textoBruto);
   $larguraLiquido = $pdf->GetStringWidth($textoLiquido);
   // Define posição final (ex: 190 mm) e ajusta para alinhar à direita
   $pdf->Text(158 + (32 - $larguraBruto), 116, $textoBruto);   // largura do campo: 32 mm
   $pdf->Text(158 + (32 - $larguraLiquido), 124, $textoLiquido);
   $pdf->SetFont('Helvetica', '', 6);
   $pdf->Text(156, 129, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '13 volumen m.cu / Peso bruto m.cu.'));
   $pdf->SetFont('Helvetica', '', 8);
   $pdf->Text(158, 134, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->volume_m3 ?? '')));

   $pdf->SetFont('Helvetica', '', 6);
   $pdf->Text(156, 140, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '14 Valor / Valor'));
   $pdf->SetFont('Helvetica', '', 8);
   $pdf->Text(158, 146, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->incoterm ?? '')));
   $pdf->Text(158, 152, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($conhecimento->valor_mercadorias ?? 0, 2, ',', '.')));
   $pdf->SetFont('Helvetica', '', 6);
   $pdf->Text(158, 156, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Moneda/moeda'));
   $pdf->SetFont('Helvetica', '', 8);
   $pdf->Text(158, 160, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->moeda_valor_mercadorias ?? '')));
 
// CONTINUAR DAQUI
  $pdf->SetFont('Helvetica', '', 6);
  $pdf->Text(106, 166, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '16 Declaración del valor de las mercancias / Declaração do valor das mercadorias'));
  $pdf->SetFont('Helvetica', '', 8);
// Obtém e formata os valores
  $incoterm = (string) ($conhecimento->incoterm16 ?? '');
  $moeda    = (string) ($conhecimento->moeda_valor_mercadorias ?? '');
  $valor    = number_format($conhecimento->valor_declarado ?? 0, 2, ',', '.');
// Concatena em uma única string
  $campo16 = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "{$incoterm} {$moeda} {$valor}");
// Exibe em uma única linha na posição desejada
  $pdf->Text(106, 171, $campo16);

  $pdf->SetFont('Helvetica', '', 6);
  $pdf->Text(106, 176, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '17 Documentos anexos'));
  $pdf->SetFont('Helvetica', '', 8);
  $pdf->Text(106, 180, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'FATURA COMERCIAL.:' . ($conhecimento->fatura_crt ?? '')));
  $pdf->SetXY(105, 182);
  $pdf->MultiCell(94, 4, mb_convert_encoding($conhecimento->documentos_anexos ?? '', 'ISO-8859-1', 'UTF-8'), 0, 'L');

  $pdf->SetFont('Helvetica', '', 6);
  $pdf->Text(106, 206, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '18 instruccioner sobre formalidades de aduana / Instruções sobre formalidades de alfândega'));
  $pdf->SetFont('Helvetica', '', 8);
  $pdf->SetXY(105, 208);
  $pdf->MultiCell(94, 4, mb_convert_encoding($conhecimento->instrucoes_alfandega ?? '', 'ISO-8859-1', 'UTF-8'), 0, 'L');

  $pdf->SetFont('Helvetica', '', 6);
  $pdf->Text(106, 236, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '22 Declaraciones y observaciones/ Declarações e observações'));
  $pdf->SetFont('Helvetica', '', 8);
  $pdf->SetXY(105, 238);
  $pdf->MultiCell(94, 4, mb_convert_encoding($conhecimento->observacoes ?? '', 'ISO-8859-1', 'UTF-8'), 0, 'L');


 $pdf->SetXY(105, 264);
 $pdf->SetFont('Helvetica', '', 6);
 $pdf->MultiCell( 55,  2,  mb_convert_encoding(
        "24 Nombre y firma del destinatario o su representante\n" .
        "Nome e assinatura do destinatário ou seu representante",
        'ISO-8859-1',
        'UTF-8'   ));
 $pdf->SetFont('Helvetica', '', 8);
 $clienteDestinatario = new Clientes($conhecimento->destinatario_id);
 $pdf->Text(106, 275, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($clienteDestinatario->nome ?? '')));
 $pdf->SetFont('Helvetica', '',6);
 $pdf->Text(106, 278, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Fecha/Data'));
 $pdf->SetFont('Helvetica', '', 8);
 $pdf->Text(106, 281, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($dataBrasileira ?? '')));


 $pdf->SetFont('Helvetica', '',6);
 $pdf->Text(6, 278, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Fecha/Data'));
 $pdf->SetFont('Helvetica', '', 8);
 $pdf->Text(6, 281, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($dataBrasileira ?? '')));

// CAMPO 15 FRETES 

$pdf->SetFont('Helvetica','',6);

$colunas = [
    ['x'=>5,  'w'=>29, 'text'=>"15 Gastos a pagar\nGastos a pagar"],
    ['x'=>34, 'w'=>21, 'text'=>"Monto remitente\nValor remitente"],
    ['x'=>55, 'w'=>15, 'text'=>"Moneda\nMoeda"],
    ['x'=>70, 'w'=>25, 'text'=>"Monto destinatario\nValor destinatario"],
    ['x'=>95, 'w'=>15, 'text'=>"Moneda\nMoeda"],
];
foreach ($colunas as $col) {
    $pdf->SetXY($col['x'], 165);
    $pdf->MultiCell(
        $col['w'], 
        2,
        iconv('UTF-8','ISO-8859-1//TRANSLIT', $col['text']),
        0,    // sem borda (use 1 para borda)
        'L'   // alinhamento à esquerda
    );
}
$pdf->SetFont('Helvetica', '', 6);
  $pdf->Text(6, 173, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->textogasto1 ?? '')));
  $pdf->Text(6, 183, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->textogasto2  ?? '')));
  $pdf->Text(6, 193, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->textogasto3  ?? '')));

  $pdf->SetFont('Helvetica', '', 8);
    // Valores formatados
    $valorGastoRem1 = !empty($conhecimento->custoremetente1) ? number_format($conhecimento->custoremetente1, 2, ',', '.') : '';
    $valorGastoRem2 = !empty($conhecimento->custoremetente2) ? number_format($conhecimento->custoremetente2, 2, ',', '.') : '';
    $valorGastoRem3 = !empty($conhecimento->custoremetente3) ? number_format($conhecimento->custoremetente3, 2, ',', '.') : '';
    $valorTotalRem  = !empty($conhecimento->total_custo_remetente) ? number_format($conhecimento->total_custo_remetente, 2, ',', '.') : '';

    // Coordenadas com deslocamento de 10mm à esquerda
    $xPosRem = 40 - 10; // Antes era 40
    $larguraCelula = 20;

    if ($valorGastoRem1) {
        $texto = mb_substr($valorGastoRem1, 0, 100);
        $texto = mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
        $textWidth = $pdf->GetStringWidth($texto);
        $pdf->Text($xPosRem + ($larguraCelula - $textWidth), 177, $texto);
    }
    if ($valorGastoRem2) {
        $texto = mb_substr($valorGastoRem2, 0, 100);
        $texto = mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
        $textWidth = $pdf->GetStringWidth($texto);
        $pdf->Text($xPosRem + ($larguraCelula - $textWidth), 187, $texto);
    }
    if ($valorGastoRem3) {
        $texto = mb_substr($valorGastoRem3, 0, 100);
        $texto = mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
        $textWidth = $pdf->GetStringWidth($texto);
        $pdf->Text($xPosRem + ($larguraCelula - $textWidth), 197, $texto);
    }
    if ($valorTotalRem) {
        $texto = mb_substr($valorTotalRem, 0, 100);
        $texto = mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
        $textWidth = $pdf->GetStringWidth($texto);
        $pdf->Text($xPosRem + ($larguraCelula - $textWidth), 202, $texto);
    }

   
    // 22) Gastos destinatário
    $xPosDest = 75 - 10; // Antes era 75
    $pdf->SetFont('Helvetica', '', 8);

    $valorGastoDest1 = !empty($conhecimento->custodestino1) ? number_format($conhecimento->custodestino1, 2, ',', '.') : '';
    $valorGastoDest2 = !empty($conhecimento->custodestino2) ? number_format($conhecimento->custodestino2, 2, ',', '.') : '';
    $valorGastoDest3 = !empty($conhecimento->custodestino3) ? number_format($conhecimento->custodestino3, 2, ',', '.') : '';
    $valorTotalDest  = !empty($conhecimento->total_custo_destinatario) ? number_format($conhecimento->total_custo_destinatario, 2, ',', '.') : '';

    if ($valorGastoDest1) {
        $texto = mb_substr($valorGastoDest1, 0, 100);
        $texto = mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
        $textWidth = $pdf->GetStringWidth($texto);
        $pdf->Text($xPosDest + ($larguraCelula - $textWidth), 177, $texto);
    }
    if ($valorGastoDest2) {
        $texto = mb_substr($valorGastoDest2, 0, 100);
        $texto = mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
        $textWidth = $pdf->GetStringWidth($texto);
        $pdf->Text($xPosDest + ($larguraCelula - $textWidth), 187, $texto);
    }
    if ($valorGastoDest3) {
        $texto = mb_substr($valorGastoDest3, 0, 100);
        $texto = mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
        $textWidth = $pdf->GetStringWidth($texto);
        $pdf->Text($xPosDest + ($larguraCelula - $textWidth), 197, $texto);
    }
    if ($valorTotalDest) {
        $texto = mb_substr($valorTotalDest, 0, 100);
        $texto = mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
        $textWidth = $pdf->GetStringWidth($texto);
        $pdf->Text($xPosDest + ($larguraCelula - $textWidth), 202, $texto);
    }
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(6, 205, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '19 Monto del extermo / Valor do frete externo'));
    $pdf->SetFont('Helvetica', '', 8);
   $pdf->Text(6, 210,  iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', 
   ($conhecimento->gastosmoeda ?? '') . ' ' . number_format($conhecimento->valor_frete_externo ?? 0, 2, ',', '.') ));



    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(6, 215, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '20 - Valor de reembolso contra entrega'));
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->Text(6, 220, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', number_format($conhecimento->valor_reembolso ?? 0, 2, ',', '.')));
  
  
    $pdf->Text(57, 177, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->gastosmoeda  ?? '')));
    $pdf->Text(57, 187, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->gastosmoeda  ?? '')));
    $pdf->Text(57, 197, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->gastosmoeda  ?? '')));
    $pdf->Text(57, 202, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->gastosmoeda  ?? '')));

   // $pdf->Text(96, 177, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->gastosmoeda  ?? '')));
   // $pdf->Text(96, 187, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->gastosmoeda  ?? '')));
   // $pdf->Text(96, 197, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->gastosmoeda  ?? '')));
   // $pdf->Text(96, 202, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($conhecimento->gastosmoeda  ?? '')));

    // 25) Assinatura do remetente
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->SetXY(5, 225);
    $pdf->MultiCell(60, 2, mb_convert_encoding(
        "21 Nombre y fima del remetente o su representante \n" .
        "Nome e assinatura do remetente ou seu representante",
        'ISO-8859-1', 'UTF-8'
    ), 0, 'L');

   $pdf->SetFont('Helvetica', '', 8);
   $clienteremetente = new Clientes($conhecimento->remetente_id);
   $pdf->Text(6, 232, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($clienteremetente->nome ?? '')));
   $pdf->Text(6, 236, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) ($dataBrasileira ?? '')));



    $pdf->SetFont('Helvetica', '', 6);
    $pdf->SetXY(5, 240);
    $pdf->MultiCell(100, 2, mb_convert_encoding(
        "Las mercancías consignadas en esta carta de porte fueron recibidas por el porteador aparentemente en\n" .
        "buen estado, bajo las condiciones generales que figuran al dorso As mercadorias consignadas neste .\n" .
        "conhecimento de transporte foram recebidas pelo transportador aparentemente em bom estado, sob as\n" .
        "condições gerais que figuram no verso.\n\n" .
        "23 Nombre y firma del porteador o su representante\Nome e assinatura do transp. ou seu representante.",
        'ISO-8859-1', 'UTF-8'
    ), 0, 'L');


     $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetXY(6, 262);
    $pdf->MultiCell(94, 3, mb_convert_encoding($conhecimento->assinatura_nome ?? '', 'ISO-8859-1', 'UTF-8'), 0, 'L');
    
  
   
}


function addFixedFooter($pdf, $texto) {
    // Salva a configuração atual
    $pdf->SetFont('Helvetica', 'B', 8);
    
    // Posição absoluta (X, Y) em mm
    // 10mm da esquerda, 287mm do topo (A4 tem 297mm de altura)
    $pdf->SetXY(5, 280);
    
    // Adiciona o texto
    $pdf->Cell(0, 10, mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
}

// Página ORIGINAL
$pdf->AddPage('P', 'A4');
$pdf->SetAutoPageBreak(false);
generatePageContent($pdf, $caminhoLogoRegistro, $registro, $conhecimento);
$pdf->Image('files/images/1/CRT.jpg', 6, 6, 18);
$pdf->Image('files/images/1/assinatura2.jpg', 56, 255, 35, 25);

addFixedFooter($pdf, 'ORIGINAL');

// Página CÓPIA
$pdf->AddPage('P', 'A4');
$pdf->Image('files/images/1/CRT.jpg', 6, 6, 18);
$pdf->Image('files/images/2/assinatura2.jpg', 56, 255, 35, 25);
generatePageContent($pdf, $caminhoLogoRegistro, $registro, $conhecimento);
addFixedFooter($pdf, 'CÓPIA');



$invalidos = ['/', '\\', ':', '*', '?', '"', '<', '>', '|'];
$baseNome = sprintf(
    'CRT_%s_%s_%s_%s_%s',
    str_replace($invalidos, '_', $conhecimento->numero ?? ''),
    str_replace($invalidos, '_', $conhecimento->fatura_crt ?? ''),
    str_replace($invalidos, '_', $conhecimento->nome_destinatario ?? ''),
    str_replace($invalidos, '_', substr($conhecimento->local_emissao ?? '', 0, 4)),
    str_replace($invalidos, '_', $conhecimento->pais_destino ?? '')
);

$diretorio = 'tmp/';
if (!file_exists($diretorio)) {
    mkdir($diretorio, 0777, true);
}

$caminhoSaida = $diretorio . $baseNome . '.pdf';
if (file_exists($caminhoSaida)) {
   $caminhoSaida = $diretorio . $baseNome . '-ALTERACAO-' . date('dmY-His') . '.pdf';
}

$pdf->Output('F', $caminhoSaida);
parent::openFile($caminhoSaida);
TTransaction::close();

} catch (Exception $e) {
    TTransaction::rollback();
    new TMessage('error', $e->getMessage());
}


 



    }//end-of-onprint_crt()
    
    /**
     * onNumerarCrt()
     */
    public static function onNumerarCrt($param)
    {
    try {
        // Cria o formulário
        $form = new BootstrapFormBuilder('form_novo_crt');
        $form->setFormTitle('<span style="font-size:16px;">📄 Gerar Novo CRT</span>');
        $form->setProperty('style', 'width:40%;margin:auto;padding:10px');

        // Campo de seleção do registro
        $comboRegistro = new TDBCombo('registro_id', 'Principal', 'registro', 'id', 'codigo');
        $comboRegistro->setSize('100%');
        $comboRegistro->enableSearch();
        $comboRegistro->addValidation('Registro', new TRequiredValidator);

        $form->addFields([new TLabel('Registro')], [$comboRegistro]);

        // Ações do formulário
        $form->addAction('Gerar CRT', new TAction([__CLASS__, 'gerarCrtComRegistro']), 'fa:check-circle green');
        $form->addAction('Cancelar', new TAction([__CLASS__, 'closeWindow']), 'fa:times-circle red');

        // Abre o formulário em um dialog
        new TInputDialog('form_novo_crt', $form);

    } catch (Exception $e) {
        new TMessage('error', $e->getMessage());
    }
    }//end-of-onNumerarCrt()
    
    /**
     * gerarCrtComRegistro()
     */
    public static function gerarCrtComRegistro($param)
    {
    try {
        // Validação de entrada
        if (empty($param['registro_id'])) {
            throw new Exception('Registro não selecionado.');
        }

        TTransaction::open('Principal');

        // Carrega o registro
        $registro = new registro($param['registro_id']);

        if (empty($registro->id)) {
            throw new Exception('Registro não encontrado.');
        }

        // Geração do número sequencial do CRT
        $novoNumero = (int) $registro->sequenciacrt + 1;
        $numeroCrt = $registro->codigo . str_pad($novoNumero, 5, '0', STR_PAD_LEFT);

        // Atualiza o sequencial no registro
        $registro->sequenciacrt = $novoNumero;
        $registro->store();

        // Cria o CRT
        $crt = new Conhecimento();
        $crt->numero           = $numeroCrt;
        $crt->pais_destino     = $registro->pais_destino;
        $crt->permisso         = $registro->codigo;
      //  $crt->data_emissao     = date('Y-m-d');
        $crt->nome_transporte  = $registro->transportadora;
        $crt->store();

        TTransaction::close();

        // Mensagem de sucesso e recarregar tela
        new TMessage('info', "CRT nº <b>{$crt->numero}</b> gerado com sucesso.");
        TScript::create("window.location.reload();");

    } catch (Exception $e) {
        TTransaction::rollback();
        new TMessage('error', $e->getMessage());
    }

    }//end-of-gerarCrtComRegistro()
    
    /**
     * closeWindow()
     */
    public static function closeWindow($param)
    {
    TScript::create('Template.closeRightPanel();');    
    }//end-of-closeWindow()
    
    /**
     * oncopycrt()
     */
    public static function oncopycrt($param)
    {
   try {
    TTransaction::open('Principal');

    // Carrega o CRT original
    $original = new Conhecimento($param['key']);

    // Carrega o registro com base na permissão (permisso = codigo)
    $criteria = new TCriteria();
    $criteria->add(new TFilter('codigo', '=', $original->permisso));
    $repository = new TRepository('registro');
    $registroLista = $repository->load($criteria);

    if (empty($registroLista)) {
        throw new Exception("Registro não encontrado para o código: " . $original->permisso);
    }

    $registro = $registroLista[0];

    // Gera novo número de CRT
    $novoNumero = (int) $registro->sequenciacrt + 1;
    $padrao = str_pad($novoNumero, 5, '0', STR_PAD_LEFT);
    $novoCrt = $registro->codigo . $padrao;

    // Atualiza sequência
    $registro->sequenciacrt = $novoNumero;
    $registro->store();

    // Copia o CRT
    $copy = new Conhecimento;
    foreach ($original->toArray() as $attr => $val) {
        if ($attr !== 'id') {
            $copy->$attr = $val;
        }
    }

    $copy->numero   = $novoCrt;
    $copy->copiacrt = null;

    // Atualiza também os campos obrigatórios de relacionamento
    $copy->permisso = $registro->codigo;

    $copy->store();

    // Atualiza CRT original (se necessário)
    $original->copiacrt = null;
    $original->store();

    TTransaction::close();

    new TMessage('info', "CRT copiado: {$novoCrt}", new TAction([__CLASS__, 'onReload']));
}
catch (Exception $e) {
    new TMessage('error', $e->getMessage());
    TTransaction::rollback();
}

    }//end-of-oncopycrt()
    
}//end-of-class
