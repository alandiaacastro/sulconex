<?php
// [class-head]

// [/class-head]

/**
 * SistemaimagensForm
 * Formulário Sistema imagens
 */
class SistemaimagensForm extends TPage
{
    private AdiantiXMLFormRender $ui;
    private TForm $form;
    private static $form_name = 'form_SistemaimagensForm';
    
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
            $this->ui->setPageName('Formulário Sistema imagens');
            $this->ui->enableForm();
            
            TTransaction::open('Principal');
            $this->ui->parseFile('app/forms/Principal/SistemaimagensForm.xml');
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
                $object = new Sistemaimagens($key);
                
                
                
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
            
            $object = new Sistemaimagens;
            $object->fromArray( (array) $data); // load the object with data
            
            
            $this->saveFile($object, $data, 'imagem', 'files/images');
            
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
            AdiantiCoreApplication::loadPage('SistemaimagensList', 'onLoad');
        }
        return $object;
    }//end-of-onSave()
    
}//end-of-class
