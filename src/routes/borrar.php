<?php 

$folder = __DIR__."/../../public/estab/1722653894001";

$d = dir($folder);

while (false !== ($entry = $d->read())){
    if (is_dir($entry) && ($entry != '.') && ($entry != '..'))
        echo $entry."\n";
}
$d->close();
// print_r($listado);