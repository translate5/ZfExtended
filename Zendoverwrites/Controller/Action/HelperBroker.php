<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2021 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file lgpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
https://www.gnu.org/licenses/lgpl-3.0.txt

 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU LESSER GENERAL PUBLIC LICENSE version 3
			 https://www.gnu.org/licenses/lgpl-3.0.txt

END LICENSE AND COPYRIGHT
*/

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * Stellt einen eigenen HelperBroker bereit
 * - Grund: So wird die Überschreibung von Helpern
 *   per factoryOverwrites.ini möglich
 */
class  ZfExtended_Zendoverwrites_Controller_Action_HelperBroker extends Zend_Controller_Action_HelperBroker{
/**
      * @var Zend_Session_Namespace
      */
    static protected $_session = NULL;
    /**
     * initiiert das interne Mail und View Object
     */
    public function  __construct(Zend_Controller_Action $actionController) {
        try {
            self::$_session = new Zend_Session_Namespace();
        }
        catch (Exception $e) {
        }
        parent::__construct($actionController);
    }

    /**
     * Retrieve or initialize a helper statically
     *
     * Retrieves a helper object statically, loading on-demand if the helper
     * does not already exist in the stack. Always returns a helper, unless
     * the helper class cannot be found.
     *
     * @param  string $name
     * @return Zend_Controller_Action_Helper_Abstract
     */
    public static function getStaticHelper($name)
    {
        $name = self::overwriteName($name);
        return parent::getStaticHelper($name);
    }
    /**
     * getHelper() - get helper by name
     *
     * @param  string $name
     * @return Zend_Controller_Action_Helper_Abstract
     */
    public function getHelper($name)
    {
        $name = self::overwriteName($name);
        return parent::getHelper($name);
    }
    
    /**
     * überschreibt in factoryOverwrites.ini gelistete ControllerHelper-Objekte,
     * so dass das dort vorgesehene Mapping geladen wird, statt des eigentlichen Helpers
     */
    protected static function overwriteName($name){
        $ucName = ucfirst($name);
        $lcName = lcfirst($name);
        
        $config = Zend_Registry::get('config');
        $fo = $config->factoryOverwrites;
        if(isset($fo->helper->$lcName)){
            return $fo->helper->$lcName;
        }
        if(isset($fo->helper->$ucName)){
            return $fo->helper->$ucName;
        }
        return $name;
    }
}
