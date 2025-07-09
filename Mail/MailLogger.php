<?php

declare(strict_types=1);

namespace MittagQI\ZfExtended\Mail;

use MittagQI\ZfExtended\Logger\SimpleFileLogger;
use Zend_Mail;

class MailLogger extends SimpleFileLogger
{
    public const LOG_NAME = 'mail.log';

    public function __construct()
    {
        parent::__construct(self::LOG_NAME);
    }

    public function logMail(Zend_Mail $mailer, string $status): void
    {
        $recipients = $mailer->getRecipients();
        $headers = $mailer->getHeaders();
        foreach ($recipients as $recipient) {
            $howList = [];
            foreach (['To', 'Cc', 'Bcc'] as $how) {
                if (array_key_exists($how, $headers) && in_array($recipient, $headers[$how], true)) {
                    $howList[] = $how;
                }
            }
            $this->log($status . ' ' . $recipient . ' (' . join(', ', $howList) . ') ' . $mailer->getSubject());
        }
    }
}
