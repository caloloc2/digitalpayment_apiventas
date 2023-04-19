<?php 

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;

class PDF{
    private $html2pdf = null;
    private $path_tmp = __DIR__.'/tmp';    
    private $path_template = __DIR__.'/templates';
    private $mysql = null;

    function __construct($orientacion = 'P', $tamano = 'A4', $idioma= 'es', $datachar = 'ISO-8859-15', $margenes = array(0, 0, 0, 0)) {
        $this->html2pdf = new Html2Pdf($orientacion, $tamano, $idioma, true, $datachar, $margenes); // array(mL, mT, mR, mB) // 15, 10, 10, 10
        $this->html2pdf->setDefaultFont('Helvetica');
        $this->html2pdf->pdf->SetAuthor('Marketing VIP');
        $this->html2pdf->pdf->SetTitle('Contrato de Servicios');

        $this->mysql = new Database("vtgsa_ventas");

        if (!file_exists($this->path_tmp)){
            mkdir($this->path_tmp, 777);
        }
    }

    function Caducidad_Letras($fecha){
        $dia = "05";
        $mes = "mayo";
        $anio = "2022";

        return array(
            "dia" => $dia,
            "mes" => $mes,
            "anio" => $anio 
        );
    }

    function Contexto($tipo, $datos){
        $parrafo = "";

        switch ($tipo) {
            case 'seguros':
                // Fecha de caducidad a Letras
                $fecha_de_caducidad = $this->Caducidad_Letras($datos['fecha_modificacion']);

                // <li style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;">Asesoramiento de Visado para 4 personas</li>

                $parrafo = '<p style="text-align: center; margin-left: 60px; margin-right: 60px;"><strong>TÉRMINOS Y CONDICIONES DE CONTRATACIÓN DE ASISTENCIA DE VIAJE TERRAWIND MUNDIAL</strong></p><p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;">Contrato de Servicios de ASISTENCIA DE VIAJE, que celebran por una parte la COMPANIA TURISTICA "MARKETING VIP S.A" COMTUMARK con RUC # 1792554144001 a quien se le denominará; "LA OPERADORA TURISTICA" y por otra parte el/la Sr(a). '.$datos['nombres'].' con cédula de identidad N# '.$datos['documento'].', a quien en lo sucesivo se le denominará; "EL CLIENTE", al tenor de las siguientes declaraciones y clausulas.</p><p style="font-size: 12px; text-align: justify; margin-left: 60px; margin-right: 60px;"><strong>DECLARACIONES</strong></p><p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;"><strong>PRIMERA.-</strong> La empresa "MARKETING VIP S.A", manifiesta ser una persona jurídica bajo las leyes ecuatorianas, sus oficinas se encuentran ubicadas en la Calle Francisco Andrade Marin E6-24 y Eloy Alfaro. Edf. CAROLINA MILENIUM piso 2 Ofc. 2C. Quito Ecuador.</p><p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;"><strong>SEGUNDA.-</strong> EL CLIENTE O BENEFICIARIO manifiesta ser una persona mayor de edad, capaz y libre de poder adquirir el beneficio que reza en este documento.</p><p style="font-size: 12px; text-align: justify; margin-left: 60px; margin-right: 60px;"><strong>CLÁUSULAS</strong></p>
                <p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;"><strong>PRIMERA.-</strong> "EL CLIENTE", ACEPTA contratar a LA OPERADORA TURÍSTICA, para los siguientes servicios:</p><ul style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;"><li style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;">Asistencia de Viaje para '.$datos['personas'].' personas por '.$datos['dias'].' días</li></ul><p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;">Esta exclusividad tiene vigencia hasta el '.$datos['fecha_caducidad'].' desde su aceptación vía telefónica y/o presencial. "EL CLIENTE" deberá comunicarse 45 días antes de su fecha de viaje para hacer el uso de los servicios contratados. En el caso, que "EL CLIENTE", haya contratado servicios de asesoramiento de visado, lo podrá utilizar en cualquier momento comunicándose a los teléfonos de servicio al cliente. Caso contrario estará sujeto a un fee adicional. Con la aceptación de "EL CLIENTE", y efectuada la activación del código internacional del paquete/servicio vacacional NO tendrá reversión ni devolución de los valores cancelados bajo ningún concepto.</p>
                <p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;">Los presentes Términos y Condiciones de los servicios contratados estarán siempre disponibles al cliente en el siguiente enlace: <a target="_blank" href="https://www.mvevip.com/shopmve/terminos-y-condiciones/">Términos y Condiciones</a>. La firma de este contrato significará que "EL CLIENTE" acepta sin ninguna objeción los términos y condiciones del mismo.</p>';

                if ($datos['forma_pago'] == 0){ // tarjetas
                    $parrafo .= '<p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;"><strong>SEGUNDA.-</strong> “El CLIENTE”, se obliga a pagar a “LA OPERADORA TURISTICA” el total del servicio, que le fue explicado y detallado vía telefónica y/o presencial, de forma pormenorizada cuyo costo total promocional es de $ '.number_format($datos['valor'], 2).', valor que “El CLIENTE”, autoriza a debitar de la cuenta y/o Tarjeta de Crédito Nº '.$datos['primeros_digitos'].'/XXXX/XXXX/'.$datos['ultimos_digitos'].' de la institución financiera '.$datos['institucion'].' y exime a la Institución emisora de la tarjeta de crédito o cuenta de cualquier reclamo posterior por el cobro de los valores autorizados a que sean debitados.</p>';
                }else{ // efectivo
                    $parrafo .= '<p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;"><strong>SEGUNDA.-</strong> “El CLIENTE”, se obliga a pagar a “LA OPERADORA TURISTICA” el total del servicio, que le fue explicado y detallado vía telefónica y/o presencial, de forma pormenorizada cuyo costo total promocional es de $ '.number_format($datos['valor'], 2).', valor que “El CLIENTE”, acepta pagar mediante forma de pago efectivo, depósito o transferencia y exime a la Institución emisora de la cuenta de cualquier reclamo posterior por el cobro de los valores autorizados a que sean debitados.</p>';
                }
                
                $parrafo .= '<p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;"><strong>TERCERA.-</strong> MODIFICACIONES.- El presente convenio no podrá ser modificado en ninguno de sus términos, excepto previo acuerdo por escrito entre las partes, que formará parte integrante del mismo, a través del respectivo ADENDUM.</p><p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;"><strong>CUARTA.-</strong> FUERZA MAYOR .- Los hechos catalogados como de fuerza mayor o caso fortuito, conforme lo previsto en el artículo 30 del Código Civil exonerarán de cualquier responsabilidad tanto a “LA OPERADORA TURISTICA”, como a “EL CLIENTE” frente a las obligaciones que contraigan en virtud del presente convenio.</p><p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;"><strong>QUINTA.-</strong> ACUERDO .- Ambas partes aceptan que el presente documento constituye el acuerdo completo entre las mismas y manifiestan que su voluntad ha sido libremente expresada, en la cual no hay error, dolo, mala fe o ignorancia, ratificando expresamente su contenido y alcance; por lo que cualquier otro acuerdo verbal o escrito relacionado con el objeto del presente contrato celebrado con anterioridad entre ellas queda sin efecto legal alguno. Para todo lo no estipulado en el presente convenio, las partes se someterán a lo dispuesto por la legislación ecuatoriana.</p><p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;">Leído el presente documento por las partes que en él intervienen y habiendo comprendido, las consecuencias de derecho que derivan del mismo lo suscriben de conformidad en dos (02) ejemplares de igual valor y tenor en la Ciudad de Quito, a los tres ('.$fecha_de_caducidad['dia'].') días del mes de '.$fecha_de_caducidad['mes'].' del '.$fecha_de_caducidad['anio'].'.</p>';
                break;
            case 'descargo':
                $parrafo = '<p style="text-align: center; margin-left: 60px; margin-right: 60px;"><strong>DESCARGO DE RESPONSABILIDADES Y ACCIDENTES</strong></p>';
                $parrafo .= '<p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;">Yo, '.$datos['nombres'].', con número de cédula '.$datos['documento'].', <strong>ASUMO TODOS LOS RIESGOS DE NO ADQUIRIR EL SEGURO DE ASISTENCIA EN VIAJES DE MARKETING VIP</strong>, incluyendo a modo de ejemplo y sin limitación, cualquier riesgo que pueda surgir por cualquier eventualidad o accidente que me ocurra durante mi viaje.  Cualquier gasto en el que yo deba incurrir por cualquier eventualidad relacionada a temas de salud la cubriré en su <strong>TOTALIDAD</strong>, en la locación que me encuentre.</p>';
                $parrafo .= '<p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;">Confirmo que me han explicado que los hospitales, clínicas o cualquier evento de algunos de mis familiares o míos son altamente costosos a mi destino de viaje y confirmo que he declinado la cobertura de hasta $50,000.00 dólares por estos eventos.</p>';
                $parrafo .= '<p style="font-size: 10px; text-align: justify; margin-left: 60px; margin-right: 60px;">Eximo a la empresa MARKETING VIP, de cualquier accidente o siniestro que pueda tener durante mis vacaciones junto a mis acompañantes.</p>';
            default:
                # code...
                break;
        }

        // $lineas = 0;
        //     $salto = ""; 
        //     $cuerpo = ;


        //     // $texto = strlen($parrafo);
        //     // $lineas += $texto / 95;

        //     $cuerpo .= $parrafo;

        //     // if ($lineas > 45){
        //     //     $salto = 'style="width: 100%; margin: 0; padding-top: 130px; padding-left:0; padding-right:0; padding-bottom:0;"';
        //     //     $lineas = 10;
        //     // }else{
        //     //     $salto = '';
        //     // }    
        
        if (!empty($parrafo)){
            $parrafo .= $this->Firma_Documento($datos);
        }

        return $parrafo;
    }

