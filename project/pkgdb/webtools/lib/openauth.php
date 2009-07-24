<?php

/******************************************************************
* Include these functions on every page that requires
* authentication. The best way to do this is to put them in a file
* and call require_once("filename.inc"); to include the
* functions. Then call auth(); on any page that you would like to
* authenticate. The auth() function writes a session variable upon
* success, so please be sure that a session is started first or
* replace $_SESSION['sea'] = $sea; with another action.
*****************************************************************/

// pempath is the location of the server private key
// for example:
// "file:///home/bobg/public_html/web/pi/bobgfc.openauth.pem"
function auth($pempath) {
  if(@!$_SESSION['sea']) {
    if((@!$_COOKIE['enc_sea']) && (@!$_COOKIE['env_key'])) {
      $ref = get_referrer();
      header("Location: https://hpopenauth.corp.hp.com/auth.php?ref=$ref");
    } else {
      $sea = read_authcookie($pempath);
      $_SESSION['sea'] = $sea;
    }
  }
}

function get_referrer() {
  $port = "";  
  if(@$_SERVER['HTTPS']) {
    $protocol = "https://";
  } else {
    $protocol = "http://";
  }
    
  $server_name = $_SERVER['SERVER_NAME'];

  if (($_SERVER['SERVER_PORT'] != "80") && ($_SERVER['SERVER_PORT'] != "443")) {
    $port = ":" . $_SERVER['SERVER_PORT'];
  }
    
  $ref = $protocol . $server_name . $port . getenv("REQUEST_URI");
  return $ref;
}

function read_authcookie($prv_key_file) {
  // Get the private key
  $prv_key = openssl_get_privatekey($prv_key_file);
  
  // Decode the cookies
  $enc_sea = base64_decode($_COOKIE['enc_sea']);
  $env_key = base64_decode($_COOKIE['env_key']);
  
  // Decrypt the data
  if (openssl_open($enc_sea, $open, $env_key, $prv_key)) {
    // Delete the encrypted cookies
    setcookie("enc_sea", "", time() - 86400, "/", ".hp.com");
    setcookie("env_key", "", time() - 86400, "/", ".hp.com");
    return $open;
  } else {
    die("<p style=\"color: red;\">Failed to encrypt data ($i). OpenSSL returned:</br>".openssl_error_string()."</p>");
  }
}

?>
