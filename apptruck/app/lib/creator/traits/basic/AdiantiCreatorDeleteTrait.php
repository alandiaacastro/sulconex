<?php
/**
 * Creator Delete Trait
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorDeleteTrait
{
    /**
     * onDelete()
     * @author Creator
     */
    private function confirmDeletion($param)
    {
        try
        {
            if (isset($param['confirmed']) && $param['confirmed'] == 'Y')
            {
                TTransaction::open($this->database);
                $class = $this->activeRecord;
                
                if (!empty($param['key']))
                {
                    $key = $param['key'];
                }
                else if (!empty($param[$class::PRIMARYKEY]))
                {
                    $key = $param[$class::PRIMARYKEY];
                }
                
                if (!empty($key))
                {
                    $object = $class::find($key);
                    if ($object)
                    {
                        $object->delete();
                    }
                }
                TTransaction::close();
                
                // reload the listing
                $this->onReload( $param );
                
                new TMessage('info', AdiantiCoreTranslator::translate('Record deleted'));
            }
            else
            {
                // define the delete action
                $action = new TAction(array(__CLASS__, 'onDelete'));
                $action->setParameters($param); // pass the key parameter ahead
                $action->setParameter('confirmed', 'Y');
                
                // shows a dialog to the user
                new TQuestion(AdiantiCoreTranslator::translate('Do you really want to delete ?'), $action);
            }
        }
        catch (Exception $e) // in case of exception
        {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
