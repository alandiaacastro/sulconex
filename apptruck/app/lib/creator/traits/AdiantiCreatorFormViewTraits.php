<?php
/**
 * Creator Form View Traits
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorFormViewTraits
{
    use AdiantiCreatorCommentPluginTrait;
    use AdiantiCreatorAttachPluginTrait;
    use AdiantiCreatorTimeTrackPluginTrait;
    use AdiantiCreatorChecklistPluginTrait;
    
    /**
     * Rebuild UI to access inner objects and properties
     */
    private function rebuildUI()
    {
        $ui = new AdiantiXMLFormRender;
        $ui->setController(__CLASS__);
        $ui->parseFile(self::$form_path);
        return $ui;
    }
    
    /**
     * Render view again
     */
    public function renderView($param)
    {
        $this->view(['key' => $param['key']]);
        
        parent::setTargetContainer(null);
        $this->setIsWrapped(true);
        parent::show();
    }
    
    /**
     * Refresh PageFragment
     */
    public function refreshPageFragment($param)
    {
        // rebuild UI to access plugin metadata
        $ui = $this->rebuildUI();
        
        $plugin = $ui->getPlugin($param['plugin_name']);
        
        if (!empty($plugin))
        {
            $container = 'container_'.$plugin->getPropertyMetadata('name');
            
            $new_params = [];
            $new_params['key'] = $param['master_object_id'];
            $new_params['page_fragment'] = $container;
            $new_params['target_container'] = $container;
            $new_params['static'] = '1';
            $new_params['register_state'] = 'false';
            
            AdiantiCoreApplication::loadPage(__CLASS__, 'renderView', $new_params);
        }
        else
        {
            throw new Exception(_t('Plugin not found') . ': <b>' . $param['plugin_name'] . '</b>');
        }
    }
}
