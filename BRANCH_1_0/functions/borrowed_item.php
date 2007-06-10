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

// This file contains functions for manipulating the borrowed_item table.
// including viewing and updating details.
include_once("./functions/user.php");
include_once("./functions/item.php");
include_once("./functions/utils.php");
include_once("./functions/database.php");
include_once("./functions/logging.php");
include_once("./functions/datetime.php");

/**
	Will split up a 'item_id_instance_no' value into
	its component item_id / instance_no or return FALSE.

	Format of input parameter:
		{item_id}_{instance_no}
*/
function get_item_id_and_instance_no($item_id_instance_no)
{
	if(strlen($item_id_instance_no)>0)
	{
		$splitidx = strpos($item_id_instance_no, '_');
    	if($splitidx !== FALSE)
		{
			$item_id = substr($item_id_instance_no,0,$splitidx);
			$instance_no = substr($item_id_instance_no,$splitidx+1);
			if(is_numeric($item_id) && is_numeric($instance_no))
				return array('item_id'=>$item_id,'instance_no'=>$instance_no);
		}
	}

	//else
	return FALSE;
}

/**
	The only status's that are valid for allowing a user access to the borrowed item
	or auxiliary details, such as the address of the other party are B and R.  
	
	There are certain rules in effect for which of the borrower or owner is the active
	party against the borrowed item record at any time. If the item is reserved, then 
	the uid must correspond to the owner.  If the item is borrowed, then the uid must 
	correspond to the borrower.
	
	@param $sequence_number sequence number of the borrowed item record
	@param $current_user    user checking access for
	@param $other_user		double check to ensure that in any case, the other user attached to this
	borrowed item is that specified.  This is a time saving measure
*/
function is_borrowed_item_accessible_to_user($sequence_number, $current_user, $other_user = NULL)
{
	$borrowed_item_r = fetch_borrowed_item_r($sequence_number);
	if(is_array($borrowed_item_r) && ($borrowed_item_r['status'] == 'R' || $borrowed_item_r['status'] == 'B'))
	{
		if($borrowed_item_r['status'] == 'R') // need to be able to checkout item to borrower
		{
			if($borrowed_item_r['borrower_id'] == $other_user) 
			{
				if($other_user == NULL || is_borrowed_item_owner($sequence_number, $current_user))
					return TRUE;
			}
		}
		else if($borrowed_item_r['status'] == 'B') // need to be able to return item to owner
		{
			if($borrowed_item_r['borrower_id'] == $current_user)
			{
				if($other_user == NULL || is_borrowed_item_owner($sequence_number, $other_user))
					return TRUE;
			}
		}
	}

	//else
	return FALSE;
}

function is_borrowed_item_owner($sequence_number, $owner_id)
{
	$item_r = fetch_borrowed_item_pk_r($sequence_number);
	if(is_array($item_r))
	{
		if(is_user_owner_of_item($item_r['item_id'], $item_r['instance_no'], $owner_id))
		{
			return TRUE;
		}
	}
	
	//else
	return FALSE;
}

/**
	Format order_by clause for borrow.php resultset functions
*/
function get_orderby_clause($order_by, $sortorder)
{
	if($order_by === "s_item_type")
		return "order by i.s_item_type $sortorder";
	else if($order_by === "title")
		return "order by i.title $sortorder";  
	else if($order_by === "borrower")
		return "order by bi.borrower_id $sortorder";
	else if($order_by === "owner")
		return "order by ii.owner_id $sortorder";
	else if($order_by === "update_on" || strlen($order_by)==0)
		return "ORDER BY bi.update_on $sortorder";//no order by!
	else 
		return "ORDER BY $order_by $sortorder";
}

