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

// This must be first - includes config.php
require_once("./include/begin.inc.php");
			
include_once("./functions/install.php");
include_once("./functions/database.php");
include_once("./functions/browser.php");

/**
*/
if(is_browser_supported())
{
	if(is_opendb_configured())
	{
		if(is_db_connected())
		{
			if(get_opendb_config_var('site', 'upgrade_check')===FALSE || check_opendb_version())
			{
				header('Location: login.php');
			}
			else
			{
				header('Location: install.php');
			}
		}//if(is_opendb_configured())
		else
		{
			header('Location: install.php');
		}
	}//if(is_db_connected())
	else
	{
		header('Location: install.php');
	}
}
else
{
	header('Location: browserSupport.php');	
}

// Cleanup after begin.inc.php
require_once("./include/end.inc.php");
?>
