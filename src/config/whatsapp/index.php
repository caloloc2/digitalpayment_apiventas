<?php 

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class whatsappFunctions{

    private $mysql = null;
    public $version = "v13.0";
    public $phone_number_id = "101389459273398"; // "109107125162022";
    private $token = "Bearer EAAKT6JBDfgEBADlJDKCpySQYZAZAgBr6djQu8MAUY15xWZCYvM1GSSZAYcNLEZB8OnErY4nmJrga4XnanSwgLJOiW4VqvRQKlQUvRyXBhviSoWG9GD8ZAemBJtp0zjTh8vqZAaU78vr901WmwLdxFh0xSrXRZCQeXzfVjdWWmiYm4JOigMDbOEJKMTc1geFaif89U3YhmHzYuZBX0UwoMhiAM";
    public $options = [
        'body'    => "",
        'headers' => [
            "Content-Type" => "application/json",
        ]
    ];

    public $cabecera = [
        'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => ""],
        'base_uri' => "https://graph.facebook.com",
        'timeout'  => 0,
    ];

    public $online = false;

    function __construct($ambiente = "test"){
        // Configuracion para API Whatsapp
        $this->mysql = new Database("whatsapp");

        // Obtiene las configuraciones y accesos de la cuenta
        $configuracion = $this->mysql->Consulta_Unico("SELECT * FROM configuracion WHERE (tag='".$ambiente."') ORDER BY id_config DESC LIMIT 1");

        if ($configuracion['id_config']){
            $this->version = $configuracion['version'];
            $this->phone_number_id = $configuracion['phone_number_id'];
            $this->token = "Bearer ".$configuracion['token'];
            $this->cabecera['headers']['Authorization'] = $this->token;
            $this->online = true;
        }
    }

    function newUser($campos){
        $retorno = array('estado' => false);

        try{
            if (((isset($campos['wa_id'])) && (!empty($campos['wa_id']))) && ((isset($campos['profile'])) && (!empty($campos['profile'])))){

                $wa_id = $campos['wa_id'];
                $profile = $campos['profile'];

                $name = $profile['name'];

                $verifica = $this->mysql->Consulta_Unico("SELECT * FROM users WHERE wa_id='".$wa_id."'");

                if (!isset($verifica['id_user'])){
                    $id_user = $this->mysql->Ingreso("INSERT INTO users (`wa_id`, `name`) VALUES (?,?)", array($wa_id, $name));

                    $retorno['id_user'] = $id_user;
                    $retorno['estado'] = true;
                }else{
                    $retorno['error'] = "Ya existe el ID de usuario.";
                }
            }else{
                $retorno['error'] = "Información incompleta.";
            }

            
        }catch(Exception $e){
            $retorno['error'] = $e->getMessage();
        }

        return $retorno;
    }
}

class whatsappAPI extends whatsappFunctions{

    function newTextMessage($recipient, $message){

        if ($this->online){
            $campos = array(
                "messaging_product" => "whatsapp",
                "preview_url" => true,
                "recipient_type" => "individual",
                "to" => '593'.substr($recipient, 1, strlen($recipient)),
                "type" => "text",
                "text" => array(
                    "body" => $message
                )
            );
            
            $this->options = ['body' => json_encode($campos)];

            $client = new Client($this->cabecera);

            $endpoint = '/'.$this->version.'/'.$this->phone_number_id.'/messages';
            
            $response = $client->post($endpoint, $this->options);

            return json_decode($response->getBody());
        }else{
            return array("error" => "No se pudo lograr la conexión con la API.");
        }
        
    }

    function newImageMessage($recipient, $message, $link){

        if ($this->online){
            $campos = array(
                "messaging_product" => "whatsapp",
                "preview_url" => true,
                "recipient_type" => "individual",
                "to" => '593'.substr($recipient, 1, strlen($recipient)),
                "type" => "image",
                "image" => array(
                    "caption" => $message,
                    "link" => $link
                )
            );
            
            $this->options = ['body' => json_encode($campos)];

            $client = new Client($this->cabecera);

            $endpoint = '/'.$this->version.'/'.$this->phone_number_id.'/messages';
            
            $response = $client->post($endpoint, $this->options);

            return json_decode($response->getBody());
        }else{
            return array("error" => "No se pudo lograr la conexión con la API.");
        }
        
    }

