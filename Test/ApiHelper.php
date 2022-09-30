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

    const PASSWORD = 'asdfasdf';

    const AUTH_COOKIE_KEY = 'zfExtended';

    /**
     * Holds internal configuration, as
     * - the api url as defined in zend config
     * - the task data dir as defined in zend config
     * - the logout url
     * - if we're in capture mode (only when single tests are called)
     * @var array
     */
    protected static array $CONFIG = [
        'API_URL' => null,
        'DATA_DIR' => null,
        'LOGOUT_PATH' => null,
        'CAPTURE_MODE' => false,
        'XDEBUG_ENABLE' => false,
        'KEEP_DATA' => false,
        'LEGACY_DATA' => false,
        'LEGACY_JSON' => false,
    ];

    /**
     * Holds the currently authenticated user login
     * This prop will be tracked over the whole testsuite avoiding needless logins/logouts
     * @var string|null
     */
    private static ?string $authLogin = null;

    /**
     * Holds the currently authenticated user cookie
     * @var string|null
     */
    private static ?string $authCookie = null;

    /**
     * Sets the Test API up. This needs to be set in the test bootstrapper
     * The given config MUST contain:
     *  'API_URL' => the api url as defined in zend config
     *  'DATA_DIR' => the task data dir as defined in zend config
     *  'LOGOUT_PATH' => the logout url
     *  'CAPTURE_MODE' => if true, defines if we're in capture mode (only when single tests are called), false by default
     *  'XDEBUG_ENABLE' => if true, defines if we should enable XDEBUG on the called test instance , false by default
     *  'KEEP_DATA' => if true, defines if test should be kept after test run, must be implemented in the test, false by default
     *  'LEGACY_DATA' => if true, defines to use the "old" data field sort order (to reduce diff clutter on capturing)
     *  'LEGACY_JSON' => if true, defines to use the "old" json encoding config (to reduce diff clutter on capturing)
     * @param array $config
     */
    public static function setup(array $config){
        //set the given config locally
        static::$CONFIG = array_replace(static::$CONFIG, $config);

        //fix path configs
        foreach(['API_URL', 'DATA_DIR'] as $key) {
            static::$CONFIG[$key] = rtrim(static::$CONFIG[$key], '/').'/';
        }
    }

    /**
     * Retrieves, if the test is running with legacy-data (data without JSON_UNESCAPED_UNICODE)
     * @return bool
     */
    public static function isLegacyData() : bool {
        return static::$CONFIG['LEGACY_DATA'];
    }

    /**
     * @return string|null
     */
    public static function getAuthLogin() : string {
        return static::$authLogin;
    }

    /**
     * @return string
     */
    public static function getAuthCookie() : string {
        return static::$authCookie;
    }

    /***
     *
     * @param string $cookie
     */
    public static function setAuthCookie(string $cookie) {
        static::$authCookie = $cookie;
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
    protected bool $cleanup = true;

    /**
     * list of files to be added to the next request
     * @var array
     */
    protected array $filesToAdd = [];
    
    /**
     * @var string
     */
    protected string $testClass;
    
    /**
     * @var Zend_Http_Response
     */
    protected Zend_Http_Response $lastResponse;

    /**
     * Test root directory
     * @var string
     */
    protected string $testRoot;

    /**
     * @throws ReflectionException
     */
    public function __construct($testClass){
        $reflector = new ReflectionClass($testClass);
        $this->testClass = $testClass;
        $this->testRoot = dirname($reflector->getFileName());
        $this->xdebug = static::$CONFIG['XDEBUG_ENABLE'];
        $this->cleanup = !static::$CONFIG['KEEP_DATA'];
    }

    /**
     * Sends a simple GET request
     * @param string $url
     * @param array $parameters
     * @return Zend_Http_Response
     * @throws Zend_Http_Client_Exception
     */
    public function get(string $url, array $parameters = []) {
        return $this->request($url, 'GET', $parameters);
    }

    /**
     * Sends a simple PUT request
     * @param string $url
     * @param array $parameters
     * @return Zend_Http_Response
     * @throws Zend_Http_Client_Exception
     */
    public function put(string $url, array $parameters = []) {
        return $this->request($url, 'PUT', $parameters);
    }

    /**
     * Sends a simple POST request
     * @param string $url
     * @param array $parameters
     * @return Zend_Http_Response
     * @throws Zend_Http_Client_Exception
     */
    public function post(string $url, array $parameters = []) {
        return $this->request($url, 'POST', $parameters);
    }

    /**
     * Sends a DELETE request
     * @param string $url
     * @param array $parameters
     * @return bool|mixed
     */
    public function delete(string $url, array $parameters = []) {
        if($this->cleanup){
            return $this->fetchJson($url, 'DELETE', $parameters, null, false);
        }
        return false;
    }

    /**
     * Sends a GET request to the application API to fetch JSON data
     * @param string $url
     * @param array $parameters
     * @param string|null $jsonFileName
     * @return mixed|boolean
     */
    public function getJson(string $url, array $parameters = [], string $jsonFileName = NULL) {
        return $this->fetchJson($url, 'GET', $parameters, $jsonFileName, false);
    }

    /**
     * Sends a GET request to the application API to get a ExtJS type JSON tree
     * @param string $url
     * @param array $parameters
     * @param string|null $jsonFileName
     * @return mixed|bool
     */
    public function getJsonTree(string $url, array $parameters = [], string $jsonFileName = NULL) {
        return $this->fetchJson($url, 'GET', $parameters, $jsonFileName, true);
    }

    /**
     * Sends a PUT request to the application API to fetch JSON data
     * @param string $url
     * @param array $parameters: will be sent json-encoded as "data"-param if no files added
     * @param string|null $jsonFileName
     * @param bool $encodeParamsAsData
     * @return mixed|boolean
     */
    public function putJson(string $url, array $parameters = [], string $jsonFileName = NULL, bool $encodeParamsAsData = true) {
        if(empty($this->filesToAdd) && $encodeParamsAsData){
            $parameters = array('data' => json_encode($parameters));
        }
        return $this->fetchJson($url, 'PUT', $parameters, $jsonFileName, false);
    }

    /**
     * Sends a POST request to the application API to fetch JSON data
     * @param string $url
     * @param array $parameters: will be sent json-encoded as "data"-param if no files added
     * @param string|null $jsonFileName
     * @param bool $encodeParamsAsData
     * @return mixed|boolean
     */
    public function postJson(string $url, array $parameters = [], string $jsonFileName = NULL, bool $encodeParamsAsData = true) {
        if(empty($this->filesToAdd) && $encodeParamsAsData){
            $parameters = array('data' => json_encode($parameters));
        }
        return $this->fetchJson($url, 'POST', $parameters, $jsonFileName, false);
    }

    /**
     * Posts raw content (not form-encoded, not as form-data)
     * @param string $url
     * @param string $content
     * @param array $parameters
     * @return stdClass
     * @throws Zend_Http_Client_Exception
     */
    public function postRaw(string $url, string $content, array $parameters=[]) {
        $http = new Zend_Http_Client();
        $http->setUri(static::$CONFIG['API_URL'].ltrim($url, '/'));
        $http->setHeaders('Accept', 'application/json');
        if(static::$authCookie !== null) {
            $http->setCookie(static::AUTH_COOKIE_KEY, static::$authCookie);
        }
        $http->setRawData($content, 'application/octet-stream');
        $http->setHeaders(Zend_Http_Client::CONTENT_TYPE, 'application/octet-stream');
        if(!empty($parameters)) {
            foreach($parameters as $key => $value) {
                $http->setParameterGet($key, $value); // when setting the raw request-body params can only be set as GET params!
            }
        }
        $this->lastResponse = $http->request('POST');
        return $this->decodeRawResponse($this->lastResponse);
    }

    /**
     * Sends a GET request to the application API to fetch unencoded data
     * @param string $url
     * @param array $parameters
     * @param string|null $fileName
     * @return string|boolean
     * @throws Zend_Http_Client_Exception
     */
    public function getRaw(string $url, array $parameters = [], string $fileName = NULL): string|bool {
        $response = $this->request($url, 'GET', $parameters);
        $status = $response->getStatus();
        if(200 <= $status && $status < 300) {
            $rawData = $response->getBody();
            $this->captureData($fileName, $rawData);
            return $rawData;
        }
        return false;
    }

    /**
     * Retrieves a JSON, ignores Server errors but will add a "success" prop to the returned JSON in any case
     * @param string $url
     * @param string $method
     * @param array $parameters
     * @return stdClass
     * @throws Zend_Http_Client_Exception
     */
    public function getJsonRaw(string $url, string $method = 'GET', array $parameters=[]) {
        $resp = $this->request($url, $method, $parameters);
        return $this->decodeRawResponse($resp);
    }

    /**
     * save the given data to the given file on capturing data
     * all JSON data is now: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
     * to reduce git glutter on diffing after capturing this legacy config can be used for easier comparsion of data
     * @param string|null $fileName
     * @param string|array|object|null $rawData
     * @param bool $encode
     */
    public function captureData(?string $fileName, mixed $rawData, bool $encode = false): void {
        if(!$this->isCapturing() || empty($fileName) || is_null($rawData)) {
            return;
        }
        if($encode) {
            $rawData = $this->encodeTestData($rawData);
        } elseif(static::$CONFIG['LEGACY_JSON'] && str_ends_with($fileName, '.json')) {
            //if data is already encoded we have to decode and recode it
            $rawData = $this->encodeTestData(json_decode($rawData));
        }
        file_put_contents($this->getFile($fileName, assert: false), $rawData);
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
        $path = join('/', array($this->testRoot, $class, $approvalFile));

        // Fix Windows paths problem
        if (preg_match('~WIN~', PHP_OS)) {
            $path = preg_replace('~^[A-Z]+:~', '', $path);
            $path = str_replace('\\', '/', $path);
        }
        if($assert) {
            $t = $this->testClass;
            $t::assertFileExists($path);
        }
        return $path;
    }

    /**
     * Loads the file contents of a file with data to be compared
     * @param string $approvalFile
     * @param string|null $rawDataToCapture
     * @return string
     */
    public function getFileContent(string $approvalFile, string $rawDataToCapture = null) {
        $this->captureData($approvalFile, $rawDataToCapture);
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
     * @return false|string
     * @throws Exception
     */
    public function getFileContentFromZip(string $zipfile, string $pathToFileInZip) {
        $pathToZip = $this->getFile($zipfile);
        return $this->getFileContentFromZipPath($pathToZip, $pathToFileInZip);
    }

    /**
     * returns the content of the given filename in a given ZIP, in filename * and ? may be used. If it mathces multiple files the first one is returned.
     * @param string $pathToZip absolute file system path to zip file
     * @param string $pathToFileInZip relative path to file inside of zip (uses glob to evaluate * ? etc pp. returns the first file if matched multiple files!)
     * @return false|string
     * @throws Exception
     */
    public function getFileContentFromZipPath(string $pathToZip, string $pathToFileInZip) {
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
    public function rmDir(string $directory) : bool {
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
                } catch (Exception $e){

                }
            }
        }
        //FIXME try catch ist nur eine übergangslösung!!!
        try {
            rmdir($directory);
        } catch (Exception $e){

        }
        return true;
    }

    /**
     * creates zipfile with testfiles in tmpDir and returns the path to it
     * @param string $pathToTestFiles relative to testcases folder
     * @param string $nameOfZipFile which is created
     * @return string path to zipfile
     * @throws Zend_Exception
     */
    public function zipTestFiles(string $pathToTestFiles, string $nameOfZipFile) : string {
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
            if (preg_match('~WIN~', PHP_OS)) {
                $filePath = preg_replace('~^[A-Z]+:~', '', $filePath);
                $filePath = str_replace(DIRECTORY_SEPARATOR, '/', $filePath);
            }
            $zip->addFile($file, str_replace('^'.$dir, '', '^'.$filePath));
        }

        $zip->close();

        return $zipFile;
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

    /**
     * Performs a login
     * @param string $login
     * @param string $password
     */
    public function login(string $login, string $password = self::PASSWORD) : bool {
        if(static::$authLogin === $login && static::$authCookie !== null){
            return false;
        } else if(static::$authLogin !== null){
            $this->logout();
        }
        $response = $this->postJson('editor/session', [
            'login' => $login,
            'passwd' => $password,
        ]);
        $plainResponse = $this->getLastResponse();
        $t = $this->testClass;
        /* @var $t \PHPUnit\Framework\TestCase */
        $t::assertEquals(200, $plainResponse->getStatus(), 'Server did not respond HTTP 200');
        $t::assertNotFalse($response, 'JSON Login request was not successfull!');
        $t::assertMatchesRegularExpression('/[a-zA-Z0-9]{26}/', $response->sessionId, 'Login call does not return a valid sessionId!');
        static::$authCookie = $response->sessionId;
        static::$authLogin = $login;
        return true;
    }

    /**
     * Makes a request to the configured logout URL
     */
    public function logout() {
        $this->request(static::$CONFIG['LOGOUT_PATH']);
        static::$authCookie = null;
        static::$authLogin = null;
    }

    /**
     * requests the REST API, can handle file uploads, add file methods must be called first
     * @param string $url
     * @param string $method GET;POST;PUT;DELETE must be POST or PUT to transfer added files
     * @return Zend_Http_Response
     * @throws Zend_Http_Client_Exception
     */
    protected function request(string $url, string $method = 'GET', array $parameters = array()) {

        $http = new Zend_Http_Client();
        $url = ltrim($url, '/');

        //prepend the taskid to the URL if the test has a task with id.
        // that each request has then the taskid is no problem, this is handled by .htaccess and finally by the called controller.
        // If the called controller does not need the taskid it just does nothing there...
        if(($this->getTask()->id ?? 0) > 0) {
            $url = preg_replace('#^editor/#', 'editor/taskid/'.$this->getTask()->id.'/', $url);
        }
        $http->setUri(static::$CONFIG['API_URL'].$url);
        $http->setHeaders('Accept', 'application/json');

        //enable xdebug debugger in eclipse
        if($this->xdebug) {
            $http->setCookie('XDEBUG_SESSION','ECLIPSE');
            $http->setConfig(array('timeout'      => 3600));
        } else {
            $http->setConfig(array('timeout'      => 30));
        }

        if(static::$authCookie !== null) {
            $http->setCookie(static::AUTH_COOKIE_KEY, static::$authCookie);
        }

        if(!empty($this->filesToAdd) && ($method == 'POST' || $method == 'PUT')) {
            foreach($this->filesToAdd as $file) {
                if(empty($file['path']) && !empty($file['data'])){
                    $http->setFileUpload($file['filename'], $file['name'], $file['data'], $file['mime']);
                    continue;
                }
                //file paths can also be absolute:
                if(str_starts_with($file['path'], '/')) {
                    $abs = $file['path'];
                }
                else {
                    $abs = $this->testRoot.'/'.$file['path'];
                }
                $t = $this->testClass;
                $t::assertFileExists($abs);
                $http->setFileUpload($abs, $file['name'], file_get_contents($abs), $file['mime']);
            }
            $this->filesToAdd = [];
        }

        if($method == 'POST' || $method == 'PUT') {
            $addParamsMethod = 'setParameterPost';
        } else {
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
     * Internal API to fetch JSON Data. Automatically saves the fetched file in capture-mode
     * @param string $url
     * @param string $method
     * @param array $parameters
     * @param string|null $jsonFileName the filename to be used for capturing the data
     * @param bool $isTreeData
     * @return mixed|boolean
     */
    protected function fetchJson(string $url, string $method = 'GET', array $parameters = [], ?string $jsonFileName, bool $isTreeData) {
        $response = $this->request($url, $method, $parameters);
        $result = $this->decodeJsonResponse($response, $isTreeData);
        if($result === false) {
            $this->testClass::fail('apiTest '.$method.' on '.$url.' returned: '."\n\n".$response->__toString());
        } else if($this->isCapturing() && !empty($jsonFileName)){
            // in capturing mode we save the requested data as the data to test against
            $this->captureData($jsonFileName, $this->encodeTestData($result));
        }
        return $result;
    }

    /**
     * Decodes a returned JSON answer from Translate5 REST API
     * @param Zend_Http_Response $resp
     * @return mixed|boolean
     */
    protected function decodeJsonResponse(Zend_Http_Response $resp, bool $isTreeData=false) {
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
     * Parses a raw result that may represents a server error to be able to retrieve failed requests without forcing a failing test (e.g. if wishing to validate the errors)
     * The result may already has a success-property, if not, it will be set by status-code
     * @param Zend_Http_Response $resp
     * @return stdClass
     */
    protected function decodeRawResponse(Zend_Http_Response $resp){
        $result = json_decode($resp->getBody());
        if(!$result){
            $result = new stdClass();
        }
        if(!property_exists($result, 'success')){
            $status = $resp->getStatus();
            $result->success = (200 <= $status && $status < 300);
        }
        return $result;
    }

    /**
     * Json encode for test data
     * @param mixed $data
     * @return string
     */
    protected function encodeTestData(mixed $data): string {
        if(is_null($data)) {
            return '';
        }
        if(static::$CONFIG['LEGACY_JSON']) {
            return json_encode($data, JSON_PRETTY_PRINT);
        }
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}