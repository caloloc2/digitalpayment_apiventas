<?php

class Database{

    private $db;
    private $path_credentials = __DIR__."/credentials.txt";

    function __construct($database, $port="63343"){
        try {
            if (file_exists($this->path_credentials)){
                $credentials = file_get_contents($this->path_credentials);

                $campos = explode(" ", $credentials);
    
                if (isset($campos[2])){
                    $hostname = trim($campos[0]);
                    $username = trim($campos[1]);
                    $password = trim($campos[2]);
                    $database = $database;

                    $this->db = new PDO("mysql:dbname={$database};host={$hostname};port:$port;", $username, $password);
                    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $this->db->exec("set names utf8");
                }else{
                    return "Las credenciales son incorrectas";
                }
            }else{
                return "No existen credenciales";
            }                    
        }catch(PDOException $e){
            return $e->getMessage();
        }        
	}

    function _destructor(){
        $this->db = null;
    }

    function Consulta($sql){
        try{
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $this->db->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rows;
        }catch(PDOException $e){
            return array("error" => $e->getMessage());
        }
    }

    function Consulta_Unico($sql){
        try{
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $this->db->query($sql);
            $rows = $stmt->fetch(PDO::FETCH_ASSOC);
            return $rows;
        }catch(PDOException $e){
            return array("error" => $e->getMessage());
        }
    }

    function Ejecutar($sql, $data){
        try{
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $this->db->prepare($sql);
            $resp = $stmt->execute($data);
            return $resp;
        }catch(PDOException $e){
            return array("error" => $e->getMessage());
        }
    }

    function Ingreso($sql, $data){
        try{
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $this->db->prepare($sql);
            $resp = $stmt->execute($data);
            $id_create = $this->db->lastInsertId();
            return $id_create;
        }catch(PDOException $e){
            return array("error" => $e->getMessage());
        }
    }

    function Modificar($sql, $data){
        try{
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $this->db->prepare($sql);
            $resp = $stmt->execute($data);            
            return $resp;
        }catch(PDOException $e){
            return array("error" => $e->getMessage());
        }
    }
}