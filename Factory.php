<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 */
/**
 * Factory implements the factory pattern  
 * to overwrite classes this has to be configured in the factoryOverwrites part of the app.ini
 */
class ZfExtended_Factory {
    public static $overwrites = null;
    
    /**
     * returns a $className instance, currently only for models
     * ControllerHelper are loaded automagicly by ZfExtended_Zendoverwrites_Controller_Action_HelperBroker
     *
     * @param string className
     * @param array params optional; parameters for class constructor
     * @return mixed
     */
    public static function get(string $className, array $params = array()){
        self::initOverwrites();
        if(isset(self::$overwrites[$className])){
            $className = self::$overwrites[$className];
        }
        $rc = new ReflectionClass($className);
        return $rc->newInstanceArgs($params);
    }
    
    /**
     * Adds a new class overwrite, first parameter is the class to overwrite, second parameter the new class
     * @param string $toOverwrite
     * @param string $newClass
     */
    public static function addOverwrite($toOverwrite, $newClass) {
        self::$overwrites[$toOverwrite] = $newClass;
    }
    
    /**
     * inits the internal overwrite array
     */
    protected static function initOverwrites() {
        if(self::$overwrites !== null) {
            return;
        }
        $config = Zend_Registry::get('config');
        /* @var $config Zend_Config */
        $fo = $config->factoryOverwrites;
        if(!isset($fo->models)) {
            self::$overwrites = array();
            return;
        }
        self::$overwrites = $fo->models->toArray();
    }
}