<?php 

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class nibemi{

    private $numberId= null;

    private $options = [
        'body'    => "",
        'headers' => [
            "Content-Type" => "application/json"
        ]
    ];

    private $cabecera = [
        'headers' => [ 'Content-Type' => 'application/json', "Authorization" => ""],
        'base_uri' => "",
        'timeout'  => 0,
    ];

    function __construct(){
        $this->numberID = "105897748968906";
        $this->accessToken = "";
        $this->cabecera['base_uri'] = "https://api.nibemi.ec";
        // $this->cabecera['headers']['Authorization'] = "Bearer ".$this->accessToken;
        $this->cabecera['headers']['Authorization'] = $this->numberID;
    }

    function sendTemplate($request){
        $this->options = ['body' => json_encode($request)];

        $client = new Client($this->cabecera);
        
        $response = $client->post('/v13.0/'.$this->numberID.'/messages', $this->options);

        return json_decode($response->getBody());  
    }

    function enviarPlantilla($request, $plantilla){
        $this->options = ['body' => json_encode($request)];

        $client = new Client($this->cabecera);
        
        $response = $client->post('/api/v1/whatsapp/plantilla/'.$plantilla, $this->options);

        return json_decode($response->getBody());  
    }

    function obtenerLink($request){
        $this->options = ['body' => json_encode($request)];

        $client = new Client($this->cabecera);
        
        $response = $client->post('/api/v1/placetoplay/pago', $this->options);

        return json_decode($response->getBody());  
    }

    
    function obtenerPago($requestId){
        $client = new Client($this->cabecera);
        
        $response = $client->get('/api/v1/placetoplay/validacion/'.$requestId);

        return json_decode($response->getBody());  
    }
}

class envioWhatsapp{


    function nuevoPedido($campos){
        $nibemi = new nibemi();

        $listaNotificacion = array(
            "0958978745", // YO
            "0983178027", // ALEJA
            "0984278738", // FER
            "0992274995", // TATY SANGO
            "0989264157", // LATIFA
            "0987054486", // CATA
            "0985337608" // MELANI
        );

        if (count($listaNotificacion) > 0){
            foreach ($listaNotificacion as $telefono) {

                $envio = $nibemi->enviarPlantilla(array(
                    "phone" => $telefono,
                    "body" => [
                        array(
                            "type" => "text",
                            "text" => $campos['nombre']
                        ),
                        array(
                            "type" => "text",
                            "text" => $campos['forma_pago']
                        ),
                        array(
                            "type" => "text",
                            "text" => $campos['fecha_hora']
                        ),
                    ]
                ), "nuevo_pedido_store");

            }
        } 

        return true;
    }

    function nuevaCita($campos){
        $nibemi = new nibemi();

        $respuesta['whatsapp'] = $nibemi->enviarPlantilla(array(
            "phone" => $campos['celular'],
            "header" => [array(
                "type" => "text",
                "text" => "Kosmetic Studio" 
            )],
            "body" => [
                array(
                    "type" => "text",
                    "text" => $campos['fecha']
                ),
                array(
                    "type" => "text",
                    "text" => $campos['hora']
                ),
            ]
        ), "nueva_cita");


        // NOTIFICACION USUARIOS

        $listaNotificacion = array(
            "0958978745", // YO
            "0983178027", // ALEJA
            "0984278738", // FER
            "0992274995", // TATY SANGO
            "0989264157", // LATIFA
            "0987054486", // CATA
            "0985337608" // MELANI
        );

        if (count($listaNotificacion) > 0){
            foreach ($listaNotificacion as $telefono) {

                $ENVIO = $nibemi->enviarPlantilla(array(
                    "phone" => $telefono,
                    "body" => [
                        array(
                            "type" => "text",
                            "text" => $campos['fecha']
                        ),
                        array(
                            "type" => "text",
                            "text" => $campos['hora']
                        ),
                        array(
                            "type" => "text",
                            "text" => $campos['nombres']
                        ),
                    ]
                ), "nuevo_agendamiento_cita");

            }
        } 


        

    }

}