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

/**
 * @package ZfExtended
 * @version 2.0
 */
class ZfExtended_Models_Installer_DbUpdater {
    /**
     * default executable of the mysql command, can be overwritten by config
     * @var string
     */
    const MYSQL_BIN = '/usr/bin/mysql';
    
    /**
     * contains errors on importing to DB
     * @var array
     */
    protected $errors = array();
    
    /**
     * The sql files which are new and can be imported
     * @var array
     */
    protected $sqlFilesNew = array();
    
    /**
     * The sql files which have been changed
     * @var array
     */
    protected $sqlFilesChanged = array();
    
    /**
     * This flag is mentioned to be set from within PHP alter files to false for testing.
     * If setting this to false the file will not be marked as updated.
     * @var boolean
     */
    protected $doNotSavePhpForDebugging = true;
    
    /**
     * @var ZfExtended_Logger
     */
    protected $log;
    
    /**
     * DB credentials, exec and base path must be given as parameter in usage of a non Zend Environment
     * @param stdClass $db optional
     * @param string $exec optional
     * @param string $path optional
     */
    public function __construct(stdClass $db = null, $exec = null, $path = null) {
        if(class_exists('Zend_Registry', false) && defined('APPLICATION_PATH')) {
            $config = Zend_Registry::get('config');
            /* @var $config Zend_Config */
            $db = (object) $config->resources->db->params->toArray();
            $exec = $this->getDbExec();
            $path = APPLICATION_PATH.'/..';
            $this->log = Zend_Registry::get('logger')->cloneMe('core.database.update');
        }
        if(!empty($db)) {
            $this->checkCredentials($db, $exec, $path);
        }
    }
    
    /**
     * Forcing all available SQL files to be set as imported in the DB, regardless if the contents were really applied or not.
     * For setting up dbversioning on instances where all SQL files are already installed.
     * @param array $toProcess
     */
    public function assumeImported(array $toProcess) {
        $dbversion = ZfExtended_Factory::get('ZfExtended_Models_Db_DbVersion');
        /* @var $dbversion ZfExtended_Models_Db_DbVersion */
        
        foreach($this->getFoundFiles() as $file) {
            $entryHash = $this->getEntryHash($file['origin'], $file['relativeToOrigin']);
            if(empty($toProcess[md5($entryHash)])) {
                continue;
            }
            $path = $file['absolutePath'];
            if(!file_exists($path) || !is_readable($path)) {
                $this->log->error('E1293', 'The following file does not exist or is not readable and is therefore ignored: {path}',[
                    'path' => $path
                ]);
                continue;
            }
            $dbversion->insert($this->getInsertData($file, ZfExtended_Utils::getAppVersion()));
        }
    }
    
    /**
     * returns an array ready for DB Insertion in dbversion
     * @param array $file
     * @param string $appVersion
     * @return array
     */
    protected function getInsertData(array $file, $appVersion) {
        return array(
            'origin' => $file['origin'],
            'filename' => $file['relativeToOrigin'],
            'md5' => $this->filehash($file['absolutePath']),
            'appVersion' => $appVersion
        );
    }
    
    /**
     * loops over all configured SQL directories and find new or modified SQL files compared to the version in the DB.
     */
    public function calculateChanges() {
        $this->sqlFilesNew = array();
        $this->sqlFilesChanged = array();
        $dbversion = ZfExtended_Factory::get('ZfExtended_Models_Db_DbVersion');
        /* @var $dbversion ZfExtended_Models_Db_DbVersion */
        $installed = $dbversion->fetchAll();
        $installHashed = array();
        foreach($installed as $row) {
            $installHashed[$this->getEntryHash($row->origin, $row->filename)] = $row->md5;
        }
        
        foreach($this->getFoundFiles() as $file) {
            $entryHash = $this->getEntryHash($file['origin'], $file['relativeToOrigin']);
            $file['entryHash'] = md5($entryHash);
            if(empty($installHashed[$entryHash])){
                $this->sqlFilesNew[] = $file;
                continue;
            }
            if($installHashed[$entryHash] !== $this->filehash($file['absolutePath'])) {
                $this->sqlFilesChanged[] = $file;
            }
        }
    }
    
    /**
     * returns all found SQL files
     */
    protected function getFoundFiles() {
        $filefinder = ZfExtended_Factory::get('ZfExtended_Models_Installer_DbFileFinder');
        /* @var $filefinder ZfExtended_Models_Installer_DbFileFinder */
        return $filefinder->getSqlFilesOrdered();
    }
    
    /**
     * returns the modified SQL files found by self::calculateChanges()
     * @return array
     */
    public function getModifiedFiles() {
        return $this->sqlFilesChanged;
    }
    
    /**
     * returns the modified SQL files found by self::calculateChanges()
     * @return array
     */
    public function getNewFiles() {
        return $this->sqlFilesNew;
    }
    
    /**
     * returns a hash value for thi given absolute file path
     * @param string $filepath
     * @return string
     */
    protected function filehash($filepath) {
        return md5_file($filepath);
    }

