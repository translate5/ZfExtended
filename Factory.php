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
 */
/**
 * Factory, die Objekte bereit stellt
 * - existiert /application/iniOverwrites/APPLICATION_AGENCY/factoryOverwrites.ini, werden die dort
 *   gemappten Objekte statt der eigentlich bei der factory angeforderten
 *   Objekte zurückgegeben
 *
 */
class  ZfExtended_Factory
{
    /**
      * @var Zend_Session_Namespace | NULL
      */
    static protected $_session = NULL;
    /**
      * @var Zend_Config_Ini | NULL
      */
    static protected $_factoryIni = NULL;

    private static function init() {
        try {
            self::$_session = new Zend_Session_Namespace();
        }
        catch (Exception $e) {
        }
    }
    /**
     * Gibt ein Objekt von $className zurück; Anwendung derzeit nur für Models;
     * ControllerHelper werden automatisch durch den ZfExtended_Zendoverwrites_Controller_Action_HelperBroker geladen
     * falls sie in der factoryOverwrites.innii entsprechend definiert wurden
     *
     * @param string className
     * @param array params Optional; Parameter, die an den Konstruktor übergeben
     *          werden sollen; Reihenfolge und Typen wie im Konstruktor
     * @return void
     */
    public static function get(string $className, array $params = array()){
        self::loadConfig();
        if(!is_null(self::$_factoryIni) and
                isset(self::$_session->_factoryOverwrites->models->$className)){
            $rc = new ReflectionClass(self::$_session->_factoryOverwrites->models->$className);
            return $rc->newInstanceArgs($params);
        }
        $rc = new ReflectionClass($className);
        return $rc->newInstanceArgs($params);
    }

     /* Legt Konfiguration aus der factoryOverwrites.ini in die Session, holt
      * sie aus der Session und stellt sie in der Klassenvariable self::$_factoryIni
      * zur Verfügung
     *
     * @return void
     */
    public static function loadConfig(){
        self::init();
        if(!is_null(self::$_session) and
                 isset(self::$_session->_factoryOverwrites)){
             self::$_factoryIni = self::$_session->_factoryOverwrites;
         }
         elseif(!is_null(self::$_session) and
                 !isset(self::$_session->_factoryOverwrites) and
                 file_exists(APPLICATION_PATH.'/iniOverwrites/'.APPLICATION_AGENCY.
                     '/factoryOverwrites.ini')){
            self::$_session->_factoryOverwrites = self::getConfig();
         }
         elseif(file_exists(APPLICATION_PATH.'/iniOverwrites/'.APPLICATION_AGENCY.
                     '/factoryOverwrites.ini')){
             self::getConfig();
         }
     }
     /* Lädt Konfiguration aus der factoryOverwrites.ini in self::$_factoryIni
     *
     * @return Zend_Config_Ini factoryIni
     */
    protected static function getConfig(){
        return self::$_factoryIni = new Zend_Config_Ini(APPLICATION_PATH.
                    '/iniOverwrites/'.APPLICATION_AGENCY.'/factoryOverwrites.ini');
     }
}