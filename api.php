<?php

//use Rest.inc.php;
require_once('Rest.inc.php');

class API extends REST {

    public $data = '';

    //Enter details of your database
    const DB_SERVER = 'localhost';
    const DB_USER = 'helb';
    const DB_PASSWORD = 'R5epmQnFG4XR4NhG';
    const DB = 'helb_webdata';
    const DB_USER_DEV = 'root';
    const DB_PASSWORD_DEV = 'we@ss';
    const DB_DEV = 'helb_api';

    private $db = null;

    public function __construct() {
        parent::__construct();    // Init parent contructor
        $this->dbConnect();          // Initiate Database connection
    }

    private function dbConnect() {
        $production = $_SERVER['HTTP_HOST']=='portal.helb.co.ke';
        
        $this->db = mysqli_connect(self::DB_SERVER, $production ? self::DB_USER : self::DB_USER_DEV, $production ? self::DB_PASSWORD : self::DB_PASSWORD_DEV) or die("Couldn't make connection: ". mysql_error());
        if ($this->db)
            mysqli_select_db($this->db, $production ? self::DB : self::DB_DEV) or die("Couldn't select database: ". mysql_error());
    }

    /**
     * Public method for access api
     * This method dynamically call the method based on the query string
     *
     */
    public function processApi() {
        $func = isset($_REQUEST['rquest']) ? strtolower(trim(str_replace('/', '', $_REQUEST['rquest']))) : '';
        
        if ((int) method_exists($this, $func) > 0)
            $this->$func();
        else
            $this->response('Error code 404 e, Page not found', 404); // If the method not exist with in this class, response would be 'Page not found'.
    }

    /**
     * confirm if one is a loanee
     */
    private function confirmLoanee() {
        $check_sql = mysqli_query($this->db, "
                select y.fname, y.mname, y.lname, x.loanee, x.loan_balance, if(x.loanee > 0, '11032666314', '1104823047') as account_no from

                (select id_no, count(loan_balance) > 0 as loanee, sum(loan_balance) as loan_balance from tbl_beneficiaries where id_no = '$_REQUEST[id]') as x

                left join

                (select id_no, first_name as fname, mid_name as mname, last_name as lname from tbl_users_applicants where id_no = '$_REQUEST[id]') as y

                on x.id_no = y.id_no
                ");

        $sql_check_data = mysqli_fetch_assoc($check_sql);
        
        echo $this->json($sql_check_data);
    }

    /**
     * confirm if one is a loanee
     * verify slip
     * generate auth_key for slip if necessary
     */
    private function verifySlip() {
        $sha = sha1(rand(1000, 9999) . time());
        
        mysqli_query($this->db, "update tbl_eslips set slip_status = 'expired', updated_at = now() where eslipno = '$_REQUEST[slipno]' && slip_status not in ('paid', expired') && expire_at < now()");

        mysqli_query($this->db, "update tbl_eslips set auth_key = '$sha' where eslipno = '$_REQUEST[slipno]' && slip_status = 'valid' && expire_at >= now()");

        $sql_check = mysqli_query($this->db, "select amount_to_pay, user_id, id_no from tbl_eslips where eslipno = '$_REQUEST[slipno]'");

        $sql_confirm = mysqli_fetch_assoc($sql_check);
        
        $check_sql = mysqli_query($this->db, "
                select
                
                y.fname, y.mname, y.lname, x.id_no, x.loanee, z.eslipno, z.initiated_at, z.amount_to_pay, z.expire_at, z.slip_status, z.slip_purpose, z.user_type, z.auth_key, if(x.loanee > 0, '11032666314', '1104823047') as account_no,
                
                z.expire_at >= now() as valid_check, z.auth_key = '$sha' as payable, z.paid
                
                from
                
                (select eslipno, user_id, initiated_at, amount_to_pay, expire_at, slip_status, slip_purpose, user_type, auth_key, amount_to_pay = amount_paid && slip_status = 'paid' as paid from tbl_eslips where eslipno = '$_REQUEST[slipno]') as z

                left join

                (select id, id_no, first_name as fname, mid_name as mname, last_name as lname from tbl_users_applicants where id = '$sql_confirm[user_id]') as y on z.user_id = y.id

                left join
                
                (select id_no, count(loan_balance) > 0 as loanee, sum(loan_balance) as loan_balance from tbl_beneficiaries where id_no = '$sql_confirm[id_no]') as x on y.id_no = x.id_no
                ");

        $sql_check_data = mysqli_fetch_assoc($check_sql);

        echo $this->json($sql_check_data);
    }

    /**
     * capture payment by auth_key
     * generate new auth_key for slip if necessary
     */
    private function payForSlip() {
        $response_at = date('Y-m-d H:i:s');

        $sha = sha1(rand(1000, 9999) . time());

        mysqli_query($this->db,$str =  "update tbl_eslips set slip_status = 'paid', eslipno2 = '$_REQUEST[slipno]', id_no = '$_REQUEST[id]', name = '$_REQUEST[name]', amount_paid = '$_REQUEST[amount_paid]', transaction_no = '$_REQUEST[transaction_no]', transaction_date = '$_REQUEST[transaction_date]', payment_mode = '$_REQUEST[payment_mode]', account_no = '$_REQUEST[account_no]', response_at = '$response_at', updated_at = now(), auth_key = '$sha' where auth_key = '$_REQUEST[auth_key]' && slip_status in ('valid', 'expired')");
        
        $this->verifySlip();
    }

    /**
     * 	Encode array into JSON
     */
    private function json($data) {
        return is_array($data) ? json_encode($data) : $data;
    }

    /**
     * 	Decode JSON into array
     */
    private function jsonDecode($data) {
        return is_array($data) ? $data : json_decode($data);
    }

}

// Initiiate Library

$api = new API;

$api->processApi();