    /**
     * makes a simple hash out of the given parameters
     * @param string $origin
     * @param string $filename
     * @return string
     */
    protected function getEntryHash($origin, $filename) {
        return $origin.'#'.$filename;
    }

    /**
     * all modified files are marked as up to date in the database
     * Since this should happen only on DEV systems we log it directly into the error_log
     * @param array $toProcess
     */
    public function updateModified(array $toProcess) {
        $dbversion = ZfExtended_Factory::get('ZfExtended_Models_Db_DbVersion');
        /* @var $dbversion ZfExtended_Models_Db_DbVersion */
        
        foreach($this->sqlFilesChanged as $key => $file) {
            $entryHash = $this->getEntryHash($file['origin'], $file['relativeToOrigin']);
            if(empty($toProcess[md5($entryHash)])) {
                continue;
            }
            
            $a = $dbversion->getAdapter();
            $where= array(
                $a->quoteInto('origin = ?', $file['origin']),
                $a->quoteInto('filename = ?', $file['relativeToOrigin']),
            );
        
            $count = $dbversion->update(array('md5' => $this->filehash($file['absolutePath'])), $where);
            //the file must be changed at least one time in db, multiple times is also possible for example for php files
            if($count > 0){
                unset($this->sqlFilesChanged[$key]);
                continue;
            }
            $this->errors[] = 'Could not update SQL file '.print_r($file,1);
        }
        $this->logErrors();
    }
    
    /**
     * Adds the new SQL files to the DB or runs the PHP script
     * @param array $toProcess
     * @return int count of imported new files
     */
    public function applyNew(array $toProcess): int
    {
        $count = 0;
        $dbversion = ZfExtended_Factory::get('ZfExtended_Models_Db_DbVersion');
        /* @var $dbversion ZfExtended_Models_Db_DbVersion */
        
        foreach($this->sqlFilesNew as $key => $file) {
            $entryHash = $this->getEntryHash($file['origin'], $file['relativeToOrigin']);
            if(empty($toProcess[md5($entryHash)])) {
                continue;
            }
            
            if(!$this->handleFile($file)) {
                //we stop processing, print the error message and do not process the following SQL files
                break;
            }
            $count++;
            $dbversion->insert($this->getInsertData($file, ZfExtended_Utils::getAppVersion()));
            unset($this->sqlFilesNew[$key]);
        }
        //we clean up all cache files after database update since DB definitions are cached
        Zend_Registry::get('cache')->clean();
        $this->logErrors();
        return $count;
    }
    
    /**
     * Calls the desired File Handler selected by the file suffix
     * returns true on handler success, false otherwise, exception if no handler found
     * @param array $file
     * @throws ZfExtended_Exception
     * @return boolean
     */
    protected function handleFile(array $file) {
        $parts = explode('.', $file['relativeToOrigin']);
        $suffix = strtolower(end($parts));
        switch($suffix) {
            case 'sql':
                return $this->handleSqlFile($file);
            case 'php':
                return $this->handlePhpFile($file);
        }
        throw new ZfExtended_Exception("No Handler found for DB Import File: ".$file['relativeToOrigin']);
    }
    
    /**
     * returns null if all OK, other than null on error
     * @param array $file
     * @return boolean
     */
    protected function handleSqlFile($file) {
        $config = Zend_Registry::get('config');
        $db = $config->resources->db->params;
        $exec = $this->getDbExec();
        
        if(! $this->executeSqlFile($exec, $db, $file['absolutePath'], $output)) {
            $msg = 'Error on Importing a SQL file. Called command: '.$exec;
            $msg .= '; called file: '.$file['absolutePath'].".\n".'Result of Command: '.print_r($output,1);
            $this->errors[] = $msg;
            return false;
        }
        return true;
    }
    
    /**
     * Handles (includes / runs) a PHP DBUpdater Script
     * @param array $file
     * @return boolean
     */
    protected function handlePhpFile($file) {
        try {
            $config = Zend_Registry::get('config');
            $db = $config->resources->db->params;
            $argv = []; //is used and needed in the required PHP file
            $argv[] = $file['relativeToOrigin'];
            $argv[] = $db->host;
            $argv[] = $db->dbname;
            $argv[] = $db->username;
            $argv[] = $db->password;
            ob_start();
            $_HIDDEN_file = $file; //$file may be overwritten by the required PHP file.
            require $file['absolutePath'];
            $result = ob_get_flush();
            $this->log->info('E1295', 'Result of imported DbUpdater PHP File {path}: {result}', [
                'path' => $_HIDDEN_file['relativeToOrigin'],
                'result' => print_r($result,1)
            ]);
            //per default true, see attribute docu for more info
            return $this->doNotSavePhpForDebugging;
        }
        catch (Exception $e) {
            $this->errors[] = 'Error on Importing a PHP DB Updater file. Called file: '.$file['relativeToOrigin'].' Result of PHP Exception: '."\n\n".$e;
        }
        return false;
    }
    
