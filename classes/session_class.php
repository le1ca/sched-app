<?php

class SessionException extends Exception {
    public function __construct($msg){
        parent::__construct($msg);
    }
}

class Session {

    private static $instance;
    
    private $authorized;
    private $user_id;
    private $username;
    private $calendar_id;
    private $last_update;
    private $hour_limit;
    private $backup_user;
    private $admin;
    
    private $database;

    public static function Initialize(){
        if(Session::$instance != null)
            throw new SessionException("A Session instance already exists");
        else
            Session::$instance = new Session();
            
        return Session::$instance;
    }

    public static function GetInstance(){
        if(Session::$instance == null)
            throw new SessionException("No Session instance exists");
        return Session::$instance;
    }

    private function __construct(){

        $cookie_path = dirname($_SERVER['SCRIPT_NAME']);
        session_set_cookie_params(3600, $cookie_path, $_SERVER['HTTP_HOST']);
        session_name("procsched_session");
        session_start();
    
        $this->database = Database::GetInstance();
        
        $this->authorized = false;

        if(isset($_GET['logout'])){
            unset($_SESSION['user_authed']);
            unset($_SESSION['user_id']);
            session_destroy();
            show_template("login.html", array("MESSAGE" => "<p class='msg'>You are now logged out.</p>"));
        }

        // ask user to log in
        if(!isset($_SESSION['user_authed'], $_SESSION['user_id']) && !isset($_POST['username'], $_POST['password'])) {
            show_template("login.html", array("MESSAGE" => ""));
        }
        // verify credentials
        elseif(isset($_POST['username'], $_POST['password'])){
            $user = $_POST['username'];
            $pass = $_POST['password'];

            $this->name = $user;
            $id = $this->database->check_user_password($user, $pass);

            if($id === FALSE){
                show_template("login.html", array("MESSAGE" => "<p class='msg'>Invalid username or password.</p>"));
            }
            else{
                $this->get_user_data($id);
            }
        }
        // already have an auth cookie
        else{
            $this->get_user_data($_SESSION['user_id']);
        }
    }

    private function get_user_data($id){
        $this->authorized = true;

        $data = $this->database->get_user_data($id);

        list( $this->user_id,
              $this->username,
              $this->calendar_id,
              $this->last_update,
              $this->hour_limit,
              $this->backup_user,
              $this->admin
            ) = $data;

        $_SESSION['user_authed'] = true;
        $_SESSION['user_id'] = $this->user_id;
    }

    public function update_user(){
        $data = array($this->calendar_id, $this->last_update, $this->hour_limit, $this->backup_user, $this->admin);
        $this->database->update_user($this->user_id, $data);
    }

    public function get_name(){
        return $this->username;
    }

    public function get_id(){
        return $this->user_id;
    }

    public function get_calendar_id(){
        return $this->calendar_id;
    }

    public function set_calendar_id($id){
        $this->calendar_id = $id;
    }

    public function get_events(){
        return $this->database->get_events($this->user_id);
    }

    public function clear_events(){
        return $this->database->clear_events($this->user_id);
    }

    public function add_event($type, $start, $end){
        return $this->database->add_event($this->user_id, $type, $start, $end);
    }

}

?>
