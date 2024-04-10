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
 * Adds Module Options for integrated other Zf-Modules based on the applications.ini
 *  of the current application module
 *
 * - each module listed beneath runtimeOptions.addModuleOptions.1, runtimeOptions.addModuleOptions.2 etc. gets added
 * - options already set stay as they are and do not get overridden
 */
class ZfExtended_Controllers_Plugins_AddModuleOptions extends Zend_Controller_Plugin_Abstract
{
    public function routeShutdown(Zend_Controller_Request_Abstract $request)
    {
        $config = Zend_Registry::get('config');
        if (isset($config->runtimeOptions->addModuleOptions)) {
            $index = ZfExtended_BaseIndex::getInstance();
            foreach ($config->runtimeOptions->addModuleOptions as $module) {
                $index->addModuleOptions($module);
            }
        }
    }
}
