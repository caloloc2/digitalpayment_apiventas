<?php 

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../src/config/mysql/mysql.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class CURLRequest{

    private $apiKey = "eyJhbGciOiJIUzI1NiJ9.eyJqdGkiOiJERUNMQVJBQ0lPTkVTIiwiaWF0IjoxNjc3NTI5MDY3LCJzdWIiOiJERUNMQVJBVE9SSUEgUFJFU0NSSVBDSU9OIEhFUkVOQ0lBIiwiZXhwIjoxNjc3NTI5NjY3fQ.vlfyMlagsATI4aU4ptGV08s42tvJjyAGuLyEUW1RiS0";

    public function setRequest($tipo, $documento){
        $url = "";

        switch ($tipo) {
            case 'cedula':
                $url = "https://srienlinea.sri.gob.ec/sri-registro-civil-servicio-internet/rest/DatosRegistroCivil/obtenerPorNumeroIdentificacionConToken?numeroIdentificacion=".$documento;
                break;
            case 'ruc':
                $url = "https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/ConsolidadoContribuyente/obtenerPorNumerosRuc?&ruc=".$documento;
                break;
            case 'contribuyente':
                $url = "https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/ClasificacionMipyme/consultarPorNumeroRuc?numeroRuc=".$documento;
                break;
            case 'agente_retencion':
                $url = "https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/ConsolidadoContribuyente/esAgenteRetencion?numeroRuc=".$documento;
                break;
            case 'existeRuc':
                $url = "https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/ConsolidadoContribuyente/existePorNumeroRuc?numeroRuc=".$documento;
                break;
            case 'establecimientos':
                $url = "https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/Establecimiento/consultarPorNumeroRuc?numeroRuc=".$documento;
        }

        $headers = array(
            'Content-Type: application/json',
            'Authorization: '.$this->apiKey
        );
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $html = curl_exec($ch);
        $data = curl_exec($ch);
        curl_close($ch);
    
        return $data;
    }    
}

$mysql = new Database("vtgsa_ventas");

$banco = 28;
$identificador = "2023-02-09-1";

$consulta = $mysql->Consulta("SELECT * FROM notas_registros WHERE (banco=".$banco.") AND (identificador='".$identificador."') AND (ruc='') ORDER BY id_lista ASC");

if (is_array($consulta)){
    if (count($consulta) > 0){
        $request = new CURLRequest();

        foreach ($consulta as $contacto) {
            $id_lista = $contacto['id_lista'];
            $documento = $contacto['documento'];

            // saca informacion del ruc
            $infoRuc = json_decode($request->setRequest('ruc', $documento), true);
            $contribuyente = json_decode($request->setRequest('contribuyente', $documento), true);
            $establecimientos = json_decode($request->setRequest('establecimientos', $documento), true);

            $resultado = array(
                "ruc" => $infoRuc, 
                "establecimientos" => $establecimientos
            );

            $guardar = array(
                "ruc" => $infoRuc[0]['numeroRuc'],
                "cedula" => substr($infoRuc[0]['numeroRuc'], 0, 10),
                "razonSocial" => $infoRuc[0]['razonSocial'],
                "actividadContribuyente" => $infoRuc[0]['actividadEconomicaPrincipal'],
                "fechaInicioActividades" => $infoRuc[0]['informacionFechasContribuyente']['fechaInicioActividades'],  
                "representantesLegales" => $infoRuc['representantesLegales'][0],
                "establecimientos" => $establecimientos
            );

            $actualizar = $mysql->Modificar("UPDATE notas_registros SET fechaInicioActividades=? WHERE id_lista=?", array(
                $guardar['fechaInicioActividades'],
                $id_lista
            ));

            $actualizar = $mysql->Modificar("UPDATE notas_registros SET ruc=?, cedula=?, razonSocial=?, actividadContribuyente=?, fechaInicioActividades=?, docRepresentanteLegal=?, representanteLegal=? WHERE id_lista=?", array(
                $guardar['ruc'],
                $guardar['cedula'],
                $guardar['razonSocial'],
                $guardar['actividadContribuyente'],
                $guardar['fechaInicioActividades'],
                $guardar['representantesLegales']['identificacion'],
                $guardar['representantesLegales']['nombre'],
                $id_lista
            ));

            if (is_array($guardar['establecimientos'])){
                if (count($guardar['establecimientos']) > 0){
                    foreach ($guardar['establecimientos'] as $establecimiento) {
                        
                        $id_establecimiento = $mysql->Ingreso("INSERT INTO notas_registros_establecimientos (id_lista, nombreComercial, tipoEstablecimiento, direccionCompleta, estado, numeroEstablecimiento) VALUES (?,?,?,?,?,?)", array($id_lista, $establecimiento['nombreFantasiaComercial'], $establecimiento['tipoEstablecimiento'], $establecimiento['direccionCompleta'], $establecimiento['estado'], $establecimiento['numeroEstablecimiento']));

                    }
                }
            }
            
        
            print_r($guardar);
        }
    }
}