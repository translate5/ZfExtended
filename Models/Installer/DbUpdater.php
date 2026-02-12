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

use MittagQI\ZfExtended\Models\Installer\DbUpdateFile;
use MittagQI\ZfExtended\Models\Installer\DbUpdateFileCheck;

/**
 * @package ZfExtended
 * @version 2.0
 */
class ZfExtended_Models_Installer_DbUpdater
{
    public const DB_INIT = APPLICATION_PATH . '/database/DbInit.sql';

    /**
     * contains errors on importing to DB
     */
    protected array $errors = [];

    protected array $warnings = [];

    /**
     * The sql files which are new and can be imported
     * @var DbUpdateFile[]
     */
    protected array $sqlFilesNew = [];

    /**
     * The sql files which have been changed
     * @var DbUpdateFile[]
     */
    protected array $sqlFilesChanged = [];

    /**
     * This flag is mentioned to be set from within PHP alter files to false for testing.
     * If setting this to false the file will not be marked as updated.
     * @var boolean
     */
    protected bool $doNotSavePhpForDebugging = true;

    protected ZfExtended_Logger $log;

    /**
     * Holds additional pathes to search for sql/php files
     */
    protected array $additionalPathes = [];

    /**
     * DB credentials, exec and base path must be given as parameter in usage of a non Zend Environment
     * @throws Zend_Db_Exception
     * @throws Zend_Exception
     */
    public function __construct(bool $checkCredentials = false)
    {
        if (Zend_Registry::isRegistered('logger')) {
            $this->log = Zend_Registry::get('logger')->cloneMe('system.database.update');
            if ($checkCredentials) {
                $this->checkCredentials();
            }
        }
    }

    /**
     * Forcing all available SQL files to be set as imported in the DB, regardless if the contents were really applied or not.
     * For setting up dbversioning on instances where all SQL files are already installed.
     */
    public function assumeImported(array $toProcess)
    {
        $dbversion = ZfExtended_Factory::get(ZfExtended_Models_Db_DbVersion::class);

        foreach ($this->getFoundFiles() as $file) {
            $entryHash = $this->getEntryHash($file->origin, $file->relativeToOrigin);
            if (empty($toProcess[md5($entryHash)])) {
                continue;
            }
            $path = $file->absolutePath;
            if (! file_exists($path) || ! is_readable($path)) {
                $this->log->error('E1293', 'The following file does not exist or is not readable and is therefore ignored: {path}', [
                    'path' => $path,
                ]);

                continue;
            }
            $dbversion->insert($this->getInsertData($file, ZfExtended_Utils::getAppVersion()));
        }
    }

    /**
     * returns an array ready for DB Insertion in dbversion
     */
    protected function getInsertData(DbUpdateFile $file, string $appVersion): array
    {
        return [
            'origin' => $file->origin,
            'filename' => $file->relativeToOrigin,
            'md5' => $this->filehash($file->absolutePath),
            'appVersion' => $appVersion,
        ];
    }

    /**
     * loops over all configured SQL directories and find new or modified SQL files compared to the version in the DB.
     */
    public function calculateChanges(): array
    {
        $this->sqlFilesNew = [];
        $this->sqlFilesChanged = [];
        $dbversion = ZfExtended_Factory::get(ZfExtended_Models_Db_DbVersion::class);

        $installed = $dbversion->fetchAll();
        $installHashed = [];
        foreach ($installed as $row) {
            $installHashed[$this->getEntryHash($row->origin, $row->filename)] = $row->md5;
        }
        $usedPathes = null;
        foreach ($this->getFoundFiles($usedPathes) as $file) {
            $entryHash = $this->getEntryHash($file->origin, $file->relativeToOrigin);
            $file->entryHash = md5($entryHash);
            if (empty($installHashed[$entryHash])) {
                $this->sqlFilesNew[] = $file;

                continue;
            }
            if ($installHashed[$entryHash] !== $this->filehash($file->absolutePath)) {
                $this->sqlFilesChanged[] = $file;
            }
        }

        return $usedPathes;
    }

    /**
     * loops over all configured SQL directories and handles all files as new
     */
    public function calculateAllAsNew(): array
    {
        $this->sqlFilesNew = [];
        $this->sqlFilesChanged = [];
        $usedPathes = null;
        foreach ($this->getFoundFiles($usedPathes) as $file) {
            $entryHash = $this->getEntryHash($file->origin, $file->relativeToOrigin);
            $file->entryHash = md5($entryHash);
            $this->sqlFilesNew[] = $file;
        }

        return $usedPathes;
    }

