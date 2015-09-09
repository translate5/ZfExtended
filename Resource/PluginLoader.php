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

/**
 * #@+
 * 
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *         
 */
/**
 */
/**
 * Initialize all plugins wich should be loaded.
 * They are defined in Zf_configuration-list runtimeOptions.plugins.active
 */
class ZfExtended_Resource_PluginLoader extends Zend_Application_Resource_ResourceAbstract {
    public function init() {
        $config = Zend_Registry::get('config');
        if (! isset($config->runtimeOptions->plugins)) {
            return;
        }
        $pluginClasses = array_unique($config->runtimeOptions->plugins->active->toArray());
        
        foreach ($pluginClasses as $pluginClass) {
            // error_log("Plugin-Class ".$pluginClass." initialized.");
            if(!$this->isPluginOfModule($pluginClass)){
                continue;
            }
            try {
                ZfExtended_Factory::get($pluginClass);
                }
            catch (ReflectionException $exception) {
                /* @var $log ZfExtended_Log */
                error_log(__CLASS__.' -> '.__FUNCTION__.'; $exception: '. print_r($exception->getMessage(), true));
            }
        }
    }
    
    protected function isPluginOfModule($pluginClass) {
        $module = Zend_Registry::get('module');
        $isDefaultPlugin = $module === 'default' && preg_match('"^Plugins_"', $pluginClass)===1;
        $isModulePlugin = $module !== 'default' && preg_match('"^'.$module.'_"', $pluginClass)===1;
        if(!($isDefaultPlugin || $isModulePlugin)){
            return false;
        }
        return true;
    }
}