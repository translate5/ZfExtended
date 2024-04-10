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
 * @package portal
 * @version 2.0
 *
 */

class ZfExtended_Models_SystemRequirement_Modules_Environment extends ZfExtended_Models_SystemRequirement_Modules_Abstract
{
    protected $installationBootstrap = true;

    protected $isWin = false;

    /**
     * @see ZfExtended_Models_SystemRequirement_Modules_Abstract::validate()
     */
    public function validate(): ZfExtended_Models_SystemRequirement_Result
    {
        $this->result->id = 'environment';
        $this->result->name = 'System Environment';
        $this->isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $this->checkLocale();
        $this->checkGitInstallation();

        return $this->result;
    }

    /**
     * checks the needed PHP version of translate5
     */
    protected function checkPhpVersion()
    {
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $this->errorsEnvironment[] = 'You are using PHP in version ' . PHP_VERSION . ', translate5 needs a PHP version >= 7.4.0';
            if ($this->isWin) {
                $this->result->error[] = 'Please update your xampp package manually or reinstall Translate5 with the latest windows installer from http://www.translate5.net';
                $this->result->error[] = 'Warning: Reinstallation can lead to data loss! Please contact support@translate5.net when you need assistance in data conversion!';
            }
        }
    }

    /**
     * Checks if this is a git installation
     */
    protected function checkGitInstallation()
    {
        if (file_exists('.git')) {
            $msg = 'A .git file/directory does exist in the project root!' . "\n";
            $msg .= 'Please use git to update your installation and call ./translate5.s database:update ';
            $this->result->info[] = $msg;
        }
    }

    /**
     * Ensure that the correct locale is set
     */
    protected function checkLocale()
    {
        $locale = setlocale(LC_CTYPE, 0);
        $msg = [
            'Your system wide used locale is not UTF-8 capable, it is set to: LC_CTYPE=' . $locale,
            'Please use a UTF-8 based locale like en_US.UTF-8 to avoid problems with special characters in filenames.',
        ];
        if (! $this->isWin && stripos($locale, 'utf-8') === false && stripos($locale, 'utf8') === false) {
            if (defined('TRANSLATE5_CLI') && TRANSLATE5_CLI) {
                array_push($this->result->warning, ...$msg);
                $this->result->warning[] = 'please re-check online in the UI!';
            } else {
                array_push($this->result->error, ...$msg);
            }
        }
        if ($this->isWin) {
            $this->result->warning[] = 'You are using WINDOWS as server environment. Please ensure that the configuration runtimeOptions.fileSystemEncoding is set correct.';
        }
    }
}
