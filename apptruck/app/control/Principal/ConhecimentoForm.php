<?php
// [class-head]

// [/class-head]

/**
 * ConhecimentoForm
 * Formulário Conhecimento
 */
class ConhecimentoForm extends TPage
{
    private AdiantiXMLFormRender $ui;
    private TForm $form;
    private static $form_name = 'form_ConhecimentoForm';
    
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
        
        try
        {
            $this->ui = new AdiantiXMLFormRender;
            $this->ui->setController(__CLASS__);
            $this->ui->setPageName('Formulário Conhecimento');
            $this->ui->enableForm();
            
            TTransaction::open('Principal');
            $this->ui->parseFile('app/forms/Principal/ConhecimentoForm.xml');
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
                $object = new Conhecimento($key);
                
                
                
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
            
            $object = new Conhecimento;
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
            AdiantiCoreApplication::loadPage('ConhecimentoList', 'onLoad');
        }
        return $object;
    }//end-of-onSave()
    
    /**
     * onexitermetente()
     */
    public static function onexitermetente($param)
    {
  {   
    try
    {
        TTransaction::open('Principal');

        if (!empty($param['remetente_id']))
        {
            $cliente = new Clientes((int) $param['remetente_id']);

            if ($cliente)
            {
                $obj = new stdClass;
                $obj->nome_remetente = $cliente->nome;
                $obj->endereco_remetente = $cliente->dados_crt;

               TForm::sendData('form_ConhecimentoForm', $obj);
            }
            else
            {
                new TMessage('warning', 'Cliente não encontrado');
            }
        }

        TTransaction::close();
    }
    catch (Exception $e)
    {
        new TMessage('error', $e->getMessage());
        TTransaction::rollback();
    }
}

    }//end-of-onexitermetente()
    
    /**
     * ondestinatatio()
     */
    public static function ondestinatatio($param)
    {
   
    try
    {
        TTransaction::open('Principal');

        if (!empty($param['destinatario_id']))
        {
            $cliente = new Clientes((int) $param['destinatario_id']);

            if ($cliente)
            {
                $obj = new stdClass;
                $obj->endereco_destinatario = $cliente->dados_crt;

                TForm::sendData(self::$form_name, $obj);
            }
            else
            {
                new TMessage('warning', 'Cliente não encontrado');
            }
        }

        TTransaction::close();
    }
    catch (Exception $e)
    {
        new TMessage('error', $e->getMessage());
        TTransaction::rollback();

    }

    }//end-of-ondestinatatio()
    
    /**
     * onconsignatario()
     */
    public static function onconsignatario($param)
    {
 try
    {
        TTransaction::open('Principal');

        if (!empty($param['consignatario_id']))
        {
            $cliente = new Clientes((int) $param['consignatario_id']);

            if ($cliente)
            {
                $obj = new stdClass;
                $obj->endereco_consignatario = $cliente->dados_crt;

                TForm::sendData(self::$form_name, $obj);
            }
            else
            {
                new TMessage('warning', 'Cliente não encontrado');
            }
        }

        TTransaction::close();
    }
    catch (Exception $e)
    {
        new TMessage('error', $e->getMessage());
        TTransaction::rollback();
    }    
   
    }//end-of-onconsignatario()
    
    /**
     * onnotificar()
     */
    public static function onnotificar($param)
    {
     try
    {
        TTransaction::open('Principal');

        if (!empty($param['notificar_id']))
        {
            $cliente = new Clientes((int) $param['notificar_id']);

            if ($cliente)
            {
                $obj = new stdClass;
                $obj->notificar_endereco = $cliente->dados_crt;

                TForm::sendData(self::$form_name, $obj);
            }
            else
            {
                new TMessage('warning', 'Cliente não encontrado');
            }
        }

        TTransaction::close();
    }
    catch (Exception $e)
    {
        new TMessage('error', $e->getMessage());
        TTransaction::rollback();
    }    
       
    }//end-of-onnotificar()
    
    /**
     * ontotalremetente()
     */
    public static function ontotalremetente($param)
    {

 try {
    $custoremetente1 = $param['custoremetente1'] ?? '0';
    $custoremetente2 = $param['custoremetente2'] ?? '0';
    $custoremetente3 = $param['custoremetente3'] ?? '0';

    // Converte de BR -> EN (remover pontos e trocar vírgula por ponto)
    $custoremetente1 = str_replace('.', '', $custoremetente1);
    $custoremetente1 = str_replace(',', '.', $custoremetente1);
    $custoremetente1 = (float) $custoremetente1;

    $custoremetente2 = str_replace('.', '', $custoremetente2);
    $custoremetente2 = str_replace(',', '.', $custoremetente2);
    $custoremetente2 = (float) $custoremetente2;

    $custoremetente3 = str_replace('.', '', $custoremetente3);
    $custoremetente3 = str_replace(',', '.', $custoremetente3);
    $custoremetente3 = (float) $custoremetente3;

    // Soma
    $total = $custoremetente1 + $custoremetente2 + $custoremetente3;

    // Formata (2 casas decimais, separador de decimais = ',', separador de milhar = '.')
    $total_formatado = number_format($total, 2, ',', '.');

    // Cria objeto e manda pro form
    $obj = new stdClass;
    $obj->total_custo_remetente = $total_formatado;

    TForm::sendData('form_ConhecimentoForm', $obj);
        // -----

     //code here

            //</autoCode>
        }
        catch (Exception $e) 
        {
            new TMessage('error', $e->getMessage());    
        }

    
        
    }//end-of-ontotalremetente()
    
    /**
     * ontotaldestinatario()
     */
    public static function ontotaldestinatario($param)
    {

        try {
    $custodestino1 = $param['custodestino1'] ?? '0';
    $custodestino2 = $param['custodestino2'] ?? '0';
    $custodestino3 = $param['custodestino3'] ?? '0';

    // Converte de BR -> EN (remover pontos e trocar vírgula por ponto)
    $custodestino1 = str_replace('.', '', $custodestino1);
    $custodestino1= str_replace(',', '.', $custodestino1);
    $custodestino1 = (float) $custodestino1;

    $custodestino2 = str_replace('.', '', $custodestino2);
    $custodestino2 = str_replace(',', '.', $custodestino2);
    $custodestino2 = (float) $custodestino2;

    $custodestino3 = str_replace('.', '', $custodestino3);
    $custodestino3 = str_replace(',', '.', $custodestino3);
    $custodestino3 = (float) $custodestino3;

    // Soma
    $total2 = $custodestino1 + $custodestino2 + $custodestino3;

    // Formata (2 casas decimais, separador de decimais = ',', separador de milhar = '.')
    $total_formatado2 = number_format($total2, 2, ',', '.');

    // Cria objeto e manda pro form
    $obj = new stdClass;
    $obj->total_custo_destinatario = $total_formatado2;

    TForm::sendData('form_ConhecimentoForm', $obj);


      //</autoCode>
        }
        catch (Exception $e) 
        {
            new TMessage('error', $e->getMessage());    
        }
    
        // -----    
    }//end-of-ontotaldestinatario()
    
}//end-of-class
