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
declare(strict_types=1);

namespace MittagQI\ZfExtended\Worker\Trigger;

use Zend_Exception;
use Zend_Registry;
use ZfExtended_Application;
use ZfExtended_BaseIndex;
use ZfExtended_Debug;
use ZfExtended_Logger;
use ZfExtended_Models_Worker;
use ZfExtended_Utils;

class Http implements TriggerInterface
{
    public const WORKER_HEADER = 'X-Translate5-Worker';

    public const TESTNAME_HEADER = 'X-Translate5-Testname';

    public const WORKER_CHECK_IGNORE = 'cli-ignore';

    protected ZfExtended_Logger $log;

    private string $host = 'localhost'; //attention the port alone does not define if SSL is used or no by fsockopent!

    private int $port = 80;

    private string $path = '';

    private array $postParameters = [];

    private string $method = 'GET';

    private string $getParameters = '';

    /**
     * @throws Zend_Exception
     */
    public function __construct()
    {
        $this->log = Zend_Registry::get('logger')->cloneMe('core.worker');
        $this->log->addDuplicatesByEcode('E1107');
    }

    /**
     * Trigger worker with id = $id.
     * To run mutex-save, the current hash is needed
     *
     * @throws Zend_Exception
     */
    public function triggerWorker(string $id, string $hash): bool
    {
        return $this->triggerUrl(
            APPLICATION_RUNDIR . '/editor/worker/' . $id,
            [
                'state' => 'running',
                'hash' => $hash,
            ],
            'PUT'
        );
    }

    /**
     * Trigger an url without waiting for response.
     * $url must be a valid URL.
     * If GET-Paramters should be sent, then append it to the url direct.
     * If POST-Parameters should be sent, use the second parameter $postParameters
     *
     * @param string $url : a valid URL
     * @param array $postParameters : named array with values to be sent to the url with method POST
     * @param string $method : defautl: 'GET', can be 'PUT' if postParamteters should be sent by PUT instead of POST
     *
     * @return boolean true if everything is OK
     * @throws Zend_Exception
     */
    protected function triggerUrl(string $url, array $postParameters = [], string $method = 'GET'): bool
    {
        $isCli = PHP_SAPI === 'cli';
        if ($isCli) {
            $postParameters['serverId'] = self::WORKER_CHECK_IGNORE;
        } else {
            $postParameters['serverId'] = ZfExtended_Utils::installationHash('ZfExtended_Worker_Abstract');
        }

        $host = $this->triggerInit($url, $postParameters, $method);
        $errno = 0;
        $errstr = '';
        $fsock = fsockopen($host, $this->port, $errno, $errstr, 30);
        if ($fsock === false) {
            $this->log->error('E1072', 'Can not trigger worker URL: {host}:{port} Error: {errorName} ({errorNumber})', [
                'host' => $host,
                'port' => $this->port,
                'errorName' => $errstr,
                'errorNumber' => $errno,
            ]);

            return false;
        }

        $out = $this->createHeader();
        fwrite($fsock, $out);

        stream_set_timeout($fsock, 1); // max readtime = 1 sec., thats ok to get start up errors

        $header = '';
        $state = 0;
        $serverId = '';
        while ($line = fgets($fsock)) {
            if ($line == "\r\n") {
                break;
            }
            $header .= $line;
            if (str_contains($line, "HTTP")) {
                $infos = explode(' ', $line);
                $state = (int) $infos[1];
            }
            if (stripos($line, ZfExtended_Models_Worker::WORKER_SERVERID_HEADER) !== false) {
                $infos = explode(' ', $line);
                $serverId = $infos[1];
            }
        }

        //if we are on CLI we can not check the serverId, so we assume that all is OK here!
        $isServerIdEqual = $isCli || $postParameters['serverId'] === trim($serverId);

        //if the other server does not send a server id, or an invalid server id,
        // the target server is not the current system!
        if ($state != 503 && ! $isServerIdEqual) {
            $state = 999;
        }

        $info = stream_get_meta_data($fsock);

        // $header will be empty if fsock-connection runs into stream_set_timeout()
        //  or if the configured Worker URL is not accessible (due HTTPS / gateway / proxy reasons)
        // a timeout normally indicates that everything is OK, since worker are intended to have a long execution time
        fclose($fsock);
        if (empty($header) || $info['timed_out']) {
            if ($info['timed_out']) {
                return true; //a real timeout is mostly OK.
            }
            $this->log->error('E1073', 'Worker URL result is no HTTP answer!: {host}:{port}', [
                'host' => $host,
                'port' => $this->port,
            ]);

            // if not (URL responds immediately with an empty result)
            // this means the called URL is not properly configured!
            // make a dedicated log entry, since the log below would be bogus for this situation
            return false;
        }

        //since it may happen that the worker does not exist anymore, 404 are valid states
        if ($state >= 200 && $state < 300 || $state === 404) {
            return true;
        }
        $this->logRequest($state, $method, $serverId, $postParameters, $host);

        return false;
    }