    /**
     * Logs collected errors on importing / updating if any
     */
    protected function logErrors() {
        if(empty($this->errors)){
            return;
        }
        $this->log->error('E1294','Errors on calling database update - see details for more information.', $this->errors);
    }
    
    /**
     * returns the mysql import command for exec()
     * @throws ZfExtended_Exception
     * @return string
     */
    protected function getDbExec() {
        $config = Zend_Registry::get('config');
        $exec = $config->resources->db->executable;
        if(!isset($exec)) {
            $exec = self::MYSQL_BIN;
        }
        if(!file_exists($exec) || !is_executable($exec)) {
            throw new ZfExtended_Exception("Cant find or execute mysql excecutable ".$exec);
        }
        return $exec;
    }
    
    /**
     * creates a shell exec command
     * @param string $mysqlExecutable
     * @param mixed $credentials
     * @param bool $addFileParam optional, default true. If false the file to import is omitted
     * @return string
     */
    protected function makeSqlCmd($mysqlExecutable, $credentials, $addFileParam = true) {
        $cmd = array(escapeshellarg($mysqlExecutable));
        $cmd[] = '-h';
        $cmd[] = escapeshellarg($credentials->host);
        $cmd[] = '-u';
        $cmd[] = escapeshellarg($credentials->username);
        if(!empty($credentials->password)) {
            //escaping % in the password since the string is used as printf string
            $cmd[] = '-p'.escapeshellarg(str_replace('%', '%%', $credentials->password));
        }
        $cmd[] = '--default-character-set=utf8mb4';
        $cmd[] = escapeshellarg($credentials->dbname);
        if($addFileParam) {
            $cmd[] = '< %s';
        }
        $cmd[] = '2>&1';
        return join(' ', $cmd);
    }
    
    /**
     * executes the given SQL file with the given mysdql binary and given credentials
     * returns false if mysql cmd exit code not was 0
     * @param string $mysqlExecutable
     * @param mixed $credentials
     * @param string $file
     * @param array $output reference where mysql result is stored as array
     * @return boolean
     */
    public function executeSqlFile($mysqlExecutable, $credentials, $file, &$output) {
        //WARNING: runs in installer once before application context,
        // so no advanced functionality (logging for example) can be used here!
        $call = sprintf($this->makeSqlCmd($mysqlExecutable, $credentials), escapeshellarg($file));
        exec($call, $output, $result);
        return $result === 0;
    }
    
    /**
     * returns errors collected on updateModified and applyNew
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Applies all found changed / added DB statement files. Returns some statistics.
     * @return array
     */
    public function importAll() {
        if(!empty($this->errors)) {
            return;
        }
        $this->calculateChanges();

        $toProcess = array();
        $new = $this->getNewFiles();
        foreach($new as $file) {
            $toProcess[$file['entryHash']] = 1;
        }
        $mod = $this->getModifiedFiles();
        foreach($mod as $file) {
            $toProcess[$file['entryHash']] = 1;
        }
        
        $this->applyNew($toProcess);
        $this->updateModified($toProcess);
        return array('new' => count($new), 'modified' => count($mod));
    }
    
    /**
     * Not all environments can deal with all characters in the DB credentials.
     * This is checked here.
     *
     * @param stdClass $credentials
     * @param string $exec
     * @param string $path
     */
    protected function checkCredentials(stdClass $credentials, $exec, $path) {
        $exitFile = $path."/library/ZfExtended/database/000-exit";
        if($this->executeSqlFile($exec, $credentials, $exitFile, $output)) {
            return;
        }
        $msg = 'No connection to MySQL server through commandline. Called command: '.$exec.".\n";
        if(stripos(join("\n", $output), 'Access denied for user') === false) {
            $msg .= 'Result of Command: '.print_r($output,1);
            $this->errors[] = $msg;
            return;
        }
        $msg .= 'You could not be authenticated at the MySQL Server.';
        
        $tocheck = array(
            'host' => $credentials->host,
            'username' => $credentials->username,
            'password' => $credentials->password,
            'dbname' => $credentials->dbname,
        );
        
        $hasSpecialCharacters = false;
        foreach($tocheck as $key => $value) {
            if (!empty($value) && !preg_match('/^[A-Z0-9]+$/i', $value)) {
                $msg .= "\n".'  Your DB '.$key.' contains the following special characters: '.preg_replace('/[A-Z0-9]/i', '', $value);
                $hasSpecialCharacters = true;
            }
        }
        if($hasSpecialCharacters) {
            $msg .= "\n".'This special characters can be the reason for the problems, ';
            $msg .= "\n".'since the command-line can not deal with them properly. ';
            $msg .= "\n".'Please try to change the above mentioned values to a value without special characters, and try it again. ';
        } else {
            $msg .= "\n".'Please verify the DB credentials in the configuration file installation.ini or entered in installation process.';
        }
        $this->errors[] = $msg;
    }
}