    /**
     * @return DbUpdateFile[]
     * @throws Zend_Exception
     * @throws ZfExtended_Exception
     * @throws ZfExtended_NoAccessException
     */
    protected function getFoundFiles(array &$usedPathes = null): array
    {
        $filefinder = ZfExtended_Factory::get(ZfExtended_Models_Installer_DbFileFinder::class);

        $usedPathes = array_merge($filefinder->getSearchPathList(), $this->additionalPathes);

        return $filefinder->getSqlFilesOrdered($this->additionalPathes);
    }

    /**
     * returns the modified SQL files found by self::calculateChanges()
     * @return DbUpdateFile[]
     */
    public function getModifiedFiles(): array
    {
        return $this->sqlFilesChanged;
    }

    /**
     * returns the modified SQL files found by self::calculateChanges()
     * @return DbUpdateFile[]
     */
    public function getNewFiles(): array
    {
        return $this->sqlFilesNew;
    }

    /**
     * returns a hash value for thi given absolute file path
     * @param string $filepath
     * @return string
     */
    protected function filehash($filepath)
    {
        return md5_file($filepath);
    }

    /**
     * makes a simple hash out of the given parameters
     * @param string $origin
     * @param string $filename
     * @return string
     */
    protected function getEntryHash($origin, $filename)
    {
        return $origin . '#' . $filename;
    }

    /**
     * all modified files are marked as up to date in the database
     * Since this should happen only on DEV systems we log it directly into the error_log
     */
    public function updateModified(array $toProcess)
    {
        $dbversion = ZfExtended_Factory::get(ZfExtended_Models_Db_DbVersion::class);

        foreach ($this->sqlFilesChanged as $key => $file) {
            $entryHash = $this->getEntryHash($file->origin, $file->relativeToOrigin);
            if (empty($toProcess[md5($entryHash)])) {
                continue;
            }

            $a = $dbversion->getAdapter();
            $where = [
                $a->quoteInto('origin = ?', $file->origin),
                $a->quoteInto('filename = ?', $file->relativeToOrigin),
            ];

            $count = $dbversion->update([
                'md5' => $this->filehash($file->absolutePath),
            ], $where);
            //the file must be changed at least one time in db, multiple times is also possible for example for php files
            if ($count > 0) {
                unset($this->sqlFilesChanged[$key]);

                continue;
            }
            $this->errors[] = 'Could not update SQL file ' . print_r($file, 1);
        }
        $this->logErrors();
    }

    /**
     * Adds the new SQL files to the DB or runs the PHP script
     * @return int count of imported new files
     * @throws ReflectionException
     * @throws Zend_Cache_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Exception
     */
    public function applyNew(array $toProcess): int
    {
        $count = 0;
        $dbversion = ZfExtended_Factory::get(ZfExtended_Models_Db_DbVersion::class);
        $appliedFiles = [];

        foreach ($this->sqlFilesNew as $key => $file) {
            $entryHash = $this->getEntryHash($file->origin, $file->relativeToOrigin);
            if (empty($toProcess[md5($entryHash)])) {
                continue;
            }

            $duration = microtime(true);
            if (! $this->handleFile($file)) {
                //we stop processing, print the error message and do not process the following SQL files
                break;
            }
            $file->duration = round(microtime(true) - $duration, 3) . ' s';
            $appliedFiles[$entryHash] = $file;
            $count++;
            $dbversion->insert($this->getInsertData($file, ZfExtended_Utils::getAppVersion()));
            unset($this->sqlFilesNew[$key]);
        }
        //we clean up all cache files after database update since DB definitions are cached
        Zend_Registry::get('cache')->clean();
        $this->logErrors($appliedFiles);

        return $count;
    }

    /**
     * Checks the given DB-update files for unwanted or dangerous SQL
     * @throws ZfExtended_Exception
     */
    public function checkNewFiles(): void
    {
        foreach ($this->sqlFilesNew as $file) {
            $checker = new DbUpdateFileCheck($file->absolutePath);
            if ($checker->checkAndSanitize() === null) {
                $this->errors[] = $checker->getError();
            }
        }
    }

    /**
     * Calls the desired File Handler selected by the file suffix
     * returns true on handler success, false otherwise, exception if no handler found
     * @throws ZfExtended_Exception
     * @return boolean
     */
    protected function handleFile(DbUpdateFile $file): bool
    {
        $parts = explode('.', $file->relativeToOrigin);
        $suffix = strtolower(end($parts));
        switch ($suffix) {
            case 'sql':
                return $this->handleSqlFile($file);
            case 'php':
                return $this->handlePhpFile($file);
        }

        throw new ZfExtended_Exception("No Handler found for DB Import File: " . $file->relativeToOrigin);
    }

