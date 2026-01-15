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

namespace MittagQI\ZfExtended;

use MittagQI\ZfExtended\Mail\MailLogger;
use Throwable;
use Zend_Config;
use Zend_Exception;
use Zend_Mail;
use Zend_Mail_Transport_Abstract;
use Zend_Registry;
use ZfExtended_Debug;
use ZfExtended_Logger;

class Mailer extends Zend_Mail
{
    /**
     * disable sending E-Mails completly
     */
    protected static bool $sendingDisabled = false;

    protected Zend_Config $config;

    protected ?Throwable $lastError = null;

    /**
     * Public constructor
     *
     * @throws Zend_Exception
     */
    public function __construct(
        private readonly MailLogger $mailLog,
        string $charset = null
    ) {
        $this->config = Zend_Registry::get('config');
        if (! self::$sendingDisabled) {
            self::$sendingDisabled = $this->config->runtimeOptions->sendMailDisabled;
        }
        parent::__construct($charset);
    }

    /**
     * Sends this email using the given transport or a previously
     * set DefaultTransport or the internal mail function if no
     * default transport had been set.
     *
     * @param Zend_Mail_Transport_Abstract $transport
     * @return self                    Provides fluent interface
     * @throws Zend_Exception
     */
    public function send($transport = null): self
    {
        if (self::$sendingDisabled) {
            if (ZfExtended_Debug::hasLevel('core', 'mailing')) {
                error_log('translate5 disabled mail: '
                    . $this->getSubject() . ' <' . implode(',', $this->getRecipients()) . '>');
            }
            $this->mailLog->logMail($this, 'DISABLED');

            return $this;
        }

        try {
            parent::send($transport);
            $this->mailLog->logMail($this, 'SUCCESS');
        } catch (Throwable $e) {
            $this->lastError = $e;
            //disable mail sending, so it not end up in endles loop
            self::$sendingDisabled = true;
            if (Zend_Registry::isRegistered('logger')) {
                Zend_Registry::get('logger')->exception($e, [
                    'level' => ZfExtended_Logger::LEVEL_WARN,
                ]);
            } else {
                error_log($e);
            }
            $this->mailLog->logMail($this, 'FAIL');
        }

        return $this;
    }

    /**
     * Returns the last email error or null if no error
     */
    public function getLastError(): ?Throwable
    {
        return $this->lastError;
    }

    public function setFrom($email, $name = null)
    {
        if (! empty($name)) {
            $name = str_replace('{companyName}', $this->config->runtimeOptions->companyName, $name);
        }

        return parent::setFrom($email, $name);
    }
}
