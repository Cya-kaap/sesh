<?php // filename: config/constants.php
if (defined('SESH_CONSTANTS_LOADED')) {
	return;
}
define('SESH_CONSTANTS_LOADED', true);

define('APP_NAME', 'SESH');
define('SESH_NAME', APP_NAME);
define('BASE_URL', '/sesh');
define('ROLE_ADMIN', 'Admin');
define('ROLE_COORDINATOR', 'Coordinator');
define('ROLE_FACULTY', 'Faculty');
define('STATUS_PENDING', 'Pending');
define('STATUS_APPROVED', 'Approved');
define('STATUS_REJECTED', 'Rejected');
define('STATUS_CANCELLED', 'Cancelled');
define('ROOM_AVAILABLE', 'Available');
define('ROOM_MAINTENANCE', 'Maintenance');
define('ROOM_UNAVAILABLE', 'Unavailable');
define('CONDITION_GOOD', 'Good');
define('CONDITION_NEEDS_REPAIR', 'Needs Repair');
define('CONDITION_NOT_WORKING', 'Not Working');