    function Firma_Documento($datos){

        $firma = '<table style="width: 100%; margin:10px 0 0 0; padding:0;"><tr><td style="width: 48%; text-align: center; vertical-align: bottom;"><p style="margin:0;padding:0;font-size:11px;"><img style="margin:0; padding:0; width: 75px;" src="'.$this->path_template."/contratos/firma.png".'"></p><p style="margin:0;padding:0;font-size:11px;">________________________________</p><p style="margin:0;padding:0;font-size:11px;"><strong>ANDREA SÁNCHEZ M.</strong></p><p style="margin:0;padding:0;font-size:11px;">GERENTE DE VENTAS</p><p style="margin:0;padding:0;font-size:11px;">MARKETING VIP S.A.</p></td><td style="width: 48%; text-align: center; vertical-align: bottom;"><p style="margin:0;padding:0;font-size:11px;">________________________________</p><p style="margin:0;padding:0;font-size:11px;"><strong>'.$datos['nombres'].'</strong></p><p style="margin:0;padding:0;font-size:11px;">CLIENTE</p><p style="margin:0;padding:0;font-size:11px;">C.I. '.$datos['documento'].'</p></td></tr></table>';

        return $firma;
    }

    function Contrato($datos, $modulo){

        try{
            error_reporting(E_ERROR | E_PARSE);
            $fecha_impresion = date("Y-m-d H:i:s");
            
            $cuerpo = $this->Contexto($modulo, $datos);
            $footer = $this->path_template."/contratos/footer.png";
            $header = $this->path_template."/contratos/header.png";
            
            $lado_derecho = "";
            switch ($modulo) {
                case 'seguros':
                    $lado_derecho = '<img src="'.$this->path_template."/contratos/terrawind.png".'" alt="" style="margin: 0; padding: 0; width: 100%;">';
                    break;
                case 'descargo':
                    $lado_derecho = '<p>Contrato de Descargo</p>';
                    break;
            }

            $contenido = file_get_contents($this->path_template."/contratos/index.html");
            $contenido = str_replace('%fecha_impresion%', $fecha_impresion, $contenido);
            $contenido = str_replace('%footer%', $footer, $contenido);
            $contenido = str_replace('%header%', $header, $contenido);
            $contenido = str_replace('%cuerpo%', $cuerpo, $contenido);
            $contenido = str_replace('%lado_derecho%', $lado_derecho, $contenido);
            $this->html2pdf->writeHTML($contenido);

            $archivo = $nombre_archivo.date("YmdHis").".pdf";
            
            $link = $this->path_tmp."/".$archivo;

            // // D: exporta el archivo en el browser
            // // F: guarda el archivo en el servidor
            $this->html2pdf->output($link, "F");

            $file = base64_encode(file_get_contents($link));

            return array("archivo" => $file, "link" => $link);
            // return array("base64" => $file);
        }catch(Html2PdfException $e){
            $this->html2pdf->clean();
            $formatter = new ExceptionFormatter($e);
            return array('error' => $formatter->getHtmlMessage());
        }catch(Exception $e){
            return array('error' => $e->getMessage());
        }

    }

