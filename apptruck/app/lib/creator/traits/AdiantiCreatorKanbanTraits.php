<?php
/**
 * Creator Kanban Traits
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorKanbanTraits
{
    private $stageField;
    
    use AdiantiCreatorSearchLoadTrait;
    use AdiantiCreatorDeleteTrait;
    use AdiantiCreatorExportTrait;
    use AdiantiCreatorPresenterTrait;
    
    /**
     * Validate kanban metadata
     */
    private function validateKanbanMetadata()
    {
        if (empty($this->kanban))
        {
            return;
        }
        
        if (empty($this->kanban->getMetadata('stage_class')))
        {
            throw new Exception(_t('^1 not defined', _t('Model (stage)')));
        }
        if (empty($this->kanban->getMetadata('stage_field')))
        {
            throw new Exception(_t('^1 not defined', 'Stage field'));
        }
        if (empty($this->kanban->getMetadata('stage_name')))
        {
            throw new Exception(_t('^1 not defined', _t('Title (stage)')));
        }
        if (empty($this->kanban->getMetadata('stage_order')))
        {
            throw new Exception(_t('^1 not defined', _t('Order (stage)')));
        }
    }
    
    /**
     * Load stages
     */
    private function loadKanbanStages()
    {
        if (empty($this->kanban))
        {
            return;
        }
        
        $stage_class = $this->kanban->getMetadata('stage_class');
        $stage_order = $this->fixDbAttribute($this->kanban->getMetadata('stage_order'));
        $stage_name  = $this->fixDbAttribute($this->kanban->getMetadata('stage_name'));
        
        TTransaction::open($this->database);
        $stages = $stage_class::orderBy($stage_order)->load();
        TTransaction::close();
        
        foreach ($stages as $key => $stage)
        {
            $this->kanban->addStage($stage->getPrimaryKeyValue(), $stage->render('{'.$stage_name.'}'), $stage);
        }
    }
    
    /**
     * Update items order inside a kanban stage
     */
    private function updateStageItemsOrder($param)
    {
        if (empty($this->kanban))
        {
            return;
        }
        
        if (empty($param['order']))
        {
            return;
        }

        $stage_field = $this->fixDbAttribute($this->kanban->getMetadata('stage_field'));
        $item_seq    = $this->fixDbAttribute($this->kanban->getMetadata('item_seq'));
        
        try
        {
            TTransaction::open($this->database);

            foreach ($param['order'] as $key => $id)
            {
                $sequence = ++ $key;

                $class = $this->activeRecord;
                $item = new $class($id);

                if (!empty($item_seq))
                {
                    $item->$item_seq = $sequence;
                }
                $item->$stage_field = $param['stage_id'];
                $item->store();
            }
    		
            TTransaction::close();
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
    }
    
    /**
     * Prepare a new item for insertion
     */
    private function prepareNewItem($param)
    {
        if (empty($this->kanban))
        {
            return;
        }
        
        $stage_field = $this->fixDbAttribute($this->kanban->getMetadata('stage_field'));
        $item_seq    = $this->fixDbAttribute($this->kanban->getMetadata('item_seq'));
        
        $data = [];
        if (!empty($param['key']))
        {
            $data[$stage_field] = $param['key'];
        }
        
        if (!empty($item_seq))
        {
            $data[$item_seq] = 999;
        }
        
        return $data;
    }
    
    /**
     * Remove prefix from db attribute
     */
    private function fixDbAttribute($attribute)
    {
        if (substr($attribute,0,1) == '[')
        {
            $parts = explode('->', $attribute);
            return substr($parts[1],0,-1);
        }
        return $attribute;
    }
    
    /**
     * Pack Kanban with different pack styles
     */
    private function packUI($with_breadcrumb)
    {
        if (!empty($this->kanban))
        {
            $attributes = $this->kanban->getMetadata('attributes');
            $search_pack = $attributes['search_pack'];
        }
        else
        {
            $search_pack = 'curtain';
        }
        
        $vbox = new TVBox;
        
        if ($search_pack == 'curtain')
        {
            $vbox->{'style'} = 'display:block;width:100%';
            
            if ($with_breadcrumb)
            {
                $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
            }
            
            $vbox->add($this->ui);
        }
        else if ($search_pack == 'top')
        {
            $vbox->{'style'} = 'width: calc(100% - 10px)';
            
            if ($with_breadcrumb)
            {
                $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
            }
            
            $vbox->add($this->search)->style='margin-bottom:10px';
            $vbox->add($this->ui);
        }
        else if ($search_pack == 'left')
        {
            $width = ($this instanceof TWindow) ? '99' : '100';
            $vbox->{'style'} = "display:block;width:{$width}%";
            
            if ($with_breadcrumb)
            {
                $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
            }
            
            $hbox = new TElement('div');
            $hbox->{'class'} = 'row';
            $hbox->add(TElement::tag('div', $this->search, ['class' => 'col-sm-3 mb-1']));
            $hbox->add(TElement::tag('div', $this->ui, ['class' => 'col-sm-9']));
            $vbox->add($hbox);
        }
        
        return $vbox;
    }
}
