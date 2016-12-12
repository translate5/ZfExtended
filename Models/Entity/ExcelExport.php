<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

require_once('ZfExtended/ThirdParty/PHPExcel/PHPExcel.php');

class ZfExtended_Models_Entity_ExcelExport {
    
    /**
     * @var PHPExcel
     */
    private $PHPExcel = false;
    
    /**
     * Container to hold document properties like filename etc. (properties->name->value)
     * @var stdClass
     */
    private $properties = false;
    
    /**
     * Container to hold fields that should not be shown in the excel
     * @var stdClass
     */
    private $hiddenFields = false;
    
    /**
     * Container to hold lablenames (lablenames->label->translation)
     * @var stdClass
     */
    private $labels = false;
    
    /**
     * Container to hold callback functions for manipulating field-content (callbacks->label->$closureFunction)
     * @var stdClass
     */
    private $callbacks = array();
    
    /**
     * Container to hold fieldType-Definitions (like PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDDSLASH)
     * @var stdClass
     */
    private $fieldTypes = false;
    
    
    /**
     * Default format for date fields
     * @var string
     */
    private $_defaultFieldTypeDate = PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2;
    
    /**
     * Default format for percent fields
     * @var string
     */
    private $_defaultFieldTypePercent = '0.0%;[RED]-0.0%';
    
    /**
     * Default format for currency fields
     * @var string
     */
    private $_defaultFieldTypeCurrency = PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE;
    
    
    
    
    public function __construct() {
        $this->PHPExcel  = ZfExtended_Factory::get('PHPExcel');
        
        $this->properties = new stdClass();
        $this->properties->filename = 'ERP-Export';
        
        $this->hiddenFields = new stdClass();
        $this->labels = new stdClass();
        $this->callbacks = new stdClass();
        $this->fieldTypes = new stdClass();
    }
    
    /**
     * The callback is called before the file is sent for download and after all cells are initialized.
	 * In the callback we can do cell resizing,change its value etc...
     * @param array $data
     * @param Closure $callback
     */
    public function simpleArrayToExcel ($data, Closure $callback = null) {
        $this->PHPExcel->setActiveSheetIndex(0);
        $tempSheet = $this->PHPExcel->getActiveSheet();
        
        $rowCount = 1;
        foreach ($data as $row)
        {
            $colCount = 0;
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
                    $tempSheet->getStyleByColumnAndRow($colCount, $rowCount+1)->getNumberFormat()->setFormatCode($this->getFieldType($key));
                }
                
                // set fields-value
                $tempSheet->setCellValueByColumnAndRow($colCount, $rowCount+1, $this->getCallback($key, $value));
                
                $colCount ++;
            }
            $rowCount++;
        }
        
        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $this->PHPExcel->setActiveSheetIndex(0);
        
        if($callback!==null){
        	$callback($this->PHPExcel);
        }
        
        $this->sendDownload();
        
    }
    
    
    /**
     * Set some properties used to generate the excel
     * @param string $name
     * @param mixed $value
     */
    public function setProperty(string $name, $value) {
        $this->properties->$name = $value;
    }
    
    /**
     * Get property with name $name used to generate the excel
     * @param string $name
     * @return mixed
     */
    public function getProperty(string $name) {
        if (property_exists($this->properties, $name)) {
            return $this->properties->$name;
        }
        return false;
    }
    
    
    /**
     * Adds a field to the list of hidden fields
     * @param string $name
     */
    public function setHiddenField(string $name) {
        $this->hiddenFields->$name = true;
    }
    
    /**
     * Checks if field is hidden
     * @param string $name
     * @return boolean
     */
    public function isHiddenField(string $name) {
        if (property_exists($this->hiddenFields, $name)) {
            return true;
        }
        return false;
    }
    
    
    /**
     * Set label used in the excelsheet to transform key of data[key] into a speaking label (or used for translation)
     * @param string $name
     * @param string $label
     */
    public function setLabel(string $name, $label) {
        $this->labels->$name = $label;
    }
    
    /**
     * Get label of $name
     * @param string $name
     * @return string
     */
    public function getLabel(string $name) {
        if (property_exists($this->labels, $name)) {
            return $this->labels->$name;
        }
        return $name;
    }
    
    
    /**
     * Set callback-function used in the excelsheet to manipulate the $value for a certain $key of data[$key] = $value
     * @param string $name
     * @param $function as closure function variable
     */
    public function setCallback(string $name, $function) {
        $this->callbacks->$name = $function;
    }
    
    /**
     * Get manipulated value of field $name 
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function getCallback(string $name, $value) {
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
     * @param string $name
     * @param string $fieldtype
     */
    public function setFieldType(string $name, $fieldtype) {
        $this->fieldTypes->$name = $fieldtype;
    }
    
    /**
     * Get fieldtype of field $name
     * @param string $name
     * @return string
     */
    public function getFieldType(string $name) {
        if (property_exists($this->fieldTypes, $name)) {
            return $this->fieldTypes->$name;
        }
    }
    
    /**
     * Set field to date field format in excel output
     * Also sets a callback function to format field value as required
     * 
     * @param string $field
     */
    public function setFieldTypeDate($field) {
        // for date fields the following callback must be set
        $stringToDate = function($string) {
            $date = strtotime($string);
            $date = PHPExcel_Shared_Date::PHPToExcel($date,true);
            return $date;
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
    public function setFieldTypePercent($field) {
        // for date fields the following callback must be set
        $percentToPercent = function($percent) {
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
    public function setFieldTypeCurrency($field) {
        $this->setFieldType($field, $this->_defaultFieldTypeCurrency);
    }
    
    
    /**
     * Redirect output to a client's web browser (Excel)
     */
    public function sendDownload () {
        $fileName = $this->getProperty('filename').date('-Y-d-m');
        
        // XLS Excel5 output
        //$objWriter = PHPExcel_IOFactory::createWriter($this->PHPExcel, 'Excel5');
        //header('Content-Type: application/vnd.ms-excel');
        //header('Content-Disposition: attachment;filename="'.$fileName.'.xls"');
        
        // XLSX Excel2007 output
        $objWriter = PHPExcel_IOFactory::createWriter($this->PHPExcel, 'Excel2007');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$fileName.'.xlsx"');
        
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');
        
        // If you're serving to IE over SSL, then the following may be needed
        header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
        header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header ('Pragma: public'); // HTTP/1.0
        
        $objWriter->save('php://output');
        exit;
    }
    public function getPhpExcel(){
    	return $this->PHPExcel;
    }
}
