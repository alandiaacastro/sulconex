<?php
/**
 * Creator Attach Plugin
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
class AdiantiCreatorPluginAttach extends TElement
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
        
        if (empty($this->getPropertyMetadata('db_file_col')))
        {
            parent::add(new TAlert('danger', _t('^1 not defined', '<b>'._t('File field').'</b>')));
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
        $this->datagrid->class = 'table table-hover vertical-middle plugin_attach';
        
        $id_col       = new TDataGridColumn('id',         'id',          'center', '1%');
        $file_col     = new TDataGridColumn('file',       _t('File'),    'center', '40%');
        $user_col     = new TDataGridColumn('created_by', _t('User'),    'center', '40%');
        $date_col     = new TDataGridColumn('created_at', _t('Date'),    'center', '20%');
        
        $file_col->setTransformer( function($value, $object, $row) {
            $ini = AdiantiApplicationConfig::get();
            $seed = APPLICATION_NAME . (string) $ini['general']['seed'];
            
            $value_trim = "<i class='fas fa-download'></i> " . basename($value);
            $db_content_col = $this->getPropertyMetadata('db_content_col');
            
            if (!empty($db_content_col) && !empty($object->record->$db_content_col))
            {
                $value_trim = '<b>'.$object->record->$db_content_col . '</b><br>'. $value_trim;
            }
            
            $link = new TActionLink($value_trim);
            $link->class = 'btn btn-default';
            $action = new TAction([$this->controller, 'downloadAttach'], ['file' => $value, 'hash' => md5($seed.$value), 'static' => '1']);
            $link->href = str_replace('index.php', 'engine.php', $action->serialize(TRUE, TRUE));
            $link->target = uniqid();
            unset($link->generator);
            return $link;
        });
        
        $date_col->setTransformer( function($value) {
           $time = AdiantiCreatorTransformers::datetimeToBr($value);
           $parts = explode(' ', $time);
           return '<i class="fa-regular fa-calendar"></i> ' .  $parts[0] . '<br><span class="gray">'.$parts[1]??''.'</span>';
        });
        
        $user_col->setTransformer( function($value) {
            return '<i class="fa-regular fa-circle-user"></i> ' . $value;
        });
        
        $this->datagrid->addColumn($id_col);
        $this->datagrid->addColumn($file_col);
        $this->datagrid->addColumn($user_col);
        $this->datagrid->addColumn($date_col);
        $id_col->setVisibility(false);
        
        $form_attach = new TForm('form_'. $this->getPropertyMetadata('name'));
        
        $attach = new TFile('attach');
        $attach->setSize('100%', 200);
        
        if (!empty($this->getPropertyMetadata('extensions')))
        {
            $attach->setAllowedExtensions(array_map('trim', explode(',', $this->getPropertyMetadata('extensions'))));
        }
        
        $button = TButton::create('save', [$this->controller, $this->getPropertyMetadata('save_method')], _t('Save'), 'fa:check');
        $button->getAction()->setParameter('plugin_name', $this->getPropertyMetadata('name'));
        $button->getAction()->setParameter('master_object_id', $this->masterObject->getPrimaryKeyValue());
        $button->getAction()->setParameter('static', '1');
        $button->class='btn btn-primary';
        $button->style='margin-top:6px;float:right';
        
        $form_attach->add($attach, true);
        
        if (!empty($this->getPropertyMetadata('db_content_col')))
        {
            $description = new TEntry('description');
            $description->placeholder = _t('Description');
            $form_attach->add($description, true);
        }
        
        $form_attach->add($button, true);
        
        $wrapper_form = new TElement('div');
        $wrapper_form->add($form_attach);
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
        
        $action_new = new TButton('new_attach');
        $action_new->setLabel(_t('Attach file'));
        $action_new->addStyleClass('btn-outline-primary');
        $action_new->style = 'float:right;margin-bottom:4px';
        $action_new->setImage('fa:plus');
        $action_new->addFunction("$('#{$wrapper_form->id}').toggle();$(this).toggleClass('btn-secondary').toggleClass('btn-outline-primary');");
        
        // wrap the content using vertical box
        $vbox_attach = new TVBox;
        $vbox_attach->id = 'container_' . $this->getPropertyMetadata('name');
        $vbox_attach->style = 'width: 100%; padding: 10px';
        $vbox_attach->add($action_new);
        $vbox_attach->add($wrapper_form);
        $vbox_attach->add($this->datagrid);
        
        parent::add($vbox_attach);
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
     * Load objects into the attach
     */
    public function load()
    {
        $class_name    = $this->getPropertyMetadata('db_model2');
        $file_col      = $this->getPropertyMetadata('db_file_col');
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
                            $data->file = $object->$file_col;
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
