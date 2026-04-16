<?php
// [class-head]

// [/class-head]

/**
 * StatusCrtList
 * Status Crt
 */
class StatusCrtList extends TPage
{
    private AdiantiXMLFormRender $ui;
    private TDataGrid $datagrid;
    private TPageNavigation $pageNavigation;
    private TForm $search;
    private static $form_name = 'form_StatusCrtList';
    
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
        $this->setActiveRecord('StatusCrt'); // defines the active record
        $this->setDefaultOrder('id', 'asc');  // defines the default order
        $this->setLimit(10);
        
        
        try
        {
            $this->ui = new AdiantiXMLFormRender;
            $this->ui->setController(__CLASS__);
            $this->ui->setPageName('Status Crt');
            $this->ui->enableForm();
            
            TTransaction::open('Principal');
            $this->ui->parseFile('app/forms/Principal/StatusCrtList.xml');
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
        self::showInWindow(self::embedPDFObject($output), 'Status Crt');
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
    
}//end-of-class