    function Reserva($datos){

        try{
            error_reporting(E_ERROR | E_PARSE);
            // $fecha_impresion = date("Y-m-d H:i:s");
            
            $cuerpo = "<p style='font-size: 45px; color: #777; font-style: italic; position:absolute; bottom: 255px; margin-left: 50px;'>Hola, <strong>".$datos['nombres']."</strong></p>"; //$this->Contexto($modulo, $datos);
            $footer = $this->path_template."/reservas/membrete.png";
            $footer_destino = $this->path_template."/reservas/destino.jpg";
            $footer_terminos = $this->path_template."/reservas/terminos.jpg";
            // $header = $this->path_template."/reservas/header.png";

            $cuerpo_destino = '<p style="font-size: 30px; color: white; font-weight: bold; position: absolute; margin-left: 450px; top: 35px;">CANCÚN</p>';
            $cuerpo_destino .= '<div style="margin: 0; margin-top: 200px; padding-top: 0px; padding-left: 75px; padding-right: 100px; padding-bottom: 0; position: absolute;">';

            $cuerpo_destino .= '<p style="font-size: 25px; font-weight: bold; color: #444;">OCEAN SPA CANCUN</p>';

            $cuerpo_destino .= '<p style="font-size: 15px; margin:0; padding-top: 0; padding-bottom: 5px;">5 días y 4 noches</p>';
            $cuerpo_destino .= '<p style="font-size: 15px; margin:0; padding-top: 0; padding-bottom: 5px;">Del 12-04-2022 al 15-04-2022</p>';
            $cuerpo_destino .= '<p style="font-size: 15px; margin:0; padding-top: 0; padding-bottom: 5px;">Check-In: 12:50</p>';
            $cuerpo_destino .= '<p style="font-size: 15px; margin:0; padding-top: 0; padding-bottom: 5px;">Check-Out: 12:55</p>';

            $cuerpo_destino .= '<p style="font-size: 13px; margin: 1em 0 0 0; padding-top: 0; padding-bottom: 5px;">Tipo de Habitación: </p>';
            $cuerpo_destino .= '<p style="font-size: 13px; margin: 0; padding-top: 0; padding-bottom: 5px;">Capacidad: </p>';
            $cuerpo_destino .= '<p style="font-size: 13px; margin: 0; padding-top: 0; padding-bottom: 5px;">Confirmación: </p>';
            $cuerpo_destino .= '<p style="font-size: 13px; margin: 0; padding-top: 0; padding-bottom: 5px;">Dirección: </p>';

            $cuerpo_destino .= '<p style="font-size: 13px;">Incluye: </p>';
            $cuerpo_destino .= '<ul>';
            $cuerpo_destino .= '<li style="font-size: 13px; margin: 0; padding: 0;">Amenitie 1</li>';
            $cuerpo_destino .= '<li style="font-size: 13px; margin: 0; padding: 0;">Amenitie 2</li>';
            $cuerpo_destino .= '<li style="font-size: 13px; margin: 0; padding: 0;">Amenitie 3</li>';
            $cuerpo_destino .= '<li style="font-size: 13px; margin: 0; padding: 0;">Amenitie 4</li>';
            $cuerpo_destino .= '</ul>';

            $cuerpo_destino .= '</div>';

            $cuerpo_terminos = '<p style="font-size: 15px; color: #555; font-weight: bold;">Términos y Condiciones - Destino 1</p>';

            $contenido = file_get_contents($this->path_template."/reservas/index.html");
            // $contenido = str_replace('%fecha_impresion%', $fecha_impresion, $contenido);
            $contenido = str_replace('%footer%', $footer, $contenido);
            $contenido = str_replace('%footer_destino%', $footer_destino, $contenido);
            $contenido = str_replace('%footer_terminos%', $footer_terminos, $contenido);
            // $contenido = str_replace('%header%', $header, $contenido);
            $contenido = str_replace('%cuerpo%', $cuerpo, $contenido);
            $contenido = str_replace('%cuerpo_destino%', $cuerpo_destino, $contenido);
            $contenido = str_replace('%cuerpo_terminos%', $cuerpo_terminos, $contenido);
            // $contenido = str_replace('%lado_derecho%', $lado_derecho, $contenido);
            $this->html2pdf->writeHTML($contenido);

            $archivo = date("YmdHis").".pdf";
            
            $link = $this->path_tmp."/".$archivo;

            // // D: exporta el archivo en el browser
            // // F: guarda el archivo en el servidor
            $this->html2pdf->output($link, "F");

            $file = base64_encode(file_get_contents($link));

            // return array("archivo" => $file, "link" => $link);
            // return array("link" => $link);
            return array("base64" => $file);
        }catch(Html2PdfException $e){
            $this->html2pdf->clean();
            $formatter = new ExceptionFormatter($e);
            return array('error' => $formatter->getHtmlMessage());
        }catch(Exception $e){
            return array('error' => $e->getMessage());
        }

    }

