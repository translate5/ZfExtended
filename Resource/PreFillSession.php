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
    /*
     * Gibt die Namen aller Module zurück
     *
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
    /*
     * Gibt die Namen aller libraries zurück
     *
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