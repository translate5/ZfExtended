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

use MittagQI\ZfExtended\Controller\Response\Header;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ZfExtended_Models_Entity_ExcelExport
{
    /**
     * @var PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    protected $spreadsheet = false;

    /**
     * Container to hold document properties like filename etc. (properties->name->value)
     * @var stdClass
     */
    protected $properties = false;

    /**
     * Container to hold fields that should not be shown in the excel
     * @var stdClass
     */
    protected $hiddenFields = false;

    /**
     * Container to hold lablenames (lablenames->label->translation)
     * @var stdClass
     */
    protected $labels = false;

    /**
     * Container to hold callback functions for manipulating field-content (callbacks->label->$closureFunction)
     * @var stdClass
     */
    protected $callbacks = [];

    /**
     * Container to hold fieldType-Definitions (like \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDDSLASH)
     * @var stdClass
     */
    protected $fieldTypes = false;

    /**
     * Default format for date fields
     * @var string
     */
    protected $_defaultFieldTypeDate = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDD2;

    /**
     * Default format for percent fields
     * @var string
     */
    protected $_defaultFieldTypePercent = '0.0%;[RED]-0.0%';

    /**
     * Default format for currency fields
     * @var string
     */
    protected $_defaultFieldTypeCurrency = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE;

    /**
     * Pre-calculate formulas
     * Forces PhpSpreadsheet to recalculate all formulae in a workbook when saving, so that the pre-calculated values are
     *    immediately available to MS Excel or other office spreadsheet viewer when opening the file
     *
     * @var boolean
     */
    protected $_preCalculateFormulas = false;

    public function __construct()
    {
        $this->spreadsheet = new Spreadsheet();

        $this->properties = new stdClass();
        $this->properties->filename = 'ERP-Export';

        $this->hiddenFields = new stdClass();
        $this->labels = new stdClass();
        $this->callbacks = new stdClass();
        $this->fieldTypes = new stdClass();
    }

    /**
     * set global document format settings
     */
    public function initDefaultFormat()
    {
        $this->spreadsheet->getDefaultStyle()->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
            ->setWrapText(true);
        // @TODO: add some padding to all fields... but how??
    }

    /**
     * The callback is called before the file is sent for download and after all cells are initialized.
     * In the callback we can do cell resizing,change its value etc...
     */
    public function simpleArrayToExcel(array $data, Closure $callback = null)
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
    public function loadArrayData(array $data, int $activeSheetIndex = 0)
    {
        $this->spreadsheet->setActiveSheetIndex($activeSheetIndex);
        $tempSheet = $this->spreadsheet->getActiveSheet();
        $rowCount = 1;
        foreach ($data as $row) {
            $colCount = 1;
            foreach ($row as $key => $value) {
                // don't show hidden fields
                if ($this->isHiddenField($key)) {
                    continue;
                }

                // set labels in first row
                if ($rowCount == 1) {
                    $tempSheet->setCellValueByColumnAndRow($colCount, 1, $this->getLabel($key));
                }

                // if fieldtype is defined for this field, set it.
                if ($this->getFieldType($key)) {
                    $tempSheet->getStyleByColumnAndRow($colCount, $rowCount + 1)->getNumberFormat()->setFormatCode($this->getFieldType($key));
                }

                // set fields-value
                $tempSheet->setCellValueByColumnAndRow($colCount, $rowCount + 1, $this->getCallback($key, $value));

                $colCount++;
            }
            $rowCount++;
        }
        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $this->spreadsheet->setActiveSheetIndex(0);
    }

    /**
     * Set some properties used to generate the excel
     * @param mixed $value
     */
    public function setProperty(string $name, $value)
    {
        $this->properties->$name = $value;
    }

    /**
     * Get property with name $name used to generate the excel
     * @return mixed
     */
    public function getProperty(string $name)
    {
        if (property_exists($this->properties, $name)) {
            return $this->properties->$name;
        }

        return false;
    }

    /**
     * Adds a field to the list of hidden fields
     */
    public function setHiddenField(string $name)
    {
        $this->hiddenFields->$name = true;
    }

    /**
     * Checks if field is hidden
     * @return boolean
     */
    public function isHiddenField(string $name)
    {
        if (property_exists($this->hiddenFields, $name)) {
            return true;
        }

        return false;
    }

    /**
     * Set label used in the excelsheet to transform key of data[key] into a speaking label (or used for translation)
     * @param string $label
     */
    public function setLabel(string $name, $label)
    {
        $this->labels->$name = $label;
    }

    /**
     * Get label of $name
     * @return string
     */
    public function getLabel(string $name)
    {
        if (property_exists($this->labels, $name)) {
            return $this->labels->$name;
        }

        return $name;
    }

    /**
     * Set callback-function used in the excelsheet to manipulate the $value for a certain $key of data[$key] = $value
     * @param Closure $function as closure function variable
     */
    public function setCallback(string $name, $function)
    {
        $this->callbacks->$name = $function;
    }

    /**
     * Get manipulated value of field $name
     * @param mixed $value
     * @return mixed
     */
    public function getCallback(string $name, $value)
    {
        if (($value == 0 || empty($value)) && property_exists($this->fieldTypes, $name)) {
            return '';
        }

        if (property_exists($this->callbacks, $name)) {
            return call_user_func($this->callbacks->$name, $value);
        }

        return $value;
    }

    /**
     * Set fieldtype used in the excelsheet to format the output in excel
     * @param string $fieldtype
     */
    public function setFieldType(string $name, $fieldtype)
    {
        $this->fieldTypes->$name = $fieldtype;
    }

    /**
     * Get fieldtype of field $name
     * @return string
     */
    public function getFieldType(string $name)
    {
        if (property_exists($this->fieldTypes, $name)) {
            return $this->fieldTypes->$name;
        }
    }

    /**
     * Set field to date field format in excel output
     * Also sets a callback function to format field value as required
     */
    public function setFieldTypeDate(string $field): void
    {
        // for date fields the following callback must be set
        $stringToDate = function ($date) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date);
        };

        $this->setCallback($field, $stringToDate);
        $this->setFieldType($field, $this->_defaultFieldTypeDate);
    }

    /**
     * Set field to percent field format in excel output
     * Also sets a callback function to format field value as required
     *
     * @param string $field
     */
    public function setFieldTypePercent($field)
    {
        // for date fields the following callback must be set
        $percentToPercent = function ($percent) {
            return $percent / 100;
        };

        $this->setCallback($field, $percentToPercent);
        $this->setFieldType($field, $this->_defaultFieldTypePercent);
    }

    /**
     * Set field to currency field format in excel output
     *
     * @param string $field
     */
    public function setFieldTypeCurrency($field)
    {
        $this->setFieldType($field, $this->_defaultFieldTypeCurrency);
    }

    /**
     * Get Pre-Calculate Formulas flag
     *     If this is true (the default), then the writer will recalculate all formulae in a workbook when saving,
     *        so that the pre-calculated values are immediately available to MS Excel or other office spreadsheet
     *        viewer when opening the file
     *     If false, then formulae are not calculated on save. This is faster for saving in PhpSpreadsheet, but slower
     *        when opening the resulting file in MS Excel, because Excel has to recalculate the formulae itself
     *
     * @return boolean
     */
    public function getPreCalculateFormulas()
    {
        return $this->_preCalculateFormulas;
    }

    /**
     * Set Pre-Calculate Formulas
     *		Set to true (the default) to advise the Writer to calculate all formulae on save
     *		Set to false to prevent precalculation of formulae on save.
     *
     * @param bool $pValue	Pre-Calculate Formulas?
     * @return ZfExtended_Models_Entity_ExcelExport
     */
    public function setPreCalculateFormulas($pValue = true)
    {
        $this->_preCalculateFormulas = (bool) $pValue;

        return $this;
    }

    /**
     * Redirect output to a client's web browser (Excel)
     */
    public function sendDownload()
    {
        $fileName = $this->getProperty('filename') . date('-Y-m-d') . '.xlsx';

        // XLSX Excel 2010 output
        $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->spreadsheet, 'Xlsx');

        $objWriter->setPreCalculateFormulas($this->getPreCalculateFormulas());

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
    public function saveToDisc(string $path)
    {
        // XLSX Excel 2010 output
        $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->spreadsheet, 'Xlsx');
        $objWriter->setPreCalculateFormulas($this->getPreCalculateFormulas());
        $objWriter->save($path);
    }

    public function getSpreadsheet()
    {
        return $this->spreadsheet;
    }

    public function getAllWorksheets()
    {
        return $this->spreadsheet->getAllSheets();
    }

    /**
     * Column index from string.
     *
     * @param string $pString eg 'A'
     *
     * @return int Column index (A = 1)
     */
    public function columnIndexFromString($pString)
    {
        return Coordinate::columnIndexFromString($pString);
    }

    /**
     * Add a new worksheet to the excel-spreadsheet
     */
    public function addWorksheet(string $sheetName, int $index): void
    {
        $tempWorksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($this->spreadsheet, $sheetName);
        $tempWorksheet->getDefaultColumnDimension()->setAutoSize(true); // does not work propper in Libre-Office. With Microsoft-Office everything is OK.
        $this->spreadsheet->addSheet($tempWorksheet, $index);
    }

    /**
     * Returns the worksheet of the given name
     * @param string $sheetName
     * @return PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
     */
    public function getWorksheetByName($sheetName)
    {
        $this->spreadsheet->setActiveSheetIndexByName($sheetName);

        return $this->spreadsheet->getActiveSheet();
    }

    /**
     * Remove the worksheet of the given index
     * @param integer $index
     */
    public function removeWorksheetByIndex(int $index)
    {
        $this->spreadsheet->removeSheetByIndex($index);
    }

    /***
     * Adjust the column size of each worksheet in given spredsheet
     * @param PhpOffice\PhpSpreadsheet\Spreadsheet $sp
     */
    public function autosizeColumns(PhpOffice\PhpSpreadsheet\Spreadsheet $sp)
    {
        foreach ($sp->getWorksheetIterator() as $worksheet) {
            $sp->setActiveSheetIndex($sp->getIndex($worksheet));

            $sheet = $sp->getActiveSheet();
            $cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);

            /** @var PHPExcel_Cell $cell */
            foreach ($cellIterator as $cell) {
                $sheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
            }
        }
    }
}
