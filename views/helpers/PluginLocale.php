<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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
 */
class ZfExtended_View_Helper_PluginLocale extends Zend_View_Helper_Abstract{
    /**
     * @var array
     */
    protected $paths = array();
    /**
     * @var ZfExtended_Plugin_Abstract
     */
    protected $plugin;
    
    /**
     * The here added phtmls are rendered at the end of localizedjsstrings.phtml
     * The path must be relative to the plugin root!
     * 
     * @param ZfExtended_Plugin_Abstract $plugin
     * @param string $localePhtmlPath
     */
    public function add(ZfExtended_Plugin_Abstract $plugin, $localePhtmlPath) {
        $basePath = $plugin->getPluginPath();
        $this->paths[] = APPLICATION_PATH.DIRECTORY_SEPARATOR.$basePath.DIRECTORY_SEPARATOR.$localePhtmlPath;
    }
    
    /**
     * Helper call
     * @return ZfExtended_View_Helper_PluginLocale
     */
    public function pluginLocale(){
        return $this;
    }

    /**
     * renders the stored
     * @return array
     */
    public function getLocalePaths() {
        return $this->paths;
    }
}
