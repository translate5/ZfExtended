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

use DirectoryIterator;
use Exception;
use FilesystemIterator;

final class RecursiveFileDeletion
{
    private array $deletedElements = [];

    public function __construct(
        protected bool $dryRun = false
    ) {
    }

    public function recursiveDelete(
        string $directory,
        ?array $extensionWhitelist = null,
        bool $whitelistIsBlacklist = false,
        bool $doDeletePassedDirectory = true
    ): bool {
        $iterator = new DirectoryIterator($directory);
        $dirIsEmpty = true; // we need to know for deleting $directory
        foreach ($iterator as $fileinfo) {
            /* @var DirectoryIterator $fileinfo */
            if ($fileinfo->isDot()) {
                continue;
            }
            if ($fileinfo->isDir()) {
                if (! $this->recursiveDelete(
                    $directory . DIRECTORY_SEPARATOR . $fileinfo->getFilename(),
                    $extensionWhitelist,
                    $whitelistIsBlacklist,
                    $doDeletePassedDirectory
                )) {
                    $dirIsEmpty = false;
                }
            } elseif (
                $fileinfo->isFile() &&
                $this->recursiveDoDeleteExtension(
                    $fileinfo->getExtension(),
                    $extensionWhitelist,
                    $whitelistIsBlacklist
                )) {
                try {
                    if (! $this->dryRun) {
                        unlink($directory . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
                    }
                    $this->deletedElements[] = $directory . DIRECTORY_SEPARATOR . $fileinfo->getFilename();
                } catch (Exception $e) {
                    error_log(
                        'ZfExtended_Utils::recursiveDelete: Could not delete file ' .
                        $directory . DIRECTORY_SEPARATOR . $fileinfo->getFilename() . ': ' . $e->getMessage()
                    );
                    $dirIsEmpty = false;
                }
            } else {
                $dirIsEmpty = false;
            }
        }
        if ($extensionWhitelist === null && $dirIsEmpty && $doDeletePassedDirectory) {
            try {
                if ($this->dryRun || rmdir($directory)) {
                    $this->deletedElements[] = $directory;

                    return true;
                }
            } catch (Exception $e) {
                error_log(
                    'ZfExtended_Utils::recursiveDelete: Could not delete file ' .
                    $directory . DIRECTORY_SEPARATOR . ': ' . $e->getMessage()
                );
            }
        }

        return false;
    }

    /**
     * Helper for recursiveDelete to evaluate the black/whitelist param
     */
    private function recursiveDoDeleteExtension(
        string $extension,
        ?array $extensionWhitelist,
        bool $whitelistIsBlacklist
    ): bool {
        if ($extensionWhitelist === null) {
            return true;
        } elseif ($whitelistIsBlacklist) {
            return ! in_array($extension, $extensionWhitelist);
        } else {
            return in_array($extension, $extensionWhitelist);
        }
    }

    /**
     * Remove files older than the given timestamp AND THE SUBDIRECTORIES IF EMPTY!
     * @return bool true if the directory has remaining content or not
     */
    public function deleteOldFiles(
        string $directory,
        int $olderThan,
    ): bool {
        $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);
        $hasContent = false;
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                $subDir = $directory . DIRECTORY_SEPARATOR . $fileInfo->getFilename();
                $subDirHasContent = $this->deleteOldFiles($subDir, $olderThan);
                if ($subDirHasContent) {
                    $hasContent = true;
                } elseif ($this->dryRun || rmdir($subDir)) {
                    $this->deletedElements[] = $subDir;
                } else {
                    $hasContent = true; //rmdir failed, so there might be content
                }

                continue;
            }

            if ($fileInfo->isFile() && filemtime($fileInfo->getRealPath()) < $olderThan) {
                $path = $fileInfo->getRealPath();
                if (! $this->dryRun) {
                    unlink($path);
                }
                $this->deletedElements[] = $path;

                continue;
            }

            $hasContent = true;
        }

        return $hasContent;
    }

    /**
     * returns the collected deleted elements of all calls of the instance
     */
    public function getDeletedElements(): array
    {
        return $this->deletedElements;
    }

    public function resetDeletedElements(): void
    {
        $this->deletedElements = [];
    }
}
