<?php
/**
 * Creator Search and Load Trait
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorSearchLoadTrait #depends:AdiantiCreatorControlTrait
{
    protected $filters;
    protected $limit;
    protected $order;
    protected $direction;
    protected $orderCommands;
    protected $criteria;
    protected $loaded;
    protected $loaderObject;
    protected $dump;
    
    use AdiantiCreatorControlTrait;
    
    /**
     * Set the object to be loaded
     */
    protected function setLoaderObject($widget)
    {
        $this->loaderObject = $widget;
        
        if (!empty($this->loaderObject->getMetadata('form_filters')))
        {
            foreach ($this->loaderObject->getMetadata('form_filters') as $filter_atts)
            {
                $this->addFilterField($filter_atts['attribute'], $filter_atts['operator'], $filter_atts['formfield'], $filter_atts['transformer'] ?? null);
            }
        }
    }
    
    /**
     * method setLimit()
     * Define the record limit
     */
    protected function setLimit($limit)
    {
        $this->limit = $limit;
    }
    
    /**
     * Returns the used limit
     */
    protected function getUsedLimit()
    {
        $limit = isset($this->limit) ? ( $this->limit > 0 ? $this->limit : NULL) : 10;
        
        if (!empty(TSession::getValue(__CLASS__.'_limit')))
        {
            $limit = TSession::getValue(__CLASS__.'_limit');
        }
        
        return $limit;
    }
    
    
    /**
     * Set order command
     */
    protected function setOrderCommand($order_column, $order_command)
    {
        if (empty($this->orderCommands))
        {
            $this->orderCommands = [];
        }
        
        $this->orderCommands[$order_column] = $order_command;
    }
    
    /**
     * Define the default order
     * @param $order The order field
     * @param $directiont the order direction (asc, desc)
     */
    protected function setDefaultOrder($order, $direction = 'asc')
    {
        $this->order = $order;
        $this->direction = $direction;
    }
    
    /**
     * method addFilterField()
     * Add a field that will be used for filtering
     * @param $filterField Field name
     * @param $operator Comparison operator
     */
    protected function addFilterField($attribute, $operator = 'like', $form_field = NULL, $transformer = NULL, $logic_operator = TExpression::AND_OPERATOR)
    {
        if (empty($this->filters))
        {
            $this->filters = [];
        }
        
        $this->filters[] = [
            'attribute'      => $attribute,
            'operator'       => $operator,
            'form_field'     => $form_field,
            'transformer'    => $transformer,
            'logic_operator' => $logic_operator
        ];
    }
    
    /**
     * method setCriteria()
     * Define the criteria
     */
    protected function setCriteria($criteria)
    {
        $this->criteria = $criteria;
    }
    
    /**
     * Register the filter in the session
     */
    private function buildSessionFilters( $param = null )
    {
        // get the search form data
        $data = $this->search->getData();
        
        $count_filters = 0;
        
        if ($this->filters)
        {
            foreach ($this->filters as $filter_key => $filter_info)
            {
                $attribute   = $filter_info['attribute'];
                $operator    = $filter_info['operator'];
                $form_field  = $filter_info['form_field'];
                $transformer = $filter_info['transformer'];
                
                // check if the user has filled the form
                if (!empty($data->{$form_field}) || (isset($data->{$form_field}) && $data->{$form_field} == '0'))
                {
                    if ($transformer)
                    {
                        $value = $transformer($data->{$form_field});
                    }
                    else
                    {
                        $value = $data->{$form_field};
                    }
                    
                    // creates a filter using what the user has typed
                    if (stristr($operator, 'like'))
                    {
                        $value = str_replace(' ', '%', $value);
                        $filter = new TFilter($attribute, $operator, "%{$value}%");
                    }
                    else
                    {
                        $filter = new TFilter($attribute, $operator, $value);
                    }
                    
                    // stores the filter in the session
                    TSession::setValue(get_class($this).'_filter_'.$filter_key, $filter);
                    
                    $count_filters ++;
                }
                else
                {
                    if (empty($param['_filter_field']) || $form_field == $param['_filter_field'])
                    {
                        TSession::setValue(get_class($this).'_filter_'.$filter_key, NULL);
                    }
                }
            }
        }
        
        if (!empty($param['_filter_field']))
        {
            $filter_field = $param['_filter_field'];
            $session_data = TSession::getValue(get_class($this).'_filter_data');
            
            if (!empty($session_data) && is_object($session_data))
            {
                $session_data->$filter_field = $data->$filter_field ?? '';
                $data = $session_data;
            }
            
            TScript::create('__adianti_clear_click_popovers();');
        }
        
        TSession::setValue(get_class($this).'_filter_data', $data);
        TSession::setValue(get_class($this).'_filter_counter', $count_filters);
        
        // fill the form with data again
        $this->search->setData($data);
        
        /*
        if (isset($param['static']) && ($param['static'] == '1') )
        {
            $class = get_class($this);
            $new_params = ['offset'=>0, 'first_page'=>1];
            
            if (!empty($param['page_fragment']))
            {
                $new_params['page_fragment'] = $param['page_fragment'];
                $new_params['target_container'] = $param['page_fragment'];
            }
            
            AdiantiCoreApplication::loadPage($class, 'onReload', $new_params);
        }
        */
    }
    
    /**
     * Clear filters
     */
    public function onClearFilters($param)
    {
        $this->clearFilters();
        AdiantiCoreApplication::loadPage(__CLASS__, 'onLoad');
    }
    
    /**
     * clear Filters
     */
    protected function clearFilters()
    {
        TSession::setValue(get_class($this).'_filter_data', null);
        TSession::setValue(get_class($this).'_datagrid_custom_filters', null);
        
        $this->search->clear();
        
        if ($this->filters)
        {
            foreach ($this->filters as $filter_key => $filter_info)
            {
                TSession::setValue(get_class($this).'_filter_'.$filter_key, NULL);
            }
        }
    }
    
    /**
     * Build search criteria
     */
    private function buildSearchCriteria($param, $paged = true)
    {
        $param_criteria = $param;
        
        $limit = $this->getUsedLimit();
        
        // creates a criteria
        $criteria = isset($this->criteria) ? clone $this->criteria : new TCriteria;
        
        if (!empty($this->loaderObject))
        {
            // merge predefined filters at widget level
            if (!empty($this->loaderObject->getMetadata('criteria')))
            {
                $criteria->mergeCriteria($this->loaderObject->getMetadata('criteria'));
            }
        }
        
        if ($this->order)
        {
            $criteria->setProperty('order',     $this->order);
            $criteria->setProperty('direction', $this->direction);
        }
        
        // Order definitions by setOrderCommand()
        if (is_array($this->orderCommands) && !empty($param['order']) && !empty($this->orderCommands[$param['order']]))
        {
            $param_criteria['order'] = $this->orderCommands[$param['order']];
        }
        
        // Order definitions from Associations (*Cidade->descricao)
        if (!empty($this->loaderObject))
        {
            if (!empty($this->loaderObject->getMetadata('order_commands')))
            {
                $order_commands = $this->loaderObject->getMetadata('order_commands');
                if (is_array($order_commands) && !empty($param['order']) && !empty($order_commands[$param['order']]))
                {
                    $param_criteria['order'] = $order_commands[$param['order']];
                }
            }
        }
        
        if ($paged)
        {
            $criteria->setProperties($param_criteria); // order, offset
            $criteria->setProperty('limit', $limit);
        }
        
        $subcriteria = new TCriteria;
        if ($this->filters)
        {
            foreach ($this->filters as $filter_key => $filter_info)
            {
                $logic_operator = $filter_info['logic_operator'] ?? TExpression::AND_OPERATOR;
                
                if (TSession::getValue(get_class($this).'_filter_'.$filter_key))
                {
                    // add the filter stored in the session to the criteria
                    $subcriteria->add(TSession::getValue(get_class($this).'_filter_'.$filter_key), $logic_operator);
                }
            }
            
            if (!$subcriteria->isEmpty())
            {
                $criteria->add($subcriteria);
            }
        }
        
        $subcriteria2 = new TCriteria;
        
        $order_commands = $this->loaderObject->getMetadata('order_commands');
        
        $custom_filters = TSession::getValue(get_class($this).'_datagrid_custom_filters');
        if (!empty($custom_filters))
        {
            foreach ($custom_filters as $filter)
            {
                if (!empty($filter['column']))
                {
                    $metadata = json_decode(base64_decode($filter['column']), true);
                    $column = $metadata['order'];
                    $operator = $filter['operator'];
                    $value = $filter['value'];
                    
                    // Ex. [cidade->nome]
                    if (!empty($order_commands[$column]))
                    {
                        $column = $order_commands[$column];
                    }
                    
                    if (in_array($operator, ['in', 'not in'] ) )
                    {
                        $value = explode(',', $value);
                        $value = array_map('trim', $value);
                    }
                    
                    if (in_array($operator, ['like', 'not like']) && (strpos($value, '%') === FALSE) )
                    {
                        $value = '%' . str_replace(' ', '%', $value) . '%';
                        $column = "UPPER($column)";
                        $value = "NOESC:UPPER('$value')";
                    }
                    
                    $subcriteria2->add(new TFilter($column, $operator, $value));
                }
            }
            
            if (!$subcriteria2->isEmpty())
            {
                $criteria->add($subcriteria2);
            }
        }
        
        if (TSession::getValue('login') == 'admin' && $criteria instanceof TCriteria && !($criteria->isEmpty()))
        {
            $alert = new TAlert('warning', '<b>' . _t('Filters') . ' (' . _t('shown just for administrator') . '): </b><br>'. $criteria->dump());
            $alert->style = 'zoom:0.80';
            $this->loaderObject->after($alert);
        }
        return $criteria;
    }
    
    /**
     * Load the datagrid with the database objects
     */
    private function loadObjectsFromFilters($param = NULL)
    {
        if (empty($this->database))
        {
            throw new Exception(AdiantiCoreTranslator::translate('^1 was not defined. You must call ^2 in ^3', AdiantiCoreTranslator::translate('Database'), 'setDatabase()', AdiantiCoreTranslator::translate('Constructor')));
        }
        
        if (empty($this->activeRecord))
        {
            throw new Exception(AdiantiCoreTranslator::translate('^1 was not defined. You must call ^2 in ^3', 'Active Record', 'setActiveRecord()', AdiantiCoreTranslator::translate('Constructor')));
        }
        
        $criteria = $this->buildSearchCriteria($param);
        
        $this->loaded = true;
        
        if (isset($this->dump) && $this->dump)
        {
            TTransaction::dump();
        }
        
        $repository = new TRepository($this->activeRecord);
        return $repository->load($criteria, FALSE);
    }
    
    /**
     * Configure page navigation
     */
    private function configurePageNavigation($param)
    {
        $limit = $this->getUsedLimit();
        
        $criteria = $this->buildSearchCriteria($param);
        $criteria->resetProperties();
        
        $repository = new TRepository($this->activeRecord);
        $count = $repository->count($criteria);
        
        if (isset($this->pageNavigation))
        {
            $this->pageNavigation->setCount($count); // count of records
            $this->pageNavigation->setProperties($param); // order, page
            $this->pageNavigation->setLimit($limit); // limit
        }
    }
    
    /**
     * Create custom filters
     */
    public function onCreateCustomFiilters($param)
    {
        TScript::create('__adianti_clear_click_popovers();');
        
        $window = TWindow::create(_t('Custom filters'), 1000, null);
        
        $form = new BootstrapFormBuilder('my_form');
        $form->setProperty('class', 'card noborder');
        
        $key = new THidden('key[]');
        
        $column = new TCombo('column[]');
        $column->setSize('100%');
        $column->enableSearch();
        
        $operator = new TCombo('operator[]');
        $operator->setSize('340');
        $operator->addItems(['='       => '=',
                            '>'        => '>',
                            '<'        => '<',
                            '<>'       => '<>',
                            '>='       => '>=',
                            '<='       => '<=',
                            'in'       => _t('Is contained by') . ' (in) ',
                            'not in'   => _t('Is not contained by') . ' (not in) ',
                            'like'     => _t('Contains the expression') . ' (like) ',
                            'not like' => _t('Does not contains the expression') . ' (not like) ']);
        
        $operator->enableSearch();
        
        $value = new TEntry('value[]');
        $value->setSize('100%');
        $value->{'placeholder'} = 'Int, String, VOID, RAW:expression';
        
        $fieldlist = new TFieldList;
        $fieldlist->generateAria();
        $fieldlist->width = '100%';
        $fieldlist->name  = 'my_field_list';
        $fieldlist->addField( '',  $key, ['width' => '1%'] );
        $fieldlist->addField( '<b>'._t('Column').'</b>',   $column,   ['width' => '33%'] );
        $fieldlist->addField( '<b>'._t('Operator').'</b>', $operator, ['width' => '33%'] );
        $fieldlist->addField( '<b>'._t('Value').'</b>',    $value,    ['width' => '33%'] );
        
        $fieldlist->enableSorting();
        
        $form->addField($key);
        $form->addField($column);
        $form->addField($operator);
        $form->addField($value);
        
        
        $columns = $this->datagrid->getColumns();
        $options = [];
        $previsibles  = [];
        
        $fieldlist->addHeader();
        
        $ordered_columns = [];
        $configuration = TSession::getValue(__CLASS__.'_datagrid_custom_filters');
        
        $items = [];
        
        if ($columns)
        {
            foreach ($columns as $datagrid_column)
            {
                $key = base64_encode(json_encode( ['name' => $datagrid_column->getName(), 'order' => $datagrid_column->getProperty('data-order-col') ] ));
                $items[$key] = '<b>' . $datagrid_column->getProperty('data-original-title') . '</b>' . ' [' . $datagrid_column->getProperty('data-order-col') . ']';
            }
        }
        
        $column->addItems($items);
        
        $configuration = TSession::getValue(__CLASS__.'_datagrid_custom_filters');
        
        if (!empty($configuration))
        {
            foreach ($configuration as $row)
            {
                $fieldlist->addDetail( (object) $row );
            }
        }
        else
        {
            $fieldlist->addDetail( new StdCLass );
        }
        
        $fieldlist->addCloneAction();
        
        // add field list to the form
        $form->addContent( [$fieldlist] );
        
        $btn = $form->addAction( _t('Apply'), new TAction([$this, 'onApplyCustomFilters']), 'fa:check');
        $btn->class = 'btn btn-primary btn-sm';
        
        $window->add($form);
        $window->show();
    }
    
    /**
     * Apply custom filters
     */
    public static function onApplyCustomFilters($param)
    {
        $configuration = [];
        
        $visibles = 0;
        if (!empty($param['column']))
        {
            foreach ($param['column'] as $row => $column)
            {
                $filter = [];
                $filter['column']   = $column;
                $filter['operator'] = $param['operator'][$row] ?? null;
                $filter['value']    = $param['value'][$row] ?? null;
                
                $configuration[] = $filter;
            }
        }
        
        TScript::create( "$('[role=\"window-wrapper\"]').remove();" );
        
        TSession::setValue(__CLASS__.'_datagrid_custom_filters', $configuration);
        AdiantiCoreApplication::loadPage(__CLASS__, 'onLoad');
    }
    
    /**
     * Change datagrid limit
     */
    public static function changeLimit($param)
    {
        $limit = $param['_field_value'] ?? 10;
        TSession::setValue(__CLASS__.'_limit', $limit);
        AdiantiCoreApplication::loadPage(__CLASS__, 'onLoad');
    }
    
    /**
     * Apply quick search
     */
    private static function quickSearch($param)
    {
        $field_name  = $param['_field_name'];
        $field_value = $param['_field_value'] ?? '';
        
        if (!empty($field_name))
        {
            $_POST[$field_name] = $field_value;
            
            $self = new self([]);
            $self->buildSessionFilters(['_autofilter' => $field_name]);
            
            AdiantiCoreApplication::loadPage(__CLASS__, 'onLoad');
        }
    }
    
    /**
     * method show()
     * Shows the page
     */
    public function show()
    {
        // check if the datagrid is already loaded
        
        if (!$this->loaded && (!isset($_GET['method']) || !(in_array($_GET['method'],  ['onReload', 'onSearch']))) && empty($_GET['static_call']))
        {
            if (func_num_args() > 0)
            {
                $this->onReload( func_get_arg(0) );
            }
            else
            {
                $this->onReload();
            }
        }
        parent::show();
    }
}
