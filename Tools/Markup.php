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
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execption
             http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

namespace MittagQI\ZfExtended\Tools;

use Zend_Registry;

final class Markup
{
    /**
     * @var string
     */
    public const PATTERN = '~(</{0,1}[a-zA-Z][^>]*/{0,1}>)~';

    /**
     * works only if ungreedy !
     * @var string
     */
    public const COMMENT_PATTERN = '~(<!--.*-->)~';

    /**
     * @var string
     */
    public const IMAGE_PATTERN = '~<img[^>]*>~';

    /**
     * Evaluates if a text contains Markup
     * @param string $text
     * @return bool
     */
    private static bool $strictEscapingUsed;

    /**
     * The central function that defines, if we use strict escaping (">" always escaped in text-content) or not
     * @throws \Zend_Exception
     */
    public static function useStrictEscaping(): bool
    {
        if (! isset(self::$strictEscapingUsed)) {
            $config = Zend_Registry::get('config');
            self::$strictEscapingUsed = $config->runtimeOptions->segment->useStrictEscaping;
        }

        return self::$strictEscapingUsed;
    }

    /**
     * Detect if a string contains any tags
     */
    public static function isMarkup(string $text): bool
    {
        return (strip_tags($text) !== $text);
    }

    /**
     * Small Helper to not duplicate code
     */
    public static function isEmpty(?string $text): bool
    {
        return ($text === null || $text === '');
    }

    /**
     * Check if the text is valid markup i.e. can be parsed with our Markup-Parsers
     * Note, that this is tolerant against smaller Problems like <img src="SOMESOURCE"> -> missing self-closing
     * delimiter
     */
    public static function isValid(string $markup): bool
    {
        // if there is markup, we have to make sure it's valid
        if (strip_tags($markup) != $markup) {
            $domDocument = new \ZfExtended_Dom();
            $domDocument->loadUnicodeMarkup($markup);

            return $domDocument->isValid(false);
        }

        return true;
    }

    /**
     * escapes markup, leaves the tags and comments alive but escape any text inbetween to XML standards
     * Obviously this expect the markup to be valid...
     */
    public static function escape(string $markup): string
    {
        return self::escapeMarkup($markup, false);
    }

    /**
     * Escapes markup by re-escaping it
     */
    public static function reEscape(string $markup): string
    {
        return self::escapeMarkup($markup, true);
    }

    /**
     * Unescapes markup escaped with ::escape
     * Be aware that this may creates invalid Markup !
     */
    public static function unescape(string $markup): string
    {
        // first we need to unescape comments as they would be destroyed by the next step otherwise
        $parts = preg_split(self::COMMENT_PATTERN . 'Us', $markup, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        foreach ($parts as $part) {
            if (preg_match(self::COMMENT_PATTERN . 's', $part) === 1) {
                $result .= $part;
            } else {
                $result .= self::unescapePureMarkup($part);
            }
        }

        return $result;
    }

    /**
     * Escapes text to XML conformity that is known to contain no tags
     */
    public static function escapeText(?string $textWithoutTags): string
    {
        if (self::isEmpty($textWithoutTags)) {
            return '';
        }

        return htmlspecialchars($textWithoutTags, ENT_XML1 | ENT_COMPAT | ENT_SUBSTITUTE, null, false);
    }

    /**
     * Unescapes text that was escaped with our ::escape API
     */
    public static function unescapeText(?string $text): string
    {
        if (self::isEmpty($text)) {
            return '';
        }

        return htmlspecialchars_decode($text, ENT_XML1 | ENT_COMPAT);
    }

    /**
     * Escapes text by re-escaping it
     */
    public static function reEscapeText(?string $text): string
    {
        if (self::isEmpty($text)) {
            return '';
        }

        return htmlspecialchars(
            htmlspecialchars_decode($text, ENT_XML1 | ENT_QUOTES),
            ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE
        );
    }

    /**
     * Escapes a string for use in as attribute-value in a HTML/XML tag
     * @param bool $escapeWhitespace : if given, whitespace-chars, which shall not show up in attributes, are also
     *     escaped
     */
    public static function escapeForAttribute(?string $value, bool $escapeWhitespace = true): string
    {
        if (self::isEmpty($value)) {
            return '';
        }
        // we encode double here
        $escaped = htmlspecialchars($value, ENT_XML1 | ENT_COMPAT | ENT_SUBSTITUTE, null, true);

        // if wanted with "toxic" whitespace chars
        return ($escapeWhitespace) ? self::whitespaceToEntities($escaped) : $escaped;
    }

    /**
     * Unescapes a string that was escaped with ::escapeForAttribute
     * @param bool $unescapeWhitespace : if given, whitespace-chars, which shall not show up in attributes, are also
     *     un-escaped
     */
    public static function unescapeFromAttribute(?string $value, bool $unescapeWhitespace = true): string
    {
        if (self::isEmpty($value)) {
            return '';
        }
        $unescaped = ($unescapeWhitespace) ? self::entitiesToWhitespace($value) : $value;

        return htmlspecialchars_decode($unescaped, ENT_XML1 | ENT_COMPAT);
    }

    /**
     * Escapes text but leaves any Quotes untouched
     */
    public static function escapeNoQuotes(?string $text): string
    {
        if (self::isEmpty($text)) {
            return '';
        }

        return htmlspecialchars($text, ENT_XML1 | ENT_SUBSTITUTE, null, false);
    }

    /**
     * Escapes text with single and double quotes
     */
    public static function escapeAllQuotes(?string $text): string
    {
        if (self::isEmpty($text)) {
            return '';
        }

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, null, false);
    }

