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
     * @template T
     * @param class-string<T> $className
     * @param array $params optional; parameters for class constructor
     * @param bool $executeContructor optional; if false no constructor is called
     * @return T|null
     * @throws ReflectionException
     */
    public static function get(string $className, array $params = array(), bool $executeContructor = true)
    {
        self::initOverwrites();
        if (isset(self::$overwrites[$className])) {
            $className = self::$overwrites[$className];
        }
        $rc = new ReflectionClass($className);
        if ($executeContructor) {
            return $rc->newInstanceArgs($params);
        }
        return $rc->newInstanceWithoutConstructor();
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