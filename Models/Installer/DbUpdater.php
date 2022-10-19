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

/**
 * @package ZfExtended
 * @version 2.0
 */
class ZfExtended_Models_Installer_DbUpdater {

    const DB_INIT = APPLICATION_PATH.'/database/DbInit.sql';

    /**
     * contains errors on importing to DB
     * @var array
     */
    protected array $errors = [];

    protected array $warnings = [];
    
    /**
     * The sql files which are new and can be imported
     * @var array
     */
    protected array $sqlFilesNew = [];
    
    /**
     * The sql files which have been changed
     * @var array
     */
    protected array $sqlFilesChanged = [];
    
    /**
     * This flag is mentioned to be set from within PHP alter files to false for testing.
     * If setting this to false the file will not be marked as updated.
     * @var boolean
     */
    protected bool $doNotSavePhpForDebugging = true;
    
    /**
     * @var ZfExtended_Logger
     */
    protected ZfExtended_Logger $log;

    /**
     * Holds additional pathes to search for sql/php files
     */
    protected array $additionalPathes = [];

    /**
     * DB credentials, exec and base path must be given as parameter in usage of a non Zend Environment
     * @param bool $checkCredentials
     * @throws Zend_Db_Exception
     * @throws Zend_Exception
     */
    public function __construct(bool $checkCredentials = false) {
        if(Zend_Registry::isRegistered('logger')) {
            $this->log = Zend_Registry::get('logger')->cloneMe('core.database.update');
            if($checkCredentials) {
                $this->checkCredentials();
            }
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
    public function calculateChanges(): array {
        $this->sqlFilesNew = array();
        $this->sqlFilesChanged = array();
        $dbversion = ZfExtended_Factory::get('ZfExtended_Models_Db_DbVersion');
        /* @var $dbversion ZfExtended_Models_Db_DbVersion */
        $installed = $dbversion->fetchAll();
        $installHashed = array();
        foreach($installed as $row) {
            $installHashed[$this->getEntryHash($row->origin, $row->filename)] = $row->md5;
        }
        $usedPathes = null;
        foreach($this->getFoundFiles($usedPathes) as $file) {
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
        return $usedPathes;
    }

    /**
     * @param array|null $usedPathes: reference that can be used to retrieve the used pathes
     * @return array
     * @throws Zend_Exception
     * @throws ZfExtended_Exception
     * @throws ZfExtended_NoAccessException
     */
    protected function getFoundFiles(array &$usedPathes = null): array
    {
        $filefinder = ZfExtended_Factory::get('ZfExtended_Models_Installer_DbFileFinder');
        /* @var $filefinder ZfExtended_Models_Installer_DbFileFinder */
        $usedPathes = array_merge($filefinder->getSearchPathList(), $this->additionalPathes);
        return $filefinder->getSqlFilesOrdered($this->additionalPathes);
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
        if(! $this->executeSqlFile($file['absolutePath'])) {
            $msg = 'Error on Importing a SQL file.';
            $msg .= '; called file: '.$file['absolutePath'];
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
        if(! empty($this->errors)){
            $this->log->error('E1294','Errors on calling database update - see details for more information.', $this->errors);
        }
        if(! empty($this->warnings)){
            $this->log->warn('E1294','Warnings on calling database update - see details for more information.', $this->warnings);
        }
    }

    /**
     * executes the given SQL file
     *
     * @param string $file
     * @param array $output reference where mysql result is stored as array
     * @return boolean
     * @throws Zend_Db_Exception
     * @throws Zend_Exception
     */
    protected function executeSqlFile(string $file): bool {
        //WARNING: runs in installer once before application context,
        // so no advanced functionality (logging for example) can be used here!

        $config = Zend_Registry::get('config');
        $db = Zend_Db::factory($config->resources->db->adapter, $config->resources->db->params->toArray());
        $sql = file_get_contents($file);

        // Replace CRLF line endings with LF, as otherwise below preg replace won't work
        $sql = preg_replace('~\r\n~', "\n", $sql);

        //remove DELIMITER statements and replace back the ;; delimiter to ;
        // reason is that it is not needed and not usable for PHP import
        $sql = preg_replace_callback('/^DELIMITER ;;$(.*?)^DELIMITER ;$/ms', function ($matches){
            return preg_replace('#;;$#', ';', $matches[1]);
        }, $sql);

        //prevent DEFINER=root`@`localhost` from TRIGGERs and VIEWs to be executed
        if(str_contains($sql, 'DEFINER=')) {
            $this->errors[] = 'The file '.$file.' contains DEFINER= statements, they must be removed manually before!';
            return false;
        }
        //prevent DELIMITER statements in the code (https://stackoverflow.com/a/5314879/1749200):
        if(str_contains($sql, 'DELIMITER')) {
            $this->errors[] = 'The file '.$file.' contains DELIMITER statements, they must be removed since not needed and not usable by PHP based import!';
            return false;
        }

        try {
            $stmt = $db->prepare($sql);
            if($stmt->execute()) {
                $stmt->closeCursor();
                $warnings = $db->query('SHOW WARNINGS');
                foreach($warnings->fetchAll() as $warning) {
                    $this->warnings[] = join(', ', $warning).' in file '.$file;
                }
                return true;
            }
        }
        catch (Throwable $e) {
            $this->errors[] = $e->getMessage();
        }
        return false;
    }

    /**
     * @throws Zend_Exception
     * @throws Zend_Db_Exception
     */
    public function initDb(): bool
    {
        return $this->executeSqlFile(self::DB_INIT);
    }
    
    /**
     * returns errors collected on updateModified and applyNew
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * returns SQL warnings occurred on usage
     */
    public function getWarnings(): array {
        return $this->warnings;
    }

    /**
     * returns errors collected on updateModified and applyNew
     */
    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    /**
     * returns SQL warnings occurred on usage
     */
    public function hasWarnings(): bool {
        return !empty($this->warnings);
    }

    /**
     * Applies all found changed / added DB statement files. Returns some statistics.
     * @return array
     */
    public function importAll(): array
    {
        if(!empty($this->errors)) {
            return [];
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
        
        $newDone = $this->applyNew($toProcess);
        $this->updateModified($toProcess);
        if($newDone < count($new)) {
            $this->errors[] = 'There are remaining DB files to be processed!'. $newDone.' # '.count($new);
        }

        return [
            'new' => count($new),
            'modified' => count($mod),
            'newProcessed' => $newDone,
        ];
    }

    /**
     * Not all environments can deal with all characters in the DB credentials.
     * This is checked here.
     *
     * @throws Zend_Db_Exception
     * @throws Zend_Exception
     */
    protected function checkCredentials(): void
    {
        $exitFile = APPLICATION_ROOT."/library/ZfExtended/database/000-exit";
        if($this->executeSqlFile($exitFile)) {
            return;
        }
        $msg = 'No connection to MySQL server through commandline.';
        if(stripos(join("\n", $this->getErrors()), 'Access denied for user') === false) {
            $this->errors[] = $msg;
            return;
        }
        $msg .= 'You could not be authenticated at the MySQL Server.';

        $config = Zend_Registry::get('config');
        $params = $config->resources->db->params->toArray();

        $hasSpecialCharacters = false;
        foreach($params as $key => $value) {
            if (!empty($value) && is_string($value) && !preg_match('/^[A-Z0-9]+$/i', $value)) {
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

    /**
     * function for generating output when using PHP alter scripts
     * @param string $msg
     */
    public function output(string $msg): void
    {
        echo $msg."\n";
        error_log($msg);
    }

    /**
     * @param string $path
     */
    public function addAdditonalSqlPath(string $path): void
    {
        $this->additionalPathes[] = $path;
    }

    /**
     * Creates an empty database with the configured DB name, drops the same named existing one if requested
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $dbname
     * @param bool $dropIfExists
     */
    public function createDatabase(string $host, string $username, string $password, string $dbname, bool $dropIfExists = false): void
    {
        // we need to use PDO, Zend works only with databases
        $pdo = new \PDO('mysql:host=' . $host, $username, $password);

        if ($dropIfExists) {
            $pdo->query('DROP DATABASE IF EXISTS ' . $dbname . ';');
        }

        //default character set utf8mb4 collate utf8mb4_unicode_ci
        $sql = 'CREATE DATABASE %s DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';

        // now create DB from scratch
        $pdo->query(sprintf($sql, $dbname));
    }
}