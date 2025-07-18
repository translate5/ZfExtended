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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * Klasse zur Kapselung des Mailversands
 */
class ZfExtended_TemplateBasedMail
{
    public const MAIL_TEMPLATE_BASEPATH = '/views/scripts/mail/';

    /**
     * @var Zend_View
     */
    protected $view;

    /**
     * @var MittagQI\ZfExtended\Mailer
     */
    protected $mail;

    /**
     * setzt ein Flag ob Inhalt schon gesetzt wurde
     * @var boolean
     */
    protected $isContentSet = false;

    /**
     * beinhaltet den template Namen, sofern gesetzt
     * @var string
     */
    protected $template;

    /**
     * @var Zend_Config
     */
    protected $config;

    /**
     * @var integer
     * @deprecated remove me and the corresponding config in favour of mail catcher
     */
    protected $_sendMailLocally = 0;

    /**
     * @var boolean
     */
    protected $_sendBcc = false;

    protected $initialLocale;

    protected static $translationInstances = [];

    /**
     * initiiert das interne Mail und View Object
     *
     * @param bool initView entscheidet, ob view initialisiert wird
     *      (Achtung: Bei false ist die Verwendung von Mailtemplates mit ZfExtended_TemplateBasedMail nicht möglich)
     *      Default: true
     */
    public function __construct($initView = true)
    {
        try {
            $this->config = Zend_Registry::get('config');
            $this->_sendMailLocally = $this->config->runtimeOptions->sendMailLocally;
            if (isset($this->config->runtimeOptions->mail->generalBcc)) {
                $this->_sendBcc = true;
            }
        } catch (Exception) {
            //do nothing
        }

        if ($initView) {
            $this->initView();
        }
        $this->setMail();
    }

    /**
     * setzt das interne Mail Object - z. B. bei verschicken zweier Mails mit der selben Objektinstanz
     */
    public function setMail()
    {
        $this->mail = new MittagQI\ZfExtended\Mailer(new MittagQI\ZfExtended\Mail\MailLogger(), 'utf-8');
    }

    /**
     * initialisiert die View für die Ausgabe des Mail Templates
     */
    protected function initView()
    {
        $this->view = new Zend_View();
        $this->view->config = Zend_Registry::get('config');
        $this->view->translate = ZfExtended_Zendoverwrites_Translate::getInstance();
        $this->initialLocale = $this->view->translate->getTargetLang();

        //cache translation instance per language to be able to send the mail in the users language
        self::$translationInstances[$this->initialLocale] = $this->view->translate;

        $libs = array_reverse($this->config->runtimeOptions->libraries->order->toArray());
        foreach ($libs as $lib) {
            $this->view->addHelperPath(APPLICATION_PATH . '/../library/' . $lib .
                    '/views/helpers/', $lib . '_View_Helper_');
            $this->view->addScriptPath(APPLICATION_PATH . '/../library/' . $lib .
                    self::MAIL_TEMPLATE_BASEPATH);
        }
        $this->view->addHelperPath(APPLICATION_PATH . '/modules/' .
                Zend_Registry::get('module') . '/views/helpers', 'View_Helper_');

        $this->view->addScriptPath(APPLICATION_PATH . '/modules/' . Zend_Registry::get('module') . self::MAIL_TEMPLATE_BASEPATH);

        $this->view->addScriptPath(APPLICATION_PATH . '/../client-specific/views/' . Zend_Registry::get('module') . '/scripts/mail/');

        $events = ZfExtended_Factory::get('ZfExtended_EventManager', [__CLASS__]);
        /* @var $events ZfExtended_EventManager */
        $events->trigger('afterMailViewInit', $this, [
            'view' => $this->view,
        ]);
    }

    /**
     * Stores the initialized translation instance per used language
     */
    protected function storeTranslationInstance(ZfExtended_Zendoverwrites_Translate $translate)
    {
        self::$translationInstances[$translate->getTargetLang()] = $translate;
    }

    /**
     * Setzt Parameter für das View Object zur Ausgabe des Mail Templates
     */
    public function setParameters(array $params)
    {
        $this->decideIfToThrowInitViewException();
        foreach ($params as $key => $val) {
            $this->view->assign($key, $val);
        }
    }

    /**
     * Setzt die Mail Anhänge
     */
    public function setAttachment(array $attachments)
    {
        foreach ($attachments as $attachment) {
            $at = $this->mail->createAttachment($attachment['body']);
            $at->type = $attachment['mimeType'];
            $at->disposition = $attachment['disposition'];
            $at->encoding = $attachment['encoding'];
            $at->filename = $attachment['filename'];
        }
    }

    /**
     * Setzt den Absender
     * @param string $frommail
     * @param string $fromname
     */
    public function setFrom($frommail, $fromname)
    {
        $this->mail->setFrom($frommail, $fromname);
    }

