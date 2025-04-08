<?php

use WilsonGlasser\Spout\Common\Manager\OptionsManagerInterface;
use WilsonGlasser\Spout\Writer\Common\Entity\Options;
use WilsonGlasser\Spout\Writer\Common\Manager\Style\StyleMerger;
use WilsonGlasser\Spout\Writer\XLSX\Manager\SharedStringsManager;
use WilsonGlasser\Spout\Writer\XLSX\Manager\Style\StyleManager;
use WilsonGlasser\Spout\Writer\XLSX\Manager\Style\StyleRegistry;
use WilsonGlasser\Spout\Writer\XLSX\Manager\WorkbookManager;

/**
 * Class ManagerFactory
 * Factory for managers needed by the XLSX Writer
 */
class ZfExtended_Models_Entity_Spout_ManagerFactory extends WilsonGlasser\Spout\Writer\XLSX\Creator\ManagerFactory
{
    /**
     * @param OptionsManagerInterface $optionsManager
     * @return WorkbookManager
     */
    public function createWorkbookManager(OptionsManagerInterface $optionsManager)
    {
        $workbook = $this->entityFactory->createWorkbook();

        $fileSystemHelper = $this->helperFactory->createSpecificFileSystemHelper($optionsManager, $this->entityFactory);
        $fileSystemHelper->createBaseFilesAndFolders();

        $xlFolder = $fileSystemHelper->getXlFolder();
        $sharedStringsManager = $this->createSharedStringsManager($xlFolder);

        $styleMerger = $this->createStyleMerger();
        $styleManager = $this->createStyleManager($optionsManager);
        $worksheetManager = $this->createWorksheetManager($optionsManager, $styleManager, $styleMerger, $sharedStringsManager);

        $stringsEscaper = $this->helperFactory->createStringsEscaper();
        $commentsManager = $this->createCommentsManager($stringsEscaper);

        return new WorkbookManager(
            $workbook,
            $optionsManager,
            $worksheetManager,
            $commentsManager,
            $styleManager,
            $styleMerger,
            $fileSystemHelper,
            $this->entityFactory,
            $this
        );
    }

    /**
     * @param OptionsManagerInterface $optionsManager
     * @param Stylemanager $styleManager
     * @param StyleMerger $styleMerger
     * @param SharedStringsManager $sharedStringsManager
     * @return \ZfExtended_Models_Entity_Spout_WorksheetManager
     */
    private function createWorksheetManager(
        OptionsManagerInterface $optionsManager,
        StyleManager $styleManager,
        StyleMerger $styleMerger,
        SharedStringsManager $sharedStringsManager
    ) {
        $rowManager = $this->createRowManager();
        $stringsEscaper = $this->helperFactory->createStringsEscaper();

        return new \ZfExtended_Models_Entity_Spout_WorksheetManager(
            $optionsManager,
            $rowManager,
            $styleManager,
            $styleMerger,
            $sharedStringsManager,
            $stringsEscaper,
            $this->entityFactory
        );
    }

    /**
     * @param OptionsManagerInterface $optionsManager
     * @return StyleManager
     */
    private function createStyleManager(OptionsManagerInterface $optionsManager)
    {
        $styleRegistry = $this->createStyleRegistry($optionsManager);

        return new StyleManager($styleRegistry);
    }

    /**
     * @param OptionsManagerInterface $optionsManager
     * @return StyleRegistry
     */
    private function createStyleRegistry(OptionsManagerInterface $optionsManager)
    {
        $defaultRowStyle = $optionsManager->getOption(Options::DEFAULT_ROW_STYLE);

        return new StyleRegistry($defaultRowStyle);
    }

    /**
     * @return StyleMerger
     */
    private function createStyleMerger()
    {
        return new StyleMerger();
    }

    /**
     * @param string $xlFolder Path to the "xl" folder
     * @return SharedStringsManager
     */
    private function createSharedStringsManager($xlFolder)
    {
        $stringEscaper = $this->helperFactory->createStringsEscaper();

        return new SharedStringsManager($xlFolder, $stringEscaper);
    }
}
