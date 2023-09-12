<?php 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class excel{
    private $spreadsheet = null;
    private $folder = __DIR__."/../../../public/tmp/";

    function __construct(){
        $this->spreadsheet = new Spreadsheet();
    }

    function createExcel(){
        $activeWorksheet = $this->spreadsheet->getActiveSheet();
        $activeWorksheet->setCellValue('A1', 'Hello World !');
        
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($this->folder.'hello world.xlsx');
    }


    function readExcel(){
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($this->folder."base.xlsx");

        return $spreadsheet->getActiveSheet();
    }
}