    /**
     * Set Reply-To Header
     *
     * @return Zend_Mail
     * @throws Zend_Mail_Exception if called more than one time
     */
    public function setReplyTo(string $email, string $name = null)
    {
        $this->mail->setReplyTo($email, $name);
    }

    /**
     * Aus den Namen von Controller und Action wird der Templatename generiert.
     * Adaptierung des alten Algorithmus
     */
    protected function detectTemplate()
    {
        $this->decideIfToThrowInitViewException();
        $trace = debug_backtrace(false);
        $classArr = explode('_', $trace[4]['class']);
        $classArr = array_reverse($classArr);

        return $this->makeTemplateName($classArr[0], $trace[4]['function']);
    }

    /**
     * Generiert aus Klassen- und Funktionsname den Templatenamen
     * @param string $classname
     * @param string $functionname
     * @return string
     */
    protected function makeTemplateName($classname, $functionname)
    {
        $this->decideIfToThrowInitViewException();

        return strtolower(preg_replace('"Controller$"', '', $classname))
                . ucfirst(strtolower(preg_replace('"Action$"', '', $functionname)))
                . '.phtml';
    }

    /**
     * Setzt direkt die Mail Content Daten
     * @param string $subject (kann null sein)
     * @param string $htmlbody
     */
    public function setContent($subject, string $textbody, $htmlbody = '')
    {
        $this->isContentSet = true;
        $textbody = preg_replace('"\n"', "\r\n", $textbody);
        if (! empty($subject)) {
            $this->mail->setSubject($subject);
        }
        $this->mail->setBodyText($textbody);
        if (! empty($htmlbody)) {
            $this->addImagesIfExist();
            $this->mail->setBodyHtml($htmlbody, null, Zend_Mime::ENCODING_QUOTEDPRINTABLE);
        }
        if (! empty($this->view->attachments)) {
            $this->setAttachment((array) $this->view->attachments);
        }
    }

    /**
     * Sucht nach dem Ordner TemplatenameImages (ohne .phtml) und fügt dort vorhandene Bilder als Attachement hinzu
     * geht alle View Script Pfade durch, und nimmt den ersten in dem ein Images Verzeichnis existiert
     */
    protected function addImagesIfExist()
    {
        $this->decideIfToThrowInitViewException();
        if (empty($this->template)) {
            return;
        }
        $paths = $this->view->getScriptPaths();
        $imagedir = str_replace('.phtml', 'Images', $this->template);
        $directoryFound = false;
        foreach ($paths as $path) {
            $directory = $path . '/' . $imagedir;
            $directoryFound = (file_exists($directory) && is_dir($directory));
            if ($directoryFound) {
                break;
            }
        }
        if (! $directoryFound) {
            return; //nichts gefunden.
        }

        $this->mail->setType(Zend_Mime::MULTIPART_RELATED);

        foreach (new DirectoryIterator($directory) as $file) {
            if ($file->isDot() || ! $file->isReadable()) {
                continue;
            }
            $filename = $file->getPathname();
            if (exif_imagetype($filename) === false) {
                continue;
            }

            try {
                $imageinfo = getimagesize($filename);
                $imagedata = file_get_contents($filename);
            } catch (Exception $e) {
                //Datei kann nicht gelesen werden bzw. ist keien Grafikdatei => ignorieren
                continue;
            }
            $attachment = $this->mail->createAttachment(
                $imagedata,
                $imageinfo['mime'],
                Zend_Mime::DISPOSITION_INLINE,
                Zend_Mime::ENCODING_BASE64,
                $file->getFilename()
            );

            $attachment->id = md5($file->getFilename()); //id wird zur cid, welche in html mails für bilder bnötigt wird
        }
    }

    /**
     * Setzt den E-Mail Subject direkt
     */
    public function setSubject(string $subject)
    {
        $this->mail->setSubject($subject);
    }

    /*
     * Entscheidet, ob eine initViewException geworfen werden soll und wirft diese bei Bedarf
     *
     * @throws Zend_Exception
     */
    protected function decideIfToThrowInitViewException()
    {
        if (! isset($this->view)) {
            throw new Zend_Exception('ZfExtended_TemplateBasedMail: Template soll verwendet werden, aber Viewinitialisierung im Konstruktor deaktiviert.');
        }
    }

    /**
     * setzt den Templatenamen der verwendet werden soll
     * weitere Infos siehe setContentByTemplate
     * @see self::setContentByTemplate
     */
    public function setTemplate(string $template)
    {
        $this->decideIfToThrowInitViewException();
        $this->template = $template;
    }

    /**
     * setzt den Templatenamen anhand eines Klassen- und Funktionsnamen
     * @param string $classname
     * @param string $functionname
     */
    public function setTemplateBySignature($classname, $functionname)
    {
        $this->decideIfToThrowInitViewException();
        $this->template = $this->makeTemplateName($classname, $functionname);
    }