//
// Count of total items borrowed
//
function fetch_all_borrowed_item_cnt()
{
	$query = "SELECT count('x') AS count FROM borrowed_item bi ".
			"WHERE bi.status = 'B'";

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

function fetch_all_borrowed_item_rs($order_by, $sortorder, $start_index=NULL, $items_per_page=NULL)
{
	$query = "SELECT bi.sequence_number, bi.item_id, bi.instance_no, UNIX_TIMESTAMP(DATE_ADD(bi.update_on, INTERVAL bi.borrow_duration DAY)) as due_date, bi.borrower_id, i.s_item_type, i.title, ii.owner_id, ii.s_status_type ".
				"FROM item i, item_instance ii, borrowed_item bi ".
				"WHERE i.id = ii.item_id AND ii.instance_no = bi.instance_no AND bi.item_id = ii.item_id AND ".
				"bi.status = 'B' ".
				get_orderby_clause($order_by, $sortorder);

	if(is_numeric($start_index) && is_numeric($items_per_page))
		$query .= ' LIMIT ' .$start_index. ', ' .$items_per_page;

	$result = db_query($query);
	if($result && db_num_rows($result)>0)
		return $result;
	else
		return FALSE;
}

//
// Count of total items borrowed by $borrower_id
//
function fetch_my_borrowed_item_cnt($borrower_id)
{
	$query = "SELECT count('x') AS count FROM borrowed_item bi ".
			"WHERE bi.status = 'B' AND bi.borrower_id = '".$borrower_id."'";

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

/**
	All records that the specified borrower has borrowed.
	Columns: bi.sequence_number, bi.item_id, i.title, i.genre, i.owner_id
	Will only apply a limit, if $index is defined.
*/
function fetch_my_borrowed_item_rs($borrower_id, $order_by, $sortorder, $start_index=NULL, $items_per_page=NULL)
{
	$query = "SELECT bi.sequence_number, bi.item_id, bi.instance_no, UNIX_TIMESTAMP(DATE_ADD(bi.update_on, INTERVAL bi.borrow_duration DAY)) as due_date, i.s_item_type, i.title, ii.owner_id, ii.s_status_type ".
				"FROM item i, item_instance ii, borrowed_item bi ".
				"WHERE i.id = ii.item_id AND ii.instance_no = bi.instance_no AND bi.item_id = ii.item_id AND ".
				"bi.status = 'B' and bi.borrower_id = '".$borrower_id."' ".
				get_orderby_clause($order_by, $sortorder);

	if(is_numeric($start_index) && is_numeric($items_per_page))
		$query .= ' LIMIT ' .$start_index. ', ' .$items_per_page;

	$result = db_query($query);
	if($result && db_num_rows($result)>0)
		return $result;
	else
		return FALSE;
}

//
// Count of total items reserved by $borrower_id
//
function fetch_all_reserved_item_cnt()
{
	$query = "SELECT count('x') AS count FROM borrowed_item bi WHERE bi.status = 'R'";

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

function fetch_all_reserved_item_rs($order_by, $sortorder, $start_index=NULL, $items_per_page=NULL)
{
	$query = "SELECT bi.sequence_number, bi.item_id, bi.instance_no, bi.borrower_id, i.s_item_type, i.title, ii.owner_id, ii.borrow_duration, ii.s_status_type, UNIX_TIMESTAMP(bi.update_on) as reserve_date ".
				"FROM item i, item_instance ii, borrowed_item bi ".
				"WHERE i.id = ii.item_id AND ii.instance_no = bi.instance_no AND bi.item_id = ii.item_id AND ".
				"bi.status = 'R' ".
				get_orderby_clause($order_by, $sortorder);

	if(is_numeric($start_index) && is_numeric($items_per_page))
		$query .= ' LIMIT ' .$start_index. ', ' .$items_per_page;

	$result = db_query($query);
	if($result && db_num_rows($result)>0)
		return $result;
	else
		return FALSE;
}

//
// Count of total items reserved by $borrower_id
//
function fetch_my_reserved_item_cnt($borrower_id)
{
	$query = "SELECT count('x') AS count FROM borrowed_item bi WHERE bi.status = 'R' AND bi.borrower_id = '".$borrower_id."'";

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
// All records that the specified borrower has reserved.
// Columns: bi.sequence_number, bi.item_id, i.title, i.genre, i.owner_id, i.s_status_type
//
function fetch_my_reserved_item_rs($borrower_id, $order_by=NULL, $sortorder=NULL, $start_index=NULL, $items_per_page=NULL)
{
	$query = "SELECT bi.sequence_number, bi.item_id, bi.instance_no, i.s_item_type, i.title, ii.owner_id, ii.borrow_duration, ii.s_status_type, UNIX_TIMESTAMP(bi.update_on) as reserve_date ".
				"FROM item i, item_instance ii, borrowed_item bi ".
				"WHERE i.id = ii.item_id AND ii.instance_no = bi.instance_no AND bi.item_id = ii.item_id AND ".
				"bi.status = 'R' and bi.borrower_id = '".$borrower_id."' ";

	if(strlen($order_by)>0)
		$query .= get_orderby_clause($order_by, $sortorder);
		
	if(is_numeric($start_index) && is_numeric($items_per_page))
		$query .= ' LIMIT ' .$start_index. ', ' .$items_per_page;

	$result = db_query($query);
	if($result && db_num_rows($result)>0)
		return $result;
	else
		return FALSE;
}

function fetch_my_history_item_cnt($borrower_id)
{
	$query = "SELECT count('x') AS count FROM borrowed_item bi WHERE bi.status IN('C','X','R','B') AND bi.borrower_id = '".$borrower_id."'";
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

function fetch_my_history_item_rs($borrower_id, $order_by, $sortorder, $start_index=NULL, $items_per_page=NULL)
{
	$query = "SELECT bi.sequence_number, 
			bi.item_id, 
			bi.instance_no, 
			i.s_item_type, 
			i.title, 
			ii.owner_id, 
			bi.status, 
			bi.borrow_duration, 
			ii.borrow_duration AS ii_borrow_duration, 
			bi.total_duration, bi.status, ii.s_status_type,
			CASE WHEN bi.status = 'C' THEN TO_DAYS(now()) - TO_DAYS(bi.update_on) ELSE NULL END AS calc_total_duration, 
			CASE WHEN bi.status IN('B','R') THEN UNIX_TIMESTAMP(bi.update_on) WHEN bi.status = 'X' THEN NULL ELSE UNIX_TIMESTAMP(DATE_SUB(bi.update_on, INTERVAL bi.total_duration DAY)) END AS borrow_date, 
			CASE WHEN bi.status IN('B') THEN UNIX_TIMESTAMP(DATE_ADD(bi.update_on, INTERVAL bi.borrow_duration DAY)) ELSE NULL END AS due_date, 
			CASE WHEN bi.status IN('C') THEN UNIX_TIMESTAMP(bi.update_on) ELSE NULL END AS return_date, 
			CASE WHEN bi.status  = 'B' THEN UNIX_TIMESTAMP(DATE_ADD(bi.update_on, INTERVAL bi.borrow_duration DAY)) ELSE NULL END AS due_date
			FROM item i, item_instance ii, borrowed_item bi 
			WHERE i.id = ii.item_id AND bi.instance_no = ii.instance_no AND bi.item_id = ii.item_id AND 
			 bi.status IN('C','X', 'R', 'B') AND bi.borrower_id = '$borrower_id' ".
				get_orderby_clause($order_by, $sortorder);

	if(is_numeric($start_index) && is_numeric($items_per_page))
		$query .= ' LIMIT ' .$start_index. ', ' .$items_per_page;

	$result = db_query($query);
	if($result && db_num_rows($result)>0)
		return $result;
	else
		return FALSE;
}

function is_item_in_reserve_basket($item_id, $instance_no, $borrower_id)
{
	return fetch_borrowed_item_seq_no($item_id, $instance_no, 'T', $borrower_id) !== FALSE;
}

function is_exists_my_reserve_basket($borrower_id)
{
	return fetch_my_basket_item_cnt($borrower_id)>0;
}

function fetch_my_basket_item_cnt($borrower_id)
{
	$query = "SELECT count('x') AS count FROM borrowed_item bi WHERE bi.status = 'T' AND bi.borrower_id = '".$borrower_id."'";
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

function fetch_borrowed_item_pk_rs($borrower_id, $status)
{
	$query = "SELECT sequence_number, item_id, instance_no ".
			"FROM borrowed_item ".
			"WHERE status  = '$status' AND borrower_id = '".$borrower_id."'";

	$result = db_query($query);
	if($result && db_num_rows($result)>0)
		return $result;
	else
		return FALSE;
}

function fetch_my_basket_item_rs($borrower_id, $order_by, $sortorder, $start_index=NULL, $items_per_page=NULL)
{
	$query = "SELECT bi.sequence_number, bi.item_id, bi.instance_no, i.s_item_type, i.title, ii.owner_id, UNIX_TIMESTAMP(bi.update_on) as basket_date, bi.status, ii.s_status_type ".
				"FROM item i, item_instance ii, borrowed_item bi ".
				"WHERE i.id = ii.item_id AND bi.instance_no = ii.instance_no AND bi.item_id = ii.item_id AND ".
				" bi.status  = 'T' AND bi.borrower_id = '".$borrower_id."' ".
				get_orderby_clause($order_by, $sortorder);

	if(is_numeric($start_index) && is_numeric($items_per_page))
		$query .= ' LIMIT ' .$start_index. ', ' .$items_per_page;

	$result = db_query($query);
	if($result && db_num_rows($result)>0)
		return $result;
	else
		return FALSE;
}

function fetch_owner_history_item_cnt($owner_id)
{
	$query = "SELECT count('x') AS count ".
			"FROM borrowed_item bi, item_instance ii ".
			"WHERE ii.item_id = bi.item_id AND ".
			"ii.instance_no = bi.instance_no AND ".
			"bi.status IN('C','X','B','R') AND ".
			"ii.owner_id = '".$owner_id."'";

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

function fetch_item_instance_history_cnt($item_id, $instance_no)
{
	$query = "SELECT count(bi.sequence_number) as count ".
				"FROM borrowed_item bi ".
				"WHERE bi.item_id = '".$item_id."' AND bi.instance_no = '".$instance_no."' AND bi.status IN ('X', 'C', 'R', 'B') ";

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

function fetch_item_instance_history_rs($item_id, $instance_no, $order_by, $sortorder, $start_index=NULL, $items_per_page=NULL)
{
	$query = "SELECT bi.sequence_number, 
			bi.borrower_id, 
			CASE WHEN bi.status IN('B','R') THEN UNIX_TIMESTAMP(bi.update_on) WHEN bi.status = 'X' THEN NULL ELSE UNIX_TIMESTAMP(DATE_SUB(bi.update_on, INTERVAL bi.total_duration DAY)) END AS borrow_date, 
			CASE WHEN bi.status IN('B') THEN UNIX_TIMESTAMP(DATE_ADD(bi.update_on, INTERVAL bi.borrow_duration DAY)) ELSE NULL END AS due_date, 
			CASE WHEN bi.status IN('C') THEN UNIX_TIMESTAMP(bi.update_on) ELSE NULL END AS return_date, 
			CASE WHEN bi.status = 'B' THEN UNIX_TIMESTAMP(DATE_ADD(bi.update_on, INTERVAL bi.borrow_duration DAY)) ELSE NULL END AS due_date, 
			bi.borrow_duration, 
			bi.total_duration, 
			CASE WHEN bi.status = 'C' THEN TO_DAYS(now()) - TO_DAYS(bi.update_on) ELSE NULL END AS calc_total_duration, 
			bi.status, 
			ii.borrow_duration AS ii_borrow_duration, 
			ii.s_status_type, 
			i.s_item_type 
			FROM item_instance ii, item i, borrowed_item bi 
			WHERE  i.id = ii.item_id AND ii.instance_no = bi.instance_no AND bi.item_id = ii.item_id AND 
			 bi.item_id = '".$item_id."' AND bi.instance_no = '".$instance_no."' AND bi.status IN ('X', 'C', 'R', 'B') ".
			get_orderby_clause($order_by, $sortorder);

	if(is_numeric($start_index) && is_numeric($items_per_page))
		$query .= ' LIMIT ' .$start_index. ', ' .$items_per_page;

	$result = db_query($query);
	if($result && db_num_rows($result)>0)
		return $result;
	else
		return FALSE;
}

//
// Count of total items reserved by $borrower_id
//
function fetch_owner_reserved_item_cnt($owner_id)
{
	$query = "SELECT count('x') AS count from item i, item_instance ii, borrowed_item bi ".
			"WHERE i.id = ii.item_id AND ii.instance_no = bi.instance_no AND bi.item_id = ii.item_id AND ii.owner_id = '".$owner_id."' AND bi.status = 'R'";

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
// All records that the specified owner has, that are reserved by a borrower.
// For each record, the is_item_borrowed($item_id) function should be called to check
// if the item can actually be checked out.
//
function fetch_owner_reserved_item_rs($owner_id, $order_by=NULL, $sortorder=NULL, $start_index=NULL, $items_per_page=NULL)
{
	$query = "SELECT bi.sequence_number, bi.item_id, bi.instance_no, i.s_item_type, i.title, ii.borrow_duration, ii.s_status_type, bi.borrower_id, UNIX_TIMESTAMP(bi.update_on) as reserve_date ".
				"FROM item i, item_instance ii, borrowed_item bi ".
				"WHERE i.id = ii.item_id AND ii.instance_no = bi.instance_no AND bi.item_id = ii.item_id AND ".
				"ii.owner_id = '".$owner_id."' AND ".
				"bi.status = 'R' ";
				
	if(strlen($order_by)>0)
		$query .= get_orderby_clause($order_by, $sortorder);

	if(is_numeric($start_index) && is_numeric($items_per_page))
		$query .= ' LIMIT ' .$start_index. ', ' .$items_per_page;
	
	$result = db_query($query);
	if($result && db_num_rows($result)>0)
		return $result;
	else
		return FALSE;
}

/*
* Count of total items that the owner has checked out to other users
*/
function fetch_owner_borrowed_item_cnt($owner_id)
{
	$query = "SELECT count('x') AS count FROM item i, item_instance ii, borrowed_item bi ".
			"WHERE i.id = ii.item_id AND ii.instance_no = bi.instance_no AND bi.item_id = ii.item_id AND ii.owner_id = '".$owner_id."' AND bi.status = 'B'";

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
// All records that the specified owner has, that are borrowed out
//
function fetch_owner_borrowed_item_rs($owner_id, $order_by, $sortorder, $start_index=NULL, $items_per_page=NULL)
{
	$query = "SELECT bi.sequence_number, bi.item_id, bi.instance_no, UNIX_TIMESTAMP(DATE_ADD(bi.update_on, INTERVAL bi.borrow_duration DAY)) as due_date, i.s_item_type, i.title, ii.s_status_type, bi.borrower_id  ".
				"FROM item i, item_instance ii, borrowed_item bi ".
				"WHERE i.id = ii.item_id AND ii.instance_no = bi.instance_no AND bi.item_id = ii.item_id and ".
				"ii.owner_id = '".$owner_id."' and ".
				"bi.status = 'B' ".
				get_orderby_clause($order_by, $sortorder);

	if(is_numeric($start_index) && is_numeric($items_per_page))
		$query .= ' LIMIT ' .$start_index. ', ' .$items_per_page;
		
	$result = db_query($query);
	if($result && db_num_rows($result)>0)
		return $result;
	else
		return FALSE;
}

/**
* Fetch a list of all items due for renewal within specified daysleft limit.
* 
* Duration range
*	+1 = once one day overdue
*	0 = on day due
*	-1 = one day before due
*/
function fetch_reminder_borrowed_item_rs($duration_range)
{
	$query = "SELECT bi.sequence_number, bi.item_id, bi.instance_no, i.s_item_type, i.title, ii.s_status_type, bi.borrower_id, UNIX_TIMESTAMP(DATE_ADD(bi.update_on, INTERVAL bi.borrow_duration DAY)) as due_date, bi.borrow_duration, (TO_DAYS(now()) - TO_DAYS(bi.update_on)) as total_duration ".
			"FROM item i, item_instance ii, borrowed_item bi ".
			"WHERE i.id = ii.item_id AND ".
			"ii.instance_no = bi.instance_no AND ".
			"bi.item_id = ii.item_id AND ".
			"bi.status = 'B' AND ".
			"bi.borrow_duration > 0 AND ".
			"((TO_DAYS(NOW()) - TO_DAYS(DATE_ADD(bi.update_on, INTERVAL bi.borrow_duration DAY))) >= $duration_range)";
	
	$result = db_query($query);
	if($result && db_num_rows($result)>0)
		return $result;
	else
		return FALSE;
}

/**
	This will return a count of all borrowed_items of the $status which
	have been updated on or before the $update_on timestamp.  The update_on
	variable can also be a proper DATETIME and it will still work.
*/
function fetch_borrowed_item_status_atdate_cnt($status, $update_on)
{
	$query = "SELECT count('x') AS count FROM borrowed_item bi ".
			"WHERE bi.status = '$status' AND bi.update_on >= '$update_on'";

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

//-----------------------------------------------------------------------------------
// Utility functions
//-----------------------------------------------------------------------------------
/**
*/
function fetch_borrowed_item_r($sequence_number, $include_duedate = FALSE)
{
	$query = "SELECT item_id, instance_no, borrower_id, borrow_duration, IFNULL(total_duration, TO_DAYS(now()) - TO_DAYS(update_on)) as total_duration, status ";
	if($include_duedate){
		$query .= ", IF(borrow_duration>0,UNIX_TIMESTAMP(DATE_ADD(update_on, INTERVAL borrow_duration DAY)),NULL) as due_date ";
	}
	$query .= " FROM borrowed_item WHERE sequence_number = $sequence_number";
	
	$result = db_query($query);
	if($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);
		return $found;
	}
	
	//else
	return FALSE;
}

/**
	Fetch PRIMARY KEY information only.  Strictly speaking this should 
	only include sequence_number, as that is the primary key.  But the
	real intention is to return all the columns that make up the 
	primary key of borrowed_item table where sequence_number is not
	available.
*/
function fetch_borrowed_item_pk_r($sequence_number)
{
	$query = "SELECT item_id, instance_no, borrower_id ".
			"FROM borrowed_item WHERE sequence_number = $sequence_number";

	$result = db_query($query);
	if($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);
		return $found;
	}
	
	//else
	return FALSE;
}

/**
 * @param string $item_id
 * @param string $instance_no
 * @param string $status
 * @param string $borrower_id if not provided, its not defined which of more than 1 record will be returned.  The borrower_id
 * should only ever be missing for status' which are guaranteed to be unique, the only example of this is 'B' for borrowed.
 * @return number
 */
function fetch_borrowed_item_seq_no($item_id, $instance_no, $status, $borrower_id = NULL)
{
	$query = "SELECT sequence_number".
			" FROM borrowed_item ".
			" WHERE item_id = '$item_id' AND instance_no = '$instance_no' AND status = '$status'";

	if(strlen($borrower_id)>0)
	{
		$query .= " AND borrower_id = '$borrower_id' ";
	}
	
	$result = db_query($query);
	if($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		db_free_result($result);
		if($found!==FALSE)
			return $found['sequence_number'];
	}
	
	//else
	return FALSE;
}

//
// Will return true if any records are found for the item_id with a status of
// 'R' or 'B'
//
function is_item_reserved_or_borrowed($item_id, $instance_no)
{
	// In this case, we will not do a reserve, if the borrower has already reserved,
	// or borrowed the item.
	$query = "SELECT 'X' FROM borrowed_item " .
			"WHERE item_id = '$item_id' AND instance_no = '$instance_no' AND " .
			"status IN('R', 'B') ";

	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		db_free_result($result);
		return TRUE;
	}
	else
		return FALSE;
}

//
// Will return true if any records are found for the item_id with a status of
// 'R' or 'B'
//
function is_item_reserved($item_id, $instance_no)
{
	// In this case, we will not do a reserve, if the borrower has already reserved,
	// or borrowed the item.
	$query = "SELECT 'X' FROM borrowed_item " .
			"WHERE item_id = '$item_id' AND instance_no = '$instance_no' AND ".
			"status = 'R'";

	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		db_free_result($result);
		return TRUE;
	}
	else
		return FALSE;
}

//
// Will return true if any records are found for the item_id with a status of
// 'R' or 'B'
//
function is_item_borrowed($item_id, $instance_no)
{
	// In this case, we will not do a reserve, if the borrower has already reserved,
	// or borrowed the item.
	$query = "SELECT 'X' FROM borrowed_item " .
			"WHERE item_id = '$item_id' AND instance_no = '$instance_no' AND ".
			"status = 'B'";

	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		db_free_result($result);
		return TRUE;
	}
	else
		return FALSE;
}

function is_exists_item_instance_borrowed_item($item_id, $instance_no)
{
	$query = "SELECT 'X' FROM borrowed_item " .
			"WHERE item_id = '$item_id' AND instance_no = '$instance_no'";

	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		db_free_result($result);
		return TRUE;
	}
	else
		return FALSE;
}

function is_exists_item_instance_history_borrowed_item($item_id, $instance_no)
{
	$query = "SELECT 'X' FROM borrowed_item " .
			"WHERE item_id = '$item_id' AND instance_no = '$instance_no' AND status IN ('X', 'C', 'R', 'B')";

	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		db_free_result($result);
		return TRUE;
	}
	else
		return FALSE;
}

function is_exists_borrower_reserved($borrower_id)
{
	return is_exists_borrower_borrowed_item($borrower_id, 'R');
}

function is_exists_borrower_borrowed($borrower_id)
{
	return is_exists_borrower_borrowed_item($borrower_id, 'B');
}

function is_exists_borrower_history($borrower_id)
{
	return is_exists_borrower_borrowed_item($borrower_id, array('C','X', 'R', 'B'));
}

/*
* @param $status - Valid values are:
* 			R - Reserved
* 			B - Borrowed
* 			C - Closed
* 			X - Cancelled
*/
function is_exists_borrower_borrowed_item($borrower_id, $status = NULL)
{
	$query = "SELECT 'X' FROM borrowed_item ".
			"WHERE borrower_id = '".$borrower_id."' ";
			
	if(is_array($status))
		$query .= " AND status IN(".format_sql_in_clause($status).") ";
	else if($status != NULL)
		 $query .= " AND status = '".$status."' ";
	
	$query .= "LIMIT 0,1";
	
	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		db_free_result($result);
		return TRUE;
	}
	else
		return FALSE;
}

function is_exists_owner_reserved($owner_id)
{
	return is_exists_owner_borrowed_item($owner_id, 'R');
}

function is_exists_owner_borrowed($owner_id)
{
	return is_exists_owner_borrowed_item($owner_id, 'B');
}

function is_exists_owner_history($owner_id)
{
	return is_exists_owner_borrowed_item($owner_id, array('C','X', 'B', 'R'));
}

/*
* @param $status - Valid values are:
* 			R - Reserved
* 			B - Borrowed
* 			C - Closed
* 			X - Cancelled
*/
function is_exists_owner_borrowed_item($owner_id, $status = NULL)
{
	$query = "SELECT 'X' FROM borrowed_item bi, item_instance ii ".
			"WHERE bi.item_id = ii.item_id AND bi.instance_no = ii.instance_no AND ".
			"ii.owner_id = '".$owner_id."'";
	
	if(is_array($status))
		$query .= " AND bi.status IN(".format_sql_in_clause($status).") ";
	else if($status != NULL)
		 $query .= " AND bi.status = '".$status."' ";
	
	$query .= "LIMIT 0,1";
	
	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		db_free_result($result);
		return TRUE;
	}
	else
		return FALSE;
}

function is_exists_reserved()
{
	return is_exists_borrowed_item('R');
}

function is_exists_borrowed()
{
	return is_exists_borrowed_item('B');
}

function is_exists_history()
{
	return is_exists_borrowed_item(array('C','X','R','B'));
}

/*
* @param $status - Valid values are:
* 			R - Reserved
* 			B - Borrowed
* 			C - Closed
* 			X - Cancelled
*/
function is_exists_borrowed_item($status = NULL)
{
	$query = "SELECT 'X' FROM borrowed_item ";
			
	if(is_array($status))
		$query .= "WHERE status IN(".format_sql_in_clause($status).") ";
	else if($status !== NULL)
		 $query .= "WHERE status = '".$status."' ";
	
	$query .= "LIMIT 0,1";
	
	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		db_free_result($result);
		return TRUE;
	}
	else
		return FALSE;
}

//
// Will return true if any records are found for the item_id with a status of
// 'R' or 'B'
//
function is_item_reserved_or_borrowed_by_user($item_id, $instance_no, $borrower_id)
{
	// In this case, we will not do a reserve, if the borrower has already reserved,
	// or borrowed the item.
	$query = "SELECT 'X' FROM borrowed_item " .
			"WHERE item_id = '$item_id' AND instance_no = '$instance_no' AND ".
			"borrower_id = '".$borrower_id."' AND ".
			"status IN ('R', 'B')";

	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		db_free_result($result);
		return TRUE;
	}
	else
		return FALSE;
}

