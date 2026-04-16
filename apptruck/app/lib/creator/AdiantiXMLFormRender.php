<?php
/**
 * AdiantiXMLFormRender
 * 
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adiantiframework.com.br/license-template
 */
class AdiantiXMLFormRender extends TElement
{
    protected $form;
    protected $datagrid;
    protected $cardview;
    protected $kanban;
    protected $calendar;
    protected $panel;
    protected $body;
    protected $header;
    protected $footer;
    protected $controller;
    protected $pageName;
    protected $renderObject;
    protected $formDetails;
    protected $formSlots;
    protected $isForm;
    protected $plugins;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('div');
        $this->formDetails = [];
        $this->plugins = [];
        $this->isForm = false;
    }
    
    /**
     * Enable form wrapper
     */
    public function enableForm()
    {
        $this->isForm = true;
    }
    
    /**
     * Set controller.
     */
    public function setController($controller)
    {
        $this->controller = $controller;
    }
    
    /**
     * Set page name
     */
    public function setPageName($name)
    {
        $this->pageName = $name;
    }
    
    /**
     *
     */
    public function setRenderObject(TRecord $object)
    {
        $this->renderObject = $object;
    }
    
    /**
     *
     */
    public function getRenderObject()
    {
        return $this->renderObject;
    }
    
    /**
     * Validate inner form
     */
    public function validate()
    {
        $this->form->validate();
    }
    
    /**
     * Get validated post data
     */
    public function getData()
    {
        return $this->form->getData();
    }
    
    /**
     * Return the form
     */
    public function getForm()
    {
        return $this->form;
    }
    
    /**
     * Return the datagrid
     */
    public function getDatagrid()
    {
        return $this->datagrid;
    }
    
    /**
     * Return the cardview
     */
    public function getCardView()
    {
        return $this->cardview;
    }
    
    /**
     * Return the kanban
     */
    public function getKanban()
    {
        return $this->kanban;
    }
    
    /**
     * Return the calendar
     */
    public function getCalendar()
    {
        return $this->calendar;
    }
    
    /**
     * Return the form detail
     */
    public function getFormDetail($detail_name)
    {
        return $this->formDetails[$detail_name] ?? null;
    }
    
    /**
     * Return the plugin
     */
    public function getPlugin($plugin_name)
    {
        return $this->plugins[$plugin_name] ?? null;
    }
    
    /**
     * Add a widget in a form slot
     */
    public function addInFormSlot($slot, $widget)
    {
        if (!empty($this->formSlots[$slot]))
        {
            $this->formSlots[$slot]->add($widget);
            $this->form->addField($widget);
        }
    }
    
    /**
     * Parse XML form file
     * @param $filename XML form file path
     */
    public function parseFile($filename)
    {
        $xml = file_get_contents($filename);
        
        $this->parseString($xml);
    }
    
    /**
     * Parse XML String
     */
    public function parseString($xml)
    {
        // Adjustes break lines
        $xml = str_replace("\r","\n", $xml);
        $xml = str_replace("\n\n","\n", $xml);
        // $xml = str_replace("\n",'&#10;', $xml);

        $node = new SimpleXMLElement($xml);
        $widgets = $this->parseElement($node);
        
        if ($widgets)
        {
            foreach ($widgets as $widget)
            {
                parent::add($widget);
            }
        }
    }
    
    /**
     * parse a xml element 
     * @param $node SimpleXMLElement node object
     * @ignore-autocomplete on
     */
    private function parseElement($node)
    {
        $widgets = [];
        
        foreach ($node as $object)
        {
            $class = $object-> getName ();
            
            switch ($class)
            {
                case 'tform':
                    $widgets[] = $this->makeTForm($object);
                    break;
                case 'wrapper':
                    $widgets[] = $this->makeTWrapper($object);
                    break;
                case 'tformslot':
                    $widgets[] = $this->makeTFormSlot($object);
                    break;
                case 'tpageframe':
                    $widgets[] = $this->makeTPageFrame($object);
                    break;
                case 'tpagewrapper':
                    $widgets[] = $this->makeTPageWrapper($object);
                    break;
                case 'tformseparator':
                    $widgets[] = $this->makeTFormSeparator($object);
                    break;
                case 'ttabs':
                    $widgets[] = $this->makeTNotebook($object);
                    break;
                case 'tnotebook':
                    $widgets[] = $this->makeTNotebook($object);
                    break;
                case 'tdatagrid':
                    $datagrid = $this->makeTDatagrid($object);
                    
                    if (is_array($datagrid)) // datagrid + pagenav
                    {
                        $widgets = array_merge($widgets, $datagrid);
                    }
                    else
                    {
                        $widgets[] = $datagrid;
                    }
                    break;
                case 'tcardview':
                    $cardview = $this->makeTCardView($object);
                    
                    if (is_array($cardview)) // cardview + pagenav
                    {
                        $widgets = array_merge($widgets, $cardview);
                    }
                    else
                    {
                        $widgets[] = $cardview;
                    }
                    break;
                case 'tkanban':
                    $widgets[] = $this->makeTKanban($object);
                    break;
                case 'tcalendar':
                    $widgets[] = $this->makeTCalendar($object);
                    break;
                case 'tlabel':
                    $widgets[] = $this->makeTLabel($object);
                    break;
                case 'thyperlink':
                    $widgets[] = $this->makeTHyperLink($object);
                    break;
                case 'tdbtextdisplay':
                    $widgets[] = $this->makeTTextDisplay($object);
                    break;
                case 'tbarcodedisplay':
                    $widgets[] = $this->makeTBarcodeDisplay($object);
                    break;
                case 'tqrcodedisplay':
                    $widgets[] = $this->makeTQRCodeDisplay($object);
                    break;
                case 'tdbtextlookup':
                    $widgets[] = $this->makeTTextDisplay($object);
                    break;
                case 'thtml':
                    $widgets[] = $this->makeTHTML($object);
                    break;
                case 'tentry':
                    $widgets[] = $this->makeTEntry($object);
                    break;
                case 'tnumeric':
                    $widgets[] = $this->makeTNumeric($object);
                    break;
                case 'tmultientry':
                    $widgets[] = $this->makeTMultiEntry($object);
                    break;
                case 'tbutton':
                    $widgets[] = $this->makeTButton($object);
                    break;
                case 'tpassword':
                    $widgets[] = $this->makeTPassword($object);
                    break;
                case 'tdate':
                    $widgets[] = $this->makeTDate($object);
                    break;
                case 'tdatetime':
                    $widgets[] = $this->makeTDateTime($object);
                    break;
                case 'thtmleditor':
                    $widgets[] = $this->makeTHtmlEditor($object);
                    break;
                case 'tmultifile':
                    $widgets[] = $this->makeTMultiFile($object);
                    break;
                case 'ttime':
                    $widgets[] = $this->makeTTime($object);
                    break;
                case 'timagecropper':
                    $widgets[] = $this->makeTImageCropper($object);
                    break;
                case 'timagecapture':
                    $widgets[] = $this->makeTImageCapture($object);
                    break;
                case 'thidden':
                    $widgets[] = $this->makeTHidden($object);
                    break;
                case 'tqrcodeinputreader':
                    $widgets[] = $this->makeTQrcodeInputReader($object);
                    break;
                case 'tbarcodeinputreader':
                    $widgets[] = $this->makeTBarcodeInputReader($object);
                    break;
                case 'tfile':
                    $widgets[] = $this->makeTFile($object);
                    break;
                case 'tcolor':
                    $widgets[] = $this->makeTColor($object);
                    break;
                case 'ticon':
                    $widgets[] = $this->makeTIcon($object);
                    break;
                case 'timage':
                    $widgets[] = $this->makeTImage($object);
                    break;
                case 'ttext':
                    $widgets[] = $this->makeTText($object);
                    break;
                case 'tcheckgroup':
                    $widgets[] = $this->makeTCheckGroup($object);
                    break;
                case 'tdbcheckgroup':
                    $widgets[] = $this->makeTDBCheckGroup($object);
                    break;
                case 'tradiogroup':
                    $widgets[] = $this->makeTRadioGroup($object);
                    break;
                case 'tdbradiogroup':
                    $widgets[] = $this->makeTDBRadioGroup($object);
                    break;
                case 'tcombo':
                    $widgets[] = $this->makeTCombo($object);
                    break;
                case 'tuniquesearch':
                    $widgets[] = $this->makeTUniqueSearch($object);
                    break;
                case 'tdbuniquesearch':
                    $widgets[] = $this->makeTDBUniqueSearch($object);
                    break;
                case 'tarrowstep':
                    $widgets[] = $this->makeTArrowStep($object);
                    break;
                case 'tdbarrowstep':
                    $widgets[] = $this->makeTDBArrowStep($object);
                    break;
                case 'tdbcombo':
                    $widgets[] = $this->makeTDBCombo($object);
                    break;
                case 'tspinner':
                    $widgets[] = $this->makeTSpinner($object);
                    break;
                case 'tstepper':
                    $widgets[] = $this->makeTSpinner($object, true);
                    break;
                case 'tslider':
                    $widgets[] = $this->makeTSlider($object);
                    break;
                case 'tlikertscale':
                    $widgets[] = $this->makeTLikertScale($object);
                    break;
                case 'tselect':
                    $widgets[] = $this->makeTSelect($object);
                    break;
                case 'tdbselect':
                    $widgets[] = $this->makeTDBSelect($object);
                    break;
                case 'tsortlist':
                    $widgets[] = $this->makeTSortList($object);
                    break;
                case 'tdbsortlist':
                    $widgets[] = $this->makeTDBSortList($object);
                    break;
                case 'tmultisearch':
                    $widgets[] = $this->makeTMultiSearch($object);
                    break;
                case 'tdbmultisearch':
                    $widgets[] = $this->makeTDBMultiSearch($object);
                    break;
                case 'tmulticombo':
                    $widgets[] = $this->makeTMultiCombo($object);
                    break;
                case 'tdbmulticombo':
                    $widgets[] = $this->makeTDBMultiCombo($object);
                    break;
                case 'tpagestep':
                    $widgets[] = $this->makeTPageStep($object);
                    break;
                case 'tplugincomment':
                    $widgets[] = $this->makeTPluginComment($object);
                    break;
                case 'tpluginattach':
                    $widgets[] = $this->makeTPluginAttach($object);
                    break;
                case 'tplugintimetrack':
                    $widgets[] = $this->makeTPluginTimeTrack($object);
                    break;
                case 'tpluginchecklist':
                    $widgets[] = $this->makeTPluginChecklist($object);
                    break;
            }
        }
        
        return $widgets;
    }
    
    /**
     * Remove prefix from dbattributes
     */
    private function fixDBAttributes($attributes)
    {
        $list = ['db_key_col', 'db_name_col', 'db_order_col', 'db_color_col', 'attribute', 'db_content_col', 'db_minutes_col', 'db_checked_col', 'db_file_col', 'db_createdat_col', 'db_createdby_col'];
        foreach ($list as $attribute)
        {
            if (!empty($attributes[$attribute]) && substr($attributes[$attribute],0,1) == '[')
            {
                $parts = explode('->', $attributes[$attribute], 2);
                $attributes[$attribute] = substr($parts[1],0,-1);
            }
        }
        return $attributes;
    }
    
    /**
     * Validate DB Attributes
     */
    private function validateDBAttributes($attributes, $object)
    {
        $not_valid = [];
        if (empty($attributes['db_connector']))
        {
            $not_valid[] = _t('Connector');
        }
        
        if (empty($attributes['db_model']))
        {
            $not_valid[] = _t('Model');
        }
        
        if (empty($attributes['db_key_col']))
        {
            $not_valid[] = _t('Identification field');
        }
        
        if (empty($attributes['db_name_col']))
        {
            $not_valid[] = _t('Display field');
        }
        
        if (count($not_valid) > 0)
        {
            $image = new TImage('fa:warning orange');
            $image->title = _t('Required properties') . ': ' . implode(', ', $not_valid);
            $object->after($image);
        }
    }
    
    /**
     * Return node attributes
     */
    public function getNodeAttributes($node)
    {
        $node_attributes = $node-> attributes ();
        if (is_null($node_attributes))
        {
            return [];
        }
        $attributes = iterator_to_array($node_attributes);
        
        array_walk($attributes, function(&$value, $key) {
            if ($key === 'height') {
                $value = str_replace('px', '', $value);
            }
            
            $value = (string) $value;
        });
        
        /*
        foreach ($attributes as $key => $value)
        {
            $camel = lcfirst(AdiantiStringConversion::camelCaseFromUnderscore($key));
            if (strpos($key, '_') !== false && $key !== $camel)
            {
                $attributes[$camel] = $value;
            }
        }
        */
        
        return $attributes;
    }
    
    /**
     * Add Form
     */
    public function makeTForm($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $this->form   = new TForm('form_'.$this->controller);
        
        if (!$this->isForm)
        {
            $this->form->setTagName('div');
        }
        
        $this->panel  = new TElement('div');
        $this->body   = new TElement('div');
        $this->header = new TElement('div');
        $this->footer = new TElement('div');
        
        $this->panel->{'class'} = 'card card-form';
        $this->body->{'class'} = 'card-body';
        $this->header->{'class'} = 'card-header';
        $this->footer->{'class'} = 'card-footer';
        
        $this->body->add($this->form);
        
        /**
         * Page header
         */
        if (!empty($node->{'tformheader'}))
        {
            $row_wrapper = new TElement('div');
            $row_wrapper->{'style'} = 'width: 100%';
            $this->header->add($row_wrapper);
            
            $header_has_content = false;
            if (!empty($node->{'tformheader'}->{'trow'}))
            {
                foreach ($node->{'tformheader'}->{'trow'} as $trow)
                {
                    $row = $this->parseFormRow($trow, false);
                    $row_wrapper->add($row);
                    $header_has_content = true;
                }
            }
            
            if ($header_has_content)
            {
                $this->panel->add($this->header);
            }
        }
        
        /**
         * Page body
         */
        $this->panel->add($this->body);
        $form_body = $this->parseFormBody($node);
        
        if ($form_body)
        { 
            $this->form->add($form_body);
        }
        
        /**
         * Page footer
         */
        if (!empty($node->{'tformfooter'}))
        {
            $row_wrapper = new TElement('div');
            $row_wrapper->{'style'} = 'width: 100%';
            $this->footer->add($row_wrapper);
            
            $footer_has_content = false;
            if (!empty($node->{'tformfooter'}->{'trow'}))
            {
                foreach ($node->{'tformfooter'}->{'trow'} as $trow)
                {
                    $row = $this->parseFormRow($trow, false);
                    $row_wrapper->add($row);
                    $footer_has_content = true;
                }
            }
            
            if ($footer_has_content)
            {
                $this->panel->add($this->footer);
            }
        }
        
        return $this->panel;
    }
    
    /**
     * Parse form body
     */
    private function parseFormBody($node)
    {
        if (!empty($node->{'tformbody'}))
        {
            $row_wrapper = new TElement('div');
            $row_wrapper->{'style'} = 'width: 100%';
            
            if (!empty($node->{'tformbody'}->{'ttabs'}))
            {
                foreach ($node->{'tformbody'}->{'ttabs'} as $tabs)
                {
                    $notebook = $this->makeTNotebook($tabs);
                    $row_wrapper->add($notebook);
                }
            }
            else
            {
                if (!empty($node->{'tformbody'}->{'trow'}))
                {
                    foreach ($node->{'tformbody'}->{'trow'} as $trow)
                    {
                        $row = $this->parseFormRow($trow, true);
                        $row_wrapper->add($row);
                    }
                }
            }
            
            return $row_wrapper;
        }
    }
    
    /**
     * Add Row
     */
    private function parseFormRow($trow, $responsive = true)
    {
        $row = new TElement('div');
        $row->{'class'} = 'form-group tformrow row';
        
        foreach ($trow as $slot)
        {
            $classes = implode(' ', $this->getNodeAttributes($slot));
            $widgets = $this->parseElement( $slot );
            
            $col = new TElement('div');
            
            if ($responsive)
            {
                $col->{'class'} = str_replace('-', '-sm-', $classes);
            }
            else
            {
                $col->{'class'} = $classes;
            }
            
            foreach ($widgets as $widget)
            {
                $col->add($widget);
            }
            $row->add($col);
        }
        
        return $row;
    }
    
    /**
     * Add Notebook
     */
    public function makeTNotebook($tabs)
    {
        $id = rand();
        
        $notebook = new BootstrapNotebookWrapper( new TNotebook, 'bordered' );
        
        if (!empty($tabs->{'ttab'}))
        {
            foreach($tabs->{'ttab'} as $ttab)
            {
                $attributesTab = $this->getNodeAttributes($ttab);
                
                $tab_content = new TElement('div');
                $notebook->appendPage($attributesTab['name'], $tab_content);
                
                if (! empty($ttab->{'trow'}))
                {
                    foreach ($ttab->{'trow'} as $trow)
                    {
                        $row = $this->parseFormRow($trow);
                        $tab_content->add($row);
                    }
                }
            }
        }
        
        return $notebook;
    }
    
    /**
     * Add Datagrid
     */
    public function makeTDatagrid($node)
    {
        $attributes = $this->getNodeAttributes($node);
        $attributes = $this->fixDBAttributes($attributes);
        
        $datagrid = new TDataGrid;
        $datagrid->class  = 'table table-striped table-hover';
        $datagrid->widget = 'bootstrapdatagridwrapper';
        $datagrid->type   = 'bootstrap';
        $datagrid->width  = '100%';
        $datagrid->style .= ';border-collapse:collapse';
        
        if (!empty($attributes['db_model']))
        {
            $db_model = $attributes['db_model'];
            $datagrid->setMetadata('db_model', $attributes['db_model'] ?: null);
            $datagrid->setMetadata('db_order_col', !empty($attributes['db_order_col']) ? $attributes['db_order_col'] : $db_model::PRIMARYKEY);
            $datagrid->setMetadata('db_order_dir', !empty($attributes['db_order_dir']) ? $attributes['db_order_dir'] : 'asc');
        }
        
        $datagrid->setMetadata('attributes', $attributes);
        
        $this->datagrid = $datagrid;
        
        if (!empty($attributes['action_side']))
        {
            $datagrid->setActionSide($attributes['action_side']);
        }
        
        if (!empty($attributes['popover']))
        {
            $datagrid->enablePopover('', $attributes['popover']);
        }
        
        if (!empty($attributes['page_size']))
        {
            $datagrid->setPageSize($attributes['page_size']);
        }
        
        if (!empty($attributes['page_orientation']))
        {
            $datagrid->setPageOrientation($attributes['page_orientation']);
        }
        
        if (!empty($attributes['default_click']) && ($attributes['default_click']) =='N' )
        {
            $datagrid->disableDefaultClick();
        }
        
        if (!empty($attributes['mutation_action']))
        {
            $action = new TAction([$this->controller, $attributes['mutation_action']]);
            $datagrid->setMutationAction($action);
        }
        
        $col_sizes = [ 'very_small' => 10, 'small' => 25, 'medium' => 50, 'large' => 75, 'very_large' => 100 ];
        
        $column_real_width = 0;
        $column_total_width = 0;
        
        $first_col = '';
        
        if ($attributes['role'] == 'form_detail')
        {
            $datagrid->generateHiddenFields();
            foreach ($node->{'tform'} as $tform)
            {
                $form_atts = $this->getNodeAttributes($tform, false);
                
                if ($form_atts['role'] == 'edit_form')
                {
                    $detail_name = 'detail_'.(count($this->formDetails) +1);
                    $form = $this->makeEditForm($tform, false, $detail_name.'_');
                    $datagrid->setEditForm($form);
                    $this->formDetails[$detail_name] = $datagrid;
                    $datagrid->setId($detail_name);
                    $form->getField($detail_name . '_save_button')->setAction(new TAction([$this->controller, 'onSaveDetail'], ['_detail' => $detail_name, 'static' => '1']));
                }
            }
            
            // used in saveDetail()
            $column = new TDataGridColumn('_rowid', '', 'left', 0);
            $column->setVisibility(false);
            $datagrid->addColumn($column);
            
            $column = new TDataGridColumn('_rawobj', '', 'left', 0);
            $column->setVisibility(false);
            $datagrid->addColumn($column);
        }
        
        $configuration = TSession::getValue($this->controller.'_datagrid_columns');
        
        $visible_columns = 0;
        
        // primeira passada para calcular totais.
        foreach ($node->{'tdatagridcolumn'} as $tdatagridcolumn)
        {
            $column_atts = $this->getNodeAttributes($tdatagridcolumn);
            
            if (!empty($column_atts['attribute']))
            {
                $attribute = $column_atts['attribute'];
                $attribute = str_replace('->', '?->', $attribute);
                
                $column_key  = base64_encode($attribute.$column_atts['title']);
                $is_visible = !empty($configuration[$column_key]['visible']) ? ($configuration[$column_key]['visible'] == 'Y') : (empty($column_atts['visible']) || $column_atts['visible'] !== 'N');
                $width      = !empty($configuration[$column_key]['width'])   ? $configuration[$column_key]['width'] : ($column_atts['width'] ?? 'very_large');
                
                if ($is_visible)
                {
                    $column_real_width = $col_sizes[$width];
                    $column_total_width += $column_real_width;
                    
                    $first_col = empty($first_col) ? $column_atts['attribute'] : $first_col;
                    $visible_columns ++;
                }
            }
        }
        
        if ($visible_columns == 0)
        {
            throw new Exception('no visible columns');
        }
        $order_commands = [];
        
        $columns = [];
        
        foreach ($node->{'tdatagridcolumn'} as $tdatagridcolumn)
        {
            $column_atts = $this->getNodeAttributes($tdatagridcolumn);
            
            if (!empty($column_atts['attribute']))
            {
                $attribute = $column_atts['attribute'];
                $attribute = str_replace('->', '?->', $attribute);
                $label = $column_atts['title'];
                
                $column_key  = base64_encode($attribute.$column_atts['title']);
                $width      = !empty($configuration[$column_key]['width'])   ? $configuration[$column_key]['width'] : ($column_atts['width'] ?? 'very_large');
                $align      = !empty($configuration[$column_key]['align']) ? $configuration[$column_key]['align'] : ($column_atts['align'] ?? 'left');
                $is_visible = !empty($configuration[$column_key]['visible']) ? ($configuration[$column_key]['visible'] == 'Y') : (empty($column_atts['visible']) || $column_atts['visible'] !== 'N');
                
                $column_real_width = $col_sizes[$width];
                $column_width = 100 / ($column_total_width/100) * ($column_real_width /100);
                
                if (!empty($column_atts['filter_field']))
                {
                    $filter_data = TSession::getValue($this->controller.'_filter_data');
                    $filter_color = empty($filter_data->{$column_atts['filter_field']}) ? '#aaa' : 'blue';
                    
                    $filter_action = new TAction([$this->controller, 'onDisplayColumnFilter'], ['static' => '1', 'static_call' => '1', 'filter_field' => $column_atts['filter_field'], 'column_label' => $label]);
                    $filter_action->usePopover();
                    $filter_link = new TActionLink('', $filter_action, null, null, null, 'fa:filter ' . $filter_color);
                    $filter_link->onclick = "event.stopPropagation()";
                    $label = $column_atts['title'] . '&nbsp;' . $filter_link;
                }
                
                $column = new TDataGridColumn($attribute, $label, $align, round($column_width,2) . '%');
                $column->setProperty('data-real-width', $column_atts['width']);
                $column->setProperty('data-original-title', $column_atts['title']);
                $column->setProperty('data-key', $column_key); // just by sort purposes
                
                $columns[] = $column;
                
                if (!$is_visible)
                {
                    $column->setVisibility(false);
                    $column->setProperty('visible', 'false');
                }
                
                if (!empty($column_atts['totalizer']) && $column_atts['totalizer'] == 'sum')
                {
                    $column->enableTotal('sum', '', 2, ',', '.');
                }
                
                if (!empty($column_atts['auto_hide']))
                {
                    $column->enableAutoHide($column_atts['auto_hide']);
                }
                
                if (!empty($column_atts['printable']) && $column_atts['printable'] == 'N')
                {
                    $column->disablePrinting();
                }
                
                if (!empty($column_atts['db_order_col']))
                {
                    $column_atts = $this->fixDBAttributes($column_atts);
                    $column->setAction(new TAction([$this->controller, 'onReload']), ['order' => $column_atts['db_order_col']]);
                    $column->setProperty('data-order-col', $column_atts['db_order_col']);
                    
                    $order_parts = explode('->', $column_atts['db_order_col'], 2); // cidade->nome
                    
                    if (count($order_parts) == 2)
                    {
                        // Order by associated column (Subquery)
                        $order_command = $this->getOrderCommand($db_model, $column_atts['db_order_col']);
                        
                        if (!empty($order_command))
                        {
                            $order_commands[ $column_atts['db_order_col'] ] = $order_command;
                        }
                    }
                }
                
                $transformer = null;
                
                if (!empty($column_atts['transformation']) )
                {
                    list($transformer_class, $transformer_method) = explode('::', $column_atts['transformation']);
                    
                    if (method_exists($transformer_class, $transformer_method))
                    {
                        $column->setTransformer($column_atts['transformation']);
                        $transformer = $column_atts['transformation'];
                    }
                }
                
                if (!empty($column_atts['local_transformation']) )
                {
                    if (method_exists($this->controller, $column_atts['local_transformation']))
                    {
                        $column->setTransformer([$this->controller, $column_atts['local_transformation']]);
                        $transformer = [$this->controller, $column_atts['local_transformation']];
                    }
                }
                
                if (!empty($column_atts['action_class']) && !empty($column_atts['action_method']) )
                {
                    $column->setTransformer(function($data, $object, $row, $cell, $last_row, $for_printing) use ($column_atts, $transformer) {
                        if (!empty($transformer))
                        {
                            $data = call_user_func($transformer, $data, $object, $row, $cell, $last_row, $for_printing);
                        }
                        
                        $action = new TAction([$column_atts['action_class'], $column_atts['action_method']], ['key' => $object->getPrimaryKeyValue()]);
                        
                        $cell->href = '#'; // block default click
                        $cell->{'data-popover'}    = 'true';
                        $cell->popside    = 'bottom';
                        $cell->poptrigger = 'click';
                        $cell->popaction  = $action->serialize(FALSE);
                        $cell->class = ' popover_action';
                        
                        return $data;
                    });
                }
            }
        }
        
        // sort columns according to the user previous selection (not predefined in XML)
        if (!empty($configuration))
        {
            $order = array_keys($configuration);
            usort($columns, function($a, $b) use ($order) {
                return strcmp(array_search($a->getProperty('data-key'), $order), array_search($b->getProperty('data-key'), $order));
            });
        }
        
        if ($columns)
        {
            foreach ($columns as $column)
            {
                $datagrid->addColumn($column);   
            }
        }
        
        $datagrid->setMetadata('order_commands', $order_commands);
        
        if (!empty($attributes['group_actions']) && ($attributes['group_actions'] == 'Y'))
        {
            $action_group = new TDataGridActionGroup('', 'fa:ellipsis');
            $datagrid->addActionGroup($action_group);
        }
        
        foreach ($node->{'tdatagridaction'} as $tdatagridaction)
        {
            $action_atts = $this->getNodeAttributes($tdatagridaction);
            
            if (!empty($action_atts['action_class']) && !empty($action_atts['action_method']))
            {
                $icon = str_replace(['far fa-', 'fas fa-', 'fab fa-', 'fal fa-', 'fad fa-'], ['far:', 'fas:', 'fab:', 'fal:', 'fad:'], $action_atts['icon']);
                
                if ($attributes['role'] == 'datagrid' || $attributes['role'] == 'view_detail' || $attributes['role'] == 'form_detail')
                {
                    $db_model = $attributes['db_model'];
                    $pk_name = $db_model::PRIMARYKEY;
                    $action = new TDataGridAction([$action_atts['action_class'], $action_atts['action_method']],   ['key' => '{'.$pk_name.'}', $pk_name => '{'.$pk_name.'}'] );
                }
                else
                {
                    $action = new TDataGridAction([$action_atts['action_class'], $action_atts['action_method']],   ['key' => '{'.$first_col.'}'] );
                }
                
                $this->configureAction($action, $action_atts);
                
                if (!empty($action_atts['display_condition']))
                {
                    $action->setDisplayCondition( [$this->controller, $action_atts['display_condition']] );
                }
                
                if ($attributes['role'] == 'form_detail' && !empty($detail_name))
                {
                    $action->enablePost();
                    $action->setParameter('_detail', $detail_name);
                    //$action->setParameter('_rowid', '{_rowid}'); // {*} already includes all object attributes
                    $action->setParameter('*', '{*}');
                }
                
                if (!empty($action_atts['use_popover']) && ($action_atts['use_popover'] == 'Y'))
                {
                    $action->usePopover();
                }
                
                if (!empty($attributes['group_actions']) && ($attributes['group_actions'] == 'Y'))
                {
                    $action_group->addAction($action);
                    $action->setLabel($action_atts['label']);
                    $action->setImage($icon . ' ' . $action_atts['color']);
                }
                else
                {
                    if (!empty($action_atts['color']))
                    {
                        $datagrid->addAction($action, $action_atts['label'], $icon . ' ' . $action_atts['color']);
                    }
                    else
                    {
                        $datagrid->addAction($action, $action_atts['label'], $icon);
                    }
                }
            }
        }
        
        if (!$datagrid->createModel())
        {
            if ($attributes['role'] == 'datagrid')
            {
                throw new Exception(_t('The datagrid has no valid columns'));
            }
            else
            {
                return;
            }
        }
        
        if (in_array($attributes['role'], ['view_detail', 'document_detail']) && $this->renderObject && !empty($attributes['db_model']))
        {
            $criteria = $this->buildCriteriaFromFilters($node->{'tdatagridfilter'});
            $this->loadComposedObjects($datagrid, $criteria, $attributes);
        }
        else if ($attributes['role'] == 'datagrid')
        {
            $form = $this->makeSearchForm($node->{'tform'}, true, true);
            $datagrid->setSearchForm($form);
            
            $criteria    = $this->buildCriteriaFromFilters($node->{'tdatagridfilter'});
            $datagrid->setMetadata('criteria', $criteria);
            
            $formfilters = $this->getFormFilters($node->{'tdatagridfilter'});
            $formfilters = $this->replaceAggregatedFilters($datagrid, $formfilters, $attributes, 'tdatagrid');
            $datagrid->setMetadata('form_filters', $formfilters);
        }
        
        if (!empty($attributes['page_navigation']) && $attributes['page_navigation'] == 'Y')
        {
            $pagenav = new TPageNavigation;
            $pagenav->enableCounters();
            $datagrid->setPageNavigation($pagenav);
            
            return [$datagrid, $pagenav];
        }
        else
        {
            return $datagrid;
        }
    }
    
    /**
     * Add CardView
     */
    public function makeTCardView($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $cardview = new TCardView;
        $cardview->setItemDatabase(TTransaction::getDatabase()); // pq os itens são resolvidos no show.
        $cardview->setUseButton();
        $this->cardview = $cardview;
        
        if (!empty($attributes['min_height']))
        {
            $cardview->setContentHeight($attributes['min_height']);
        }
        
        if (!empty($attributes['page_size']))
        {
            $cardview->setPageSize($attributes['page_size']);
        }
        
        if (!empty($attributes['page_orientation']))
        {
            $cardview->setPageOrientation($attributes['page_orientation']);
        }
        
        if (!empty($attributes['item_width']) && !empty($attributes['item_width_unit']))
        {
            $cardview->setItemWidth($attributes['item_width'].$attributes['item_width_unit']);
        }
        
        $cardview->setMetadata('attributes', $attributes);
        
        // percorre o card body para transformar em template.
        if (!empty($node->{'tcardbody'}))
        {
            $cardview->setItemTemplateCallback( function($item) use ($node) {
                if ($item instanceof \Adianti\Database\TRecord)
                {
                    $this->setRenderObject($item);
                }
                
                $rows = [];
                if (!empty($node->{'tcardbody'}->{'trow'}))
                {
                    foreach ($node->{'tcardbody'}->{'trow'} as $trow)
                    {
                        $rows[] = $this->parseFormRow($trow, false);
                    }
                }
                
                return implode('', $rows);
            });
        }
        
        foreach ($node->{'tcardaction'} as $tcardaction)
        {
            $action_atts = $this->getNodeAttributes($tcardaction);
            
            if (!empty($action_atts['action_class']) && !empty($action_atts['action_method']))
            {
                $icon = str_replace(['far fa-', 'fas fa-', 'fab fa-', 'fal fa-', 'fad fa-'], ['far:', 'fas:', 'fab:', 'fal:', 'fad:'], $action_atts['icon']);
                
                $db_model = $attributes['db_model'];
                $pk_name = $db_model::PRIMARYKEY;
                
                $action = new TAction([$action_atts['action_class'], $action_atts['action_method']],  ['key' => '{'.$pk_name.'}', $pk_name => '{'.$pk_name.'}'] );
                
                $this->configureAction($action, $action_atts);
                
                if (!empty($action_atts['use_popover']) && ($action_atts['use_popover'] == 'Y'))
                {
                    $action->usePopover();
                }
                
                $display_condition = null;
                if (!empty($action_atts['display_condition']))
                {
                    $display_condition = [$this->controller, $action_atts['display_condition']];
                }
                
                $tooltip = !empty($action_atts['tooltip']) ? $action_atts['tooltip'] : null;
                $card_action = $cardview->addAction($action, $action_atts['label'], $icon . ' ' . $action_atts['color'], $display_condition, $tooltip);
                $card_action->buttonClass = 'btn btn-sm btn-default';
            }
        }
        
        $criteria = $this->buildCriteriaFromFilters($node->{'tcardfilter'});
        $cardview->setMetadata('criteria', $criteria);
        
        $formfilters = $this->getFormFilters($node->{'tcardfilter'});
        $formfilters = $this->replaceAggregatedFilters($cardview, $formfilters, $attributes, 'tcardview');
        $cardview->setMetadata('form_filters', $formfilters);
        
        // make search form
        $form = $this->makeSearchForm($node->{'tform'});
        $cardview->setSearchForm($form);
        
        if (!empty($attributes['page_navigation']) && $attributes['page_navigation'] == 'Y')
        {
            $pagenav = new TPageNavigation;
            $pagenav->enableCounters();
            $cardview->setPageNavigation($pagenav);
            
            return [$cardview, $pagenav];
        }
        else
        {
            return $cardview;
        }
    }
    
    /**
     * Add Kanban
     */
    public function makeTKanban($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $kanban = new TKanban;
        $kanban->setItemDatabase(TTransaction::getDatabase()); // pq os itens são resolvidos no show.
        // $kanban->setUseButton();
        $this->kanban = $kanban;
        
        $kanban->setMetadata('stage_class', $attributes['db_model'] ?? null);
        $kanban->setMetadata('stage_order', $attributes['db_order_col'] ?? null);
        $kanban->setMetadata('stage_name',  $attributes['db_name_col'] ?? null);
        $kanban->setMetadata('item_seq',    $attributes['db_sequence_col'] ?? null);
        
        $kanban->setMetadata('attributes', $attributes);
        
        if (!empty($attributes['db_model']) && !empty($attributes['db_model2']))
        {
            // busca a fkey para a model associada.
            $item_class = $attributes['db_model2'];
            $object = new $item_class;
            $association = $object->findAssociationFor($attributes['db_model']);
            
            if (!empty($association))
            {
                $kanban->setMetadata('stage_field', $association['fkey']);
            }
            else
            {
                $kanban->before(new TAlert('danger', _t('Association not found between ^1 and ^2', '<b>' . get_class($object) . '</b>', '<b>' . $attributes['db_model'] . '</b>') . '. ' . _t('Review the relationships between classes')));
            }
        }
        
        // percorre o kanban body para transformar em template.
        if (!empty($node->{'tkanbanbody'}))
        {
            $kanban->setItemTemplateCallback( function($item) use ($node) {
                if ($item instanceof \Adianti\Database\TRecord)
                {
                    $this->setRenderObject($item);
                }
                
                $rows = [];
                if (!empty($node->{'tkanbanbody'}->{'trow'}))
                {
                    foreach ($node->{'tkanbanbody'}->{'trow'} as $trow)
                    {
                        $rows[] = $this->parseFormRow($trow, false);
                    }
                }
                
                return implode('', $rows);
            });
        }
        
        $kanban->addStageShortcut(_t('Add'), new TAction([$this->controller, 'onAddItem'], ['register_state' => 'false', 'static' => '1']),   'fa:plus fa-fw');
        
        if (!empty($attributes['allow_drag']) && $attributes['allow_drag'] == 'Y')
        {
            $kanban->setItemDropAction(new TAction([$this->controller, 'onMoveItem'], ['static'=>'1']));
        }
        
        foreach ($node->{'tkanbanaction'} as $tkanbanaction)
        {
            $action_atts = $this->getNodeAttributes($tkanbanaction);
            
            if (!empty($action_atts['action_class']) && !empty($action_atts['action_method']))
            {
                $icon = str_replace(['far fa-', 'fas fa-', 'fab fa-', 'fal fa-', 'fad fa-'], ['far:', 'fas:', 'fab:', 'fal:', 'fad:'], $action_atts['icon']);
                
                $db_model = $attributes['db_model2']; //db_model2 is the item model
                $pk_name = $db_model::PRIMARYKEY;
                
                $action = new TAction([$action_atts['action_class'], $action_atts['action_method']],  ['key' => '{'.$pk_name.'}', $pk_name => '{'.$pk_name.'}'] );
                
                $this->configureAction($action, $action_atts);
                
                if (!empty($action_atts['use_popover']) && ($action_atts['use_popover'] == 'Y'))
                {
                    $action->usePopover();
                }
                
                $display_condition = null;
                if (!empty($action_atts['display_condition']))
                {
                    $display_condition = [$this->controller, $action_atts['display_condition']];
                }
                
                $kanban_action = $kanban->addItemAction($action_atts['label'], $action, $icon . ' ' . $action_atts['color'], $display_condition, true);
                $kanban_action->buttonClass = 'btn btn-sm btn-default';
                
                if (!empty($action_atts['tooltip']))
                {
                    $kanban_action->title = $action_atts['tooltip'];
                }
            }
        }
        
        $criteria = $this->buildCriteriaFromFilters($node->{'tkanbanfilter'});
        $kanban->setMetadata('criteria', $criteria);
        
        $formfilters = $this->getFormFilters($node->{'tkanbanfilter'});
        $formfilters = $this->replaceAggregatedFilters($kanban, $formfilters, $attributes, 'tkanban');
        $kanban->setMetadata('form_filters', $formfilters);
        
        foreach ($node->{'tform'} as $tform)
        {
            $form_atts = $this->getNodeAttributes($tform, false);
            
            if ($form_atts['role'] == 'search_form')
            {
                $form = $this->makeSearchForm($tform, false);
                $kanban->setSearchForm($form);
            }
            else if ($form_atts['role'] == 'edit_form')
            {
                $form = $this->makeEditForm($tform, false);
                $kanban->setEditForm($form);
            }
        }
        
        return $kanban;
    }
    
    /**
     * Add Calendar
     */
    public function makeTCalendar($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $calendar = new TFullCalendar(date('Y-m-d'), 'month');
        $calendar->setReloadAction($get_action = new TAction([$this->controller, 'onGetEvents'], [ 'static' => '1'] ));
        
        $param = $_REQUEST;
        unset($param['class']);
        unset($param['method']);
        unset($param['static']);
        $get_action->setParameters($param);
        
        if (empty($attributes['allow_edit']) || $attributes['allow_edit'] == 'Y')
        {
            $calendar->setDayClickAction(new TAction([$this->controller, 'onAddEvent'], [ 'static' => '1'] ));
            $calendar->setEventClickAction(new TAction([$this->controller, 'onEdit'], [ 'static' => '1'] ));
        }
        
        $calendar->enableFullHeight();
        $calendar->setOption('businessHours', [ [ 'dow' => [ 1, 2, 3, 4, 5 ], 'start' => '08:00', 'end' => '18:00' ]]);
        
        $this->calendar = $calendar;
        
        $calendar->setMetadata('start_field', $attributes['db_start_col'] ?? null);
        $calendar->setMetadata('end_field',   $attributes['db_end_col'] ?? null);
        $calendar->setMetadata('title_field', $attributes['db_name_col'] ?? null);
        $calendar->setMetadata('color_field', $attributes['db_color_col'] ?? null);
        $calendar->setMetadata('popover',     $attributes['popover'] ?? null);
        
        $calendar->setMetadata('attributes', $attributes);
        
        if (!empty($attributes['allow_drag']) && $attributes['allow_drag'] == 'N')
        {
            $calendar->disableDragging();
            $calendar->disableResizing();
        }
        else
        {
            $calendar->setEventUpdateAction(new TAction([$this->controller, 'onUpdateEvent'], [ 'static' => '1'] ));
        }
        
        foreach ($node->{'tform'} as $tform)
        {
            $form_atts = $this->getNodeAttributes($tform, false);
            
            if ($form_atts['role'] == 'search_form')
            {
                $form = $this->makeSearchForm($tform, false);
                $calendar->setSearchForm($form);
            }
            else if ($form_atts['role'] == 'edit_form')
            {
                $edit_form = $this->makeEditForm($tform, false);
                $calendar->setEditForm($edit_form);
            }
        }
        
        foreach ($node->{'tcalendaraction'} as $tcalendaraction)
        {
            $action_atts = $this->getNodeAttributes($tcalendaraction);
            
            if (!empty($action_atts['action_class']) && !empty($action_atts['action_method']))
            {
                $icon = str_replace(['far fa-', 'fas fa-', 'fab fa-', 'fal fa-', 'fad fa-'], ['far:', 'fas:', 'fab:', 'fal:', 'fad:'], $action_atts['icon']);
                
                $db_model = $attributes['db_model'];
                $pk_name = $db_model::PRIMARYKEY;
                
                $action = new TAction([$action_atts['action_class'], $action_atts['action_method']]);
                
                $this->configureAction($action, $action_atts);
                
                $button = new TButton($action_atts['action_class'].'::'.$action_atts['action_method']);
                $button->setLabel($action_atts['label']);
                $button->setImage($icon . ' ' . $action_atts['color']);
                $button->{'class'} = 'btn btn-default btn-sm';
                $button->setAction($action);
                
                $edit_form->addField($button);
                $edit_form->getVirtualProperty('footer')->add($button);
                /*
                $calendar_action = $calendar->addItemAction($action_atts['label'], $action, $icon . ' ' . $action_atts['color'], null, true);
                $calendar_action->buttonClass = 'btn btn-sm btn-default';
                */
            }
        }
        
        $criteria = $this->buildCriteriaFromFilters($node->{'tcalendarfilter'});
        $calendar->setMetadata('criteria', $criteria);
        
        $formfilters = $this->getFormFilters($node->{'tcalendarfilter'});
        $formfilters = $this->replaceAggregatedFilters($calendar, $formfilters, $attributes, 'tcalendar');
        $calendar->setMetadata('form_filters', $formfilters);
        
        return $calendar;
    }
    
    /**
     * Add Wrapper
     */
    public function makeTWrapper($node)
    {
        $wrapper = new TElement('div');
        
        if (! empty($node->{'trow'}))
        {
            foreach ($node->{'trow'} as $trow)
            {
                $row = $this->parseFormRow($trow);
                $wrapper->add($row);
            }
        }
        
        return $wrapper;
    }
    
    /**
     * Add Form slot
     */
    public function makeTFormSlot($node)
    {
        $attributes = $this->getNodeAttributes($node);
        $name = $attributes['name'];
        
        $slot = new TElement('div');
        
        $this->formSlots[$name] = $slot;
        return $slot;
    }
    
    /**
     * Add PageFrame
     */
    public function makeTPageFrame($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $iframe = new TPageFrame;
        $iframe->setClass($attributes['action_class']);
        $iframe->setMethod($attributes['action_method']);
        $iframe->setSize('100%', (!empty($attributes['height'])? $attributes['height'] : '100'));
        
        if (!empty($attributes['object_id']))
        {
            $iframe->setId($attributes['object_id']);
        }
        
        if (!empty($attributes['preserve_params']))
        {
            $preserve_list = array_map('trim', explode(',', $attributes['preserve_params']));
            $iframe->preserveRequestParameters($preserve_list);
        }
        
        if (!empty($attributes['custom_params']))
        {
            $custom_params = str_replace('#rn#', ';', $attributes['custom_params']);
            $lines = explode(";", $custom_params);
            foreach ($lines as $line)
            {
                $parts = array_map('trim', explode(':', $line));
                
                if (!empty($parts[0]) && !empty($parts[1]))
                {
                    $iframe->setParameter($parts[0], $parts[1]);
                }
            }
        }
        
        return $iframe;
    }
    
    /**
     * Add PageWrapper
     */
    public function makeTPageWrapper($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $frame = new TPageWrapper;
        $frame->setClass($attributes['action_class']);
        $frame->setMethod($attributes['action_method']);
        $frame->setSize('100%', (!empty($attributes['height'])? $attributes['height'] : '100'));
        
        if (!empty($attributes['object_id']))
        {
            $frame->setId($attributes['object_id']);
        }
        
        if (!empty($attributes['preserve_params']))
        {
            $preserve_list = array_map('trim', explode(',', $attributes['preserve_params']));
            $frame->preserveRequestParameters($preserve_list);
        }
        
        if (!empty($attributes['custom_params']))
        {
            $custom_params = str_replace('#rn#', ';', $attributes['custom_params']);
            $lines = explode(";", $custom_params);
            foreach ($lines as $line)
            {
                $parts = array_map('trim', explode(':', $line));
                
                if (!empty($parts[0]) && !empty($parts[1]))
                {
                    $frame->setParameter($parts[0], $parts[1]);
                }
            }
        }
        
        return $frame;
    }
    
    /**
     * Add Form separator
     */
    public function makeTFormSeparator($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        return new TFormSeparator($attributes['label'], $attributes['color'], $attributes['font_size']);
    }
    
    /**
     * Add Label
     */
    public function makeTLabel($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        if (empty($attributes['label']) && empty($attributes['font_style']))
        {
            $attributes['label'] = 'Label';
            $attributes['font_style'] = 'b';
        }
        
        $content = $attributes['label'] ?? 'Label';
        $content = str_replace('[page_name]', (string) $this->pageName, $content);
        
        $label = new TLabel( $content );
        
        if (!empty($attributes['color']) && $attributes['color'] !== '#333333') // do not apply default color, to work with darkmode
        {
            $label->setFontColor( $attributes['color'] );
        }
        
        if (!empty($attributes['font_size']))
        {
            $label->setFontSize( $attributes['font_size'] );
        }
        else
        {
            $label->setFontSize( '14px' );
        }
        
        if (!empty($attributes['font_style']))
        {
            $label->setFontStyle( $attributes['font_style'] );
        }
        
        if (!empty($attributes['align']))
        {
            $label->setTextAlign( $attributes['align'] );
        }
        
        if (!empty($attributes['padding']))
        {
            $label->setStyleProperty('padding', $attributes['padding'] . 'px');
        }
        
        if (!empty($attributes['border_radius']))
        {
            $label->setStyleProperty('border-radius', $attributes['border_radius'] . 'px');
        }
        
        if (!empty($attributes['border_style']))
        {
            if (strpos($attributes['border_style'], 'l') !== false)
            {
                $label->setStyleProperty('border-left', "{$attributes['border_width']}px solid {$attributes['border_color']}");
            }
            if (strpos($attributes['border_style'], 'r') !== false)
            {
                $label->setStyleProperty('border-right', "{$attributes['border_width']}px solid {$attributes['border_color']}");
            }
            if (strpos($attributes['border_style'], 't') !== false)
            {
                $label->setStyleProperty('border-top', "{$attributes['border_width']}px solid {$attributes['border_color']}");
            }
            if (strpos($attributes['border_style'], 'b') !== false)
            {
                $label->setStyleProperty('border-bottom', "{$attributes['border_width']}px solid {$attributes['border_color']}");
            }
        }
        
        if (!empty($attributes['background_color']))
        {
            $label->setStyleProperty('background-color', $attributes['background_color']);
        }
        
        $label->setSize('100%');
        
        if (!empty($attributes['style']))
        {
            $label->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $label->class .= ' ' . $attributes['css_class'];
        }
        
        return $label;
    }
    
    /**
     * Add HyperLink
     */
    public function makeTHyperLink($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        if (empty($attributes['label']) && empty($attributes['font_style']))
        {
            $attributes['label'] = 'Link';
            $attributes['font_style'] = 'iu';
        }
        
        $label = $attributes['label'] ?? 'Link';
        $value = $attributes['value'] ?? '';
        $label = str_replace('[page_name]', (string) $this->pageName, $label);
        
        if (!empty($this->renderObject))
        {
            $label = AdiantiTemplateHandler::replace($label, $this->renderObject);
            $value = AdiantiTemplateHandler::replace($value, $this->renderObject);
        }
        
        if ($value)
        {
            $link = new THyperLink($label, $value, $attributes['color'] ?? null, $attributes['font_size'] ?? null, $attributes['font_style'] ?? null);
            $link->{'target'} = 'target_'.mt_rand(1000000000, 1999999999);
            
            if (!empty($attributes['align']))
            {
                $link->setTextAlign( $attributes['align'] );
                
                if ($attributes['align'] !== 'left')
                {
                    $link->setSize('100%');
                    $link->setStyleProperty('display', 'inline-block');
                }
            }
            return $link;
        }
        else
        {
            return new TElement('div');
        }
    }
    
    /**
     * Add TextDisplay
     */
    public function makeTTextDisplay($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $label = $attributes['label'];
        $label = str_replace('[page_name]', (string) $this->pageName, $label);
        
        if (!empty($this->renderObject))
        {
            //$label = $this->renderObject->render($label);
            $label = AdiantiTemplateHandler::replace($label, $this->renderObject);
        }
        
        if (!empty($attributes['lookup_type']) && $attributes['lookup_type'] == '1')
        {
            if (!empty($attributes['db_model']) && !empty($attributes['db_key_col']) && !empty($attributes['db_name_col']) && !empty($label))
            {
                $attributes = $this->fixDBAttributes($attributes);
                $label = $this->getItemFromModel($label, $attributes['db_model'], $attributes['db_key_col'], $attributes['db_name_col']);
            }
        }
        else if (!empty($attributes['lookup_type']) && $attributes['lookup_type'] == 'N')
        {
            if (!empty($attributes['db_model']) && !empty($attributes['db_key_col']) && !empty($attributes['db_name_col']))
            {
                $attributes = $this->fixDBAttributes($attributes);
                $label = $this->getItemsFromModel($label, $attributes['db_model'], $attributes['db_key_col'], $attributes['db_name_col']);
            }
        }
        
        if (!empty($attributes['transformation']))
        {
            list($transformer_class, $transformer_method) = explode('::', $attributes['transformation']);
            
            if (method_exists($transformer_class, $transformer_method))
            {
                $label = call_user_func($attributes['transformation'], $label);
            }
        }
        
        if (empty($label))
        {
            $label = '&nbsp;';
        }
        
        $text = new TTextDisplay( $label ?? 'Text', $attributes['color'] ?? null, $attributes['font_size'] ?? null, $attributes['font_style'] ?? null );
        $text->class .= ' py-1';
        $text->style = 'display:block;width:100%;';
        
        if (!empty($attributes['align']))
        {
            $text->style .= 'text-align:'.$attributes['align'].';';
        }
        
        if (!empty($attributes['padding']))
        {
            $text->setStyleProperty('padding', $attributes['padding'] . 'px');
        }
        
        if (!empty($attributes['border_radius']))
        {
            $text->setStyleProperty('border-radius', $attributes['border_radius'] . 'px');
        }
        
        if (!empty($attributes['border_style']))
        {
            if (strpos($attributes['border_style'], 'l') !== false)
            {
                $text->setStyleProperty('border-left', "{$attributes['border_width']}px solid {$attributes['border_color']}");
            }
            if (strpos($attributes['border_style'], 'r') !== false)
            {
                $text->setStyleProperty('border-right', "{$attributes['border_width']}px solid {$attributes['border_color']}");
            }
            if (strpos($attributes['border_style'], 't') !== false)
            {
                $text->setStyleProperty('border-top', "{$attributes['border_width']}px solid {$attributes['border_color']}");
            }
            if (strpos($attributes['border_style'], 'b') !== false)
            {
                $text->setStyleProperty('border-bottom', "{$attributes['border_width']}px solid {$attributes['border_color']}");
            }
        }
        
        if (!empty($attributes['background_color']))
        {
            $text->setStyleProperty('background-color', $attributes['background_color']);
        }
        
        if (!empty($attributes['style']))
        {
            $text->style .= str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $text->class .= ' ' . $attributes['css_class'];
        }
        
        return $text;
    }
    
    /**
     * Add TBarcodeDisplay
     */
    public function makeTBarcodeDisplay($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $label = $attributes['label'];
        
        if (!empty($this->renderObject))
        {
            //$label = $this->renderObject->render($label);
            $label = AdiantiTemplateHandler::replace($label, $this->renderObject);
        }
        
        $barcode = new TBarcodeDisplay($label);
        $barcode->setType($attributes['barcode_type']);
        $barcode->setHeight($attributes['height']);
        
        return $barcode;
    }
    
    /**
     * Add TQRCodeDisplay
     */
    public function makeTQRCodeDisplay($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $label = $attributes['label'];
        
        if (!empty($this->renderObject))
        {
            //$label = $this->renderObject->render($label);
            $label = AdiantiTemplateHandler::replace($label, $this->renderObject);
        }
        
        $qrcode = new TQRCodeDisplay($label);
        $qrcode->setHeight($attributes['height']);
        
        return $qrcode;
    }
    
    /**
     * Add HTML
     */
    public function makeTHTML($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $code =  trim(html_entity_decode($node-> children (), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        
        if (!empty($this->renderObject))
        {
            //$code = $this->renderObject->render($code);
            $code = AdiantiTemplateHandler::replace($code, $this->renderObject);
        }
        $code = str_replace(';;pointer-events:none;;', '', $code);
        $html = new TElement( 'div' );
        $html->add( str_replace('\\n', "\n", $code ) );
        
        return $html;
    }
    
    /**
     * Add Entry
     */
    public function makeTEntry($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $entry = new TEntry((string) $attributes['name']);
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($entry, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($entry, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['maxlen'])) // added later (not in the first version)
        {
            $entry->setMaxLength((int) $attributes['maxlen']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $entry->setTip((string) $attributes['tooltip']);
        }
        
        if (!empty($attributes['placeholder']))
        {
            $entry->{'placeholder'} = $attributes['placeholder'];
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $entry->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['force_case']) AND $attributes['force_case'] == 'u')
        {
            $entry->forceUpperCase();
        }
        
        if (isset($attributes['exit_on_enter']) AND $attributes['exit_on_enter'] == 'Y')
        {
            $entry->exitOnEnter();
        }
        
        if (isset($attributes['force_case']) AND $attributes['force_case'] == 'l')
        {
            $entry->forceLowerCase();
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $entry->setEditable(false);
        }
        
        if (!empty($attributes['mask']))
        {
            $entry->setMask($attributes['mask'], true );
        }
        
        $entry->setSize('100%');
        
        if (!empty($attributes['style']))
        {
            $entry->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $entry->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['exit_action']))
        {
            $action = new TAction([$this->controller, $attributes['exit_action']]);
            $entry->setExitAction($action);
        }
        
        $this->form->addField($entry);
        
        return $entry;
    }
    
    /**
     * Add Numeric
     */
    public function makeTNumeric($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $entry = new TEntry((string) $attributes['name']);
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($entry, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($entry, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['maxlen'])) // added later (not in the first version)
        {
            $entry->setMaxLength((int) $attributes['maxlen']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $entry->setTip((string) $attributes['tooltip']);
        }
        
        if (!empty($attributes['placeholder']))
        {
            $entry->{'placeholder'} = $attributes['placeholder'];
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $entry->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['force_case']) AND $attributes['force_case'] == 'u')
        {
            $entry->forceUpperCase();
        }
        
        if (isset($attributes['force_case']) AND $attributes['force_case'] == 'l')
        {
            $entry->forceLowerCase();
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $entry->setEditable(false);
        }
        
        if (!empty($attributes['mask']))
        {
            $entry->setMask($attributes['mask'], true );
        }
        
        $entry->setSize('100%');

        if (!empty($attributes['precision']) && !empty($attributes['decimal_separator']) && !empty($attributes['thousand_separator']))
        {
            $entry->setNumericMask($attributes['precision'], $attributes['decimal_separator'], $attributes['thousand_separator'], true);
        }
        
        if (!empty($attributes['style']))
        {
            $entry->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $entry->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['exit_action']))
        {
            $action = new TAction([$this->controller, $attributes['exit_action']]);
            $entry->setExitAction($action);
        }
        
        $this->form->addField($entry);
        
        return $entry;
    }
    
    /**
     * Add MultiEntry
     */
    public function makeTMultiEntry($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $multientry = new TMultiEntry((string) $attributes['name']);
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($multientry, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($multientry, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $multientry->setTip((string) $attributes['tooltip']);
        }
        
        if (!empty($attributes['placeholder']))
        {
            $multientry->{'placeholder'} = $attributes['placeholder'];
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $multientry->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $multientry->setEditable(false);
        }
        
        if (!empty($attributes['mask']))
        {
            $multientry->setMask($attributes['mask'], true );
        }

        $multientry->setSize('100%', $attributes['height'] ?? null);

        $this->form->addField($multientry);
        
        return $multientry;
    }
    
    /**
     * Add Button
     */
    public function makeTButton($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        if (!$this->isForm)
        {
            $attributes['http_method'] = 'get';
        }
        
        if (!empty($attributes['http_method']) && $attributes['http_method'] == 'post')
        {
            $name = 'btn_' . AdiantiStringConversion::slug( (string) $attributes['label'] );
            $button = new TButton($name);
            
            if ($this->form->getField($name)) // a button with this name already exists in form
            {
                $button->setName( 'btn_'.uniqid() );
            }
        }
        else
        {
            $button = new TActionLink('');
        }
        
        if( !empty($attributes['icon']) )
        {
            $iconParts = explode(' ', $attributes['icon']);
            $prefix = $iconParts[0];
            $icon = str_replace('fa-', '', $iconParts[1]);
            $button->setImage($prefix . ':' . $icon);
            $button->setLabel(''); // workaround to update icon
        }
        
        $label = $attributes['label'] ?? '';
        $label = str_replace('[page_name]', (string) $this->pageName, $label);
        
        if( !empty($label) )
        {
            if (!empty($this->renderObject))
            {
                $label = $this->renderObject->render($label);
            }
            $button->setLabel($label);
        }
        
        if( !empty($attributes['button_class']) )
        {
            $button->addStyleClass('btn btn-sm btn-'.$attributes['button_class']);
        }

        if( !empty($attributes['action_class']) && !empty($attributes['action_method']))
        {
            $action = new TAction([$attributes['action_class'], $attributes['action_method']]);
            
            $this->configureAction($action, $attributes);
            
            if (!empty($attributes['use_popover']) && $attributes['use_popover'] == 'Y')
            {
                $action->usePopover();
            }
            
            if (!empty($attributes['validate_post']) && $attributes['validate_post'] == 'N')
            {
                $action->setParameter('novalidate', '1');
            }
            
            $button->setAction($action);
        }
        
        if (!empty($attributes['action_js']))
        {
            $button->addFunction(str_replace('#rn#', ';', $attributes['action_js']));
        }
        
        if (!empty($attributes['style']))
        {
            $button->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $button->title = $attributes['tooltip'];
        }
        
        if (!empty($attributes['css_class']))
        {
            $button->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['available_mobile']) && $attributes['available_mobile'] == 'N')
        {
            $button->class .= ' hide-mobile';
        }
        
        if ($button instanceof AdiantiWidgetInterface)
        {
            $this->form->addField($button);
        }
        
        return $button;
    }
    
    /**
     * Add Spinner
     */
    public function makeTSpinner($node, $stepper = false)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $spinner = new TSpinner((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $spinner->setTip((string) $attributes['tooltip']);
        }
      
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $spinner->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $spinner->setEditable(false);
        }
        
        if ($stepper)
        {
            $spinner->enableStepper();
        }
        
        $spinner->setSize('100%');
        
        $spinner->setRange( ($attributes['min']??0), ($attributes['max']??999999), ($attributes['step']??1) );
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($spinner, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($spinner, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['exit_action']))
        {
            $action = new TAction([$this->controller, $attributes['exit_action']]);
            $spinner->setExitAction($action);
        }
        
        $this->form->addField($spinner);
        
        return $spinner;
    }
    
    /**
     * Add Slider
     */
    public function makeTSlider($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $slider = new TSlider((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $slider->setTip((string) $attributes['tooltip']);
        }
      
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $slider->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $slider->setEditable(false);
        }
        
        $slider->setSize('100%');
        
        $slider->setRange( ($attributes['min'] ?? 0), ($attributes['max'] ?? 100), ($attributes['step'] ?? 1) );
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($slider, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($slider, $attributes['value_dynamic']);
        }
        
        $this->form->addField($slider);
        
        return $slider;
    }
    
    /**
     * Add Likert Scale
     */
    public function makeTLikertScale($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $likertscale = new TLikertScale((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $likertscale->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $likertscale->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $likertscale->setEditable(false);
        }
        
        $likertscale->setSize('100%');
        
        if (!empty($attributes['items']))
        {
            $this->fillStaticItems($likertscale, $attributes['items']);
        }
        
        if (!empty($attributes['items_dynamic']))
        {
            $this->fillDynamicItems($likertscale, $attributes['items_dynamic']);
        }
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($likertscale, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($likertscale, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $likertscale->setChangeAction($action);
        }
        
        $this->form->addField($likertscale);
        
        return $likertscale;
    }
    
    /**
     * Add Password
     */
    public function makeTPassword($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $password = new TPassword((string) $attributes['name']);
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($password, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($password, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['maxlen'])) // added later (not in the first version)
        {
            $password->setMaxLength((int) $attributes['maxlen']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $password->setTip((string) $attributes['tooltip']);
        }
        
        if (!empty($attributes['placeholder']))
        {
            $password->{'placeholder'} = $attributes['placeholder'];
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $password->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $password->setEditable(false);
        }
        
        $password->setSize('100%');
        
        if (!empty($attributes['style']))
        {
            $password->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $password->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['exit_action']))
        {
            $action = new TAction([$this->controller, $attributes['exit_action']]);
            $password->setExitAction($action);
        }
        
        $this->form->addField($password);
        
        return $password;
    }
    
    /**
     * Add Date
     */
    public function makeTDate($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $date = new TDate((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $date->setTip((string) $attributes['tooltip']);
        }
        
        if (!empty($attributes['placeholder']))
        {
            $date->{'placeholder'} = $attributes['placeholder'];
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $date->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $date->setEditable(false);
        }
        
        if (!empty($attributes['view_mask']))
        {
            $date->setMask($attributes['view_mask']);
        }

        if (!empty($attributes['database_mask']))
        {
            $date->setDatabaseMask($attributes['database_mask']);
        }
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($date, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($date, $attributes['value_dynamic']);
        }
        
        $date->setSize('100%');
        
        if (!empty($attributes['style']))
        {
            $date->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $date->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $date->setChangeAction($action);
        }
        
        $this->form->addField($date);
        
        return $date;
    }
    
    /**
     * Add Datetime
     */
    public function makeTDateTime($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $datetime = new TDateTime((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $datetime->setTip((string) $attributes['tooltip']);
        }
        
        if (!empty($attributes['placeholder']))
        {
            $datetime->{'placeholder'} = $attributes['placeholder'];
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $datetime->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $datetime->setEditable(false);
        }
        
        if (!empty($attributes['view_mask']))
        {
            $datetime->setMask($attributes['view_mask']);
        }

        if (!empty($attributes['database_mask']))
        {
            $datetime->setDatabaseMask($attributes['database_mask']);
        }
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($datetime, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($datetime, $attributes['value_dynamic']);
        }
        
        $datetime->setSize('100%');
        
        if (!empty($attributes['style']))
        {
            $datetime->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $datetime->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $datetime->setChangeAction($action);
        }
        
        $this->form->addField($datetime);
        
        return $datetime;
    }
    
    /**
     * Add Time
     */
    public function makeTTime($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $time = new TTime((string) $attributes['name']);
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($time, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($time, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $time->setTip((string) $attributes['tooltip']);
        }
        
        if (!empty($attributes['placeholder']))
        {
            $time->{'placeholder'} = $attributes['placeholder'];
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $time->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $time->setEditable(false);
        }
        
        if (!empty($attributes['view_mask']))
        {
            $time->setMask($attributes['view_mask']);
        }

        if (!empty($attributes['database_mask']))
        {
            $time->setDatabaseMask($attributes['database_mask']);
        }
        
        $time->setSize('100%');
        
        if (!empty($attributes['style']))
        {
            $time->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $time->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $time->setChangeAction($action);
        }
        
        $this->form->addField($time);
        
        return $time;
    }
    
    /**
     * Add Hidden
     */
    public function makeTHidden($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $hidden = new THidden((string) $attributes['name']);
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($hidden, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($hidden, $attributes['value_dynamic']);
        }
        
        $this->form->addField($hidden);
        
        return $hidden;
    }
    
    /**
     * Add Image Cropper
     */
    public function makeTImageCropper($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $cropper = new TImageCropper((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $cropper->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $cropper->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $cropper->setEditable(false);
        }
        
        if (!empty($attributes['extensions']))
        {
            $cropper->setAllowedExtensions(explode(',', $attributes['extensions']));
        }
        
        $cropper->setSize('100%', $attributes['height'] ?? 100);
        $cropper->setCropSize($attributes['crop_width'] ?? 100, $attributes['crop_height'] ?? 100);
        
        $this->form->addField($cropper);
        
        return $cropper;
    }
    
    /**
     * Add Image Capture
     */
    public function makeTImageCapture($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $capture = new TImageCapture((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $capture->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $capture->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $capture->setEditable(false);
        }
        
        $capture->setSize('100%', $attributes['height'] ?? 100);
        $capture->setCropSize($attributes['crop_width'] ?? 100, $attributes['crop_height'] ?? 100);
        
        $this->form->addField($capture);
        
        return $capture;
    }
    
    /**
     * Add QRCode
     */
    public function makeTQrcodeInputReader($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $qrcode = new TQRCodeInputReader((string) $attributes['name']);
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($qrcode, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($qrcode, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $qrcode->setTip((string) $attributes['tooltip']);
        }
        
        if (!empty($attributes['placeholder']))
        {
            $qrcode->{'placeholder'} = $attributes['placeholder'];
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $qrcode->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $qrcode->setEditable(false);
        }
        
        $qrcode->setSize('100%');
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $qrcode->setChangeAction($action);
        }
        
        $this->form->addField($qrcode);
        
        return $qrcode;
    }
    
    /**
     * Add Barcode
     */
    public function makeTBarcodeInputReader($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $barcode = new TBarCodeInputReader((string) $attributes['name']);
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($barcode, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($barcode, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $barcode->setTip((string) $attributes['tooltip']);
        }
        
        if (!empty($attributes['placeholder']))
        {
            $barcode->{'placeholder'} = $attributes['placeholder'];
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $barcode->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $barcode->setEditable(false);
        }
        
        $barcode->setSize('100%');
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $barcode->setChangeAction($action);
        }
        
        $this->form->addField($barcode);
        
        return $barcode;
    }
    
    /**
     * Add File
     */
    public function makeTFile($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $file = new TFile((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $file->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $file->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $file->setEditable(false);
        }
        
        $file->setSize('100%');
        
        if (!empty($attributes['image_gallery']) && $attributes['image_gallery'] == 'Y')
        {
            $file->enableImageGallery();
        }
        
        if (!empty($attributes['enable_popover']) && $attributes['enable_popover'] == 'Y')
        {
            $file->enablePopover();
        }
        
        if (!empty($attributes['extensions']))
        {
            $file->setAllowedExtensions(array_map('trim', explode(',', $attributes['extensions'])));
        }
        
        if (!empty($attributes['upload_limit']))
        {
            $file->setLimitUploadSize($attributes['upload_limit']);
        }
        
        if (!empty($attributes['complete_action']))
        {
            $action = new TAction([$this->controller, $attributes['complete_action']]);
            $file->setCompleteAction($action);
        }
        
        if (empty($attributes['file_handling']) || $attributes['file_handling'] == 'json')
        {
            $file->enableFileHandling();
        }
        
        $this->form->addField($file);
        
        return $file;
    }
    
    /**
     * Add Multifile
     */
    public function makeTMultiFile($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $file = new TMultiFile((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $file->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $file->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $file->setEditable(false);
        }
        
        $file->setSize('100%');
        
        if (!empty($attributes['image_gallery']) && $attributes['image_gallery'] == 'Y')
        {
            $file->enableImageGallery();
        }
        
        if (!empty($attributes['enable_popover']) && $attributes['enable_popover'] == 'Y')
        {
            $file->enablePopover();
        }
        
        if (!empty($attributes['extensions']))
        {
            $file->setAllowedExtensions(array_map('trim', explode(',', $attributes['extensions'])));
        }
        
        if (!empty($attributes['upload_limit']))
        {
            $file->setLimitUploadSize($attributes['upload_limit']);
        }
        
        if (!empty($attributes['complete_action']))
        {
            $action = new TAction([$this->controller, $attributes['complete_action']]);
            $file->setCompleteAction($action);
        }
        
        if (empty($attributes['file_handling']) || $attributes['file_handling'] == 'json')
        {
            $file->enableFileHandling();
        }
        
        $this->form->addField($file);
        
        return $file;
    }
    
    /**
     * Add Color
     */
    public function makeTColor($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $color = new TColor((string) $attributes['name']);
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($color, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($color, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $color->setTip((string) $attributes['tooltip']);
        }
        
        if (!empty($attributes['placeholder']))
        {
            $color->{'placeholder'} = $attributes['placeholder'];
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $color->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $color->setEditable(false);
        }
        
        $color->setSize('100%');
        
        if (!empty($attributes['style']))
        {
            $color->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $color->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $color->setChangeAction($action);
        }
        
        $this->form->addField($color);
        
        return $color;
    }
    
    /**
     * Add Icon
     */
    public function makeTIcon($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $icon = new TIcon((string) $attributes['name']);
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($icon, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($icon, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $icon->setTip((string) $attributes['tooltip']);
        }
        
        if (!empty($attributes['placeholder']))
        {
            $icon->{'placeholder'} = $attributes['placeholder'];
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $icon->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $icon->setEditable(false);
        }
        
        $icon->setSize('100%');
        
        if (!empty($attributes['style']))
        {
            $icon->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $icon->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $icon->setChangeAction($action);
        }
        
        $this->form->addField($icon);
        
        return $icon;
    }
    
    /**
     * Add Image
     */
    public function makeTImage($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $label = $attributes['label'];
        
        if (!empty($this->renderObject))
        {
            //$label = $this->renderObject->render($label);
            $label = AdiantiTemplateHandler::replace($label, $this->renderObject);
        }
        
        if (file_exists($label))
        {
            $image = new TElement('img');
            $image->src = 'download.php?file='.$label;
            $image->style = 'max-width:98%;';
            
            if (!empty($attributes['height']))
            {
                $image->style .= 'height:'.$attributes['height'] . 'px';
            }
            return $image;
        }
        else
        {
            return new TElement('div');
        }
    }
    
    /**
     * Add Text
     */
    public function makeTText($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $text = new TText((string) $attributes['name']);
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($text, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($text, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $text->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $text->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $text->setEditable(false);
        }
        
        if (isset($attributes['force_case']) AND $attributes['force_case'] == 'u')
        {
            $text->forceUpperCase();
        }
        
        if (isset($attributes['force_case']) AND $attributes['force_case'] == 'l')
        {
            $text->forceLowerCase();
        }
        
        $text->setSize('100%', $attributes['height'] ?? null);

        if (!empty($attributes['placeholder']))
        {
            $text->{'placeholder'} = $attributes['placeholder'];
        }
        
        if (!empty($attributes['style']))
        {
            $text->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $text->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['exit_action']))
        {
            $action = new TAction([$this->controller, $attributes['exit_action']]);
            $text->setExitAction($action);
        }
        
        $this->form->addField($text);
        
        return $text;
    }
    
    /**
     * Add Checkgroup
     */
    public function makeTCheckGroup($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $checkgroup = new TCheckGroup((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $checkgroup->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $checkgroup->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $checkgroup->setEditable(false);
        }
        
        $checkgroup->setSize('100%');
        
        if (!empty($attributes['use_button']) AND $attributes['use_button'] == 'Y')
        {
            $checkgroup->setUseButton();
            $checkgroup->setLayout('horizontal');
        }

        if (!empty($attributes['layout']))
        {
            $checkgroup->setLayout($attributes['layout']);
        }
        
        if (!empty($attributes['items']))
        {
            $this->fillStaticItems($checkgroup, $attributes['items']);
        }
        
        if (!empty($attributes['items_dynamic']))
        {
            $this->fillDynamicItems($checkgroup, $attributes['items_dynamic']);
        }
        
        if (!empty($attributes['value']))
        {
            $checkgroup->setValue( explode(',', (string) $attributes['value']) );
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($checkgroup, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $checkgroup->setChangeAction($action);
        }
        
        $this->form->addField($checkgroup);
        
        return $checkgroup;
    }
    
    /**
     * Add DBCheckgroup
     */
    public function makeTDBCheckGroup($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        if (!empty($attributes['db_connector']) && !empty($attributes['db_model']) && !empty($attributes['db_key_col'] && !empty($attributes['db_name_col'])))
        {
            $attributes = $this->fixDBAttributes($attributes);
            $criteria = !empty($attributes['custom_filters']) ? $this->parseFilter($attributes['custom_filters']) : null;
            try
            {
                $checkgroup = new TDBCheckGroup((string) $attributes['name'], $attributes['db_connector'], $attributes['db_model'], $attributes['db_key_col'], $attributes['db_name_col'], $attributes['db_order_col'] ?? null, $criteria);
                
                if (TSession::getValue('login') == 'admin' && $criteria instanceof TCriteria)
                {
                    $image = new TImage('fa:info-circle blue');
                    $image->title = '<b>' . _t('Filters') . ' (' . _t('shown just for administrator') . '): </b><br>'. $criteria->dump();
                    $checkgroup->after($image);
                }
            }
            catch (Exception $e)
            {
                $checkgroup = new TCheckGroup((string) $attributes['name']);
                $image = new TImage('fa:warning red');
                $image->title = $e->getMessage();
                $checkgroup->after($image);
            }
        }
        else
        {
            $checkgroup = new TCheckGroup((string) $attributes['name']);
            $this->validateDBAttributes($attributes, $checkgroup);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $checkgroup->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $checkgroup->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $checkgroup->setEditable(false);
        }
        
        $checkgroup->setSize('100%');
        
        if (!empty($attributes['use_button']) AND $attributes['use_button'] == 'Y')
        {
            $checkgroup->setUseButton();
            $checkgroup->setLayout('horizontal');
        }

        if (!empty($attributes['layout']))
        {
            $checkgroup->setLayout($attributes['layout']);
        }
        
        if (!empty($attributes['value']))
        {
            $checkgroup->setValue( explode(',', (string) $attributes['value']) );
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($checkgroup, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $checkgroup->setChangeAction($action);
        }
        
        $this->form->addField($checkgroup);
        
        return $checkgroup;
    }
    
    /**
     * Add Radio group
     */
    public function makeTRadioGroup($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $radiogroup = new TRadioGroup((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $radiogroup->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $radiogroup->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $radiogroup->setEditable(false);
        }
        
        $radiogroup->setSize('100%');
        
        if (!empty($attributes['use_button']) AND $attributes['use_button'] == 'Y')
        {
            $radiogroup->setUseButton();
            $radiogroup->setLayout('horizontal');
        }

        if (!empty($attributes['layout']))
        {
            $radiogroup->setLayout($attributes['layout']);
        }
        
        if (!empty($attributes['items']))
        {
            $this->fillStaticItems($radiogroup, $attributes['items']);
        }
        
        if (!empty($attributes['items_dynamic']))
        {
            $this->fillDynamicItems($radiogroup, $attributes['items_dynamic']);
        }
        
        if (!empty($attributes['boolean_mode']) && $attributes['boolean_mode'] == 'Y')
        {
            $radiogroup->setBooleanMode();
        }
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($radiogroup, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($radiogroup, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $radiogroup->setChangeAction($action);
        }
        
        $this->form->addField($radiogroup);
        
        return $radiogroup;
    }
    
    /**
     * Add DB Radio Group
     */
    public function makeTDBRadioGroup($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        if (!empty($attributes['db_connector']) && !empty($attributes['db_model']) && !empty($attributes['db_key_col'] && !empty($attributes['db_name_col'])))
        {
            $attributes = $this->fixDBAttributes($attributes);
            $criteria = !empty($attributes['custom_filters']) ? $this->parseFilter($attributes['custom_filters']) : null;
            
            try
            {
                $radiogroup = new TDBRadioGroup((string) $attributes['name'], $attributes['db_connector'], $attributes['db_model'], $attributes['db_key_col'], $attributes['db_name_col'], $attributes['db_order_col'] ?? null, $criteria);
                
                if (TSession::getValue('login') == 'admin' && $criteria instanceof TCriteria)
                {
                    $image = new TImage('fa:info-circle blue');
                    $image->title = '<b>' . _t('Filters') . ' (' . _t('shown just for administrator') . '): </b><br>'. $criteria->dump();
                    $radiogroup->after($image);
                }
            }
            catch (Exception $e)
            {
                $radiogroup = new TRadioGroup((string) $attributes['name']);
                $image = new TImage('fa:warning red');
                $image->title = $e->getMessage();
                $radiogroup->after($image);
            }
        }
        else
        {
            $radiogroup = new TRadioGroup((string) $attributes['name']);
            $this->validateDBAttributes($attributes, $radiogroup);
        }
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($radiogroup, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($radiogroup, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $radiogroup->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $radiogroup->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $radiogroup->setEditable(false);
        }
        
        $radiogroup->setSize('100%');
        
        if (!empty($attributes['use_button']) AND $attributes['use_button'] == 'Y')
        {
            $radiogroup->setUseButton();
            $radiogroup->setLayout('horizontal');
        }

        if (!empty($attributes['layout']))
        {
            $radiogroup->setLayout($attributes['layout']);
        }

        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $radiogroup->setChangeAction($action);
        }
        
        $this->form->addField($radiogroup);
        
        return $radiogroup;
    }
    
    /**
     * Add UniqueSearch
     */
    public function makeTUniqueSearch($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $uniquesearch = new TUniqueSearch((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $uniquesearch->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $uniquesearch->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $uniquesearch->setEditable(false);
        }
        
        $uniquesearch->setSize('100%');
        
        if (!empty($attributes['items']))
        {
            $this->fillStaticItems($uniquesearch, $attributes['items']);
        }
        
        if (!empty($attributes['items_dynamic']))
        {
            $this->fillDynamicItems($uniquesearch, $attributes['items_dynamic']);
        }
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($uniquesearch, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($uniquesearch, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['min_length']))
        {
            $uniquesearch->setMinLength($attributes['min_length']);
        }
        else
        {
            $uniquesearch->setMinLength(0);
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $uniquesearch->setChangeAction($action);
        }
        
        $this->form->addField($uniquesearch);
        
        return $uniquesearch;
    }
    
    /**
     * Add DB Combo
     */
    public function makeTDBUniqueSearch($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        if (!empty($attributes['db_connector']) && !empty($attributes['db_model']) && !empty($attributes['db_key_col'] && !empty($attributes['db_name_col'])))
        {
            $attributes = $this->fixDBAttributes($attributes);
            $criteria = !empty($attributes['custom_filters']) ? $this->parseFilter($attributes['custom_filters']) : null;
            
            try
            {
                $unique = new TDBUniqueSearch((string) $attributes['name'], $attributes['db_connector'], $attributes['db_model'], $attributes['db_key_col'], $attributes['db_name_col'], $attributes['db_order_col'] ?? null, $criteria);
                
                if (TSession::getValue('login') == 'admin' && $criteria instanceof TCriteria)
                {
                    $image = new TImage('fa:info-circle blue');
                    $image->title = '<b>' . _t('Filters') . ' (' . _t('shown just for administrator') . '): </b><br>'. $criteria->dump();
                    $unique->after($image);
                }
            }
            catch (Exception $e)
            {
                $unique = new TUniqueSearch((string) $attributes['name']);
                $image = new TImage('fa:warning red');
                $image->title = $e->getMessage();
                $unique->after($image);
            }
        }
        else
        {
            $unique = new TUniqueSearch((string) $attributes['name']);
            $this->validateDBAttributes($attributes, $unique);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $unique->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $unique->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $unique->setEditable(false);
        }
        
        $unique->setSize('100%');
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($unique, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($unique, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['mask']))
        {
            $unique->setMask($attributes['mask']);
        }
        
        if (!empty($attributes['style']))
        {
            $unique->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $unique->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['min_length']))
        {
            $unique->setMinLength($attributes['min_length']);
        }
        else
        {
            $unique->setMinLength(0);
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $unique->setChangeAction($action);
        }
        
        $this->form->addField($unique);
        return $unique;
    }
    
    /**
     * Add Combo
     */
    public function makeTCombo($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $combo = new TCombo((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $combo->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $combo->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $combo->setEditable(false);
        }
        
        $combo->setSize('100%');
        
        if (!empty($attributes['enable_search']) && $attributes['enable_search'] == 'Y')
        {
            $combo->enableSearch();
        }
        
        if (!empty($attributes['items']))
        {
            $this->fillStaticItems($combo, $attributes['items']);
        }
        
        if (!empty($attributes['items_dynamic']))
        {
            $this->fillDynamicItems($combo, $attributes['items_dynamic']);
        }

        if (empty($attributes['allow_null_option']) || $attributes['allow_null_option'] == 'Y')
        {
            $combo->setDefaultOption( true );
        }
        
        if (!empty($attributes['boolean_mode']) && $attributes['boolean_mode'] == 'Y')
        {
            $combo->setBooleanMode();
        }
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($combo, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($combo, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['style']))
        {
            $combo->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $combo->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $combo->setChangeAction($action);
        }
        
        $this->form->addField($combo);
        
        return $combo;
    }
    
    /**
     * Add DB Combo
     */
    public function makeTDBCombo($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        if (!empty($attributes['db_connector']) && !empty($attributes['db_model']) && !empty($attributes['db_key_col'] && !empty($attributes['db_name_col'])))
        {
            $attributes = $this->fixDBAttributes($attributes);
            $criteria = !empty($attributes['custom_filters']) ? $this->parseFilter($attributes['custom_filters']) : null;
            
            try
            {
                $combo = new TDBCombo((string) $attributes['name'], $attributes['db_connector'], $attributes['db_model'], $attributes['db_key_col'], $attributes['db_name_col'], $attributes['db_order_col'] ?? null, $criteria);
                
                if (TSession::getValue('login') == 'admin' && $criteria instanceof TCriteria)
                {
                    $image = new TImage('fa:info-circle blue');
                    $image->title = '<b>' . _t('Filters') . ' (' . _t('shown just for administrator') . '): </b><br>'. $criteria->dump();
                    $combo->after($image);
                }
            }
            catch (Exception $e)
            {
                $combo = new TCombo((string) $attributes['name']);
                $image = new TImage('fa:warning red');
                $image->title = $e->getMessage();
                $combo->after($image);
            }
            
            
        }
        else
        {
            $combo = new TCombo((string) $attributes['name']);
            $this->validateDBAttributes($attributes, $combo);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $combo->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $combo->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $combo->setEditable(false);
        }
        
        $combo->setSize('100%');
        
        if (!empty($attributes['enable_search']) && $attributes['enable_search'] == 'Y')
        {
            $combo->enableSearch();
        }
        
        if (!empty($attributes['boolean_mode']) && $attributes['boolean_mode'] == 'Y')
        {
            $combo->setBooleanMode();
        }

        if (empty($attributes['allow_null_option']) || $attributes['allow_null_option'] == 'Y')
        {
            $combo->setDefaultOption( true );
        }
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($combo, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($combo, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['style']))
        {
            $combo->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $combo->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $combo->setChangeAction($action);
        }
        
        $this->form->addField($combo);
        return $combo;
    }
    
    /**
     * Add Select
     */
    public function makeTSelect($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $select = new TSelect((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $select->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $select->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $select->setEditable(false);
        }
        
        $select->setSize('100%');
        
        if (empty($attributes['allow_null_option']) || $attributes['allow_null_option'] == 'Y')
        {
            $select->setDefaultOption( true );
        }
        
        if (!empty($attributes['items']))
        {
            $this->fillStaticItems($select, $attributes['items']);
        }
        
        if (!empty($attributes['items_dynamic']))
        {
            $this->fillDynamicItems($select, $attributes['items_dynamic']);
        }
        
        if (!empty($attributes['value']))
        {
            $select->setValue( explode(',', (string) $attributes['value']) );
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($select, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['style']))
        {
            $select->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $select->setChangeAction($action);
        }
        
        $this->form->addField($select);
        
        return $select;
    }
    
    /**
     * Add DB Select
     */
    public function makeTDBSelect($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        if (!empty($attributes['db_connector']) && !empty($attributes['db_model']) && !empty($attributes['db_key_col'] && !empty($attributes['db_name_col'])))
        {
            $attributes = $this->fixDBAttributes($attributes);
            $criteria = !empty($attributes['custom_filters']) ? $this->parseFilter($attributes['custom_filters']) : null;
            
            try
            {
                $dbselect = new TDBSelect((string) $attributes['name'], $attributes['db_connector'], $attributes['db_model'], $attributes['db_key_col'], $attributes['db_name_col'], $attributes['db_order_col'] ?? null, $criteria);
                
                if (TSession::getValue('login') == 'admin' && $criteria instanceof TCriteria)
                {
                    $image = new TImage('fa:info-circle blue');
                    $image->title = '<b>' . _t('Filters') . ' (' . _t('shown just for administrator') . '): </b><br>'. $criteria->dump();
                    $dbselect->after($image);
                }
            }
            catch (Exception $e)
            {
                $dbselect = new TSelect((string) $attributes['name']);
                $image = new TImage('fa:warning red');
                $image->title = $e->getMessage();
                $dbselect->after($image);
            }
        }
        else
        {
            $dbselect = new TSelect((string) $attributes['name']);
            $this->validateDBAttributes($attributes, $dbselect);
        }
        
        if (!empty($attributes['value']))
        {
            $dbselect->setValue( explode(',', (string) $attributes['value']) );
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($dbselect, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $dbselect->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $dbselect->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $dbselect->setEditable(false);
        }
        
        $dbselect->setSize('100%');
        
        if (!empty($attributes['style']))
        {
            $dbselect->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $dbselect->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $dbselect->setChangeAction($action);
        }
        
        $this->form->addField($dbselect);
        
        return $dbselect;

    }
    
    /**
     * Add Sort list
     */
    public function makeTSortList($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $sortlist = new TSortList((string) $attributes['name']);
        
        if (!empty($attributes['value']))
        {
            $sortlist->setValue((string) $attributes['value']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $sortlist->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $sortlist->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $sortlist->setEditable(false);
        }
        
        if (!empty($attributes['width']))
        {
            $sortlist->setSize($attributes['width']);
            
            if (!empty($attributes['height']))
            {
                $sortlist->setSize($attributes['width'], $attributes['width']);
            }
        }
        
        if (!empty($attributes['items']))
        {
            $this->fillStaticItems($sortlist, $attributes['items']);
        }
        
        if (!empty($attributes['items_dynamic']))
        {
            $this->fillDynamicItems($sortlist, $attributes['items_dynamic']);
        }
        
        $this->form->addField($sortlist);
        
        return $sortlist;
    }
    
    /**
     * Add DB Sort list
     */
    public function makeTDBSortList($node)
    {
        return new TLabel('TODO TDBSortList', 'grey');
    }
    
    /**
     * Add Multi Search
     */
    public function makeTMultiSearch($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $multisearch = new TMultiSearch((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $multisearch->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $multisearch->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $multisearch->setEditable(false);
        }
        
        $multisearch->setSize('100%', $attributes['height'] ?? '100');
        
        if (!empty($attributes['items']))
        {
            $this->fillStaticItems($multisearch, $attributes['items']);
        }
        
        if (!empty($attributes['items_dynamic']))
        {
            $this->fillDynamicItems($multisearch, $attributes['items_dynamic']);
        }
        
        if (!empty($attributes['value']))
        {
            $multisearch->setValue( explode(',', (string) $attributes['value']) );
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($multisearch, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['min_length']))
        {
            $multisearch->setMinLength($attributes['min_length']);
        }
        else
        {
            $multisearch->setMinLength(0);
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $multisearch->setChangeAction($action);
        }
        
        if (!empty($attributes['max_size']))
        {
            $multisearch->setMaxSize($attributes['max_size']);
        }

        $this->form->addField($multisearch);
        
        return $multisearch;
    }
    
    /**
     * Add DB MultiSearch
     */
    public function makeTDBMultiSearch($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        if (!empty($attributes['db_connector']) && !empty($attributes['db_model']) && !empty($attributes['db_key_col'] && !empty($attributes['db_name_col'])))
        {
            $attributes = $this->fixDBAttributes($attributes);
            $criteria = !empty($attributes['custom_filters']) ? $this->parseFilter($attributes['custom_filters']) : null;
            try
            {
                $multi = new TDBMultiSearch((string) $attributes['name'], $attributes['db_connector'], $attributes['db_model'], $attributes['db_key_col'], $attributes['db_name_col'], $attributes['db_order_col'] ?? null, $criteria);
                
                if (TSession::getValue('login') == 'admin' && $criteria instanceof TCriteria)
                {
                    $image = new TImage('fa:info-circle blue');
                    $image->title = '<b>' . _t('Filters') . ' (' . _t('shown just for administrator') . '): </b><br>'. $criteria->dump();
                    $multi->after($image);
                }
            }
            catch (Exception $e)
            {
                $multi = new TMultiSearch((string) $attributes['name']);
                $image = new TImage('fa:warning red');
                $image->title = $e->getMessage();
                $multi->after($image);
            }
        }
        else
        {
            $multi = new TMultiSearch((string) $attributes['name']);
            $this->validateDBAttributes($attributes, $multi);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $multi->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $multi->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $multi->setEditable(false);
        }
        
        $multi->setSize('100%', $attributes['height'] ?? '100');
        
        if (!empty($attributes['value']))
        {
            $multi->setValue( explode(',', (string) $attributes['value']) );
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($multi, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['mask']))
        {
            $multi->setMask($attributes['mask']);
        }
        
        if (!empty($attributes['style']))
        {
            $multi->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $multi->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['min_length']))
        {
            $multi->setMinLength($attributes['min_length']);
        }
        else
        {
            $multi->setMinLength(0);
        }
        
        if (!empty($attributes['max_size']))
        {
            $multi->setMaxSize($attributes['max_size']);
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $multi->setChangeAction($action);
        }
        
        $this->form->addField($multi);
        return $multi;

    }
    
    /**
     * Add MultiCombo
     */
    public function makeTMultiCombo($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $multicombo = new TMultiCombo((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $multicombo->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $multicombo->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $multicombo->setEditable(false);
        }
        
        $multicombo->setSize('100%');
        
        if (!empty($attributes['items']))
        {
            $this->fillStaticItems($multicombo, $attributes['items']);
        }
        
        if (!empty($attributes['items_dynamic']))
        {
            $this->fillDynamicItems($multicombo, $attributes['items_dynamic']);
        }
        
        if (!empty($attributes['value']))
        {
            $multicombo->setValue( explode(',', (string) $attributes['value']) );
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($multicombo, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['style']))
        {
            $multicombo->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $multicombo->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $multicombo->setChangeAction($action);
        }
        
        $this->form->addField($multicombo);
        
        return $multicombo;
    }
    
    /**
     * Add DB MultiCombo
     */
    public function makeTDBMultiCombo($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        if (!empty($attributes['db_connector']) && !empty($attributes['db_model']) && !empty($attributes['db_key_col'] && !empty($attributes['db_name_col'])))
        {
            $attributes = $this->fixDBAttributes($attributes);
            $criteria = !empty($attributes['custom_filters']) ? $this->parseFilter($attributes['custom_filters']) : null;
            
            try
            {
                $dbmulticombo = new TDBMultiCombo((string) $attributes['name'], $attributes['db_connector'], $attributes['db_model'], $attributes['db_key_col'], $attributes['db_name_col'], $attributes['db_order_col'] ?? null, $criteria);
                
                if (TSession::getValue('login') == 'admin' && $criteria instanceof TCriteria)
                {
                    $image = new TImage('fa:info-circle blue');
                    $image->title = '<b>' . _t('Filters') . ' (' . _t('shown just for administrator') . '): </b><br>'. $criteria->dump();
                    $dbmulticombo->after($image);
                }
            }
            catch (Exception $e)
            {
                $dbmulticombo = new TMultiCombo((string) $attributes['name']);
                $image = new TImage('fa:warning red');
                $image->title = $e->getMessage();
                $dbmulticombo->after($image);
            }
            
        }
        else
        {
            $dbmulticombo = new TMultiCombo((string) $attributes['name']);
            $this->validateDBAttributes($attributes, $dbmulticombo);
        }
        
        if (!empty($attributes['value']))
        {
            $dbmulticombo->setValue( explode(',', (string) $attributes['value']) );
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($dbmulticombo, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $dbmulticombo->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $dbmulticombo->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $dbmulticombo->setEditable(false);
        }
        
        $dbmulticombo->setSize('100%');
        
        if (!empty($attributes['style']))
        {
            $dbmulticombo->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $dbmulticombo->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $dbmulticombo->setChangeAction($action);
        }
        
        $this->form->addField($dbmulticombo);
        
        return $dbmulticombo;
    }
    
    /**
     * Add ArrowStep
     */
    public function makeTArrowStep($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $arrowstep = new TArrowStep((string) $attributes['name']);
        
        if (!empty($attributes['tooltip']))
        {
            $arrowstep->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $arrowstep->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $arrowstep->setEditable(false);
        }
        
        $arrowstep->setSize('100%');
        
        if (!empty($attributes['items']))
        {
            $items  = str_replace(['\n', '#rn#'], ["\n", "\n"], $attributes['items']);
            $pieces = explode("\n", $items);
            
            $items = [];
            if ($pieces)
            {
                foreach ($pieces as $line)
                {
                    $part = explode(':', $line);
                    if (count($part) == 3)
                    {
                        $arrowstep->addItem($part[1], $part[0], $part[2]);
                    }
                }
            }
        }
        
        if (!empty($attributes['items_dynamic']))
        {
            $this->fillDynamicItems($arrowstep, $attributes['items_dynamic']);
        }
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($arrowstep, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($arrowstep, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['height']))
        {
            $arrowstep->setHeight($attributes['height']);
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $arrowstep->setAction($action);
        }
        
        $this->form->addField($arrowstep);
        
        return $arrowstep;
    }
    
    /**
     * Add DB ArrowStep
     */
    public function makeTDBArrowStep($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        if (!empty($attributes['db_connector']) && !empty($attributes['db_model']) && !empty($attributes['db_key_col'] && !empty($attributes['db_name_col'])))
        {
            $attributes = $this->fixDBAttributes($attributes);
            $criteria = !empty($attributes['custom_filters']) ? $this->parseFilter($attributes['custom_filters']) : null;
            
            try
            {
                $arrowstep = new TDBArrowStep((string) $attributes['name'], $attributes['db_connector'], $attributes['db_model'], $attributes['db_key_col'], $attributes['db_name_col'], $attributes['db_order_col'] ?? null, $criteria);
                $arrowstep->setColorColumn($attributes['db_color_col']);
                
                if (TSession::getValue('login') == 'admin' && $criteria instanceof TCriteria)
                {
                    $image = new TImage('fa:info-circle blue');
                    $image->title = '<b>' . _t('Filters') . ' (' . _t('shown just for administrator') . '): </b><br>'. $criteria->dump();
                    $arrowstep->after($image);
                }
            }
            catch (Exception $e)
            {
                $arrowstep = new TArrowStep((string) $attributes['name']);
                $image = new TImage('fa:warning red');
                $image->title = $e->getMessage();
                $arrowstep->after($image);
            }
        }
        else
        {
            $arrowstep = new TArrowStep((string) $attributes['name']);
            $this->validateDBAttributes($attributes, $arrowstep);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $arrowstep->setTip((string) $attributes['tooltip']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $arrowstep->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $arrowstep->setEditable(false);
        }
        
        $arrowstep->setSize('100%');
        
        if (!empty($attributes['value']))
        {
            $this->setStaticValue($arrowstep, $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($arrowstep, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['height']))
        {
            $arrowstep->setHeight($attributes['height']);
        }
        
        if (!empty($attributes['style']))
        {
            $arrowstep->style = str_replace('#rn#', ';', $attributes['style']);
        }
        
        if (!empty($attributes['css_class']))
        {
            $arrowstep->class .= ' ' . $attributes['css_class'];
        }
        
        if (!empty($attributes['change_action']))
        {
            $action = new TAction([$this->controller, $attributes['change_action']]);
            $arrowstep->setAction($action);
        }
        
        $this->form->addField($arrowstep);
        return $arrowstep;
    }
    
    /**
     * Add HTML Editor
     */
    public function makeTHtmlEditor($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $thtmleditor = new THtmlEditor((string) $attributes['name']);
        
        if (!empty($attributes['value']))
        {
            $thtmleditor->setValue((string) $attributes['value']);
        }
        
        if (!empty($attributes['value_dynamic']))
        {
            $this->setDynamicValue($thtmleditor, $attributes['value_dynamic']);
        }
        
        if (!empty($attributes['tooltip']))
        {
            $thtmleditor->setTip((string) $attributes['tooltip']);
        }
        
        if (!empty($attributes['placeholder']))
        {
            $thtmleditor->setOption('placeholder', $attributes['placeholder']);
        }
        
        if (isset($attributes['required']) AND $attributes['required'] == 'Y') // added later (not in the first version)
        {
            $thtmleditor->addValidation((string) $attributes['label_required'], new TRequiredValidator);
        }
        
        if (isset($attributes['editable']) AND $attributes['editable'] == 'N')
        {
            $thtmleditor->setEditable(false);
        }
        
        $thtmleditor->setSize('100%', $attributes['height'] ?? '100');

        $this->form->addField($thtmleditor);
        
        return $thtmleditor;
    }
    
    /**
     * Add Plugin comment
     */
    public function makeTPluginComment($node)
    {
        $attributes = $this->getNodeAttributes($node);
        $attributes = $this->fixDBAttributes($attributes);
        
        if (empty($attributes['name']))
        {
            $plugin = new TAlert('danger', _t('Empty plugin name'));
            return $plugin;
        }
        
        if (!empty($this->plugins[$attributes['name']]))
        {
            $plugin = new TAlert('danger', _t('Plugin already registered with this name') . ': '. $attributes['name']);
            return $plugin;
        }
        
        if (!empty($attributes['admin_rule']) && ($attributes['admin_rule'] == 'A') && (TSession::getValue('login') == 'admin'))
        {
            $attributes['delete_rule']  = 'A';
            $attributes['display_rule'] = 'A';
            unset($attributes['delete_delay']);
        }
        
        $plugin = new AdiantiCreatorPluginComment;
        $plugin->setMetadata($attributes);
        $plugin->setController($this->controller);
        
        if (!empty($this->renderObject))
        {
            $plugin->setMasterObject($this->renderObject);
            
            if ($plugin->validate())
            {
                $plugin->render();
                $plugin->load();
            }
        }
        
        $this->plugins[$attributes['name']] = $plugin;
        return $plugin;
    }
    
    /**
     * Add Plugin Attach
     */
    public function makeTPluginAttach($node)
    {
        $attributes = $this->getNodeAttributes($node);
        $attributes = $this->fixDBAttributes($attributes);
        
        if (empty($attributes['name']))
        {
            $plugin = new TAlert('danger', _t('Empty plugin name'));
            return $plugin;
        }
        
        if (!empty($this->plugins[$attributes['name']]))
        {
            $plugin = new TAlert('danger', _t('Plugin already registered with this name') . ': '. $attributes['name']);
            return $plugin;
        }
        
        if (!empty($attributes['admin_rule']) && ($attributes['admin_rule'] == 'A') && (TSession::getValue('login') == 'admin'))
        {
            $attributes['delete_rule']  = 'A';
            $attributes['display_rule'] = 'A';
            unset($attributes['delete_delay']);
        }
        
        $plugin = new AdiantiCreatorPluginAttach;
        $plugin->setMetadata($attributes);
        $plugin->setController($this->controller);
        
        if (!empty($this->renderObject))
        {
            $plugin->setMasterObject($this->renderObject);
            
            if ($plugin->validate())
            {
                $plugin->render();
                $plugin->load();
            }
        }
        
        $this->plugins[$attributes['name']] = $plugin;
        return $plugin;
    }
    
    /**
     * Add Plugin TimeTrack
     */
    public function makeTPluginTimeTrack($node)
    {
        $attributes = $this->getNodeAttributes($node);
        $attributes = $this->fixDBAttributes($attributes);
        
        if (empty($attributes['name']))
        {
            $plugin = new TAlert('danger', _t('Empty plugin name'));
            return $plugin;
        }
        
        if (!empty($this->plugins[$attributes['name']]))
        {
            $plugin = new TAlert('danger', _t('Plugin already registered with this name') . ': '. $attributes['name']);
            return $plugin;
        }
        
        if (!empty($attributes['admin_rule']) && ($attributes['admin_rule'] == 'A') && (TSession::getValue('login') == 'admin'))
        {
            $attributes['delete_rule']  = 'A';
            $attributes['display_rule'] = 'A';
            unset($attributes['delete_delay']);
        }
        
        $plugin = new AdiantiCreatorPluginTimeTrack;
        $plugin->setMetadata($attributes);
        $plugin->setController($this->controller);
        
        if (!empty($this->renderObject))
        {
            $plugin->setMasterObject($this->renderObject);
            
            if ($plugin->validate())
            {
                $plugin->render();
                $plugin->load();
            }
        }
        
        $this->plugins[$attributes['name']] = $plugin;
        return $plugin;
    }
    
    /**
     * Add Plugin Checklist
     */
    public function makeTPluginChecklist($node)
    {
        $attributes = $this->getNodeAttributes($node);
        $attributes = $this->fixDBAttributes($attributes);
        
        if (empty($attributes['name']))
        {
            $plugin = new TAlert('danger', _t('Empty plugin name'));
            return $plugin;
        }
        
        if (!empty($this->plugins[$attributes['name']]))
        {
            $plugin = new TAlert('danger', _t('Plugin already registered with this name') . ': '. $attributes['name']);
            return $plugin;
        }
        
        if (!empty($attributes['admin_rule']) && ($attributes['admin_rule'] == 'A') && (TSession::getValue('login') == 'admin'))
        {
            $attributes['delete_rule']  = 'A';
            $attributes['display_rule'] = 'A';
            $attributes['update_rule']  = 'A';
            unset($attributes['delete_delay']);
        }
        
        $plugin = new AdiantiCreatorPluginChecklist;
        $plugin->setMetadata($attributes);
        $plugin->setController($this->controller);
        
        if (!empty($this->renderObject))
        {
            $plugin->setMasterObject($this->renderObject);
            
            if ($plugin->validate())
            {
                $plugin->render();
                $plugin->load();
            }
        }
        
        $this->plugins[$attributes['name']] = $plugin;
        return $plugin;
    }
    
    /**
     * Add PageStep
     */
    public function makeTPageStep($node)
    {
        $attributes = $this->getNodeAttributes($node);
        
        $pagestep = new TPageStep;
        
        if (!empty($attributes['items']))
        {
            $attributes['items'] = str_replace(['\n', '#rn#'], ["\n", "\n"], $attributes['items']);
            $pieces = explode("\n", $attributes['items']);
            
            $items = array();
            if ($pieces)
            {
                foreach ($pieces as $line)
                {
                    $pagestep->addItem($line);
                }
            }
            
        }
        
        if (!empty($attributes['value']))
        {
            $pagestep->select((string) $attributes['value']);
        }
        
        // $this->form->addField($pagestep);
        
        return $pagestep;
    }


    /**
     *
     */
    private function getItemFromModel($value, $model, $key, $name)
    {
        try
        {
            if (strpos($value, '{') === false)
            {
                $object = $model::where($key, '=', $value)->first();
                
                if ($object instanceof $model)
                {
                    $value = $object->$name;
                }
            }
            
            return $value;
        }
        catch (Exception $e)
        {
            return $value;
        }
    }
    
    /**
     *
     */
    private function getItemsFromModel($value, $model, $key, $name)
    {
        try
        {
            $items = [];
            if ($this->renderObject)
            {
                $objects = $this->renderObject->loadAggregatedClass($model);
                if ($objects)
                {
                    foreach ($objects as $object)
                    {
                        $items[] = $object->$name;
                    }
                }
            }
            
            sort($items, SORT_NATURAL | SORT_FLAG_CASE);
            return implode(', ', $items);
        }
        catch (Exception $e)
        {
            return $value;
        }
    }
    
    /**
     * Make search form from tform node
     */
    public function makeSearchForm($tforms, $iterate = true, $custom_filters = false)
    {
        $form   = new TForm('form_search_'.$this->controller);
        $panel  = new TElement('div');
        $body   = new TElement('div');
        $header = new TElement('div');
        $footer = new TElement('div');
        
        $panel->{'class'} = 'card search-form';
        $header->{'class'} = 'card-header';
        $body->{'class'} = 'card-body';
        $footer->{'class'} = 'card-footer';
        
        $header->add( new TLabel(_t('Find')) );
        
        $form->add($panel);
        $panel->add($header);
        $panel->add($body);
        $panel->add($footer);
        
        $main_form = $this->form;
        
        // Run $this->form->addField() over the inner form.
        $this->form = $form;
        
        if ($iterate)
        {
            foreach ($tforms as $tform)
            {
                $form_body = $this->parseFormBody($tform);
            }
        }
        else
        {
            $form_body = $this->parseFormBody($tforms);
        }
        
        $button = new TButton('search_button');
        $button_clear = new TButton('clear_button');
        $button_custom = new TButton('custom_button');
        
        $form->addField($button);
        $form->addField($button_clear);
        
        $button->setLabel(_t('Find'));
        $button->setImage('fa:search');
        $button->{'class'} = 'btn btn-primary btn-sm';
        
        $button_clear->setLabel(_t('Clear'));
        $button_clear->setImage('fa:eraser red');
        $button_clear->{'class'} = 'btn btn-outline-danger btn-sm';
        $button_clear->setAction(new TAction([$this->controller, 'onClearFilters'], ['static' => '1']));
        
        if (!empty($form_body))
        {
            $body->add($form_body);
        }
        
        $footer->add($button);
        
        if ($custom_filters)
        {
            $form->addField($button_custom);
            $button_custom->setLabel(_t('More filters'));
            $button_custom->setImage('fa:sliders');
            $button_custom->{'class'} = 'btn btn-outline-primary btn-sm hide-mobile';
            $button_custom->setAction(new TAction([$this->controller, 'onCreateCustomFiilters'], ['static' => '1']));
            $footer->add($button_custom);
        }
        
        $footer->add($button_clear);
        
        // restore main form.
        $this->form = $main_form;
        
        return $form;
    }
    
    /**
     * Make edit form from tform node
     */
    public function makeEditForm($tforms, $iterate = true, $field_prefix = '')
    {
        $form   = new TForm('form_edit_'.$this->controller);
        $panel  = new TElement('div');
        $body   = new TElement('div');
        $header = new TElement('div');
        $footer = new TElement('div');
        
        $panel->{'class'} = 'card edit-form';
        $header->{'class'} = 'card-header';
        $body->{'class'} = 'card-body';
        $footer->{'class'} = 'card-footer';
        
        $header->add( new TLabel(_t('Edit')) );
        
        $form->add($panel);
        $panel->add($header);
        $panel->add($body);
        $panel->add($footer);
        
        $form->setVirtualProperty('footer', $footer);
        
        $main_form = $this->form;
        
        // Run $this->form->addField() over the inner form.
        $this->form = $form;
        
        if ($iterate)
        {
            foreach ($tforms as $tform)
            {
                $form_body = $this->parseFormBody($tform);
            }
        }
        else
        {
            $form_body = $this->parseFormBody($tforms);
        }
        
        $button = new TButton($field_prefix . 'save_button');
        $form->addField($button);
        
        $button->setLabel(_t('Save'));
        $button->setImage('fa:check');
        $button->{'class'} = 'btn btn-primary btn-sm';
        
        if (!empty($form_body))
        {
            $body->add($form_body);
        }
        
        $footer->add($button);
        
        // restore main form.
        $this->form = $main_form;
        
        return $form;
    }
    
    /**
     *
     */
    private function configureAction($action, $action_atts)
    {
        if (!empty($action_atts['register_state']) && $action_atts['register_state'] == 'N')
        {
            $action->disableState();
        }
        
        if (!empty($action_atts['static_call']) && $action_atts['static_call'] == 'Y')
        {
            $action->setParameter('static', '1');
            $action->setParameter('static_call', '1'); // popovers remap static=0 for page to be shown, but some activities must be ignored (onReload must not be executed).
        }
        
        if (!empty($action_atts['preserve_params']))
        {
            $action->preserveRequestParameters( array_map('trim', explode(',', $action_atts['preserve_params'])) );
        }
        
        if (!empty($action_atts['custom_params']))
        {
            $custom_params = str_replace('#rn#', ';', $action_atts['custom_params']);
            $lines = explode(";", $custom_params);
            foreach ($lines as $line)
            {
                $parts = array_map('trim', explode(':', $line));
                
                if (!empty($parts[0]) && !empty($parts[1]))
                {
                    $action->setParameter( $parts[0], $parts[1] );
                }
            }
        }
    }
    
    /**
     * Create Criteria object from string
     */
    private function parseFilter($custom_filters)
    {
        if (!empty($custom_filters))
        {
            $criteria = new TCriteria;
            
            $custom_filters = str_replace(['\n', '#rn#'], ["\n", "\n"], $custom_filters);
            $pieces = explode("\n", $custom_filters);
            
            $items = array();
            if ($pieces)
            {
                foreach ($pieces as $line)
                {
                    $parts = explode(' ', $line, 3);
                    
                    if (count($parts) == 3)
                    {
                        $column   = $parts[0];
                        $operator = strtolower($parts[1]);
                        $operand  = $parts[2];
                        
                        // if constant
                        if (defined($operand))
                        {
                            $operand = constant($operand);
                        }
                        
                        if (is_string($operand) && strpos((string) $operand, '{session.') !== false)
                        {
                            $session_var = AdiantiStringConversion::getBetween($operand, '{session.', '}');
                            $operand = str_replace("{session.{$session_var}}", (string) ( TSession::getValue($session_var) ?? ''), $operand);
                        }
                        
                        // if method call
                        if (is_string($operand) && substr($operand,-2) == '()' && is_callable(substr($operand,0,-2)))
                        {
                            $operand = call_user_func(substr($operand,0,-2));
                        }
                        
                        if (is_string($operand) && substr($operand,0,6) == '$param')
                        {
                            $operand = $this->parseParam($operand);
                            if (empty($operand))
                            {
                                continue;
                            }
                        }
                        
                        if ( (count($parts) == 3) && (in_array(strtolower($operator), ['=', '>', '<', '>=', '<=', '<>', 'in', 'notin', 'like', 'notlike', 'is', 'isnot'])) )
                        {
                            $operator = str_replace('notin', 'not in', $operator);
                            $operator = str_replace('isnot', 'is not', $operator);
                            
                            if (in_array($operator, ['in', 'not in']) && (is_string($operand)) && (substr($operand,0,1) == '[') && (substr($operand,-1) == ']') )
                            {
                                $operand = str_replace("'", '', $operand);
                                $options = explode(',', substr($operand,1,-1));
                                $options = array_map('trim', $options);
                                $criteria->add( new TFilter($column, $operator, $options ));
                            }
                            else if (in_array($operator, ['is', 'is not']))
                            {
                                $operand = str_replace("'", '', $operand);
                                $operand = strtolower($operand) == 'null' ? null : $operand;
                                $criteria->add( new TFilter($column, $operator, $operand) );
                            }
                            else
                            {
                                
                                $criteria->add( new TFilter($column, $operator, $operand) );
                            }
                        }
                    }
                }
            }
            
            return $criteria;
        }
    }
    
    /**
     *
     */
    private function loadComposedObjects($datagrid, $criteria, $attributes)
    {
        $composed   = $attributes['db_model'];
        $order_col  = $attributes['db_order_col'] ?: $composed::PRIMARYKEY;
        $order_dir  = $attributes['db_order_dir'] ?: 'asc';
        $limit      = $attributes['page_records'];
        $id         = $this->renderObject->getPrimaryKeyValue();
        
        $composition = $this->renderObject->findCompositionFor($composed);
        $dependency  = $this->renderObject->findDependencyFor($composed);
        
        $relation = !empty($composition) ? $composition : $dependency;
        
        if ($relation)
        {
            $fkey  = $relation['fkey'];
            $criteria->add( new TFilter($fkey, '=', $id) );
            
            $repository = new TRepository($composed);
            $repository->setCriteria($criteria);
            $items = $repository->take($limit)->orderBy($order_col, $order_dir)->load();
            
            $datagrid->addItems($items);
            
            if (TSession::getValue('login') == 'admin')
            {
                $image = new TImage('fa:info-circle blue');
                $image->title = '<b>' . _t('Filters') . ' (' . _t('shown just for administrator') . '): </b><br>'. $criteria->dump();
                $datagrid->after($image);
            }
        }
        else
        {
            $datagrid->after(new TAlert('danger', _t('Relationship (composition, dependency) not found between ^1 and ^2', '<b>' . get_class($this->renderObject) . '</b>', '<b>' . $composed . '</b>') . '. ' . _t('Review the relationships between classes')));
        }

    }
    
    /**
     * Build criteria object from filters node
     */
    private function buildCriteriaFromFilters($nodes)
    {
        $criteria = new TCriteria;
        
        foreach ($nodes as $node_filter)
        {
            $filter_atts = $this->getNodeAttributes($node_filter);
            
            if (!empty($filter_atts['attribute']) && !empty($filter_atts['operator']))
            {
                $filter_atts = $this->fixDBAttributes($filter_atts);
                
                if ($filter_atts['filter_type'] == 'systemvar' && !empty($filter_atts['systemvar']))
                {
                    $criteria->add( new TFilter($filter_atts['attribute'], $filter_atts['operator'], call_user_func($filter_atts['systemvar'], $_REQUEST)));
                }
                else if ($filter_atts['filter_type'] == 'constant' && !empty($filter_atts['constant']))
                {
                    $criteria->add( new TFilter($filter_atts['attribute'], $filter_atts['operator'], constant($filter_atts['constant'])));
                }
                else if ($filter_atts['filter_type'] == 'fixedval' && ($filter_atts['fixedval'] !== ''))
                {
                    $criteria->add( new TFilter($filter_atts['attribute'], $filter_atts['operator'], $filter_atts['fixedval']));
                }
                else if ($filter_atts['filter_type'] == 'dynaval' && ($filter_atts['dynaval'] !== ''))
                {
                    $dynaval = $filter_atts['dynaval'];
                    
                    if (strpos((string) $dynaval, '{session.') !== false)
                    {
                        $session_var = AdiantiStringConversion::getBetween($dynaval, '{session.', '}');
                        $dynaval = str_replace("{session.{$session_var}}", (string) ( TSession::getValue($session_var) ?? ''), $dynaval);
                        $criteria->add( new TFilter($filter_atts['attribute'], $filter_atts['operator'], substr($dynaval,1)));
                    }
                    else if (substr($dynaval,0,7) == '=$param')
                    {
                        $dynaval = $this->parseParam($dynaval);
                        
                        if (!empty($dynaval))
                        {
                            $criteria->add( new TFilter($filter_atts['attribute'], $filter_atts['operator'], $dynaval));
                        }
                    }
                    // if constant
                    else if (defined(substr($dynaval,1)))
                    {
                        $dynaval = constant(substr($dynaval,1));
                        $criteria->add( new TFilter($filter_atts['attribute'], $filter_atts['operator'], $dynaval));
                    }
                    else if (substr($dynaval,-2) == '()' && is_callable(substr($dynaval,1,-2)))
                    {
                        $dynaval = call_user_func(substr($dynaval,1,-2));
                        $criteria->add( new TFilter($filter_atts['attribute'], $filter_atts['operator'], $dynaval));
                    }
                    else if (substr($dynaval,0,1) == '=')
                    {
                        try
                        {
                            @eval('$dynaval' . ' = ' . substr($dynaval,1) . ';');
                            $criteria->add( new TFilter($filter_atts['attribute'], $filter_atts['operator'], $dynaval));
                        }
                        catch (Exception $e)
                        {
                            new TMessage('error', $e->getMessage() . '<br><b>' . $dynaval . '</b>');
                        }
                        catch (Error $e)
                        {
                            new TMessage('error', $e->getMessage() . '<br><b>' . $dynaval . '</b>');
                        }
                    }
                }
            }
        }
        
        return $criteria;
    }
    
    /**
     * Build criteria object from filters node
     */
    private function getFormFilters($nodes)
    {
        $criteria = new TCriteria;
        
        $form_filters = [];
        
        foreach ($nodes as $node_filter)
        {
            $filter_atts = $this->getNodeAttributes($node_filter);
            
            if (!empty($filter_atts['attribute']) && !empty($filter_atts['operator']))
            {
                $filter_atts = $this->fixDBAttributes($filter_atts);
                
                if ($filter_atts['filter_type'] == 'formfield' && !empty($filter_atts['formfield']))
                {
                    $form_filters[] = $filter_atts;
                }
            }
        }
        
        return $form_filters;
    }
    
    /**
     * Convert generic aggregation filter into specific subquery
     */
    private function replaceAggregatedFilters($widget, $formfilters, $attributes, $widget_type)
    {
        if ($formfilters)
        {
            foreach ($formfilters as $key => $formfilter)
            {
                if (substr($formfilter['attribute'],0,1) == '@')
                {
                    $aggregated_class = substr($formfilter['attribute'],1);
                    
                    if ($widget_type == 'tkanban')
                    {
                        $main_model = $attributes['db_model2'];
                    }
                    else
                    {
                        $main_model = $attributes['db_model'];
                    }
                    
                    $record = new $main_model;
                    $aggregation = $record->findAggregationFor($aggregated_class);
                    
                    if (!empty($aggregation))
                    {
                        $intermediate_class = $aggregation['model']; // Ex. PessoaGrupo
                        $aggregated_fkey    = $aggregation['fkey'];
                        $aggregated_fkey2   = $aggregation['fkey2'];
                        $intermediate_table = $intermediate_class::TABLENAME;
                        $main_table         = $main_model::TABLENAME;
                        $main_key           = $main_model::PRIMARYKEY;
                        
                        $formfilters[$key]['attribute'] = "{$main_table}.$main_key";
                        $formfilters[$key]['operator'] = 'IN';
                        $formfilters[$key]['transformer'] = function($values) use ($aggregated_fkey, $aggregated_fkey2, $intermediate_table) {
                            $prepared_values = [];
                            if ($values)
                            {
                                foreach ($values as $key => $value)
                                {
                                    $prepared_values[] = ":[{$value}]:";
                                }
                            }
                            $prepared_values_string = implode(',', $prepared_values);
                            return "(SELECT {$aggregated_fkey} from {$intermediate_table} WHERE {$intermediate_table}.{$aggregated_fkey2} IN ({$prepared_values_string}) )";
                        };
                    }
                    else
                    {
                        unset($formfilters[$key]);
                        $widget->after(new TAlert('danger', _t('Aggregation not found between ^1 and ^2', '<b>' . $main_model . '</b>', '<b>' . $aggregated_class . '</b>') . '. ' . _t('Review the relationships between classes')));
                    }
                }
            }
        }
        
        return $formfilters;
    }
    
    /**
     * Set value
     */
    private function setStaticValue($object, $value)
    {
        $value = str_replace('{session_filter->', '{session.[__CLASS__]_filter_data->', $value);
        $value = str_replace('{session_limit}', '{session.[__CLASS__]_limit}', $value);
        $value = str_replace('[__CLASS__]', $this->controller, $value);
        $value = str_replace('={session', '{session', $value);
        $default = '';
        
        if (strpos($value, '??') !== false)
        {
            $parts = explode('??', $value);
            $value = trim($parts[0]);
            $default = trim($parts[1]);
        }
        
        if (substr($value,0,7) == '=$param')
        {
            preg_match("/\\['(.*?)'\\]/", $value, $matches);
            
            if ( (count($matches) == 2) && !empty($matches[1]) && !empty($_REQUEST[$matches[1]]))
            {
                $object->setValue((string) $_REQUEST[$matches[1]]);
            }
        }
        else if (defined(substr($value,1)))
        {
            $object->setValue(constant(substr($value,1)));
        }
        else if (substr($value,0,1) == '=')
        {
            try
            {
                @eval('$result_var' . ' = ' . substr($value,1) . ';');
                $object->setValue((string) $result_var);
            }
            catch (Error $e)
            {
                $image = new TImage('fa:warning red');
                $image->title = $e->getMessage();
                $object->after($image);
            }
        }
        else if (strpos((string) $value, '{session.') !== false)
        {
            $session_var = AdiantiStringConversion::getBetween($value, '{session.', '}');
            if (strpos($session_var, '->') !== false)
            {
                $parts = explode('->', $session_var);
                $session_obj = TSession::getValue($parts[0]);
                $session_var = $parts[1];
                if (is_object($session_obj) && !empty($session_var))
                {
                    $result = $session_obj->$session_var ?? $default;
                    $object->setValue( $result );
                }
                else
                {
                    $object->setValue( $default );
                }
            }
            else
            {
                $result = str_replace("{session.{$session_var}}", (string) ( TSession::getValue($session_var) ?? $default), $value);
                $object->setValue( $result );
            }
        }
        else
        {
            $object->setValue((string) $value);
        }
    }
    
    /**
     * Set value from callback
     */
    private function setDynamicValue($object, $callback)
    {
        try
        {
            $object->setValue( call_user_func($callback) );
        }
        catch (Exception $e)
        {
            $image = new TImage('fa:warning red');
            $image->title = $e->getMessage();
            $object->after($image);
        }
        catch (Error $e)
        {
            $image = new TImage('fa:warning red');
            $image->title = $e->getMessage();
            $object->after($image);
        }
    }
    
    /**
     * Fill widget from callback
     */
    private function fillDynamicItems($object, $callback)
    {
        try
        {
            $object->addItems( call_user_func($callback) );
        }
        catch (Exception $e)
        {
            $image = new TImage('fa:warning red');
            $image->title = $e->getMessage();
            $object->after($image);
        }
        catch (Error $e)
        {
            $image = new TImage('fa:warning red');
            $image->title = $e->getMessage();
            $object->after($image);
        }
    }
    
    /**
     * Fill widget with static items
     */
    private function fillStaticItems($object, $items)
    {
        $items  = str_replace(['\n', '#rn#'], ["\n", "\n"], $items);
        $pieces = explode("\n", $items);
        
        $items = [];
        if ($pieces)
        {
            foreach ($pieces as $line)
            {
                $part = explode(':', $line);
                if (count($part) == 2)
                {
                    $items[$part[0]] = $part[1];
                }
            }
        }
        $object->addItems($items);
    }
    
    /**
     * Get order command
     */
    private function getOrderCommand($db_model, $attribute)
    {
        $order_parts = explode('->', $attribute); // cidade->nome
        
        $associated_variable  = $order_parts[0];
        $associated_attribute = $order_parts[1];
        
        $object = new $db_model;
        if ($object instanceof TRecord)
        {
            $association = $object->findAssociationFor($associated_variable, 'var');
            
            if (!empty($association))
            {
                $associated_class = $association['model'];
                
                if (defined($associated_class.'::TABLENAME') && defined($associated_class.'::PRIMARYKEY'))
                {
                    $associated_table = $associated_class::TABLENAME;
                    $associated_pkey  = $associated_class::PRIMARYKEY;
                    $associated_fkey  = $association['fkey'];
                    $main_table       = $db_model::TABLENAME;
                    $associated_obj   = new $associated_class;
                    
                    // avoid SQL injection in order attribute (by URL)
                    if (in_array($associated_attribute, $associated_obj->getAttributes()))
                    {
                        return "(SELECT {$associated_attribute} from {$associated_table} where {$associated_pkey} = {$main_table}.{$associated_fkey})";
                    }
                }
            }
        }
    }
    
    /**
     * Get content from $param['something'] expression.
     */
    private function parseParam($expression)
    {
        preg_match("/\\['(.*?)'\\]/", $expression, $matches);
        
        if ( (count($matches) == 2) && !empty($matches[1]))
        {
            if (!empty($_REQUEST[$matches[1]]))
            {
                return (string) $_REQUEST[$matches[1]];
            }
        }
        
        return null;
    }
}