    /**
     * Initializes the worker URL parts
     * @return string the host which should be used by fsockopen
     * @throws Zend_Exception
     */
    private function triggerInit(string $path, array $postParameters = [], string $method = 'GET'): string
    {
        $url = ZfExtended_Application::createWorkerUrl($path);
        $urlParts = parse_url($url);

        if (! empty($urlParts['host'])) {
            $this->host = $urlParts['host'];
        }
        $host = $this->host;

        if (! empty($urlParts['scheme']) && $urlParts['scheme'] === 'https') {
            //fsockopen needs ssl:// scheme when using https
            $host = 'ssl://' . $host;
            $this->port = 443;
        }
        if (! empty($urlParts['port'])) {
            $this->port = (int) $urlParts['port'];
        }

        if (! empty($urlParts['path'])) {
            $this->path = $urlParts['path'];
        }

        if (! empty($postParameters)) {
            $this->postParameters = $postParameters;
        }

        if (! empty($postParameters) && $method != 'PUT') {
            $this->method = 'POST';
        }

        if (! empty($method)) {
            $this->method = $method;
        }

        if (! empty($urlParts['query'])) {
            $this->getParameters = '&' . $urlParts['query'];
        }

        //return the hostname dedicated for fsockopen
        return $host;
    }

    /**
     * Creates all headers needed for a worker-call
     * The worker-endpoints are not CSRF protected
     */
    private function createHeader(): string
    {
        $out = $this->method . ' ' . $this->path . $this->getParameters . ' HTTP/1.1' . "\r\n";
        $out .= 'Host: ' . $this->host . "\r\n";
        $out .= 'Accept: application/json' . "\r\n"; // this is translate5-specific !!!
        $out .= self::WORKER_HEADER . ': 1' . "\r\n"; // this is translate5-specific !!!

        $debug = null;
        //activated debug by browser plugin via cookie
        if (isset($_COOKIE['XDEBUG_SESSION'])) {
            $debug = $_COOKIE['XDEBUG_SESSION'];
        }
        if (isset($_SERVER['XDEBUG_CONFIG'])) {
            $debug = $_SERVER['XDEBUG_CONFIG'];
        }
        if (isset($_SERVER['HTTP_X_TRANSLATE5_TESTNAME'])) {
            $out .= self::TESTNAME_HEADER . ': ' . $_SERVER['HTTP_X_TRANSLATE5_TESTNAME'] . "\r\n"; // this is translate5-specific !!!
        }
        //forced debug by config
        if (ZfExtended_Debug::hasLevel('core', 'worker')) {
            $debug = 'PHPSTORM';
        }
        if (! empty($debug)) {
            $out .= 'Cookie: XDEBUG_SESSION=' . $debug . "\r\n";
        }
        // we need to trigger correct environment for worker in API tests
        if (defined('APPLICATION_APITEST') && APPLICATION_APITEST) {
            $origin = (APPLICATION_ENV === ZfExtended_BaseIndex::ENVIRONMENT_TEST)
                ? ZfExtended_BaseIndex::ORIGIN_TEST
                : ZfExtended_BaseIndex::ORIGIN_APPTEST;
            $out .= 'Origin: ' . $origin . "\r\n";
        }

        if ($this->method == 'GET') {
            $out .= 'Connection: Close' . "\r\n";
            $out .= "\r\n";
        }

        // !!! if there are POST/PUT-data then add data here
        if ($this->method == 'POST' || $this->method == 'PUT') {
            $postData = 'data=' . json_encode($this->postParameters);
            $length = strlen($postData);

            $out .= 'Content-Type: application/x-www-form-urlencoded' . "\r\n";
            $out .= 'Content-Length: ' . $length . "\r\n";
            $out .= 'Connection: Close' . "\r\n";
            $out .= "\r\n";
            $out .= $postData;
        }

        return $out;
    }

    private function logRequest(
        int $state,
        string $httpMethod,
        string $serverId,
        array $postParameters,
        string $host
    ): void {
        $code = 'E1074';
        $msg = 'Worker HTTP response state was not 2XX but {state}.';
        switch ($state) {
            case 503:
                //if the service is not available, we just do nothing since this is mostly
                // wanted behaviour due maintenance
                $method = 'info';
                $msg = 'Worker not started, maintenance is scheduled or in progress!';

                break;
            case 500:
                //since on a 500 the real exception was logged in the worker, we just log that here as debug info
                $method = 'debug';

                break;
            case 999:
                //a 999 means we are requesting the wrong server!
                $method = 'error';
                $code = 'E1107';
                $msg = 'Worker HTTP response was not successful, the worker system requests probably the wrong server!';

                break;
            default:
                $method = 'warn';

                break;
        }
        $this->log->__call($method, [$code, $msg, [
            'state' => $state,
            'method' => $httpMethod,
            'serverId' => $serverId,
            'serverIP' => $_SERVER['SERVER_ADDR'],
            'remoteIP' => $_SERVER['REMOTE_ADDR'],
            'postParameters' => $postParameters,
            'host' => $host . $this->path,
            'port' => $this->port,
        ]]);
    }

    /**
     * @throws Zend_Exception
     */
    public function triggerQueue(): bool
    {
        return $this->triggerUrl(APPLICATION_RUNDIR . '/editor/worker/queue');
    }
}
