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

/**#@+
 * @author Marc Mittag
 * @package portal
 * @version 2.0
 *
 */
require_once 'Zend/Application.php';
/**
 * Standard Inhalt der index.php gekapselt
 */
class ZfExtended_Application extends Zend_Application {
    public function setPhpSettings(array $settings, $prefix = '') {
        //remove phpSettings.iconv.internal_encoding = "UTF-8" since this is deprecated in PHP >= 5.6.0
        $is560 = version_compare(PHP_VERSION, '5.6.0', '>=');
        if($is560 && isset($settings['iconv']) && isset($settings['iconv']['internal_encoding'])) {
            unset($settings['iconv']['internal_encoding']);
        }
        return parent::setPhpSettings($settings, $prefix);
    }
    
    public function run() {
        define('APPLICATION_VERSION', ZfExtended_Utils::getAppVersion());
        parent::run();
    }
}