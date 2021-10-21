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

class ZfExtended_Test_ApiHelper {
    
    /***
     * How many time the task status will be check while the task is importing.
     * @var integer
     */
    const RELOAD_TASK_LIMIT = 100;
    
    /***
     * How many times the language reosurces status will be checked while the resource is importing
     * @var integer
     */
    const RELOAD_RESOURCE_LIMIT = 20;
    
    /***
     * Project taskType
     * @var string
     */
    const INITIAL_TASKTYPE_PROJECT = 'project';
    
    /***
     * Project task type
     * @var string
     */
    const INITIAL_TASKTYPE_PROJECT_TASK = 'projectTask';
    
    const AUTH_COOKIE_KEY = 'zfExtended';
    const SEGMENT_DUPL_SAVE_CHECK = '<img src="data:image/gif;base64,R0lGODlhAQABAID/AMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" class="duplicatesavecheck" data-segmentid="%s" data-fieldname="%s">';
    
    /**
     * Holds internal configuration, as
     * - the api url as defined in zend config
     * - the task data dir as defined in zend config
     * - the logout url
     * - if we're in capture mode (only when single tests are called)
     * @var string
     */
    private static array $CONFIG = [
        'API_URL' => null,
        'DATA_DIR' => null,
        'LOGOUT_PATH' => null,
        'CAPTURE_MODE' => false,
        'XDEBUG_ENABLE' => false,
        'KEEP_DATA' => false,
    ];

    /**
     * Sets the Test API up. This needs to be set in the test bootstrapper
     * The given config MUST contain:
     *  'API_URL' => the api url as defined in zend config
     *  'DATA_DIR' => the task data dir as defined in zend config
     *  'LOGOUT_PATH' => the logout url
     *  'CAPTURE_MODE' => if true, defines if we're in capture mode (only when single tests are called), false by default
     *  'XDEBUG_ENABLE' => if true, defines if we should enable XDEBUG on the called test instance , false by default
     *  'KEEP_DATA' => if true, defines if test should be kept after test run, must be implemented in the test, false by default
     * @param array $config
     */
    public static function setup(array $config){
        //set the given config locally
        self::$CONFIG = array_replace(self::$CONFIG, $config);

        //fix path configs
        foreach(['API_URL', 'DATA_DIR'] as $key) {
            static::$CONFIG[$key] = rtrim(static::$CONFIG[$key], '/').'/';
        }
    }
    
    /**
     * enable xdebug debugger in IDE
     * @var boolean
     */
    public bool $xdebug = false;

    /**
     * flag to be used in the test to check if test cleanup should be done (default) or the testfiles should be kept for further investigation
     * @var boolean
     */
    public bool $cleanup = true;

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
    
    /***
     *
     * array of stdObject with the values of the last imported project tasks
     * @var array
     */
    protected $projectTasks;
    
    /**
     * Test root directory
     * @var string
     */
    protected $testRoot;
    
    /***
     * stdObject with the values of the test customer
     * @var stdClass
     */
    protected $customer;
    
    
    /***
     * Collection of language resources created from addResources method
     * @var array
     */
    protected static $resources = []; //TODO: remove from memory ?
    
    protected $testusers = array(
        'testmanager' => '{00000000-0000-0000-C100-CCDDEE000001}',
        'testlector' => '{00000000-0000-0000-C100-CCDDEE000002}',
        'testtranslator' => '{00000000-0000-0000-C100-CCDDEE000003}',
    );
    
    public function __construct($testClass){
        $this->testClass = $testClass;
        $this->testRoot = getcwd();
        $this->xdebug = self::$CONFIG['XDEBUG_ENABLE'];
        $this->cleanup = !self::$CONFIG['KEEP_DATA'];
    }
    
    /**
     * @return string
     */
    public function getLogin() {
        return $this->authLogin;
    }
    
    /**
     * @return string
     */
    public function getAuthCookie() {
        return $this->authCookie;
    }
    
