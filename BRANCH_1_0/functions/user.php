<?php
/* 	
	Open Media Collectors Database
	Copyright (C) 2001,2006 by Jason Pell

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/

include_once("./functions/database.php");
include_once("./functions/logging.php");
include_once("./functions/utils.php");
include_once("./functions/address_type.php");

function get_user_types_rs($user_type_r, $checked_user_type=NULL)
{
	$checked = FALSE;
	
	for($i=0; $i<count($user_type_r); $i++)
	{
		$checked_ind = 'N';
		if(!$checked && $checked_user_type!==NULL && $user_type_r[$i] == $checked_user_type)
		{
			$checked_ind = 'Y';
			$checked = TRUE;
		}
		
		$user_type_rs[] = 
					array(
						'value'=>$user_type_r[$i], 
						'display'=>get_usertype_prompt($user_type_r[$i]),
						'description'=>get_usertype_description($user_type_r[$i]),
						'checked_ind'=>$checked_ind);
	}
	
	if(is_array($user_type_rs))
	{
		if(!$checked)
		{
			$user_type_rs[0]['checked_ind'] = 'Y';	
		}
		return $user_type_rs;
	}
	else// make sure we at least have an empty array.
	{
		return array();
	}
}

/**
	Return an array of owner types to be used as argument
	to fetch_user_rs(...) and fetch_user_cnt(...)
*/
function get_owner_user_types_r()
{
	return array('A','N');
}

/**
	Fetch users which can borrow items.
*/
function get_borrower_user_types_r()
{
	return array('A','N','B');
}

function get_changeuser_user_types_r()
{
	return array('N','B');
}

/**
	Fetch users which can borrow items.
*/
function get_review_user_types_r()
{
	return array('A','N','B');
}

function get_editinfo_user_types_r()
{
	return array('A', 'N', 'B');
}

function get_email_user_types_r()
{
	return array('A', 'N', 'B');
}

/**
* An array of all user type's - for use in User Type radio button grid widget
*/
function get_user_types_r()
{
	return array('A', 'N', 'B', 'G');
}

/**
* Validates that the $uid is of a user, which an administrator
* can change to.
*/
function is_user_changeuser($uid, $type=NULL)
{
	if(strlen($type)==0)
		$type = fetch_user_type($uid);
	
	if(in_array($type, get_changeuser_user_types_r()))
		return TRUE;
	else
		return FALSE;
}

/**
*/
function is_user_allowed_to_own($uid, $type=NULL)
{
	if(strlen($type)==0)
		$type = fetch_user_type($uid);
	
	if(in_array($type, get_owner_user_types_r()))
		return TRUE;
	else
		return FALSE;
}

/**
*/
function is_user_allowed_to_borrow($uid, $type=NULL)
{
	if(strlen($type)==0)
		$type = fetch_user_type($uid);
	
	if(in_array($type, get_borrower_user_types_r()))
		return TRUE;
	else
		return FALSE;
}

function is_user_allowed_to_review($uid, $type=NULL)
{
	if(strlen($type)==0)
		$type = fetch_user_type($uid);
	
	if(in_array($type, get_review_user_types_r()))
		return TRUE;
	else
		return FALSE;
}

function is_user_allowed_to_edit_info($uid, $type=NULL)
{
	if(strlen($type)==0)
		$type = fetch_user_type($uid);
	
	if(in_array($type, get_editinfo_user_types_r()))
		return TRUE;
	else
		return FALSE;
}

/*
* 
*/
function is_usertype_valid($usertype)
{
	if($usertype == 'N' || $usertype == 'A' || $usertype == 'B' || $usertype == 'G')
		return TRUE;
	else
		return FALSE;
}

function get_usertype_prompt($usertype)
{
	if($usertype == 'N')
		return get_opendb_lang_var('normal');
	else if($usertype == 'A')
		return get_opendb_lang_var('administrator');
	else if($usertype == 'B')
		return get_opendb_lang_var('borrower');
	else if($usertype == 'G')
		return get_opendb_lang_var('guest');
	else
		return get_opendb_lang_var('unknown');
}

function get_usertype_description($usertype)
{
	if($usertype == 'N')
		return get_opendb_lang_var('normal_usertype_description');
	else if($usertype == 'A')
		return get_opendb_lang_var('administrator_usertype_description');
	else if($usertype == 'B')
		return get_opendb_lang_var('borrower_usertype_description');
	else if($usertype == 'G')
		return get_opendb_lang_var('guest_usertype_description');
	else
		return NULL;
}

