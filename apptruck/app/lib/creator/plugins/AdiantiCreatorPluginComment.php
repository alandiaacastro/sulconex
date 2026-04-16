<?php
/**
 * Creator Comment Plugin
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
class AdiantiCreatorPluginComment extends TElement
{
    private $metadata;
    private $controller;
    private $masterObject;
    private $timeline;
    
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
            parent::add(new TAlert('danger', _t('^1 not defined', '<b>'._t('Content field').'</b>')));
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
        $this->timeline = new TTimeline;
        $this->timeline->setTimeDisplayMask('dd/mm/yyyy');
        $this->timeline->setFinalIcon( 'fa:flag-checkered bg-red' );
        $this->timeline->class .= ' plugin_comment';
        
        if ($this->getPropertyMetadata('use_both_sides') == 'Y')
        {
            $this->timeline->setUseBothSides();
        }
        
        $form_timeline = new TForm('form_'. $this->getPropertyMetadata('name'));
        $content = new THtmlEditor('content');
        $content->setSize('100%', 200);
        
        $button = TButton::create('save', [$this->controller, $this->getPropertyMetadata('save_method')], _t('Save'), 'fa:check');
        $button->getAction()->setParameter('plugin_name', $this->getPropertyMetadata('name'));
        $button->getAction()->setParameter('master_object_id', $this->masterObject->getPrimaryKeyValue());
        $button->getAction()->setParameter('static', '1');
        $button->class='btn btn-primary';
        $button->style='margin-top:6px;float:right';
        
        $form_timeline->add($content, true);
        $form_timeline->add($button, true);
        
        $wrapper_form = new TElement('div');
        $wrapper_form->add($form_timeline);
        $wrapper_form->id = 'wrapper_form_'. $this->getPropertyMetadata('name');;
        $wrapper_form->style = 'display:none';
        
        $class_name = $this->getPropertyMetadata('db_model2');
        
        $action_del = new TAction([$this->controller, $this->getPropertyMetadata('delete_method')], ['static' => '1']);
        $action_del->setParameter('key', '{'.$class_name::PRIMARYKEY.'}');
        $action_del->setParameter('plugin_name', $this->getPropertyMetadata('name'));
        $action_del->setParameter('master_object_id', $this->masterObject->getPrimaryKeyValue());
        $action_del->setProperty('btn-class', 'btn');
        $this->timeline->addAction($action_del, '', 'far:trash-alt red', [$this, 'displayDeleteCondition'] );
        
        $action_new = new TButton('new_comment');
        $action_new->setLabel(_t('New comment'));
        $action_new->addStyleClass('btn-outline-primary');
        $action_new->style = 'float:right;margin-bottom:4px';
        $action_new->setImage('fa:plus');
        $action_new->addFunction("$('#{$wrapper_form->id}').toggle();$(this).toggleClass('btn-secondary').toggleClass('btn-outline-primary');");
        
        // wrap the content using vertical box
        $vbox_timeline = new TVBox;
        $vbox_timeline->id = 'container_' . $this->getPropertyMetadata('name');
        $vbox_timeline->style = 'width: 100%; padding: 10px';
        $vbox_timeline->add($action_new);
        $vbox_timeline->add($wrapper_form);
        $vbox_timeline->add($this->timeline);
        
        parent::add($vbox_timeline);
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
            $time = new DateTime($object->$createdat_col);
            $time->add(new DateInterval('PT' . $delete_delay . 'M'));
            
            if ($now > $time)
            {
                return false;
            }
        }
        
        if ($delete_rule == 'U')
        {
            return $object->isCreatedBySessionUser();
        }
        
        return true;
    }
    
    /**
     * Load objects into the timeline
     */
    public function load()
    {
        $class_name    = $this->getPropertyMetadata('db_model2');
        $content_col   = $this->getPropertyMetadata('db_content_col');
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
                            $author_name = $user->name;
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
                            $this->timeline->addItem($object->getPrimaryKeyValue(), $author_name,  $object->$content_col, $object->$createdat_col,  'fa:comment '.$bg_color,  $side,  $object );
                        }
                    }
                }
            }
            else
            {
                $this->timeline->after(new TAlert('danger', _t('Composition not found between ^1 and ^2', '<b>' . get_class($this->masterObject) . '</b>', '<b>' . $class_name . '</b>')));
            }
        }
    }
}
