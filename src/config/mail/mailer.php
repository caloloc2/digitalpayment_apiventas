<?php 

// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'Exception.php';
require 'PHPMailer.php';
require 'SMTP.php';
require 'POP3.php';

class Email{
    private $mail;
    private $credentials;
    private $path = __DIR__."/templates";

    function __construct($correo, $password, $nombre, $empresa = "Marketing VIP") {
        // $this->credentials = (object) [            
        //     'username' => 'info@vtgsa.com',
        //     'password' => 'Nueva2021**',
        //     'title' => 'VTG SA'
        // ];

        $this->credentials = (object) [            
            'username' => $correo,
            'password' => $password,
            'title' => $empresa.' | '.$nombre
        ];

        // 'username' => 'marketingvip@mvevip.com',
        //     'password' => 'jkLo125*',  // usada en marketing ventas =>  Logistica2021*-
        //     'title' => 'ALEJO MARKETING'

        $this->mail = new PHPMailer(true);  // Passing `true` enables exceptions
        $this->mail->SMTPDebug = 0;
        $this->mail->isSMTP();
        $this->mail->Host = 'email-smtp.us-west-2.amazonaws.com'; //'smtp.office365.com'; //'smtp.gthis->mail.com';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = "AKIAZ5NV5JMLBZROWXM2"; //$this->credentials->username;
        $this->mail->Password = 'BPlYVMDTh11H8+oYo365ekIgnxG4du0MbA1ZQjl5G/tF'; //$this->credentials->password;
        $this->mail->SMTPSecure = 'tls';
        $this->mail->Port = 587;
        $this->mail->CharSet = 'UTF-8';
        $this->mail->SMTPOptions = array(
            'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
            )
        );
        $this->mail->setFrom($this->credentials->username, $this->credentials->title);
        $this->mail->addBCC($this->credentials->username);
    }    

    function Contenido($data, $modulo){
        $contenido = null;

        switch ($modulo) {
            case 'nueva_reserva':
                $contenido = file_get_contents($this->path.'/nueva_reserva/index.html');                
                $contenido = str_replace('%documento%', $data['cliente']['documento'], $contenido);
                $contenido = str_replace('%nombres_apellidos%', $data['cliente']['nombres_apellidos'], $contenido);
                $contenido = str_replace('%correo%', $data['cliente']['correo'], $contenido);
                $contenido = str_replace('%telefono%', $data['cliente']['telefono'], $contenido);
                $contenido = str_replace('%asesor%', $data['cliente']['asesor'], $contenido);

                $contenido = str_replace('%adultos%', $data['pasajeros']['adultos'], $contenido);
                $contenido = str_replace('%ninos1%', $data['pasajeros']['ninos1'], $contenido);
                $contenido = str_replace('%ninos2%', $data['pasajeros']['ninos2'], $contenido);
                $contenido = str_replace('%discapacitados%', $data['pasajeros']['discapacitados'], $contenido);

                $contenido = str_replace('%pu_destino%', $data['transfers']['pu_destino'], $contenido);
                $contenido = str_replace('%pu_fecha%', $data['transfers']['pu_fecha'], $contenido);
                $contenido = str_replace('%pu_hora%', $data['transfers']['pu_hora'], $contenido);
                $contenido = str_replace('%pu_vuelo%', $data['transfers']['pu_vuelo'], $contenido);

                $contenido = str_replace('%do_destino%', $data['transfers']['do_destino'], $contenido);
                $contenido = str_replace('%do_fecha%', $data['transfers']['do_fecha'], $contenido);
                $contenido = str_replace('%do_hora%', $data['transfers']['do_hora'], $contenido);
                $contenido = str_replace('%do_vuelo%', $data['transfers']['do_vuelo'], $contenido);

                $contenido = str_replace('%lista_destinos%', $data['destinos'], $contenido);
                $contenido = str_replace('%listado_actividades%', $data['actividades'], $contenido);
                break;
            case "tasa_hotelera":
                $contenido = file_get_contents($this->path.'/tasa_hotelera/index.html');                
                $contenido = str_replace('%detalle_impuestos%', $data['impuestos'], $contenido);
                $contenido = str_replace('%observaciones_impuestos%', $data['observaciones_impuestos'], $contenido);
                $contenido = str_replace('%nombres_apellidos%', $data['cliente']['nombres_apellidos'], $contenido);                
                break;
            case "anulacion_reserva":
                $contenido = file_get_contents($this->path.'/anulacion_reserva/index.html');                                
                $contenido = str_replace('%nombres_apellidos%', $data['cliente']['nombres_apellidos'], $contenido);                
                $contenido = str_replace('%motivo_cancelacion%', $data['motivo_cancelacion'], $contenido);                
                break;
            case "contabilidad":
                $contenido = file_get_contents($this->path.'/contabilidad/index.html');
                break;
            case "encuesta":
                $contenido = file_get_contents($this->path.'/encuesta/index.html');
                $contenido = str_replace('%pickup_fecha%', $data['pickup_fecha'], $contenido);         
                $contenido = str_replace('%dropoff_fecha%', $data['dropoff_fecha'], $contenido);
                $contenido = str_replace('%destino%', $data['destino'], $contenido);         
                $contenido = str_replace('%hash%', $data['hash'], $contenido);
                $contenido = str_replace('%destinatario%', $data['destinatario'], $contenido);
                break;
        }

        return $contenido;
    }    

    function Enviar_Correo($correo, $nombres, $asunto, $contenido, $adjuntos, $copias){
        try{
            // Si existen archivos adjuntos, se incluyen
            if (is_array($adjuntos)){
                if (count($adjuntos) > 0){
                    foreach ($adjuntos as $linea_adjunto) {
                        if (file_exists($linea_adjunto['link'])){
                            $this->mail->addAttachment($linea_adjunto['link']);
                        }                    
                    }
                }
            }

            // Agrega correo electronico principal del destinatario
            $separa_correos = explode(";", $correo);
            if (is_array($separa_correos)){
                if (count($separa_correos) > 0){
                    foreach ($separa_correos as $linea_correo) {
                        $this->mail->addAddress(trim($linea_correo), $nombres);
                    }
                }
            }
            
             // Si existen correos para copias, se adjuntan
             if (is_array($copias)){
                if (count($copias) > 0){
                    foreach ($copias as $linea_copia) {
                        $this->mail->addCC($linea_copia['correo']);
                    }
                }
            }

            $this->mail->isHTML(true);
            $this->mail->Subject = $asunto;
            $this->mail->MsgHTML($contenido);

            if ($this->mail->send()){
                return array('estado' => true, 'mensaje' => "Mensaje enviado correctamente");
            }else{
                return array('estado' => false, 'mensaje' => $this->mail->ErrorInfo);
            }

        }catch (phpmailerException $e) {            
            return array('estado' => false, 'mensaje' => $e->errorMessage());
        } catch (Exception $e) {            
            return array('estado' => false, 'mensaje' => $e->getMessage());
        }
    }

    function Enviar_Email($data, $copias, $adjuntos, $reply, $modulo){
        try{
            $contenido = $this->Contenido($data, $modulo);

            // Agrega correo electronico principal del destinatario            
            $this->mail->addAddress($data['correo'], $data['destinatario']);
            $this->mail->addAddress("fsarzosa@mvevip.com", "Fernanda Sarzosa");
            // Si existen correos para copias, se adjuntan
            if (is_array($copias)){
                if (count($copias) > 0){
                    foreach ($copias as $linea_copia) {
                        $this->mail->addCC($linea_copia['correo']);
                    }
                }
            }
            // Si existen archivos adjuntos, se incluyen
            if (is_array($adjuntos)){
                if (count($adjuntos) > 0){
                    foreach ($adjuntos as $linea_adjunto) {
                        if (file_exists($linea_adjunto['link'])){
                            $this->mail->addAttachment($linea_adjunto['link']);
                        }                    
                    }
                }
            }

            $this->mail->isHTML(true);
            $this->mail->Subject = $data['asunto'];
            $this->mail->MsgHTML($contenido);
            if (isset($reply['correo'])){
                $this->mail->addReplyTo($reply['correo'], $reply['nombre']);
            }            

            if ($this->mail->send()){
                return array('estado' => true, 'mensaje' => "Mensaje enviado correctamente");
            }else{
                return array('estado' => false, 'mensaje' => $this->mail->ErrorInfo);
            }

        }catch (phpmailerException $e) {            
            return array('estado' => false, 'mensaje' => $e->errorMessage());
        } catch (Exception $e) {            
            return array('estado' => false, 'mensaje' => $e->getMessage());
        }
    }

    function Enviar_Tasas($data, $copias, $adjuntos, $reply, $modulo){
        try{
            $contenido = $this->Contenido($data, $modulo);

            // Agrega correo electronico principal del destinatario            
            $this->mail->addAddress($data['correo'], $data['destinatario']);
            $this->mail->addAddress("fsarzosa@mvevip.com", "Fernanda Sarzosa");
            // Si existen correos para copias, se adjuntan
            if (is_array($copias)){
                if (count($copias) > 0){
                    foreach ($copias as $linea_copia) {
                        $this->mail->addCC($linea_copia['correo']);
                    }
                }
            }
            // Si existen archivos adjuntos, se incluyen
            if (is_array($adjuntos)){
                if (count($adjuntos) > 0){
                    foreach ($adjuntos as $linea_adjunto) {
                        if (file_exists($linea_adjunto['link'])){
                            $this->mail->addAttachment($linea_adjunto['link']);
                        }                    
                    }
                }
            }

            $this->mail->isHTML(true);
            $this->mail->Subject = $data['asunto'];
            $this->mail->MsgHTML($contenido);
            if (isset($reply['correo'])){
                $this->mail->addReplyTo($reply['correo'], $reply['nombre']);
            }            

            if ($this->mail->send()){
                return array('estado' => true, 'mensaje' => "Mensaje enviado correctamente");
            }else{
                return array('estado' => false, 'mensaje' => $this->mail->ErrorInfo);
            }

        }catch (phpmailerException $e) {            
            return array('estado' => false, 'mensaje' => $e->errorMessage());
        } catch (Exception $e) {            
            return array('estado' => false, 'mensaje' => $e->getMessage());
        }
    }

    function Enviar_Contabilidad($data, $copias, $adjuntos, $modulo){
        try{
            $contenido = $this->Contenido($data, $modulo);

            // Agrega correo electronico principal del destinatario            
            $this->mail->addAddress("gmena@mvevip.com", "Geovany Mena");
            $this->mail->addAddress("mchiriboga@mvevip.com", "Macarena Chiriboga");
            $this->mail->addAddress("jsanchez@mvevip.com", "Juan Carlos Sanchez");
            $this->mail->addAddress("vsanchez@mvevip.com", "Fernanda Sanchez");
            $this->mail->addAddress("amorales@mvevip.com", "Alejandro Morales");

            // Si existen correos para copias, se adjuntan
            if (is_array($copias)){
                if (count($copias) > 0){
                    foreach ($copias as $linea_copia) {
                        $this->mail->addCC($linea_copia['correo']);
                    }
                }
            }
            // Si existen archivos adjuntos, se incluyen
            if (is_array($adjuntos)){
                if (count($adjuntos) > 0){
                    foreach ($adjuntos as $linea_adjunto) {
                        if (file_exists($linea_adjunto['link'])){
                            $this->mail->addAttachment($linea_adjunto['link']);
                        }                    
                    }
                }
            }

            $this->mail->isHTML(true);
            $this->mail->Subject = $data['asunto'];
            $this->mail->MsgHTML($contenido);


            if ($this->mail->send()){
                return array('estado' => true, 'mensaje' => "Mensaje enviado correctamente");
            }else{
                return array('estado' => false, 'mensaje' => $this->mail->ErrorInfo);
            }

        }catch (phpmailerException $e) {            
            return array('estado' => false, 'mensaje' => $e->errorMessage());
        } catch (Exception $e) {            
            return array('estado' => false, 'mensaje' => $e->getMessage());
        }
    }

    function Enviar_Anulacion($data, $copias, $adjuntos, $reply, $modulo){
        try{
            $contenido = $this->Contenido($data, $modulo);

            // Agrega correo electronico principal del destinatario            
            $this->mail->addAddress($data['correo'], $data['destinatario']);
            $this->mail->addAddress("fsarzosa@mvevip.com", "Fernanda Sarzosa");
            // Si existen correos para copias, se adjuntan
            if (is_array($copias)){
                if (count($copias) > 0){
                    foreach ($copias as $linea_copia) {
                        $this->mail->addCC($linea_copia['correo']);
                    }
                }
            }
            // Si existen archivos adjuntos, se incluyen
            if (is_array($adjuntos)){
                if (count($adjuntos) > 0){
                    foreach ($adjuntos as $linea_adjunto) {
                        if (file_exists($linea_adjunto['link'])){
                            $this->mail->addAttachment($linea_adjunto['link']);
                        }                    
                    }
                }
            }

            $this->mail->isHTML(true);
            $this->mail->Subject = $data['asunto'];
            $this->mail->MsgHTML($contenido);
            if (isset($reply['correo'])){
                $this->mail->addReplyTo($reply['correo'], $reply['nombre']);
            }            

            if ($this->mail->send()){
                return array('estado' => true, 'mensaje' => "Mensaje enviado correctamente");
            }else{
                return array('estado' => false, 'mensaje' => $this->mail->ErrorInfo);
            }

        }catch (phpmailerException $e) {            
            return array('estado' => false, 'mensaje' => $e->errorMessage());
        } catch (Exception $e) {            
            return array('estado' => false, 'mensaje' => $e->getMessage());
        }
    }



    function Enviar_Encuesta($data, $modulo){
        try{
            $contenido = $this->Contenido($data, $modulo);

            // Agrega correo electronico principal del destinatario            
            $this->mail->addAddress($data['correo'], $data['destinatario']);                       

            $this->mail->isHTML(true);
            $this->mail->Subject = $data['asunto'];
            $this->mail->MsgHTML($contenido);                     

            if ($this->mail->send()){
                return array('estado' => true, 'mensaje' => "Mensaje enviado correctamente");
            }else{
                return array('estado' => false, 'mensaje' => $this->mail->ErrorInfo);
            }

        }catch (phpmailerException $e) {            
            return array('estado' => false, 'mensaje' => $e->errorMessage());
        } catch (Exception $e) {            
            return array('estado' => false, 'mensaje' => $e->getMessage());
        }
    }
}