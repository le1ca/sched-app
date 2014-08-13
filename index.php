<?php

require_once 'include/functions.inc.php';
require_once 'classes/database_class.php';
require_once 'classes/session_class.php';

require_once 'sql_globals.inc.php';

// create objects
$database     = Database::Connect($sql_host, $sql_user, $sql_pass, $sql_db);
$user_session = Session::Initialize();
$content      = "view_cal";

if(isset($_GET['change_pw'])){
    if(isset($_POST['username'], $_POST['password_old'], $_POST['password_new'], $_POST['password_ver'])){
        $id = $database->check_user_password($_POST['username'], $_POST['password_old']);
        
        if($id != $user_session->get_id()){
            show_template("change_pw.html", array(
                "MESSAGE"  => "<p class='msg'>Error: Old password was incorrect.</p>",
                "USERNAME" => htmlspecialchars($user_session->get_name())
            ));
        }
        elseif($_POST['password_new'] != $_POST['password_ver']){
            show_template("change_pw.html", array(
                "MESSAGE" => "<p class='msg'>Error: New password and verify password did not match.</p>",
                "USERNAME" => htmlspecialchars($user_session->get_name())
            ));
        }
        else{
            $database->update_user_password($id, $_POST['password_new']);
            $_SESSION['message'] = "Your password has been updated.";
            header('location: ./');
            exit;
        }
    }
    else{
        show_template("change_pw.html", array(
            "USERNAME" => htmlspecialchars($user_session->get_name()),
            "MESSAGE" => ""
        ));
    }
}

?>
<!DOCTYPE html>
<html>

<head>

    <title>Proctor Scheduling</title>
    
    <link rel="stylesheet" type="text/css" href="http://cdnjs.cloudflare.com/ajax/libs/fullcalendar/2.0.2/fullcalendar.css"/>
    <link rel="stylesheet" type="text/css" href="./style.css"/>

    <script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
    <script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.4/jquery-ui.min.js"></script>
    <script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/moment.js/2.7.0/moment.min.js"></script>
    <script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/fullcalendar/2.0.2/fullcalendar.min.js"></script>
    <script type="text/javascript" src="./sched.js"></script>
    
</head>

<body>
    <div id="bg"></div>
    <?php
        switch($content){
            case 'view_cal':
                include "content/view_cal.inc.php";
                break;

            case 'default':
                include "content/default.inc.php";
                break;

            default:
                include "content/error.inc.php";
                break;
        }
    ?>
    
</body>
</html>
