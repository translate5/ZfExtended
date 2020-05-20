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

abstract class ZfExtended_Test_ApiTestcase extends \ZfExtended_Test_Testcase {
    /**
     * @var ZfExtended_Test_ApiHelper
     */
    protected static $api;
    
    /**
     * @return ZfExtended_Test_ApiHelper
     */
    public function api() {
        return self::$api;
    }
    
    /**
     * Asserts that the configured termtagger(s) is(are) running.
     * @return stdClass returns the application state object
     */
    public static function assertTermTagger() {
        $state = self::assertAppState();
        self::assertFalse(empty($state->termtagger), 'Termtagger Plugin not active!');
        self::assertTrue($state->termtagger->runningAll, 'Some configured termtaggers are not running: '.print_r($state->termtagger->running,1));
        return $state;
    }
    
    /**
     * Asserts that the application state could be loaded
     * @return mixed|boolean
     */
    public static function assertAppState() {
        self::$api->login('testapiuser', 'asdfasdf');
        self::assertLogin('testapiuser');
        $state = self::$api->requestJson('editor/index/applicationstate');
        self::assertTrue(is_object($state), 'Application state data is no object!');
        //other system checks
        self::assertEquals(0, $state->worker->scheduled, 'For API testing no scheduled workers are allowed in DB!');
        self::assertEquals(0, $state->worker->waiting, 'For API testing no waiting workers are allowed in DB!');
        self::assertEquals(0, $state->worker->running, 'For API testing no running workers are allowed in DB!');
        if(!$state->database->isUptodate) {
            die('Database is not up to date! '.$state->database->newCount.' new / '.$state->database->modCount.' modified.'."\n\n");
        }
        return $state;
    }
    
    /**
     * asserts that a certain user is loggedin
     * @param string $user
     * @return stdClass the login/status JSON for further processing
     */
    public static function assertLogin($user) {
        $json = self::$api->requestJson('editor/session/'.self::$api->getAuthCookie());
        
        self::assertTrue(is_object($json), 'User "'.$user.'" is not authenticated!');
        self::assertEquals('authenticated', $json->state, 'User "'.$user.'" is not authenticated!');
        self::assertEquals($user, $json->user->login);
        return $json;
    }
    
    /**
     * Asserts that a default set of test users is available (provided by testdata.sql not imported by install-and-update kit!)
     */
    public static function assertNeededUsers() {
        self::$api->login('testlector', 'asdfasdf');
        $json = self::assertLogin('testlector');
        self::assertContains('editor', $json->user->roles, 'Checking users roles:');
        self::assertNotContains('pm', $json->user->roles, 'Checking users roles:');
        self::assertContains('basic', $json->user->roles, 'Checking users roles:');
        self::assertContains('noRights', $json->user->roles, 'Checking users roles:');
        
        self::$api->login('testtranslator', 'asdfasdf');
        $json = self::assertLogin('testtranslator');
        self::assertContains('editor', $json->user->roles, 'Checking users roles:');
        self::assertNotContains('pm', $json->user->roles, 'Checking users roles:');
        self::assertContains('basic', $json->user->roles, 'Checking users roles:');
        self::assertContains('noRights', $json->user->roles, 'Checking users roles:');
        
        
        self::$api->login('testtermproposer', 'asdfasdf');
        $json = self::assertLogin('testtermproposer');
        self::assertContains('termProposer', $json->user->roles, 'Checking users roles:');
        self::assertContains('editor', $json->user->roles, 'Checking users roles:');
        self::assertContains('pm', $json->user->roles, 'Checking users roles:');
        self::assertContains('basic', $json->user->roles, 'Checking users roles:');
        self::assertContains('noRights', $json->user->roles, 'Checking users roles:');
        
        self::$api->login('testmanager', 'asdfasdf');
        $json = self::assertLogin('testmanager');
        self::assertContains('editor', $json->user->roles, 'Checking users roles:');
        self::assertContains('pm', $json->user->roles, 'Checking users roles:');
        self::assertContains('basic', $json->user->roles, 'Checking users roles:');
        self::assertContains('noRights', $json->user->roles, 'Checking users roles:');
    }
    
    /***
     * Asserts that a default customer is loaded
     */
    public static function assertCustomer(){
        self::$api->loadCustomer();
    }
    
    /**
     * compares the given segment content to the content in the given assert file
     * @param string $fileToCompare
     * @param stdClass $segment
     */
    public static function assertSegmentContentToFile($fileToCompare, stdClass $segment, $msg = '') {
        $segmentToTest = self::$api->removeUntestableSegmentContent($segment);
        self::assertEquals(self::$api->getFileContent($fileToCompare), $segmentToTest, $msg);
    }
}