<?php 

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class Functions{

    function __construct(){

    }

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

    function Validar_Credenciales($hash){
        $response = array("estado" => false);

        $mysql = new Database('mvevip_crm');

        $separa = explode("-", $this->encrypt_decrypt("decrypt", $hash));
        
        $id_asesor = $separa[0];
        $descripcion_acceso = $separa[1];
        
        $confirma = $mysql->Consulta_Unico("SELECT * FROM asesores WHERE (id_asesor=".$id_asesor.") AND (hash='".$hash."')");

        if (isset($confirma['id_asesor'])){
            $response['asesor']['id_asesor'] = (int) $confirma['id_asesor'];
            $response['asesor']['asesor'] = $confirma['asesor'];
            $response['asesor']['correo'] = $confirma['correo'];
            $response['asesor']['password'] = $confirma['clave_correo'];
            $response['asesor']['acceso']['tipo'] = (int) $confirma['acceso'];
            $response['asesor']['acceso']['descripcion'] = $descripcion_acceso;

            $response['estado'] = true;
        }

        return $response;
    }

    function getRangeDates($fecha, $rango = "days", $number = 6){
        $listaRango = [];
    
        $startTime = strtotime(date("Y-m-d", strtotime($fecha."- ".$number." ".$rango)));
        $endTime = strtotime($fecha);
    
        $rangoDias = array();
        switch ($rango) {
            case 'days':
                $startTime = strtotime(date("Y-m-d", strtotime($fecha."- ".$number." ".$rango)));
                $period = new DatePeriod(
                    new DateTime(date("Y-m-d", $startTime)),
                    new DateInterval('P1D'),
                    new DateTime(date("Y-m-d", $endTime))
                );
                foreach ($period as $key => $value) {
                    array_push($listaRango, array(
                        "from" => $value->format("Y-m-d"),
                        "to" => $value->format("Y-m-d")
                    ));
                }
                // agrego el dia actual
                array_push($listaRango, array(
                    "from" => date("Y-m-d", $endTime),
                    "to" => date("Y-m-d", $endTime)
                ));
                break;
            case 'week':
                while ($startTime <= $endTime) {  
                    $rangoDias[] = date('W', $startTime);  // numero de semana
                    $startTime += strtotime('+1 week', 0); // suma una semana 
                }
    
                foreach ($rangoDias as $semana) {
                    $dto = new DateTime();
                    $dto->setISODate(date("Y"), $semana);
                    $inicio_semana = $dto->format('Y-m-d');
                    $dto->modify('+6 days');
                    $fin_semana = $dto->format('Y-m-d');
            
                    array_push($listaRango, array(
                        "from" => $inicio_semana,
                        "to" => $fin_semana
                    ));
                }
                break;
            case 'month':
                $startTime = strtotime(date("Y-m-d", strtotime($fecha."- ".$number." ".$rango)));
                $period = new DatePeriod(
                    new DateTime(date("Y-m-d", $startTime)),
                    new DateInterval('P1M'),
                    new DateTime(date("Y-m-d", $endTime))
                );
                foreach ($period as $key => $value) { 
                    array_push($listaRango, array(
                        "from" => $value->format("Y-m-01"),
                        "to" => $value->format("Y-m-t")
                    ));
                }
                // agrego el dia actual
                array_push($listaRango, array(
                    "from" => date("Y-m-01", $endTime),
                    "to" => date("Y-m-t", $endTime)
                ));
                break;
        }
     
        return $listaRango;
    }

    function Validar_Solo_Texto($campo){
        $retorno = false;
        
        $patron_texto = "/^[a-zA-ZáéíóúÁÉÍÓÚäëïöüÄËÏÖÜàèìòùÀÈÌÒÙ\s]+$/";
    
        if (preg_match($patron_texto, $campo) ){
            $retorno = true;
        }
    
        return $retorno;
    }   
    
    function Validar_Email($email){
        $retorno = false;
    
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $retorno = true;
        }
    
        return $retorno;
    }

    function Verificar_Mobile($celular){
        $retorno = false; 

        if ((strlen($celular)>=8) && (strlen($celular)<=30) && (ctype_alnum($celular))){
            $retorno = true;
        }

        return $retorno;
    }

    function number_random($length){
        $key = '';
        // $pattern = '1A2B3C4D5E6F7G8H9I0';
        // $max = strlen($pattern)-1;
        // for($i=0;$i < $length;$i++) $key .= $pattern{mt_rand(0,$max)};
        return $key;
    }

    function meses($mes){
        $retorno = "";
        switch ($mes) {
            case 1:
                $retorno = "Enero";
                break;
            case 2:
                $retorno = "Febrero";
                break;
            case 3:
                $retorno = "Marzo";
                break;
            case 4:
                $retorno = "Abril";
                break;
            case 5:
                $retorno = "Mayo";
                break;
            case 6:
                $retorno = "Junio";
                break;
            case 7:
                $retorno = "Julio";
                break;
            case 8:
                $retorno = "Agosto";
                break;
            case 9:
                $retorno = "Septiembre";
                break;
            case 10:
                $retorno = "Octubre";
                break;
            case 11:
                $retorno = "Noviembre";
                break;
            case 12:
                $retorno = "Diciembre";
                break;
        }
        return $retorno;
    }

     /// Obtiene informacion de la reserva segun el ticket
     function Informacion_Ticket_Reserva($mysql, $id_ticket){
        $retorno = array("estado" => false);

        $ticket = $mysql->Consulta_Unico("SELECT T.id_ticket, T.id_asesor, C.documento, C.nombres, C.apellidos, C.telefono, C.correo, A.asesor, A.correo AS correo_asesor FROM tickets T, clientes C, asesores A WHERE (T.id_ticket=".$id_ticket.") AND ((T.id_cliente=C.id_cliente) AND (T.id_asesor=A.id_asesor))");

        $data_cliente = $mysql->Consulta_Unico("SELECT * FROM tickets T, clientes C WHERE (T.id_ticket=".$id_ticket.") AND (T.id_cliente=C.id_cliente)");

        if (isset($ticket['id_ticket'])){
            $reserva = $mysql->Consulta_Unico("SELECT * FROM reservas WHERE id_ticket=".$id_ticket);

            if (isset($reserva['id_reserva'])){
                $path = __DIR__.'/../../../public/storage';

                // datos del cliente y asesor
                $cliente = array(
                    "documento" => $ticket['documento'],
                    "nombres_apellidos" => $ticket['apellidos']." ".$ticket['nombres'],
                    "telefono" => $ticket['telefono'],
                    "correo" => $ticket['correo'],
                    "email1" => $reserva['email1'],
                    "email2" => $reserva['email2'],
                    "asesor" => $ticket['asesor'],
                    "idclienteCRM" => $data_cliente['idclienteCRM'],
                    "correo_asesor" => $ticket['correo_asesor']
                );

                // datos de los pasajeros

                $pasajeros = array(
                    "adultos" => (int) $reserva['num_adultos'],
                    "ninos1" => (int) $reserva['num_ninos'],
                    "ninos2" => (int) $reserva['num_ninos2'],
                    "discapacitados" => (int) $reserva['num_discapacitados_3edad']
                );

                // datos del vuelo / transfer
                $pickup = $mysql->Consulta_Unico("SELECT nombre FROM destinos WHERE id_destino=".$reserva['pickup_destino']);
                $dropoff = $mysql->Consulta_Unico("SELECT nombre FROM destinos WHERE id_destino=".$reserva['dropoff_destino']);
                $transfers = array(
                    "pu_destino" => $pickup['nombre'],
                    "pu_fecha" => $reserva['pickup_fecha'],
                    "pu_hora" => $reserva['pickup_hora'],
                    "pu_vuelo" => $reserva['pickup_codvuelo'],
                    "do_destino" => $dropoff['nombre'],
                    "do_fecha" => $reserva['dropoff_fecha'],
                    "do_hora" => $reserva['dropoff_hora'],
                    "do_vuelo" => $reserva['dropoff_codvuelo']
                );

                // lista de destinos

                $destinos = $mysql->Consulta("SELECT * FROM reservas_destinos R, destinos D WHERE (R.id_reserva=".$reserva['id_reserva'].") AND (R.destino=D.id_destino)");
                $lista_destinos = '';
                $primer_destino = 0;
                $primer_destino_nombre = '';
                $destinos_data = [];
                if (is_array($destinos)){
                    if (count($destinos) > 0){
                        foreach ($destinos as $linea_destino) {
                            if ($primer_destino == 0){
                                $primer_destino = $linea_destino['destino'];
                                $primer_destino_nombre = $linea_destino['nombre'];
                            }
                            $lista_destinos .= '<div style="border-bottom: 1px solid rgba(0,0,0,0.2);">';
                            $lista_destinos .= '<p style="font-family: `Tahoma`; font-size: 13px; margin: 0; padding: 0.2em 0;">Destino: <span style="font-weight: bolder; color: #000D47;">'.$linea_destino['nombre'].'</span></p>';
                            $lista_destinos .= '<p style="font-family: `Tahoma`; font-size: 13px; margin: 0; padding: 0.2em 0;">Check-In: <span style="font-weight: bolder; color: #000D47;">'.$linea_destino['check_in'].'</span> - Check-Out: <span style="font-weight: bolder; color: #000D47;">'.$linea_destino['check_out'].'</span></p>';
                            // $lista_destinos .= '<p style="font-family: `Tahoma`; font-size: 13px; margin: 0; padding: 0.2em 0;">Nombre del Hotel: <span style="font-weight: bolder; color: #000D47;">'.$linea_destino['nombre_hotel'].'</span></p>';
                            // $lista_destinos .= '<p style="font-family: `Tahoma`; font-size: 13px; margin: 0; padding: 0.2em 0;">Direcci&oacute;n del Hotel: <span style="font-weight: bolder; color: #000D47;">'.$linea_destino['direccion_hotel'].'</span></p>';
                            $lista_destinos .= '</div>';

                            $empresas_recibo = "";
                            if ($linea_destino['recibo']!=''){                                
                                $empresas_recibo = $path."/".$linea_destino['recibo'];
                            }
                            array_push($destinos_data, array(
                                "recibo" => $empresas_recibo
                            ));

                            $empresas_recibo_liquidacion = "";
                            if ($linea_destino['recibo_liquidacion']!=''){                                
                                $empresas_recibo_liquidacion = $path."/".$linea_destino['recibo_liquidacion'];
                            }
                            array_push($destinos_data, array(
                                "recibo" => $empresas_recibo_liquidacion
                            ));
                        }
                    }
                }

                // Lista de actividades
                $actividades = $mysql->Consulta("SELECT * FROM reservas_actividades WHERE id_reserva=".$reserva['id_reserva']);
                $lista_actividades = '';
                if (is_array($actividades)){
                    if (count($actividades) > 0){
                        foreach ($actividades as $linea_actividad) {                             
                            $lista_actividades .= '<div style="border-bottom: 1px solid rgba(0,0,0,0.2);">';
                            $lista_actividades .= '<p style="font-family: `Tahoma`; font-size: 13px; margin: 0; padding: 0.2em 0;">Actividad: <span style="font-weight: bolder; color: #000D47;">'.$linea_actividad['actividad'].'</span></p>';
                            $lista_actividades .= '<p style="font-family: `Tahoma`; font-size: 13px; margin: 0; padding: 0.2em 0;">Fecha: <span style="font-weight: bolder; color: #000D47;">'.$linea_actividad['fecha'].'</span></p>';
                            $lista_actividades .= '</div>';
                        }
                    }
                }

                // Lista de Impuestos
                $impuestos = $mysql->Consulta("SELECT * FROM reservas_impuestos WHERE id_reserva=".$reserva['id_reserva']);
                $lista_impuestos = '';
                if (is_array($impuestos)){
                    if (count($impuestos) > 0){
                        $total_impuestos = 0;

                        foreach ($impuestos as $linea_impuesto) {                                                         
                            $lista_impuestos .= '<tr>';
                            $lista_impuestos .= '<td style="text-align: left;">';
                            $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; color: #666666;">'.$linea_impuesto['descripcion'].'</p>';
                            $lista_impuestos .= '</td>';
                            $lista_impuestos .= '<td style="text-align: left; width: 100px;">';
                            $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; color: #666666;">'.$linea_impuesto['num_noches'].'</p>';
                            $lista_impuestos .= '</td>';
                            $lista_impuestos .= '<td style="text-align: right; width: 100px;">';
                            $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; color: #666666;">'.number_format($linea_impuesto['valor'], 2).'</p>';
                            $lista_impuestos .= '</td>';
                            $lista_impuestos .= '<td style="text-align: right; width: 100px;">';
                            $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; color: #666666;">'.number_format($linea_impuesto['total'] ,2).'</p>';
                            $lista_impuestos .= '</td>';
                            $lista_impuestos .= '</tr>';
                            $total_impuestos += floatval($linea_impuesto['total']);
                        }

                        $lista_impuestos .= '<tr>';
                        $lista_impuestos .= '<td style="text-align: left;">';
                        $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; font-weight: bold; color: #666666;"></p>';
                        $lista_impuestos .= '</td>';
                        $lista_impuestos .= '<td style="text-align: left; width: 100px;">';
                        $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; font-weight: bold; color: #666666;"></p>';
                        $lista_impuestos .= '</td>';
                        $lista_impuestos .= '<td style="text-align: right; width: 100px;">';
                        $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; font-weight: bold; color: #666666;">TOTAL</p>';
                        $lista_impuestos .= '</td>';
                        $lista_impuestos .= '<td style="text-align: right; width: 100px;">';
                        $lista_impuestos .= '<p style="font-family: `Tahoma`; margin: 0; padding: 0 0.5em; font-size: 13px; font-weight: bold; color: #666666;">'.number_format($total_impuestos ,2).'</p>';
                        $lista_impuestos .= '</td>';
                        $lista_impuestos .= '</tr>';
                    }
                }

                // lista de adjuntos               
                $adjuntos = $mysql->Consulta("SELECT * FROM reservas_adjuntos WHERE id_reserva=".$reserva['id_reserva']);
                $lista_adjuntos = [];
                if (is_array($adjuntos)){
                    if (count($adjuntos) > 0){
                        foreach ($adjuntos as $linea_adjunto) {
                            array_push($lista_adjuntos, array(
                                "link" => $path."/".$linea_adjunto['link']
                            ));
                        }
                    }
                }

                // Informacion adicional si el destino es Galapagos
                $galapagos = '';
                if ($primer_destino == 6){
                    $galapagos .= '<p>Por favor una vez realizado el pago, necesitamos que nos envíe el comprobante por este mismo medio para registrarlo y así poder enviarle la confirmación de su reserva.</p></br>';
                    $galapagos .= '<p>Adicional le informo y adjunto los requisitos para el destino de Galapagos, así como también las recomendaciones a tomar en cuenta y el valor de pago de impuestos que debe ser realizado 5 días anticipados a su viaje.</p>';

                    $galapagos .= '<p>Adjunto encontrará la nueva resolución emitida por el Gobierno de Galapagos para el respectivo ingreso a la Isla, por favor considere los parámetros para su viaje.</p>';
    
                    $galapagos .= '<p>Debido a la emergencia sanitaria que se ha dado a nivel mundial por el COVID -19 las Islas Galápagos ha tomado diferentes medidas de salud para el ingreso de los turistas.</p>';
    
                    $galapagos .= '<p>Es por esto que nosotros como su operadora de viajes le guiaremos para que usted cuente con todos los requisitos</p>';
                    $galapagos .= '<ul>';
                    $galapagos .= '<li>Para iniciar con su viaje necesitamos que todos los pasajeros mantengan su documentación al dia, sea su Cédula de Identidad para nacionales o Pasaporte para extranjeros también, que nos envié esta información por correo puede ser una fotografía de la parte delantera de su cedula o una copia escaneada enviada por este medio.</li>';
                    $galapagos .= '<li>Debido a los nuevos lineamientos aprobados por el COE Nacional, los requisitos para ingresar a Galápagos cambian a partir del 23 de Octubre del 2021</li>';
                    $galapagos .= '<li>Todo pasajero mayor de 2 años de edad con destino a Galápagos además de los requisitos migratorios para turista, transeúnte o residente deberá presentar estos dos requisitos:</li>';
                    $galapagos .= '<ul>';
                    $galapagos .= '<li>1. Certificado de vacunación con dosis completas o prueba RT-PCR para detección de COVID-19 con resultado negativo y vigencia de máximo de 72 horas previas al viaje, contadas desde la toma de la muestra. (2 días antes del Viaje)</li>';
                    // $galapagos .= '<li>2. Carnet de vacunación contra el COVID-19* o su equivalente, con el esquema completo según corresponda, el mismo que deberá encontrarse vigente al menos 14 días antes del vuelo.</li>            ';
                    $galapagos .= '</ul>';
                    $galapagos .= '</ul>';
                    // $galapagos .= '<p>* Para los pasajeros menores de 16 años no es obligatorio presentar Carnet de Vacunación</p>';
    
                    $galapagos .= '<ul>';
                    $galapagos .= '<li>Los laboratorios autorizados por el MSP para realizarse las pruebas, los puede consultar aquí👇: <a href="http://www.calidadsalud.gob.ec/wp-content/uploads/2021/01/Laboratorios-autorizados-Galapagos-ACESS.pdf.">http://www.calidadsalud.gob.ec/wp-content/uploads/2021/01/Laboratorios-autorizados-Galapagos-ACESS.pdf.</a></li>';
                    $galapagos .= '<li>Si no cuenta con el certificado de vacunación puede descargarse a través del siguiente link: <a href="https://sgrdacaa-admision.msp.gob.ec/hcue/paciente/certificadovacuna/public/index">https://sgrdacaa-admision.msp.gob.ec/hcue/paciente/certificadovacuna/public/index</a>, con 72 horas de antelación.</li>';
                    $galapagos .= '<li>El momento en que se Realice su Prueba PCR debe solicitar al Laboratorio que envíen el resultado a usted con copia al correo del Aeropuerto de la Ciudad donde usted Tiene la Salida</li>';
                    $galapagos .= '<li>Para Quito el correo es: pruebapcrquito@gobiernogalapagos.gob.ec</li>';
                    $galapagos .= '<li>Para Guayaquil el correo es: pruebapcrguayaquil@gobiernogalapagos.gob.ec</li>';
                    $galapagos .= '<li>Llevar Factura del pago de la prueba realizada.</li>';
                    $galapagos .= '<li>Formulario “Declaración de Salud del Viajero” con indicación del lugar de su permanencia. El Formulario lo debe llevar lleno al aeropuerto, al terminarlo debe firmarlo ( uno por familia).</li>';
                    $galapagos .= '<li>Se recomienda llevar lleno su Formulario para su revisión en el Aeropuerto.</li>';
                    $galapagos .= '<li>Se le entregara un Voucher de Servicios para su viaje (1 dia antes del viaje)</li>';
                    $galapagos .= '<li>Pago de tasas establecidas para ingreso a la provincia (Tarjeta Migratoria $25), Parque Nacional Galápagos (Estación Científica $6), Bus Lobito ($5 Transporte de Cruce de Islas + $1 la lancha), cada uno de estos pagos son por persona.</li>';
                    $galapagos .= '<li>Se recomienda mantenga un aislamiento de mínimo 10 Días antes de su viaje para que puedan viajar sin ningún contratiempo.</li>';
                    $galapagos .= '<li>Solicitamos que antes de realizar la prueba PCR se comunique con la aerolínea para confirmar la salida del vuelo, ya que por minoría de pasajeros las salidas están siendo modificadas.</li>';
                    $galapagos .= '<li>Si su prueba PCR es realizada con más de 72 horas es posible que no pueda abordar en su vuelo. (2 días antes)</li>';
                    $galapagos .= '<li>Si fuera el caso de que su vuelo ha sido cancelado o modificado, deberá comunicarse con nosotros inmediatamente.</li>';
                    $galapagos .= '<li>Tomar en cuenta que sus servicios son prepagados antes de que usted Viaje, si sus pruebas PCR SON Positivas no podemos cancelar ningún servicio.</li>';
                    $galapagos .= '</ul>';
    
                    $galapagos .= '<p>Le brindamos todas estas indicaciones para que pueda realizarlas con anticipación a su viaje.</p>';
                    $galapagos .= '<p>Cualquier duda puede contactarse con nosotros</p>';
                }                

                $retorno['informacion']['cliente'] = $cliente;
                $retorno['informacion']['pasajeros'] = $pasajeros;
                $retorno['informacion']['transfers'] = $transfers;
                $retorno['informacion']['destinos'] = $lista_destinos;
                $retorno['informacion']['destinos_data'] = $destinos_data;
                $retorno['informacion']['actividades'] = $lista_actividades;
                $retorno['informacion']['adjuntos'] = $lista_adjuntos;
                $retorno['informacion']['impuestos'] = $lista_impuestos;
                $retorno['informacion']['observaciones_impuestos'] = $reserva['observaciones_impuestos'];
                $retorno['informacion']['galapagos'] = $galapagos;
                $retorno['informacion']['primer_destino'] = $primer_destino_nombre;

                $retorno['estado'] = true;
            }else{
                $retorno['error'] = "No se encontró información de la reserva.";    
            }

        }else{
            $retorno['error'] = "No se encontró información del ticket.";
        }

        return $retorno;
    }

    function Realizar_Cobro($id_cliente, $id_paquete){

        $retorno = array('estado' => false);

        $mysql = new Database('vtgsa_ventas');
        $p2p = new PlacetoPay(PLACETOPAY);

        // $retorno['statte'] = $p2p->get_state();

        if ($p2p->get_state()['estado']){
            $datos_cliente = $mysql->Consulta_Unico("SELECT * FROM clientes WHERE id_cliente=".$id_cliente);

            if (isset($datos_cliente['id_cliente'])){
                $tipo_documento = $datos_cliente['tipo_documento'];
                $documento = $datos_cliente['documento'];
                $apellidos = $datos_cliente['apellidos'];
                $nombres = $datos_cliente['nombres'];
                $correo = $datos_cliente['correo'];
                $celular = $datos_cliente['celular'];
                // $token = $datos_cliente['token'];

                $cuotas_pendientes = $this->Verificar_Cuotas_Pendientes($id_cliente);

                if ($cuotas_pendientes['estado'] == false){
                    // Obtiene Pagos Pendientes

                    $consulta_pagos = $mysql->Consulta_Unico("SELECT C.id_cuota, C.id_compra, C.id_cliente, C.id_paquete, C.valor, C.fecha_pago, T.token FROM clientes_cuotas C, clientes_compras M, clientes_tarjetas T WHERE ((C.id_cliente=".$id_cliente.") AND (C.id_paquete=".$id_paquete.") AND ((C.estado=0) OR (C.estado=5))) AND (C.id_compra=M.id_compra) AND (M.id_tarjeta=T.id_tarjeta) ORDER BY C.id_cuota ASC LIMIT 1");

                    if (isset($consulta_pagos['id_cuota'])){
                        // Crea estructura para pago
                        $id_cuota = $consulta_pagos['id_cuota'];  
                        $token = $consulta_pagos['token'];                                
                        
                        $valida_tipo_documento = "CI";
                        switch ($tipo_documento) {
                            case 0:
                                $valida_tipo_documento = "CI";
                                break;
                            case 1:
                                $valida_tipo_documento = "RUC";
                                break;
                            case 2:
                                $valida_tipo_documento = "PPN";
                                break;
                        }
                                                    
                        $payment = array(
                            "documento" => $documento,
                            "tipo" => $valida_tipo_documento,
                            "nombres" => $nombres,
                            "apellidos" => $apellidos,
                            "correo" => $correo,
                            "celular" => $celular,
                            "descripcion" => "Pago Suscripcion",
                            "paquete" => 1, // paquete nuevo = 1
                            "total" => (float) $consulta_pagos['valor']
                        );


                        $reference = "PQTC".$id_cuota.date("YmdHis");
                        $info_pago = $p2p->make_payment_suscription($reference, $token, $payment);

                        $retorno['process'] = $info_pago;

                        $base64 = base64_encode(json_encode($info_pago));
        
                        $status = $info_pago['response']['status']['status'];
                        $message = $info_pago['response']['status']['message'];
                        $date = $info_pago['response']['status']['date'];
                        
                        $requestId = "";
                        $status_pago = "";
                        $date_pago = "";
                        $message_pago = "";
                        $internalReference = "";
                        $paymentMethodName = "";
                        $authorization = "";
                        $reference_cuota = $reference;
                        $receipt = "";
                        $estado_cuota = 0;

                        $informacion = $info_pago['response'];
                        $requestId = $informacion['requestId'];

                        $pago = null;
                        $status_pago = "";
                        $date_pago = "";
                        $message_pago = "";

                        if (isset($informacion['payment'][0])){
                            $pago = $informacion['payment'][0];
                            $status_pago = $pago['status']['status'];
                            $date_pago = $pago['status']['date'];
                            $message_pago = $pago['status']['message'];
                        }                                                            

                        switch ($status) {
                            case 'APPROVED':
                                $internalReference = $pago['internalReference'];
                                $paymentMethodName = $pago['paymentMethodName'];
                                $authorization = $pago['authorization'];
                                $reference_cuota = $pago['reference'];
                                $receipt = $pago['receipt'];
            
                                $estado_cuota = 1;
                                
                                $retorno['cuota'] = $requestId;
                                $retorno['estado'] = true;
                                break;                                        
                            case "REJECTED":
                                $status_pago = $info_pago['response']['status']['status'];
                                $date_pago = $info_pago['response']['status']['date'];
                                $message_pago = $info_pago['response']['status']['message'];
                                $estado_cuota = 5;

                                $respuesta['error'] = $message;
                                $retorno['estado'] = true;
                                break;
                            case 'FAILED':
                                $status_pago = $info_pago['response']['status']['status'];
                                $date_pago = $info_pago['response']['status']['date'];
                                $message_pago = $info_pago['response']['status']['message'];
                                $estado_cuota = 5;

                                $respuesta['error'] = $message;
                                $retorno['estado'] = true;
                                break;
                            case 'PENDING':
                                $status_pago = $info_pago['response']['status']['status'];
                                $date_pago = $info_pago['response']['status']['date'];
                                $message_pago = $info_pago['response']['status']['message'];
                                $estado_cuota = 0;

                                $respuesta['error'] = $message;
                                $retorno['estado'] = true;
                                break;
                            case 'PENDING_VALIDATION':
                                $status_pago = $info_pago['response']['status']['status'];
                                $date_pago = $info_pago['response']['status']['date'];
                                $message_pago = $info_pago['response']['status']['message'];
                                $estado_cuota = 0;

                                $respuesta['error'] = $message;
                                $retorno['estado'] = true;
                                break;
                        }                                   

                        $actualizar = $mysql->Modificar("UPDATE clientes_cuotas SET requestId=?, status=?, date_status=?, message_status=?, internalReference=?, paymentMethodName=?, authorization=?, reference=?, receipt=?, estado=?, base64=? WHERE id_cuota=?", array($requestId, $status_pago, $date_pago, $message_pago, $internalReference, $paymentMethodName, $authorization, $reference_cuota, $receipt, $estado_cuota, $base64, $id_cuota));

                    }else{
                        $retorno['error'] = "El cliente ya no tiene cobros por realizar.";
                    }
                }else{
                    $retorno['error'] = "El cliente mantiene cuotas pendientes de verificacion.";
                }

                    
            }else{
                $retorno['error'] = "No se encontro informacion del cliente.";
            }
        }else{
            $retorno['error'] = $p2p->get_state()['error'];
        }

        return $retorno;
    }

    function Validar_Documento_Identidad($documento){
        $validador = new ValidarIdentificacion();
        $documento_valido = array(
            "estado" => false,
            "tipo" => "El documento no es válido",
            "error" => "",
            "codigo" => ""
        );

        try{
            if ($documento!=''){
                if (strlen($documento)>=10){                    
                    if ($validador->validarCedula($documento)) {
                        $documento_valido['estado'] = true;
                        $documento_valido['tipo'] = "Cedula de Identidad";
                        $documento_valido['codigo'] = "CI";
                    }else if ($validador->validarRucPersonaNatural($documento)) {
                        // validar RUC persona natural
                        $documento_valido['estado'] = true;
                        $documento_valido['tipo'] = "RUC Persona Natural";
                        $documento_valido['codigo'] = "RUC";
                    }else if ($validador->validarRucSociedadPrivada($documento)) {
                        // validar RUC sociedad privada
                        $documento_valido['estado'] = true;
                        $documento_valido['tipo'] = "RUC Sociedad Privada";
                        $documento_valido['codigo'] = "RUC";
                    }else if ($validador->validarRucSociedadPublica($documento)) {
                        // validar RUC sociedad publica
                        $documento_valido['estado'] = true;
                        $documento_valido['tipo'] = "RUC Sociedad P&uacute;blica";
                        $documento_valido['codigo'] = "RUC";
                    }
                }     
            }
        }catch(Exception $e){
            $documento_valido['error'] = $e->getMessage();
        }

        return $documento_valido;
    }

    function Verificar_Cuotas_Pendientes($id_cliente){
        $retorno = array("estado" => false);

        $mysql = new Database("vtgsa_ventas");

        $cuotas_pendientes = $mysql->Consulta_Unico("SELECT * FROM clientes_cuotas WHERE (id_cliente=".$id_cliente.") AND (estado=0) AND (status='PENDING') ORDER BY id_cuota ASC LIMIT 1");
        
        if (isset($cuotas_pendientes['id_cuota'])){
            $retorno['referencia'] = $cuotas_pendientes['reference'];
            $retorno['valor'] = (float) $cuotas_pendientes['valor'];
            $retorno['estado'] = true;
        }
        
        return $retorno;
    }

    function Traducir_Estado($estado){
        switch ($estado) {
            case 'APPROVED':
                return 'APROBADA';
                break;
            case 'PENDING':
                return 'PENDIENTE';
                break;
            case 'REJECTED':
                return 'RECHAZADA';
                break;
            case 'FAILED':
                return 'FALLIDO';
                break;
            default:
                return "";
                break;
        }        
    }

    function Cambiando_Fecha($fecha){
        $anio = substr($fecha, 0, 4);
        $mes = substr($fecha, 4, 2);
        $dia = substr($fecha, 6, 2);

        return $anio."-".$mes."-".$dia;
    }


    function Archivo_Temporal($nombre_archivo, $archivo_temporal, $descripcion){
        $folder_tmp = __DIR__."/../../../public/tmp/firmados";
        $retorno = array("estado" => false);
                
        // obtiene la extension
        $separacion = explode(".", $nombre_archivo);
        $separacion_lng = count($separacion);
        if ($separacion_lng > 0){
            $extension = $separacion[($separacion_lng - 1)];
            $nombre_archivo = $descripcion."_".date("YmdHis").".".$extension;
            
            $destino = $folder_tmp.'/'.$nombre_archivo;
            move_uploaded_file($archivo_temporal, $destino);

            $retorno['archivo'] = array(
                "tmp" => $destino,
                "nombre" => $nombre_archivo
            );

            $retorno['estado'] = true;
        }else{
            $retorno['error'] = "No se puede obtener la extensión del archivo.";
        }        

        return $retorno;
    }

    function Fecha_Sin_Separadores($fecha){
        $separa = explode(' ', $fecha);
        $campos1 = explode('-', $separa[0]);
        $campos2 = explode(':', $separa[1]);
    
        return $campos1[0].$campos1[1].$campos1[2].$campos2[0].$campos2[1].$campos2[2];
    }

    function Obtener_Estado($estado_lista){
        $retorno = array("estado" => false);

        try{
            $descripcion_estado = "";
            $color_estado = "";

            switch ($estado_lista) {
                case 0:
                    $descripcion_estado = "Sin Contactar";
                    $color_estado = "warning";
                    break;
                case 1:
                    $descripcion_estado = "Llamar más tarde";
                    $color_estado = "warning";
                    break;
                case 2:
                    $descripcion_estado = "Apagado";
                    $color_estado = "warning";
                    break;
                case 3:
                    $descripcion_estado = "No Contesta";
                    $color_estado = "warning";
                    break;
                case 4:
                    $descripcion_estado = "Interesado";
                    $color_estado = "info";
                    break;
                case 5:
                    $descripcion_estado = "No Interesado";
                    $color_estado = "danger";
                    break;
                case 6:
                    $descripcion_estado = "Información Incorrecta";
                    $color_estado = "danger";
                    break;
                case 7:
                    $descripcion_estado = "Venta Realizada";
                    $color_estado = "success";
                    break;               
                case 8:
                    $descripcion_estado = "Vendido";
                    $color_estado = "primary";
                    break; 
                case 10:
                    $descripcion_estado = "Bloqueado";
                    $color_estado = "secondary";
                    break;
            }

            $retorno['descripcion'] = $descripcion_estado;
            $retorno['color'] = $color_estado;

            $retorno['estado'] = true;
        }catch(Exception $e){
            $retorno['error'] = $e->getMessage();
        }
        
        return $retorno;
    }

    function Esconder_Numero($numero){
        $mascara = "";

        for($i=0; $i<strlen($numero); $i++){
            if (($i<=2
            ) || ($i>=8)){
                $mascara .= $numero[$i];
            }else{
                $mascara .= "X";
            }
        }

        return $mascara;
    }

    function Procesar_Nueva_Base($archivo, $procesar = false){
        $retorno = array("estado" => false);

        try{
            $carpeta = __DIR__."/../../../public/tmp";
            $path_archivo = $carpeta."/".$archivo;

            $verificacion_archivo = $this->Leer_Archivo($path_archivo);

            if ($verificacion_archivo['estado']){
                $retorno['procesamiento'] = $verificacion_archivo['resultados'];
                $retorno['estado'] = true;
            }else{  
                $retorno['error'] = $verificacion_archivo['error'];
            }
            
        }catch(Exception $e){
            $retorno['error'] = $e->getMessage();
        }

        return $retorno;
    }

    function Leer_Archivo($destino, $delimitador = ",", $longitudDeLinea = 2500, $caracterCircundante = "'"){
        $retorno = array("estado" => false);
        
        if (file_exists($destino)){
            // # Abrir el archivo
            $gestor = fopen($destino, "r");
            if (!$gestor) {
                $retorno['error'] = "No se puede abrir el archivo";                        
            }

            $retorno['resultados'] = array(
                "listado" => [],
                "total" => 0,
                "con_error" => 0
            );
            
            $numeroDeFila = 1;                    
            while (($fila = fgetcsv($gestor, $longitudDeLinea, $delimitador, $caracterCircundante)) !== false) {
                if ($numeroDeFila >= 2) {
                    // Revisa estructura del archivo
                    if (
                        (isset($fila[0])) && 
                        (isset($fila[1])) && 
                        (isset($fila[2])) && 
                        (isset($fila[3])) && 
                        (isset($fila[4])) && 
                        (isset($fila[5])) && 
                        (isset($fila[6])) && 
                        (isset($fila[7])) && 
                        (isset($fila[8])) && 
                        (isset($fila[9])) && 
                        (isset($fila[10])) && 
                        (isset($fila[11]))
                    ){
                        $documento = utf8_decode(trim($fila[0]));
                        $nombres = utf8_decode(trim($fila[1]));

                        $telefono1 = "0".utf8_decode(trim($fila[2]));
                        $telefono2 = "0".utf8_decode(trim($fila[3]));
                        $telefono3 = "0".utf8_decode(trim($fila[4]));
                        $telefono4 = "0".utf8_decode(trim($fila[5]));
                        $telefono5 = "0".utf8_decode(trim($fila[6]));
                        $telefono6 = "0".utf8_decode(trim($fila[7]));

                        $celular_principal = "";
                        if ((trim($telefono1) != "") && (strlen(trim($telefono1)) == 10)){
                            $celular_principal = $telefono1;
                        }else if ((trim($telefono2) != "") && (strlen(trim($telefono2)) == 10)){
                            $celular_principal = $telefono2;
                        }else if ((trim($telefono3) != "") && (strlen(trim($telefono3)) == 10)){
                            $celular_principal = $telefono3;
                        }else if ((trim($telefono4) != "") && (strlen(trim($telefono4)) == 10)){
                            $celular_principal = $telefono4;
                        }else if ((trim($telefono5) != "") && (strlen(trim($telefono5)) == 10)){
                            $celular_principal = $telefono5;
                        }else if ((trim($telefono6) != "") && (strlen(trim($telefono6)) == 10)){
                            $celular_principal = $telefono6;
                        }

                        if (empty($celular_principal)){
                            $celular_principal = $telefono1;
                        }

                        $ciudad = utf8_decode(trim($fila[8]));
                        $direccion = utf8_decode(trim($fila[9]));
                        $correo = utf8_decode(trim($fila[10]));
                        $observaciones = utf8_decode(trim($fila[11]));

                        $retorno['resultados']['total'] += 1;
                        array_push($retorno['resultados']['listado'], array(
                            "documento" => $documento,
                            "nombres" => $nombres,
                            "principal" => $celular_principal,
                            "telefono1" => $telefono1,
                            "telefono2" => $telefono2,
                            "telefono3" => $telefono3,
                            "telefono4" => $telefono4,
                            "telefono5" => $telefono5,
                            "telefono6" => $telefono6,
                            "ciudad" => $ciudad,
                            "direccion" => $direccion,
                            "correo" => $correo,
                            "observaciones" => $observaciones,
                            "estado" => 10
                        ));

                        $retorno['estado'] = true;
                    }else{
                        $retorno['error'] = "La estructura del archivo no es la correcta. Favor revisar contenido.";
                    }
                }
                $numeroDeFila++;                        
            }

            fclose($gestor);
            unlink($destino); 
        }else{
            $retorno['error'] = "No existe el archivo a procesar.";
        }

        return $retorno;
    }
}

