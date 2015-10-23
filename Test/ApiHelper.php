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
class ZfExtended_Test_ApiHelper {
    const AUTH_COOKIE_KEY = 'zfExtended';
    const SEGMENT_DUPL_SAVE_CHECK = '<img src="data:image/gif;base64,R0lGODlhAQABAID/AMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" class="duplicatesavecheck" data-segmentid="%s" data-fieldname="%s">';
    /**
     * enable xdebug debugger in eclipse
     * @var boolean
     */
    public $xdebug = false;

    /**
     * Authentication / session cookie
     * @var string
     */
    protected $authCookie;

    /**
     * Authenticated login
     * @var string
     */
    protected $authLogin;
    
    /**
     * list of files to be added to the next request
     * @var array
     */
    protected $filesToAdd = array();
    
    /**
     * @var string
     */
    protected $testClass;
    
    /**
     * @var Zend_Http_Response
     */
    protected $lastResponse;
    
    /**
     * stdObject with the values of the last imported task
     * @var stdClass
     */
    protected $task;
    
    /**
     * Test root directory
     * @var string
     */
    protected $testRoot;
    
    protected $testusers = array(
        'testmanager' => '{00000000-0000-0000-C100-CCDDEE000001}',
        'testlector' => '{00000000-0000-0000-C100-CCDDEE000002}',
        'testtranslator' => '{00000000-0000-0000-C100-CCDDEE000003}',
    );
    
    public function __construct($testClass){
        $this->testClass = $testClass;
        $this->testRoot = getcwd();
    }
    
    /**
     * @return string
     */
    public function getLogin() {
        return $this->authLogin;
    }
    
    /**
     * requests the REST API, can handle file uploads, add file methods must be called first
     * @param string $url
     * @param string $method GET;POST;PUT;DELETE must be POST or PUT to transfer added files
     * @param string $url
     * @return Zend_Http_Response
     */
    public function request($url, $method = 'GET', $parameters = array()) {
        global $T5_API_URL;
        $http = new Zend_Http_Client();
        $url = $T5_API_URL.$url;
        $http->setUri($url);
        $http->setHeaders('Accept', 'application/json');
        
        //enable xdebug debugger in eclipse
        if($this->xdebug) {
            $http->setCookie('XDEBUG_SESSION','ECLIPSE_DBGP_192.168.178.31');
            $http->setConfig(array('timeout'      => 3600));
        }
        else {
            $http->setConfig(array('timeout'      => 30));
        }
        
        if(!empty($this->authCookie)) {
            $http->setCookie(self::AUTH_COOKIE_KEY, $this->authCookie);
        }
        
        if(!empty($this->filesToAdd) && ($method == 'POST' || $method == 'PUT')) {
            foreach($this->filesToAdd as $file) {
                $abs = $this->testRoot.'/'.$file['path'];
                $t = $this->testClass;
                $t::assertFileExists($abs);
                $http->setFileUpload($abs, $file['name'], file_get_contents($abs), $file['mime']);
            }
            $this->filesToAdd = array();
        }
        
        $addParamsMethod = $method == 'POST' ? 'setParameterPost' : 'setParameterGet';
        
        if(!empty($parameters)) {
            foreach($parameters as $key => $value) {
                $http->$addParamsMethod($key, $value);
            }
        }
        
        $this->lastResponse = $http->request($method);
        return $this->lastResponse;
    }
    
    /**
     * Sends a JSON request to the application API, returns 
     *   - false on HTTP response state other than 2XX
     *   - the decoded JSON result on HTTP == 2XX
     * The raw response object is stored in lastResponse 
     * @param string $url
     * @param string $method
     * @param array $parameters
     * @return mixed a array/object structure (parsed from json) on HTTP Status 2XX, false otherwise 
     */
    public function requestJson($url, $method = 'GET', $parameters = array()) {
        if(empty($this->filesToAdd) && ($method == 'POST' || $method == 'PUT')){
            $parameters = array('data' => json_encode($parameters));
        }
        $resp = $this->request($url, $method, $parameters);
        $status = $resp->getStatus();
        
        
        if(200 <= $status && $status < 300) {
            $json = json_decode($resp->getBody());
            $t = $this->testClass;
            $t::assertEquals('No error', json_last_error_msg(), 'Server did not response valid JSON: '.$resp->getBody());
            if(isset($json->success)) {
                $t::assertEquals(true, $json->success);
            }
            return isset($json->rows) ? $json->rows : $json;
        }
        return false;
    }
    
