<?php
/**
 * Creator Checklist Plugin Trait
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorChecklistPluginTrait
{
    /**
     * Save Checklist item
     */
    private function saveChecklist($param)
    {
        // rebuild UI to access plugin metadata
        $ui = $this->rebuildUI();
        
        $plugin = $ui->getPlugin($param['plugin_name']);
        
        if (empty($param['description']))
        {
            return;
        }
        
        if (!empty($plugin))
        {
            $master_class = $plugin->getPropertyMetadata('db_model');
            $class_name   = $plugin->getPropertyMetadata('db_model2');
            
            $object = new $master_class( $param['master_object_id'] );
            
            if (!empty($object))
            {
                $composition = $object->findCompositionFor($class_name);
                
                if (!empty($composition))
                {
                    $foreign_key   = $composition['fkey'];
                    $content_field = $plugin->getPropertyMetadata('db_content_col');
                    $created_by_field = $plugin->getPropertyMetadata('db_createdby_col');
                    $created_at_field = $plugin->getPropertyMetadata('db_createdat_col');
                    
                    $checklist = new $class_name;
                    $checklist->$foreign_key   = $param['master_object_id'];
                    
                    if (!empty($content_field))
                    {
                        $checklist->$content_field = $param['description'];
                    }
                    
                    if (empty($checklist->getCreatedAtColumn()))
                    {
                        $checklist->$created_at_field = date('Y-m-d H:i:s');
                    }
                    
                    if (empty($checklist->getCreatedByColumn()))
                    {
                        $checklist->$created_by_field = TSession::getValue('userid');
                    }
                    
                    $checklist->store();
                    
                    TToast::show('success', _t('Data added successfully'), 'bottom center', 'far:check-circle' );
                    
                    return $checklist;
                }
                else
                {
                    throw new Exception(_t('Composition not found between ^1 and ^2', '<b>' . get_class($object) . '</b>', '<b>' . $class_name . '</b>'));
                }
            }
        }
        else
        {
            throw new Exception(_t('Plugin not found') . ': <b>' . $param['plugin_name'] . '</b>');
        }
    }
    
    /**
     * Delete Checklist item
     */
    private function deleteChecklist($param)
    {
        // rebuild UI to access plugin metadata
        $ui = $this->rebuildUI();
        
        $plugin = $ui->getPlugin($param['plugin_name']);
        
        if (!empty($plugin))
        {
            $delete_rule   = $plugin->getPropertyMetadata('delete_rule');
            $class_name    = $plugin->getPropertyMetadata('db_model2');
            $createdat_col = $plugin->getPropertyMetadata('db_createdat_col');
            $delete_delay  = $plugin->getPropertyMetadata('delete_delay');
            
            if ($delete_rule == 'X')
            {
                throw new Exception(_t('Permission denied') );
            }
            
            $obj = $class_name::find($param['key']);
            
            if (!empty($obj))
            {
                if ( ($delete_rule == 'U') && (!$obj->isCreatedBySessionUser()) )
                {
                    throw new Exception(_t('Permission denied') );
                }
                
                if (!empty($delete_delay))
                {
                    $now  = new DateTime;
                    $time = new DateTime($obj->$createdat_col);
                    $time->add(new DateInterval('PT' . $delete_delay . 'M'));
                    
                    if ($now > $time)
                    {
                        throw new Exception(_t('Permission denied') );
                    }
                }
                $obj->delete();
                
                TToast::show('success', _t('Data removed successfully'), 'bottom center', 'far:check-circle' );
                return true;
            }
        }
        else
        {
            throw new Exception(_t('Plugin not found') . ': <b>' . $param['plugin_name'] . '</b>');
        }
    }
    
    /**
     * Update Checklist item
     */
    private function updateChecklist($param)
    {
        // rebuild UI to access plugin metadata
        $ui = $this->rebuildUI();
        
        $plugin = $ui->getPlugin($param['plugin_name']);
        
        if (!empty($plugin))
        {
            $update_rule   = $plugin->getPropertyMetadata('update_rule');
            $class_name    = $plugin->getPropertyMetadata('db_model2');
            $createdat_col = $plugin->getPropertyMetadata('db_createdat_col');
            $checked_col   = $plugin->getPropertyMetadata('db_checked_col');
            
            if ($update_rule == 'X')
            {
                throw new Exception(_t('Permission denied') );
            }
            
            $obj = $class_name::find($param['key']);
            
            if (!empty($obj))
            {
                if ( ($update_rule == 'U') && (!$obj->isCreatedBySessionUser()) )
                {
                    throw new Exception(_t('Permission denied') );
                }
                
                $obj->$checked_col = !empty($obj->$checked_col) ? 0 : 1;
                $obj->store();
                
                TToast::show('success', _t('Data added successfully'), 'bottom center', 'far:check-circle' );
                return true;
            }
        }
        else
        {
            throw new Exception(_t('Plugin not found') . ': <b>' . $param['plugin_name'] . '</b>');
        }
    }
    
    /**
     * Refresh Checklist panel
     */
    public function refreshChecklistPanel($param)
    {
        $this->refreshPageFragment($param);
    }
}
