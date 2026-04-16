<?php
/**
 * Creator Master Detail Traits
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorFormMasterDetailTraits
{
    use AdiantiCreatorFormTraits;
    use AdiantiCreatorPresenterTrait;
    
    /**
     * Returns the detail TForm
     */ 
    private function getDetailForm($detail_name)
    {
        $datagrid = $this->ui->getFormDetail($detail_name);
        if ($datagrid)
        {
            return $datagrid->getEditForm();
        }
    }
    
    /**
     * Returns the detail class (Active Record)
     */
    private function getDetailClass($detail_name)
    {
        $datagrid = $this->ui->getFormDetail($detail_name);
        if ($datagrid)
        {
            return $datagrid->getMetadata('db_model');
        }
    }
    
    /**
     * Load detail objects into datagrid
     */
    private function loadDetailItems($detail_name, $object)
    {
        $class_name  = $this->getDetailClass($detail_name);
        $composition = $object->findCompositionFor($class_name);
        $datagrid = $this->ui->getFormDetail($detail_name);
        
        if (!empty($composition))
        {
            $foreign_key = $composition['fkey'];
            $repos = $class_name::where($foreign_key, '=', $object->getPrimaryKeyValue());
            
            if (!empty($datagrid->getMetadata('db_order_col')) && !empty($datagrid->getMetadata('db_order_dir')))
            {
                $repos->orderBy($datagrid->getMetadata('db_order_col'), $datagrid->getMetadata('db_order_dir'));
            }
            
            $items = $repos->load();
            
            
            foreach( $items as $item )
            {
                $item->_rowid  = uniqid();
                $item->_rawobj = base64_encode($item->toJson());
                
                $row = $datagrid->addItem( $item );
                $row->id = $item->_rowid;
            }
            
            if (TSession::getValue('login') == 'admin')
            {
                $image = new TImage('fa:info-circle blue');
                $image->title = '<b>' . _t('Filters') . ' (' . _t('shown just for administrator') . '): </b><br>'. $repos->getCriteria()->dump();
                $datagrid->after($image);
            }
            return $items;
        }
        else
        {
            $datagrid->after(new TAlert('danger', _t('Composition not found between ^1 and ^2', '<b>' . get_class($object) . '</b>', '<b>' . $class_name . '</b>')));
        }
    }
    
    /**
     * Save datagrid detail items into database
     */
    private function saveDetailItems($detail_name, $object, $param)
    {
        $class_name  = $this->getDetailClass($detail_name);
        $composition = $object->findCompositionFor($class_name);
        
        $collection = [];
        if (!empty($composition))
        {
            $foreign_key = $composition['fkey'];
            
            if( !empty($param[$detail_name.'__rowid'] ))
            {
                $raw_collection = $this->getDetailPostData($param, $detail_name);
                
                if ($raw_collection)
                {
                    $detail_ids = [];
                    
                    foreach ($raw_collection as $raw_item)
                    {
                        $item = new $class_name;
                        $item->fromArray( json_decode( base64_decode($raw_item['_rawobj']), true) );
                        $item->$foreign_key = $object->getPrimaryKeyValue();
                        $item->store();
                        
                        $detail_ids[] = $item->getPrimaryKeyValue();
                        $collection[] = $item;
                    }
                    
                    if ($detail_ids)
                    {
                        $class_name::where($foreign_key, '=', $object->getPrimaryKeyValue())
                                   ->where($class_name::PRIMARYKEY, 'not in', $detail_ids)
                                   ->delete();
                    }
                    else
                    {
                        $class_name::where($foreign_key, '=', $object->getPrimaryKeyValue())->delete();
                    }
                }
                else
                {
                    $class_name::where($foreign_key, '=', $object->getPrimaryKeyValue())->delete();
                }
            }
            else
            {
                $class_name::where($foreign_key, '=', $object->getPrimaryKeyValue())->delete();
            }
        }
        else
        {
            throw new Exception(_t('Composition not found between ^1 and ^2', '<b>' . get_class($object) . '</b>', '<b>' . $class_name . '</b>'));
        }
        
        return $collection;
    }
    
    /**
     * Get Detail data from Raw post
     */
    private function getDetailPostData($array, $prefix)
    {
        foreach ($array as $field_name => $values)
        {
            if (substr($field_name, 0, strlen($prefix)) == $prefix)
            {
                $field_name = str_replace($prefix . '_', '', $field_name);
                
                if (is_array($values))
                {
                    foreach ($values as $row => $value)
                    {
                        $list[$row] = $list[$row] ?? [];
                        $list[$row][$field_name] = $value;
                    }
                }
            }
        }
        
        return $list;
    }
    
    /**
     * Prepare an object to be injected as datagrid row
     */
    private function prepareDetailRow($item, $param)
    {
        $detail_name = $param['_detail'];
        $datagrid = $this->ui->getFormDetail($detail_name);
        
        if ($datagrid)
        {
            $item->_rowid  = (!empty($param['_rowid'])) ? $param['_rowid'] : uniqid();
            $item->_rawobj = base64_encode( $item->toJson() );
            
            $row = $datagrid->addItem( $item );
            $row->id = $item->_rowid;                
            
            return $row;
        }
    }
    
    /**
     * Decode detail object from request
     */
    private function decodeDetailObject($param)
    {
        return json_decode(base64_decode($param['_rawobj']));
    }
    
    /**
     * Decode detail object from request
     */
    private static function staticDecodeDetailObject($param)
    {
        return json_decode(base64_decode($param['_rawobj']));
    }
    
    /**
     * Update detail datagrid row contents
     */
    private function updateDatagrid($detail_name, $row)
    {
        TDataGrid::replaceRowById($detail_name, $row->id, $row);
    }
    
    /**
     * Remove detail datagrid row
     */
    private function removeDetailRow($detail_name, $rowid)
    {
        TDataGrid::removeRowById($detail_name, $rowid);
    }
    
    /**
     * Show detail form inside a window or side panel
     */
    private function showDetailForm($form, $param = [])
    {
        if ($this instanceof TWindow)
        {
            self::showInWindow($form);
        }
        else if (!empty($param['inside_popover']))
        {
            $form->show();
        }
        else
        {
            self::showInRightPanel($form);
        }
    }
    
    /**
     * Close Detail view
     */
    private function closeDetailView()
    {
        if ($this instanceof TWindow)
        {
            parent::closeWindow();
        }
        else
        {
            TScript::create("Template.closeRightPanel()");
        }
    }
}