    /**
     * @throws Zend_Exception
     */
    public function setContentByTemplate(): void
    {
        $this->decideIfToThrowInitViewException();

        if (empty($this->template)) {
            $templatename = $this->detectTemplate();
        } else {
            $templatename = $this->template;
        }

        $body = $this->view->render($templatename);
        $subject = (string) $this->view->subject;
        $textbody = (string) $this->view->textbody;

        if (strlen($textbody) > 0) {
            //html Mail
            $this->setContent($subject, $textbody, $body);
        } else {
            //text only Mail
            $this->setContent($subject, $body);
        }
    }

    /**
     * sendet die E-Mail an den angegebenen Empfänger.
     * Wurde noch kein Content gesetzt, wird das Template aus dem Controller bestimmt
     * - beinhaltet einen Local Mail Hack
     * - versendet an alle per runtimeOptions.mail.generalBcc gelisteten Mailadressen
     *   eine BCC-Mail
     * @param string $toMail
     * @param string $toName
     */
    public function send($toMail, $toName)
    {
        if (! $this->isContentSet) {
            $this->setContentByTemplate();
        }

        //hack, um im lokalen development mails an lokale linux-mailadressen zu senden
        if ((int) $this->_sendMailLocally === 1) {
            $toMail = preg_replace('"@.+$"', '', $toMail);
        }

        $this->mail->addTo($toMail, $toName);
        if ($this->_sendBcc) {
            foreach ($this->config->runtimeOptions->mail->generalBcc as $bcc) {
                if (preg_match($this->config->runtimeOptions->defines->EMAIL_REGEX, $bcc)) {
                    $this->mail->addBcc($bcc);
                }
            }
        }
        $this->mail->send();
        $this->mail->clearRecipients();
    }

    /**
     * Sends the mail to the given user in his chosen translation
     * if user has no translation chosen, first fallback is applicationLocale, second fallback is fallbackLocale
     */
    public function sendToUser(ZfExtended_Models_User $user)
    {
        $this->resetTranslationLanguage($this->getUserLocale($user));
        $this->view->receiver = $user->getDataObject();
        $this->send($user->getEmail(), $user->getUserName());
    }

    /**
     * Resets the internal used locale for mail translation
     * @param string $locale
     */
    public function resetTranslationLanguage($locale)
    {
        $this->decideIfToThrowInitViewException();
        if (empty(self::$translationInstances[$locale])) {
            $translation = ZfExtended_Factory::get('ZfExtended_Zendoverwrites_Translate', [$locale]);
            /* @var $translation ZfExtended_Zendoverwrites_Translate */
            $this->storeTranslationInstance($translation);
        }
        $this->view->translate = self::$translationInstances[$locale];
    }

    /**
     * @return string
     */
    protected function getUserLocale(ZfExtended_Models_User $user)
    {
        $locale = $user->getLocale();
        // user has locale, use that:
        if (! empty($locale)) {
            return $locale;
        }
        //if an applicationLocale is configured use that
        $locale = $this->config->runtimeOptions->translation->applicationLocale;
        if (! empty($locale)) {
            return $locale;
        }

        //finally use the fallback:
        return $this->config->runtimeOptions->translation->fallbackLocale;
    }

    /**
     * returns the configured subject
     * @return string
     */
    public function getSubject()
    {
        return $this->mail->getSubject();
    }

    /**
     * returns the configured text body
     * @return string
     */
    public function getTextBody()
    {
        return $this->mail->getBodyText(true);
    }

    /**
     * returns the configured html body
     * @return string
     */
    public function getHtmlBody()
    {
        return $this->mail->getBodyHtml(true);
    }

    /**
     * sendet eine Mail an den gegebenen employee, Empfängeradresse und Namen, werden automatisch ausgelesen,
     * ebenso die companyGUID für die Suche nach einem Kundenspezifischen Template
     */
    public function send2customer(Zend_Session_Namespace $employee)
    {
        $this->decideIfToThrowInitViewException();
        $this->view->addScriptPath(APPLICATION_PATH . '/modules/' . Zend_Registry::get('module') . self::MAIL_TEMPLATE_BASEPATH . $employee->companyGUID . '/');
        $this->send($employee->eMail, $employee->firstname . ' ' . $employee->surname);
    }

    /**
     * Adds BCC-recipient, $email can be an array, or a single string address
     * @param string|array $email
     */
    public function addBcc($email)
    {
        $this->mail->addBcc($email);
    }

    /**
     * Adds CC-recipient, $email can be an array, or a single string address
     * if it is an associative array the keys are used as receiver name, the value as email
     * @param string|array $email
     */
    public function addCc($email, $name = '')
    {
        $this->mail->addCc($email, $name = '');
    }

    /**
     * @throws Zend_Exception
     */
    public function hasCustomTemplate(string $template): bool
    {
        $scriptsPath = APPLICATION_ROOT . '/client-specific/views/' . Zend_Registry::get('module') . '/scripts/mail/';

        return file_exists($scriptsPath . $template);
    }
}
