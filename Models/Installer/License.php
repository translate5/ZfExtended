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

/**
 * @package ZfExtended
 * @version 2.0
 */
class ZfExtended_Models_Installer_License
{
    public const DEFAULT_TITLE = '{LABEL} license agreement ({LICENSE})';

    public const DEFAULT_AGREEMENT = 'translate5 uses {USES}. Please read the following license agreement and accept it for {LABEL}.

  {RELPATH}
            
  You must accept the terms of this agreement for {LABEL} by typing "y" and <ENTER> before continuing with the installation.
  If you type "y", the translate5 installer will download and install {LABEL} for you.{SUFFIX}';

    /**
     * @var stdClass
     */
    protected $license;

    protected $dependency;

    /**
     * creates the license instances for the given dependency
     *
     * replaces {UPPERCASE} variables with same named variables in the license
     * or dependency object (the variable must be there completly in lowercase)
     *
     * if a variable "agreement" or "title" is given in license, this texts will be used.
     *
     * @return multitype:|multitype:ZfExtended_Models_Installer_License
     */
    public static function create(stdClass $dependency)
    {
        if (empty($dependency->licenses)) {
            return [];
        }
        $res = [];
        foreach ($dependency->licenses as $license) {
            $res[] = new self($dependency, $license);
        }

        return $res;
    }

    protected function __construct(stdClass $dependency, stdClass $license)
    {
        settype($license->uses, 'string');
        settype($license->relPath, 'string');
        settype($license->suffix, 'string');
        settype($license->license, 'string');
        $this->license = $license;
        $this->dependency = $dependency;
        $this->setUsesByFile();
    }

    /**
     * Overwrites the license uses field from optional usesFile if usesFile is given and a file exists,
     * otherwise the previous license->uses value remains
     */
    protected function setUsesByFile()
    {
        if (empty($this->license->usesFile)) {
            return;
        }
        $usesFile = getcwd() . DIRECTORY_SEPARATOR . $this->license->usesFile;
        if (file_exists($usesFile)) {
            $this->license->uses = file_get_contents($usesFile);
        }
    }

    /**
     * Checks is the configured license file really does exist
     * @return boolean
     */
    public function checkFileExistance()
    {
        $notGiven = empty($this->license->relpath);

        return $notGiven || file_exists(getcwd() . DIRECTORY_SEPARATOR . $this->license->relpath);
    }

    /**
     * returns the agreement text of the given dependency
     * @return string
     */
    public function getAgreementText()
    {
        $text = empty($this->license->agreement) ? self::DEFAULT_AGREEMENT : $this->license->agreement;

        return '  ' . join(PHP_EOL, array_map(function ($item) {
            return wordwrap($item, 80, PHP_EOL . "  ");
        }, explode("\n", $this->replaceVariables($text))));
    }

    /**
     * returns the agreement title of the given dependency
     * @return string
     */
    public function getAgreementTitle()
    {
        $text = empty($this->license->title) ? self::DEFAULT_TITLE : $this->license->title;

        return $this->replaceVariables($text);
    }

    /**
     * replaces the variables in the given text by data from the given dependency object
     * @param string $text
     * @return string
     */
    protected function replaceVariables($text)
    {
        $dep = $this->dependency;
        $license = $this->license;

        return preg_replace_callback('/\{([A-Z]+)\}/', function ($matches) use ($dep, $license) {
            $key = strtolower($matches[1]);
            if (! empty($license->$key)) {
                return $license->$key;
            }
            if (! empty($dep->$key)) {
                return $dep->$key;
            }

            return '';
        }, $text);
    }
}
