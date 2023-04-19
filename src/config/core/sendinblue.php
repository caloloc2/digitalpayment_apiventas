<?php 

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class sendinblue{ 

    private $options = [
        'body'    => "",
        'headers' => [
            "Content-Type" => "application/json",
            "api-key" => null
        ]
    ];

    private $cabecera = [
        'headers' => [ 'Content-Type' => 'application/json', "api-key" => null],
        'base_uri' => "",
        'timeout'  => 0,
    ];

    function __construct(){ 
        $mysql = new Database("vtgsa_ventas");

        $consulta = $mysql->Consulta_Unico("SELECT * FROM configuracion_sendinblue ORDER BY id_sendinblue DESC LIMIT 1");
        
        if (isset($consulta['id_sendinblue'])){
            $this->cabecera['headers']['api-key'] = $consulta['api-key'];
            $this->cabecera['base_uri'] = "https://api.sendinblue.com";
        }
    }

    function getData(){
        return $this->cabecera;
    }

    function envioMail($body){
        $this->options = ['body' => json_encode($body)]; 

        $client = new Client($this->cabecera);
        
        $response = $client->post('/v3/smtp/email', $this->options);

        return json_decode($response->getBody(), true);
    }
}