<?php
	
	require_once("SMS.inc");
	require_once("../../../wp-config.php");
	require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	
	
	$iUserId = $_POST["smsblaster_userid"];
	$iNumber = $_POST["smsblaster_natel"];
	$sMessage = $_POST["smsblaster_msg"];
	
	
	$tables = $wpdb->get_results('select * from '.SMSBLASTER_TABLEPREFIX . SMSBLASTER_TABLE.' where smsblaster_author="'.$iUserId.'" and DATE_FORMAT(smsblaster_dateadded, "%Y%m") = DATE_FORMAT(now(), "%Y%m");', ARRAY_N);
	
	$oSms = new SMS(SMSBLASTER_USERNAME, SMSBLASTER_PASSWORD);
	$oSms->setOriginator(SMSBLASTER_ORIGINATOR);
	$oSms->addRecipient($_POST["smsblaster_natel"]);
	$oSms->setContent($_POST["smsblaster_msg"]);
	
	if(SMSBLASTER_ONLYREGISTEREDUSERS == 1) {
		if(count($tables) <= SMSBLASTER_AMOUNT) {
			//Create new instance;
		
			$result = $oSms->sendSMS();
		    if ($result != 1) {
		      $return = $oSms->getErrorDescription();
		    } else {
			    //store data;
			    $sql = "insert into " . SMSBLASTER_TABLEPREFIX . SMSBLASTER_TABLE ." (smsblaster_author, smsblaster_number, smsblaster_msg, smsblaster_dateadded) values ('". $iUserId ."', '" . $iNumber . "', '" . $sMessage . "', now() )";
				$wpdb->query($sql);
			    $return = __('SMS ist Gesendet', 'smsblaster');
			}
		} else {
			$return = "No more SMS";
		}
	} else {
		$result = $oSms->sendSMS();

		if ($result != 1) {
			$return = $oSms->getErrorDescription();
		} else {
			//store data;
			$sql = "insert into " . SMSBLASTER_TABLEPREFIX . SMSBLASTER_TABLE ." (smsblaster_author, smsblaster_number, smsblaster_msg, smsblaster_dateadded) values ('". $iUserId ."', '" . $iNumber . "', '" . $sMessage . "', now() )";
			$wpdb->query($sql);
			$return = __('SMS ist Gesendet', 'smsblaster');
		}
	}
	
	echo $return;
?>