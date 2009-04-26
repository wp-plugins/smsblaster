<?php
/*
Plugin Name: SMSBlaster
Plugin URI: http://plugins.wirtschaftsinformatiker.cc/wp-smsblaster
Description: Allows the user to send sms via the smsblaster service.
Author: Marco Bischoff
Version: 0.3
Author URI: http://wirtschaftsinformatiker.cc/
License: GPL 2.0, @see http://www.gnu.org/licenses/gpl-2.0.html
*/

/*SMSBlaster class initialisieren*/
require_once("SMS.inc");

if(function_exists('load_plugin_textdomain'))
	load_plugin_textdomain('smsblaster', false, dirname(plugin_basename(__FILE__)) . '/languages');

define("SMSBLASTER_USERNAME", get_option('smsblaster_userid'));
define("SMSBLASTER_PASSWORD", decrypt(get_option('smsblaster_password')));
define("SMSBLASTER_TITLE", get_option('smsblaster_title'));
define("SMSBLASTER_TABLE", "smsblaster");
define("SMSBLASTER_URL",  'http://' . $_SERVER['SERVER_NAME'] . str_replace ('\\','/',substr (dirname (__FILE__),strlen ($_SERVER['DOCUMENT_ROOT']))) . '/');
define("SMSBLASTER_ORIGINATOR", get_option("smsblaster_originator"));
define("SMSBLASTER_AMOUNT", get_option("smsblaster_smsamount"));
define("SMSBLASTER_ONLYREGISTEREDUSERS", get_option("smsblaster_registeredusers"));

function SMSBlaster_install() {
	global $table_prefix, $user_level, $wpdb;
	
	$table_name = $table_prefix . SMSBLASTER_TABLE;
	get_currentuserinfo();
	if ($user_level < 8) { return false; };
	$tables = array();
	$tables = $wpdb->get_results('SHOW TABLES FROM '.DB_NAME.';', ARRAY_N);
	update_option("smsblaster_title", "SMSBlaster");

	$first_install = false;

	$sql = "CREATE TABLE ".$table_name." (
			  smsblaster_id bigint(20) unsigned NOT NULL auto_increment,
			  smsblaster_author varchar(128) NOT NULL default '',
			  smsblaster_msg varchar(160) NOT NULL default '',
			  smsblaster_number varchar(160) NOT NULL default '',
			  smsblaster_dateadded datetime NOT NULL default '0000-00-00 00:00:00',
			  PRIMARY KEY  (smsblaster_id)
			) ";


	require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	dbDelta($sql);
} 
function encrypt($text) {
	return base64_encode($text);
} 

function decrypt($encrypted_text) {
 	return base64_decode($encrypted_text);
}

