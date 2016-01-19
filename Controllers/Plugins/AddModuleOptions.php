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
 * 
 */

/**
 * Adds Module Options for integrated other Zf-Modules based on the applications.ini
 *  of the current application module
 * 
 * - each module listed beneath runtimeOptions.addModuleOptions.1, runtimeOptions.addModuleOptions.2 etc. gets added
 * - options already set stay as they are and do not get overridden
 *
 */
class ZfExtended_Controllers_Plugins_AddModuleOptions extends Zend_Controller_Plugin_Abstract {


    /**
     * 
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function RouteShutdown(Zend_Controller_Request_Abstract $request) {
        $config = Zend_Registry::get('config');
        if(isset($config->runtimeOptions->addModuleOptions)){
            $index = ZfExtended_BaseIndex::getInstance();
            foreach($config->runtimeOptions->addModuleOptions as $module){
                $index->addModuleOptions($module);
            }
        }
    }
}
