<?php 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class excel{
    private $spreadsheet = null;
    private $abecedario = "ABCDEFGHIJKLMOPQRSTUVWXYZ";
    private $folder = __DIR__."/../../../public/tmp/";

    function __construct(){
        $this->spreadsheet = new Spreadsheet();
    }

    function createExcel($titulo, $registros, $nombre = ""){

        $nombreFile = "";
        $nombreFileTemp = date("YmdHis").".xlsx";
        if (!empty($nombre)){
            $nombreFile = $nombre;
        }
        $nombreFile .= $nombreFileTemp;

        $row = 0;
        $columna = 0;

        $activeWorksheet = $this->spreadsheet->getActiveSheet();

        foreach ($titulo as $key => $value) {
            $tagColumna = $this->abecedario[$columna].($row+1);
            $activeWorksheet->setCellValue($tagColumna, $value);
            $columna += 1;
        } 

        $row = 1;
        foreach ($registros as $datos) {
            $columna = 0;            
            foreach ($datos as $key => $value) {
                $tagColumna = $this->abecedario[$columna].($row+1);
                $activeWorksheet->setCellValue($tagColumna, $value);
                $columna += 1;
            }
            $row += 1;
        }
        
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($this->folder.$nombreFile);

        return array(
            "file" => $this->folder.$nombreFile,
            "filename" => $nombreFile
        );
    }


    function readExcel(){
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($this->folder."base.xlsx");

        return $spreadsheet->getActiveSheet();
    }
}


