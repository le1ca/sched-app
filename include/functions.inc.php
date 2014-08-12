<?php

function show_template($file, $repl){
    $data = file_get_contents("static/$file");
    foreach($repl as $i=>$v){
        $data = str_replace("%$i%", $v, $data);
    }
    echo $data;
    exit;
}

?>