//
// Will return true if any records are found for the item_id with a status of
// 'R' or 'B'
//
function is_item_reserved_by_user($item_id, $instance_no, $borrower_id)
{
	// In this case, we will not do a reserve, if the borrower has already reserved,
	// or borrowed the item.
	$query = "SELECT 'X' FROM borrowed_item " .
			"WHERE item_id = '$item_id' AND instance_no = '$instance_no' AND ".
			"borrower_id = '".$borrower_id."' AND ".
			"status = 'R'";

	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		db_free_result($result);
		return TRUE;
	}
	else
		return FALSE;
}

/**
	Will return true if the borrower_id has borrowed the item, or previously
	returned it.
*/
function is_item_borrowed_or_returned_by_user($item_id, $instance_no, $borrower_id)
{
	// In this case, we will not do a reserve, if the borrower has already reserved,
	// or borrowed the item.
	$query = "SELECT 'X' FROM borrowed_item " .
			"WHERE item_id = '$item_id' AND instance_no = '$instance_no' AND ".
			"borrower_id = '".$borrower_id."' AND ".
			"status IN ('B', 'C')";

	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		db_free_result($result);
		return TRUE;
	}
	else
		return FALSE;
}

//
// Will return true if $borrower_id has borrowed item (status of 'B')
//
function is_item_borrowed_by_user($item_id, $instance_no, $borrower_id)
{
	// In this case, we will not do a reserve, if the borrower has already reserved,
	// or borrowed the item.
	$query = "SELECT 'X' FROM borrowed_item " .
			"WHERE item_id = '$item_id' AND instance_no = '$instance_no' AND ".
			"borrower_id = '".$borrower_id."' AND ".
			"status = 'B'";

	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		db_free_result($result);
		return TRUE;
	}
	
	return FALSE;
}

