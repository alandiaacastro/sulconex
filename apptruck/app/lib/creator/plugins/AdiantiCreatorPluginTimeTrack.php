<?php
/**
 * Creator TimeTrack Plugin
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
class AdiantiCreatorPluginTimeTrack extends TElement
{
    private $metadata;
    private $controller;
    private $masterObject;
    private $datagrid;
    
    /**
     * Constructor method
     */
    public function __construct()
    {
        parent::__construct('div');
    }
    
    /**
     * Set the page controller for the plugin
     */
    public function setController($controller)
    {
        $this->controller = $controller;
    }
    
    /**
     * Set the plugin metadata
     */
    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }
    
    /**
     * Returns plugin metadata by property
     */
    public function getPropertyMetadata($property)
    {
        return !empty($this->metadata[$property]) ? $this->metadata[$property] : null;
    }
    
    /**
     * Set the master object
     */
    public function setMasterObject($object)
    {
        $this->masterObject = $object;
    }
    
    /**
     *
     */
    public function validate()
    {
        if (empty($this->getPropertyMetadata('db_model2')))
        {
            parent::add(new TAlert('danger', _t('^1 not defined', '<b>'._t('Recording model').'</b>')));
            return false;
        }
        
        if (empty($this->getPropertyMetadata('db_content_col')))
        {
            parent::add(new TAlert('danger', _t('^1 not defined', '<b>'._t('Description field').'</b>')));
            return false;
        }
        
        if (empty($this->getPropertyMetadata('db_minutes_col')))
        {
            parent::add(new TAlert('danger', _t('^1 not defined', '<b>'._t('Minutes field').'</b>')));
            return false;
        }
        
        if (empty($this->getPropertyMetadata('db_createdat_col')))
        {
            parent::add(new TAlert('danger', _t('^1 not defined', '<b>'._t('Created at field').'</b>')));
            return false;
        }
        
        if (empty($this->getPropertyMetadata('db_createdby_col')))
        {
            parent::add(new TAlert('danger', _t('^1 not defined', '<b>'._t('Created by field').'</b>')));
            return false;
        }
        
        if (empty($this->getPropertyMetadata('save_method')))
        {
            parent::add(new TAlert('danger', _t('^1 not defined', '<b>'._t('Save method').'</b>')));
            return false;
        }
        
        if (empty($this->getPropertyMetadata('delete_method')))
        {
            parent::add(new TAlert('danger', _t('^1 not defined', '<b>'._t('Delete method').'</b>')));
            return false;
        }
        
        return true;
    }
    
    /**
     * Render page contents
     */
    public function render()
    {
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->disableDefaultClick();
        $this->datagrid->setActionSide('right');
        $this->datagrid->class = 'table table-hover vertical-middle plugin_timetrack';
        
        $id_col       = new TDataGridColumn('id',         'id',              'center', '1%');
        $descr_col    = new TDataGridColumn('description',_t('Description'), 'left',   '40%');
        $time_col     = new TDataGridColumn('minutes',    _t('Time'),        'center', '20%');
        $user_col     = new TDataGridColumn('created_by', _t('User'),        'center', '20%');
        $date_col     = new TDataGridColumn('created_at', _t('Date'),        'center', '19%');
        
        $date_col->setTransformer( function($value) {
           $time = AdiantiCreatorTransformers::datetimeToBr($value);
           $parts = explode(' ', $time);
           return '<i class="fa-regular fa-calendar"></i> ' .  $parts[0] . '<br><span class="gray">'.$parts[1]??''.'</span>';
        });
        
        $user_col->setTransformer( function($value) {
            return '<i class="fa-regular fa-circle-user"></i> ' . $value;
        });
        
        $time_col->setTransformer( function($value) {
            $hours = floor($value / 60);
            $minutes = $value % 60;
            return sprintf("%02d:%02d", $hours, $minutes);
        });
        
        $time_col->setTotalFunction( function($values) {
           return array_sum($values); 
        });
        $this->datagrid->addColumn($id_col);
        $this->datagrid->addColumn($descr_col);
        $this->datagrid->addColumn($time_col);
        $this->datagrid->addColumn($user_col);
        $this->datagrid->addColumn($date_col);
        
        $id_col->setVisibility(false);
        
        $form_time = new TForm('form_'. $this->getPropertyMetadata('name'));
        
        $time = new TEntry('time');
        $time->setMask('99:99');
        $time->placeholder = '99:99';
        
        $button = TButton::create('save', [$this->controller, $this->getPropertyMetadata('save_method')], _t('Save'), 'fa:check');
        $button->getAction()->setParameter('plugin_name', $this->getPropertyMetadata('name'));
        $button->getAction()->setParameter('master_object_id', $this->masterObject->getPrimaryKeyValue());
        $button->getAction()->setParameter('static', '1');
        $button->class='btn btn-primary';
        
        $description = new TEntry('description');
        $description->placeholder = _t('Description');
        
        $hbox = new THBox;
        $hbox->style = 'width: 100%';
        $hbox->add($description)->style .= ' width:50%';
        $hbox->add($time)->style .= ' width:30%';
        $hbox->add($button);
        
        $form_time->addField($description, true);
        $form_time->addField($time);
        $form_time->addField($button);
        
        $form_time->add($hbox);
        
        $wrapper_form = new TElement('div');
        $wrapper_form->add($form_time);
        $wrapper_form->id = 'wrapper_form_'. $this->getPropertyMetadata('name');
        $wrapper_form->style = 'padding:20px;display:none';
        
        $class_name = $this->getPropertyMetadata('db_model2');
        
        // works with '{id}' (fixed) because it's filled in addItem() at this file's end
        $action_del = new TDataGridAction([$this->controller, $this->getPropertyMetadata('delete_method')], ['static' => '1', 'key' => '{id}']);
        $action_del->setParameter('plugin_name', $this->getPropertyMetadata('name'));
        $action_del->setParameter('master_object_id', $this->masterObject->getPrimaryKeyValue());
        $action_del->setProperty('btn-class', 'btn');
        $action_del->setDisplayCondition( [$this, 'displayDeleteCondition'] );
        $action_del->setTitle(_t('Delete'));
        $this->datagrid->addAction($action_del, '', 'far:trash-alt red' );
        
        // creates the datagrid model
        $this->datagrid->createModel();
        
        $action_new = new TButton('new_time');
        $action_new->setLabel(_t('Register time'));
        $action_new->addStyleClass('btn-outline-primary');
        $action_new->style = 'float:right;margin-bottom:4px';
        $action_new->setImage('fa:plus');
        $action_new->addFunction("$('#{$wrapper_form->id}').toggle();$(this).toggleClass('btn-secondary').toggleClass('btn-outline-primary');");
        
        // wrap the content using vertical box
        $vbox_time = new TVBox;
        $vbox_time->id = 'container_' . $this->getPropertyMetadata('name');
        $vbox_time->style = 'width: 100%; padding: 10px';
        $vbox_time->add($action_new);
        $vbox_time->add($wrapper_form);
        $vbox_time->add($this->datagrid);
        
        parent::add($vbox_time);
    }
    
    /**
     * Display condition for delete button
     */
    public function displayDeleteCondition($object)
    {
        $delete_rule  = $this->getPropertyMetadata('delete_rule');
        $delete_delay = $this->getPropertyMetadata('delete_delay');
        $createdat_col = $this->getPropertyMetadata('db_createdat_col');
        
        if ($delete_rule == 'X')
        {
            return false;
        }
        
        if (!empty($delete_delay))
        {
            $now  = new DateTime;
            $time = new DateTime($object->record->$createdat_col);
            $time->add(new DateInterval('PT' . $delete_delay . 'M'));
            
            if ($now > $time)
            {
                return false;
            }
        }
        
        if ($delete_rule == 'U')
        {
            return $object->record->isCreatedBySessionUser();
        }
        
        return true;
    }
    
    /**
     * Load objects into the datagrid
     */
    public function load()
    {
        $class_name    = $this->getPropertyMetadata('db_model2');
        $content_col   = $this->getPropertyMetadata('db_content_col');
        $minutes_col   = $this->getPropertyMetadata('db_minutes_col');
        $createdby_col = $this->getPropertyMetadata('db_createdby_col');
        $createdat_col = $this->getPropertyMetadata('db_createdat_col');
        $display_rule  = $this->getPropertyMetadata('display_rule');
        
        if (!empty($this->masterObject))
        {
            $composition = $this->masterObject->findCompositionFor($class_name);
            
            if (!empty($composition))
            {
                $foreign_key  = $composition['fkey'];
                $objects = $class_name::where($foreign_key, '=', $this->masterObject->getPrimaryKeyValue())->orderBy($this->masterObject->getPrimaryKey(), 'desc')->load();
                
                if ($objects)
                {
                    foreach ($objects as $object)
                    {
                        $userby_att = 'userid';
                        $author_name = '';
                        
                        if (!empty($object->getCreatedByColumn()) && !empty($object->getUserByAttribute()))
                        {
                            $userby_att = $object->getUserByAttribute();
                        }
                        
                        TTransaction::open('permission');
                        if ($userby_att == 'userid')
                        {
                            $user = SystemUser::find($object->$createdby_col);
                        }
                        else if ($userby_att == 'custom_code')
                        {
                            $user = SystemUser::newFromCustomCode($object->$createdby_col);
                        }
                        else if ($userby_att == 'login')
                        {
                            $user = SystemUser::newFromLogin($object->$createdby_col);
                        }
                        
                        if (!empty($user) && $user instanceof SystemUser)
                        {
                            $author_name = $user->name . '<br><span class="gray">' . $user->email . '</span>';
                        }
                        TTransaction::close();
                        
                        $bg_color = $object->isCreatedBySessionUser() ? 'bg-primary' : 'bg-secondary';
                        $side     = $object->isCreatedBySessionUser() ? 'right' : 'left';
                        
                        $display = true;
                        if (!empty($display_rule) && $display_rule == 'U')
                        {
                            $display = $object->isCreatedBySessionUser();
                        }
                        
                        if ($display)
                        {
                            $data = new stdClass;
                            $data->id = $object->getPrimaryKeyValue();
                            $data->description = $object->$content_col;
                            $data->minutes = $object->$minutes_col;
                            $data->created_by = $author_name;
                            $data->created_at = $object->$createdat_col;
                            $data->record = $object;
                            $this->datagrid->addItem($data);
                        }
                    }
                }
            }
            else
            {
                $this->datagrid->after(new TAlert('danger', _t('Composition not found between ^1 and ^2', '<b>' . get_class($this->masterObject) . '</b>', '<b>' . $class_name . '</b>')));
            }
        }
    }
}
