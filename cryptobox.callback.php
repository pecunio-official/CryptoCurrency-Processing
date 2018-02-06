<?php



if(!defined("CRYPTOBOX_WORDPRESS")) define("CRYPTOBOX_WORDPRESS", false);

if (!CRYPTOBOX_WORDPRESS) include_once("cryptobox.class.php");
elseif (!defined('ABSPATH')) exit; // Exit if accessed directly in wordpress


// a. check if private key valid
$valid_key = false;
if (isset($_POST["private_key_hash"]) && strlen($_POST["private_key_hash"]) == 128 && preg_replace('/[^A-Za-z0-9]/', '', $_POST["private_key_hash"]) == $_POST["private_key_hash"])
{
    $keyshash = array();
    $arr = explode("^", CRYPTOBOX_PRIVATE_KEYS);
    foreach ($arr as $v) $keyshash[] = strtolower(hash("sha512", $v));
    if (in_array(strtolower($_POST["private_key_hash"]), $keyshash)) $valid_key = true;
}


// b. alternative - ajax script send ctrlholdings.com json data
if (!$valid_key && isset($_POST["json"]) && $_POST["json"] == "1")
{
    $data_hash = $boxID = "";
    if (isset($_POST["data_hash"]) && strlen($_POST["data_hash"]) == 128 && preg_replace('/[^A-Za-z0-9]/', '', $_POST["data_hash"]) == $_POST["data_hash"]) { $data_hash = $_POST["data_hash"]; unset($_POST["data_hash"]); }
    if (isset($_POST["box"]) && is_numeric($_POST["box"]) && $_POST["box"] > 0) $boxID = intval($_POST["box"]);
    
    if ($data_hash && $boxID)
    {
        $private_key = "";
        $arr = explode("^", CRYPTOBOX_PRIVATE_KEYS);
        foreach ($arr as $v) if (strpos($v, $boxID."AA") === 0) $private_key = $v;
    
        if ($private_key)
        {
            $data_hash2 = strtolower(hash("sha512", $private_key.json_encode($_POST).$private_key));
            if ($data_hash == $data_hash2) $valid_key = true;
        }
        unset($private_key);
    }
    
    if (!$valid_key) die("Error! Invalid Json Data sha512 Hash!"); 
    
}


// c.
if ($_POST) foreach ($_POST as $k => $v) if (is_string($v)) $_POST[$k] = trim($v);



// d.
if (isset($_POST["plugin_ver"]) && !isset($_POST["status"]) && $valid_key)
{
	echo "cryptoboxver_" . (CRYPTOBOX_WORDPRESS ? "wordpress_" . GOURL_VERSION : "php_" . CRYPTOBOX_VERSION);
	die; 
}


// e.
if (isset($_POST["status"]) && in_array($_POST["status"], array("payment_received", "payment_received_unrecognised")) &&
		$_POST["box"] && is_numeric($_POST["box"]) && $_POST["box"] > 0 && $_POST["amount"] && is_numeric($_POST["amount"]) && $_POST["amount"] > 0 && $valid_key)
{
	
	foreach ($_POST as $k => $v)
	{
		if ($k == "datetime") 						$mask = '/[^0-9\ \-\:]/';
		elseif (in_array($k, array("err", "date", "period")))		$mask = '/[^A-Za-z0-9\.\_\-\@\ ]/';
		else								$mask = '/[^A-Za-z0-9\.\_\-\@]/';
		if ($v && preg_replace($mask, '', $v) != $v) 	$_POST[$k] = "";
	}
	
	if (!$_POST["amountusd"] || !is_numeric($_POST["amountusd"]))	$_POST["amountusd"] = 0;
	if (!$_POST["confirmed"] || !is_numeric($_POST["confirmed"]))	$_POST["confirmed"] = 0;
	
	
	$dt			= gmdate('Y-m-d H:i:s');
	$obj 		= run_sql("select paymentID, txConfirmed from crypto_payments where boxID = ".$_POST["box"]." && orderID = '".$_POST["order"]."' && userID = '".$_POST["user"]."' && txID = '".$_POST["tx"]."' && amount = ".$_POST["amount"]." && addr = '".$_POST["addr"]."' limit 1");
	
	
	$paymentID		= ($obj) ? $obj->paymentID : 0;
	$txConfirmed	= ($obj) ? $obj->txConfirmed : 0; 
	
	// Save new payment details in local database
	if (!$paymentID)
	{
		$sql = "INSERT INTO crypto_payments (boxID, boxType, orderID, userID, countryID, coinLabel, amount, amountUSD, unrecognised, addr, txID, txDate, txConfirmed, txCheckDate, recordCreated)
				VALUES (".$_POST["box"].", '".$_POST["boxtype"]."', '".$_POST["order"]."', '".$_POST["user"]."', '".$_POST["usercountry"]."', '".$_POST["coinlabel"]."', ".$_POST["amount"].", ".$_POST["amountusd"].", ".($_POST["status"]=="payment_received_unrecognised"?1:0).", '".$_POST["addr"]."', '".$_POST["tx"]."', '".$_POST["datetime"]."', ".$_POST["confirmed"].", '$dt', '$dt')";

		$paymentID = run_sql($sql);
		
		$box_status = "cryptobox_newrecord";
	}
	// Update transaction status to confirmed
	elseif ($_POST["confirmed"] && !$txConfirmed)
	{
		$sql = "UPDATE crypto_payments SET txConfirmed = 1, txCheckDate = '$dt' WHERE paymentID = $paymentID LIMIT 1";
		run_sql($sql);
		
		$box_status = "cryptobox_updated";
	}
	else 
	{
		$box_status = "cryptobox_nochanges";
	}
	

	if (in_array($box_status, array("cryptobox_newrecord", "cryptobox_updated")) && function_exists('cryptobox_new_payment')) cryptobox_new_payment($paymentID, $_POST, $box_status);
}   

else
	$box_status = "Only POST Data Allowed";


	echo $box_status; // don't delete this!!!
 
?>