    /**
     * returns null if all OK, other than null on error
     */
    protected function handleSqlFile(DbUpdateFile $file): bool
    {
        if (! $this->executeSqlFile($file->absolutePath)) {
            $this->errors[] = 'Error on Importing a SQL file, called file: ' . $file->absolutePath;

            return false;
        }

        return true;
    }

    /**
     * Handles (includes / runs) a PHP DBUpdater Script
     */
    protected function handlePhpFile(DbUpdateFile $file): bool
    {
        try {
            $config = Zend_Registry::get('config');
            $db = $config->resources->db->params;
            $argv = []; //is used and needed in the required PHP file
            $argv[] = $file->relativeToOrigin;
            $argv[] = $db->host;
            $argv[] = $db->dbname;
            $argv[] = $db->username;
            $argv[] = $db->password;
            ob_start();
            $_HIDDEN_file = $file; //$file may be overwritten by the required PHP file.
            require $file->absolutePath;
            $result = ob_get_flush();
            $this->log->info('E1295', 'Result of imported DbUpdater PHP File {path}: {result}', [
                'path' => $_HIDDEN_file->relativeToOrigin,
                'result' => print_r($result, 1),
            ]);

            //per default true, see attribute docu for more info
            return $this->doNotSavePhpForDebugging;
        } catch (Exception $e) {
            $this->errors[] = 'Error on Importing a PHP DB Updater file. Called file: ' . $file->relativeToOrigin . ' Result of PHP Exception: ' . "\n\n" . $e;
        }

        return false;
    }

    /**
     * Logs collected errors on importing / updating if any
     */
    protected function logErrors(array $appliedFiles = []): void
    {
        if (! empty($this->errors)) {
            $this->log->error(
                'E1294',
                'Errors on calling database update - see details for more information.',
                $this->errors
            );
        }
        if (! empty($this->warnings)) {
            $this->log->warn(
                'E1294',
                'Warnings on calling database update - see details for more information.',
                $this->warnings
            );
        }
        if (! empty($appliedFiles)) {
            $this->log->info(
                'E1294',
                'Applied {count} files to the database.',
                [
                    'count' => count($appliedFiles),
                    'filesDuration' => $appliedFiles,
                ]
            );
        }
    }

