<?php 

use Twilio\Rest\Client; 

function Whatsapp($numero, $voucher){
    $sid = "AC48751e88562a6781e0b615e3a6b8bdf2";
    $token = "5e11c3dab68ffb94761b016693d464e8";

    $twilio = new Client($sid, $token); 

    $celular = '+593'.$numero;

    $message = $twilio->messages->create("whatsapp:".$celular, // to 
        array( 
            "from" => "whatsapp:+14155238886",
            "mediaUrl" => ["http://postventa.mvevip.com/php/voucher/".$voucher],
            "body" => "Muchas gracias por su pago. Aqui se encuentra su recibo"
        ) 
    ); 

    return $message->sid;
}