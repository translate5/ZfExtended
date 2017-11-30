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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * Uses the given string as template
 * - translates the template string (before any data is replaced)
 * - replaces {DATA} with $view->DATA
 * - replaces {DATA.FIELD} with $view->DATA->FIELD or $view->DATA['FIELD']
 * - replaces {DATA.method()} with the result of $view->DATA->method()
 * @param string template string
 * @return string replaced string
 *
 */

class ZfExtended_View_Helper_TemplateApply extends Zend_View_Helper_Abstract {
    /**
     * The translated template strings, array to be used as template stack
     * @var array
     */
    protected $template = [];
    
    /**
     * Container for the data to be displayed, sanitized list of data which is allowed to be seen by users (since mail texts are coming from foreign XLF)
     * @var array
     */
    protected $data = [];
    
    public function templateApply($template, array $data = null) {
        if(!empty($data)) {
            $this->data = array_merge($this->data, $data);
        }
        $this->setTemplate($template);
        return $this->render();
    }
    
    public function setTemplate($template) {
        $this->template[] = $this->view->translate->_($template);
    }
    
    /**
     * render the internal stored template
     * @return string
     */
    protected function render() {
        return preg_replace_callback('#\{([^\}]+)\}#', function($matches){
            $placeHolder = $matches[1];
            $keys = explode('.', $placeHolder);
            return $this->getData($this->data, $keys, $placeHolder);
        }, array_pop($this->template));
    }
    
    /**
     * returns the data of the given placeholder or the placeHolder if nothing found
     * data is parsed recursivly if nested arrays or objects and a "." is given in the placeholder
     */
    protected function getData($data, $keys, $placeHolder) {
        $key = array_shift($keys);
        if(empty($key)) {
            return $data;
        }
        if(is_array($data) && array_key_exists($key, $data)) {
            return $this->getData($data[$key], $keys, $placeHolder);
        }
        if(is_object($data) && property_exists($data, $key)){
            return $this->getData($data->{$key}, $keys, $placeHolder);
        }
        return $placeHolder;
    }
    
    public function __toString() {
        try{
            return $this->render();
        }
        catch (Exception $e) {
            error_log($e);
            return end($this->template);
        }
    }
}