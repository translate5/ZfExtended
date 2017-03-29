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
            $http->setCookie('XDEBUG_SESSION','netbeans-xdebug');
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
                global $T5_LOGOUT_PATH;
                $this->request($T5_LOGOUT_PATH);
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

        while(true){
            $taskResult = $this->requestJson('editor/task/'.$this->task->id);
            if($taskResult->state == 'open') {
                break;
            }
            sleep(5);
        }
        
    }
    
    /**
     * tests the config names and values in the given associated array against the REST accessible application config
     * @param array $configsToTest
     */
    public function testConfig(array $configsToTest) {
        $test = $this->testClass;
        foreach($configsToTest as $name => $value) {
            $config = $this->requestJson('editor/config', 'GET', array(
                'filter' => '[{"type":"string","value":"'.$name.'","property":"name","operator":"like"}]',
            ));
            $test::assertCount(1, $config);
            $test::assertEquals($value, $config[0]->value);
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
     * @param boolean $assert false to skip file existence check
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
     * 
     * @param string $pathToZip absolute file system path to zip file
     * @param string $pathToFileInZip relative path to file inside of zip
     */
    public function getFileContentFromZipPath($pathToZip,$pathToFileInZip) {
        $zip = new ZipArchive();
        $zip->open($pathToZip);
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'translate5Test'.DIRECTORY_SEPARATOR;
        $this->rmDir($dir);
        mkdir($dir);
        $zip->extractTo($dir);
        $file = $dir.$pathToFileInZip;
        $t = $this->testClass;
        $t::assertFileExists($file);
        $content = file_get_contents($file);
        $this->rmDir($dir);
        //delete exported file, so that next call can recreate it
        return $content;
    }
    /**
     * 
     * @param type $directory
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
        return preg_replace('/sdl:revid="[^"]{36}"/', 'sdl:revid="replaced-for-testing"', $changesXml);
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
     * @param $pathToTestFiles relative to testcases folder
     * @param $nameOfZipFile which is created
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