    /***
     * 
     * @param string $cookie
     */
    public function setAuthCookie(string $cookie) {
        $this->authCookie = $cookie;
    }
    
    /**
     * requests the REST API, can handle file uploads, add file methods must be called first
     * @param string $url
     * @param string $method GET;POST;PUT;DELETE must be POST or PUT to transfer added files
     * @param string $url
     * @return Zend_Http_Response
     */
    public function request($url, $method = 'GET', $parameters = array()) {

        $http = new Zend_Http_Client();
        $http->setUri(static::$CONFIG['API_URL'].ltrim($url, '/'));
        $http->setHeaders('Accept', 'application/json');
        
        //enable xdebug debugger in eclipse
        if($this->xdebug) {
            $http->setCookie('XDEBUG_SESSION','ECLIPSE');
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
                if(empty($file['path']) && !empty($file['data'])){
                    $http->setFileUpload($file['filename'], $file['name'], $file['data'], $file['mime']);
                    continue;
                }
                //file paths can also be absolute:
                if(substr($file['path'], 0, 1) === '/') {
                    $abs = $file['path'];
                }
                else {
                    $abs = $this->testRoot.'/'.$file['path'];
                }
                $t = $this->testClass;
                $t::assertFileExists($abs);
                $http->setFileUpload($abs, $file['name'], file_get_contents($abs), $file['mime']);
            }
            $this->filesToAdd = array();
        }
        