    /**
     * Un-Escapes text with single and double quotes
     */
    public static function unescapeAllQuotes(?string $text): string
    {
        if (self::isEmpty($text)) {
            return '';
        }

        return htmlspecialchars_decode($text, ENT_XML1 | ENT_QUOTES);
    }

    public static function strip(string $markup, string $newline = "\n"): string
    {
        $markup = self::breaksToNewlines($markup, $newline);

        return strip_tags($markup);
    }

    public static function stripImages(string $markup): string
    {
        return preg_replace(self::IMAGE_PATTERN . 'U', '', $markup);
    }

    public static function breaksToNewlines(string $markup, string $newline = "\n"): string
    {
        return preg_replace('~<br\s*/{0,1}>~i', "\n", $markup);
    }

    public static function newlinesToBreak(string $text, string $breaktag = '<br/>'): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        return str_replace("\n", $breaktag, $text);
    }

    /**
     * Protects tags with special t5 tags like '<t5tag17/>'
     * This protection can help avoiding problems with texts / characters in attributes or with invalid nestings since
     * the returned text just contains simple single tags The non-tag will be escaped and unescaped when reverting back
     * The protected markup is accessibe via $protectionResult->markup
     */
    public static function protectTags(string $markup): \stdClass
    {
        $result = new \stdClass();
        $result->map = [];
        $result->markup = '';
        // first, replace Comments
        $count = 0;
        $converted = '';
        $parts = preg_split(self::COMMENT_PATTERN . 'Us', $markup, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $part) {
            if (preg_match(self::COMMENT_PATTERN . 's', $part) === 1) {
                $key = '<t5protectedcomment' . $count . '/>';
                $converted .= $key;
                $result->map[$key] = $part;
                $count++;
            } else {
                $converted .= $part;
            }
        }
        // second, replace the tags (but keep comment-tags alive)
        $count = 0;
        $parts = preg_split(self::PATTERN . 'U', $converted, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $part) {
            if (preg_match(self::PATTERN, $part) === 1) {
                if (substr($part, 0, 19) === '<t5protectedcomment') {
                    $result->markup .= $part;
                } else {
                    $key = '<t5protectedtag' . $count . '/>';
                    $result->markup .= $key;
                    $result->map[$key] = $part;
                    $count++;
                }
            } else {
                $result->markup .= self::escapeText($part);
            }
        }

        return $result;
    }

    /**
     * @param \stdClass $protectionResult : must be what ::protectTags returns
     */
    public static function unprotectTags(string $tagProtectedMarkup, \stdClass $protectionResult): string
    {
        $parts = preg_split(
            self::PATTERN . 'U',
            $tagProtectedMarkup,
            -1,
            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
        );
        $result = '';
        foreach ($parts as $part) {
            if (preg_match(self::PATTERN, $part) === 1) {
                $result .= (array_key_exists($part, $protectionResult->map) ? $protectionResult->map[$part] : $part);
            } else {
                $result .= self::unescapeText($part);
            }
        }

        return $result;
    }

    /**
     * Internal API to unify escape/re-escape
     * Protects comments that may be in the markup
     */
    public static function escapeMarkup(string $markup, bool $reEscape = false): string
    {
        // first we need to escape comments as they would be destroyed by the next step otherwise
        $parts = preg_split(self::COMMENT_PATTERN . 'Us', $markup, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        foreach ($parts as $part) {
            if (preg_match(self::COMMENT_PATTERN . 's', $part) === 1) {
                $result .= $part;
            } else {
                $result .= self::escapePureMarkup($part, $reEscape);
            }
        }

        return $result;
    }

    /**
     * Escapes Markup that is expected to contain no comments
     * Optionally can be used to re-escape
     */
    private static function escapePureMarkup(string $markup, bool $reEscape = false): string
    {
        $parts = preg_split(self::PATTERN . 'U', $markup, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        foreach ($parts as $part) {
            if (preg_match(self::PATTERN, $part) === 1) {
                $result .= $part;
            } else {
                $result .= ($reEscape) ? self::reEscapeText($part) : self::escapeText($part);
            }
        }

        return $result;
    }

    /**
     * Unescapes markup escaped with ::escape
     * Be aware that this may create invalid Markup !
     */
    private static function unescapePureMarkup(string $markup): string
    {
        $parts = preg_split(self::PATTERN . 'U', $markup, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        foreach ($parts as $part) {
            if (preg_match(self::PATTERN, $part) === 1) {
                $result .= $part;
            } else {
                $result .= self::unescapeText($part);
            }
        }

        return $result;
    }

    /**
     * Prepares whitespace for the use in XML/HTML-Attributes
     */
    private static function whitespaceToEntities(string $text): string
    {
        return str_replace(["\r", "\n", "\t"], ['&#13;', '&#10;', '&#9;'], $text);
    }

    /**
     * Re-converts whitespace from an XML/HTML-Attributes
     */
    private static function entitiesToWhitespace(string $text): string
    {
        return str_replace(['&#13;', '&#10;', '&#9;'], ["\r", "\n", "\t"], $text);
    }
}
