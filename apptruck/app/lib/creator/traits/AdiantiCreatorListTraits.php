<?php
/**
 * Creator List Traits
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorListTraits
{
    use AdiantiCreatorSearchLoadTrait;
    use AdiantiCreatorDeleteTrait;
    use AdiantiCreatorExportTrait;
    use AdiantiCreatorPresenterTrait;
    
    /**
     * Pack Datagrid with different pack styles
     */
    private function packUI($with_breadcrumb)
    {
        if (!empty($this->datagrid))
        {
            $attributes = $this->datagrid->getMetadata('attributes');
            $search_pack = $attributes['search_pack'];
        }
        else
        {
            $search_pack = 'curtain';
        }
        
        $vbox = new TVBox;
        
        if ($search_pack == 'curtain')
        {
            $vbox->{'style'} = 'display:block;width:100%';
            
            if ($with_breadcrumb)
            {
                $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
            }
            
            $vbox->add($this->ui);
        }
        else if ($search_pack == 'top')
        {
            $vbox->{'style'} = 'width: calc(100% - 10px)';
            
            if ($with_breadcrumb)
            {
                $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
            }
            
            $vbox->add($this->search)->style='margin-bottom:10px';
            $vbox->add($this->ui);
        }
        else if ($search_pack == 'left')
        {
            $width = ($this instanceof TWindow) ? '99' : '100';
            $vbox->{'style'} = "display:block;width:{$width}%";
            
            if ($with_breadcrumb)
            {
                $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
            }
            
            $hbox = new TElement('div');
            $hbox->{'class'} = 'row';
            $hbox->add(TElement::tag('div', $this->search, ['class' => 'col-sm-3 mb-1']));
            $hbox->add(TElement::tag('div', $this->ui, ['class' => 'col-sm-9']));
            $vbox->add($hbox);
        }
        
        return $vbox;
    }
    
    /**
     * Popover to select visible columns
     */
    public function selectColumns($param)
    {
        parent::clearChildren();
        
        $columns = $this->datagrid->getColumns();
        $options = [];
        $previsibles  = [];
        
        $configuration = TSession::getValue($this->controller.'_datagrid_columns');
        
        $all_column_keys = [];
        if ($columns)
        {
            foreach ($columns as $column)
            {
                $column_key = base64_encode($column->getName().$column->getProperty('data-original-title'));
                $options[$column_key] = $column->getProperty('data-original-title');
                $all_column_keys[] = $column_key;
                
                if (!empty($configuration[$column_key]['visible']))
                {
                    $previsibles[] = $column_key;
                }
                else if ($column->getProperty('visible') !== 'false')
                {
                    $previsibles[] = $column_key;
                }
            }
        }
        
        $all_keys = new THidden('all_column_keys');
        $all_keys->setValue(base64_encode(json_encode($all_column_keys)));
        
        $checkgroup = new TCheckGroup('columns');
        $checkgroup->addItems($options);
        $checkgroup->setValue($previsibles);
        
        $apply = TButton::create('btn_apply_columns', [$this, 'onApplyVisibleColumns'], _t('Apply'), 'fa:check');
        $apply->class = 'btn btn-primary btn-sm';
        $apply->style = 'margin-top: 10px';
        
        $configure = TButton::create('btn_configure_columns', [$this, 'onConfigureColumns'], _t('More options'), 'fa:sliders');
        $configure->class = 'btn btn-outline-primary btn-sm';
        $configure->style = 'margin-top: 10px';
        $configure->getAction()->setParameter('static','1');
        
        $hbox = THBox::pack($apply, $configure);
        
        $form = new TForm('form_datagrid_columns');
        $form->setProperty('style', 'min-width: 300px');
        $form->add($all_keys, true);
        $form->add($checkgroup, true);
        $form->add($hbox);
        
        $form->addField($apply);
        $form->addField($configure);
        
        parent::add(new TLabel(_t('Columns'), null, null, 'b'));
        parent::add(TElement::tag('hr', null, ['style' => 'margin:5px 0px 10px 0px']));
        parent::add($form);
    }
    
    /**
     * Apply selected columns
     */
    public static function onApplyVisibleColumns($param)
    {
        $configuration = TSession::getValue(__CLASS__.'_datagrid_columns') ?? [];
        
        $all_column_keys = json_decode(base64_decode($param['all_column_keys']), true);
        
        if (empty($param['columns']))
        {
            return new TMessage('error', _t('The field ^1 is required', '<b>' . _t('Columns') . '</b>'));
        }
        
        if ($all_column_keys)
        {
            foreach ($all_column_keys as $key)
            {
                $configuration[$key]['visible'] = 'N';
            }
        }
        
        // refill column visibles
        if (!empty($param['columns']))
        {
            foreach ($param['columns'] as $key)
            {
                $configuration[$key]['visible'] = 'Y';
            }
        }
        
        TSession::setValue(__CLASS__.'_datagrid_columns', $configuration);
        
        TScript::create('__adianti_clear_click_popovers();');
        AdiantiCoreApplication::loadPage(__CLASS__, 'onLoad');
    }
    
    /**
     * Configure columns
     */
    public function onConfigureColumns($param)
    {
        TScript::create('__adianti_clear_click_popovers();');
        
        $window = TWindow::create(_t('Configure columns'), 1000, null);
        
        $form = new BootstrapFormBuilder('my_form');
        $form->setProperty('class', 'card noborder');
        
        $key = new THidden('key[]');
        
        $label = new TEntry('label[]');
        $label->setSize('100%');
        $label->setEditable(false);
        $label->style = 'background:inherit !important;font-weight:bold;';
        
        $visible = new TCombo('visible[]');
        $visible->setSize('100%');
        $visible->addItems(['Y' => '<i class="fa-regular fa-eye fa-fw green"></i> ' . _t('Yes'),
                            'N' => '<i class="fa-regular fa-eye-slash fa-fw red"></i> ' . _t('No')]);
        $visible->enableSearch();
        
        $align = new TCombo('align[]');
        $align->setSize('100%');
        $align->addItems(['left'   => '<i class="fa-solid fa-align-left fa-fw blue"></i> ' . _t('Left'),
                          'center' => '<i class="fa-solid fa-align-center fa-fw blue"></i> ' . _t('Center'),
                          'right'  => '<i class="fa-solid fa-align-right fa-fw blue"></i> ' . _t('Right')]);
        $align->enableSearch();
        
        $width = new TCombo('width[]');
        $width->setSize('100%');
        $width->addItems( ['very_small' => _t('Very small'),
                           'small'      => _t('Small'),
                           'medium'     => _t('Medium'),
                           'large'      => _t('Large'),
                           'very_large' => _t('Very large') ] );
        $width->enableSearch();
        
        $fieldlist = new TFieldList;
        $fieldlist->generateAria();
        $fieldlist->disableRemoveButton();
        $fieldlist->width = '100%';
        $fieldlist->name  = 'my_field_list';
        $fieldlist->addField( '',  $key, ['width' => '1%'] );
        $fieldlist->addField( '<b>'._t('Column').'</b>',  $label,   ['width' => '25%'] );
        $fieldlist->addField( '<b>'._t('Visible').'</b>', $visible, ['width' => '25%'] );
        $fieldlist->addField( '<b>'._t('Align').'</b>',   $align,   ['width' => '25%'] );
        $fieldlist->addField( '<b>'._t('Width').'</b>',   $width,   ['width' => '25%'] );
        
        $fieldlist->enableSorting();
        
        $form->addField($key);
        $form->addField($label);
        $form->addField($align);
        $form->addField($width);
        
        
        $columns = $this->datagrid->getColumns();
        $options = [];
        $previsibles  = [];
        
        $fieldlist->addHeader();
        $configuration = TSession::getValue(__CLASS__.'_datagrid_columns');
        $ordered_columns = [];
        
        if ($columns)
        {
            foreach ($columns as $column)
            {
                $key = base64_encode($column->getName().$column->getProperty('data-original-title'));
                
                $obj = new stdClass;
                $obj->key     = $key;
                $obj->label   = $column->getProperty('data-original-title');
                $obj->align   = $column->getAlign();
                $obj->width   = $column->getProperty('data-real-width');
                $obj->visible = ($column->getProperty('visible') !== 'false') ? 'Y' : 'N';
                
                if (!empty($configuration[$key]['visible']))
                {
                    $obj->visible = $configuration[$key]['visible'];
                }
                if (!empty($configuration[$key]['align']))
                {
                    $obj->align = $configuration[$key]['align'];
                }
                if (!empty($configuration[$key]['width']))
                {
                    $obj->width = $configuration[$key]['width'];
                }
                $ordered_columns[] = $obj;
            }
        }
        
        $configuration = TSession::getValue(__CLASS__.'_datagrid_columns');
        
        // sort columns according to the user previous selection (not predefined in XML)
        if (!empty($configuration))
        {
            $order = array_keys($configuration);
            usort($ordered_columns, function($a, $b) use ($order) {
                return strcmp(array_search($a->key, $order), array_search($b->key, $order));
            });
        }
        
        foreach ($ordered_columns as $obj)
        {
            $fieldlist->addDetail( $obj );
        }
        
        // add field list to the form
        $form->addContent( [$fieldlist] );
        
        $btn = $form->addAction( _t('Apply'), new TAction([$this, 'onApplyConfiguredColumns']), 'fa:check');
        $btn->class = 'btn btn-primary btn-sm';
        
        $btn2 = $form->addAction( _t('Apply and close'), new TAction([$this, 'onApplyConfiguredColumns'], ['close_window' => '1']), '');
        $btn2->class = 'btn btn-outline-primary btn-sm';
        
        $window->add($form);
        $window->show();
    }
    
    /**
     * Apply configured columns
     */
    public static function onApplyConfiguredColumns($param)
    {
        $configuration = [];
        
        $visibles = 0;
        if (!empty($param['key']))
        {
            foreach ($param['key'] as $row => $key)
            {
                $configuration[$key] = [];
                
                $configuration[$key]['label']   = $param['label'][$row] ?? null;
                $configuration[$key]['visible'] = $param['visible'][$row] ?? null;
                $configuration[$key]['align']   = $param['align'][$row] ?? null;
                $configuration[$key]['width']   = $param['width'][$row] ?? null;
                
                if ($param['visible'][$row] == 'Y')
                {
                    $visibles ++;
                }
            }
        }
        
        if ($visibles == 0)
        {
            return new TMessage('error', _t('There must be at least one visible column'));
        }
        
        if (!empty($param['close_window']))
        {
            TWindow::closeAll();
            TScript::create( "$('[role=\"window-wrapper\"]').remove();" );
        }
        
        TSession::setValue(__CLASS__.'_datagrid_columns', $configuration);
        AdiantiCoreApplication::loadPage(__CLASS__, 'onLoad');
    }
    
    /**
     * Open Column filter popover
     */
    public function onDisplayColumnFilter($param)
    {
        parent::clearChildren();
        
        if (!empty($this->search))
        {
            $field = $this->search->getField($param['filter_field']);
            
            if (!empty($field))
            {
                $field_name = $field->getName();
                
                if (!empty(TSession::getValue(get_class($this).'_filter_data')))
                {
                    $field->setValue(TSession::getValue(get_class($this).'_filter_data')->$field_name);
                }
                
                $action = new TAction([$this, 'applyColumnFilter'], ['_filter_field' => $param['filter_field'], 'static' => '1']);
                
                $apply = new TButton('btn_apply_columns');
                $apply->setLabel(_t('Apply'));
                $apply->setImage('fa:check');
                $apply->setAction($action);
                $apply->class = 'btn btn-primary btn-sm';
                $apply->style = 'margin-top: 15px';
                
                $clear = TButton::create('btn_configure_columns', [$this, 'onClearColumnFilter'], _t('Clear'), 'fa:eraser');
                $clear->class = 'btn btn-outline-danger btn-sm';
                $clear->style = 'margin-top: 15px';
                $clear->getAction()->setParameter('static','1');
                $clear->getAction()->setParameter('_filter_field', $param['filter_field']);
                
                $hbox = THBox::pack($apply, $clear);
                $hbox->style = 'clear:both';
                
                $form = new TForm('form_datagrid_autofilter');
                $form->setProperty('style', 'width: 300px');
                $form->add($field, true);
                $form->add($hbox);
                $form->addField($apply);
                $form->addField($clear);
                
                $this->style = 'padding: 10px';
                parent::add(new TLabel($param['column_label'], null, null, 'b'));
                parent::add($form);
            }
            else
            {
                parent::add(_t('Field not found') . ': ' . $param['autofilter']);
            }
        }
    }
    
    /**
     * Apply column filter search
     */
    public function applyColumnFilter($param)
    {
        $this->buildSessionFilters($param);
        //$this->onReload( ['offset'=>0, 'first_page'=>1] );
        AdiantiCoreApplication::loadPage(__CLASS__, 'onLoad');
    }
    
    /**
     * Clear column filter
     */
    public function onClearColumnFilter($param)
    {
        unset($_POST[$param['_filter_field']]);
        $this->buildSessionFilters($param);
        //$this->onReload( ['offset'=>0, 'first_page'=>1] );
        AdiantiCoreApplication::loadPage(__CLASS__, 'onLoad');
    }
    
    /**
     * Select row by id
     */
    private static function selectRow($param)
    {
        $key = $param['key'];
        $selected_rows = TSession::getValue(__CLASS__.'_selected_rows') ?? [];
        
        if (!empty($selected_rows[$key]))
        {
            TScript::create("table_unselected_row_by_key('{$key}')");
            unset($selected_rows[$key]);
        }
        else
        {
            TScript::create("table_selected_row_by_key('{$key}')");
            $selected_rows[$key] = $key;
        }
        ksort($selected_rows);
        TSession::setValue(__CLASS__.'_selected_rows', $selected_rows);
    }
    
    /**
     * Select all rows
     */
    private function selectAllRows($param)
    {
        try
        {
            TTransaction::open($this->database);
            $class = $this->activeRecord;
            $pkey = $class::PRIMARYKEY;
            
            $repos = new TRepository($class);
            $repos->setCriteria($this->buildSearchCriteria([], false));
            
            $ids = $repos->select([$pkey])->getIndexedArray($pkey);
            TSession::setValue(__CLASS__.'_selected_rows', $ids);
            
            TTransaction::close();
        }
        catch (Exception $e) // in case of exception
        {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
        
        $this->onReload($param);
    }
    
    /**
     * Select no rows
     */
    private function selectNoRows($param)
    {
        TSession::setValue(__CLASS__.'_selected_rows', []);
        $this->onReload($param);
    }
    
    /**
     * Highlight selected rows
     */
    private static function formatSelectedRow($value, $object, $row, $cell)
    {
        $selected_rows = TSession::getValue(__CLASS__.'_selected_rows') ?? [];
        
        if (!empty($selected_rows[$object->getPrimaryKeyValue()]))
        {
            $row->class = 'selected';
            
            $buttons = $row->find('i', ['class'=>'far fa-square']);
            
            if (!empty($buttons) && is_array($buttons))
            {
                $buttons[0]->class = 'far fa-square-check';
            }
        }
        return $value;
    }
}
