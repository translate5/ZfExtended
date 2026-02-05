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

namespace MittagQI\ZfExtended;

use DOMNode;
use MittagQI\ZfExtended\Sanitizer\Attribute\ImageSrcDataSanitizer;
use MittagQI\ZfExtended\Sanitizer\Attribute\TrackChangesAttrSanitizer;
use MittagQI\ZfExtended\Sanitizer\SegmentContentException;
use MittagQI\ZfExtended\Sanitizer\Type;
use MittagQI\ZfExtended\Tools\Markup;
use stdClass;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Zend_Exception;
use Zend_Registry;
use ZfExtended_BadRequest;
use ZfExtended_Dom;
use ZfExtended_Logger;
use ZfExtended_SecurityException as SecurityException;

/**
 * general sanitizer that sanitizes ALL request params
 */
final class Sanitizer
{
    /**
     * Sanitizes a request value that represents the given type
     * The type must be one of our constants
     *
     * @throws SecurityException
     * @throws ZfExtended_BadRequest
     */
    public static function sanitize(?string $val, Type $type): ?string
    {
        if (empty($val)) {
            return $val;
        }

        return match ($type) {
            Type::SegmentContent => self::segmentContent($val),
            Type::Markup => self::markup($val),
            Type::Unsanitized => $val,
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

        if (strtolower($node->nodeName) === 'script' || strtolower($node->nodeName) === 'iframe') {
            throw new SecurityException('Script tags are not allowed in the sent markup');
        }

        if (strtolower($node->nodeName) === 'iframe') {
            throw new SecurityException('Iframe tags are not allowed in the sent markup');
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

        return array_map(function ($value) use ($exceptionRegex) {
            return match (true) {
                is_array($value) => self::escapeHtmlRecursive($value, $exceptionRegex),
                is_string($value) => self::escapeHtml($value, $exceptionRegex),
                is_object($value) => self::escapeHtmlInObject($value, $exceptionRegex),
                default => $value
            };
        }, $content);
    }

    public static function escapeHtmlInObject(?object $content, ?string $exceptionRegex = null): ?object
    {
        if (empty($content)) {
            return $content;
        }

        $result = new stdClass();
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

    /**
     * @throws SegmentContentException
     * @throws ZfExtended_BadRequest
     * @throws Zend_Exception
     */
    public static function segmentContent(string $markup): string
    {
        // Invalid markup will be rejected
        if (! Markup::isValid($markup)) {
            // Note: we do not throw a security-exception here since this error usually comes from our frontend!
            throw new ZfExtended_BadRequest('E1623', [
                'markup' => $markup,
            ]);
        }

        $markup = strip_tags($markup, '<span><div><img><ins><del>');

        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
            ->allowElement('span')
            ->allowElement('img')
            ->allowElement('ins')
            ->allowElement('del')
            ->allowAttribute('src', ['img'])
            ->allowAttribute('class', ['ins', 'del', 'span', 'div', 'img'])
            ->allowAttribute('title', ['ins', 'del', 'span', 'div'])
            ->allowAttribute('data-usertrackingid', ['ins', 'del'])
            ->allowAttribute('data-usertrackingid', ['ins', 'del'])
            ->allowAttribute('data-userguid', ['ins', 'del'])
            ->allowAttribute('data-username', ['ins', 'del'])
            ->allowAttribute('data-usercssnr', ['ins', 'del'])
            ->allowAttribute('data-workflowstep', ['ins', 'del'])
            ->allowAttribute('data-timestamp', ['ins', 'del'])
            ->allowAttribute('data-tbxid', ['div'])
            ->allowAttribute('data-comment', ['img'])
            ->allowAttribute('data-t5qid', ['img', 'div'])
            ->allowAttribute('data-segmentid', ['img'])
            ->allowAttribute('data-fieldname', ['img'])
            ->allowAttribute('id', ['img'])
            ->allowAttribute('data-originalid', ['span'])
            ->allowAttribute('data-length', ['span'])
            ->allowAttribute('data-source', ['span'])
            ->allowAttribute('data-target', ['span'])
            // keep track-changes attrs (timestamps, ids, cssnr, workflowstep) intact while validating format
            ->withAttributeSanitizer(new TrackChangesAttrSanitizer())
            // Restrict data: URIs on img src to safe image mime types only
            ->withAttributeSanitizer(new ImageSrcDataSanitizer())
            ->allowRelativeMedias()
            ->allowRelativeLinks();

        $sanitizer = new HtmlSanitizer($config);

        $cleaned = $sanitizer->sanitize($markup);
        // HtmlSanitizer encodes '+' in attribute values; decode for timestamps so they round-trip
        $cleaned = preg_replace_callback(
            '/data-timestamp="([^"]*)"/',
            static fn (array $m) => 'data-timestamp="' . str_replace('&#43;', '+', $m[1]) . '"',
            $cleaned
        );
        // Restore empty attributes that get rendered as boolean attributes (important for equality check)
        $cleaned = preg_replace('/\b(data-comment|title)(?=[\s>])/i', '$1=""', $cleaned);

        if (self::normalizeForComparison($cleaned) !== self::normalizeForComparison($markup)) {
            self::handleSegmentContentError($markup, $cleaned);
        }

        // Return the original markup to avoid DOM normalization changes (self-closing tags, entity encoding)
        return $markup;
    }

    private static function normalizeEntities(string $markup): string
    {
        return htmlspecialchars(
            html_entity_decode($markup, ENT_QUOTES | ENT_HTML5),
            ENT_QUOTES | ENT_HTML5
        );
    }

    private static function normalizeForComparison(string $markup): string
    {
        // Align void elements (self-closing slash, trailing whitespace) to a single <img…> form
        $markup = preg_replace_callback(
            '/<img([^>]*?)\/?\s*>/i',
            static fn (array $m) => '<img' . rtrim($m[1]) . '>',
            $markup
        );

        return self::normalizeEntities($markup);
    }

    /**
     * @throws SegmentContentException
     * @throws Zend_Exception
     */
    private static function handleSegmentContentError(string $markup, string|null $cleaned): void
    {
        $e = new SegmentContentException('E1764', [
            'input' => $markup,
            'cleaned' => $cleaned,
        ]);

        if (! Zend_Registry::get('config')->runtimeOptions->input->segmentContent->warnOnly) {
            throw $e;
        }

        Zend_Registry::get('logger')->exception(
            $e,
            [
                'level' => ZfExtended_Logger::LEVEL_WARN,
            ]
        );
    }
}
