<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2021 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3 requirements will be met:
 http://www.gnu.org/licenses/agpl.html
  
 There is a plugin exception available for use with this release of translate5 for
 translate5: Please see http://www.translate5.net/plugin-exception.txt or 
 plugin-exception.txt in the root folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-excecption
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/**
 * A simple extension of the DOMDocument class to be able to capture the errors that may occur in the process of loading HTML/XML
 * To not change the original API we do not throw exceptions but collect the errors instead of reporting them and make them accessible
 * Also an added API makes it simpler to load Unicode HTML (only XML will be loaded as UTF-8 by default) properly
 */
final class ZfExtended_Dom extends DOMDocument {
    const LIBXML_LEVEL_NAME = [
        LIBXML_ERR_WARNING => 'warning',
        LIBXML_ERR_ERROR   => 'error',
        LIBXML_ERR_FATAL   => 'fatal',
    ];
    
    /**
     * Used to set UTF-8 encoding for Markup
     * @var string
     */
    const UTF8_METATAG = '<meta http-equiv="content-type" content="text/html;charset=utf-8" />';
    
    /**
     * Helper to retrieve the inner HTML of a DOM Node
     * @param DOMNode $element
     * @return string
     */
    public static function innerHTML(DOMNode $element) : string {
        $html = '';
        foreach ($element->childNodes as $child){
            $html .= $element->ownerDocument->saveHTML($child);
        }
        return $html;
    }
    /**
     * 
     * @var libXMLError[]
     */
    private array $domErrors = [];
    /**
     * 
     * @var boolean
     */
    private bool $traceDomErrors = false;

    private DOMXPath $xpath;

    public function __construct($version=null, $encoding=null){
        parent::__construct($version, $encoding);
        // as long as libxml reports completely outdated errors (-> HTML 4.0.1 strict specs) we disable this
        $this->strictErrorChecking = false;
    }

    /**
     * @param string $filename
     * @param null $options
     * @return bool|DOMDocument
     */
    public function load ($filename, $options=NULL) { // returns bool when called dynamic
        $filename = realpath($filename);
        $this->domErrors = [];
        libxml_clear_errors();
        $useErrors = libxml_use_internal_errors(true);
        try {
            $result = parent::load($filename, $options);
            $this->domErrors = libxml_get_errors();
        } catch (Exception $e) {
            $this->domErrors[] = $this->createLibXmlError($e->getMessage(), $filename);
            $result = false;
        }
        libxml_use_internal_errors($useErrors);
        $this->traceWarningsAndErrors();
        return $result;
    }

    /**
     * @param string $source
     * @param null $options
     * @return bool|DOMDocument
     */
    public function loadXML ($source, $options=NULL){
        $this->domErrors = [];
        libxml_clear_errors();
        $useErrors = libxml_use_internal_errors(true);
        try {
            $result = parent::loadXML($source, $options);
            $this->domErrors = libxml_get_errors();
        } catch (Exception $e) {
            $this->domErrors[] = $this->createLibXmlError($e->getMessage());
            $result = false;
        }
        libxml_use_internal_errors($useErrors);
        $this->traceWarningsAndErrors();
        return $result;
    }

    /**
     * @param string $source
     * @param null $options
     * @return bool|DOMDocument
     */
    public function loadHTML ($source, $options=NULL){
        $this->domErrors = [];
        libxml_clear_errors();
        $useErrors = libxml_use_internal_errors(true);
        try {
            $result = parent::loadHTML($source, $options);
            $this->domErrors = libxml_get_errors();
        } catch (Exception $e) {
            $this->domErrors[] = $this->createLibXmlError($e->getMessage());
            $result = false;
        }
        libxml_use_internal_errors($useErrors);
        $this->traceWarningsAndErrors();
        return $result;
    }
    /**
     * Loads an HTML-String and forces a proper UTF-8 Encoding, returns the DOMNode-children representing the passed Markup
     * @param string $source
     * @return DOMNodeList|NULL
     */
    public function loadUnicodeMarkup (string $source): ?DOMNodeList {
        $result = $this->loadHTML('<html><head>'.self::UTF8_METATAG.'</head><body>'.$source.'</body>');
        if($result){
            $this->encoding = 'UTF-8';
            $body = $this->getElementsByTagName('body');
            if($body->length > 0 && $body->item(0)->hasChildNodes()){
                return $body->item(0)->childNodes;
            }
            return new DOMNodeList();
        }        
        return NULL;
    }
    /**
     * Loads an HTML-String and forces a proper UTF-8 Encoding, returns the DOMNode-children representing the passed Element Markup
     * Note that if multiple nodes/elements are passed, this will return the first Element
     * @param string $source
     * @return DOMElement|NULL
     */
    public function loadUnicodeElement (string $source) : ?DOMElement {
        $nodes = $this->loadUnicodeMarkup($source);
        if($nodes != NULL){
            for($i=0; $i < $nodes->length; $i++){
                $node = $nodes->item($i);
                if($node->nodeType == XML_ELEMENT_NODE){
                    return $node;
                }
            }
        }
        return NULL;
    }

