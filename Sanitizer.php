<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2022 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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
 * general sanitizer that sanitizes ALL request params
 */
final class ZfExtended_Sanitizer {

    /**
     * Leads to stripping of all tags
     */
    const STRING = 'string';
    /**
     * Leads to checking for script-tags & on** handlers and javascript: URLs
     * In these cases exceptions are thrown
     */
    const MARKUP = 'markup';
    /**
     * leads to NO sanitization and thus the application logic must ensure XSS prevention
     */
    const UNSANITIZED = 'unsanitized';

    /**
     * Sanitizes a request value that represents the given type
     * The type must be one of our constants
     * @param string $val
     * @param string $type
     * @return string
     * @throws ZfExtended_SecurityException
     */
    public static function sanitize(string $val, string $type) : string {
        return match ($type) {
            self::MARKUP => self::markup($val),
            self::UNSANITIZED => $val,
            default => self::string($val),
        };
    }

    /**
     * @param string $string
     * @return string
     */
    public static function string(string $string) : string {
        return strip_tags($string);
    }

    /**
     * Sanitizes markup
     * If unwanted contents are detected, an exception is thrown, otherwise the markup is returned
     * Note, that this function expects markup and not whole documents
     * Note, that invalid markup will be stripped and the text returned
     * @param string $markup
     * @return string
     * @throws ZfExtended_SecurityException
     */
    public static function markup(string $markup) : string {
        $dom = new ZfExtended_Dom();
        $nodeList = $dom->loadUnicodeMarkup($markup);
        // this is debatable: when invalid markup is posted, we remove it and use the posted text contents
        // this is neccessary, as browsers are much more tolerant than DOMDocument, and we can not expect the broken markup to be "broken enough" to not make attacks possible
        // may it is better to throw an "invalid markup exception" here ?
        if($nodeList === null){
            return strip_tags($markup);
        }
        foreach($nodeList as $node){
            self::checkNode($node);
        }
        return $markup;
    }

    /**
     * Checks a DOM node and throws an exception if something illegal was detected
     * @param DOMNode $node
     * @throws ZfExtended_SecurityException
     */
    private static function checkNode(DOMNode $node){
        if($node->nodeType == XML_ELEMENT_NODE) {
            if(strtolower($node->nodeName) === 'script'){
                throw new ZfExtended_SecurityException('Script tags are not allowed in the sent markup');
            }
            if($node->hasAttributes()){
                foreach ($node->attributes as $attribute){ /* @var $attribute DOMNode */
                    $name = strtolower($attribute->nodeName);
                    // any event-handler attribute will be rejected. We do not check, if this is actually a valid event-handler, so "onanything" will also be invalid
                    if(strlen($name) > 2 && str_starts_with($name, 'on')){
                        throw new ZfExtended_SecurityException('Event-handler attributes are not allowed in the sent markup');
                    }
                    // old-school but still possible: attack with "javascript:" pseudo-protocol
                    if($name === 'href'){
                        $href = preg_replace('/\s+/', '', strtolower($attribute->nodeValue));
                        if(str_starts_with($href, 'javascript:')){
                            throw new ZfExtended_SecurityException('JavaScript-hrefs are not allowed in the sent markup');
                        }
                    }
                }
            }
            if($node->hasChildNodes()){
                foreach($node->childNodes as $childNode){ /* @var $childNode DOMNode */
                    self::checkNode($childNode);
                }
            }
        } else if($node->nodeType == XML_DOCUMENT_NODE){
            throw new ZfExtended_SecurityException('Embedded documents are not allowed in the sent markup');
        }
    }
}