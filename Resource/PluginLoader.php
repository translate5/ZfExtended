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

/**
 * #@+
 *
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 */

/**
 * Initialize all plugins wich should be loaded.
 * They are defined in Zf_configuration-list runtimeOptions.plugins.active
 */
class ZfExtended_Resource_PluginLoader extends Zend_Application_Resource_ResourceAbstract
{
    public function init()
    {
        $pluginmanager = ZfExtended_Factory::get('ZfExtended_Plugin_Manager');
        /* @var $pluginmanager ZfExtended_Plugin_Manager */
        $pluginmanager->bootstrap();
        Zend_Registry::set('PluginManager', $pluginmanager);
    }
}