    function crear_pdf($titulo_reporte, $detalle_reporte, $nombre_archivo = "report"){
        try{
            // $fecha_impresion = date("Y-m-d H:i:s");
            // $css = $this->path_template."/estilo.css";
            // $logotipo =  $this->path_template."/sinlogo.png";
            $contenido = file_get_contents($this->path_template."/contratos/index.html");
            // $contenido = str_replace('%css%', $css, $contenido);
            // $contenido = str_replace('%logotipo%', $logotipo, $contenido);
            // $contenido = str_replace('%fecha_impresion%', $fecha_impresion, $contenido);
            // $contenido = str_replace('%nombre_reporte%', $titulo_reporte, $contenido);
            // $contenido = str_replace('%detalle_reporte%', $detalle_reporte, $contenido);
            $this->html2pdf->writeHTML($contenido);

            $archivo = $nombre_archivo.date("YmdHis").".pdf";
            
            $link = "tmp/".$archivo;

            // D: exporta el archivo en el browser
            // F: guarda el archivo en el servidor
            $this->html2pdf->output($link, "F");

            $file = base64_encode(file_get_contents($link));

            return array("archivo" => $file, "link" => $link);
        }catch(Html2PdfException $e){
            $this->html2pdf->clean();
            $formatter = new ExceptionFormatter($e);
            return array('error' => $formatter->getHtmlMessage());
        }
    }
}