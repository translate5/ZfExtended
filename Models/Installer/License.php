<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/**
 * @package ZfExtended
 * @version 2.0
 */
class ZfExtended_Models_Installer_License {
    
    const DEFAULT_TITLE = '{LABEL} license agreement ({LICENSE})';
    const DEFAULT_AGREEMENT = 'translate5 uses {USES}. Please read the following license agreement and accept it for {LABEL}.

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
     * @param stdClass $dependency
     * @return multitype:|multitype:ZfExtended_Models_Installer_License
     */
    public static function create(stdClass $dependency) {
        if(empty($dependency->licenses)) {
            return array();
        }
        $res = array();
        foreach($dependency->licenses as $license) {
            $res[] = new self($dependency, $license);
        }
        return $res;
    }
    
    /**
     * @param stdClass $dependency
     * @param stdClass $license
     */
    protected function __construct(stdClass $dependency, stdClass $license) {
        settype($license->uses, 'string');
        settype($license->relPath, 'string');
        settype($license->suffix, 'string');
        settype($license->license, 'string');
        $this->license = $license;
        $this->dependency = $dependency;
    }
    
    /**
     * Checks is the configured license file really does exist
     * @return boolean
     */
    public function checkFileExistance() {
        $notGiven = empty($this->license->relpath);
        return  $notGiven || file_exists(getcwd().DIRECTORY_SEPARATOR.$this->license->relpath);
    }
    
    /**
     * returns the agreement text of the given dependency
     * @return string
     */
    public function getAgreementText() {
        $text = empty($this->license->agreement) ? self::DEFAULT_AGREEMENT : $this->license->agreement;
        return '  '.join(PHP_EOL, array_map(function($item) {
            return wordwrap($item, 70, PHP_EOL."  ");
        }, explode("\n", $this->replaceVariables($text))));
    }
    
    /**
     * returns the agreement title of the given dependency
     * @return string
     */
    public function getAgreementTitle() {
        $text = empty($this->license->title) ? self::DEFAULT_TITLE : $this->license->title;
        return $this->replaceVariables($text);
    }
    
    /**
     * replaces the variables in the given text by data from the given dependency object
     * @param string $text
     * @return string
     */
    protected function replaceVariables($text) {
        $dep = $this->dependency;
        $license = $this->license;
        return preg_replace_callback('/\{([A-Z]+)\}/', function($matches) use ($dep, $license) {
            $key = strtolower($matches[1]);
            if(!empty($license->$key)){
                return $license->$key;
            }
            if(!empty($dep->$key)){
                return $dep->$key;
            }
            return '';
        }, $text);
    }
}