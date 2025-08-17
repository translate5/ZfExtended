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

use WilsonGlasser\Spout\Common\Entity\ColumnDimension;
use WilsonGlasser\Spout\Writer\Common\Creator\Style\StyleBuilder;
use WilsonGlasser\Spout\Writer\Common\Creator\WriterEntityFactory;
use WilsonGlasser\Spout\Writer\Common\Entity\Sheet;
use WilsonGlasser\Spout\Writer\XLSX\Writer as XLSXWriter;

class ZfExtended_Models_Entity_ExcelExportSpout extends ZfExtended_Models_Entity_ExcelExport
{
    public ?XLSXWriter $writer = null;

    /**
     * @throws \WilsonGlasser\Spout\Common\Exception\UnsupportedTypeException
     */
    public function __construct()
    {
        // Call parent
        parent::__construct();

        // Create writer
        $this->writer = WriterEntityFactory::createWriter('xlsx');

        // Prepare default style
        $defaultStyle = (new StyleBuilder())
            ->setFontName('Calibri')
            ->setFontSize(11)
            ->build();

        // Apply default style
        $this->writer->setDefaultRowStyle($defaultStyle);
    }

    /**
     * @param string $sheetName
     * @throws \WilsonGlasser\Spout\Writer\Exception\SheetNotFoundException
     * @throws \WilsonGlasser\Spout\Writer\Exception\WriterNotOpenedException
     * @throws \Exception
     */
    public function getWorksheetByName($sheetName): Sheet
    {
        foreach ($this->writer->getSheets() as $sheet) {
            if ($sheet->getName() === $sheetName) {
                $this->writer->setCurrentSheet($sheet);

                return $sheet;
            }
        }

        throw new Exception('Workbook does not contain sheet:' . $sheetName);
    }

    /**
     * Add a new worksheet to the excel-spreadsheet
     *
     * @throws \WilsonGlasser\Spout\Writer\Exception\InvalidSheetNameException
     * @throws \WilsonGlasser\Spout\Writer\Exception\WriterNotOpenedException
     */
    public function addWorksheet(string $sheetName, int $index): void
    {
        $this->writer->addNewSheetAndMakeItCurrent();
        $this->writer->getCurrentSheet()->setName($sheetName);
    }

    /**
     * Loads the array data in the excel spreadsheet
     */
    public function loadArrayData(array $data, int $activeSheetIndex = 0)
    {
        $this->setCurrentSheetByIndex($activeSheetIndex);

        // Write headings
        if (count($data)) {
            $this->writeHeadings(array_keys($data[0]));
        }

        // Write data
        foreach ($data as $item) {
            // Init new row
            $row = WriterEntityFactory::createRow();

            // Foreach data field
            foreach ($item as $field => $value) {
                // Skip if hidden
                if ($this->isHiddenField($field)) {
                    continue;
                }

                // Add cell
                $row->addCell(WriterEntityFactory::createCell($this->getCallback($field, $value)));
            }

            // Write row to the sheet
            $this->writer->addRow($row);
        }

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $this->setCurrentSheetByIndex(0);
    }

    /**
     * @throws \WilsonGlasser\Spout\Writer\Exception\SheetNotFoundException
     * @throws \WilsonGlasser\Spout\Writer\Exception\WriterNotOpenedException
     */
    public function setCurrentSheetByIndex(int $sheetIndex): void
    {
        $this->writer->setCurrentSheet($this->writer->getSheets()[$sheetIndex]);
    }

    /**
     * @throws \WilsonGlasser\Spout\Common\Exception\IOException
     * @throws \WilsonGlasser\Spout\Writer\Exception\WriterNotOpenedException
     */
    public function writeHeadings(array $headingFields): void
    {
        // Get current sheet
        $sheet = $this->writer->getCurrentSheet();

        // Init headings row
        $row = WriterEntityFactory::createRow();

        // Columns array
        $columns = explode(',', 'A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z');

        // Foreach heading field
        foreach ($headingFields as $headingField) {
            // Skip if hidden
            if ($this->isHiddenField($headingField)) {
                continue;
            }

            // Add to the row
            $row->addCell(WriterEntityFactory::createCell($this->getLabel($headingField)));

            // Add autoSize
            $sheet->addColumnDimension(new ColumnDimension(
                array_shift($columns),
                -1,
                true
            ));
        }

        // Write row to the sheet
        $this->writer->addRow($row);
    }
}
