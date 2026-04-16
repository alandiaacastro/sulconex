<?php
/**
 * AdiantiCreatorTransformers
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
class AdiantiCreatorTransformers
{
    /**
     * Converts a ISO date to Brazilian format
     */
    public static function dateToBr($value)
    {
        return TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy');
    }
    
    /**
     * Converts a ISO datetime to Brazilian format
     */
    public static function datetimeToBr($value)
    {
        return TDateTime::convertToMask($value, 'yyyy-mm-dd hh:ii:ss', 'dd/mm/yyyy hh:ii:ss');
    }
    
    /**
     * View an image path as <img>
     */
    public static function viewAsImage($value)
    {
        return "<img style='width:100%' src='$value'>";
    }
    
    /**
     * View a telephone number as a callto link
     */
    public static function linkTelephone($value)
    {
        return "<a href='tel:$value'>$value</a>";
    }
    
    /**
     * View a telephone number as a whatsapp link
     */
    public static function linkWhatsapp($value)
    {
        return "<a target='newwindow' href='https://api.whatsapp.com/send?phone={$value}'>{$value}</a>";
    }
    
    /**
     * View a email as a mailto link
     */
    public static function linkEmail($value)
    {
        return "<a href='mailto:$value'>$value</a>";
    }
    
    /**
     *
     */
    public static function upper($value)
    {
        return strtoupper($value);
    }
    
    /**
     *
     */
    public static function booleanYesNo($value)
    {
        $bool = ($value && $value !== 'N' && $value !== 'false');
        $class = !$bool ? 'danger' : 'success';
        $label = !$bool ? _t('No') : _t('Yes');
        
        $div = new TElement('span');
        $div->{'class'} = "badge rounded-pill text-bg-{$class}";
        $div->{'style'} = "text-shadow:none; font-size:10pt;";
        
        $div->add($label);
        return $div;
    }
    
    /**
     *
     */
    public static function formatMonetary($value)
    {
        if (is_numeric($value)) {
            return 'R$ ' . number_format($value, 2, ',', '.');
        }
        return $value;
    }
    
    /**
     *
     */
    public static function formatNumeric($value)
    {
        if (is_numeric($value)) {
            return number_format($value, 2, ',', '.');
        }
        return $value;
    }
    
    /**
     * Display a badge
     */
    public static function showAsPill($value)
    {
        if (substr($value,0,7) == 'pill://') {
            $badge = substr($value, 7);
            $parts = explode('::', $badge);
            $name = strip_tags( (string) $parts[0]);
            $cor = strip_tags( (string) $parts[1]);
            return '<span style="font-size:100%;font-weight:normal;background-color:'.$cor.';color:white" class="badge rounded-pill">'.$name.' </span>';
        }
        return $value;
    }
    
    /**
     * Show system user name
     */
    public static function showSystemUserName($value)
    {
        if (!empty($value))
        {
            TTransaction::open('permission');
            $user = SystemUser::findCache($value);
            TTransaction::close();
            
            if ($user instanceof SystemUser)
            {
                return $user->name;
            }
        }
        return $value;
    }
}
