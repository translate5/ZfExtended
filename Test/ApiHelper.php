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

use PHPUnit\Framework\TestCase;
use MittagQI\ZfExtended\CsrfProtection;

class ZfExtended_Test_ApiHelper {

    const PASSWORD = 'asdfasdf';

    const AUTH_COOKIE_KEY = 'zfExtended';

    /**
     * Development option that triggers all requests to be captured in a file called "TestClassName-TIMESTAMP.log"
     * TODO FIXME: this shall better be a command-option
     */
    const TRACE_REQUESTS = false;

    /**
     * The filename to zip the test-tasks. This zip will be stored temporarily in the APPLICATION_DATA dir
     */
    const TEST_ZIP_FILENAME = 'api-test-tmp.zip';

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
        'SKIP_PRETESTS' => false,
        'SKIP_TESTS' => [],
        'XDEBUG_ENABLE' => false,
        'KEEP_DATA' => false,
        'LEGACY_DATA' => false,
        'LEGACY_JSON' => false,
        'IS_SUITE' => true,
        'ENVIRONMENT' => 'application',
        'CSRF_TOKEN' => ''
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
     * Holds the currently authenticated user session token
     * @var string|null
     */
    private static ?string $authToken = null;

    /***
     * Token used for authentication with token
     * @var string|null
     */
    private static ?string $applicationToken = null;

    /**
     * Steers, if a request shall have an origin set, what is usually neccessary, only in case a RealWorld Request shall be sent this need's to be adjusted
     * @var bool
     */
    private static bool $useOrigin = true;