function fetch_item_borrower($item_id, $instance_no)
{
	$query = "SELECT borrower_id FROM borrowed_item " .
			"WHERE item_id = '$item_id' AND instance_no = '$instance_no' AND status = 'B'";

	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		if ($found)
		{
			db_free_result($result);
			return $found['borrower_id'];
		}
	}

	//else
	return FALSE;
}

/**
	This function will return the item duedate string.

	Note: there should only ever be one instance of item_id/instance_no with status = 'B'!!
*/
function fetch_item_duedate_timestamp($item_id, $instance_no)
{
	$query = "SELECT IF(borrow_duration>0,UNIX_TIMESTAMP(DATE_ADD(update_on, INTERVAL borrow_duration DAY)),NULL) as due_date FROM borrowed_item " .
			"WHERE item_id = '$item_id' AND instance_no = '$instance_no' AND status = 'B'";

	$result = db_query($query);
	if ($result && db_num_rows($result)>0)
	{
		$found = db_fetch_assoc($result);
		if ($found)
		{
			db_free_result($result);
			return $found['due_date'];
		}
	}

	//else
	return FALSE;
}

//----------------------------------------------------------------------------------
// Update functions
//----------------------------------------------------------------------------------
/**
	Assumes validation has been performed to ensure reservation does not already exist.
		Will return new sequence number if successful.
*/
function reserve_item($item_id, $instance_no, $borrower_id)
{
	$sequence_number = fetch_borrowed_item_seq_no($item_id, $instance_no, 'T', $borrower_id);
	if($sequence_number !== FALSE)
	{
		if(update_borrowed_item($sequence_number, 'R'))
		{
			return $sequence_number;
		}
		else
		{
			return FALSE;			
		}
	}
	else
	{
		$query = "INSERT INTO borrowed_item(item_id, instance_no, borrower_id, status)".
				"VALUES('$item_id', '$instance_no', '$borrower_id', 'R')";
		$insert = db_query($query);
		if($insert && db_affected_rows()>0)
		{
			opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($item_id, $instance_no, $borrower_id));
			return db_insert_id();
		}
		else
		{
			opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($item_id, $instance_no, $borrower_id));
			return FALSE;
		}
	}
}

