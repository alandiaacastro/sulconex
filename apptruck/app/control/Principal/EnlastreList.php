<?php
// [class-head]

// [/class-head]

/**
 * EnlastreList
 * Listar Enlastre
 */
class EnlastreList extends TPage
{
    private AdiantiXMLFormRender $ui;
    private TDataGrid $datagrid;
    private TPageNavigation $pageNavigation;
    private TForm $search;
    private static $form_name = 'form_EnlastreList';
    
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
        $this->setActiveRecord('enlastre'); // defines the active record
        $this->setDefaultOrder('id', 'asc');  // defines the default order
        $this->setLimit(10);
        
        
        try
        {
            $this->ui = new AdiantiXMLFormRender;
            $this->ui->setController(__CLASS__);
            $this->ui->setPageName('Listar Enlastre');
            $this->ui->enableForm();
            
            TTransaction::open('Principal');
            $this->ui->parseFile('app/forms/Principal/EnlastreList.xml');
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
        self::showInWindow(self::embedPDFObject($output), 'Listar Enlastre');
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
     * onEnlastre()
     */
    public static function onEnlastre($param)
    {

TTransaction::open('Principal');      
// 2) Carrega o registro
// Carrega o objeto Enlastre
$enlastre = new enlastre($param['key'] ?? null);
if (!$enlastre || !$enlastre->id) {
    throw new Exception('Enlastre não encontrado ou inválido.');
}
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
    $pdf->Text(45, 12, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'MIC DTA - Manifiesto Internacional de Carga por Carretera / Declaração de Trânsito Aduaneiro'));
    $pdf->SetFont('Helvetica', '', 8);   
    $pdf->Text(45, 15, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'MIC DTA - Manifiesto Internacional de Carga por Carretera / Declaração de Trânsito Aduaneiro'));
    // Retângulo geral
    //  $pdf->Rect(10, 30, 190, 250); // borda externa
    // Retângulos de campos conforme layout da imagem
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
    $pdf->Text(106, 22, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '3 Trânsito aduaneiro'));
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
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '7 Alfândega, cidade e país de partida ');
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
    $pdf->Text(6, 68, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '9 CAMINHÃO ORIGINAL: Nome e endereço do proprietário'));
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(6, 70, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '  CAMIÓN ORIGINAL: Nombre y domicilio del propietario'));
     
    $pdf->SetFont('Helvetica', '', 8);
     $pdf->SetXY(6, 71);
    $pdf->MultiCell(100, 4, mb_convert_encoding($enlastre->transportadora ?? '', 'ISO-8859-1', 'UTF-8'), 0, 'L');
  


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
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '24 Alfândega de destino');
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
    $textoNegrito = iconv(    'UTF-8',    'ISO-8859-1//TRANSLIT',    '41 Assinatura e carimbo da Alfândega de Partida');
    $pdf->Write(4, $textoNegrito);
    $pdf->SetFont('Helvetica', '', 6);
    $textoNormal = iconv(    'UTF-8',     'ISO-8859-1//TRANSLIT',     '/ Firma y sello de la Aduana de Partida');
    $pdf->Write(4, $textoNormal);






    $pdf->SetXY(105, 66);
    $pdf->SetFont('Helvetica', 'B', 6);
    $pdf->Text(106, 68, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '16 CAMINHÃO SUBSTITUTO: Nome e endereço do proprietário'));
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(106, 70, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '  CAMIÓN SUBSTITUTO: Nombre y domicilio del propietario'));


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
    parent::openFile($caminhoSaida);

    // Fecha a transação
    TTransaction::close();

} catch (Exception $e) {
    // Se ocorrer um erro, faz rollback da transação e exibe a mensagem de erro
    TTransaction::rollback();
    new TMessage('error', $e->getMessage());
}













        
    }//end-of-onEnlastre()
    
    /**
     * onnumeraremlaste()
     */
    public static function onnumeraremlaste($param)
    {
    try {
    // Abre a transação uma única vez
    TTransaction::open('Principal');

    // Formulário
    $form = new BootstrapFormBuilder('form_novo_enalstre');
    $form->setFormTitle('<span style="font-size:16px;">📄 Gerar Novo Enlastre</span>');
    $form->setProperty('style','width:40%;margin:auto;padding:10px');

    // Campo de seleção baseado no banco
    $comboRegistro = new TDBCombo('registro_id', 'Principal', 'registro', 'id', 'codigo');
    $comboRegistro->setSize('100%');
    $comboRegistro->enableSearch();
    $comboRegistro->addValidation('Registro', new TRequiredValidator);

    // Adiciona campos ao formulário
    $form->addFields([ new TLabel('Registro') ], [ $comboRegistro ]);

    // Ações do formulário
    $form->addAction('Gerar CRT', new TAction([__CLASS__, 'gerarEnlastreComRegistro']), 'fa:check-circle green');
    $form->addAction('Cancelar', new TAction([__CLASS__, 'closeWindow']), 'fa:times-circle red');

    // Exibe o formulário em um dialog
    new TInputDialog('form_novo_enalstre', $form);

    // Fecha a transação
    TTransaction::close();
} catch (Exception $e) {
    TTransaction::rollback();
    new TMessage('error', $e->getMessage());
}

    }//end-of-onnumeraremlaste()
    
    /**
     * gerarEnlastreComRegistro()
     */
    public static function gerarEnlastreComRegistro($param)
    {
try {
    TTransaction::open('Principal');

    // Carrega o registro
    $registro = new registro($param['registro_id']);
    
    // Gera o novo número de enlastre
    $novoNumero = (int) $registro->enlastre + 1;
    $numero = $registro->codigo . '-' . str_pad($novoNumero, 5, '0', STR_PAD_LEFT);

    // Atualiza o número de enlastre no registro
    $registro->enlastre = $novoNumero;
    $registro->store();

    // Cria um novo objeto Enlastre e preenche os dados
    $enlastre = new enlastre();
    $enlastre->numeroenlastre   = $numero;
    $enlastre->nometransporte   = $registro->nometransportadora;
    $enlastre->transportadora   = $registro->transportadora;
    $enlastre->cnpj             = $registro->cnpj;
    $enlastre->dta_emissao      = date('Y-m-d');
    $enlastre->enlastre_id      = $registro->id; // relação com registro

    $enlastre->store();

    TTransaction::close();

    new TMessage('info', "Enlastre nº <b>{$enlastre->numeroenlastre}</b> gerado com sucesso.");
    TScript::create("window.location.reload();");

} catch (Exception $e) {
    TTransaction::rollback();
    new TMessage('error', $e->getMessage());
}

    }//end-of-gerarEnlastreComRegistro()
    
    /**
     * closeWindow()
     */
    public static function closeWindow($param)
    {
       TScript::create('Template.closeRightPanel();');   
    }//end-of-closeWindow()
    
}//end-of-class