class Events{

    private $mysql = null;
    private $fecha_alta = null;    
    private $id_asesor = null;

    function __construct($mysql, $id_asesor){
        $this->mysql = $mysql;
        $this->fecha_alta = date("Y-m-d H:i:s");        
        $this->id_asesor = $id_asesor;
    }

    function New_Event($id_ticket, $comentarios, $link = ""){
        $retorno = array("estado" => false);        

        $id_evento = $this->mysql->Ingreso("INSERT INTO tickets_eventos (id_ticket, id_asesor, comentario, link, fecha_alta, estado) VALUES (?,?,?,?,?,?)", array($id_ticket, $this->id_asesor, $comentarios, $link, $this->fecha_alta, 0));

        $retorno['estado'] = true;        

        return $retorno;
    }

    function Get_Event($id_ticket){        
        $retorno = $this->mysql->Consulta("SELECT A.asesor, A.apodo, T.comentario, T.link, T.fecha_alta FROM tickets_eventos T, asesores A WHERE (T.id_ticket=".$id_ticket.") AND (T.id_asesor=A.id_asesor) ORDER BY T.fecha_alta DESC");

        return $retorno;
    }   
}

class CRM_API { 
    
    private $url = null;
    private $client = null;
    private $options = [
        'body'    => "",
        'headers' => [
            "Content-Type" => "application/json",
        ]
    ];  
    
