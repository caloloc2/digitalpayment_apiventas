<?php 

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class whatsappNibemi{

    public $options = [
        'body'    => "",
        'headers' => [
            "Content-Type" => "application/json",
        ]
    ];

    public $cabecera = [
        'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => ""],
        // 'base_uri' => "https://api.nibemi.ec",
        'base_uri' => "https://canales.mvevip.com",
        'timeout'  => 0,
    ];

    function __construct($phoneCode){
        $this->cabecera['headers']['Authorization'] = $phoneCode;
    }

    function envioPlantilla($nombrePlantilla, $campos){
        $retorno = array("estado" => false);

        try{

            $this->options = ['body' => json_encode($campos)];
            $client = new Client($this->cabecera);
            $endpoint = '/api/v1/whatsapp/plantilla/'.$nombrePlantilla;
            $response = $client->post($endpoint, $this->options);

            $getBody = json_decode($response->getBody(), true);
            $retorno['response'] = $getBody;

            if ((isset($getBody['estado'])) && ($getBody['estado'])){
                $retorno['estado'] = true;
            }
            
        }catch(Exception $e){
            $retorno['error'] = $e->getMessage();
        }

        return $retorno;
    }
 
    function envioContrato($campos){
        $retorno = array("estado" => false);

        try{

            $this->options = ['body' => json_encode($campos)];
            $client = new Client($this->cabecera);
            $endpoint = '/api/v1/whatsapp/envioContrato';
            $response = $client->post($endpoint, $this->options);

            $getBody = json_decode($response->getBody(), true);
            $retorno['response'] = $getBody;

            if ((isset($getBody['estado'])) && ($getBody['estado'])){
                $retorno['estado'] = true;
            }
            
        }catch(Exception $e){
            $retorno['error'] = $e->getMessage();
        }

        return $retorno;
    }

}