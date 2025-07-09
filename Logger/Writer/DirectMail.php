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

class ZfExtended_Logger_Writer_DirectMail extends ZfExtended_Logger_Writer_Abstract
{
    protected Zend_Config $config;

    /**
     * @throws Zend_Mail_Exception
     * @see ZfExtended_Logger_Writer_Abstract::write()
     */
    public function write(ZfExtended_Logger_Event $event): void
    {
        if ($this->getDuplicateCount($event) > 0) {
            return;
        }

        $subject = preg_replace('#^https?://#', '', ($event->httpHost ?? '')) . ': ';
        $subject .= $event->levelName . ' in ' . $event->domain . ': ';
        if (! empty($event->eventCode)) {
            $subject .= $event->eventCode . ' - ';
        }
        $subject .= $event->message;
        $subject = substr($subject, 0, 254); //limited length of 254, the whole message is in the body

        $mail = new MittagQI\ZfExtended\Mailer(new MittagQI\ZfExtended\Mail\MailLogger(), 'utf-8');
        $mail->addTo($this->options['receiver']);
        $mail->setFrom($this->options['sender']);

        if (isset($this->config->runtimeOptions->mail->generalBcc)) {
            foreach ($this->config->runtimeOptions->mail->generalBcc as $bcc) {
                $mail->addBcc($bcc);
            }
        }

        $mail->setSubject($subject);
        $mailEvent = clone $event;
        //fill extraflat
        $mailEvent->getExtraFlattenendAndSanitized();
        //remove the extra only from the mail. Other writers are using the same event extra.
        $mailEvent->extra = null;
        $mail->setBodyText($mailEvent);
        $mail->setBodyHtml($mailEvent->toHtml());
        if (! empty($mailEvent->extraFlat)) {
            $mail->createAttachment(
                print_r($mailEvent->extraFlat, 1),
                'text/plain',
                Zend_Mime::DISPOSITION_ATTACHMENT,
                Zend_Mime::ENCODING_BASE64,
                'extra_log_data.txt'
            );
        }
        $mail->send();
    }

    /**
     * @throws ZfExtended_Logger_Exception
     * @throws Zend_Exception
     */
    public function validateOptions(array &$options): void
    {
        parent::validateOptions($options);
        $this->config = Zend_Registry::get('config');
        if (empty($options['sender'])) {
            $options['sender'] = $this->config->resources->mail->defaultFrom->email;
        }
        if (empty($options['receiver'])) {
            $options['receiver'] = $this->config->resources->mail->defaultFrom->email;
        }
        if (empty($options['sender']) || empty($options['receiver'])) {
            throw new ZfExtended_Logger_Exception(
                __CLASS__ . ': Missing option receiver or sender and no defaultFrom is defined!'
            );
        }
    }

    /**
     * @see ZfExtended_Logger_Writer_Abstract::isEnabled()
     */
    public function isEnabled(): bool
    {
        return ! $this->config->runtimeOptions->sendMailDisabled;
    }
}
