<?php
/**
 * Creator Attach Plugin Trait
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorAttachPluginTrait
{
    /**
     * Save Attach
     */
    private function saveAttach($param)
    {
        // rebuild UI to access plugin metadata
        $ui = $this->rebuildUI();
        
        $plugin = $ui->getPlugin($param['plugin_name']);
        
        if (empty($param['attach']))
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
                    $file_field = $plugin->getPropertyMetadata('db_file_col');
                    $content_field = $plugin->getPropertyMetadata('db_content_col');
                    $created_by_field = $plugin->getPropertyMetadata('db_createdby_col');
                    $created_at_field = $plugin->getPropertyMetadata('db_createdat_col');
                    
                    $folder = $plugin->getPropertyMetadata('save_path') ?? 'files/images';
                    $tmp_file = (str_replace('C:\\fakepath\\', '', $param['attach']));
                    $final_name = $folder.'/'.$param['master_object_id'].'/'.TSession::getValue('userid') . '_' . $tmp_file;
                    
                    if ( (!file_exists($final_name) && is_writable(dirname($final_name))) OR is_writable($folder))
                    {
                        $attach = new $class_name;
                        $attach->$foreign_key   = $param['master_object_id'];
                        $attach->$file_field = $final_name;
                        
                        if (!empty($content_field))
                        {
                            $attach->$content_field = $param['description'];
                        }
                        
                        if (empty($attach->getCreatedAtColumn()))
                        {
                            $attach->$created_at_field = date('Y-m-d H:i:s');
                        }
                        
                        if (empty($attach->getCreatedByColumn()))
                        {
                            $attach->$created_by_field = TSession::getValue('userid');
                        }
                        
                        $attach->store();
                        
                        @mkdir($folder.'/'.$param['master_object_id']);
                        rename("tmp/{$tmp_file}", $final_name);
                        
                        TToast::show('success', _t('Data added successfully'), 'bottom center', 'far:check-circle' );
                        
                        return $attach;
                    }
                    else
                    {
                        throw new Exception(_t('Permission denied') . ': '. '<b>' . $folder . '</b>');
                    }
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
     * Delete Attach
     */
    private function deleteAttach($param)
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
                $file_field = $plugin->getPropertyMetadata('db_file_col');
                $attach =  $obj->$file_field;
                
                $obj->delete();
                @unlink($attach);
                
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
     *
     */
    public static function downloadAttach($param)
    {
        $ini = AdiantiApplicationConfig::get();
        $seed = APPLICATION_NAME . (string) $ini['general']['seed'];
        
        if (md5($seed.$param['file']) == $param['hash'])
        {
            SystemDocumentDownloaderService::download($param['file'], 'inline');
        }
    }
    
    /**
     * Refresh Attach panel
     */
    public function refreshAttachPanel($param)
    {
        $this->refreshPageFragment($param);
    }
}