/*
* Specify a IN clause, of user_type's that the current
* user_type is of at least that level.  For instance a
* normal user qualifies, if the minimum user type is
* G, B or N
*/
function get_min_user_type_r($user_type)
{
	switch($user_type)
	{
		case 'G':
			return array('G');
		case 'B':
			return array('G','B');
		case 'N':
			return array('G','B','N');
		case 'A':
			return array('G','B','N','A');
		default:
			return array(''); // invalid usertype.
	}
}

/*
* Returns 	-1 if new_user_type is lesser than old_user_type
* 			0 If no change
* 			1 Iff new_user_type is better than old_user_type
*/
function user_type_cmp($old_user_type, $new_user_type)
{
	if($old_user_type == $new_user_type)
		return 0;
	else if($old_user_type == 'G')
	{
	 	if($new_user_type == 'B' || $new_user_type == 'N' || $new_user_type == 'A')
			return 1;
		else
			return -1; // should never happen.
	}
	else if($old_user_type == 'B')
	{
		if($new_user_type == 'N' || $new_user_type == 'A')
			return 1;
		else
			return -1;
	}
	else if($old_user_type == 'N')
	{
		if($new_user_type == 'A')
			return 1;
		else
			return -1;
	}
	else
		return FALSE;
}

/*
*/
function is_user_active($uid)
{
	$query = "SELECT active_ind FROM user WHERE user_id = '$uid'";
	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);

		if($found['active_ind'] == 'Y')
		{
			return TRUE;
		}
	}
	//else
	return FALSE;
}

/**
	Is this a new user
*/
function is_user_not_activated($uid)
{
	$query = "SELECT active_ind FROM user WHERE user_id = '$uid'";
	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);

		if($found['active_ind'] == 'X')
		{
			return TRUE;
		}
	}
	
	//else
	return FALSE;
}

/**
Are there any users awaiting activation.
*/
function is_exist_users_not_activated()
{
	$query = "SELECT 'X' FROM user WHERE active_ind = 'X'";
	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		db_free_result($result);
		return TRUE;
	}

	//else
	return FALSE;
}

//
// Will return true if $uid is an admin user.
//
function is_user_admin($uid, $type = NULL)
{
	if(strlen($type)==0)
		$type = fetch_user_type($uid);
	if ($type == 'A')
		return TRUE;
	else
		return FALSE;
}

//
// Will return true if $uid is an admin user.
//
function is_user_borrower($uid, $type = NULL)
{
	if(strlen($type)==0)
		$type = fetch_user_type($uid);
	if ($type == 'B')
		return TRUE;
	else
		return FALSE;
}

//
// Will return true if $uid is a guest user.
//
function is_user_guest($uid, $type = NULL)
{
	if(strlen($type)==0)
		$type = fetch_user_type($uid);
	if ($type == 'G')
		return TRUE;
	else
		return FALSE;
}

//
// Will return true if $uid is normal user.
//
function is_user_normal($uid, $type = NULL)
{
	if(strlen($type)==0)
		$type = fetch_user_type($uid);
	if ($type == 'N')
		return TRUE;
	else
		return FALSE;
}

/**
	Checks if uid actually exists.

	By default do not do case-insensitive check.  Generally $case=FALSE
	will be used in user_admin.php, to ensure two users with uid only
	differing in case are not created.  But because the rest of the logic
	in opendb does case-sensitive comparisons, we are going to do it here
	by default as well.
	
	$doUpperCheck	Do a case-sensitive comparison.  If false, the SQL query
					will have a UPPER added around user_id and $uid parameter.
*/
function is_user_valid($uid, $doUpperCheck=FALSE)
{
	// Do a pre-emptive check!
	if(strlen($uid)==0)
		return FALSE;
	
	// CASE SENSITIVE comparison by default.  This is the fastest
	// to execute as well.
	if($doUpperCheck === FALSE)
		$query = "SELECT 'x' FROM user WHERE user_id = '$uid'";
	else
		$query = "SELECT 'x' FROM user WHERE UPPER(user_id) = '".strtoupper($uid)."'";

	$result = db_query($query);
	if($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);
		return TRUE;
	}
	//else
	return FALSE;
}