    /**
     * @param string $filename
     * @param null $options
     * @return bool|DOMDocument
     */
    public function loadHTMLFile ($filename, $options=NULL){
        $filename = realpath($filename);
        $this->domErrors = [];
        libxml_clear_errors();
        $useErrors = libxml_use_internal_errors(true);
        try {
            $result = parent::loadHTMLFile($filename, $options);
            $this->domErrors = libxml_get_errors();
        } catch (Exception $e) {
            $this->domErrors[] = $this->createLibXmlError($e->getMessage(), $filename);
            $result = false;
        }
        libxml_use_internal_errors($useErrors);
        $this->traceWarningsAndErrors();
        return $result;
    }
    /**
     * Evaluates, if a loaded Document had no fatal errors and therefore can be seen as "valid"
     * If the evaluation has to be more strict you have to use the options
     */
    /**
     * @param bool $allowErrors
     * @return bool
     */
    public function isValid(bool $allowErrors=true) : bool {
        if(count($this->domErrors) == 0){
            return true;
        }
        foreach($this->domErrors as $error){ /* @var $error libXMLError */
            if($error->level == LIBXML_ERR_FATAL || (!$allowErrors && $error->level == LIBXML_ERR_ERROR)){
                return false;
            }
        }
        return true;
    }
    /**
     * Evaluates, if a string is a valid XML string in the sense, that it produces no fatal errors when loaded as such
     * @param string $string
     * @return boolean
     */
    public function isValidXmlString(string $string) : bool {
        //surround with dummy tags so the string validation can be done with DOMDocument
        $testString = '<dummytag>'.$string.'</dummytag>';
        $this->loadXML($testString);
        return $this->isValid();
    }
    /**
     * Activates error tracing using error_log
     */
    public function activateErrorTracing(){
        $this->traceDomErrors = true;
    }
    /**
     *
     * @return boolean
     */
    public function hasWarningsOrErrors() : bool {
        return (count($this->domErrors) > 0);
    }
    /**
     * Checks, if there were any errors or warnings when loading a document
     * @return libXMLError[]
     */
    public function getWarningsAndErrors() : array {
        return $this->domErrors;
    }
    /**
     * Retrieves the errors and warnings as a concatenated string
     * @param string $glue
     * @param bool $verbose enables column and line number
     * @return string
     */
    public function getErrorMsg(string $glue=', ', bool $verbose = false) : string
    {
        $errors = [];
        if (count($this->domErrors) > 0) {
            foreach ($this->domErrors as $error) {
                /* @var libXMLError $error */
                if ($verbose) {
                    $errorName = $this::LIBXML_LEVEL_NAME[$error->level];
                    $errors[] = "$errorName@$error->line,$error->column: $error->message";
                } else {
                    $errors[] = $this->createLibXmlErrorMsg($error);
                }
            }
        }
        return implode($glue, $errors);
    }
    /**
     * Traces the captured errors if there were any
     */
    private function traceWarningsAndErrors(){
        if($this->traceDomErrors && count($this->domErrors) > 0){
            foreach($this->domErrors as $error){
                error_log($this->createLibXmlErrorMsg($error));
            }
        }
    }
    /**
     * 
     * @param libXMLError $error
     * @return string
     */
    private function createLibXmlErrorMsg(libXMLError $error): string {
        $errorName = ($error->level == LIBXML_ERR_FATAL) ? 'FATAL ERROR' : (($error->level == LIBXML_ERR_ERROR) ? 'ERROR' : 'WARNING');
        return 'LibXML '.$errorName.': '.$error->message;
    }
    /**
     * @param string $message
     * @param string|null $file
     * @param int $level
     * @return libXMLError
     */
    private function createLibXmlError(string $message, string $file=null, int $level=LIBXML_ERR_FATAL): libXMLError {
        $error = new libXMLError();
        $error->message = $message;
        $error->file = $file;
        $error->level = $level;
        return $error;
    }

    /**
     * @return DOMXPath
     */
    public function getXpath(): DOMXPath {
        return $this->xpath ?? ($this->xpath = new DOMXPath($this));
    }

    /**
     * @param string $selector
     * @return DOMNodeList|false
     */
    public function query(string $selector) {
        return $this->getXpath()->query($selector);
    }
}
