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

namespace MittagQI\ZfExtended\Models\Installer;

/**
 * Checking SQL files (and also PHP) for unwanted contents like
 * Delimiters and Constraints that are not explicitly named
 */
class DbUpdateFileCheck
{
    private string $error;

    private bool $segmentTablesChanged;

    public function __construct(
        private readonly string $absolutePath,
    ) {
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function hasSegmentTablesChanges(): bool
    {
        return $this->segmentTablesChanged;
    }

    public function checkAndSanitize(): ?string
    {
        $content = file_get_contents($this->absolutePath);
        if ($content === false) {
            throw new \ZfExtended_Exception('Cannot read path ' . $this->absolutePath);
        }

        if (strtolower(pathinfo($this->absolutePath, PATHINFO_EXTENSION)) === 'sql') {
            $content = $this->cleanSql($content);
        }

        //prevent DEFINER=root`@`localhost` from TRIGGERs and VIEWs to be executed
        if (str_contains($content, 'DEFINER=')) {
            $this->error = 'The file ' . $this->absolutePath .
                ' contains DEFINER= statements, they must be removed manually before!';

            return null;
        }
        //prevent DELIMITER statements in the code (https://stackoverflow.com/a/5314879/1749200):
        if (str_contains($content, 'DELIMITER')) {
            $this->error = 'The file ' . $this->absolutePath . ' contains DELIMITER statements,' .
                ' they must be removed since not needed and not usable by PHP based import!';

            return null;
        }
        // prevent not explicitly named constraints
        $constraints = $this->findUnnamedConstraints($content);
        if (count($constraints) > 0) {
            $this->error = 'The file ' . $this->absolutePath . ' contains CONSTRAINTS that are not explicitly named.' .
                ' These constrained must be named: "' . implode('", "', $constraints) . '"';

            return null;
        }

        $this->checkSegmentTablesChanges($content);

        return $content;
    }

    private function cleanSql(string $sql): string
    {
        // Replace CRLF line endings with LF, as otherwise below preg replace won't work
        $sql = preg_replace('~\r\n~', "\n", $sql);

        //remove DELIMITER statements and replace back the ;; delimiter to ;
        // reason is that it is not needed and not usable for PHP import
        return preg_replace_callback('/^DELIMITER ;;$(.*?)^DELIMITER ;$/ms', function ($matches) {
            return preg_replace('#;;$#', ';', $matches[1]);
        }, $sql);
    }

    private function findUnnamedConstraints(string $sql): array
    {
        $matches = [];
        $constraints = [];
        // find "FOREIGN KEY ... ON DELETE|UPDATE" constructions without preceiding CONSTRAINT or no CONSTRAINT NAME
        $result = preg_match_all(
            '/(constraint[^,;]+)?foreign\s+key\s+[^,;]+references[^,;]+\s+on\s+(delete|update)\s+(restrict|cascade|set|no)/is',
            $sql,
            $matches,
            PREG_PATTERN_ORDER
        );
        if ($result !== false && $result > 0) {
            foreach ($matches[0] as $match) {
                $match = $this->normalizeWhitespace($match);
                $lower = strtolower($match);
                // we included the "constraint" in the match to be able to find those without ...
                if (! str_contains($lower, 'constraint')) {
                    $constraints[] = $match;
                    // echo "\nMATCH: " .$match. "\n";
                    // or it contains no CONSTRAINT name
                } elseif (str_contains($lower, 'constraint foreign')) {
                    $constraints[] = $match;
                    // echo "\nMATCH: " .$match. "\n";
                }
            }
            // it could be, that explicit constraints without ON UPDATE|DELETE are created ...
        } elseif (preg_match_all('/constraint\s+(foreign|unique|check)/is', $sql, $matches, PREG_PATTERN_ORDER) > 0) {
            foreach ($matches[0] as $match) {
                $constraints[] = $this->normalizeWhitespace($match);
            }
            // echo "\nMATCHES: " .print_r($matches[0], true). "\n";
        }

        return $constraints;
    }

    private function checkSegmentTablesChanges(string $sql): void
    {
        $this->segmentTablesChanged = preg_match(
                 // altering table
                '~alter\s+table\s+' .
                // table-name with/without database-prefix
                '(?:`?\w+`?\.)?`?(LEK_segments|LEK_segment_data)`?\s+(' .
                // add column
                'add\s|' .
                'add\s+column\s|' .
                // drop column
                'drop\s|' .
                'drop\s+column\s|' .
                // modify/change/rename column
                'modify\s+column\s|' .
                'change\s+column\s|' .
                'rename\s+column\s' .
                ')~i',
                $sql
            ) === 1;
    }

    private function normalizeWhitespace(string $sql): string
    {
        $sql = str_replace(["\r", "\n", "\t"], ['', ' ', ' '], $sql);

        return preg_replace('/ +/', ' ', $sql);
    }
}
