<?php 

class MVE {
    private $database = null;
    private $hash = "TUFSS0VUSU5HVklQLUNSTS0yMDIxLzEwLzI5LVNJU1RFTUEtUkVTRVJWQUNJT05FUy1DQVJMT1MtTUlOTw==";
    private $headers = null;

    function __construct(){
        $this->headers = apache_request_headers();
    }

    function Valida_Conexion(){
        $hash = $this->headers['Authorization']; // Obtiene la Authorization enviada por cada llamada

        if ($hash == $this->hash){
            return true;
        }else{
            return false;
        }
    }

    function format_cotizacion($data){
        $cabecera = '';
        $cuerpo = '';
        $original = '';

        $cabecera .= '<table align="center" border="1" cellspacing="0" cellpadding="0" bordercolor="#075FA7" width="100%" style="font-family:Verdana, Geneva, sans-serif" >';
        $cabecera .= '<tr bgcolor="#075FA7" style="color:#FFF" align="center">';
        $cabecera .= '<th colspan="4">COTIZACI&Oacute;N: '.$data['cotizacion'].'</th>';
        $cabecera .= '</tr>';
        $cabecera .= '<tr>';
        $cabecera .= '<td rowspan="3"><img src="../img/logo-v2.png" width="206" height="106" alt="Logo" /></td>';
        $cabecera .= '<td colspan="3">';
        $cabecera .= '<ul>';
        $cabecera .= '<li>R.U.C: 1792554144001</li>';
        $cabecera .= '<li>COMPANIA TURISTICA MARKETING VIP S. A. COMTUMARK</li>';
        $cabecera .= '<li>Dir Matriz: FRANCISCO ANDRADE MARIN OE6-24 Y ELOY ALFARO</li>';
        $cabecera .= '<li>Dir Sucursal: </li>';
        $cabecera .= '<li>Telf: 023815940</li>';
        $cabecera .= '<li>OBLIGADO A LLEVAR CONTABILIDAD: SI</li>';
        $cabecera .= '</ul>';
        $cabecera .= '</td>';
        $cabecera .= '</tr>';
        $cabecera .= '</table><table align="center" border="1" cellspacing="0" cellpadding="0" bordercolor="#075FA7" width="100%" style="font-family:Verdana, Geneva, sans-serif" >';
        $cabecera .= '<tr bgcolor="#075FA7" style="color:#FFF">';
        $cabecera .= '<th colspan="4" scope="col">Resumen del Paquete: '.$data['nombre_paquete'].'</th>';
        $cabecera .= '</tr>';
        $cabecera .= '<tr>';
        $cabecera .= '<td  scope="col"><strong>RAZON SOCIAL / NOMBRE Y APELLIDOS</strong></td>';
        $cabecera .= '<td  scope="col">'.$data['nombres_cliente'].'</td>';
        $cabecera .= '<td  scope="col"><strong>IDENTIFICACION</strong></td>';
        $cabecera .= '<td  scope="col">'.$data['identificacion'].'</td>';
        $cabecera .= '</tr>';
        $cabecera .= '<tr>';
        $cabecera .= '<td  scope="col"><strong>FECHA EMISION</strong></td>';
        $cabecera .= '<td  scope="col">'.$data['fecha_emision'].'</td>';
        $cabecera .= '<td  scope="col"><strong>CODIGO DEL CLIENTE</strong></td>';
        $cabecera .= '<td  scope="col">'.$data['codigo_cliente'].'</td>';
        $cabecera .= '</tr>';
        $cabecera .= '<tr>';
        $cabecera .= '<td scope="col"><strong>CORREO</strong></td>';
        $cabecera .= '<td scope="col">'.$data['correo_cliente'].'</td>';
        $cabecera .= '<td scope="col"><strong>CIUDAD</strong></td>';
        $cabecera .= '<td scope="col">'.$data['ciudad'].'</td>';
        $cabecera .= '</tr>';
        $cabecera .= '</table>';
        $cabecera .= '<br>';

        
        $cuerpo .= '<table align="center" border="1" cellspacing="0" cellpadding="0" bordercolor="#075FA7" width="100%" style="font-family:Verdana, Geneva, sans-serif" id="factura" >';
        $cuerpo .= '<thead>';
        $cuerpo .= '<tr>';
        $cuerpo .= '<th scope="col">CODIGO</th>';
        $cuerpo .= '<th scope="col">CANTIDAD</th>';
        $cuerpo .= '<th scope="col">DESCRIPCI&Oacute;N</th>';
        $cuerpo .= '<th scope="col">PRECIO UNITARIO</th>';
        $cuerpo .= '<th scope="col">PRECIO TOTAL</th>';
        $cuerpo .= '</tr>';
        $cuerpo .= '</thead>';
        $cuerpo .= '<tbody><tr>';
        $cuerpo .= '<td scope="col">'.$data['valores']['paquete'].'-'.$data['valores']['combo'].'</td>';
        $cuerpo .= '<td scope="col">'.$data['valores']['cantidad'].'</td>';
        $cuerpo .= '<td scope="col">'.$data['valores']['descripcion'].'</td>';
        $cuerpo .= '<td scope="col" align="right">'.number_format($data['valores']['imponible'], 2, ".", "").'</td>';
        $cuerpo .= '<td scope="col" align="right">'.number_format($data['valores']['imponible'], 2, ".", "").'</td>';
        $cuerpo .= '</tr></tbody>';
        $cuerpo .= '<tfoot>';
        $cuerpo .= '<tr>';
        $cuerpo .= '<td colspan="4" scope="col" align="right">SUBTOTAL 12% </td>';
        $cuerpo .= '<td scope="col" align="right">'.number_format($data['valores']['imponible'], 2, ".", "").'</td>';
        $cuerpo .= '</tr>';
        $cuerpo .= '<tr>';
        $cuerpo .= '<td colspan="4" scope="col" align="right">SUBTOTAL 0% </td>';
        $cuerpo .= '<td scope="col" align="right">0.00</td>';
        $cuerpo .= '</tr>';
        $cuerpo .= '<tr>';
        $cuerpo .= '<td colspan="4" scope="col" align="right">I.V.A. 12% </td>';
        $cuerpo .= '<td scope="col" align="right">'.number_format($data['valores']['iva'], 2, ".", "").'</td>';
        $cuerpo .= '</tr>';
        $cuerpo .= '<tr>';
        $cuerpo .= '<td colspan="4" scope="col" align="right"><strong>VALOR TOTAL</strong> </td>';
        $cuerpo .= '<td scope="col" align="right">'.number_format($data['valores']['total'], 2, ".", "").'</td>';
        $cuerpo .= '</tr>';
        $cuerpo .= '</tfoot>';
        $cuerpo .= '</table>';


        $original .= '<table align="center" border="1" cellspacing="0" cellpadding="0" bordercolor="#075FA7" width="100%" style="font-family:Verdana, Geneva, sans-serif" >';
        $original .= '<tr bgcolor="#075FA7" style="color:#FFF">';
        $original .= '<th  scope="col">CODIGO</th>';
        $original .= '<th  scope="col">CANTIDAD</th>';

        $original .= '<th  scope="col">DESCRIPCI&Oacute;N</th>';
        $original .= '<th  scope="col">PRECIO UNITARIO</th>';

        $original .= '<th  scope="col">PRECIO TOTAL</th>';
        $original .= '</tr>';
        $original .= '<tr>';
        $original .= '<td  scope="col"> '.$data['valores']['paquete'].'-'.$data['valores']['combo'].'</td>';
        $original .= '<td  scope="col">'.$data['valores']['cantidad'].'</td>';
        $original .= '<td  scope="col">'.$data['valores']['descripcion'].'</td>';
        $original .= '<td  scope="col" align="right">'.number_format($data['valores']['imponible'], 2, ".", "").'</td>';
        $original .= '<td  scope="col" align="right">'.number_format($data['valores']['imponible'], 2, ".", "").'</td>';
        $original .= '</tr>';
        $original .= '<tr>';
        $original .= '<td colspan="4" align="right"  scope="col"><strong>VALOR TOTAL</strong>&nbsp;</td>';
        $original .= '<td  scope="col" align="right"><strong>'.number_format($data['valores']['total'], 2, ".", "").'</strong>';
        $original .= '</td>';
        $original .= '</tr>';
        $original .= '</table>';

        return array(
            "cabecera" => $cabecera,
            "cuerpo" => $cuerpo,
            "original" => $original
        );
    }
}