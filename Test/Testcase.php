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

//include phar archive, if installation has been done via phar
try {
    //check if class can be included by search path, if not try phpunit.phar
    if(!class_exists('PHPUnit_Framework_TestCase', true)) {
        include_once 'phpunit.phar';
    }
} catch (Exception $exc) {
    error_log('Could not load phpunit.phar'); //On debugging only
}

abstract class ZfExtended_Test_Testcase extends \PHPUnit\Framework\TestCase {
    /**
     * @var array
     */
    public static $messages = array();
}