    /**
     * returns the last requested response
     * @return Zend_Http_Response
     */
    public function getLastResponse() {
        return $this->lastResponse;
    }
    
    /**
     * Adds a file to be uploaded on the next request.
     * @param string $name
     * @param string $path
     * @param string $mimetype
     */
    public function addFile($name, $path, $mimetype) {
        $this->filesToAdd[] = array('name' => $name, 'path' => $path, 'mime' => $mimetype);
    }
    
    public function login($login, $password = 'asdfasdf') {
        if(isset($this->authLogin)){
            if($this->authLogin == $login) {
                return;
            }
            else {
                global $T5_LOGOUT_PATH;
                $this->request($T5_LOGOUT_PATH);
            }
        }
        
        $response = $this->request('editor/');
        
        $t = $this->testClass;
        $t::assertEquals(200, $response->getStatus(), 'Server did not respond HTTP 200');
        
        $cookies = $response->getHeader('Set-Cookie');
        if(!is_array($cookies)) {
            $cookies = array($cookies);
        }
        $t::assertTrue(count($cookies) > 0, 'Server did not send a Cookie.');
        
        $sessionId = null;
        foreach($cookies as $cookie) {
            if(preg_match('/'.self::AUTH_COOKIE_KEY.'=([^;]+)/', $cookie, $matches)) {
                $sessionId = $matches[1];
            }
        }
        $t::assertNotEmpty($sessionId, 'No session ID given from server as Cookie.');
        $this->authCookie = $sessionId;
        $this->authLogin = $login;
        
        $body = $response->getBody();
        $noCsrf = null;
        if(preg_match('#<input\s+type="hidden"\s+name="noCsrf"\s+value="([^"]+)"\s+id="noCsrf"\s+/>#', $body, $matches)) {
            $noCsrf = $matches[1];
        }
        $t::assertNotEmpty($noCsrf, 'No "noCsrf" key found in server response.');
        
        $response = $this->request('login/', 'POST', array(
            'noCsrf' => $noCsrf,
            'login' => $login,
            'passwd' => $password,
        ));
        if(preg_match('#<ul class="errors">(.+)</ul>#s', $response->getBody(), $matches)) {
            $t::fail('Could not login to server, message was: '.$matches[1]);
        }
    }
    
    /**
     * Imports the task described in array $task, parameters are the API parameters, at least:
     * 
        $task = array(
            'sourceLang' => 'en', // mandatory, source language in rfc5646
            'targetLang' => 'de', // mandatory, target language in rfc5646
            'relaisLang' => 'de', // optional, must be given on using relais column
            'taskName' => 'simple-en-de', //optional, defaults to __CLASS__::__TEST__
            'orderdate' => date('Y-m-d H:i:s'), //optional, defaults to now
            'targetDeliveryDate' => date('Y-m-d H:i:s'), //optional, defaults to now
            'wordCount' => 666, //optional, defaults to heavy metal
        );
     * 
     * @param array $task
     */
    public function import(array $task) {
        $this->initTaskPostData($task);
        
        $test = $this->testClass;
        $test::assertLogin('testmanager');
        
        $this->task = $this->requestJson('editor/task', 'POST', $task);
        $resp = $this->getLastResponse();
        $test::assertEquals(200, $resp->getStatus(), 'Import Request does not respond HTTP 200! Body was: '.$resp->getBody());

        //error_log(__FUNCTION__.': starting importing task '.$this->task->taskName);
        while(true){
            $taskResult = $this->requestJson('editor/task/'.$this->task->id);
            if($taskResult->state == 'open') {
                break;
            }
            sleep(5);
        }
        
    }
    
    /**
     * returns the current active task to test
     * @return stdClass
     */
    public function getTask() {
        return $this->task;
    }
    
