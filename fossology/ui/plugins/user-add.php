<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/
/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) {
	exit;
}

class user_add extends FO_Plugin {
	var $Name = "user_add";
	var $Title = "Add A User";
	var $MenuList = "Admin::Users::Add";
	var $Version = "1.0";
	var $Dependency = array("db");
	var $DBaccess = PLUGIN_DB_USERADMIN;

	/*********************************************
	 Add(): Add a user.
	 Returns NULL on success, string on failure.
	 *********************************************/
	function Add() {
			
		global $DB;
		global $PG_CONN;

		if (!$PG_CONN) {
			$dbok = $DB->db_init();
			if (!$dbok)
$text = _("NO DB connection!\n");
			echo "<pre>$text</pre>";
		}

		/* Get the parameters */
		$User = str_replace("'", "''", GetParm('username', PARM_TEXT));
		$Pass = GetParm('pass1', PARM_TEXT);
		$Pass2 = GetParm('pass2', PARM_TEXT);
		$Seed = rand() . rand();
		$Hash = sha1($Seed . $Pass);
		$Desc = str_replace("'", "''", GetParm('description', PARM_TEXT));
		$Perm = GetParm('permission', PARM_INTEGER);
		$Folder = GetParm('folder', PARM_INTEGER);
		$Email_notify = GetParm('enote', PARM_TEXT);
		$Email = str_replace("'", "''", GetParm('email', PARM_TEXT));
		$agentList = userAgents();
		$default_bucketpool_fk = GetParm('default_bucketpool_fk', PARM_INTEGER);

		/* debug 
		print "<pre>";
		print "UserAddDB: User is:$User\n";
		print "UserAddDB: Desc is:$Desc\n";
		print "UserAddDB: Pass is:$Pass\n";
		print "UserAddDB: Pass2 is:$Pass2\n";
		print "UserAddDB: Seed is:$Seed\n";
		print "UserAddDB: Hash is:$Hash\n";
		print "UserAddDB: Perm is:$Perm\n";
		print "UserAddDB: Email is:$Email\n";
		print "UserAddDB: EM_notify is:$Email_notify\n";
		print "UserAddDB: agent list is:$agentList\n";
		print "UserAddDB: folder is:$Folder\n";
		print "UserAddDB: default_bucket_pool is:$default_bucketpool_fk\n";
		print "</pre>";
		*/


		/* Make sure username looks valid */
		if (empty($User)) {
			return ("Username must be specified. Not added.");
		}
		/* Make sure password matches */
		if ($Pass != $Pass2) {
			return ("Passwords did not match. Not added.");
		}
		/* Make sure email looks valid */
		$Check = preg_replace("/[^a-zA-Z0-9@_.+-]/", "", $Email);
		if ($Check != $Email) {
			return ("Invalid email address.  Not added.");
		}
		/* See if the user already exists (better not!) */
		$SQL = "SELECT * FROM users WHERE user_name = '$User' LIMIT 1;";
		$Results = $DB->Action($SQL);
		if (!empty($Results[0]['user_name'])) {
			return ("User already exists.  Not added.");
		}

		/* check email notification, if empty (box not checked), or if no email
		 * specified for the user set to 'n'.
		 */
		if(empty($Email_notify)) {
			$Email_notify = '';
		}
		elseif(empty($Email)) {
			$Email_notify = '';
		}

		/* Add the user */
		if($defult_bucketpool_fk === NULL) {
			$VALUES = " VALUES ('$User','$Desc','$Seed','$Hash',$Perm,'$Email',
	             '$Email_notify','$agentList',$Folder, NULL);";
		}
		else {
			$VALUES = " VALUES ('$User','$Desc','$Seed','$Hash',$Perm,'$Email',
	             '$Email_notify','$agentList',$Folder, $default_bucketpool_fk);";
		}
		$SQL = "INSERT INTO users
      (user_name,user_desc,user_seed,user_pass,user_perm,user_email,
       email_notify,user_agent_list,root_folder_fk, default_bucketpool_fk) 
	      $VALUES";
