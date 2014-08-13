<?php

require_once 'include/functions.inc.php';
require_once 'classes/database_class.php';
require_once 'classes/session_class.php';

require_once 'sql_globals.inc.php';

// create objects
$database     = Database::Connect($sql_host, $sql_user, $sql_pass, $sql_db);
$user_session = Session::Initialize();

function print_json($code, $error_or_data){

    if($code != 0){
        $data = json_encode($error_or_data);
        echo "{\"success\":".intval($code).", \"data\": $data}";
    }
    else{
        echo "{\"success\":0, \"error\":\"".addslashes($error_or_data)."\"}";
    }    
    
    exit;
}

if(!isset($_GET['method'])){
    print_json(0, "No method specified.");
}
else{
    switch($_GET['method']){
        case 'get_events':
            if(!isset($_GET['calid'])){
                print_json(0, "No calendar ID was sent.");
            }

            $dat = array();
            $dat['events']  = $user_session->get_events($_GET['calid']);
            $dat['title']   = $database->get_calendar_name($_GET['calid']);
            $dat['mutable'] = $database->calendar_is_mutable($_GET['calid']);
            
            print_json(1, $dat);
            break;
            
        case 'post_events':
            if(!isset($_POST['events'])){
                print_json(0, "No event array was sent.");
            }

            if(!isset($_GET['calid'])){
                print_json(0, "No calendar ID was sent.");
            }

            if(!$database->calendar_is_mutable($_GET['calid'])){
                print_json(0, "Attempted to commit changes to immutable calendar.");
            }
            
            $j = json_decode($_POST['events'], true);
            
            if($j == null){
                print_json(0, "JSON decoding error.");
            }
            
            $user_session->clear_events($_GET['calid']);
            foreach($j['events'] as $e){
                $user_session->add_event($_GET['calid'], $e['type'], $e['start'], $e['end']);
            }
            print_json(1, array());
            
            exit;
            break;
            
        default:
            print_json(0, "Invalid method specified.");
            break;
    }
}

?>
