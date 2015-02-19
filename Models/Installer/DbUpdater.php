<?php
 /*
   START LICENSE AND COPYRIGHT

  This file is part of the ZfExtended library and build on Zend Framework

  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

  This file is multi-licensed under the EPL, LGPL and GPL licenses. 

  It may be used under the terms of the Eclipse Public License - v 1.0
  as published by the Eclipse Foundation and appearing in the file eclipse-license.txt 
  included in the packaging of this file.  Please review the following information 
  to ensure the Eclipse Public License - v 1.0 requirements will be met:
  http://www.eclipse.org/legal/epl-v10.html.

  Also it may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
  or the GNU GENERAL PUBLIC LICENSE version 3 as published by the 
  Free Software Foundation, Inc. and appearing in the files lgpl-license.txt and gpl3-license.txt
  included in the packaging of this file.  Please review the following information 
  to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3 requirements or the
  GNU GENERAL PUBLIC LICENSE version 3 requirements will be met:
  http://www.gnu.org/licenses/lgpl.html   /  http://www.gnu.org/licenses/gpl.html
  
  @copyright  Marc Mittag, MittagQI - Quality Informatics
  @author     MittagQI - Quality Informatics
  @license    Multi-licensed under Eclipse Public License - v 1.0 http://www.eclipse.org/legal/epl-v10.html, GNU LESSER GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/lgpl.html and GNU GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/gpl.html

  END LICENSE AND COPYRIGHT 
 */

/**
 * @package ZfExtended
 * @version 2.0
 */
class ZfExtended_Models_Installer_DbUpdater {
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
     * default executable of the mysql command, can be overwritten by config
     * @var string
     */
    protected $mysqlBin = '/usr/bin/mysql';
    
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
                $this->log('The following file does not exist or is not readable and is therefore ignored: '.$path);
                continue;
            }
            $version = 'INITIAL'; //FIXME with TRANSLATE-131
            //$dbversion->delete(true);
            $dbversion->insert($this->getInsertData($file, $version));
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
        //FIXME update appVersion also after TRANSLATE-131
        
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
            if($count === 1){
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
     */
    public function applyNew(array $toProcess) {
        $dbversion = ZfExtended_Factory::get('ZfExtended_Models_Db_DbVersion');
        /* @var $dbversion ZfExtended_Models_Db_DbVersion */
        
        foreach($this->sqlFilesNew as $key => $file) {
            $entryHash = $this->getEntryHash($file['origin'], $file['relativeToOrigin']);
            if(empty($toProcess[md5($entryHash)])) {
                continue;
            }
            
            if(!$this->handleFile($file)) {
                continue;
            }
            $data = array();
            $version = 'UPDATED';
            //FIXME set correct $version after TRANSLATE-131
            $dbversion->insert($this->getInsertData($file, $version));
            unset($this->sqlFilesNew[$key]);
        }
        $this->logErrors();
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
        $cmd = $this->getDbCommand();
        $call = sprintf($cmd, escapeshellarg($file['absolutePath']));
        exec($call, $output, $result);
        if($result > 0) {
            $this->errors[] = 'Error on Importing a SQL file. Called command: '.$call.".\n".'Result of Command: '.print_r($output,1);
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
            $argv = array();
            $argv[] = $file['relativeToOrigin'];
            $argv[] = $db->host;
            $argv[] = $db->dbname;
            $argv[] = $db->username;
            $argv[] = $db->password;
            ob_start();
            require $file['absolutePath'];
            $result = ob_get_flush();
            error_log('Result of imported DbUpdater PHP File '.$file['relativeToOrigin'].': '.print_r($result,1));
            return true;
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
        foreach($this->errors as $error) {
            $this->log($error);
        }
    }
    
    /**
     * returns the mysql import command for exec() 
     * @throws ZfExtended_Exception
     * @return string
     */
    protected function getDbCommand() {
        $config = Zend_Registry::get('config');
        $db = $config->resources->db->params;
        $exec = $config->resources->db->executable;
        if(!isset($exec)) {
            $exec = $this->mysqlBin;
        }
        if(!file_exists($exec) || !is_executable($exec)) {
            throw new ZfExtended_Exception("Cant find or execute mysql excecutable ".$exec);
        }
        $cmd = array($this->mysqlBin);
        $cmd[] = '-h';
        $cmd[] = escapeshellarg($db->host);
        $cmd[] = '-u';
        $cmd[] = escapeshellarg($db->username);
        $cmd[] = '-p'.escapeshellarg($db->password);
        $cmd[] = escapeshellarg($db->dbname);
        $cmd[] = '< %s 2>&1';
        return join(' ', $cmd);
    }
    
    /**
     * returns errors collected on updateModified and applyNew
     */
    public function getErrors() {
        return $this->errors;
    }
    
    protected function log($msg) {
        //@todo send this to a installer wide logging
        error_log($msg);
    }
}