function update_borrowed_item($sequence_number, $status)
{
	$query = "UPDATE borrowed_item SET status ='$status' WHERE sequence_number = $sequence_number";
	$update = db_query($query);
	if($update && db_affected_rows()>0)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($sequence_number));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($sequence_number));
		return FALSE;
	}		
}

function insert_cart_item($item_id, $instance_no, $borrower_id)
{
	$query = "INSERT INTO borrowed_item(item_id, instance_no, borrower_id, status)".
			"VALUES('$item_id', '$instance_no', '$borrower_id', 'T')";
	$insert = db_query($query);
	if($insert && db_affected_rows()>0)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($item_id, $instance_no, $borrower_id));
		return db_insert_id();
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($item_id, $instance_no, $borrower_id));
		return FALSE;
	}		
}

function delete_cart_item($sequence_number)
{
	$query = "DELETE FROM borrowed_item WHERE sequence_number = $sequence_number AND status = 'T'";
	$delete = db_query($query);
	if($delete && db_affected_rows()>0)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($sequence_number));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($sequence_number));
		return FALSE;
	}
}

/**
	Cancel Reservation.  It is assumed that borrow.php has performed validation to
	ensure that only borrower or owner actually cancels reservation.
*/	
function cancel_reserve_item($sequence_number)
{
	$query = "UPDATE borrowed_item SET status ='X' WHERE sequence_number = $sequence_number";
	$update = db_query($query);
	if($update && db_affected_rows()>0)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($sequence_number));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($sequence_number));
		return FALSE;
	}
}

