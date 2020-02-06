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
        
        if($this->config->runtimeOptions->maintenance->startDate != $this->getConfFromDb()->startDate) {
            die("\nError: There is some maintenance configuration in the installation.ini, \n please remove it for proper usage of this tool!\n\n");
        }
    }
    
    public function announce($time, $msg = '') {        
        $receiver = $this->config->runtimeOptions->maintenance->announcementMail ?? '';
        $preventDuplicates = [];
        if(empty($receiver)) {
            die('No receiver groups/users set in runtimeOptions.maintenance.announcementMail, so no email sent!'."\n\n");
        }
        $receiver = explode(',', $receiver);
        $plainUsers = [];
        $receiverGroups = array_filter($receiver, function($item) use (&$plainUsers) {
            $item = explode(':', $item);
            if(count($item) == 1) {
                return true;
            }
            $plainUsers[] = end($item);
            return false;
        });
        
        $startTimeStamp = strtotime($time);
        
        Zend_Registry::set('module', 'editor'); // fix to load correct mail paths
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en')); // fix to prevent error message
        $mailer = ZfExtended_Factory::get('ZfExtended_TemplateBasedMail');
        /* @var $mailer ZfExtended_TemplateBasedMail */
        $mailer->setParameters([
            'appName' => $this->config->runtimeOptions->appName,
            'maintenanceDate' => date('Y-m-d H:i (O)', $startTimeStamp),
            'message' => $msg,
        ]);
        $mailer->setTemplate('announceMaintenance.phtml');
        
        $user = ZfExtended_Factory::get('ZfExtended_Models_User');
        /* @var $user ZfExtended_Models_User */
        $receivers = $user->loadAllByRole($receiverGroups);
        
        
        foreach($plainUsers as $login) {
            try {
                $receivers[] = $user->loadByLogin($login)->toArray();
            }
            catch(ZfExtended_Models_Entity_NotFoundException $e) {
                echo "There is a non existent user '$login' in the runtimeOptions.maintenance.announcementMail configuration!\n";
            }
        }
        
        echo "Send maintenance announcement mails to:\n";
        foreach($receivers as $userData) {
            $user->init($userData);
            if(in_array($user->getEmail(), $preventDuplicates)) {
                continue;
            }
            $preventDuplicates[] = $user->getEmail();
            echo "  ".$user->getUsernameLong().' '.$user->getEmail()."\n";
            $mailer->sendToUser($user);
        }
    }
    
    public function disable() {
        $this->db->query("UPDATE `Zf_configuration` SET `value` = null WHERE `name` = 'runtimeOptions.maintenance.startDate'");
        $this->db->query("UPDATE `Zf_configuration` SET `value` = null WHERE `name` = 'runtimeOptions.maintenance.message'");
        $this->status();
    }
    
    public function status() {
        $conf = $this->getConfFromDb();
        if(empty($conf->startDate)) {
            die("\n Maintenance mode disabled!\n\n");
        }
        $startTimeStamp = strtotime($conf->startDate);
        $now = time();
        echo "\n";
        if($startTimeStamp < $now) {
            echo " \033[1;31mMaintenance mode active!\033[00m\n\n";
        }
        
        elseif ($startTimeStamp - ($conf->timeToNotify*60) < $now){
            echo " \033[1;33mMaintenance mode notified!\033[00m\n\n";
        }
        echo "         start: ".date('Y-m-d H:i (O)', $startTimeStamp)."\n";
        echo "  start notify: ".date('Y-m-d H:i (O)', $startTimeStamp - ($conf->timeToNotify*60))."\n";
        echo "    login lock: ".date('Y-m-d H:i (O)', $startTimeStamp - ($conf->timeToLoginLock*60))."\n";
        echo "       message: ".$conf->message."\n";
        echo "     receivers: ".$conf->announcementMail."\n";
        echo "\n";
    }
    
    /**
     * returns the configured start date from DB, false if no entry found
     * @return string|boolean
     */
    protected function getConfFromDb() {
        $result = new stdClass();
        $result->message = null;
        $result->startDate = null;
        $result->timeToNotify = null;
        $result->timeToLoginLock = null;
        $result->announcementMail = null;
        
        $res = $this->db->query('SELECT name, value FROM `Zf_configuration` WHERE `name` like "runtimeOptions.maintenance.%"');
        $conf = $res->fetchAll();
        foreach($conf as $row) {
            $name = explode('.', $row['name']);
            $name = end($name);
            $result->$name = $row['value'];
        }
        return $result;
    }
     
    public function set($time, $msg = '') {
        $timeStamp = strtotime($time);
        if(!$timeStamp) {
            echo 'Given time parameter "'.$time.'" can not be parsed to a valid timestamp!';
            return;
        }
        $timeStamp = date('Y-m-d H:i', $timeStamp);
        $this->db->query("UPDATE `Zf_configuration` SET `value` = ? WHERE `name` = 'runtimeOptions.maintenance.startDate'", $timeStamp);
        $this->db->query("UPDATE `Zf_configuration` SET `value` = ? WHERE `name` = 'runtimeOptions.maintenance.message'", $msg);
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