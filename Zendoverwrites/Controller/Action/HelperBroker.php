<?php
 /*
   START LICENSE AND COPYRIGHT

  This file is part of the ZfExtended library and build on Zend Framework

  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

  This file is multi-licensed under the EPL, LGPL and GPL licenses. 

  It may be used under the terms of the Eclipse Public License - v 1.0
  as published by the Eclipse Foundation and appearing in the file eclipse-license.txt 
  included in the packaging of this file.  Please review the following information 
  to ensure the Eclipse Public License - v 1.0 requirements will be met:
  http://www.eclipse.org/legal/epl-v10.html.

  Also it may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
  or the GNU GENERAL PUBLIC LICENSE version 3 as published by the 
  Free Software Foundation, Inc. and appearing in the files lgpl-license.txt and gpl3-license.txt
  included in the packaging of this file.  Please review the following information 
  to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3 requirements or the
  GNU GENERAL PUBLIC LICENSE version 3 requirements will be met:
  http://www.gnu.org/licenses/lgpl.html   /  http://www.gnu.org/licenses/gpl.html
  
  @copyright  Marc Mittag, MittagQI - Quality Informatics
  @author     MittagQI - Quality Informatics
  @license    Multi-licensed under Eclipse Public License - v 1.0 http://www.eclipse.org/legal/epl-v10.html, GNU LESSER GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/lgpl.html and GNU GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/gpl.html

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
    /*
     * überschreibt in factoryOverwrites.ini gelistete ControllerHelper-Objekte,
     * so dass das dort vorgesehene Mapping geladen wird, statt des eigentlichen Helpers
     */
    protected static function overwriteName($name){
        ZfExtended_Factory::loadConfig();
        $ucName = ucfirst($name);
        $lcName = lcfirst($name);
        if(is_null(self::$_session)){
            return $name;
        }
        if(isset(self::$_session->_factoryOverwrites->helper->$lcName)){
            return self::$_session->_factoryOverwrites->helper->$lcName;
        }
        if(isset(self::$_session->_factoryOverwrites->helper->$ucName)){
            return self::$_session->_factoryOverwrites->helper->$ucName;
        }
        return $name;
    }
}
