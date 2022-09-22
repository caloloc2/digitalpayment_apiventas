<?php 

class Authentication{

    function encrypt_decrypt($action, $string) {
        $output = false;
    
        $encrypt_method = "aes-256-cbc-hmac-sha256";
        $secret_key = '385402292Mica_02';
        $secret_iv = date("Ymd");
    
        // hash
        $key = hash('sha256', $secret_key);
        
        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
    
        if ( $action == 'encrypt' ) {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        } else if( $action == 'decrypt' ) {
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }
    
        return $output;
    }

    function codigo_aleatorio($longitud) {
        $key = '';
        $pattern = '1234567890';
        $max = strlen($pattern)-1;
        for($i=0;$i < $longitud;$i++) $key .= $pattern{mt_rand(0,$max)};
        return $key;
    }

    function Valida_Hash($hash){
        $retorno = array("estado" => false, "usuario" => []);

        $mysql = new Database('vtgsa_ventas');        
        $decrypt = $this->encrypt_decrypt('decrypt', $hash);

        $info = explode("|", $decrypt);
        if (isset($info[2])){            
            $verifica = $mysql->Consulta_Unico("SELECT id_cliente, tipo_documento, documento, apellidos, nombres, correo, celular, token FROM clientes WHERE ((correo='".$info[0]."') AND (identificador='".$info[1]."') AND (hash='".$hash."'))");
                       
            if (isset($verifica['id_cliente'])){
                $id_cliente = $verifica['id_cliente'];

                $retorno['usuario'] = $verifica;

                $retorno['estado'] = true;
            }else{
                $retorno['error'] = "Su token no corresponde a ningún cliente registrado";
            }                    
        }else{
            $retorno['error'] = "Su token no es correcto o se encuentra caducado.";
        }                
        return $retorno;
    }

    function Verifica_Comercio($id_comercio){
        $retorno = array("estado" => false, "agencia" => []);

        $mysql = new Database('vtgsa');        
        $decrypt = $this->encrypt_decrypt('decrypt', $id_comercio);        

        $info = explode("-", $decrypt);
        if (isset($info[2])){
            $verifica = $mysql->Consulta_Unico("SELECT * FROM agencias_credenciales WHERE (((username='".$info[1]."') OR (correo='".$info[1]."')) AND (password='".$info[2]."') AND (hash='".$id_comercio."'))");
                       
            if (isset($verifica['id_credential'])){
                $id_agencia = $verifica['id_agencia'];

                // Datos de la Agencia
                if ($id_agencia == 0){

                }else{
                    $agencia = $mysql->Consulta_Unico("SELECT * FROM agencias WHERE (id_agencia=".$id_agencia.")");    
                }                

                // Consulta de porcentajes
                $porcentajes = $mysql->Consulta("SELECT modulo, porcentaje FROM agencias_porcentajes WHERE (id_agencia=".$id_agencia.")");                            

                $retorno['agencia'] = array(
                    "id_agencia" => (int) $agencia['id_agencia'],
                    "id_comercio" => $agencia['id_comercio'],
                    "razon_social" => $agencia['razon_social'],
                    "documento" => $agencia['documento'],
                    "nombres" => $agencia['nombres'],
                    "direccion" => $agencia['direccion'],
                    "correo" => $agencia['correo'],
                    "telefono" => $agencia['telefono'],
                    "celular" => $agencia['celular'],
                    "credito" => (float) $agencia['credito'],
                    "numero_reserva" => (int) $agencia['numeracion_reserva'],
                    "fecha_alta" => $agencia['fecha_alta'],
                    "fecha_modificacion" => $agencia['fecha_modificacion'],
                    "estado" => (int) $agencia['estado'],
                    "porcentajes" => $porcentajes
                );

                $ultimo_acceso = $mysql->Consulta_Unico("SELECT fecha_hora, accion FROM auditorias WHERE id_usuario=".$verifica['id_credential']);

                $retorno['usuario'] = array(          
                    "id_credential" => $verifica['id_credential'],
                    "nombres" => $verifica['nombres'],
                    "apellidos" => $verifica['apellidos'],
                    "username" => $verifica['username'],
                    "ultimo_acceso" => array(
                        "fecha" => $ultimo_acceso['fecha_hora'],
                        "accion" => $ultimo_acceso['accion'],
                    )
                );

                $retorno['estado'] = true;
            }else{
                $retorno['error'] = "Su token no corresponde a ningún comercio registrado";
            }                    
        }else{
            $retorno['error'] = "Su token no es correcto o se encuentra caducado.";
        }                
        return $retorno;
    }

    function Valida_Sesion($hash){
        $retorno = array("estado" => false, "usuario" => []);

        $mysql = new Database('vtgsa_ventas');
        $decrypt = $this->encrypt_decrypt('decrypt', $hash);

        $info = explode("|", $decrypt);
        if (isset($info[0])){
            $verifica = $mysql->Consulta_Unico("SELECT id_usuario, id_usuario_crm, nombres, correo, estado FROM usuarios WHERE (id_usuario=".$info[0].") AND (hash='".$hash."')");

            if (isset($verifica['id_usuario'])){
                if ($verifica['estado'] == 0){

                    $retorno['usuario'] = array(
                        "id_usuario" => (int) $verifica['id_usuario'],
                        "id_usuario_crm" => (int) $verifica['id_usuario_crm'],
                        "nombres" => $verifica['nombres'],
                        "correo" => $verifica['correo']
                    );

                    $retorno['estado'] = true;
                }else{
                    $verifica['error'] = "Su usuario ha sido deshabilitado.";
                }

            }else{
                $retorno['error'] = "Su token no corresponde a ningún cliente registrado";
            }
        }else{
            $retorno['error'] = "Su token no es correcto o se encuentra caducado.";
        }
        return $retorno;
    }


    function Valida_Sesion_Cliente($hash){
        $retorno = array("estado" => false, "usuario" => []);

        $mysql = new Database('mvevip_crm');        
        $decrypt = $this->encrypt_decrypt('decrypt', $hash);

        $info = explode("|", $decrypt);
        if (isset($info[0])){            
            $verifica = $mysql->Consulta_Unico("SELECT * FROM clientes WHERE (id_cliente=".$info[0].") AND (hash='".$hash."')");
            
            if (isset($verifica['id_cliente'])){
                if ($verifica['estado'] == 0){
                    $nombres = strtolower(explode(" ", $verifica['nombres'])[0]);
                    $apellidos = strtolower(explode(" ", $verifica['apellidos'])[0]);
                    $siglas = ucfirst($nombres)." ".ucfirst($apellidos);

                    $hoy = date("Y-m-d H:i:s");
                    $ultimo_ingreso = $hoy;
                    if ((isset($verifica['ultimo_ingreso'])) && (!empty($verifica['ultimo_ingreso']))){
                        $ultimo_ingreso = $verifica['ultimo_ingreso'];
                    }

                    $retorno['cliente'] = array(
                        "id_cliente" => (int) $verifica['id_cliente'],
                        "apellidos" => $verifica['apellidos'],
                        "nombres" => $verifica['nombres'],
                        "correo" => $verifica['correo'],
                        "siglas" => $siglas,
                        "ultimo_ingreso" => $ultimo_ingreso
                    );
    
                    $retorno['estado'] = true;
                }else{
                    $verifica['error'] = "Su usuario ha sido deshabilitado.";
                }
                
            }else{
                $retorno['error'] = "Su token no corresponde a ningún cliente registrado";                
            }                    
        }else{
            $retorno['error'] = "Su token no es correcto o se encuentra caducado.";
        }                
        return $retorno;
    }
}