    /**
     * Sets the Test API up. This needs to be set in the test bootstrapper
     * The given config MUST contain:
     *  'API_URL' => the api url as defined in zend config
     *  'DATA_DIR' => the task data dir as defined in zend config
     *  'LOGOUT_PATH' => the logout url
     *  'CAPTURE_MODE' => if true, defines if we're in capture mode (only when single tests are called), false by default
     *  'SKIP_PRETESTS' => if true, defines if pretests (testing the environment before running the test/suite) shall be reduced to a minimum
     *  'SKIP_TESTS' => if set, defines tests to be skipped
     *  'XDEBUG_ENABLE' => if true, defines if we should enable XDEBUG on the called test instance , false by default
     *  'KEEP_DATA' => if true, defines if test should be kept after test run, must be implemented in the test, false by default
     *  'LEGACY_DATA' => if true, defines to use the "old" data field sort order (to reduce diff clutter on capturing)
     *  'LEGACY_JSON' => if true, defines to use the "old" json encoding config (to reduce diff clutter on capturing)
     *  'IS_SUITE' => if true, a multi-test suite is running, otherwise a single test
     *  'ENVIRONMENT' => 'application' or 'test'. 'test' hints, that the tests run in the test environment and the origin-header must be set to "t5test"
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
     * Retrieves the origin to set for the API calls
     * The TEST environment incorporates an own database for testing
     * The APPTEST enviroment is just the "normal" application DB and just triggers the detection of an API-test
     * @return string
     */
    protected static function createOrigin() : string {
        if(static::$CONFIG['ENVIRONMENT'] === ZfExtended_BaseIndex::ENVIRONMENT_TEST) {
            return ZfExtended_BaseIndex::ORIGIN_TEST;
        } else {
            return ZfExtended_BaseIndex::ORIGIN_APPTEST;
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

    /**
     * @return string
     */
    public static function getAuthToken() : string {
        return static::$authToken;
    }

    /**
     * This method sets the internally managed authentication and is only meant to be used in tests testing the authentication-API
     * @param string $cookie
     */
    public static function setAuthentication(string $cookie, string $login) {
        static::$authCookie = $cookie;
        static::$authLogin = $login;
        static::$authToken = null;
    }

    /***
     * Sets an application-token for the following requests.
     * Make sure to always unset such tokens after your test
     * @param string $token
     * @return void
     */
    public static function setApplicationToken(string $token){
        static::$applicationToken = $token;
    }

    /**
     * Invalidates the token set above
     */
    public static function unsetApplicationToken(){
        static::$applicationToken = null;
    }

    /**
     * Retrieves the current CSRF token
     * @return string
     */
    public static function getCsrfToken(): string
    {
        return static::$CONFIG['CSRF_TOKEN'];
    }

    /**
     * Sets the CSRF token. Generally, Tests run with a Fixed CSRF Token (saved as tmp file)
     * This API is only for testing the CSRF feature
     * Always set this token back to the original state when manipulating it for a test
     * @param string|null $token
     */
    public static function setCsrfToken(string $token = null)
    {
        static::$CONFIG['CSRF_TOKEN'] = $token;
    }

    /**
     * Steers, if the Origin-Header should be set for the requests. Normally, this Header steers the Environment on the Server side
     * Be Aware, that deactivating this header leads to the "real" CSRF protection to kick in and disables any adjustments for API-tests
     * And always re-activate this header when manipulating it within a test
     * @param bool $flag
     */
    public static function activateOriginHeader(bool $flag = true)
    {
        static::$useOrigin = $flag;
    }

    /**
     * returns the absolute data path to the base directory for task data
     * @return string
     */
    public static function getTaskDataBaseDirectory(): string
    {
        return static::$CONFIG['DATA_DIR'];
    }

    /**
     * Holds a list of allowed http status code other then 200-299.
     * Is cleaned after each request.
     * @var array
     */
    private array $allowHttpStatusOnce = [];
    
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
     * @var Zend_Http_Response
     */
    protected Zend_Http_Response $lastResponse;

    /**
     * Test root directory
     * @var string
     */
    protected string $testRoot;

    /**
     * @var string
     */
    protected string $testClass;

    /**
     * Test root directory
     * @var TestCase
     */
    protected TestCase $test;

    /**
     * @var string
     */
    private string $requestTrace;

    /**
     * @throws ReflectionException
     */
    public function __construct(string $testClass, TestCase $test) {
        $reflector = new ReflectionClass($testClass);
        $this->test = $test;
        $this->testClass = $testClass;
        $this->testRoot = dirname($reflector->getFileName());
        $this->xdebug = static::$CONFIG['XDEBUG_ENABLE'];
        $this->cleanup = !static::$CONFIG['KEEP_DATA'];

        if(self::TRACE_REQUESTS){
            $this->requestTrace = 'REQUEST TRACE FOR TEST "'.$testClass.'"'."\n\n";
        }
    }

    public function __destruct() {
        if(self::TRACE_REQUESTS){
            $filename = $this->testClass.'-'.time().'.log';
            file_put_contents($filename, $this->requestTrace);
        }
    }

    /**
     * Retrieves the class of the currently executed test
     * @return string
     */
    public function getTestClass() : string {
        return $this->testClass;
    }

    /**
     * Checks if a test is marked as "skipped" by command-options
     * @return bool
     */
    public function isTestSkipped(): bool
    {
        return in_array($this->testClass, static::$CONFIG['SKIP_TESTS']);
    }

    /**
     * Retrieves an instance of the current test (Note, that this is not the executed instance)
     * @return TestCase
     */
    public function getTest() : TestCase {
        return $this->test;
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
     * The delete will not be sent, if the cleanup is prevented in the test-setup
     * if it is expected to fail, it will always be sent
     * @param string $url
     * @param array $parameters
     * @param bool $expectedToFail
     * @return array|false|stdClass
     * @throws Zend_Http_Client_Exception
     */
    public function delete(string $url, array $parameters = [], bool $expectedToFail = false) {
        if($this->cleanup || $expectedToFail){
            return $this->fetchJson($url, 'DELETE', $parameters, null, false, $expectedToFail);
        }
        return false;
    }

    /**
     * Sends a GET request to the application API to fetch JSON data
     * @param string $url
     * @param array $parameters
     * @param string|null $jsonFileName
     * @param bool $expectedToFail
     * @return stdClass|array
     * @throws Zend_Http_Client_Exception
     */
    public function getJson(string $url, array $parameters = [], string $jsonFileName = null, bool $expectedToFail = false) {
        return $this->fetchJson($url, 'GET', $parameters, $jsonFileName, false, $expectedToFail);
    }

    /**
     * Sends a GET request to the application API to get a ExtJS type JSON tree
     * @param string $url
     * @param array $parameters
     * @param string|null $jsonFileName
     * @param bool $expectedToFail
     * @return stdClass|array
     * @throws Zend_Http_Client_Exception
     */
    public function getJsonTree(string $url, array $parameters = [], string $jsonFileName = null, bool $expectedToFail = false) {
        return $this->fetchJson($url, 'GET', $parameters, $jsonFileName, true, $expectedToFail);
    }

    /**
     * Sends a PUT request to the application API to fetch JSON data
     * @param string $url
     * @param array $parameters
     * @param string|null $jsonFileName
     * @param bool $encodeParamsAsData
     * @param bool $expectedToFail
     * @return stdClass|array
     * @throws Zend_Http_Client_Exception
     */
    public function putJson(string $url, array $parameters = [], string $jsonFileName = NULL, bool $encodeParamsAsData = true, bool $expectedToFail = false) {
        if(empty($this->filesToAdd) && $encodeParamsAsData){
            $parameters = array('data' => json_encode($parameters));
        }
        return $this->fetchJson($url, 'PUT', $parameters, $jsonFileName, false, $expectedToFail);
    }

    /**
     * Sends a POST request to the application API to fetch JSON data
     * @param string $url
     * @param array $parameters
     * @param string|null $jsonFileName
     * @param bool $encodeParamsAsData
     * @param bool $expectedToFail
     * @return stdClass|array
     * @throws Zend_Http_Client_Exception
     */
    public function postJson(string $url, array $parameters = [], string $jsonFileName = null, bool $encodeParamsAsData = true, bool $expectedToFail = false) {
        if(empty($this->filesToAdd) && $encodeParamsAsData){
            $parameters = array('data' => json_encode($parameters));
        }
        return $this->fetchJson($url, 'POST', $parameters, $jsonFileName, false, $expectedToFail);
    }

    /**
     * Posts raw content (not form-encoded, not as form-data)
     * @param string $url
     * @param string $content
     * @param array $parameters
     * @return bool|mixed|stdClass|null
     * @throws Zend_Http_Client_Exception
     */
    public function postRawData(string $url, string $content, array $parameters=[], string $jsonFileName = null, bool $expectedToFail=false) : stdClass {
        $http = new Zend_Http_Client();
        $http->setUri(static::$CONFIG['API_URL'].ltrim($url, '/'));
        $this->addClientAuthorization($http);
        
        $http->setHeaders('Accept', 'application/json');
        $http->setRawData($content, 'application/octet-stream');
        $http->setHeaders(Zend_Http_Client::CONTENT_TYPE, 'application/octet-stream');
        if(!empty($parameters)) {
            foreach($parameters as $key => $value) {
                $http->setParameterGet($key, $value); // when setting the raw request-body params can only be set as GET params!
            }
        }
        if(self::TRACE_REQUESTS){
            $this->traceRequest($http, $parameters, 'POST');
        }
        $this->lastResponse = $http->request('POST');
        $result = $this->decodeJsonResponse($this->lastResponse, false);
        if(!$expectedToFail && !$this->isStatusSuccess($this->lastResponse->getStatus())) {
            $this->test::fail('apiTest POST RAW DATA on '.$url.' returned '.$this->lastResponse->__toString());
        } else if($this->isCapturing() && !empty($jsonFileName)){
            // in capturing mode we save the requested data as the data to test against
            $this->captureData($jsonFileName, $this->encodeTestData($result));
        }
        return $result;
    }

    /**
     * Sends a GET request to the application API to fetch unencoded data
     * Returns an object with 3 props: success, status, data (which is the raw response body)
     * @param string $url
     * @param array $parameters
     * @param string|null $fileName: if set, the raw response body will be captured
     * @return stdClass
     * @throws Zend_Http_Client_Exception
     */
    public function getRaw(string $url, array $parameters = [], string $fileName = NULL): stdClass {
        $response = $this->request($url, 'GET', $parameters);
        $result = $this->createResponseResult($response);
        if(!$this->isJsonResultError($result)) {
            $this->captureData($fileName, $result->data);
        }
        return $result;
    }

    /**
     * Helper to fetch an application page
     * This will not send an origin-header triggering a test-enviromnment
     * @param string $url
     * @param array $parameters
     * @param string|null $authCookie
     * @return Zend_Http_Response
     * @throws Zend_Http_Client_Exception
     */
    public function getHtmlPage(string $url, array $parameters = [], string $authCookie = null) {
        // set a proper user-agent
        $http = new Zend_Http_Client(static::$CONFIG['API_URL'].ltrim($url, '/'), ['useragent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0']);
        $http->setHeaders('Accept', 'text/html');
        // Authorization
        if(!empty($authCookie)) {
            $http->setCookie(static::AUTH_COOKIE_KEY, $authCookie);
        }
        if(!empty($parameters)) {
            foreach($parameters as $key => $value) {
                $http->setParameterGet($key, $value);
            }
        }
        if(self::TRACE_REQUESTS){
            $this->traceRequest($http, $parameters, 'GET');
        }
        return $http->request('GET');
    }

    /**
     * Retrieves a JSON, ignores Server errors but will add a "success" prop to the returned JSON in any case
     * @param string $url
     * @param string $method
     * @param array $parameters
     * @return stdClass
     * @throws Zend_Http_Client_Exception
     */
    public function getJsonRaw(string $url, string $method = 'GET', array $parameters=[]) : stdClass {
        $response = $this->request($url, $method, $parameters);
        return $this->createResponseResult($response);
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

    /***
     * returns the last requested response decoded
     * @return mixed|stdClass
     */
    public function getLastResponseDecodeed(): mixed
    {
        return $this->decodeJsonResponse($this->lastResponse);
    }
    
    /**
     * Checks, whether the result of a getJson / putJson / postJson request represents an error
     * corresponds what is done in createResponseResult
     * @param stdClass $result
     * @return bool
     */
    public function isJsonResultError(stdClass $result) : bool {
        return (property_exists($result, 'error') && !empty($result->error));
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
    public function getFile(string $approvalFile, string $class = null, bool $assert = true) {
        if(empty($class)) {
            $class = $this->testClass;
        }
        $path = join('/', array($this->testRoot, $class, $approvalFile));

        // Fix Windows paths problem
        if (PHP_OS_FAMILY == 'Windows') {
            $path = preg_replace('~^[A-Z]+:~', '', $path);
            $path = str_replace('\\', '/', $path);
        }
        if($assert) {
            $this->test::assertFileExists($path);
        }
        return $path;
    }

    /**
     * Creates an absolute path for a filePath relative to the main test folder "editorAPI"
     * @param string $relativePath
     * @return string
     */
    public function getAbsFilePath(string $relativePath, bool $addTestClassFolder = false){
        // the file may already is an absolute path
        if(str_starts_with($relativePath, '/')) {
            return $relativePath;
        }
        if($addTestClassFolder){
            return $this->testRoot.'/'.$this->testClass.'/'.$relativePath;
        }
        return $this->testRoot.'/'.$relativePath;
    }

    /**
     * Loads the file contents of a file with data to be compared
     * @param string $approvalFile
     * @param string|stdClass|array|null $rawDataToCapture
     * @param bool $encode: must be true when non-textual data shall be captured
     * @return stdClass|array|string
     */
    public function getFileContent(string $approvalFile, mixed $rawDataToCapture = null, bool $encode = false) {
        $this->captureData($approvalFile, $rawDataToCapture, $encode);
        $data = file_get_contents($this->getFile($approvalFile));
        if(preg_match('/\.json$/i', $approvalFile)){
            $data = json_decode($data);
            $this->test::assertEquals('No error', json_last_error_msg(), 'Test file '.$approvalFile.' does not contain valid JSON!');
        }
        return $data;
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
        if (!mkdir($dir) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }
        $zip->extractTo($dir);
        $files = glob($dir.$pathToFileInZip, GLOB_NOCHECK);
        $file = reset($files);
        $this->test::assertFileExists($file);
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
     * @return string path to zipfile
     * @throws Zend_Exception
     */
    public function zipTestFiles(string $pathToTestFiles) : string {

        $dir = $this->getFile($pathToTestFiles);
        // we create the zip in the data-dir to not have trouble with rights
        $zipFile = APPLICATION_DATA . '/' . self::TEST_ZIP_FILENAME;
        // remniscents of former tests can be cleaned up
        if(file_exists($zipFile)) {
            unlink($zipFile);
        }

        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
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
            if (PHP_OS_FAMILY == 'Windows') {
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
     * Retrieves, if the current run is for a multitest-suite or a single testcase
     * @return bool
     */
    public function isSuite(): bool
    {
        return static::$CONFIG['IS_SUITE'];
    }

    /**
     * Retrieves, if the current test should be cleaned up
     * @return bool
     */
    public function doCleanup(): bool
    {
        return $this->cleanup;
    }

    /**
     * Retrieves, if the pre-tests/environment-tests shall be skipped
     * @return bool
     */
    public function doSkipPretests() : bool
    {
        return static::$CONFIG['SKIP_PRETESTS'];
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
        /* @var $t \PHPUnit\Framework\TestCase */
        $this->assertResponseStatus($plainResponse, 'Login');
        $this->test::assertTrue((property_exists($response, 'sessionId') && property_exists($response, 'sessionToken')), 'JSON Login request was not successfull!');
        $this->test::assertMatchesRegularExpression('/[a-zA-Z0-9]{26}/', $response->sessionId, 'Login call does not return a valid sessionId!');
        static::$authCookie = $response->sessionId;
        static::$authToken = $response->sessionToken;
        static::$authLogin = $login;
        return true;
    }

    /**
     * Makes a request to the configured logout URL
     * Destroys the internally cached authentication data
     */
    public function logout() {
        $this->request(static::$CONFIG['LOGOUT_PATH']);
        static::$authCookie = null;
        static::$authToken = null;
        static::$authLogin = null;
    }

    /**
     * requests the REST API, can handle file uploads, add file methods must be called first
     * @param string $url
     * @param string $method GET;POST;PUT;DELETE must be POST or PUT to transfer added files
     * @return Zend_Http_Response
     * @throws Zend_Http_Client_Exception
     */
    protected function request(string $url, string $method = 'GET', array $parameters = []) {

        $http = new Zend_Http_Client();
        $url = ltrim($url, '/');

        //prepend the taskid to the URL if the test has a task with id.
        // that each request has then the taskid is no problem, this is handled by .htaccess and finally by the called controller.
        // If the called controller does not need the taskid it just does nothing there...
        if(($this->getTask()->id ?? 0) > 0) {
            $url = preg_replace('#^editor/#', 'editor/taskid/'.$this->getTask()->id.'/', $url);
        }
        $http->setUri(static::$CONFIG['API_URL'].$url);
        $this->addClientAuthorization($http);

        $http->setHeaders('Accept', 'application/json');
        //enable xdebug debugger in eclipse
        if($this->xdebug) {
            $http->setCookie('XDEBUG_SESSION','PHPSTORM');
            $http->setConfig(array('timeout' => 3600));
        } else {
            $http->setConfig(array('timeout' => 30));
        }

        if(!empty($this->filesToAdd) && ($method == 'POST' || $method == 'PUT')) {
            foreach($this->filesToAdd as $file) {
                if(empty($file['path']) && !empty($file['data'])){
                    $http->setFileUpload($file['filename'], $file['name'], $file['data'], $file['mime']);
                    continue;
                }
                $absolutePath = $this->getAbsFilePath($file['path']);
                $this->test::assertFileExists($absolutePath);
                $http->setFileUpload($absolutePath, $file['name'], file_get_contents($absolutePath), $file['mime']);
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
        if(self::TRACE_REQUESTS){
            $this->traceRequest($http, $parameters, $method);
        }
        $this->lastResponse = $http->request($method);
        return $this->lastResponse;
    }

    /**
     * Internal API to fetch JSON Data. Automatically saves the fetched file in capture-mode
     * @param string $url
     * @param string $method
     * @param array $parameters
     * @param string|null $jsonFileName
     * @param bool $isTreeData
     * @param bool $expectedToFail
     * @return stdClass|array
     * @throws Zend_Http_Client_Exception
     */
    protected function fetchJson(string $url, string $method = 'GET', array $parameters = [], ?string $jsonFileName, bool $isTreeData, bool $expectedToFail = false) {
        $response = $this->request($url, $method, $parameters);
        $result = $this->decodeJsonResponse($response, $isTreeData);
        if(!$expectedToFail && !$this->isStatusSuccess($response->getStatus())) {
            $this->test::fail('apiTest '.$method.' on '.$url.' returned '.$response->__toString().' with parameters '.print_r($parameters,1));
        } else if($this->isCapturing() && !empty($jsonFileName)){
            // in capturing mode we save the requested data as the data to test against
            $this->captureData($jsonFileName, $this->encodeTestData($result));
        }
        $this->allowHttpStatusOnce = [];
        return $result;
    }

    public function allowHttpStatusOnce(int $httpStatus): void
    {
        $this->allowHttpStatusOnce[] = $httpStatus;
    }

    /**
     * Decodes a returned JSON answer from Translate5 REST API
     * @param Zend_Http_Response $resp
     * @param bool $isTreeData
     * @return mixed|stdClass
     */
    protected function decodeJsonResponse(Zend_Http_Response $resp, bool $isTreeData=false) {
        $status = $resp->getStatus();
        if($this->isStatusSuccess($status)) {
            $body = $resp->getBody();
            if(empty($body)) {
                return $this->createResponseResult($resp);
            }
            $json = json_decode($resp->getBody());
            $this->test::assertEquals('No error', json_last_error_msg(), 'Server did not response valid JSON: '.$resp->getBody());
            if($isTreeData){
                if(property_exists($json, 'children') && count($json->children) > 0){
                    return $json->children[0];
                } else {
                    $result = $this->createResponseResult($resp);
                    $result->error = 'The fetched data had no children in the root object';
                    return $result;
                }
            } else if(property_exists($json, 'rows')){
                return $json->rows;
            } else {
                return $json;
            }
        }
        return $this->createResponseResult($resp);
    }

    /**
     * Create unified result object for failing requests or if we need to mock a result
     * @param Zend_Http_Response|null $response
     * @param string|null $error
     * @return stdClass
     */
    protected function createResponseResult(?Zend_Http_Response $response, string $error=null) : stdClass {
        $status = ($response) ? $response->getStatus() : 0;
        $result = new stdClass();
        $result->status = $status;
        $result->data = ($response) ? $response->getBody() : null;
        if(!$this->isStatusSuccess($status)){
            if($error){
                $result->error = $error;
                return $result;
            }
            if($response){
                try {
                    $json = json_decode($response->getBody());
                    if(is_object($json)){
                        // std exception response
                        if(property_exists($json, 'errorCode') && property_exists($json, 'errorMessage')) {
                            $result->error = $json->errorMessage;
                            $result->errorCode = $json->errorCode;
                            return $result;
                        }
                        if(property_exists($json, 'error')){
                            $result->error = $json->error;
                            return $result;
                        }
                        if(property_exists($json, 'errors') && count($json->errors) > 0){
                            $errors = '';
                            foreach($json->errors as $error){
                                if(property_exists($error, 'msg')){
                                    $errors .= ($errors === '') ? $error->msg : "\n".$error->msg;
                                }
                            }
                            if(!empty($errors)){
                                $result->error = $errors;
                                return $result;
                            }
                        }
                    }
                } catch(Throwable){
                }
            }
            $result->error = 'Request failed with status '.$status;
        }
        return $result;
    }

    /**
     * Generally evaluates our accepted status codes
     * @param int $status
     * @return bool
     */
    protected function isStatusSuccess(int $status) : bool {
        if(in_array($status, $this->allowHttpStatusOnce)) {
            return true;
        }
        return (200 <= $status && $status < 300);
    }

    /**
     * @param Zend_Http_Response $response
     * @param string $requestType
     */
    protected function assertResponseStatus(Zend_Http_Response $response, string $requestType){
        $this->test::assertTrue($this->isStatusSuccess($response->getStatus()), $requestType.' Request does not respond HTTP 200-299! Body was: '.$response->getBody());
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

    /**
     * Sets the appropriate Cookies & Headers for a HTTP client
     * @param Zend_Http_Client $client
     * @throws Zend_Http_Client_Exception
     */
    private function addClientAuthorization(Zend_Http_Client $client){
        // set the proper test-origin
        if(static::$useOrigin){
            $client->setHeaders('Origin', static::createOrigin());
        }
        // App-token as alternative for authentication
        if(static::$applicationToken !== null) {
            $client->setHeaders(ZfExtended_Authentication::APPLICATION_TOKEN_HEADER, static::$applicationToken);
        } else {
            // Authorization cookie if set
            if(static::$authCookie !== null) {
                $client->setCookie(self::AUTH_COOKIE_KEY, static::$authCookie);
            }
            // CSRF token if set
            if(!empty(static::$CONFIG['CSRF_TOKEN'])){
                $client->setHeaders(CsrfProtection::HEADER_NAME, static::$CONFIG['CSRF_TOKEN']);
            }
        }
    }

    /**
     * Helper to trace all requests
     * @param Zend_Http_Client $http
     * @param array $parameters
     * @param string $method
     */
    private function traceRequest(Zend_Http_Client $http, array $parameters, string $method){
        $this->requestTrace .= "\n\n".$method.': '.$http->getUri(true);
        // AUTH stuff
		if(static::$applicationToken !== null) {
			$this->requestTrace .= "\n ".ZfExtended_Authentication::APPLICATION_TOKEN_HEADER.': '.static::$applicationToken;
        } else {
            if(static::$authCookie !== null) {
                $this->requestTrace .= "\n Auth: ".static::$authLogin.", ".static::$authCookie;
            }
            if(!empty(static::$CONFIG['CSRF_TOKEN'])){
                $this->requestTrace .= "\n ".CsrfProtection::HEADER_NAME.': '.static::$CONFIG['CSRF_TOKEN'];
            }
        }
        ksort($parameters);
        foreach($parameters as $key => $value){
            $this->requestTrace .= "\n  ".$key.': '.$this->traceParam($value);
        }
    }

    /**
     * Helper to trace a single request param
     * @param $value
     * @return string
     */
    private function traceParam($value){
        if(is_bool($value)){
            return $value ? 'true' : 'false';
        }
        if(is_numeric($value)){
            return strval($value);
        }
        if(is_object($value) || is_array($value)){
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $value = strval($value);
        $value = str_replace("\r", '', $value);
        $value = str_replace("\n", '\\n', $value);
        return $value;
    }
}