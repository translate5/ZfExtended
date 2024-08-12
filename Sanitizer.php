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

use MittagQI\ZfExtended\Tools\Markup;
use ZfExtended_SecurityException as SecurityException;

/**
 * general sanitizer that sanitizes ALL request params
 */
final class ZfExtended_Sanitizer
{
    /**
     * Leads to stripping of all tags
     */
    public const STRING = 'string';

    /**
     * Leads to checking for script-tags & on** handlers and javascript: URLs
     * In these cases exceptions are thrown
     */
    public const MARKUP = 'markup';

    /**
     * leads to NO sanitization and thus the application logic must ensure XSS prevention
     */
    public const UNSANITIZED = 'unsanitized';

    /**
     * Sanitizes a request value that represents the given type
     * The type must be one of our constants
     *
     * @throws SecurityException
     */
    public static function sanitize(?string $val, string $type): ?string
    {
        if (empty($val)) {
            return $val;
        }

        return match ($type) {
            self::MARKUP => self::markup($val),
            self::UNSANITIZED => $val,
            default => self::string($val),
        };
    }

    public static function string(string $string): string
    {
        return strip_tags($string);
    }

    /**
     * Sanitizes markup
     * If unwanted contents are detected, an exception is thrown, otherwise the markup is returned
     * Note, that this function expects markup and not whole documents
     * Note, that invalid markup will be stripped and the text returned
     *
     * @throws SecurityException
     * @throws ZfExtended_BadRequest
     */
    public static function markup(string $markup): string
    {
        // Invalid markup will be rejected
        if (! Markup::isValid($markup)) {
            // Note: we do not throw a security-exception here since this error usually comes from our frontend!
            throw new ZfExtended_BadRequest('E1623', [
                'markup' => $markup,
            ]);
        }

        $dom = new ZfExtended_Dom();
        $nodeList = $dom->loadUnicodeMarkup($markup);
        // this is debatable: when invalid markup is posted, we remove it and use the posted text contents
        // this is neccessary, as browsers are much more tolerant than DOMDocument,
        // and we can not expect the broken markup to be "broken enough" to not make attacks possible
        // may it is better to throw an "invalid markup exception" here ?
        // TODO FIXME: can that still happen with the isValid check above ??
        if ($nodeList === null) {
            return strip_tags($markup);
        }

        foreach ($nodeList as $node) {
            self::checkNode($node);
        }

        return $markup;
    }

    /**
     * Checks a DOM node and throws an exception if something illegal was detected
     *
     * @throws SecurityException
     */
    private static function checkNode(DOMNode $node): void
    {
        if ($node->nodeType == XML_DOCUMENT_NODE) {
            throw new SecurityException('Embedded documents are not allowed in the sent markup');
        }

        if ($node->nodeType != XML_ELEMENT_NODE) {
            return;
        }

        if (strtolower($node->nodeName) === 'script') {
            throw new SecurityException('Script tags are not allowed in the sent markup');
        }

        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $childNode) {
                /* @var $childNode DOMNode */
                self::checkNode($childNode);
            }
        }

        if (! $node->hasAttributes()) {
            return;
        }

        /* @var $attribute DOMNode */
        foreach ($node->attributes as $attribute) {
            $name = strtolower($attribute->nodeName);

            // any event-handler attribute will be rejected.
            // We do not check, if this is actually a valid event-handler, so "onanything" will also be invalid
            if (strlen($name) > 2 && str_starts_with($name, 'on')) {
                throw new SecurityException('Event-handler attributes are not allowed in the sent markup');
            }

            if ($name !== 'href') {
                continue;
            }

            // attack with "javascript:" pseudo-protocol
            $href = preg_replace('/\s+/', '', strtolower($attribute->nodeValue));

            if (str_starts_with($href, 'javascript:')) {
                throw new SecurityException('JavaScript-hrefs are not allowed in the sent markup');
            }
        }
    }

    public static function escapeHtml(?string $content, ?string $exceptionRegex = null): ?string
    {
        if (empty($content)) {
            return $content;
        }

        if (null === $exceptionRegex) {
            return Markup::escapeAllQuotes($content);
        }

        $hasInternalTags = false !== preg_match_all($exceptionRegex, $content, $tags, PREG_SET_ORDER);

        if (! $hasInternalTags) {
            return Markup::escapeAllQuotes($content);
        }

        $textParts = preg_split($exceptionRegex, $content);

        $result = '';
        foreach ($textParts as $i => $part) {
            $result .= Markup::escapeText($part);

            if (isset($tags[$i])) {
                $result .= $tags[$i][0];
            }
        }

        return $result;
    }

    public static function escapeHtmlRecursive(?array $content, ?string $exceptionRegex = null): ?array
    {
        if (empty($content)) {
            return $content;
        }

        $result = [];
        foreach ($content as $key => $value) {
            $result[$key] = match (true) {
                is_array($value) => self::escapeHtmlRecursive($value, $exceptionRegex),
                is_string($value) => self::escapeHtml($value, $exceptionRegex),
                is_object($value) => self::escapeHtmlInObject($value, $exceptionRegex),
                default => $value
            };
        }

        return $result;
    }

    public static function escapeHtmlInObject(?object $content, ?string $exceptionRegex = null): ?object
    {
        if (empty($content)) {
            return $content;
        }

        $result = new \stdClass();
        foreach ((array) $content as $key => $value) {
            $result->{$key} = match (true) {
                is_array($value) => self::escapeHtmlRecursive($value, $exceptionRegex),
                is_string($value) => self::escapeHtml($value, $exceptionRegex),
                is_object($value) => self::escapeHtmlInObject($value, $exceptionRegex),
                default => $value
            };
        }

        return $result;
    }
}