//
// This will return the FULL NAME of the matching user id,
// or if this is empty, the $uid will be returned instead.
// It does not check that the uid is valid, too bad if it is not.
//
function fetch_user_name($uid)
{
	$query = "SELECT IF(LENGTH(fullname)>0,fullname,user_id) as fullname FROM user WHERE user_id = '$uid'";
	$result = db_query($query);
	if($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);
		return trim($found['fullname']);
	}
	//else
	return FALSE;
}

/**
* Since the EMAIL address is used throughout the system, a specialised
* abstraction of the user_address structure is required.  There is a general
* assumption that the EMAIL address will ALWAYS be present, and all current
* code generally assumes this.
*/
function fetch_user_email($uid)
{
	$email_attribute = get_opendb_config_var('email', 'user_address_attribute');
	if(strlen($email_attribute)>0)
	{
		$address_type_r = fetch_user_address_type_r($uid, 'EMAIL');
		if(is_not_empty_array($address_type_r))
		{
			$email_addr = fetch_user_address_attribute_val(
							$address_type_r['sequence_number'],
							$email_attribute);
		
			if($email_addr !== FALSE)
			{
				return $email_addr;
			}
			else
			{
				return FALSE;
			}
		}
		else
		{
			return FALSE;
		}
	}
	else
	{
		return FALSE;
	}
}

// returns the user's specified language
function fetch_user_language($uid)
{
	$query = "SELECT language FROM user WHERE user_id = '$uid'";
	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);
		return $found['language'];
	}
	//else
	return FALSE;
}

// returns the user's specified theme
function fetch_user_theme($uid)
{
	$query = "SELECT theme FROM user WHERE user_id = '$uid'";
	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);
		return $found['theme'];
	}
	//else
	return FALSE;
}

function fetch_user_type($uid)
{
	$query = "SELECT if(length(type)>0,type,'N') as type FROM user WHERE user_id = '$uid'";
	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);
		return $found['type'];
	}
	
	//else
	return FALSE;
}

//
// This returns the location of the user as a string.  Or FALSE if not found.
// 
function fetch_user_lastvisit($uid)
{
	$query = "SELECT lastvisit FROM user WHERE user_id = '$uid'";
	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);
		
		//print_r($found);
		return $found['lastvisit'];
	}
	//else
	return FALSE;
}

/**
	Return a resultset of all users, ordered by user_id
	user_id,fullname,type,lastvisit.  Will
	replace fullname with user_id if not defined. We only
	want to replace fullname with user_id if empty in this function
	because it is used for readonly operations.  The fetch_user_r
	is used to populate update forms, so the fullname must be 
	included with its current value, empty or not!

	@param $user_types		Specifies a list of usertypes to return in SQL statment.
							It is specified as an array.  Example: array('A','N','B','G')
							
							NOTE: If a set of user_types is specified, it is assumed that
							only ACTIVE user's should be returned, so a active_ind = 'Y'
							clause is appended to the WHERE.

	@param $active_ind      If not NULL, restrict to users with active_ind=$active_ind
	@param $order_by		Specify an order by column. Options are: user_id, fullname,
							location, type, email, lastvisit
* 	@param $exclude_user	A neat way to exclude one user from the list. Does not 
* 							currently support excluding more than one user.
*/
function fetch_user_rs($user_types=NULL, $active_ind=NULL, $order_by=NULL, $sortorder="ASC", $include_deactivated_users=FALSE, $exclude_user=NULL, $start_index=NULL, $items_per_page=NULL)
{
	// Uses the special 'zero' value lastvisit = 0 to test for default date value.
	$query = "SELECT user_id, active_ind, IF(LENGTH(fullname)>0,fullname,user_id) as fullname, IF(LENGTH(type)>0,type,'N') as type, language, theme, IF(lastvisit <> 0,UNIX_TIMESTAMP(lastvisit),'') as lastvisit FROM user";

	// List all users who can borrow records.
	$user_type_clause = format_sql_in_clause($user_types);
	if($user_type_clause != NULL)
	{
		$where_clause = "IF(LENGTH(type)>0,type,'N') IN($user_type_clause)";
	}

	if(strlen($exclude_user)>0)
	{
		if(strlen($where_clause)>0)
			$where_clause .= " AND ";
		$where_clause .= "user_id NOT IN ('$exclude_user')";
	}

	if($active_ind!=NULL)
	{
	    if(strlen($where_clause)>0)
			$where_clause .= " AND ";
		$where_clause .= "active_ind = '$active_ind'";
	}
	else if($include_deactivated_users !== TRUE)
	{
		if(strlen($where_clause)>0)
			$where_clause .= " AND ";
		$where_clause .= " active_ind = 'Y' ";
	}
	
	if(strlen($where_clause)>0)
		$query .= " WHERE $where_clause";
	
	// For simplicity sake!
	if(strlen($order_by)==0)
		$order_by = "fullname";

	if($order_by === "user_id")
		$query .= " ORDER BY user_id $sortorder";
	else if($order_by === "fullname")
		$query .= " ORDER BY fullname $sortorder, user_id";
	else if($order_by === "type")
		$query .= " ORDER BY type $sortorder, fullname, user_id";
	else if($order_by === "lastvisit")
		$query .= " ORDER BY lastvisit $sortorder, fullname, user_id";

	if(is_numeric($start_index) && is_numeric($items_per_page))
		$query .= ' LIMIT ' .$start_index. ', ' .$items_per_page;

	$result = db_query($query);
	if($result && db_num_rows($result)>0)
		return $result;
	else
		return FALSE;
}

