<?php
// [class-head]

// [/class-head]

/**
 * EnlastreForm
 * Formulário Enlastre
 */
class EnlastreForm extends TPage
{
    private AdiantiXMLFormRender $ui;
    private TForm $form;
    private static $form_name = 'form_EnlastreForm';
    
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
            $this->ui->setPageName('Formulário Enlastre');
            $this->ui->enableForm();
            
            TTransaction::open('Principal');
            $this->ui->parseFile('app/forms/Principal/EnlastreForm.xml');
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
                $object = new enlastre($key);
                
                
                
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
            
            $object = new enlastre;
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
            AdiantiCoreApplication::loadPage('EnlastreList', 'onLoad');
        }
        return $object;
    }//end-of-onSave()
    
    /**
     * onenlastre()
     */
    public static function onenlastre($param)
    {
    
$pdf->Rect(105,121, 95, 7);  // Campo 24

// Moeda e origem
$pdf->Rect(10,128, 95, 7);   // Campo 25
$pdf->Rect(105,128, 95, 7);  // Campo 26

// Valor FOT e frete
$pdf->Rect(10,135, 95, 7);   // Campo 27
$pdf->Rect(105,135, 95, 7);  // Campo 28

// Seguro
$pdf->Rect(10,142, 95, 7);   // Campo 29

// Tipo de Bultos / Quantidade / Peso
$pdf->Rect(10,149, 63.3, 7);  // Campo 30
$pdf->Rect(73.3,149, 31.7, 7); // Campo 31
$pdf->Rect(105,149, 95, 7);   // Campo 32

// Remetente / Destinatário / Consignatário
$pdf->Rect(10,156, 95, 14);   // Campo 33
$pdf->Rect(105,156, 95, 14);  // Campo 34
$pdf->Rect(10,170, 190, 10);  // Campo 35

// Documentos Anexos
$pdf->Rect(10,180, 190, 7);   // Campo 36

// Número dos precintos
$pdf->Rect(10,187, 190, 7);   // Campo 37

// Mercadorias
$pdf->Rect(10,194, 190, 30);  // Campo 38

// Assinaturas
$pdf->Rect(10,224, 95, 10);   // Campo 39
$pdf->Rect(105,224, 95, 10);  // Campo 41

// Nº DTA, Rota e Prazo
$pdf->Rect(10,234, 190, 7);   // Campo 40

// Rodapé: Data
$pdf->Rect(10,241, 95, 7);   // Data 1
$pdf->Rect(105,241, 95, 7);  // Data 2

// Marcar campos
$pdf->SetFont('Helvetica','',7);
$pdf->Text(12, 50, utf8_decode('1 - Nombre y Domicilio de Porteador'));
$pdf->Text(107, 50, utf8_decode('2 - Rol de Contribuyente'));
$pdf->Text(12, 60, utf8_decode('3 - Tránsito Aduanero'));
$pdf->Text(107, 60, utf8_decode('4 - Nº'));
$pdf->Text(12, 67, utf8_decode('5 - Hoja'));
$pdf->Text(107, 67, utf8_decode('6 - Fecha de Emisión: 07/05/2025'));
$pdf->Text(12, 74, utf8_decode('7 - Aduana y País de Partida'));
$pdf->Text(107, 74, utf8_decode('8 - Ciudad y País de Destino Final'));

// Observação final
$pdf->SetXY(10, 250);
$pdf->SetFont('Helvetica', '', 6);
$pdf->MultiCell(190, 3, utf8_decode("Declaramos que las informaciones prestadas en este documento son expresión de verdad, que los datos referentes a las mercancías fueron transcriptos exactamente conforme a la declaración del remitente, los cuales son de exclusiva responsabilidad, y que esta operación obedece a lo dispuesto en el convenio sobre Transporte Internacional de los Países del Cono Sur."), 0, 'J');

// Exibir PDF
$pdf->Output('I', 'enlastre_formulario.pdf');
    
    }//end-of-onenlastre()
    
}//end-of-class
