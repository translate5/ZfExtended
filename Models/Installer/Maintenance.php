<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3 requirements will be met:
 http://www.gnu.org/licenses/agpl.html
  
 There is a plugin exception available for use with this release of translate5 for
 translate5: Please see http://www.translate5.net/plugin-exception.txt or 
 plugin-exception.txt in the root folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execption
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/**#@+
 * @author Marc Mittag
 * @package portal
 * @version 2.0
 *
 */
/**
 */
class Models_Installer_Maintenance {
    
    /**
     * @var Zend_Config
     */
    protected $config;
    
    /**
     * @var Zend_Db_Adapter_Abstract
     */
    protected $db;
    
    public function __construct() {
        $this->config = Zend_Registry::get('config');
        $this->db = Zend_Db::factory($this->config->resources->db);
        
        if($this->config->runtimeOptions->maintenance->startDate != $this->getStartDateFromDb()) {
            die("\nError: There is some maintenance configuration in the installation.ini, \n please remove it for proper usage of this tool!\n\n");
        }
    }
    
    public function disable() {
        $this->db->query("UPDATE `Zf_configuration` SET `value` = null WHERE `name` = 'runtimeOptions.maintenance.startDate'");
        $this->status();
    }
    
    public function status() {
        $startDate = $this->getStartDateFromDb();
        if(empty($startDate)) {
            die("\n Maintenance mode disabled!\n\n");
        }
        $conf = $this->config->runtimeOptions->maintenance;
        $startTimeStamp = strtotime($startDate);
        $now = time();
        echo "\n";
        if($startTimeStamp < $now) {
            echo " \033[1;31mMaintenance mode active!\033[00m\n\n";
        }
        
        elseif ($startTimeStamp - ($conf->timeToNotify*60) < $now){
            echo " \033[1;33mMaintenance mode notified!\033[00m\n\n";
        }
        echo "  Maintenance mode start:         ".date('Y-m-d H:i (O)', $startTimeStamp)."\n";
        echo "  Maintenance mode start notify:  ".date('Y-m-d H:i (O)', $startTimeStamp - ($conf->timeToNotify*60))."\n";
        echo "  Maintenance mode login lock:    ".date('Y-m-d H:i (O)', $startTimeStamp - ($conf->timeToLoginLock*60))."\n";
        echo "\n";
    }
    
    /**
     * returns the configured start date from DB, false if no entry found
     * @return string|boolean
     */
    protected function getStartDateFromDb() {
        $res = $this->db->query('SELECT value FROM `Zf_configuration` WHERE `name` = "runtimeOptions.maintenance.startDate"');
        if($startDate = $res->fetchObject()) {
            return $startDate->value;
        }
        return false;
    }
    
    public function set($time) {
        $timeStamp = strtotime($time);
        if(!$timeStamp) {
            echo 'Given time parameter "'.$time.'" can not be parsed to a valid timestamp!';
            return;
        }
        $timeStamp = date('Y-m-d H:i', $timeStamp);
        $this->db->query("UPDATE `Zf_configuration` SET `value` = ? WHERE `name` = 'runtimeOptions.maintenance.startDate'", $timeStamp);
        $this->status();
    }
    
    public function checkUsers() {
        session_start();
        $config = Zend_Registry::get('config');
        $db = Zend_Db::factory($config->resources->db);
        $result = $db->query('SELECT count(*) active FROM session where modified + lifetime > unix_timestamp()');
        $activeSessions = $result->fetchObject()->active;
        
        $result = $db->query('SELECT count(*) active FROM session where modified + 3600 > unix_timestamp()');
        $lastHourSessions = $result->fetchObject()->active;
        
        echo "Session Summary:\n";
        echo "Active Sessions:               ".$activeSessions."\n";
        echo "Active Sessions (last hour):   ".$lastHourSessions."\n";
        
        //$result = $db->query('SELECT session_data FROM session where modified + lifetime > unix_timestamp()');
        $result = $db->query('SELECT * FROM session where modified + 3600 > unix_timestamp()');
        
        echo "Session Users (last hour):\n";
        while($row = $result->fetchObject()) {
            session_decode($row->session_data);
            if(!empty($_SESSION['user']) && !empty($_SESSION['user']['data']) && !empty($_SESSION['user']['data']->login)){
                $data = $_SESSION['user']['data'];
                settype($data->firstName, 'string');
                settype($data->surName, 'string');
                settype($data->login, 'string');
                settype($data->email, 'string');
                $username = $data->firstName.' '.$data->surName.' ('.$data->login.': '.$data->email.')';
                echo "                               ".$username."\n";
            }
            else {
                echo "                               No User\n";
            }
        }
        session_destroy();
    }
    
    public function checkWorkers() {
        $config = Zend_Registry::get('config');
        $db = Zend_Db::factory($config->resources->db);
        $result = $db->query('SELECT count(*) cnt, state FROM Zf_worker group by state');
        echo "Workers:\n";
        while($row = $result->fetchObject()) {
            echo "        ".str_pad($row->state, 23).$row->cnt."\n";
        }
    }
}