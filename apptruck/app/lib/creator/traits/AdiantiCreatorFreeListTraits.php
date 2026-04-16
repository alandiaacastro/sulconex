<?php
/**
 * Creator Free List Traits
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorFreeListTraits
{
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
            $items = $this->datagrid->getItems();
            $columns = $this->datagrid->getColumns();
            $first_column = reset($columns);
            $attribute_name = $first_column->getName();
            
            $ids = [];
            foreach ($items as $item)
            {
                if (isset($item->$attribute_name))
                {
                    $ids[$item->$attribute_name] = $item->$attribute_name;
                }
            }
            TSession::setValue(__CLASS__.'_selected_rows', $ids);
        }
        catch (Exception $e) // in case of exception
        {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
        
        $this->datagrid->rebuild(); // readd items in order to run transformers again, because session var was modified and transformers were run in the constructor (addItems),
        $this->onLoad($param);
    }
    
    /**
     * Select no rows
     */
    private function selectNoRows($param)
    {
        TSession::setValue(__CLASS__.'_selected_rows', []);
        $this->datagrid->rebuild(); // readd items in order to run transformers again, because session var was modified and transformers were run in the constructor (addItems),
        $this->onLoad($param);
    }
    
    /**
     * Highlight selected rows
     */
    private static function formatSelectedRow($value, $object, $row, $cell)
    {
        $selected_rows = TSession::getValue(__CLASS__.'_selected_rows') ?? [];
        
        if (!empty($selected_rows[$value]))
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
