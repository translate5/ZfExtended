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
 */
class ZfExtended_Logger_Writer_DirectMail extends ZfExtended_Logger_Writer_Abstract {
    public function write(ZfExtended_Logger_Event $event) {
        if(!empty($_SERVER['HTTP_HOST'])) {
            $subject = $_SERVER['HTTP_HOST'].': ';
        }
        else {
            $subject = '';
        }
        $subject .= $event->levelName.' in '.$event->domain.': ';
        if(!empty($event->eventCode)){
            $subject .= $event->eventCode.' - ';
        }
        $subject .= $event->message;
        $subject = substr($subject, 0, 80);//max length of 80, the whole message is in the body
        
        $mail = new Zend_Mail();
        $mail->addTo($this->options['receiver']);
        $mail->setFrom($this->options['sender']);
        $mail->setSubject($subject);
        $mail->setBodyText($event);
        $mail->setBodyHtml($event->toHtml());
        $mail->send();
    }
    
    public function validateOptions(array $options) {
        parent::validateOptions($options);
        if(empty($options['sender'])) {
            throw new ZfExtended_Logger_Exception(__CLASS__.': Missing option sender!');
        }
        if(empty($options['receiver'])) {
            throw new ZfExtended_Logger_Exception(__CLASS__.': Missing option receiver!');
        }
        
    }
    
}