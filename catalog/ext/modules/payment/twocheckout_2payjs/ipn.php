<?php

require ('twocheckout_2payjs_ipn.php');
chdir('../../../../');
require('includes/application_top.php');

if ( strtoupper( $_SERVER['REQUEST_METHOD'] ) !== 'POST' )
{
	return false;
}
if ( ! isset( $_REQUEST['REFNOEXT'] ) )
{
	return false;
}

$tco_ipn = new twocheckout_2payjs_ipn();
$params = $_REQUEST;
$tco_ipn->indexAction($params);
