<?php
/**
 * Creator Time Track Plugin Trait
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorTimeTrackPluginTrait
{
    /**
     * Save Time
     */
    private function saveTime($param)
    {
        // rebuild UI to access plugin metadata
        $ui = $this->rebuildUI();
        
        $plugin = $ui->getPlugin($param['plugin_name']);
        
        if (empty($param['description']))
        {
            return;
        }
        
        if (empty($param['time']))
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
                    $time_field = $plugin->getPropertyMetadata('db_minutes_col');
                    $content_field = $plugin->getPropertyMetadata('db_content_col');
                    $created_by_field = $plugin->getPropertyMetadata('db_createdby_col');
                    $created_at_field = $plugin->getPropertyMetadata('db_createdat_col');
                    
                    $time = new $class_name;
                    $time->$foreign_key   = $param['master_object_id'];
                    
                    if (!empty($content_field))
                    {
                        $time->$content_field = $param['description'];
                    }
                    
                    if (!empty($time_field))
                    {
                        @list($hour, $minute) = explode(':', $param['time']);
                        $time->$time_field = ($hour * 60) + $minute;
                    }
                    
                    if (empty($time->getCreatedAtColumn()))
                    {
                        $time->$created_at_field = date('Y-m-d H:i:s');
                    }
                    
                    if (empty($time->getCreatedByColumn()))
                    {
                        $time->$created_by_field = TSession::getValue('userid');
                    }
                    
                    $time->store();
                    
                    TToast::show('success', _t('Data added successfully'), 'bottom center', 'far:check-circle' );
                    
                    return $time;
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
     * Delete Time
     */
    private function deleteTime($param)
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
     * Refresh Time panel
     */
    public function refreshTimePanel($param)
    {
        $this->refreshPageFragment($param);
    }
}