/**
	This function expects that all validation has been performed already.
*/	
function quick_check_out_item($item_id, $instance_no, $borrower_id, $borrow_duration)
{
	$query = "INSERT INTO borrowed_item(item_id, instance_no, borrower_id, borrow_duration, status)".
			"VALUES('$item_id', '$instance_no', '$borrower_id', ".(is_numeric($borrow_duration) && $borrow_duration>0?"'$borrow_duration'":"NULL").", 'B')";
	$insert = db_query($query);
	if($insert && db_affected_rows()>0)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($item_id, $instance_no, $borrower_id, $borrow_duration));
		return db_insert_id();
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($item_id, $instance_no, $borrower_id, $borrow_duration));
		return FALSE;
	}
}

/**
	This function expects that all validation has been performed already.
*/	
function check_out_item($sequence_number, $borrow_duration)
{
	$query = "UPDATE borrowed_item SET status ='B', borrow_duration = ".(is_numeric($borrow_duration) && $borrow_duration>0?"'$borrow_duration'":"NULL").
		 " WHERE sequence_number = $sequence_number";
	$update = db_query($query);
	if($update && db_affected_rows()>0)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($sequence_number, $borrow_duration));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($sequence_number, $borrow_duration));
		return FALSE;
	}
}

/**
	This function assumes only one checked out to borrower of item_id.  Set status to 'C' for Closed.
*/
function check_in_item($sequence_number)
{
	$query = "UPDATE borrowed_item SET status ='C', total_duration = TO_DAYS(now()) - TO_DAYS(update_on)".
			" WHERE sequence_number = $sequence_number";
	$update = db_query($query);
	if($update && db_affected_rows()>0)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($sequence_number));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($sequence_number));
		return FALSE;
	}
}

