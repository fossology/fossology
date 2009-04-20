<?php
/***
Email Forwarding plugin for SquirrelMail
----------------------------------------
Ritchie Low <rlow@xipware.com>
Ver 0.1, Jan 2001
***/

function squirrelmail_plugin_init_mail_fwd() {
  global $squirrelmail_plugin_hooks;
  global $mailbox, $imap_stream, $imapConnection;

  $squirrelmail_plugin_hooks["options_personal_save"]["mail_fwd"] = "mail_fwd_save_pref";
  $squirrelmail_plugin_hooks["loading_prefs"]["mail_fwd"] = "mail_fwd_load_pref";
  $squirrelmail_plugin_hooks["options_personal_inside"]["mail_fwd"] = "mail_fwd_inside";

}

function mail_fwd_inside() {
  global $username,$data_dir;
  global $mailfwd_user;
  global $color;
  ?>
      <tr>
        <td align=right>Forward Emails To:</td>
        <td><input type=text name=mfwd_user value="<?php echo "$mailfwd_user" ?>" size=30></td>
      </tr>
  <?php
}

function mail_fwd_load_pref() {
  global $username,$data_dir;
  global $mailfwd_user;

  $mailfwd_user = getPref($data_dir,$username,"mailfwd_user");
}

function mail_fwd_save_pref() {
  global $username,$data_dir;
  global $mfwd_user;

  if (isset($mfwd_user)) {
    setPref($data_dir,$username,"mailfwd_user",$mfwd_user);
  } else {
    setPref($data_dir,$username,"mailfwd_user","");
  }
  exec("/usr/sbin/wfwd ".$username." ".$mfwd_user);
}

?>
