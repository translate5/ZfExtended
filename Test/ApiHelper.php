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
    
    /***
     * stdObject with the values of the test customer
     * @var stdClass
     */
    protected $customer;
    
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
     * @return string
     */
    public function getAuthCookie() {
        return $this->authCookie;
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
    public function requestJson($url, $method = 'GET', $parameters = [], $additionalParameters = []) {
        if(empty($this->filesToAdd) && ($method == 'POST' || $method == 'PUT')){
            $parameters = array('data' => json_encode($parameters));
            $parameters = array_merge($parameters, $additionalParameters);
        }
        return $this->decodeJsonResponse($this->request($url, $method, $parameters));
    }
    
    /**
     * Decodes a returned JSON answer from Translate5 REST API 
     * @param Zend_Http_Response $resp
     * @return mixed|boolean
     */
    public function decodeJsonResponse(Zend_Http_Response $resp) {
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
            return $json->rows ?? $json;
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
        global $T5_LOGOUT_PATH;
        $this->request($T5_LOGOUT_PATH);
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
    public function import(array $task, $failOnError = true,$waithForImport=true) {
        $this->initTaskPostData($task);
        
        $test = $this->testClass;
        $test::assertLogin('testmanager');
        
        $this->task = $this->requestJson('editor/task', 'POST', $task);
        $resp = $this->getLastResponse();
        $test::assertEquals(200, $resp->getStatus(), 'Import Request does not respond HTTP 200! Body was: '.$resp->getBody());

        while($waithForImport){
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
            sleep(5);
        }
        
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
    
    /**
     * tests the config names and values in the given associated array against the REST accessible application config
     * If the given value to the config is null, the config value is just checked for existence and if the configured value is not empty 
     * @param array $configsToTest
     */
    public function testConfig(array $configsToTest) {
        $test = $this->testClass;
        foreach($configsToTest as $name => $value) {
            $config = $this->requestJson('editor/config', 'GET', array(
                'filter' => '[{"type":"string","value":"'.$name.'","property":"name","operator":"like"}]',
            ));
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
        if(empty($task['wordCount'])) {
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
     * @param string $class
     * @return string
     */
    public function getFileContent($approvalFile, $class = null) {
        $t = $this->testClass;
        $data = file_get_contents($this->getFile($approvalFile, $class));
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
     * returns the untestable segment fields (like id, taskGuid etc)
     * @param stdClass $segmentContent
     * @param boolean $keepId optional, true to keep segment ID
     * @return stdClass
     */
    public function removeUntestableSegmentContent(stdClass $segmentContent, $keepId = false) {
        if(!$keepId) {
            unset($segmentContent->id);
        }
        unset($segmentContent->fileId);
        unset($segmentContent->taskGuid);
        unset($segmentContent->timestamp);
        if(isset($segmentContent->metaCache)) {
            $meta = json_decode($segmentContent->metaCache, true);
            if(!empty($meta['siblingData'])) {
                $data = [];
                foreach($meta['siblingData'] as $sibling) {
                    $data['fakeSegId_'.$sibling['nr']] = $sibling;
                }
                ksort($data);
                $meta['siblingData'] = $data;
            }
            $segmentContent->metaCache = json_encode($meta, JSON_FORCE_OBJECT);
        }
        
        if(!empty($segmentContent->comments)) {
            $segmentContent->comments = preg_replace('/<span class="modified">[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}</', '<span class="modified">NOT_TESTABLE<', $segmentContent->comments);
        }
        $segmentContent->targetEdit = preg_replace('/data-usertrackingid="[0-9]+"/', 'data-usertrackingid="NOT_TESTABLE"', $segmentContent->targetEdit);
        $segmentContent->targetEdit = preg_replace('/data-timestamp="[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\+[0-9]{2}:[0-9]{2}"/', 'data-timestamp="NOT_TESTABLE"', $segmentContent->targetEdit);
        $segmentContent->source = preg_replace('/data-tbxid="term_[0-9]+"/', 'data-tbxid="term_NOT_TESTABLE"', $segmentContent->source);
        if(property_exists($segmentContent, 'sourceEdit')) {
            $segmentContent->sourceEdit = preg_replace('/data-tbxid="term_[0-9]+"/', 'data-tbxid="term_NOT_TESTABLE"', $segmentContent->sourceEdit);
        }
        $segmentContent->target = preg_replace('/data-tbxid="term_[0-9]+"/', 'data-tbxid="term_NOT_TESTABLE"', $segmentContent->target);
        $segmentContent->targetEdit = preg_replace('/data-tbxid="term_[0-9]+"/', 'data-tbxid="term_NOT_TESTABLE"', $segmentContent->targetEdit);
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
    
    public function addImportTbx($path, $mime = 'application/xml') {
        $this->addFile('importTbx', $path, $mime);
    }
    
    /**
     * Adds the given XML file as task-template, using the importTbx way, since this works out of the box
     * @param string $path
     */
    public function addImportTaskTemplate($path) {
        $data = file_get_contents($this->testRoot.'/'.$path);
        $this->addFilePlain('importTbx', $data, 'application/xml', 'task-template.xml');
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
        array_unshift($data, '"mid", "quelle", "ziel"');
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
}