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
 * Ensures that the controllers in the lib-dirs are being loaded
 * 
 * - if a Controller of the same name in the current module exists, 
 *   this controller will be loaded instead of a libcontroller of this name
 * - if there are different libs and in 2 or more libs a controller of the same
 *   name exists, the controller of the same name in the controller-dir-list 
 *   in application.ini will be loaded
 * 
 *
 *
 *
 */
class ZfExtended_Resource_LoadLibController extends Zend_Application_Resource_ResourceAbstract {
    public function init()
    {
        $bootstrap = $this->getBootstrap();
        $bootstrap->bootstrap('frontController');
        $front = $bootstrap->getResource('frontController');
        /* @var $front Zend_Controller_Front */
        
        
        $modContrDir = $front->getModuleDirectory().DIRECTORY_SEPARATOR.
                $front->getModuleControllerDirectoryName();
        $controllerDirs = $front->getControllerDirectory();
        if(is_string($controllerDirs)){
            $controllerDirs = array($controllerDirs);
        }
        $dirs = array();
        $config = Zend_Registry::get('config');
        $libs = $config->runtimeOptions->libraries->order->toArray();
        foreach($libs as $lib){
            $needle = 'library'.DIRECTORY_SEPARATOR.$lib.DIRECTORY_SEPARATOR;
            foreach($controllerDirs as $cDir){
                if(strpos($cDir, $needle)!==false){
                     $dirs[] = $cDir;
                }
            }
        }
        foreach($dirs as $dir){
            foreach (scandir($dir) as $file) {
                if (strpos($file, "Controller.php") !== false && 
                        !class_exists(preg_replace('"(.*).php$"', '\\1', $file))){
                    if(file_exists($modContrDir.DIRECTORY_SEPARATOR.$file)){
                        include_once $modContrDir . DIRECTORY_SEPARATOR . $file;
                        continue;
                    }
                    include_once $dir . DIRECTORY_SEPARATOR . $file;
                }
            }
        }
    }
}