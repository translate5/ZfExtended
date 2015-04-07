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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * had to overwrite everything in the parent class because of the need to change
 * 3 lines of code, because the whole parent had been declared priate. Thats's really 
 * bad still in the normally good ZF
 * 
 * only changed part is the base64-decoding of trans-unit-ids _startElement-function
 * in addition overwrites _log-method of parent::parent
 * @todo: migrate translation-part of ZfExtended ot ZF2 and extend ZF2 with an Zend_Translate_Adapter_Xliff
 * 
 */
class  ZfExtended_Zendoverwrites_Translate_Adapter_Xliff extends Zend_Translate_Adapter_Xliff {
    // Internal variables
    private $_file        = false;
    private $_useId       = true;
    private $_cleared     = array();
    private $_transunit   = null;
    private $_source      = null;
    private $_target      = null;
    private $_langId      = null;
    private $_scontent    = null;
    private $_tcontent    = null;
    private $_stag        = false;
    private $_ttag        = false;
    private $_data        = array();

    /**
     * Logs a message when the log option is set - overwrites parent:_log to base64_encode id
     *
     * @param string $message Message to log
     * @param String $locale  Locale to log
     */
    protected function _log($message, $locale) {
        if ($this->_options['logUntranslated'] && !empty($message)) {
            $origMessage = $message;
            $message = str_replace('%message%', $message, $this->_options['logMessage']);
            $message = str_replace('%id%', base64_encode($origMessage), $message);
            $message = str_replace('%locale%', $locale, $message);
            if ($this->_options['log']) {
                $this->_options['log']->log($message, $this->_options['logPriority']);
            } else {
                trigger_error($message, E_USER_NOTICE);
            }
        }
    }
    /**
     * Load translation data (XLIFF file reader)
     *
     * @param  string  $locale    Locale/Language to add data for, identical with locale identifier,
     *                            see Zend_Locale for more information
     * @param  string  $filename  XLIFF file to add, full path must be given for access
     * @param  array   $option    OPTIONAL Options to use
     * @throws Zend_Translation_Exception
     * @return array
     */
    protected function _loadTranslationData($filename, $locale, array $options = array())
    {
        $this->_data = array();
        if (!is_readable($filename)) {
            require_once 'Zend/Translate/Exception.php';
            throw new Zend_Translate_Exception('Translation file \'' . $filename . '\' is not readable.');
        }

        if (empty($options['useId'])) {
            $this->_useId = false;
        } else {
            $this->_useId = true;
        }

        $encoding      = $this->_findEncoding($filename);
        $this->_target = $locale;
        $this->_file   = xml_parser_create($encoding);
        xml_set_object($this->_file, $this);
        xml_parser_set_option($this->_file, XML_OPTION_CASE_FOLDING, 0);
        xml_set_element_handler($this->_file, "_startElement", "_endElement");
        xml_set_character_data_handler($this->_file, "_contentElement");

        if (!xml_parse($this->_file, file_get_contents($filename))) {
            $ex = sprintf('XML error: %s at line %d',
                          xml_error_string(xml_get_error_code($this->_file)),
                          xml_get_current_line_number($this->_file));
            xml_parser_free($this->_file);
            require_once 'Zend/Translate/Exception.php';
            throw new Zend_Translate_Exception($ex);
        }

        return $this->_data;
    }

    private function _startElement($file, $name, $attrib)
    {
        if ($this->_stag === true) {
            $this->_scontent .= "<".$name;
            foreach($attrib as $key => $value) {
                $this->_scontent .= " $key=\"$value\"";
            }
            $this->_scontent .= ">";
        } else if ($this->_ttag === true) {
            $this->_tcontent .= "<".$name;
            foreach($attrib as $key => $value) {
                $this->_tcontent .= " $key=\"$value\"";
            }
            $this->_tcontent .= ">";
        } else {
            switch(strtolower($name)) {
                case 'file':
                    $this->_source = $attrib['source-language'];
                    if (isset($attrib['target-language'])) {
                        $this->_target = $attrib['target-language'];
                    }

                    if (!isset($this->_data[$this->_source])) {
                        $this->_data[$this->_source] = array();
                    }

                    if (!isset($this->_data[$this->_target])) {
                        $this->_data[$this->_target] = array();
                    }

                    break;
                case 'trans-unit':
                    $this->_transunit = true;
                    $this->_langId = base64_decode($attrib['id'],true);
                    if(!$this->_langId)
                        $this->_langId = $attrib['id'];
                    break;
                case 'source':
                    if ($this->_transunit === true) {
                        $this->_scontent = null;
                        $this->_stag = true;
                        $this->_ttag = false;
                    }
                    break;
                case 'target':
                    if ($this->_transunit === true) {
                        $this->_tcontent = null;
                        $this->_ttag = true;
                        $this->_stag = false;
                    }
                    break;
                default:
                    break;
            }
        }
    }

    private function _endElement($file, $name)
    {
        if (($this->_stag === true) and ($name !== 'source')) {
            $this->_scontent .= "</".$name.">";
        } else if (($this->_ttag === true) and ($name !== 'target')) {
            $this->_tcontent .= "</".$name.">";
        } else {
            switch (strtolower($name)) {
                case 'trans-unit':
                    $this->_transunit = null;
                    $this->_langId    = null;
                    $this->_scontent  = null;
                    $this->_tcontent  = null;
                    break;
                case 'source':
                    if ($this->_useId) {
                        if (!empty($this->_scontent) && !empty($this->_langId) &&
                            !isset($this->_data[$this->_source][$this->_langId])) {
                            $this->_data[$this->_source][$this->_langId] = $this->_scontent;
                        }
                    } else {
                        if (!empty($this->_scontent) &&
                            !isset($this->_data[$this->_source][$this->_scontent])) {
                            $this->_data[$this->_source][$this->_scontent] = $this->_scontent;
                        }
                    }
                    $this->_stag = false;
                    break;
                case 'target':
                    if ($this->_useId) {
                        if (!empty($this->_tcontent) && !empty($this->_langId) &&
                            !isset($this->_data[$this->_target][$this->_langId])) {
                            $this->_data[$this->_target][$this->_langId] = $this->_tcontent;
                        }
                    } else {
                        if (!empty($this->_tcontent) && !empty($this->_scontent) &&
                            !isset($this->_data[$this->_target][$this->_scontent])) {
                            $this->_data[$this->_target][$this->_scontent] = $this->_tcontent;
                        }
                    }
                    $this->_ttag = false;
                    break;
                default:
                    break;
            }
        }
    }

    private function _contentElement($file, $data)
    {
        if (($this->_transunit !== null) and ($this->_source !== null) and ($this->_stag === true)) {
            $this->_scontent .= $data;
        }

        if (($this->_transunit !== null) and ($this->_target !== null) and ($this->_ttag === true)) {
            $this->_tcontent .= $data;
        }
    }

    private function _findEncoding($filename)
    {
        $file = file_get_contents($filename, null, null, 0, 100);
        if (strpos($file, "encoding") !== false) {
            $encoding = substr($file, strpos($file, "encoding") + 9);
            $encoding = substr($encoding, 1, strpos($encoding, $encoding[0], 1) - 1);
            return $encoding;
        }
        return 'UTF-8';
    }

    /**
     * Returns the adapter name
     *
     * @return string
     */
    public function toString()
    {
        return "Xliff";
    }
}