/*
*/
function item_borrow_duration_extension($sequence_number, $borrow_extension)
{
	if(is_numeric($borrow_extension))
	{
		// set update_on - to itself, to prevent timestamp auto-update.
		$query = "UPDATE borrowed_item SET borrow_duration = IFNULL(borrow_duration,0) + $borrow_extension, update_on = update_on".
				" WHERE sequence_number = $sequence_number";
				
		$update = db_query($query);
		if($update && db_affected_rows()>0)
		{
			opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($sequence_number, $borrow_extension));
			return TRUE;
		}
		else
		{
			opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($sequence_number, $borrow_extension));
			return FALSE;
		}
	}
	else
	{
		// Should never happen, so don't bother logging it.
		return FALSE;
	}
}

/*
* Items which are currently active, can never be deleted via this function, they must be closed, cancelled or basket first.
* 
* This function returns TRUE, even if no records are deleted.  As long as the delete operation succeeded, is
* all that matters.
*/
function delete_my_inactive_borrowed_items($borrower_id)
{
	$query = "DELETE FROM borrowed_item WHERE borrower_id = '$borrower_id' AND status IN ('X','C','T')";
	$delete = db_query($query);
	if ($delete)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($borrower_id));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($borrower_id));
		return FALSE;
	}
}

/*
* Items which are currently active, can never be deleted via this function, they must be closed, cancelled or basket first.
* 
* This function returns TRUE, even if no records are deleted.  As long as the delete operation succeeded, is
* all that matters.
*/
function delete_item_instance_inactive_borrowed_items($item_id, $instance_no)
{
	$query= "DELETE FROM borrowed_item WHERE item_id = $item_id AND instance_no = $instance_no AND status IN ('X','C','T')";
	$delete = db_query($query);
	if($delete)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($item_id, $instance_no));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($item_id, $instance_no));
		return FALSE;
	}
}

function delete_item_instance_borrower_borrowed_items($item_id, $instance_no, $borrower_id)
{
	$query= "DELETE FROM borrowed_item WHERE item_id = $item_id AND instance_no = $instance_no AND borrower_id = '$borrower_id'";
	$delete = db_query($query);
	if($delete)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($item_id, $instance_no, $borrower_id));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($item_id, $instance_no, $borrower_id));
		return FALSE;
	}
}
?>
