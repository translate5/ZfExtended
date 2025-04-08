<?php

//namespace WilsonGlasser\Spout\Writer\Common\Creator;

use WilsonGlasser\Spout\Common\Creator\HelperFactory;
use WilsonGlasser\Spout\Common\Exception\UnsupportedTypeException;
use WilsonGlasser\Spout\Common\Helper\GlobalFunctionsHelper;
use WilsonGlasser\Spout\Common\Type;
use WilsonGlasser\Spout\Writer\Common\Creator\InternalEntityFactory;
use WilsonGlasser\Spout\Writer\Common\Creator\Style\StyleBuilder;
use WilsonGlasser\Spout\Writer\CSV\Manager\OptionsManager as CSVOptionsManager;
use WilsonGlasser\Spout\Writer\CSV\Writer as CSVWriter;
use WilsonGlasser\Spout\Writer\ODS\Creator\HelperFactory as ODSHelperFactory;
use WilsonGlasser\Spout\Writer\ODS\Creator\ManagerFactory as ODSManagerFactory;
use WilsonGlasser\Spout\Writer\ODS\Manager\OptionsManager as ODSOptionsManager;
use WilsonGlasser\Spout\Writer\ODS\Writer as ODSWriter;
use WilsonGlasser\Spout\Writer\WriterInterface;
use WilsonGlasser\Spout\Writer\XLSX\Creator\HelperFactory as XLSXHelperFactory;
use ZfExtended_Models_Entity_Spout_ManagerFactory as XLSXManagerFactory;
use WilsonGlasser\Spout\Writer\XLSX\Manager\OptionsManager as XLSXOptionsManager;
use WilsonGlasser\Spout\Writer\XLSX\Writer as XLSXWriter;

/**
 * Class WriterFactory
 * This factory is used to create writers, based on the type of the file to be read.
 * It supports CSV, XLSX and ODS formats.
 */
class ZfExtended_Models_Entity_Spout_WriterFactory
{
    /**
     * This creates an instance of the appropriate writer, given the type of the file to be read
     *
     * @param  string $writerType Type of the writer to instantiate
     * @throws \WilsonGlasser\Spout\Common\Exception\UnsupportedTypeException
     * @return WriterInterface
     */
    public function create($writerType)
    {
        switch ($writerType) {
            case Type::CSV: return $this->getCSVWriter();
            case Type::XLSX: return $this->getXLSXWriter();
            case Type::ODS: return $this->getODSWriter();
            default:
                throw new UnsupportedTypeException('No writers supporting the given type: ' . $writerType);
        }
    }

    /**
     * @return CSVWriter
     */
    private function getCSVWriter()
    {
        $optionsManager = new CSVOptionsManager();
        $globalFunctionsHelper = new GlobalFunctionsHelper();

        $helperFactory = new HelperFactory();

        return new CSVWriter($optionsManager, $globalFunctionsHelper, $helperFactory);
    }

    /**
     * @return XLSXWriter
     */
    private function getXLSXWriter()
    {
        $styleBuilder = new StyleBuilder();
        $optionsManager = new XLSXOptionsManager($styleBuilder);
        $globalFunctionsHelper = new GlobalFunctionsHelper();

        $helperFactory = new XLSXHelperFactory();
        $managerFactory = new XLSXManagerFactory(new InternalEntityFactory(), $helperFactory);

        return new XLSXWriter($optionsManager, $globalFunctionsHelper, $helperFactory, $managerFactory);
    }

    /**
     * @return ODSWriter
     */
    private function getODSWriter()
    {
        $styleBuilder = new StyleBuilder();
        $optionsManager = new ODSOptionsManager($styleBuilder);
        $globalFunctionsHelper = new GlobalFunctionsHelper();

        $helperFactory = new ODSHelperFactory();
        $managerFactory = new ODSManagerFactory(new InternalEntityFactory(), $helperFactory);

        return new ODSWriter($optionsManager, $globalFunctionsHelper, $helperFactory, $managerFactory);
    }
}