    function __construct($ambiente = "test"){
        if ($ambiente == "test"){
            $this->url = "https://datacrm.mvevip.com/api";
        }else{
            $this->url = "https://datacrm.mvevip.com/api";
        }
        
        $this->client = new Client();
    }

    function getIdCliente($id_cliente){
        $response = $this->client->request('GET', $this->url."/?idcliente=".$id_cliente);
        return json_decode($response->getBody(), true);
    }

    function getAbono($request){     

        //  = array(
        //     "lote" => "1224",
        //     "referencia" => "4700",
        //     "fecha" => "2021-12-01",
        //     "valor" => 1298
        // );
        $this->options['body'] = json_encode($request);
        $response = $this->client->request('POST', $this->url."/buscar_pagos_tarjetas.php", $this->options);
        
        return json_decode($response->getBody(), true);
    }

    function Registros_Pendientes_Tarjetas($params){        
        $response = $this->client->request('GET', $this->url."/buscar_registros_tarjetas.php?fecha=".$params['fecha']."&buscador=".$params['buscador']."&banco=".$params['banco']."&filtro=".$params['filtro'], $this->options);
        
        return json_decode($response->getBody(), true);        
    }

    function Registros_Consolidado_Tarjetas($params){        
        $response = $this->client->request('GET', $this->url."/buscar_registros_consolidado_tarjetas.php?fecha=".$params['fecha']."&buscador=".$params['buscador']."&banco=".$params['banco']."&filtro=".$params['filtro'], $this->options);
        
        return json_decode($response->getBody(), true);     
    }