$text = _("SQL is:\n$SQL\n");
		//print "<pre>$text</pre>";
		
		$Results = pg_query($PG_CONN, $SQL);
		DBCheckResult($Results, $sql, __FILE__, __LINE__);
		/* Make sure it was added */
		$SQL = "SELECT * FROM users WHERE user_name = '$User' LIMIT 1;";
		$Results = $DB->Action($SQL);
		if (empty($Results[0]['user_name'])) {
			return ("Failed to insert user.");
		}
		return (NULL);
	} // Add()
	/*********************************************
	Output(): Generate the text for this plugin.
	*********************************************/
	function Output() {
		if ($this->State != PLUGIN_STATE_READY) {
			return;
		}
		global $DB;
		$V = "";
		switch ($this->OutputType) {
			case "XML":
				break;
			case "HTML":
				/* If this is a POST, then process the request. */
				$User = GetParm('username', PARM_TEXT);
				if (!empty($User)) {
					$rc = $this->Add();
					if (empty($rc)) {
						/* Need to refresh the screen */
						$V.= displayMessage("User $User added.");
					} else {
						$V.= displayMessage($rc);
					}
				}
				/* Build HTML form */
				$V.= "<form name='formy' method='POST'>\n"; // no url = this url
				$V.= "To create a new user, enter the following information:<P />\n";
				$Style = "<tr><td colspan=3 style='background:black;'></td></tr><tr>";
				$V.= "<table style='border:1px solid black; text-align:left; background:lightyellow;' width='75%'>";
				$Val = htmlentities(GetParm('username', PARM_TEXT), ENT_QUOTES);
$text = _("1.");
$text1 = _("Enter the username.");
				$V.= "$Style<th width='5%'>$text</th><th width='25%'>$text1</th>";
				$V.= "<td><input type='text' value='$Val' name='username' size=20></td>\n";
				$V.= "</tr>\n";
				$Val = htmlentities(GetParm('description', PARM_TEXT), ENT_QUOTES);
$text = _("2.");
$text1 = _("Enter a description for the user (name, contact, or other information).  This may be blank.");
				$V.= "$Style<th>$text</th><th>$text1</th>\n";
				$V.= "<td><input type='text' name='description' value='$Val' size=60></td>\n";
				$V.= "</tr>\n";
				$Val = htmlentities(GetParm('email', PARM_TEXT), ENT_QUOTES);
$text = _("3.");
$text1 = _("Enter an email address for the user, see step 8. This field may be left blank.");
				$V .= "$Style<th>$text</th><th>$text1</th>\n";
				$V.= "<td><input type='text' name='email' value='$Val' size=60></td>\n";
				$V.= "</tr>\n";
$text = _("4.");
$text1 = _("Select the user's access level.");
				$V.= "$Style<th>$text</th><th>$text1</th>";
				$V.= "<td><select name='permission'>\n";
				$V.= "<option value='" . PLUGIN_DB_NONE . "'>None (very basic, no database access)</option>\n";
				$V.= "<option selected value='" . PLUGIN_DB_READ . "'>Read-only (read, but no writes or downloads)</option>\n";
				$V.= "<option value='" . PLUGIN_DB_DOWNLOAD . "'>Download (Read-only, but can download files)</option>\n";
				$V.= "<option value='" . PLUGIN_DB_WRITE . "'>Read-Write (read, download, or edit information)</option>\n";
				$V.= "<option value='" . PLUGIN_DB_UPLOAD . "'>Upload (read-write, and permits uploading files)</option>\n";
				$V.= "<option value='" . PLUGIN_DB_ANALYZE . "'>Analyze (... and permits scheduling analysis tasks)</option>\n";
				$V.= "<option value='" . PLUGIN_DB_DELETE . "'>Delete (... and permits deleting uploaded files and analysis)</option>\n";
				$V.= "<option value='" . PLUGIN_DB_DEBUG . "'>Debug (... and allows access to debugging functions)</option>\n";
				$V.= "<option value='" . PLUGIN_DB_USERADMIN . "'>Full Administrator (all access including adding and deleting users)</option>\n";
				$V.= "</select></td>\n";
				$V.= "</tr>\n";
$text = _("5.");
$text1 = _("Select the user's top-level folder. Access is restricted to this folder.");
				$V.= "$Style<th>$text</th><th>$text1";
				$V.= " (NOTE: This is only partially implemented right now. Current users can escape the top of tree limitation.)";
				$V.= "</th>";
				$V.= "<td><select name='folder'>";
				$V.= FolderListOption(-1, 0);
				$V.= "</select></td>\n";
				$V.= "</tr>\n";
$text = _("6.");
$text1 = _("Enter the user's password.  It may be blank.");
				$V.= "$Style<th>$text</th><th>$text1</th><td><input type='password' name='pass1' size=20></td>\n";
				$V.= "</tr>\n";
$text = _("7.");
$text1 = _("Re-enter the user's password.");
				$V.= "$Style<th>$text</th><th>$text1</th><td><input type='password' name='pass2' size=20></td>\n";
				$V.= "</tr>\n";
$text = _("8.");
$text1 = _("E-mail Notification");
				$V .= "$Style<th>$text</th><th>$text1</th><td><input type='checkbox'";
                "name='enote' value='y' checked='checked'>" .
                "Check to enable email notification of completed analysis.</td>\n";
				$V.= "</tr>\n";
$text = _("9.");
$text1 = _("Default Agents: Select the ");
				$V .= "$Style<th>$text</th><th>$text1";
              "agent(s) to automatically run when uploading data. These" .
$text = _(" ");
              " selections can be changed on the upload screens.\n</th><td>$text";
				$V.= AgentCheckBoxMake(-1, "agent_unpack");
				$V .= "</td>\n";
$text = _("10.");
$text1 = _("Default bucketpool.");
				$V.= "$Style<th>$text</th><th>$text1</th>";
				$V.= "<td>";
				$V.= SelectBucketPool($default_bucketpool_fk);
				$V.= "</td>";
				$V .= "</tr>\n";
				$V.= "</table border=0><P />";
				$V.= "<input type='submit' value='Add!'>\n";
				$V.= "</form>\n";
				break;
			case "Text":
				break;
			default:
				break;
		}
		if (!$this->OutputToStdout) {
			return ($V);
		}
		print ("$V");
		return;
	}
};
$NewPlugin = new user_add;
?>
