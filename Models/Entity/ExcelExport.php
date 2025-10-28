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
declare(strict_types=1);

namespace MittagQI\ZfExtended\Models\Entity;

use MittagQI\ZfExtended\Controller\Response\Header;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelExport
{
    protected Spreadsheet $spreadsheet;

    /**
     * Container to hold document properties like filename etc. (properties->name->value)
     */
    protected string $fileName = 'NoName';

    /**
     * Container to hold fields that should not be shown in the excel
     */
    protected array $hiddenFields = [];

    /**
     * Container to hold lablenames (lablenames->label->translation)
     */
    protected array $labels = [];

    /**
     * Container to hold callback functions for manipulating field-content (callbacks->label->$closureFunction)
     */
    protected array $callbacks = [];

    /**
     * Container to hold fieldType-Definitions
     * (like \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDDSLASH)
     */
    protected array $fieldTypes = [];

    /**
     * Default format for date fields
     */
    protected string $defaultFieldTypeDate = NumberFormat::FORMAT_DATE_YYYYMMDD;

    /**
     * Default format for percent fields
     */
    protected string $defaultFieldTypePercent = '0.0%;[RED]-0.0%';

    /**
     * Default format for currency fields
     */
    protected string $defaultFieldTypeCurrency = NumberFormat::FORMAT_CURRENCY_EUR_INTEGER;

    /**
     * Pre-calculate formulas
     * Forces PhpSpreadsheet to recalculate all formulae in a workbook on saving, so that the pre-calculated values are
     *    immediately available to MS Excel or other office spreadsheet viewer when opening the file
     *
     * @var boolean
     */
    protected bool $preCalculateFormulas = false;

    public function __construct()
    {
        $this->spreadsheet = new Spreadsheet();

        $stringBinder = new StringValueBinder();
        $stringBinder->setNumericConversion(false)
            ->setBooleanConversion(false)
            ->setNullConversion(false)
            ->setFormulaConversion(true);

        $this->spreadsheet->setValueBinder($stringBinder);
    }

    /**
     * set global document format settings
     */
    public function initDefaultFormat(): void
    {
        $this->spreadsheet->getDefaultStyle()->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setWrapText(true);
        // @TODO: add some padding to all fields... but how??
    }

    /**
     * The callback is called before the file is sent for download and after all cells are initialized.
     * In the callback we can do cell resizing,change its value etc...
     */
    public function simpleArrayToExcel(array $data, callable $callback = null): void
    {
        //set spreadsheet cells data from the array
        $this->loadArrayData($data);

        if ($callback !== null) {
            $callback($this->spreadsheet);
        }
        $this->sendDownload();
    }

    /**
     * Loads the array data in the excel spreadsheet
     */
    public function loadArrayData(array $data, int $activeSheetIndex = 0): void
    {
        $this->spreadsheet->setActiveSheetIndex($activeSheetIndex);
        $tempSheet = $this->spreadsheet->getActiveSheet();
        $rowCount = 1;
        foreach ($data as $row) {
            $colCount = 'A';
            foreach ($row as $key => $value) {
                // don't show hidden fields
                if ($this->isHiddenField($key)) {
                    continue;
                }

                // set labels in first row
                if ($rowCount == 1) {
                    $tempSheet->setCellValue($colCount . 1, $this->getLabel($key));
                }

                // if fieldtype is defined for this field, set it.
                if ($this->getFieldType($key) !== null) {
                    $tempSheet
                        ->getStyle($colCount . ($rowCount + 1))
                        ->getNumberFormat()
                        ->setFormatCode($this->getFieldType($key));
                }

                // set fields-value
                $tempSheet->setCellValue($colCount . ($rowCount + 1), $this->getCallback($key, $value));

                $colCount++;
            }
            $rowCount++;
        }
        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $this->spreadsheet->setActiveSheetIndex(0);
    }

    /**
     * Set some properties used to generate the excel
     */
    public function setFilename(string $filename): void
    {
        $this->fileName = $filename;
    }

    /**
     * Adds a field to the list of hidden fields
     */
    public function setHiddenField(string $name): void
    {
        $this->hiddenFields[$name] = true;
    }

    /**
     * Checks if field is hidden
     */
    public function isHiddenField(string $name): bool
    {
        return $this->hiddenFields[$name] ?? false;
    }

    /**
     * Set label used in the excelsheet to transform key of data[key] into a speaking label (or used for translation)
     */
    public function setLabel(string $name, string $label): void
    {
        $this->labels[$name] = $label;
    }

    /**
     * Get label of $name
     */
    public function getLabel(string $name): string
    {
        return $this->labels[$name] ?? $name;
    }

    /**
     * Set callback-function used in the excelsheet to manipulate the $value for a certain $key of data[$key] = $value
     */
    public function setCallback(string $name, callable $function): void
    {
        $this->callbacks[$name] = $function;
    }

    /**
     * Get manipulated value of field $name
     */
    public function getCallback(string $name, mixed $value): mixed
    {
        if (($value == 0 || empty($value)) && array_key_exists($name, $this->fieldTypes)) {
            return '';
        }

        if (array_key_exists($name, $this->callbacks)) {
            return call_user_func($this->callbacks[$name], $value);
        }

        return $value;
    }

    /**
     * Set fieldtype used in the excelsheet to format the output in excel
     */
    public function setFieldType(string $name, string $fieldtype): void
    {
        $this->fieldTypes[$name] = $fieldtype;
    }

    /**
     * Get fieldtype of field $name
     */
    public function getFieldType(string $name): ?string
    {
        return $this->fieldTypes[$name] ?? null;
    }

    /**
     * Set field to date field format in excel output
     * Also sets a callback function to format field value as required
     */
    public function setFieldTypeDate(string $field): void
    {
        // for date fields the following callback must be set
        $stringToDate = function ($date) {
            return Date::PHPToExcel($date);
        };

        $this->setCallback($field, $stringToDate);
        $this->setFieldType($field, $this->defaultFieldTypeDate);
    }

    /**
     * Set field to percent field format in excel output
     * Also sets a callback function to format field value as required
     */
    public function setFieldTypePercent(string $field): void
    {
        // for date fields the following callback must be set
        $percentToPercent = function ($percent) {
            return $percent / 100;
        };

        $this->setCallback($field, $percentToPercent);
        $this->setFieldType($field, $this->defaultFieldTypePercent);
    }

    /**
     * Set field to currency field format in excel output
     */
    public function setFieldTypeCurrency(string $field): void
    {
        $this->setFieldType($field, $this->defaultFieldTypeCurrency);
    }

    /**
     * Set Pre-Calculate Formulas
     *   Set to true (the default) to advise the Writer to calculate all formulae on save
     *   Set to false to prevent precalculation of formulae on save.
     */
    public function setPreCalculateFormulas(bool $pValue = true): self
    {
        $this->preCalculateFormulas = $pValue;

        return $this;
    }

    /**
     * Redirect output to a client's web browser (Excel)
     */
    public function sendDownload(): void
    {
        $fileName = $this->fileName . date('-Y-m-d') . '.xlsx';

        // XLSX Excel 2010 output
        $objWriter = IOFactory::createWriter($this->spreadsheet, 'Xlsx');

        $objWriter->setPreCalculateFormulas($this->preCalculateFormulas);

        Header::sendDownload(
            rawurlencode($fileName),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'max-age=0'
        );
        $objWriter->save('php://output');
        exit;
    }

    /***
     * Save the current spreadsheet to the given path
     * @param string $path
     */
    public function saveToDisc(string $path): void
    {
        // XLSX Excel 2010 output
        $objWriter = IOFactory::createWriter($this->spreadsheet, 'Xlsx');
        $objWriter->setPreCalculateFormulas($this->preCalculateFormulas);
        $objWriter->save($path);
    }

    public function getSpreadsheet(): Spreadsheet
    {
        return $this->spreadsheet;
    }

    public function getAllWorksheets(): array
    {
        return $this->spreadsheet->getAllSheets();
    }

    /**
     * Column index from string.
     */
    public function columnIndexFromString(string $charColumnIndex): int
    {
        return Coordinate::columnIndexFromString($charColumnIndex);
    }

    /**
     * Add a new worksheet to the excel-spreadsheet
     */
    public function addWorksheet(string $sheetName, int $index): void
    {
        $tempWorksheet = new Worksheet($this->spreadsheet, $sheetName);
        // does not work propper in Libre-Office. With Microsoft-Office everything is OK.
        $tempWorksheet->getDefaultColumnDimension()->setAutoSize(true);
        $this->spreadsheet->addSheet($tempWorksheet, $index);
    }

    /**
     * Returns the worksheet of the given name
     */
    public function getWorksheetByName(string $sheetName): Worksheet
    {
        $this->spreadsheet->setActiveSheetIndexByName($sheetName);

        return $this->spreadsheet->getActiveSheet();
    }

    /**
     * Remove the worksheet of the given index
     * @param integer $index
     */
    public function removeWorksheetByIndex(int $index): void
    {
        $this->spreadsheet->removeSheetByIndex($index);
    }

    /***
     * Adjust the column size of each worksheet in given spredsheet
     */
    public function autosizeColumns(Spreadsheet $sp): void
    {
        foreach ($sp->getWorksheetIterator() as $worksheet) {
            $sp->setActiveSheetIndex($sp->getIndex($worksheet));

            $sheet = $sp->getActiveSheet();
            $cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);

            foreach ($cellIterator as $cell) {
                $sheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
            }
        }
    }
}
