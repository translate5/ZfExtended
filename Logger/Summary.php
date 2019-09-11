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
 * Logger Summary creator 
 */
class ZfExtended_Logger_Summary {
    
    /**
     * @var ZfExtended_Models_Db_ErrorLog
     */
    protected $db; 
    
    public function __construct() {
        $this->db = ZfExtended_Factory::get('ZfExtended_Models_Db_ErrorLog');
    }
    
    /**
     * @param integer $date optional, unix time stamp of today, the logs to be considered are from the day before
     */
    public function sendSummaryToAdmins($date = null) {
        if(empty($date)) {
            $date = time();
        }
        $date = date('Y-m-d', $date);
        $summary = $this->getSummaryLastDay($date);
        $overview = $this->getOverviewLastDay($date, [ZfExtended_Logger::LEVEL_FATAL, ZfExtended_Logger::LEVEL_ERROR, ZfExtended_Logger::LEVEL_WARN]);
        if(empty($summary) && empty($overview)) {
            return;
        }
        $user = ZfExtended_Factory::get('ZfExtended_Models_User');
        /* @var $user ZfExtended_Models_User */
        $admins = $user->loadAllByRole(['admin']);
        
        $mail = ZfExtended_Factory::get('ZfExtended_Mailer', ['utf8']);
        /* @var $mail ZfExtended_Mailer */
        
        $version = ZfExtended_Utils::getAppVersion();

        
        $config = Zend_Registry::get('config');
        $hostname = $config->runtimeOptions->server->name;
        
        $mail->setSubject('translate5 ('.$version.') error log summary on '.$hostname);
        $html  = 'Yesterdays error summary of your translate5 installation on '.$hostname.' in version <b>'.$version.'</b>.<br><br>';
        
        if(!empty($summary)) {
            $html .= '<b><u>Summary: </u></b><br>';
            $html .= '<table>';
            $html .= '<tr><th style="text-align:left;">Level:</th><th style="text-align:left;">Count:</th></tr>';
            foreach($summary as $levelSum) {
                $html .= '<tr><td>'.$this->getLevel($levelSum['level']).'</td><td>'.$levelSum['cnt'].'</td></tr>'."\n";
            }
            $html .= '</table>';
        }
        
        if(!empty($overview)) {
            $html .= '<br><b><u>Overview: </u></b><br>';
            $html .= '<table>';
            $html .= '<tr><th style="text-align:left;padding-right:10px;">Date:</th>';
            $html .= '<th style="text-align:left;padding-right:10px;">Level:</th>';
            $html .= '<th style="text-align:left;padding-right:10px;">Message:</th>';
            $html .= '<th style="text-align:left;">Version:</th></tr>';
            //https://confluence.translate5.net/display/TAD/ErrorCodes#ErrorCodes-E1010
            foreach($overview as $levelOverview) {
                $url = str_replace('{0}', $levelOverview['eventCode'], $config->runtimeOptions->errorCodesUrl);
                $msg = '<a href="'.$url.'">'.$levelOverview['eventCode'].'</a> '.htmlspecialchars($levelOverview['message']);
                $html .= '<tr>';
                $html .= '<td style="white-space:nowrap;padding-right:10px;">'.$levelOverview['created'].'</td>';
                $html .= '<td>'.$this->getLevel($levelOverview['level']).'</td>';
                $html .= '<td>'.$msg.'</td>';
                $html .= '<td>'.$levelOverview['appVersion'].'</td></tr>'."\n";
            }
            $html .= '</table>';
        }
        
        $html .= '<br><br>This e-mail was created automatically by cron job.';
        
        $mail->setBodyHtml($html);
        //if there are no admins, no e-mails are sent
        if(empty($admins)) {
            return;
        }
        foreach($admins as $admin) {
            //$mail->setFrom('thomas@mittagqi.com'); //system mailer?
            $mail->addTo($admin['email'], $admin['firstName'].' '.$admin['surName']);
        }
        $mail->send();
    }
    
    /**
     * returns the level name colorized
     * @param integer $level
     * @return string
     */
    protected function getLevel($level) {
        $logger = Zend_Registry::get('logger');
        /* @var $logger ZfExtended_Logger */
        $levelName = $logger->getLevelName($level);
        switch ($level) {
            case $logger::LEVEL_FATAL: 
                return '<b style="color:#b60000;">'.$levelName.'</b>';
            case $logger::LEVEL_ERROR: 
                return '<span style="color:#b60000;">'.$levelName.'</span>';
            case $logger::LEVEL_WARN: 
                return '<span style="color:#e89b00;">'.$levelName.'</span>';
        }
        return $levelName;
    }
    
    /**
     * returns a summary of how many events per level has happened
     * @param string $date
     * @return array
     */
    protected function getSummaryLastDay($date) {
        $s = $this->db->select()
            ->from($this->db, ['level', 'cnt' => 'count(*)'])
            ->where('(? - INTERVAL 1 DAY) <= created AND created <= ?', $date)
            ->group('level');
        return $this->db->fetchAll($s)->toArray();
    }
    
    /**
     * returns a summary of how many events per level has happened
     * @param string $date
     * @return array
     */
    protected function getOverviewLastDay($date, array $level) {
        $s = $this->db->select()
            ->from($this->db, ['created', 'level', 'message', 'eventCode', 'appVersion'])
            ->where('(? - INTERVAL 1 DAY) <= created AND created <= ?', $date)
            ->where('level in (?)', $level);
        return $this->db->fetchAll($s)->toArray();
    }
}