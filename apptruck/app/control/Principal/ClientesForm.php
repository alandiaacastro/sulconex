<?php
// [class-head]

// [/class-head]

/**
 * ClientesForm
 * Formulário Clientes
 */
class ClientesForm extends TPage
{
    private AdiantiXMLFormRender $ui;
    private TForm $form;
    private static $form_name = 'form_ClientesForm';
    
    // import traits
    use AdiantiCreatorFormTraits;
    
    // [class-body]

    // [/class-body]
    
    /**
     * Constructor
     * @author Creator
     */
    public function __construct($param)
    {
        parent::__construct();
        parent::setTargetContainer('adianti_right_panel');
        
        try
        {
            $this->ui = new AdiantiXMLFormRender;
            $this->ui->setController(__CLASS__);
            $this->ui->setPageName('Formulário Clientes');
            $this->ui->enableForm();
            
            TTransaction::open('Principal');
            $this->ui->parseFile('app/forms/Principal/ClientesForm.xml');
            TTransaction::close();
            
            $this->form = $this->ui->getForm();
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
        
        $vbox = new TVBox;
        $vbox->{'style'} = 'display:block;width:100%';
        $vbox->add($this->ui);
        parent::add( $vbox );
        
        parent::callIfExists('onAfterConstruct', $param);
    }//end-of-__construct()
    
    /**
     * onLoad()
     * @author Creator
     */
    public function onLoad($param)
    {
    
    }//end-of-onLoad()
    
    
    /**
     * edit()
     * @author Creator
     */
    private function edit($param)
    {
        try
        {
            if (isset($param['key']))
            {
                $key = $param['key'];
                TTransaction::open('Principal');
                $object = new Clientes($key);
                
                
                
                $this->form->setData($object);
                
                TTransaction::close();
                return $object;
            }
            else
            {
                $this->form->clear(true);
            }
        }
        catch (Exception $e) // in case of exception
        {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }//end-of-edit()
    
    /**
     * save()
     * @author Creator
     */
    private function save($param)
    {
        try
        {
            TTransaction::open('Principal');
            
            $this->form->validate(); // run form validations
            $data = $this->form->getData(); // get form data as array
            
            $object = new Clientes;
            $object->fromArray( (array) $data); // load the object with data
            
            
            
            $object->store();
            
            TTransaction::close();
            
            TToast::show('success', _t('Record saved'), 'bottom center', 'far:check-circle' );
            
            $this->closePage();
            
            return $object;
        }
        catch (Exception $e) // in case of exception
        {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }//end-of-save()
    
    
    /**
     * onEdit()
     */
    public function onEdit($param)
    {
        return $this->edit($param);
    }//end-of-onEdit()
    
    /**
     * onSave()
     */
    public function onSave($param)
    {
        $object = $this->save($param);
        if ($object)
        {
            AdiantiCoreApplication::loadPage('ClientesList', 'onLoad');
        }
        return $object;
    }//end-of-onSave()
    
    /**
     * crtprint()
     */
    public function crtprint($param)
    {
 try {
    TTransaction::open(self::$database);

    $class = self::$activeRecord;
    $object = new $class($param['key']);
} catch (Exception $e) {
    // Handle exception
}

$pdf = new FPDF(); 
$pdf->SetAutoPageBreak(true, 10);
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTopMargin(10);
$pdf->SetLeftMargin(10);
$pdf->SetRightMargin(10);

function addPageContent($pdf, $object, $name) {
    $pdf->AddPage('P', 'A4');
    /* --- Rect --- */
    $pdf->Rect(5, 5, 200, 20, 'D'); //cabecario crt 
    $pdf->SetFont('Helvetica', 'B', 20);

    $pdf->Image('app/images/CRT.jpg',6,6,18,18);
    $pdf->Image('app/images/assinatura2.jpg',56, 255, 35, 25);

    // Add a button - Implementation using a rectangle with text
    $pdf->SetFillColor(220, 220, 220); // Light grey background
    $pdf->Rect(160, 10, 30, 10, 'F');
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetTextColor(0, 0, 0); // Black text
    $pdf->SetXY(160, 10);
    $pdf->Cell(30, 10, 'SUBMIT', 1, 0, 'C', true);
    $pdf->SetTextColor(0, 0, 0); // Reset text color
    $pdf->SetFont('Helvetica', '', 6);

    /* --- Text --- */
    $pdf->SetFont('Helvetica', '', 6);  // fonte texto pequeno 

    $pdf->Image('app/images/sulconexlog.png', 160, 40, 40, 20);

    $texto = "El transporte realizado bajo esta carta de ponte Internacional está sujeto a las disposiciones del Convenio sobre el
contrato de transporte y la responsabilidad Civil del porteador en el Transportes Terrestre Internacional de Mercancias.
las cuales anulan toda estipulación que se aparte de ellas en prejuicio del remitente o del consignatário.
O transporte realizado ao amparo deste Conheçimento de Transporte Internacional está sujeito às disposições do
Convênio sobre o Contrato de Transporte e a Responsabilidade Civil do Transportador no transporte terrestre internacional.
de mercadorias, as quais anulam toda especulação contrária às mesmas em prejuizo do remetente ou do consignatário";

    // Espaço para o texto
    $pdf->SetXY(84,7);
    // Adiciona as informações ao PDF
    $pdf->MultiCell(0, 2, utf8_decode($texto), 0, 'L');

    $pdf->SetFont('Helvetica', '', 10);  // fonte texto grande

    $texto2 = "Carta de Porte Internacional por carretera               Conhecimento de Transporte Internacional por Rodovia";

    // Espaço para o texto
    $pdf->SetXY(30,7);
    // Adiciona as informações ao PDF
    $pdf->MultiCell(50, 4, utf8_decode($texto2), 0, 'L');
    
    /* --- Rect --- */
    $pdf->Rect(5, 25, 100, 25, 'D'); // 1remetente
    $pdf->Rect(5, 50, 100, 22, 'D'); // 4 destinatario
    $pdf->Rect(5, 72, 100, 22, 'D');  //6  consignatario
    $pdf->Rect(5, 94, 100, 22, 'D');  //9 notificar
    $pdf->Rect(5, 116, 160, 58, 'D');  // 11 descriçao 
    $pdf->Rect(5, 174, 25, 40, 'D');
    $pdf->Rect(30,174,25, 40, 'D');   
    $pdf->Rect(55,174,10, 40, 'D');
    $pdf->Line(5,207,105,207);
    $pdf->Rect(65,174,30, 40, 'D'); 

    $pdf->Rect(5, 214, 100, 7, 'D'); //19  CAMPOS 
    $pdf->Rect(5, 221, 100, 10, 'D'); //20 CAMPOS 
    $pdf->Rect(5, 231, 100, 16, 'D');   // 21 CAMPO 
    $pdf->Rect(5, 247, 100, 37, 'D');   // 23 CAMPO

    $pdf->Rect(105, 25, 100, 6, 'D');  //  2 numero crt
    $pdf->Rect(105, 31, 100, 35, 'D');   // 3 transportadora 
    $pdf->Rect(105, 66, 100, 13, 'D');   // 5 pais de emissao
    $pdf->Rect(105, 79, 100, 13, 'D');    // 7 pais responsabilide
    $pdf->Rect(105, 92, 100, 13, 'D');     // 8 local entrega
    $pdf->Rect(105, 105, 100, 11, 'D');   // 10 porteadores
    $pdf->Rect(165, 116, 40, 21, 'D');   // 12 peso bruto 
    $pdf->Rect(165, 137, 40, 11, 'D');   // 13 M3
    $pdf->Rect(165, 148, 40, 26, 'D');  // CAMPO 14
    $pdf->Rect(105, 174, 100, 10, 'D'); // CAMPO 16
    $pdf->Rect(105, 184, 100, 25, 'D'); // CAMPO 17
    $pdf->Rect(105, 209, 100, 25, 'D'); // CAMPO 18
    $pdf->Rect(105, 234, 100, 25, 'D'); // CAMPO 22
    $pdf->Rect(105, 259, 100, 25, 'D'); // CAMPO 24
    
    // Add a clickable button for a form-like functionality
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetFillColor(0, 102, 204); // Blue button
    $pdf->SetTextColor(255, 255, 255); // White text
    $pdf->Rect(150, 220, 40, 10, 'F');
    $pdf->SetXY(150, 220);
    $pdf->Cell(40, 10, 'IMPRIMIR', 1, 0, 'C', true);
    $pdf->SetTextColor(0, 0, 0); // Reset text color
    
    // Continue with the rest of the content...
    $pdf->SetFont('Helvetica', '', 6);
    $pdf->Text(6, 27, utf8_decode('1 Nombre y domicilio del remitente / Nome e endereço do remetente'));
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetXY(6,28);
    $pdf->MultiCell(90, 3, utf8_decode($object->endereco_remetente), 0, 'L');
    
    // Many more content lines...
    // All the existing code for fields and content would continue here
    
    $pdf->Text(5, 289, $name);
}

// Generate pages with different names
$name = "1 VIA ORIGINAL";
addPageContent($pdf, $object, $name);

$name = "2 VIA ORIGINAL ";
addPageContent($pdf, $object, $name);

$name = "3 VIA ORIGINAL ";
addPageContent($pdf, $object, $name);

$name = "4 VIA ORIGINAL ";
addPageContent($pdf, $object, $name);

$name = "5 VIA ORIGINAL ";
addPageContent($pdf, $object, $name);

$name = "COPIA";
addPageContent($pdf, $object, $name);

$pdf->Output('app/output/crt.pdf');
parent::openFile('app/output/crt.pdf');
TTransaction::close();
    


    }//end-of-crtprint()
    
}//end-of-class
