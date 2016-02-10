<?php
/**
 * oc_adduser.php
 * 
 * This file import users from external mssql database to owncloud >= 8.0
 *
 * Long description for file (if any)...
 *
 * Tested on Ubuntu 14.04, PHP version 5.5.9, OwnCloud 8.2, SqlServer 2008 R2 Express w/ Advanced Services, freetds v0.91, TDS 4.2
 * Require FreeTDS for connecting to MSSSQL (mssql_connect will be deprecated on php 7.0), php5-curl
 * OwnCloud API: https://doc.owncloud.org/server/8.2/admin_manual/configuration_user/user_provisioning_api.html
 * 
 * @author Domenico Milano <d.milano@enasco.it>
 * @version 1.0 04/02/2016
 * 
 */

//****** Configuration *********************************************************************************************

// External database connection info
$myServer = "";
$myUser = "";
$myPass = "";
$myDB = "";
$myUsersTable = "";

// Table colums user info
$myUsersTableUsername = "";
$myUsersTablePassword = "";
$myUsersGroups = "";


//declare the SQL statement that will query the database
$query = "SELECT DISTINCT * ";
$query .= "FROM $myUsersTable ";
$query .= "WHERE 1=1 AND 1=1"; 
//$query .= "AND $myUsersGroups = 'D11'";

// Login Credentials as OwnCloud Admin
$ownHost = "192.168.1.1";
$ownAdminname = 'admin';
$ownAdminpassword = 'password';

$showReport = true; // Display report 

// **** OwnCloud API Configuration **********************************************************************************

// Add data, to owncloud post array
$userUrl = 'http://' . $ownAdminname . ':' . $ownAdminpassword . '@'.$ownHost.'/owncloud/ocs/v1.php/cloud/users';
$groupUrl ='http://' . $ownAdminname . ':' . $ownAdminpassword . '@'.$ownHost.'/owncloud/ocs/v1.php/cloud/groups';

// ******************************************************************************************************************

//connection to the database
$dbhandle = mssql_connect($myServer, $myUser, $myPass)
  or die("Couldn't connect to SQL Server on $myServer"); 

//select a database to work with
$selected = mssql_select_db($myDB, $dbhandle)
  or die("Couldn't open database $myDB"); 
 
//execute the SQL query and return records
$result = mssql_query($query);

//$numRows = mssql_num_rows($result); 
//echo "<h1>" . $numRows . " Risultat" . ($numRows == 1 ? "o" : "i") . "</h1>"; 

// Retrieve users from QNAP
// Todo: check difference of user list
/*
$userUrl = 'http://' . $ownAdminname . ':' . $ownAdminpassword . '@'.$ownHost.'/owncloud/ocs/v1.php/cloud/users';
$ch = curl_init($userUrl);
curl_setopt($ch, CURLOPT_GET);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$users = curl_exec($ch);
curl_close($ch);
*/

if ($showReport) echo "<h1>Import Users to ownCloud</h1>";

while($row = mssql_fetch_array($result))
{
	// Todo: Creation/Delete user action
	// $row["Attivo"] = 1 / 0
	
	$apiAction = "CreateUser";

	if ($apiAction == "CreateUser") {
		// Create user
		// -----------
		// Todo: check if user exists or users diff outside loop
		// Todo: update user password?
		$ownCloudArray = array('userid' => $row[$myUsersTableUsername], 'password' => $row[$myUsersTablePassword] );
		OwnCloud_Api($apiAction, $userUrl, $ownCloudArray, $showReport);

		// Create the group
		// ----------------
		// Syntax: ocs/v1.php/cloud/groups
		// Todo: check if group exists: use getgroups outside loop
		$ownCloudArray = array('groupid' => $row[$myUsersGroups]);
		OwnCloud_Api($apiAction, $groupUrl, $ownCloudArray, $showReport);

		// Add user to group
		// -----------------
		// Todo: check if user is in group
		// Todo: update user group?
		$ownCloudArray = array('groupid' => $row[$myUsersGroups]);
		$groupsUrl = $userUrl . '/' . $row[$myUsersTableUsername] . '/' . 'groups';
		OwnCloud_Api($apiAction, $groupsUrl, $ownCloudArray, $showReport);
	} else if ($apiAction == "DeleteUser") {
		// Delete user
		// -----------
		// Todo: check if user exists or users diff outside loop
		$ownCloudArray = array('userid' => $row[$myUsersTableUsername]);
		OwnCloud_Api($apiAction, $userUrl, $ownCloudArray, $showReport);
	}
}

//close the connection
mssql_close($dbhandle);

/**
* OwnCloud_Api
*
* @param string $action values: CreateUser, DeleteUser
* @param string $myurl OwnCloud api url
* @param array $arrayParam parameters passed to curl
* @param bool $report display report
*/
function OwnCloud_Api($action, $myurl, $arrayParam, $report = false) {
		
	$curl = curl_init();
	
	if ($action == "CreateUser") {
		curl_setopt($curl, CURLOPT_URL, $myurl);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $arrayParam);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);		
	} else if ($action == "DeleteUser") {
		curl_setopt($curl, CURLOPT_URL, $myurl. '/'. $arrayParam["userid"]);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	}
	$response = curl_exec($curl);
	curl_close($curl);

	// ***** WARNING **** Clear password
	// Todo: Add this on a filesystem log
	
	if ($report) {
		echo "<h3>$action</h3>";
		echo "<ul><li>";
		print_r($arrayParam);
		echo "</li>";
		echo "<li>Created URL is " . $myurl . "</li>";
		echo "<li>Response from curl :" . $response . "</li>";
		echo "</ul>";
	}
}

?>