    /**
     * executes the given SQL file
     *
     * @throws Zend_Db_Exception
     * @throws Zend_Exception
     */
    protected function executeSqlFile(string $file): bool
    {
        //WARNING: runs in installer once before application context,
        // so no advanced functionality (logging for example) can be used here!

        $config = Zend_Registry::get('config');
        $db = Zend_Db::factory($config->resources->db->adapter, $config->resources->db->params->toArray());

        $checker = new DbUpdateFileCheck($file);
        $sql = $checker->checkAndSanitize();
        if ($sql === null) {
            $this->errors[] = $checker->getError();

            return false;
        }

        try {
            $stmt = $db->prepare($sql);
            if ($stmt->execute()) {
                $stmt->closeCursor();
                $warnings = $db->query('SHOW WARNINGS');
                foreach ($warnings->fetchAll() as $warning) {
                    $this->warnings[] = join(', ', $warning) . ' in file ' . $file;
                }
                // may the DB-update makes a renewal of the materialized views neccessary
                // HINT: this is done for every DB-update file - what does not hurt because once all
                // materialized views are dropped, no materialized views are found anymore ...
                if ($checker->hasSegmentTablesChanges()) {
                    $this->dropAllMaterializedViews($db);
                }

                return true;
            }
        } catch (Throwable $e) {
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
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * returns SQL warnings occurred on usage
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * returns errors collected on updateModified and applyNew
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * returns SQL warnings occurred on usage
     */
    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * Applies all found changed / added DB statement files. Returns some statistics.
     */
    public function importAll(): array
    {
        if (! empty($this->errors)) {
            return [];
        }
        $this->calculateChanges();

        $toProcess = [];
        $new = $this->getNewFiles();
        foreach ($new as $file) {
            $toProcess[$file->entryHash] = 1;
        }
        $mod = $this->getModifiedFiles();
        foreach ($mod as $file) {
            $toProcess[$file->entryHash] = 1;
        }

        $newDone = $this->applyNew($toProcess);
        $this->updateModified($toProcess);
        if ($newDone < count($new)) {
            $this->errors[] = 'There are remaining DB files to be processed!' . $newDone . ' # ' . count($new);
        }

        return [
            'new' => count($new),
            'modified' => count($mod),
            'newProcessed' => $newDone,
        ];
    }

    public function deinstallPlugin(string $pluginName): array
    {
        $filefinder = ZfExtended_Factory::get(ZfExtended_Models_Installer_DbFileFinder::class);
        $toProcess = $filefinder->findDeinstallFiles($pluginName);
        $applied = $this->applyNew($toProcess);
        $toProcessCount = count($toProcess);

        if ($applied < $toProcessCount) {
            $this->errors[] = 'There are remaining DB files to be processed!' . $applied . ' # ' . $toProcessCount;
        }

        return [
            'new' => $toProcessCount,
            'modified' => 0,
            'newProcessed' => $applied,
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
        $exitFile = APPLICATION_ROOT . "/library/ZfExtended/database/000-exit";
        if ($this->executeSqlFile($exitFile)) {
            return;
        }
        $msg = 'No connection to MySQL server through commandline.';
        if (stripos(join("\n", $this->getErrors()), 'Access denied for user') === false) {
            $this->errors[] = $msg;

            return;
        }
        $msg .= 'You could not be authenticated at the MySQL Server.';

        $config = Zend_Registry::get('config');
        $params = $config->resources->db->params->toArray();

        $hasSpecialCharacters = false;
        foreach ($params as $key => $value) {
            if (! empty($value) && is_string($value) && ! preg_match('/^[A-Z0-9]+$/i', $value)) {
                $msg .= "\n" . '  Your DB ' . $key . ' contains the following special characters: ' . preg_replace('/[A-Z0-9]/i', '', $value);
                $hasSpecialCharacters = true;
            }
        }
        if ($hasSpecialCharacters) {
            $msg .= "\n" . 'This special characters can be the reason for the problems, ';
            $msg .= "\n" . 'since the command-line can not deal with them properly. ';
            $msg .= "\n" . 'Please try to change the above mentioned values to a value without special characters, and try it again. ';
        } else {
            $msg .= "\n" . 'Please verify the DB credentials in the configuration file installation.ini or entered in installation process.';
        }
        $this->errors[] = $msg;
    }

    /**
     * function for generating output when using PHP alter scripts
     */
    public function output(string $msg): void
    {
        echo $msg . "\n";
        error_log($msg);
    }

    public function addAdditonalSqlPath(string $path): void
    {
        $this->additionalPathes[] = $path;
    }

    /**
     * Creates an empty database with the configured DB name, drops the same named existing one if requested
     */
    public function createDatabase(\ZfExtended_Models_Installer_DbConfig $dbConfig, bool $dropIfExists = false): void
    {
        // we need to use PDO, Zend works only with databases
        $pdo = new \PDO($dbConfig->toPdoString(omitField: ['dbname']), $dbConfig->username, $dbConfig->password);

        if ($dropIfExists) {
            $pdo->query('DROP DATABASE IF EXISTS ' . $dbConfig->dbname . ';');
        }

        //default character set utf8mb4 collate utf8mb4_unicode_ci
        $sql = 'CREATE DATABASE %s DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';

        // now create DB from scratch
        $pdo->query(sprintf($sql, $dbConfig->dbname));
    }

    /**
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Db_Exception
     */
    public function isDbEmpty(): bool
    {
        $config = Zend_Registry::get('config');
        $db = Zend_Db::factory($config->resources->db->adapter, $config->resources->db->params->toArray());
        $tables = $db->query('SHOW TABLES');

        return count($tables->fetchAll()) === 0;
    }

    /**
     * To be used in PHP scripts to check, if updates/transformations,
     * that potentially harm or disturb test-runs and are not needed for Empty DBs
     * can be skipped
     */
    public function isTestOrInstallEnvironment(): bool
    {
        return (defined('DATABASE_RECREATION') && DATABASE_RECREATION);
    }

    /**
     * @throws Zend_Exception
     * @throws Zend_Db_Exception
     */
    public function dropSegmentMaterializedViews(): void
    {
        $config = Zend_Registry::get('config');
        $db = Zend_Db::factory($config->resources->db->adapter, $config->resources->db->params->toArray());
        $this->dropAllMaterializedViews($db);
    }

    /**
     * Helper to drop all materialized views
     */
    private function dropAllMaterializedViews(Zend_Db_Adapter_Abstract $db)
    {
        $conf = $db->getConfig();
        $res = $db->query('SHOW TABLES FROM `' . $conf['dbname'] . '` LIKE "LEK_segment_view_%"');
        $tables = $res->fetchAll(Zend_Db::FETCH_NUM);
        if (! empty($tables)) {
            foreach ($tables as $table) {
                $tableName = $table[0];
                $db->query('DROP TABLE IF EXISTS `' . $tableName . '`');
            }
        }
        $this->log->info('E1773', 'Dropped all materialized views. Drop count: {count}', [
            'count' => count($tables),
        ]);
    }
}
