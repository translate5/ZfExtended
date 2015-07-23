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
//include phar archive, if installation has been done via phar
try {
    require_once'phpunit.phar';
} catch (Exception $exc) {
}

abstract class ZfExtended_Testcase extends \PHPUnit_Framework_TestCase {
    public static $parentTestFolderRelativePath = 'data/testcases';
    public static $parentTestFolderAbsolutePath;
    public static $testSuitePath;
    /**
     * @var editor_Models_Task
     */
    protected static $testTask;
    /**
     * @var array
     */
    public static $messages = array();
    /**
     * 
     * @param type $name
     * @param array $data
     * @param type $dataName
     */

    public static function setUpBeforeClass() {
        self::$testTask = ZfExtended_Factory::get('editor_Models_Task');
        parent::setUpBeforeClass();
    }
}