    function newDocumentMessage($recipient, $message, $link){

        if ($this->online){
            $campos = array(
                "messaging_product" => "whatsapp",
                "preview_url" => true,
                "recipient_type" => "individual",
                "to" => '593'.substr($recipient, 1, strlen($recipient)),
                "type" => "document",
                "document" => array(
                    "caption" => $message,
                    "link" => $link
                )
            );
            
            $this->options = ['body' => json_encode($campos)];

            $client = new Client($this->cabecera);

            $endpoint = '/'.$this->version.'/'.$this->phone_number_id.'/messages';
            
            $response = $client->post($endpoint, $this->options);

            return json_decode($response->getBody());
        }else{
            return array("error" => "No se pudo lograr la conexión con la API.");
        }
        
    }

    function newVideoMessage($recipient, $message, $link){

        if ($this->online){
            $campos = array(
                "messaging_product" => "whatsapp",
                "preview_url" => true,
                "recipient_type" => "individual",
                "to" => '593'.substr($recipient, 1, strlen($recipient)),
                "type" => "video",
                "video" => array(
                    "caption" => $message,
                    "link" => $link
                )
            );
            
            $this->options = ['body' => json_encode($campos)];

            $client = new Client($this->cabecera);

            $endpoint = '/'.$this->version.'/'.$this->phone_number_id.'/messages';
            
            $response = $client->post($endpoint, $this->options);

            return json_decode($response->getBody());
        }else{
            return array("error" => "No se pudo lograr la conexión con la API.");
        }
        
    }

    function newAudioMessage($recipient, $link){

        if ($this->online){
            $campos = array(
                "messaging_product" => "whatsapp",
                "preview_url" => true,
                "recipient_type" => "individual",
                "to" => '593'.substr($recipient, 1, strlen($recipient)),
                "type" => "audio",
                "audio" => array(
                    "link" => $link
                )
            );
            
            $this->options = ['body' => json_encode($campos)];

            $client = new Client($this->cabecera);

            $endpoint = '/'.$this->version.'/'.$this->phone_number_id.'/messages';
            
            $response = $client->post($endpoint, $this->options);

            return json_decode($response->getBody());
        }else{
            return array("error" => "No se pudo lograr la conexión con la API.");
        }
        
    }

    function newLocationMessage($recipient, $coordenadas, $nombre, $direccion){

        if ($this->online){
            $campos = array(
                "messaging_product" => "whatsapp",
                "preview_url" => true,
                "recipient_type" => "individual",
                "to" => '593'.substr($recipient, 1, strlen($recipient)),
                "type" => "location",
                "location" => array(
                    "latitude" => $coordenadas['latitud'],
                    "longitude" => $coordenadas['longitud'],
                    "name" => $nombre,
                    "address" => $direccion
                )
            );
            
            $this->options = ['body' => json_encode($campos)];

            $client = new Client($this->cabecera);

            $endpoint = '/'.$this->version.'/'.$this->phone_number_id.'/messages';
            
            $response = $client->post($endpoint, $this->options);

            return json_decode($response->getBody());
        }else{
            return array("error" => "No se pudo lograr la conexión con la API.");
        }
        
    }

    function newButtonMessage($recipient, $message, $buttons){

        if ($this->online){
            $campos = array(
                "messaging_product" => "whatsapp",
                "recipient_type" => "individual",
                "to" => '593'.substr($recipient, 1, strlen($recipient)),
                "type" => "interactive",
                "interactive" => array(
                    "type" => "button",
                    "body" => array(
                        "text" => $message
                    ),
                    "action" => array(
                        "buttons" => $buttons
                    )
                )
            );
            
            $this->options = ['body' => json_encode($campos)];

            $client = new Client($this->cabecera);

            $endpoint = '/'.$this->version.'/'.$this->phone_number_id.'/messages';
            
            $response = $client->post($endpoint, $this->options);

            return json_decode($response->getBody());
            // return array("cabecera" => $this->cabecera, "options" => $this->options);
            // return $campos;
        }else{
            return array("error" => "No se pudo lograr la conexión con la API.");
        }
        
    }
}