    function Verificar_Estados_Transacciones(){        
        $response = $this->client->request('GET', $this->url."/verificar_pagos_tarjetas_estados.php");
        
        return json_decode($response->getBody(), true);
    }

    function guardarPagos($request){     
      
        $this->options['body'] = json_encode($request);
        $response = $this->client->request('POST', $this->url."/guardar_pagos_tarjetas.php", $this->options);
        
        return json_decode($response->getBody(), true);
    }

    function ActualizarPagos($id_transaccion, $request){     
      
        $this->options['body'] = json_encode($request);
        $response = $this->client->request('POST', $this->url."/actualizar_registro_tarjetas.php?id_transaccion=".$id_transaccion, $this->options);
        
        return json_decode($response->getBody(), true);
    }

    function Obtener_Usuario($documento){
        
        $response = $this->client->request('GET', $this->url."/obtener_usuario.php?documento=".$documento);
        
        
        
        return json_decode($response->getBody(), true);
    }   
    
    function Obtener_Reporte_Facturas(){
        
        $response = $this->client->request('GET', $this->url."/busca_primera_factura_cliente.php");

        return json_decode($response->getBody(), true);
    }


    function Obtener_Facturas_Pendientes($id_cliente){
        
        $response = $this->client->request('GET', $this->url."/buscar_facturas_asignar_reserva.php?idcliente=".$id_cliente);

        return json_decode($response->getBody(), true);
    }


