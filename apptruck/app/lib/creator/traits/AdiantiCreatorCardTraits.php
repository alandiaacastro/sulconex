<?php
/**
 * Creator Card Traits
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorCardTraits
{
    use AdiantiCreatorSearchLoadTrait;
    use AdiantiCreatorDeleteTrait;
    use AdiantiCreatorExportTrait;
    use AdiantiCreatorPresenterTrait;
    
    /**
     * Pack Card with different pack styles
     */
    private function packUI($with_breadcrumb)
    {
        if (!empty($this->cardview))
        {
            $attributes = $this->cardview->getMetadata('attributes');
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