function form_SMSBlaster() {
	global $current_user;
	
	$title = get_option('smsblaster_title');
	
	echo $before_widget . $before_title . '<h2 class="widget SMSBlaster">'. $title .'</h2>'. $after_title;
	echo "<p>";
		_e('Es stehen dir noch die folgende Anzahl SMS zur Verf&uuml;gung: ','smsblaster');
		echo(get_option("smsblaster_smsamount") - smsblaster_checkcreditsperuser());
	echo "</p>";
			get_currentuserinfo();
			
			print '
				<script language="javascript" type="text/javascript">
				function smsblaster_send() {
					var form = $("smsblaster_form");
					var input = form["smsblaster_msg"];

					new Ajax.Request("'.SMSBLASTER_URL.'/smsblaster_send.php", {
					  method: "post",
					  parameters: $("smsblaster_form").serialize() + "&smsblaster_msg=" + $(input).getValue() ,
					  onSuccess: function(transport) {
					    $("smsblaster_submitted").innerHTML = transport.responseText;
					    $("smsblaster_form").reset();
					    $("smsblaster_chars").innerHTML = "160";
					  }
					});
 				}
 				
 				function smsblaster_counter() {
					var div, txt, counter;
 					div = $("smsblaster_chars");
  					txt = $("smsblaster_msg");
  					counter = 160 - parseInt(txt.value.length,10);
  					div.innerHTML = counter;
  					if(counter < 1) {
	  					txt.value = txt.value.substring(0, 160);
  					}
  					txt.focus();
				}
 				
				</script>
			';
			echo '<div id="smsblaster_submitted"></div>';
			echo 'Zeichen: <span id="smsblaster_chars">160</span>';
			echo '<div>';
			print '<form id="smsblaster_form" onsubmit="smsblaster_send(); return false;">
				<input type="hidden" name="smsblaster_userid" value="'. $current_user->ID .'" />
				<label for="smsblaster_natel">'.__('Natelnummer', 'smsblaster').'</label><br />
				<input type="text" id="smsblaster_natel" name="smsblaster_natel" value=""><br />
				<label for="smsblaster_msg">'.__('Mitteilung', 'smsblaster').'</label><br />
				<textarea id="smsblaster_msg" cols="20" rows="10" onkeyup="smsblaster_counter();" wrap="physical" ></textarea><br />
				<input type="submit" class="button-primary" value="'.__('Senden', 'smsblaster').'"  />
				</form>';
			
			echo '</div>';
			
			echo $after_widget;
}
//function SMSBlaster_widget_init() {
function widget_SMSBlaster($args) {			
	//Speichern der Nachricht in einer Tabellen mit IP/Id des Users und Datum/Uhrzeit
	global $current_user;

	extract($args);
		if(get_option("smsblaster_registeredusers") == true) {
			if(is_user_logged_in()) {
				
				if(smsblaster_checkcreditsperuser() < get_option("smsblaster_smsamount")) {
					form_SMSBlaster();
				} else {
					echo "<p>";
					_e('Es stehen dir f&uuml;r diesen Monat keine SMS mehr zur Verf&uuml;gung', 'smsblaster');
					echo "</p>";
				}
				
			}
		} else {
				form_SMSBlaster();
		}
}
//}

function smsblaster_checkcredits() {
	$iReturn = 0;
	$oSms = new SMS(SMSBLASTER_USERNAME, SMSBLASTER_PASSWORD);
	if(!$oSms->showCredits()) {
	  die('Error: ' . $oSms->getErrorDescription() . "\n");
	} else {
		$iReturn = $oSms->getCredits();
	}	
	return $iReturn;
}

function smsblaster_checkcreditsperuser() {
	global $current_user, $wpdb, $table_prefix;
	$tablename = $table_prefix . SMSBLASTER_TABLE;
	$aResult = $wpdb->get_results('select * from '.$tablename.' where smsblaster_author = '.$current_user->ID.' and DATE_FORMAT(smsblaster_dateadded, "%Y%m") = DATE_FORMAT(now(), "%Y%m");', ARRAY_N);
	$iSMS = count($aResult);
	//$iSMS = get_option("smsblaster_smsamount") - $iSMS;
	return $iSMS;
}