    /// LOG LLAMADAS SERVIDOR 192.168.0.40
    function Obtener_Status_Extension($extension){
        
        $response = $this->client->request('GET', $this->url."/consulta_status_extension.php?ext=".$extension);

        return json_decode($response->getBody(), true);
    }

    function Obtener_Log_Llamadas($celular, $audio = ""){
        
        $response = $this->client->request('GET', $this->url."/consulta_log_llamadas.php?celular=".$celular."&audio=".$audio);

        return json_decode($response->getBody(), true);
    }

    function Obtener_Audio_Llamada($grabacion){
        
        $response = $this->client->request('GET', $this->url."/consulta_audio_llamada.php?grabacion=".$grabacion);

        return json_decode($response->getBody(), true);
    }


    function NuevoSeguro($request){     
      
        $this->options['body'] = json_encode($request);
        $response = $this->client->request('POST', $this->url."/nueva_venta_seguro.php", $this->options);
        
        return json_decode($response->getBody(), true);
    }

    function Lista_Paises(){
        
        $response = $this->client->request('GET', $this->url."/listar_paises.php");

        return json_decode($response->getBody(), true);
    }

    function Lista_Provincias($id_pais){
        
        $response = $this->client->request('GET', $this->url."/listar_provincias.php?id_pais=".$id_pais);

        return json_decode($response->getBody(), true);
    }