        if($method == 'POST' || $method == 'PUT') {
            $addParamsMethod = 'setParameterPost';
        }
        else {
            $addParamsMethod = 'setParameterGet';
        }
        
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
     * @param array $parameters added as json in data parameter
     * @param array $additionalParameters attached as plain form parameters
     * @return mixed a array/object structure (parsed from json) on HTTP Status 2XX, false otherwise
     */
    public function requestJson(string $url, string $method = 'GET', array $parameters = [], array $additionalParameters = [], string $jsonFileName = NULL) {
        if(empty($this->filesToAdd) && ($method == 'POST' || $method == 'PUT')){
            $parameters = array('data' => json_encode($parameters));
            $parameters = array_merge($parameters, $additionalParameters);
        }
        return $this->fetchJson($url, $method, $parameters, $jsonFileName, false);
    }
    /**
     * Sends a GET request to the application API to fetch JSON data
     * @param string $url
     * @param array $parameters
     * @param string $jsonFileName
     * @return mixed|boolean
     */
    public function getJson(string $url, array $parameters = [], string $jsonFileName = NULL) {
        return $this->fetchJson($url, 'GET', $parameters, $jsonFileName, false);
    }
    /**
     * Sends a GET request to the application API to get a ExtJS type JSON tree
     * @param string $url
     * @param array $parameters
     * @return mixed a array/object structure (parsed from json) on HTTP Status 2XX, false otherwise
     */
    public function getJsonTree(string $url, array $parameters = [], string $jsonFileName = NULL) {
        return $this->fetchJson($url, 'GET', $parameters, $jsonFileName, true);
    }
    /**
     * Sends a PUT request to the application API to fetch JSON data
     * @param string $url
     * @param array $parameters
     * @param string $jsonFileName
     * @return mixed|boolean
     */
    public function putJson(string $url, array $parameters = [], string $jsonFileName = NULL) {
        if(empty($this->filesToAdd)){
            $parameters = array('data' => json_encode($parameters));
        }
        return $this->fetchJson($url, 'PUT', $parameters, $jsonFileName, false);
    }
    /**
     * Sends a POST request to the application API to fetch JSON data
     * @param string $url
     * @param array $parameters
     * @param string $jsonFileName
     * @return mixed|boolean
     */
    public function postJson(string $url, array $parameters = [], string $jsonFileName = NULL) {
        if(empty($this->filesToAdd)){
            $parameters = array('data' => json_encode($parameters));
        }
        return $this->fetchJson($url, 'POST', $parameters, $jsonFileName, false);
    }
    /**
     * Sends a GET request to the application API to fetch unencoded data
     * @param string $url
     * @param array $parameters
     * @param string $jsonFileName
     * @return string|boolean
     */
    public function getRaw(string $url, array $parameters = [], string $fileName = NULL){
        $response = $this->request($url, 'GET', $parameters);
        $status = $response->getStatus();
        if(200 <= $status && $status < 300) {
            $rawData = $response->getBody();
            if($this->isCapturing() && !empty($fileName)){
                file_put_contents($this->getFile($fileName, null, false), $rawData);
            }
            return $rawData;
        }
        return false;
    }
    /**
     * Internal API to fetch JSON Data. Automatically saves the fetched file in capture-mode
     * @param string $url
     * @param string $method
     * @param array $parameters
     * @param string $jsonFileName
     * @param bool $isTreeData
     * @return mixed|boolean
     */
    private function fetchJson(string $url, string $method = 'GET', array $parameters = [], ?string $jsonFileName, bool $isTreeData) {
        $resp = $this->request($url, $method, $parameters);
        $result = $this->decodeJsonResponse($resp, $isTreeData);
        if($result === false) {
            error_log('apiTest '.$method.' on '.$url.' returned '.$resp->__toString());
        } else if($this->isCapturing() && !empty($jsonFileName)){
            // in capturing mode we save the requested data as the data to test against
            file_put_contents($this->getFile($jsonFileName, null, false), json_encode($result, JSON_PRETTY_PRINT));
        }
        return $result;
    }
    /**
     * Decodes a returned JSON answer from Translate5 REST API
     * @param Zend_Http_Response $resp
     * @return mixed|boolean
     */
    public function decodeJsonResponse(Zend_Http_Response $resp, bool $isTreeData=false) {
        $status = $resp->getStatus();
        if(200 <= $status && $status < 300) {
            $body = $resp->getBody();
            if(empty($body)) {
                return null;
            }
            $json = json_decode($resp->getBody());
            $t = $this->testClass;
            //error_log('#'.json_last_error_msg().'#');
            //error_log('#'.$resp->getBody().'#');
            $t::assertEquals('No error', json_last_error_msg(), 'Server did not response valid JSON: '.$resp->getBody());
            if(isset($json->success)) {
                $t::assertEquals(true, $json->success);
            }            
            if($isTreeData){
                if(property_exists($json, 'children') && count($json->children) > 0){
                    return $json->children[0];
                } else {
                    $json = new stdClass();
                    $json->error = 'The fetched data had no children in the root object';
                    return $json;
                }
            } else if(property_exists($json, 'rows')){
                return $json->rows;
            } else {
                return $json;
            }
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
    
    /**
     * Adds a file to be uploaded on the next request.
     * @param string $name
     * @param string $path
     * @param string $mimetype
     * @param string $filename file name to be used
     */
    public function addFilePlain($name, $data, $mimetype, $filename) {
        $this->filesToAdd[] = array('name' => $name, 'data' => $data, 'mime' => $mimetype, 'filename' => $filename);
    }
    
    public function login($login, $password = 'asdfasdf') {
        if(isset($this->authLogin)){
            if($this->authLogin == $login) {
                return;
            }
            else {
                $this->logout();
            }
        }
        
        $response = $this->requestJson('editor/session', 'POST', [
            'login' => $login,
            'passwd' => $password,
        ]);
        
        
        $plainResponse = $this->getLastResponse();
        
        $t = $this->testClass;
        /* @var $t \ZfExtended_Test_ApiTestcase */
        $t::assertEquals(200, $plainResponse->getStatus(), 'Server did not respond HTTP 200');
        $t::assertNotFalse($response, 'JSON Login request was not successfull!');
        $t::assertRegExp('/[a-zA-Z0-9]{26}/', $response->sessionId, 'Login call does not return a valid sessionId!');

        $this->authCookie = $response->sessionId;
        $this->authLogin = $login;
    }
    
    /**
     * Makes a request to the configured logout URL
     */
    public function logout() {
        $this->request(static::$CONFIG['LOGOUT_PATH']);
        $this->authLogin = null;
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
            'wordCount' => 666, //optional, defaults to heavy metal
        );
     *
     * @param array $task
     * @param bool $failOnError default true
     * @param bool $waithForImport default true : if this is set to false, the function will not check the task import state
     * @return boolean;
     */
    public function import(array $task, $failOnError = true, $waitForImport = true): bool {
        $this->initTaskPostData($task);
        
        $test = $this->testClass;
        $test::assertLogin('testmanager');
        
        $this->task = $this->requestJson('editor/task', 'POST', $task);
        $resp = $this->getLastResponse();
        $test::assertEquals(200, $resp->getStatus(), 'Import Request does not respond HTTP 200! Body was: '.$resp->getBody());
        
        if(!$waitForImport){
            return true;
        }
        if($this->task->taskType == self::INITIAL_TASKTYPE_PROJECT){
            return $this->checkProjectTasksStateLoop($failOnError);
        }
        return $this->checkTaskStateLoop($failOnError);
    }
    
    /***
     * Check the task state. The test will fail when $failOnError = true and if the task is in state error or after RELOAD_TASK_LIMIT task state checks
     * @param bool $failOnError
     * @throws Exception
     * @return boolean
     */
    public function checkTaskStateLoop(bool $failOnError = true): bool {
        $test = $this->testClass;
        $counter=0;
        while(true){
            error_log('Task state check '.$counter.'/'.self::RELOAD_TASK_LIMIT.' state: '.$this->task->state.' ['.$test.']');
            $taskResult = $this->requestJson('editor/task/'.$this->task->id);
            if($taskResult->state == 'open') {
                $this->task = $taskResult;
                return true;
            }
            if($taskResult->state == 'unconfirmed') {
                //with task templates we could implement separate tests for that feature:
                throw new Exception("runtimeOptions.import.initialTaskState = unconfirmed is not supported at the moment!");
            }
            if($taskResult->state == 'error') {
                if($failOnError) {
                    $test::fail('Task Import stopped. Task has state error.');
                }
                return false;
            }
            
            //break after RELOAD_TASK_LIMIT reloads
            if($counter==self::RELOAD_TASK_LIMIT){
                if($failOnError) {
                    $test::fail('Task Import stopped. Task doees not have state open after '.self::RELOAD_TASK_LIMIT.' task checks.');
                }
                return false;
            }
            $counter++;
            sleep(3);
        }
    }
    
    /***
     * Check the state of all project tasks. The test will fail when $failOnError = true and if one of the project task is in state error or after RELOAD_TASK_LIMIT task state checks
     * @param bool $failOnError
     * @throws Exception
     * @return bool
     */
    public function checkProjectTasksStateLoop(bool $failOnError = true): bool {
        $test = $this->testClass;
        $counter=0;
        while(true){
            
            //reload the project
            $this->reloadProjectTasks();
            
            $toCheck = count($this->projectTasks);
            //foreach project task check the state
            foreach ($this->projectTasks as $task) {
                
                error_log('Project tasks state check '.$counter.'/'.self::RELOAD_TASK_LIMIT.', [ name:'.$task->taskName.'], [state: '.$task->state.'] ['.$test.']');
                
                if($task->state == 'open') {
                    $toCheck--;
                    continue;
                }
                if($task->state == 'unconfirmed') {
                    //with task templates we could implement separate tests for that feature:
                    throw new Exception("runtimeOptions.import.initialTaskState = unconfirmed is not supported at the moment!");
                }
                
                if($task->state == 'error') {
                    if($failOnError) {
                        $test::fail('Task Import stopped. Task has state error.');
                    }
                    return false;
                }
            }
            
            if($toCheck == 0){
                return true;
            }
            
            //break after RELOAD_TASK_LIMIT reloads
            if($counter==self::RELOAD_TASK_LIMIT){
                if($failOnError) {
                    $test::fail('Project task import stopped. After '.self::RELOAD_TASK_LIMIT.' task state checks, all of the project task are not in state open.');
                }
                return false;
            }
            $counter++;
            sleep(3);
        }
    }
    
    /***
     * Add task specific config. The config must be added after the task is created and before the import is triggered.
     * @param string $configName
     * @param string $configValue
     * @return mixed|boolean
     */
    public function addTaskImportConfig(string $taskGuid, string $configName, string $configValue){
        $this->requestJson('editor/config', 'PUT', [
            'name' => $configName,
            'value' => $configValue,
            'taskGuid'=> $taskGuid
        ]);
        $resp = $this->getLastResponse();
        $this->testClass::assertEquals(200, $resp->getStatus(), 'Import Request does not respond HTTP 200! Body was: '.$resp->getBody());
        return $this->decodeJsonResponse($resp);
    }
    
    /***
     * Load the default customer
     * @param string $user
     */
    public function loadCustomer(){
        $test = $this->testClass;
        $filter='[{"operator":"eq","value":"123456789","property":"number"}]';
        $filter=urlencode($filter);
        $url='editor/customer?page=1&start=0&limit=20&filter='.$filter;
        $customerData=$this->requestJson($url, 'GET');
        $test::assertNotEmpty($customerData,"Unable to load test customer.No test customer was found for number:123456789");
        $this->customer = $customerData[0];
        $resp = $this->getLastResponse();
        $test::assertEquals(200, $resp->getStatus(), 'Load test customer Request does not respond HTTP 200! Body was: '.$resp->getBody());
    }
    
    /***
     * Get all available langues from lek_languages table
     */
    public function getLanguages() {
        $this->requestJson('editor/language');
        $resp = $this->getLastResponse();
        $this->testClass::assertEquals(200, $resp->getStatus(), 'Import Request does not respond HTTP 200! Body was: '.$resp->getBody());
        return $this->decodeJsonResponse($resp);
    }
    
    /**
     * tests the config names and values in the given associated array against the REST accessible application config
     * If the given value to the config is null, the config value is just checked for existence and if the configured value is not empty
     * @param array $configsToTest
     * @param array $filter provide an array with several filtering guids. Key taskGuid or userGuid or customerId, value the according value
     */
    public function testConfig(array $configsToTest, array $plainFilter = []) {
        $test = $this->testClass;
        foreach($configsToTest as $name => $value) {
            $filter = array_merge([
                'filter' => '[{"type":"string","value":"'.$name.'","property":"name","operator":"like"}]',
            ], $plainFilter);
            $config = $this->requestJson('editor/config', 'GET', $filter);
            $test::assertCount(1, $config, 'No Config entry for config "'.$name.'" found in instance config!');
            if(is_null($value)) {
                $test::assertNotEmpty($config[0]->value, 'Config '.$name.' in instance is empty but should be set with a value!');
            }
            else {
                $test::assertEquals($value, $config[0]->value, 'Config '.$name.' in instance config is not as expected: ');
            }
        }
    }
    
    /**
     * adds the given user to the actual task
     * @param string $username one of the predefined users (testmanager, testlector, testtranslator)
     * @param string $state open, waiting, finished, as available by the workflow
     * @param string $role reviewer or translator, as available by the workflow
     * @param array $params add additional taskuserassoc params to the add user call
     *
     * @return stdClass taskuserassoc result
     */
    public function addUser($username, $state = 'open', $role = 'reviewer',array $params=[]) {
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
        $p=array_merge($p,$params);
        $json = $this->requestJson('editor/taskuserassoc', 'POST', $p);
        $resp = $this->getLastResponse();
        $test::assertEquals(200, $resp->getStatus(), 'User "'.$username.'" could not be added to test task '.$this->task->taskGuid.'! Body was: '.$resp->getBody());
        return $json;
    }
    
    /***
     * Create new language resource
     *
     * @param array $params: api params
     * @param string $fileName: the resource upload file name
     * @param bool $waitForImport: wait until the resource is imported
     * @return mixed|boolean
     */
    public function addResource(array $params ,string $fileName = null, bool $waitForImport=false){
        
        $test = $this->testClass;
        //if filename is provided, set the file upload field
        if($fileName){
            $this->addFile('tmUpload', $this->getFile($fileName), "application/xml");
            $resource = $this->requestJson('editor/languageresourceinstance', 'POST',$params);
        }else{
            //request because the requestJson will encode the params with "data" as parent
            $response =$this->request('editor/languageresourceinstance', 'POST',$params);
            $resource = $this->decodeJsonResponse($response);
        }
        $test::assertTrue(is_object($resource), 'Unable to create the language resource:'.$params['name']);
        $test::assertEquals($params['name'], $resource->name);
        
        //collect the created resource
        self::$resources[]=$resource;
        
        error_log("Language resources created. ".$resource->name);
        
        $resp = $this->requestJson('editor/languageresourceinstance/'.$resource->id, 'GET',[]);
        
        if(!$waitForImport){
            return $resp;
        }
        error_log('Languageresources status check:'.$resp->status);
        $counter=0;
        while ($resp->status!='available'){
            if($resp->status=='error'){
                break;
            }
            //break after RELOAD_RESOURCE_LIMIT trys
            if($counter==self::RELOAD_RESOURCE_LIMIT){
                break;
            }
            sleep(5);
            $resp = $this->requestJson('editor/languageresourceinstance/'.$resp->id, 'GET',[]);
            error_log('Languageresources status check '.$counter.'/'.self::RELOAD_RESOURCE_LIMIT.' state: '.$resp->status);
            $counter++;
        }
        
        $test::assertEquals('available',$resp->status,'Resource import stoped. Resource state is:'.$resp->status);
        return $resp;
    }
    
    /***
     *
     * @param array $params
     * @param string $filename
     */
    public function addTermCollection(array $params,string $filename=null) {
        //create the language resource
        $collection = $this->addResource($params,$filename);

        //validate the results
        $response=$this->requestJson('editor/termcollection/export', 'POST',['collectionId' =>$collection->id]);
        $this->assertTrue(is_object($response),"Unable to export the terms by term collection");
        $this->assertNotEmpty($response->filedata,"The exported tbx file by collection is empty");
        error_log("Termcollection created. ".$collection->name);
    }
    
    
    /***
     * Associate all $resources to the current task
     */
    public function addTaskAssoc(){
        $taskGuid = $this->getTask()->taskGuid;
        $test = $this->testClass;
        $test::assertNotEmpty($taskGuid,'Unable to associate resources to task. taskGuid empty');
        
        foreach ($this->getResources() as $resource){
            // associate languageresource to task
            $this->requestJson('editor/languageresourcetaskassoc', 'POST',[
                'languageResourceId'=>$resource->id,
                'taskGuid'=>$taskGuid,
                'segmentsUpdateable' => 0
            ]);
            error_log('Languageresources assoc to task. '.$resource->name.' -> '.$taskGuid);
        }
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
        if(!isset($task['wordCount'])) {
            $task['wordCount'] = 666;
        }
        //currently all test tasks are started automatically, no test of the /editor/task/ID/import URL is implemented!
        if(!isset($task['autoStartImport'])) {
            $task['autoStartImport'] = 1;
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
     * @return array
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
     * @param string $class The directory name in editorAPI where the testfiles are
     * @param bool $assert false to skip file existence check
     * @return string
     */
    public function getFile($approvalFile, $class = null, $assert = true) {
        if(empty($class)) {
            $class = $this->testClass;
        }
        $path = join('/', array($this->testRoot, 'editorAPI', $class, $approvalFile));
        if($assert) {
            $t = $this->testClass;
            $t::assertFileExists($path);
        }
        return $path;
    }
    
    /**
     * Loads the file contents of a file with data to be compared
     * @param string $approvalFile
     * @param string $rawDataToCapture
     * @return string
     */
    public function getFileContent($approvalFile, $rawDataToCapture=null) {
        if($this->isCapturing() && $rawDataToCapture != null){
            file_put_contents($this->getFile($approvalFile, null, false), $rawDataToCapture);
        }
        $t = $this->testClass;
        $data = file_get_contents($this->getFile($approvalFile));
        if(preg_match('/\.json$/i', $approvalFile)){
            $data = json_decode($data);
            $t::assertEquals('No error', json_last_error_msg(), 'Test file '.$approvalFile.' does not contain valid JSON!');
        }
        return $data;
    }
    
    /**
     *
     * @param string $zipfile absolute file system path to zip file
     * @param string $pathToFileInZip relative path to file inside of zip
     */
    public function getFileContentFromZip($zipfile,$pathToFileInZip) {
        $pathToZip = $this->getFile($zipfile);
        return $this->getFileContentFromZipPath($pathToZip, $pathToFileInZip);
    }
    
    /**
     * returns the content of the given filename in a given ZIP, in filename * and ? may be used. If it mathces multiple files the first one is returned.
     * @param string $pathToZip absolute file system path to zip file
     * @param string $pathToFileInZip relative path to file inside of zip (uses glob to evaluate * ? etc pp. returns the first file if matched multiple files!)
     */
    public function getFileContentFromZipPath($pathToZip,$pathToFileInZip) {
        $zip = new ZipArchive();
        $zip->open($pathToZip);
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'translate5Test'.DIRECTORY_SEPARATOR;
        $this->rmDir($dir);
        mkdir($dir);
        $zip->extractTo($dir);
        $files = glob($dir.$pathToFileInZip, GLOB_NOCHECK);
        $file = reset($files);
        $t = $this->testClass;
        $t::assertFileExists($file);
        $content = file_get_contents($file);
        $this->rmDir($dir);
        //delete exported file, so that next call can recreate it
        return $content;
    }
    /**
     *
     * @param string $directory
     * @return boolean false if directory did not exist
     * @throws Exception if directory is a file
     */
    public function rmDir($directory) {
        if(!is_dir($directory)){
            if(is_file($directory)){
                throw new Exception($directory.' is a file.');
            }
            return false;
        }
        $iterator = new DirectoryIterator($directory);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDot()) {
                continue;
            }
            if ($fileinfo->isDir()) {
                $this->rmDir($directory . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
            }
            if ($fileinfo->isFile()) {
                try {
                    unlink($directory . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
                }
                catch (Exception $e){
                       
                }
            }
        }
        //FIXME try catch ist nur eine übergangslösung!!!
        try {
            rmdir($directory);
        }
        catch (Exception $e){

        }
        return true;
    }
    
    /**
     * removes random revIds from the given XML string of changes.xml files
     * @param string $changesXml
     * @return string
     */
    public function replaceChangesXmlContent($changesXml) {
        $guid = htmlspecialchars($this->task->taskGuid);
        $changesXml = str_replace(' translate5:taskguid="'.$guid.'"', ' translate5:taskguid="TASKGUID"', $changesXml);
        return preg_replace('/sdl:revid="[^"]{36}"/', 'sdl:revid="replaced-for-testing"', $changesXml);
    }
    
    /**
     * reloads the internal stored task
     * @return stdClass
     */
    public function reloadTask(int $id = null) {
        return $this->task = $this->requestJson('editor/task/'.($id ?? $this->task->id));
    }
    
    /***
     * Reload the tasks of the current project
     * @return mixed|boolean
     */
    public function reloadProjectTasks() {
        return $this->projectTasks = $this->requestJson('editor/task/', 'GET',[
            'filter' => '[{"operator":"eq","value":"'.$this->task->projectId.'","property":"projectId"}]',
        ]);
    }
    
    /**
     * returns the absolute data path to the task
     * @return string
     */
    public function getTaskDataDirectory() {
        return static::$CONFIG['DATA_DIR'].trim($this->task->taskGuid, '{}').'/';
    }
    
    public function addImportFile($path, $mime = 'application/zip') {
        $this->addFile('importUpload', $path, $mime);
    }
    
    public function addImportTbx($path, $mime = 'application/xml') {
        $this->addFile('importTbx', $path, $mime);
    }
    
    /**
     * Adds directly data to be imported instead of providing a filepath
     * useful for creating CSV testdata direct in testcase
     *
     * @param string $data
     * @param string $mime
     */
    public function addImportPlain($data, $mime = 'application/csv', $filename = 'apiTest.csv') {
        $this->addFilePlain('importUpload', $data, $mime, $filename);
    }
    
    /**
     * Receives a two dimensional array and add it as a CSV file to the task
     * MID col and CSV head line is added automatically
     *
     * multiple targets currently not supported!
     *
     * @param array $data
     */
    public function addImportArray(array $data) {
        $i = 1;
        $data = array_map(function($row) use (&$i){
            $row = array_map(function($cell){
                //escape " chars
                return str_replace('"', '""', $cell);
            },$row);
            array_unshift($row, $i++); //add mid
            return '"'.join('","', $row).'"';
        }, $data);
        array_unshift($data, '"id", "source", "target"');
        $this->addImportPlain(join("\n", $data));
    }
    
    /**
     * creates zipfile with testfiles in tmpDir and returns the path to it
     * @param string $pathToTestFiles relative to testcases folder
     * @param string $nameOfZipFile which is created
     * @return string path to zipfile
     * @throws Zend_Exception
     */
    public function zipTestFiles($pathToTestFiles, $nameOfZipFile) {
        $dir = $this->getFile($pathToTestFiles);
        $zipFile = $this->getFile($nameOfZipFile, null, false);
        
        if(file_exists($zipFile)) {
            unlink($zipFile);
        }
        
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE)!==true) {
            throw new Zend_Exception('Could not create zip.');
        }
        // create recursive directory iterator
        $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                        $dir, RecursiveDirectoryIterator::SKIP_DOTS
                ), RecursiveIteratorIterator::LEAVES_ONLY);

        // let's iterate
        foreach ($files as $name => $file) {
            $filePath = $file->getRealPath();
            $zip->addFile($file, str_replace('^'.$dir, '', '^'.$filePath));
        }
        
        $zip->close();
        
        return $zipFile;
    }
    
