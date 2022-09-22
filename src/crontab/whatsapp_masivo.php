<?php 
require __DIR__.'/../../vendor/autoload.php';
// require __DIR__.'/../../src/config/mysql/meta.php';
require __DIR__.'/../../src/config/mysql/mysql.php';
require __DIR__.'/../../src/config/s3/s3class.php';
require __DIR__.'/../../src/config/files/filesclass.php';
require __DIR__.'/../../src/config/sri/index.php';
require __DIR__.'/../../src/config/twilio/whatsapp.php';
require __DIR__.'/../../src/config/functions/index.php';
require __DIR__.'/../../src/config/mail/mailer.php';
require __DIR__.'/../../src/config/placetopay/p2p.php';
require __DIR__.'/../../src/config/functions/auth.php';
require __DIR__.'/../../src/config/functions/valida_documentos.php';
require __DIR__.'/../../src/config/whatsapp/index.php';

$mysql = new Database("whatsapp_chats");
$whatsapp = new Whatsapp();

try{
    $hora_actual = date("Y-m-d H:i:s");    

    if ($hora_actual >= "2022-01-11 08:00:00"){
        $contacto = $mysql->Consulta("SELECT * FROM contactos_masivo WHERE (estado=0) ORDER BY id_contacto ASC LIMIT 3");

        if (is_array($contacto)){
            if (count($contacto) > 0){
                foreach ($contacto as $linea_contacto) {
                    $id_contacto = $linea_contacto['id_contacto'];
                    $celular = $linea_contacto['celular'];
                    
                    $mensaje = "⚠️❌𝗠𝗨𝗖𝗛𝗢 𝗖𝗨𝗜𝗗𝗔𝗗𝗢❌⚠️
                    \nActualmente existen varias empresas que están tomando nuestro nombre 😡😡para realizar cobros fraudulentos ❌💵❌por supuestas membresías y cancelaciones🤦🏻‍♂️.
                    \n▶️ @marketingvipecuador comunica a todos sus clientes y publico en general que 𝗡𝗢 tiene ningún vínculo comercial con ninguna empresa que realice cobros por afiliación o desvinculación de servicios.
                    \n❗️𝗤𝗨𝗘 𝗡𝗢 𝗧𝗘 𝗖𝗢𝗡𝗙𝗨𝗡𝗗𝗔𝗡❗️Nuestras transacciones son a través de Bancos locales y no solicitamos depósitos ni transferencias bancarias a cuentas personales.
                    \n✅ Nuestras cuentas bancarias están legalmente registradas a nombre de nuestra compañía y cada valor acreditado será respaldado por una factura electrónica🧾.
                    \nTenemos 1️⃣2️⃣ años de respaldo y garantía en el sector turístico y cumplimos con todos los requerimientos para ser la única Operadora Turística 100% ecuatoriana con clientes totalmente satisfechos.
                    \n💭🗯𝗥𝗘𝗖𝗨𝗘𝗥𝗗𝗔… nuestras transacciones registran en la cabecera del voucher  las palabras 𝗠𝗔𝗥𝗞𝗘𝗧𝗜𝗡𝗚 𝗩𝗜𝗣.
                    \nCualquier duda o inquietud, puedes contactarnos 📲a través de nuestros canales disponibles.";

                    $link = "https://apicrm.mvevip.com/tmp/noteconfundas.jpg";
                    // $archivo = file_get_contents(__DIR__."/../../public/tmp/noteconfundas.jpg");
                    // $link = "data:image/jpeg;base64,".base64_encode($archivo);
                    $filename = "noteconfundas.jpg";
                    
                    $envio = $whatsapp->sendfile2($celular, $link, $filename, $mensaje);
                    
                    if ($envio->sent){
                        $actualiza = $mysql->Modificar("UPDATE contactos_masivo SET idMessage=?, queueNumber=?, estado=? WHERE id_contacto=?", array($envio->id, $envio->queueNumber, 1, $id_contacto));                                               
                    }
                    sleep(3);
                }
            }
        }
    }
}catch(PDOException $e){
    echo $e->getMessage();
}