<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 10000");
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header('Content-Type: application/json; charset=utf-8');

// Codigo para CRM anterior y realizar conexiones con la base de datos
// y obtener/grabar informacion

require("lib/mysql.php");
require("lib/functions.php");

$mve = new MVE();
$respuesta['estado'] = false;

$modulo = 0;
if (isset($_GET['modulo'])){
    $modulo = $_GET['modulo'];

    if ($mve->Valida_Conexion()){
                
        $mysql = new Database('crm_marketing');

        $campos = null;        
        $inputJSON = file_get_contents('php://input');
        $campos = json_decode($inputJSON, TRUE); // convert JSON into array

        switch ($modulo) {
            case "ciudades":
                $consulta = $mysql->Consulta("SELECT * FROM mve_geo_ciudades WHERE estado=1 ORDER BY nombre ASC");
                $respuesta['consulta'] = $consulta;
                break;
            case 'clientes':
                $buscador = "";
                if (isset($_GET['buscador'])){
                    $buscador = $_GET['buscador'];
                }
                
                $consulta = $mysql->Consulta("SELECT * FROM mve_clientes WHERE ((cli_codigo=".$buscador.") OR (cli_cedula LIKE '%".$buscador."%') OR (cli_nombre LIKE '%".$buscador."%') OR (cli_apellido LIKE '%".$buscador."%')) ORDER BY cli_codigo DESC");

                $respuesta['consulta'] = $consulta;                
                break;
            
            default:
                # code...
                break;
        }
        
        $respuesta['estado'] = true;
    }else{
        $respuesta['error'] = "No tiene acceso a la informacion.";
    }
}else{
    $respuesta['error'] = "No se ha especificado el endpoint";
}

echo json_encode($respuesta);