/**
	Returns a count of items owned by the specified owner_id, or FALSE if no records.
	
	@param $UserTypes = array('A','N','B','G')
			NOTE: If a set of user_types is specified, it is assumed that
			only ACTIVE user's should be returned, so a active_ind = 'Y'
			clause is appended to the WHERE.

    @param $active_ind      If not NULL, restrict to users with active_ind=$active_ind
*/
function fetch_user_cnt($user_types=NULL, $active_ind=NULL, $include_deactivated_users=FALSE, $exclude_user=NULL)
{
	$query = "SELECT count(user_id) as count FROM user";

	// List all users who can borrow records.
	$user_type_clause = format_sql_in_clause($user_types);
	if($user_type_clause != NULL)
	{
		$where_clause = "IF(LENGTH(type)>0,type,'N') IN($user_type_clause)";
	}
	
	if(strlen($exclude_user)>0)
	{
		if(strlen($where_clause)>0)
			$where_clause .= " AND ";
		$where_clause .= "user_id NOT IN ('$exclude_user')";
	}
	
	if($active_ind!=NULL)
	{
	    if(strlen($where_clause)>0)
			$where_clause .= " AND ";
		$where_clause .= "active_ind = '$active_ind'";
	}
	else if($include_deactivated_users !== TRUE)
	{
		if(strlen($where_clause)>0)
			$where_clause .= " AND ";
		$where_clause .= " active_ind = 'Y' ";
	}
	
	if(strlen($where_clause)>0)
		$query .= " WHERE $where_clause";
	
	$result = db_query($query);
	if($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);
		if ($found!==FALSE)
			return $found['count'];
	}
	
	//else
	return FALSE;
}

//
// Returns a single user record.
// user_id,fullname,location,type,email,lastvisit
//
function fetch_user_r($uid)
{
	$query = "SELECT user_id, fullname, if(length(type)>0,type,'N') as type, language, theme, lastvisit FROM user where user_id = '".$uid."'";
	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);
		return $found;
	}
	//else
	return FALSE;
}

//
// Checks password to see if it matches.
//
function validate_user_passwd($uid, $pwd)
{
	$query = "SELECT pwd FROM user WHERE user_id = '$uid'";
	$result = db_query($query);
	if($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);

		if ($found && md5($pwd)==$found['pwd'])
			return TRUE;
	}

	//else
	return FALSE;
}

/*
*/
function deactivate_user($uid)
{
	$query= "UPDATE user SET active_ind = 'N' WHERE user_id = '$uid'";
	$update = db_query($query);

	if($update && db_affected_rows()>0)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($uid));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($uid));
		return FALSE;
	}
}

/*
*/
function activate_user($uid)
{
	$query= "UPDATE user SET active_ind = 'Y' WHERE user_id = '$uid'";
	$update = db_query($query);

	if($update && db_affected_rows()>0)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($uid));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($uid));
		return FALSE;
	}
}

//
// This function will not check if the original password matches before allowing a change, it simply
// makes the change.
//
function update_user_passwd($uid, $pwd)
{
	$query = "UPDATE user SET pwd = '".(strlen($pwd)>0?md5($pwd):"")."' WHERE user_id='$uid'";

	$update = db_query($query);
	// We should not treat updates that were not actually updated because value did not change as failures.
	$rows_affected = db_affected_rows();
	if ($rows_affected !== -1)
	{
		if($rows_affected>0)
			opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($uid, '*'));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($uid, '*'));
		return FALSE;
	}
}

