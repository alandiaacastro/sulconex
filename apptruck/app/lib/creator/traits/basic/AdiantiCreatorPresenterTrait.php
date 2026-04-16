<?php
/**
 * Creator Presenter Trait
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorPresenterTrait
{
    /**
     *
     */
    private static function showInRightPanel($content, $title = '', $override = false)
    {
        if (!empty($content))
        {
            try
            {
                // create empty page for right panel
                $page = TPage::create();
                $page->setTargetContainer('adianti_right_panel');
                if ($override)
                {
                    $page->setProperty('override', 'true'); // Master detail cannot override
                }
                $page->setPageName(__CLASS__);
                
                $btn_close = new TButton('closeCurtain');
                $btn_close->onClick = "Template.closeRightPanel();";
                $btn_close->setLabel(AdiantiCoreTranslator::translate('Close'));
                $btn_close->setImage('fas:times red');
                $btn_close->style='float:right;right:20px;';
                
                $close_added = false;
                if ($content instanceof TForm)
                {
                    $header = $content->getChild()->getChildren()[0];
                    if ($header instanceof TElement && $header->class == 'card-header')
                    {
                        $btn_close->style='float:right;right:20px;position:absolute';
                        $header->add($btn_close);
                        $close_added = true;
                    }
                }
                
                if ($close_added)
                {
                    $page->add($content);
                }
                else
                {
                    $panel = new TPanelGroup($title);
                    $panel->{'style'} = 'height: 100%';
                    $panel->addHeaderWidget($btn_close);
                    $panel->add($content);
                    $page->add($panel);
                }
                
                $page->show();
            }
            catch (Exception $e) 
            {
                new TMessage('error', $e->getMessage());    
            }
        }
    }
    
    
    /**
     *
     */
    private static function showInWindow($content, $title = '', $width = 0.8, $height = 0.8)
    {
        if (!empty($content))
        {
            try
            {
                // create a window
                $page = TWindow::create($title, $width, $height);
                $page->removePadding();
                
                if ($content instanceof TForm)
                {
                    $header = $content->getChild()->getChildren()[0];
                    if ($header instanceof TElement && $header->class == 'card-header')
                    {
                        $header->hide();
                    }
                }
                
                // embed form inside window
                $page->add($content);
                $page->setIsWrapped(true);
                $page->show();
            }
            catch (Exception $e) 
            {
                new TMessage('error', $e->getMessage());    
            }
        }
    }
    
    /**
     *
     */
    private static function embedPDFObject($file)
    {
        if (!empty($file))
        {
            $object = new TElement('object');
            $object->{'data'}  = 'download.php?file='.$file;
            $object->{'type'}  = 'application/pdf';
            $object->{'style'} = "width: 100%; height:calc(100% - 10px)";
            $object->add('<div style="padding:20px">' . _t('Your browser does not support displaying this content') . '<br><br>' .
                            '<a class="btn btn-primary btn-sn" style="color:white" target=_newwindow href="'.$object->data.'">' . _t('click here to download') .'<div>');
            return $object;
        }
    }
    
    /**
     * Open file for download
     */
    private static function downloadFile($file)
    {
        if (!empty($file))
        {
            TPage::openFile( $file );
        }
    }
    
    /**
     * Show detail form inside a window or side panel
     */
    private function closePage()
    {
        if ($this instanceof TWindow)
        {
            parent::closeWindow();
        }
        else if ($this instanceof TPage && $this->getTargetContainer() == 'adianti_right_panel')
        {
            TScript::create("Template.closeRightPanel()"); // closes popover if there's no right panel
        }
    }
}
