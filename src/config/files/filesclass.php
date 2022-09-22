<?php 

class Files{   

    function __construct($folder) {
        $this->path = __DIR__."/bucket";
        $this->folder = $folder;
    }

    public function saveFile($archivo, $nombre){
        $carpeta = $this->path."/".$this->folder;

        if (!file_exists($carpeta)){
            // Si no existe la carpeta la crea
            mkdir($carpeta, 0777, true);
        }        
        $destino = $carpeta.'/'.$nombre;
        move_uploaded_file($archivo, $destino);

        return $destino;
    }

    public function getFolder(){
        return $this->carpeta;
    }

    public function getFile($nombre){
        $carpeta = $this->path."/".$this->folder;
        $destino = $carpeta.'/'.$nombre;
        return file_get_contents($destino);
    }

    public function getPathFile($nombre){
        $carpeta = $this->path."/".$this->folder;
        $destino = $carpeta.'/'.$nombre;
        return $destino;
    }

    public function removeFile($nombre){
        $carpeta = $this->path."/".$this->folder;
        $destino = $carpeta.'/'.$nombre;
        unlink($destino);
    }
}