//
// Will update the last visit value of the specified user.
//
function update_user_lastvisit($uid)
{
	$query= "UPDATE user SET lastvisit=now() WHERE user_id = '$uid'";
	$update = db_query($query);

	// Any failure to update this one should be treated as that, this includes a lastvisit
	// being set to the same value, which should not happen.
	if ($update && db_affected_rows()>0)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($uid));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($uid));
		return FALSE;
	}
}

//
// Will update the User type.
//
function update_user_type($uid, $type)
{
	if(strlen($type)==0)
		$type = "N"; //default!
		
	$query="UPDATE user SET type='".$type."' WHERE user_id='$uid'";
	$update = db_query($query);
	
	// We should not treat updates that were not actually updated because value did not change as failures.
	$rows_affected = db_affected_rows();
	if ($update && $rows_affected !== -1)
	{
		if($rows_affected>0)
			opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($uid, $type));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($uid, $type));
		return FALSE;
	}
}

/**
	Update User.  
	
		Specify FALSE for type to not attempt to update it.
		Specify FALSE to not update theme.
		Specify FALSE to not update language.
*/
function update_user($uid, $fullname, $language, $theme, $type)
{
	// Do not set to default if explicitly set to FALSE
	if($type!==FALSE && strlen($type)==0)
		$type = 'N';

	$query = "UPDATE user SET ".
					"fullname='".addslashes($fullname)."'".
					($language!==FALSE?", language='".addslashes($language)."'":"").
					($theme!==FALSE?", theme='".addslashes($theme)."'":"").
					($type!==FALSE?", type='$type'":"").
				" WHERE user_id = '$uid'";

	$update = db_query($query);
	// We should not treat updates that were not actually updated because value did not change as failures.
	$rows_affected = db_affected_rows();
	if ($update && $rows_affected !== -1)
	{
		if($rows_affected>0)
			opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($uid, $fullname, $language, $theme, $type));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($uid, $fullname, $language, $theme, $type));
		return FALSE;
	}
}

//
// Insert a New user.  Does not check if $uid exists already, before doing so.
// This relies on the user_id UNIQUE constraint.  The $uid is the updating user.
// Will do md5($pwd) before inserting...
//
function insert_user($uid, $fullname, $pwd, $type, $language, $theme, $active_ind='Y')
{
	if(strlen($type)==0)
		$type = "N"; //default!

	$query = "INSERT INTO user (user_id, fullname, pwd, type, language, theme, active_ind, lastvisit)".
				"VALUES('".$uid."',".
						"'".addslashes($fullname)."',".
						"'".md5($pwd)."',".
						"'".$type."',".
						"'".addslashes($language)."',".
						"'".addslashes($theme)."',".
						"'".$active_ind."',".
						"'0000-00-00 00:00:00')";

	$insert = db_query($query);
	if($insert && db_affected_rows()>0)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($uid, $fullname, '*', $type, $language, $theme, $active_ind));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($uid, $fullname, '*', $type, $language, $theme, $active_ind));
		return FALSE;
	}
}

//
// Delete user.  Assumes validation has already been performed.
//
function delete_user($uid)
{
	$query= "DELETE FROM user WHERE user_id = '$uid'";
	$delete = db_query($query);
	if (db_affected_rows()>0)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($uid));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($uid));
		return FALSE;
	}
}

//---------------------------------------------------
// Utilities
//---------------------------------------------------

// Randomly generates a password of $length characters
function generate_password($length)
{
	// length should be at minimum 4 characters
	if($length<4) $length=5;

	// seed random generator with system time
	srand((double)microtime()*1000000); 

	// make a string containing acceptable characters 
	$symbols = "abcdefghijklmnopqrstuvwxyz" 
		  ."ABCDEFGHIJKLMNOPQRSTUVWXYZ" 
                  ."0123456789"; 

	// loop for $length 
	for($ix = 0; $ix < $length; $ix++) 
	{ 
		// pick random symbol
		$randomNum   = rand(0,strlen($symbols)); 
		$randomChar  = substr($symbols,$randomNum,1); 

		// append random symbol to password
		$randomPass .= $randomChar; 
	} 

	// now returns our random password
	return $randomPass; 
}
?>
