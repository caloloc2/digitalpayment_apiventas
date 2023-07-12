<?php 

$folder = __DIR__."/../../public/estab/1722653894001";
$url = "https://api.digitalpaymentnow.com/estab/1722653894001";

$listado = [];
$d = dir($folder);
while (false !== ($entry = $d->read())){
    if (!is_dir($entry) && ($entry != '.') && ($entry != '..')){
        // echo $entry."\n";
        array_push($listado, array(
            "link" => $url."/".$entry
        ));
    }
}
$d->close();
// print_r($listado);

echo json_encode($listado);