    /***
     * Remove all resources from the database
     */
    public function removeResources() {
        foreach ($this->getResources() as $resource){
            $route = 'editor/languageresourceinstance/'.$resource->id;
            if($resource->serviceName == 'TermCollection'){
                $route = 'editor/termcollection/'.$resource->id;
            }
            $this->requestJson($route,'DELETE');
        }
    }
    
    /**
     * returns the current active task to test
     * @return stdClass
     */
    public function getTask() {
        return $this->task;
    }
    
    /***
     * return the test customer
     * @return stdClass
     */
    public function getCustomer(){
        return $this->customer;
    }
    
    /***
     * Get the created language resources
     */
    public function getResources() {
        return self::$resources;
    }
    
    /***
     *
     * @return array|mixed|boolean
     */
    public function getProjectTasks() {
        return $this->projectTasks;
    }
    
    /**
     * returns a XML string as formatted XML
     * @param string $xml
     * @return string
     */
    public function formatXml(string $xml): string {
        $xmlDoc = new DOMDocument();
        $xmlDoc->preserveWhiteSpace = false;
        $xmlDoc->formatOutput = true;
        $xmlDoc->loadXML($xml);
        return $xmlDoc->saveXML();
    }
    /**
     * Retrieves, if the test is running in capturing mode, e.g. saving the fetched data as static data to compare againts (only for single tests)
     * @return bool
     */
    public function isCapturing() : bool {
        return static::$CONFIG['CAPTURE_MODE'];
    }
}