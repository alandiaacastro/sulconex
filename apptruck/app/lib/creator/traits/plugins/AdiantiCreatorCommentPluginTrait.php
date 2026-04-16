<?php
/**
 * Creator Comment Plugin Trait
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorCommentPluginTrait
{
    /**
     * Save comment
     */
    private function saveComment($param)
    {
        // rebuild UI to access plugin metadata
        $ui = $this->rebuildUI();
        
        $plugin = $ui->getPlugin($param['plugin_name']);
        
        if (empty($param['content']))
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
                    
                    $comment = new $class_name;
                    $comment->$foreign_key   = $param['master_object_id'];
                    $comment->$content_field = $param['content'];
                    
                    if (empty($comment->getCreatedAtColumn()))
                    {
                        $comment->$created_at_field = date('Y-m-d H:i:s');
                    }
                    
                    if (empty($comment->getCreatedByColumn()))
                    {
                        $comment->$created_by_field = TSession::getValue('userid');
                    }
                    
                    $comment->store();
                    
                    TToast::show('success', _t('Data added successfully'), 'bottom center', 'far:check-circle' );
                    
                    return $comment;
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
     * Delete comment
     */
    private function deleteComment($param)
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
     * Refresh comment panel
     */
    public function refreshCommentPanel($param)
    {
        $this->refreshPageFragment($param);
    }
}
