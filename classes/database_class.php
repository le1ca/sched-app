<?php

class DatabaseException extends Exception {
    public function __construct($msg){
        parent::__construct($msg);
    }
}

class Database {

    private $mysqli;
    private static $instance;

    public static function Connect($host, $user, $pass, $db){
        if(Database::$instance != null)
            throw new DatabaseException("A Database instance already exists");
        else
            Database::$instance = new Database($host, $user, $pass, $db);
            
        return Database::$instance;
    }

    public static function GetInstance(){
        if(Database::$instance == null)
            throw new DatabaseException("No Database instance exists");
        return Database::$instance;
    }

    private function __construct($host, $user, $pass, $db){
        $this->mysqli = mysqli_connect($host, $user, $pass, $db);

        if($this->mysqli->connect_errno)
            throw new DatabaseException("Failed to connect to database");
    }

    public function check_user_password($username, $password){
        $q = $this->mysqli->query("SELECT `pw_salt`,`pw_hash`,`id` FROM `users` WHERE `username` = '" . $this->escape($username) . "'");

        if(!$q->num_rows)
            return false;

        $r = $q->fetch_assoc();

        if(hash("sha256", $r['pw_salt'].$password) != $r['pw_hash'])
            return false;

        return $r['id'];
    }

    public function update_user_password($userid, $password){
        $salt = bin2hex(openssl_random_pseudo_bytes(4));
        $hash = hash("sha256", $salt.$password);
        $this->mysqli->query("UPDATE `users` SET `pw_salt`='".$this->escape($salt)."', `pw_hash`='$hash' WHERE `id`='".intval($userid)."'");
    }

    public function get_user_data($user_id){
        $q = $this->mysqli->query("SELECT * FROM `users` WHERE `id` = '" . $this->escape($user_id) . "'");

        if(!$q->num_rows)
            return false;

        $r = $q->fetch_assoc();
    
        if($r['last_update'] == '')
            $r['last_update'] = '1000-01-01 00:00:00';

        if($r['hour_limit'] == '')
            $r['hour_limit'] = 0;

        if($r['backup_user'] == '')
            $r['backup_user'] = 0;

        if($r['admin'] == '')
            $r['admin'] = 0;

        return array($r['id'],
                     $r['username'],
                     $r['calendar_id'],
                     $r['last_update'],
                     $r['hour_limit'],
                     $r['backup_user'],
                     $r['admin']
                    );
    }

    public function update_user($id, $data){
        $q = $this->mysqli->query("UPDATE `users` SET ".
                                    //"`calendar_id` = '" . $this->escape($data[0]) . "', " .
                                    "`last_update` = '" . $this->escape($data[1]) . "', " .
                                    "`hour_limit`  = '" . $this->escape($data[2]) . "', " .
                                    "`backup_user` = '" . $this->escape($data[3]) . "', " .
                                    "`admin`       = '" . $this->escape($data[4]) . "'  " .
                                  "WHERE `id` = '" . $this->escape($id) . "'"
                                 );
        
        if($q !== TRUE)
            throw new DatabaseException("Could not update data for user #$id in Database");
    }

    public function get_calendar_list(){
        $a = array();
        $q = $this->mysqli->query("SELECT * FROM `calendars`");

        while($r = $q->fetch_assoc()){
            $a[] = $r;
        }

        return $a;
    }

    public function get_events($uid, $cid){
        $a = array();
        $m = $this->calendar_is_mutable($cid);
        $q = $this->mysqli->query("SELECT `type`,`start`,`end`,`mutable` FROM `events` WHERE `user_id` = '".$this->escape($uid)."' AND  `cal_id` = '".$this->escape($cid)."'");

        if($q === FALSE)
            return false;

        while($r = $q->fetch_assoc()){
            if(!$m)
                $r['mutable'] = 0;
            $a[] = $r;
        }

        return $a;
    }

    public function clear_events($uid, $cid){
        $this->mysqli->query("DELETE FROM `events` WHERE `user_id` = '".$this->escape($uid)."' AND `cal_id` = '".$this->escape($cid)."'");
    }

    public function add_event($uid, $cid, $type, $start, $end){
        $this->mysqli->query("INSERT INTO `events` (`user_id`, `cal_id`, `type`, `start`, `end`) ".
                             "VALUES ('".$this->escape($uid)."', '".$this->escape($cid)."', '".$this->escape($type)."', '".$this->escape($start)."', '".$this->escape($end)."')"
                            );
    }

    public function calendar_is_mutable($cid){
        $q = $this->mysqli->query("SELECT `mutable` FROM `calendars` WHERE `cal_id` = '".$this->escape($cid)."'");
        $r = $q->fetch_array();
        return $r[0];
    }

    public function get_calendar_name($cid){
        $q = $this->mysqli->query("SELECT `name` FROM `calendars` WHERE `cal_id` = '".$this->escape($cid)."'");
        $r = $q->fetch_array();
        return $r[0];
    }

    private function escape($str){
        return $this->mysqli->real_escape_string($str);
    }

    public function error(){
        return $this->mysqli->error;
    }


}
    
?>