    /**
     * adds the given user to the actual task
     * @param string $username one of the predefined users (testmanager, testlector, testtranslator)
     * @param string $state open, waiting, finished, as available by the workflow
     * @param string $role lector or translator, as available by the workflow
     */
    public function addUser($username, $state = 'open', $role = 'lector') {
        $test = $this->testClass;
        $test::assertFalse(empty($this->testusers[$username]), 'Given testuser "'.$username.'" does not exist!');
        $p = array(
                "id" => 0,
                "entityVersion" => $this->task->entityVersion,
                "taskGuid" => $this->task->taskGuid,
                "userGuid" => $this->testusers[$username],
                "state" => $state,
                "role" => $role,
        );
        $this->requestJson('editor/taskuserassoc', 'POST', $p);
        $resp = $this->getLastResponse();
        $test::assertEquals(200, $resp->getStatus(), 'User "'.$username.'" could not be added to test task '.$this->task->taskGuid.'! Body was: '.$resp->getBody());
    }
    
    /**
     * @param array $task
     */
    protected function initTaskPostData(array &$task) {
        $now = date('Y-m-d H:i:s');
        $test = $this->testClass;
        if(empty($task['taskName'])) {
            $task['taskName'] = 'API Testing::'.$test.' '.$now;
        }
        if(empty($task['orderdate'])) {
            $task['orderdate'] = $now;
        }
        if(empty($task['targetDeliveryDate'])) {
            $task['targetDeliveryDate'] = $now;
        }
        if(empty($task['wordCount'])) {
            $task['wordCount'] = 666;
        }
    }
    
    /**
     * returns a data structure ready for segment PUT, 
     * if last parameter is an ID creates the data structure, or if a data structure is given, 
     *   add the segment field with its data
     * @param string $field
     * @param string $value
     * @param mixed $idOrObject
     * @param number $duration optional, defaults to 666
     */
    public function prepareSegmentPut($field, $value, $idOrObject, $duration = 666) {
        if(is_numeric($idOrObject)) {
            $result = array(
                "autoStateId" => 999,
                "durations" => array(),
                "id" => $idOrObject,
            );
        }
        else {
            $result = $idOrObject;
        }
        $result[$field] = $value.sprintf(self::SEGMENT_DUPL_SAVE_CHECK, $idOrObject, $field);
        $result['durations'][$field] = $duration;
        return $result;
    }

    /**
     * Returns an absolute file path to a approval file
     * @param string $approvalFile
     * @param string $class
     * @return string
     */
    public function getFile($approvalFile, $class = null) {
        if(empty($class)) {
            $class = $this->testClass;
        }
        $path = join('/', array($this->testRoot, 'editorAPI', $class, $approvalFile));
        $t = $this->testClass;
        $t::assertFileExists($path);
        return $path;
    }
    
    /**
     * Loads the file contents of a file with data to be compared
     * @param string $approvalFile
     * @param string $class
     * @return string
     */
    public function getFileContent($approvalFile, $class = null) {
        $t = $this->testClass;
        $data = file_get_contents($this->getFile($approvalFile, $class));
        if(preg_match('/\.json$/i', $approvalFile)){
            $data = json_decode($data);
        }
        $t::assertEquals('No error', json_last_error_msg(), 'Test file '.$approvalFile.' does not contain valid JSON!');
        return $data;
    }
    
    /**
     * returns the untestable segment fields (like id, taskGuid etc)
     * @param stdClass $segmentContent
     * @return stdClass
     */
    public function removeUntestableSegmentContent(stdClass $segmentContent) {
        unset($segmentContent->id);
        unset($segmentContent->fileId);
        unset($segmentContent->taskGuid);
        unset($segmentContent->timestamp);
        return $segmentContent;
    }
    
    /**
     * reloads the internal stored task
     * @return stdClass
     */
    public function reloadTask() {
        return $this->task = $this->requestJson('editor/task/'.$this->task->id);
    }
    
    /**
     * returns the absolute data path to the task
     * @return string
     */
    public function getTaskDataDirectory() {
        global $T5_DATA_DIR;
        $dataPath = trim($T5_DATA_DIR, '/');
        $application = $this->testRoot.'/../../../../application/';
        return $application.$dataPath.'/'.trim($this->task->taskGuid, '{}').'/';
    }
    
    public function addImportFile($path, $mime = 'application/zip') {
        $this->addFile('importUpload', $path, $mime);
    }
}