function smsblaster_control() {	
	
		/*
			NEU Speichern von ID, Passwort und Absender
			sobald username und passwort vorhanden: Rückgabe der Anzahl von SMS Creditts
			Widget nur Sichtbar für angemeldete User
			Anzahl SMS pro User
		*/
		
		if(isset($_POST['submitted'])) {

			update_option('smsblaster_userid', $_POST['smsblaster_userid']);
			update_option('smsblaster_password', encrypt($_POST['smsblaster_password']));
			update_option('smsblaster_originator', $_POST['smsblaster_originator']);
			update_option('smsblaster_title', $_POST['smsblaster_title']);
	
			if(isset($_POST['smsblaster_registeredusers'])){
				update_option('smsblaster_registeredusers', true);
				$checked = ' checked="checked"';
			}else{
				update_option('smsblaster_registeredusers', false);
				$checked='';
			}
			update_option('smsblaster_smsamount', $_POST['smsblaster_smsamount']);
			
			echo '<div id="message" class="updated fade"><p>' . __('Options saved.','') . '</p></div>';
		}
		else {
			if(get_option('smsblaster_registeredusers')) {
				$checked = ' checked="checked"';
				$div = 'block';
			} else {
				$checked = '';
				$div = 'none';
			}
		}
		
		?>
		<div class="wrap">
			<h2>SMSBlaster</h2>
			<h3>SMS Credits: <?php echo smsblaster_checkcredits();?></h3>
			<form method="post" action="">
				<?php wp_nonce_field('update-options'); ?>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php _e('Titel', 'smsblaster')?></th>
							<td><input type="text" name="smsblaster_title" value="<?php echo get_option('smsblaster_title'); ?>" /></td>
						</tr>
						
						<tr valign="top">
							<th scope="row"><?php _e('BenutzerID', 'smsblaster')?></th>
							<td><input type="text" name="smsblaster_userid" value="<?php echo get_option('smsblaster_userid'); ?>" /></td>
						</tr>
			 
						<tr valign="top">
							<th scope="row"><?php _e('Passwort', 'smsblaster')?></th>
							<td><input type="password" name="smsblaster_password" value="<?php echo decrypt(get_option('smsblaster_password')); ?>" /></td>
						</tr>
			
						<tr valign="top">
							<th scope="row"><?php _e('Versender', 'smsblaster')?></th>
							<td><input type="text"  name="smsblaster_originator" value="<?php echo get_option('smsblaster_originator'); ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('Nur Registrierte Benutzer', 'smsblaster')?></th>
							<td><input type="checkbox" <?php echo $checked; ?> id="smsblaster_registeredusers" name="smsblaster_registeredusers" onclick="if(document.getElementById('smsblaster_registeredusers').checked) { document.getElementById('smsblaster_smsamounttr').style.display='block'; } else { document.getElementById('smsblaster_smsamounttr').style.display='none'; }" value="<?php echo get_option('smsblaster_registeredusers'); ?>" /></td>
						</tr>
						
							<tr valign="top" id="smsblaster_smsamounttr" style="display:<?php echo $div; ?>" >
								<th scope="row"><?php _e('Anzahl SMS Pro Monat', 'smsblaster')?></th>
									<td>
										
										<select name="smsblaster_smsamount">
											<option value="5" <?php if(get_option('smsblaster_smsamount') == 5) echo 'selected'; ?> >5</option>
											<option value="10"  <?php if(get_option('smsblaster_smsamount') == 10) echo 'selected'; ?>>10</option>
											<option value="15"  <?php if(get_option('smsblaster_smsamount') == 15) echo 'selected'; ?>>15</option>
											<option value="20"  <?php if(get_option('smsblaster_smsamount') == 20) echo 'selected'; ?>>20</option>
											<option value="25"  <?php if(get_option('smsblaster_smsamount') == 25) echo 'selected'; ?>>25</option>
											<option value="30"  <?php if(get_option('smsblaster_smsamount') == 30) echo 'selected'; ?>>30</option>
										</select>
									
								</td>
							 </tr>
						
					</table>
					
					<input type="hidden" name="action" value="update" />
					<!--<input type="hidden" name="page_options" value="smsblaster_userid,smsblaster_password,smsblaster_originator,smsblaster_registeredusers,smsblaster_smsamount" />-->
				
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
					<input type="hidden" name="submitted" value="1" />
				</p>
			</form>
		</div>
<?php
		
		
	}

function SMSBlaster_addmenuitems() {
	if (function_exists('add_management_page')) {
		add_management_page('SMSBlaster', 'SMSBlaster', 0, __FILE__, 'smsblaster_control');
	}
	if (function_exists('add_submenu_page')) {
		add_submenu_page('options-general.php','SMSBlaster', 'SMSBlaster', 0, __FILE__, 'smsblaster_control');
	}
}

function SMSBlaster_init()
{
	global $table_prefix;
	define("SMSBLASTER_TABLEPREFIX", $table_prefix);
	
	wp_enqueue_script('prototype');
	register_sidebar_widget(__('SMSBlaster'), 'widget_SMSBlaster');
    register_widget_control('SMSBlaster', 'widget_SMSBlaster', 'widget_SMSBlaster', 'smsblaster_control', array('width' => 300));
}

if (isset($_GET['activate']) && $_GET['activate'] == 'true') {
	add_action('init', 'SMSBlaster_install');
} 

add_action("plugins_loaded", "SMSBlaster_init");
add_action('admin_menu', 'SMSBlaster_addmenuitems');


?>
