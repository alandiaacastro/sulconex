<?php
/**
 * Creator Calendar Traits
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorCalendarTraits
{
    use AdiantiCreatorSearchLoadTrait;
    use AdiantiCreatorDeleteTrait;
    use AdiantiCreatorExportTrait;
    use AdiantiCreatorPresenterTrait;
    
    /**
     * Validate calendar metadata
     */
    private function validateCalendarMetadata()
    {
        if (empty($this->calendar))
        {
            return;
        }
        
        if (empty($this->calendar->getMetadata('start_field')))
        {
            throw new Exception(_t('^1 not defined', _t('Start column')));
        }
        if (empty($this->calendar->getMetadata('end_field')))
        {
            throw new Exception(_t('^1 not defined', _t('End column')));
        }
        if (empty($this->calendar->getMetadata('title_field')))
        {
            throw new Exception(_t('^1 not defined', _t('Title column')));
        }
        if (empty($this->calendar->getMetadata('color_field')))
        {
            throw new Exception(_t('^1 not defined', _t('Color column')));
        }
    }
    
    /**
     * Update the event after drag and drop
     */
    private function updateEvent($param)
    {
        if (empty($this->calendar))
        {
            return;
        }
        
        $start_field = $this->fixDbAttribute($this->calendar->getMetadata('start_field'));
        $end_field   = $this->fixDbAttribute($this->calendar->getMetadata('end_field'));
        
        try
        {
            if (isset($param['id']))
            {
                // get the parameter $key
                $key=$param['id'];
                
                TTransaction::open($this->database);
                
                $class = $this->activeRecord;
                $object = new $class($key);
                
                $object->$start_field = str_replace('T', ' ', $param['start_time']);
                $object->$end_field   = str_replace('T', ' ', $param['end_time']);
                $object->store();
                                
                // close the transaction
                TTransaction::close();
            }
        }
        catch (Exception $e) // in case of exception
        {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
    
    /**
     * Load events from session filters
     */
    private function loadEventsFromFilters($param)
    {
        if (empty($this->calendar))
        {
            return;
        }
        
        $start_field = $this->fixDbAttribute($this->calendar->getMetadata('start_field'));
        $end_field   = $this->fixDbAttribute($this->calendar->getMetadata('end_field'));
        
        $this->criteria = new TCriteria;
        $this->criteria->add(new TFilter($start_field, '<=', $param['end']));
        $this->criteria->add(new TFilter($end_field, '>=', $param['start']));
        
        return $this->loadObjectsFromFilters($param);
    }
    
    /**
     * Prepare a new event for insertion
     */
    private function prepareNewEvent($param)
    {
        if (empty($this->calendar))
        {
            return;
        }
        
        $start_field = $this->fixDbAttribute($this->calendar->getMetadata('start_field'));
        $end_field   = $this->fixDbAttribute($this->calendar->getMetadata('end_field'));
        
        $date = $param['date'] ?? '';
        
        if (!empty($date) && strlen($date) == 10)
        {
            $date .= ' '. date('H:00:00');
        }
        
        $data = [];
        $data[$start_field] = $date;
        $data[$end_field]   = $date;
        
        return $data;
    }
    
    /**
     * Prepare object to be rendered in calendar
     */
    private function prepareEvent($event)
    {
        if (empty($this->calendar))
        {
            return;
        }
        
        $start_field = $this->fixDbAttribute($this->calendar->getMetadata('start_field'));
        $end_field   = $this->fixDbAttribute($this->calendar->getMetadata('end_field'));
        $title_field = $this->fixDbAttribute($this->calendar->getMetadata('title_field'));
        $color_field = $this->fixDbAttribute($this->calendar->getMetadata('color_field'));
        $popover     = $this->fixDbAttribute($this->calendar->getMetadata('popover'));
        
        $event_array = $event->toArray();
        $event_array['start'] = str_replace(' ', 'T', $event_array[$start_field]);
        $event_array['end']   = str_replace(' ', 'T', $event_array[$end_field]);
        
        if (empty($popover))
        {
            $event_array['title'] = $event_array[$title_field];
        }
        else
        {
            $popover_content = $event->render($popover);
            $event_array['title'] = TFullCalendar::renderPopover($event_array[$title_field] ?? '', 'Popover title', $popover_content);
        }
        $event_array['color'] = $event->render('{'.$color_field.'}');
        return $event_array;
    }
    
    /**
     * Remove prefix from db attribute
     */
    private function fixDbAttribute($attribute)
    {
        if (substr($attribute,0,1) == '[')
        {
            $parts = explode('->', $attribute,2);
            return substr($parts[1],0,-1);
        }
        return $attribute;
    }
    
    /**
     * Pack Calendar with different pack styles
     */
    private function packUI($with_breadcrumb)
    {
        if (!empty($this->calendar))
        {
            $attributes = $this->calendar->getMetadata('attributes');
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
