<?php
/**
 * AdiantiCreatorVariables
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
class AdiantiCreatorVariables
{
    /**
     * Return the user login from sesssion
     */
    public static function getUserLogin()
    {
        return TSession::getValue('login');
    }
    
    /**
     * Return the user id from session
     */
    public static function getUserId()
    {
        return TSession::getValue('userid');
    }
    
    /**
     * Return the user custom code from session
     */
    public static function getUserCustomCode()
    {
        return TSession::getValue('usercustomcode');
    }
    
    /**
     * Return the unit id from session
     */
    public static function getUnitId()
    {
        return TSession::getValue('userunitid');
    }
    
    /**
     * Return the unit custom code from session
     */
    public static function getUnitCustomCode()
    {
        return TSession::getValue('userunitcustomcode');
    }
    
    /**
     * Return the current date
     */
    public static function getCurrentDate()
    {
        return date('Y-m-d');
    }
    
    /**
     * Return the current time
     */
    public static function getCurrentTime()
    {
        return date('Y-m-d H:i:s');
    }
    
    /**
     * Return the current year
     */
    public static function getCurrentYear()
    {
        return date('Y');
    }
    
    /**
     * Return the current month
     */
    public static function getCurrentMonth()
    {
        return date('m');
    }
    
    /**
     * Return the request 'key' parameter
     */
    public static function getParamKey()
    {
        return !empty($_REQUEST['key'])? $_REQUEST['key'] : null;
    }
}
