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
 * Ensures that the controllers in the lib-dirs are being loaded
 *
 * - if a Controller of the same name in the current module exists,
 *   this controller will be loaded instead of a libcontroller of this name
 * - if there are different libs and in 2 or more libs a controller of the same
 *   name exists, the controller of the same name in the controller-dir-list
 *   in application.ini will be loaded
 */
class ZfExtended_Resource_LoadLibController extends Zend_Application_Resource_ResourceAbstract
{
    public function init()
    {
        $bootstrap = $this->getBootstrap();
        $bootstrap->bootstrap('frontController');
        $front = $bootstrap->getResource('frontController');
        /* @var $front Zend_Controller_Front */

        $controllerDirs = $front->getControllerDirectory();
        if (is_string($controllerDirs)) {
            $controllerDirs = [$controllerDirs];
        }
        $dirs = [];
        $config = Zend_Registry::get('config');
        $libs = $config->runtimeOptions->libraries->order->toArray();
        foreach ($libs as $lib) {
            $needle = 'library' . DIRECTORY_SEPARATOR . $lib . DIRECTORY_SEPARATOR;
            foreach ($controllerDirs as $cDir) {
                $cDir = (DIRECTORY_SEPARATOR !== '/') ? str_replace('/', DIRECTORY_SEPARATOR, $cDir) : $cDir; //to ensure windows path compatiblity
                if (strpos($cDir, $needle) !== false) {
                    $dirs[] = $cDir;
                }
            }
        }
        foreach ($dirs as $dir) {
            foreach (scandir($dir) as $file) {
                if (strpos($file . '#', "Controller.php#") !== false &&
                        ! class_exists(preg_replace('"(.*).php$"', '\\1', $file), false)) {
                    include_once $dir . DIRECTORY_SEPARATOR . $file;
                }
            }
        }
    }
}
