<?php
/**
 * This is the wrapper for the HYExcel module
 * 
 * @package    ThirdParty
 * @subpackage HYExcel
 * @author     lhe<lhe@bytecraft.com.au>
 */
class HYExcel_Autoload
{
    /**
     * spl autoload the register for bootstraping HYExcel
     * @return boolean
     */
    public static function Register() 
    {
		return spl_autoload_register(array(__CLASS__, 'Load'));
	}
	/**
	 * auto loader
	 * 
	 * @param string $pObjectName The HYExcel class name
	 * 
	 * @return boolean
	 */
	public static function Load($pObjectName)
	{
		if ((class_exists($pObjectName)) || (strpos($pObjectName, 'HYExcel') === False))
			return false;
		$pObjectFilePath =	dirname(__FILE__) . '/' . $pObjectName . '.php';
		if ((file_exists($pObjectFilePath) === false) || (is_readable($pObjectFilePath) === false))
		    return false;
		require_once($pObjectFilePath);
	}
}