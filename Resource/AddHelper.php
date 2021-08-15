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
 * creates all helper paths
 */
class ZfExtended_Resource_AddHelper extends Zend_Application_Resource_ResourceAbstract {
    public function init()
    {
        $cache = Zend_Cache::factory('Core', new ZfExtended_Cache_MySQLMemoryBackend(), ['automatic_serialization' => true]);
        /* @var $cache Zend_Cache_Core */
        $cacheKey = 'helper_paths_'.APPLICATION_MODULE;
        $paths = $cache->load($cacheKey);
        if($paths === false) {
            $paths = $this->generatePaths();
            $cache->save($paths, $cacheKey);
        }
        foreach($paths as $prefix => $path){
            ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::addPath($path, $prefix);
        }
    }

    /**
     * Generiert die Prefix und Pfad Kombinationen
     */
    protected function generatePaths() {
        $paths = [];
        
        if(APPLICATION_MODULE === "default"){
            $modulePrefix = '';
        }
        else {
            $modulePrefix = ucfirst(APPLICATION_MODULE).'_';
        }

        $agency = ucfirst(APPLICATION_AGENCY);
        $fowBase = APPLICATION_PATH .'/factoryOverwrites/'.APPLICATION_AGENCY;
        $fowMod = $fowBase.'/modules/'.APPLICATION_MODULE;
        $modulesBase = APPLICATION_PATH .'/modules/'.APPLICATION_MODULE;
        $c_h = 'Controller_Helper_';
        $c_h_path = '/Controllers/helpers';
        $v_h = 'View_Helper_';
        $v_h_path = '/views/helpers';

        //Kunden Module Controller Helper
        $paths[$agency.'_'.$modulePrefix.$c_h] = $fowMod.$c_h_path;
        //Module Controller Helper
        $paths[$modulePrefix.$c_h] = $modulesBase.$c_h_path;
        //Kunden Module View Helper
        $paths[$agency.'_'.$modulePrefix.$v_h] = $fowMod.$v_h_path;
        //Module View Helper
        $paths[$modulePrefix.$v_h] = $modulesBase.$v_h_path;

        //Es folgen die Controller und View Helper Pfade der einzelnen Libs,
        // ebenfalls in Abhängigkeit des Kunden (Agency)
        $config = Zend_Registry::get('config');
        $libs = array_reverse($config->runtimeOptions->libraries->order->toArray());
        foreach ($libs as $lib) {
            $libPrefix = ucfirst($lib).'_';
            $paths[$agency.'_'.$libPrefix.$c_h] = $fowBase.'/library/'.$lib.$c_h_path;
            $paths[$libPrefix.$c_h] = APPLICATION_PATH .'/../library/'.$lib.$c_h_path;
            $paths[$agency.'_'.$libPrefix.$v_h] = $fowBase.'/library/'.$lib.$v_h_path;
            $paths[$libPrefix.$v_h] = APPLICATION_PATH .'/../library/'.$lib.$v_h_path;
        }
        
        return array_filter($paths, 'is_dir');
    }
}