    function Lista_Ciudades($id_pais, $id_provincia){
        
        $response = $this->client->request('GET', $this->url."/listar_ciudades.php?id_pais=".$id_pais."&id_provincia=".$id_provincia);

        return json_decode($response->getBody(), true);
    }

    function Crear_Cliente($request){     
      
        $this->options['body'] = json_encode($request);
        $response = $this->client->request('POST', $this->url."/nuevo_cliente.php", $this->options);
        
        return json_decode($response->getBody(), true);
    }

    function Lista_Clientes($id_asesor, $buscador){
        
        $response = $this->client->request('GET', $this->url."/listar_clientes.php?id_asesor=".$id_asesor."&buscador=".$buscador);

        return json_decode($response->getBody(), true);
    }

    function Lista_Productos(){
        
        $response = $this->client->request('GET', $this->url."/listar_productos.php");

        return json_decode($response->getBody(), true);
    }


    function agregarLiquidaciones($request){     
      
        $this->options['body'] = json_encode($request);
        $response = $this->client->request('POST', $this->url."/creacionLiquidacion.php", $this->options);
        
        return json_decode($response->getBody(), true);
    }

    function numerarLiquidacionesPendientes(){     
      
        $response = $this->client->request('GET', $this->url."/agruparLiquidacion.php");

        return json_decode($response->getBody(), true);
    }
        
}

class CALL_API { 
    
    private $url = null;
    private $client = null;
    private $options = [
        'body'    => "",
        'headers' => [
            "Content-Type" => "application/json",
        ]
    ];  
    function __construct(){
        $this->url = "http://192.168.0.40/asteMKV";
        $this->client = new Client();
    }

    function getCalls($celular, $archivo, $tipo = "mp3"){
        $response = $this->client->request('GET', $this->url."/verificacion.php?celular=".$celular."&archivo=".$archivo."&tipo=".$tipo);
        return json_decode($response->getBody(), true);
    }
    
    function getStatus($extension){
        $response = $this->client->request('GET', $this->url."/llamada.php?sip=".$extension);
        return json_decode($response->getBody(), true);
    }
} 