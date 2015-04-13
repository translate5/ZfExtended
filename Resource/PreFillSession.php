<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
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
 *
 */
/**
 * Stellt die folgenden Objekte in der Session zur Verfügung, damit sie nicht
 * bei jedem Seitenaufruf neu berechnet werden müssen
 * - runtimeOptions
 * - in der application.ini festgelegte php-Konstanten
 * - moduleNames (die Namen aller verfügbaren Module)
 * - libraryNames (die Namen aller verfügbaren libraries)
 *
 *
 */
class ZfExtended_Resource_PreFillSession extends Zend_Application_Resource_ResourceAbstract {
    public function init()
    {
        //Stelle sicher, dass ZfExtended_Resource_Session bereits ausgeführt und
        //Session somit initialisiert wurde
        $bootstrap = $this->getBootstrap();
        $bootstrap->bootstrap('ZfExtended_Resource_Session');
        $bootstrap->bootstrap('ZfExtended_Resource_InitRegistry');
        //lese runtimeoptions aus der config und speichere sie in die Session
        $session = new Zend_Session_Namespace();
        if(!isset($session->moduleNames)){
            $session->moduleNames =  $this->getModules();
        }
        if(!isset($session->libraryNames)){
            $session->libraryNames =  $this->getLibraries();
        }
    }
    
    /**
     * returns all module names
     * @return array modules
     */
    private function getModules()
    {
        $dirs = scandir(APPLICATION_PATH.'/modules');
        $modules = array();
        foreach ($dirs as $dir) {
            if(is_dir(APPLICATION_PATH .'/modules/'.$dir) and $dir !== '.' and $dir !== '..' and $dir !== '.svn'){
                $modules[] = $dir;
            }
        }
        return $modules;
    }
    
    /**
     * returns all available library names
     * @return array libraries
     */
    private function getLibraries()
    {
        $dirs = scandir(APPLICATION_PATH.'/../library');
        $libraries = array();
        foreach ($dirs as $dir) {
            if(is_dir(APPLICATION_PATH .'/../library/'.$dir) and $dir !== '.' and $dir !== '..' and $dir !== '.svn'){
                $libraries[] = $dir;
            }
        }
        return $libraries;
    }
}