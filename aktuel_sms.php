<?php
if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

function aktuel_sms_config() {
	$configarray = array(
		"name" => "Aktüel SMS",
		"description" => "Müşterilerinize SMS ile bilgilendirme gönderebilirsiniz.",
		"version" => "1.2.3",
		"author" => "BurtiNET & Aktüel",
		"language" => "turkish",
	);
	return $configarray;
}

function aktuel_sms_activate() {

	$query = "CREATE TABLE IF NOT EXISTS `mod_aktuelsms_messages` (`id` int(11) NOT NULL AUTO_INCREMENT,`sender` varchar(40) NOT NULL,`to` varchar(15) DEFAULT NULL,`text` text,`msgid` varchar(50) DEFAULT NULL,`status` varchar(10) DEFAULT NULL,`errors` text,`logs` text,`user` int(11) DEFAULT NULL,`datetime` datetime NOT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";
	full_query($query);

	$query = "CREATE TABLE IF NOT EXISTS `mod_aktuelsms_settings` (`id` int(11) NOT NULL AUTO_INCREMENT,`api` varchar(40) CHARACTER SET utf8 NOT NULL,`apiparams` varchar(500) CHARACTER SET utf8 NOT NULL,`wantsmsfield` int(11) DEFAULT NULL,`gsmnumberfield` int(11) DEFAULT NULL,`dateformat` varchar(12) CHARACTER SET utf8 DEFAULT NULL,`version` varchar(6) CHARACTER SET utf8 DEFAULT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;";
	full_query($query);

	$query = "INSERT INTO `mod_aktuelsms_settings` (`api`, `apiparams`, `wantsmsfield`, `gsmnumberfield`,`dateformat`, `version`) VALUES ('', '', 0, 0,'%d.%m.%y','1.1.3');";
	full_query($query);

	$query = "CREATE TABLE IF NOT EXISTS `mod_aktuelsms_templates` (`id` int(11) NOT NULL AUTO_INCREMENT,`name` varchar(50) CHARACTER SET utf8 NOT NULL,`type` enum('client','admin') CHARACTER SET utf8 NOT NULL,`admingsm` varchar(255) CHARACTER SET utf8 NOT NULL,`template` varchar(240) CHARACTER SET utf8 NOT NULL,`variables` varchar(500) CHARACTER SET utf8 NOT NULL,`active` tinyint(1) NOT NULL,`extra` varchar(3) CHARACTER SET utf8 NOT NULL,`description` text CHARACTER SET utf8,PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;";
	full_query($query);

	//Creating hooks
	require_once("smsclass.php");
	$class = new AktuelSms();
	$class->checkHooks();

	return array('status'=>'success','description'=>'Aktüel Sms succesfully activated :)');
}
use WHMCS\Database\Capsule;
function aktuel_sms_deactivate() {

	$query = "DROP TABLE `mod_aktuelsms_templates`";
	full_query($query);
	$query = "DROP TABLE `mod_aktuelsms_settings`";
	full_query($query);
	$query = "DROP TABLE `mod_aktuelsms_messages`";
	full_query($query);

	return array('status'=>'success','description'=>'Aktüel Sms succesfully deactivated :(');
}

function aktuel_sms_output($vars){
	$modulelink = $vars['modulelink'];
	$version = $vars['version'];
	$LANG = $vars['_lang'];
	putenv("TZ=Europe/Istanbul");

	$class = new AktuelSms();

	$tab = $_GET['tab'];
	
	$tabItems = [];
	$tabItems[] = [
		'link' => 'addonmodules.php?module=aktuel_sms&tab=settings',
		'name' => $LANG['settings'],
		'class' => ($tab == "settings"||empty($tab))?"active":null
	];
	$tabItems[] = [
		'link' => 'addonmodules.php?module=aktuel_sms&tab=templates&type=client',
		'name' => $LANG['clientsmstemplates'],
		'class' => ((@$_GET['type'] == "client")?"active":null)
	];
	$tabItems[] = [
		'link' => 'addonmodules.php?module=aktuel_sms&tab=templates&type=admin',
		'name' => $LANG['adminsmstemplates'],
		'class' => ((@$_GET['type'] == "admin")?"active":null)
	];
	$tabItems[] = [
		'link' => 'addonmodules.php?module=aktuel_sms&tab=sendbulk',
		'name' => $LANG['sendsms'],
		'class' => (($tab == "sendbulk")?"active":null)
	];
	$tabItems[] = [
		'link' => 'addonmodules.php?module=aktuel_sms&tab=messages',
		'name' => $LANG['messages'],
		'class' => (($tab == "messages")?"active":null)
	];

	echo '
	<div id="tabs">
		<ul class="nav nav-tabs">
	';
	foreach($tabItems AS $tabs){
		echo '
		<li role="presentation" class="'.$tabs['class'].'"><a href="'.$tabs['link'].'">'.$tabs['name'].'</a></li>
		';
	}
	echo '
		</ul>
	</div>
	';
	if (!isset($tab) || $tab == "settings")
	{
		/* UPDATE SETTINGS */
		if ($_POST['params']) {
			$update = array(
				"api" => $_POST['api'],
				"apiparams" => json_encode($_POST['params']),
				'wantsmsfield' => $_POST['wantsmsfield'],
				'gsmnumberfield' => $_POST['gsmnumberfield'],
				'dateformat' => $_POST['dateformat']
			);
			Capsule::table('mod_aktuelsms_settings')->update($update);
		}
		/* UPDATE SETTINGS */

		$settings = $class->getSettings();

		$apiparams = json_decode($settings['apiparams']);

		/* CUSTOM FIELDS START */
		$result = Capsule::table('tblcustomfields')
		->select('id','fieldname')
		->where("fieldtype",'like',"tickbox")
		->where("showorder",'like',"on")
		->get();

		$wantsms = '';
		foreach ($result AS $data) {
			$selected = ($data->id == $settings['wantsmsfield']) ? ' selected="selected"' : '';
			$wantsms .= '<option value="'.$data->id.'"'.$selected.'>'.$data->fieldname.'</option>';
		}

		$result = Capsule::table('tblcustomfields')
		->select('id','fieldname')
		->where("fieldtype",'like',"text")
		->where("showorder",'like',"on")
		->get();
		$gsmnumber = '';
		foreach ($result AS $key => $data) {
			$selected = ($data->id == $settings['gsmnumberfield']) ? ' selected="selected"' : '';
			$gsmnumber .= '<option value="' . $data->id . '" ' . $selected . '>' . $data->fieldname . '</option>';
		}
		/* CUSTOM FIELDS FINISH HIM */

		$classers = $class->getSenders();
		$classersoption = '';
		$classersfields = '';
		foreach($classers as $classer){
			$classersoption .= '<option value="'.$classer['value'].'" ' . (($settings['api'] == $classer['value'])?"selected=\"selected\"":"") . '>'.$classer['label'].'</option>';
			if($settings['api'] == $classer['value']){
				foreach($classer['fields'] as $field){
					$formType = 'text';
					if($field == "pass"){
						$formType = 'password';
					}
					$classersfields .=
						'<tr>
							<td class="fieldlabel" style="min-width:200px;">'.$LANG[$field].'</td>
							<td class="fieldarea"><input type="'.$formType.'" name="params['.$field.']" class="form-control input-inline input-300" size="40" value="' . $apiparams->$field . '"></td>
						</tr>';
				}
			}
		}

		echo '
		<script type="text/javascript">
			$(document).ready(function(){
				$("#api").change(function(){
					$("#form").submit();
				});
			});
		</script>
		<form action="" method="post" id="form">
		<input type="hidden" name="action" value="save" />
			<div style="text-align: left;background-color: whiteSmoke;margin: 0px;padding: 10px;">
				<table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">
					<tbody>
						<tr>
							<td class="fieldlabel" style="min-width:200px;">'.$LANG['sender'].'</td>
							<td class="fieldarea">
								<select name="api" id="api" class="form-control select-inline">
									'.$classersoption.'
								</select>
							</td>
						</tr>
						<tr>
							<td class="fieldlabel" style="min-width:200px;">'.$LANG['senderid'].'</td>
							<td class="fieldarea"><input type="text" name="params[senderid]" class="form-control input-inline input-300" size="40" value="' . $apiparams->senderid . '"> e.g:  AktuelHost</td>
						</tr>
						'.$classersfields.'
						<tr>
							<td class="fieldlabel" style="min-width:200px;">'.$LANG['signature'].'</td>
							<td class="fieldarea">
								<input type="text" name="params[signature]" class="form-control input-inline input-300" size="40" value="' . $apiparams->signature . '"> e.g:  www.aktuelsistem.com
							</td>
						</tr>
						<tr>
							<td class="fieldlabel" style="min-width:200px;">'.$LANG['wantsmsfield'].'</td>
							<td class="fieldarea">
								<select name="wantsmsfield" class="form-control select-inline">
									' . $wantsms . '
								</select>
							</td>
						</tr>

						<tr>
							<td class="fieldlabel" style="min-width:200px;">'.$LANG['gsmnumberfield'].'</td>
							<td class="fieldarea">
								<select name="gsmnumberfield" class="form-control select-inline">
									' . $gsmnumber . '
								</select>
							</td>
						</tr>
						<tr>
							<td class="fieldlabel" style="min-width:200px;">'.$LANG['dateformat'].'</td>
							<td class="fieldarea"><input type="text" name="dateformat" class="form-control input-inline input-300" size="40" value="' . $settings['dateformat'] . '"> e.g:  %d.%m.%y ('.date("d.m.Y").')</td>
						</tr>
					</tbody>
				</table>
			</div>
			<p align="center"><input type="submit" class="btn" value="'.$LANG['save'].'" class="button" /></p>
		</form>
		';
	}
	elseif ($tab == "templates")
	{
		if ($_POST['submit']) {
			$result = Capsule::table('mod_aktuelsms_templates')
			->where("type","like",$_GET['type'])
			->get();
			foreach ($result AS $data) {
				if ($_POST[$data->id . '_active'] == "on") {
					$tmp_active = 1;
				} else {
					$tmp_active = 0;
				}
				$update = array(
					"template" => $_POST[$data->id . '_template'],
					"active" => $tmp_active
				);

				if(isset($_POST[$data->id . '_extra'])){
					$update['extra']= trim($_POST[$data->id . '_extra']);
				}
				if(isset($_POST[$data->id . '_admingsm'])){
					$update['admingsm']= $_POST[$data->id . '_admingsm'];
					$update['admingsm'] = str_replace(" ","",$update['admingsm']);
				}
				Capsule::table('mod_aktuelsms_templates')
				->where('id', $data->id)
				->update($update);
			}
		}

		echo '<form action="" method="post">
		<input type="hidden" name="action" value="save" />
			<div style="text-align: left;background-color: whiteSmoke;margin: 0px;padding: 10px;">
				<table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">
					<tbody>';
		$result = Capsule::table('mod_aktuelsms_templates')
		->where("type","like",$_GET['type'])
		->get();
		foreach ($result AS $data) {
			if ($data->active == 1) {
				$active = 'checked = "checked"';
			} else {
				$active = '';
			}
			$desc = json_decode($data->description);
			if(isset($desc->$LANG['lang'])){
				$name = $desc->$LANG['lang'];
			}else{
				$name = $data->name;
			}
			echo '
				<tr>
					<td class="fieldlabel" style="max-width:200px;">' . $name . '</td>
					<td class="fieldarea">
						<textarea class="form-control" cols="50" name="' . $data->id . '_template">' . $data->template . '</textarea>
					</td>
				</tr>';
			echo '
			<tr>
				<td class="fieldlabel" style="max-width:200px;" style="float:right;">'.$LANG['active'].'</td>
				<td class="fieldarea"><input type="checkbox" value="on" name="' . $data->id . '_active" ' . $active . '></td>
			</tr>
			';
			echo '
			<tr>
				<td class="fieldlabel" style="max-width:200px;" style="float:right;">'.$LANG['parameter'].'</td>
				<td class="fieldarea">' . $data->variables . '</td>
			</tr>
			';

			if(!empty($data->extra)){
				echo '
				<tr>
					<td class="fieldlabel" style="max-width:200px;">'.$LANG['ekstra'].'</td>
					<td class="fieldarea">
						<input type="text" class="form-control" name="'.$data->id.'_extra" value="'.$data->extra.'">
					</td>
				</tr>
				';
			}
			if($_GET['type'] == "admin"){
				echo '
				<tr>
					<td class="fieldlabel" style="max-width:200px;">'.$LANG['admingsm'].'</td>
					<td class="fieldarea">
						<input type="text" class="form-control" name="'.$data->id.'_admingsm" value="'.$data->admingsm.'">
						'.$LANG['admingsmornek'].'
					</td>
				</tr>
				';
			}
			echo '<tr>
				<td colspan="2"><hr></td>
			</tr>';
		}
		echo '
		</tbody>
				</table>
			</div>
			<p align="center"><button type="submit" name="submit" class="btn btn-primary">Save Changes</button></p>
		</form>';

	}
	elseif ($tab == "messages")
	{
		if(!empty($_GET['deletesms'])){
			$smsid = (int)$_GET['deletesms'];
			Capsule::table('mod_aktuelsms_messages')->where('id',$smsid)->delete();
		}
		echo  '
		<!--<script src="http://ajax.aspnetcdn.com/ajax/jquery.dataTables/1.9.4/jquery.dataTables.min.js"></script>
		<link rel="stylesheet" href="http://ajax.aspnetcdn.com/ajax/jquery.dataTables/1.9.4/css/jquery.dataTables.css" type="text/css">
		<link rel="stylesheet" href="http://ajax.aspnetcdn.com/ajax/jquery.dataTables/1.9.4/css/jquery.dataTables_themeroller.css" type="text/css">
		<script type="text/javascript">
			$(document).ready(function(){
				$(".datatable").dataTable();
			});
		</script>-->

		<div style="text-align: left;background-color: whiteSmoke;margin: 0px;padding: 10px;">
		<table class="datatable" border="0" cellspacing="1" cellpadding="3">
		<thead>
			<tr>
				<th><input type="text" name="id" class="form-control input-inline input-100" /></th>
				<th><input type="text" name="client" class="form-control input-inline input-300" /></th>
				<th><input type="text" name="gsmnumber"  class="form-control input-inline input-300" /></th>
				<th><input type="text" name="message" class="form-control input-inline input-300" /></th>
				<th><input type="date" name="datetime" class="form-control input-inline input-300" /></th>
				<th><select class="form-control" name="status"><option></option><option>success</option><option>error</option></select></th>
				<th></th>
			</tr>
			<tr>
				<th>#</th>
				<th>'.$LANG['client'].'</th>
				<th>'.$LANG['gsmnumber'].'</th>
				<th>'.$LANG['message'].'</th>
				<th>'.$LANG['datetime'].'</th>
				<th>'.$LANG['status'].'</th>
				<th width="20"></th>
			</tr>
		</thead>
		<tbody>
		';

if($_GET['page'] == ""){
	$page = 1;
}elseif(intval($_GET['page']) == 0 ){
	$page = 1;
}elseif($page < 0 ){
	$page = 1;
}else{
	$page = intval($_GET['page']);
}
$nowPage = $page;
$kacar = 50;
$start = ($page -1 ) * $kacar;


$sayfaQuery = Capsule::table('mod_aktuelsms_messages AS m')
	->select('m.*', 'user.firstname', 'user.lastname')
	->join('tblclients AS user', 'm.user', '=', 'user.id')
	->orderBy('m.datetime', 'desc');

$kayitsayisi = $sayfaQuery->count();

		/* Getting messages order by date desc */
$sql = $sayfaQuery->skip($start)->take($kacar);

		/*$sql = "SELECT `m`.*,`user`.`firstname`,`user`.`lastname`
		FROM `mod_aktuelsms_messages` as `m`
		JOIN `tblclients` as `user` ON `m`.`user` = `user`.`id`
		ORDER BY `m`.`datetime` DESC LIMIT ".$start.",".$kacar;*/
		$result = $sql->get();
$sayfasayisi = ceil($kayitsayisi / $kacar);
if($page > $sayfasayisi){
	$page =	$sayfasayisi;
}
	   
		foreach ($result AS $data) {
			if($data->msgid && $data->status == ""){
				$status = $class->getReport($data->msgid);
				full_query("UPDATE mod_aktuelsms_messages SET status = '$status' WHERE id = ".$data->id."");
			}else{
				$status = $data->status;
			}

			
			echo  '<tr>
			<td>'.$data->id.'</td>
			<td><a href="clientssummary.php?userid='.$data->user.'">'.$data->firstname.' '.$data->lastname.'</a></td>
			<td>'.$data->to.'</td>
			<td>'.$data->text.'</td>
			<td>'.$data->datetime.'</td>
			<td>'.$LANG[$status].'</td>
			<td><a href="addonmodules.php?module=aktuel_sms&tab=messages&deletesms='.$data->id.'" title="'.$LANG->delete.'"><i class="fa fa-minus-circle"></i></a></td></tr>';
		}
		/* Getting messages order by date desc */

		echo '
		</tbody>
		</table>
		';

if($sayfasayisi>0){
	$pageUrl='addonmodules.php?module=aktuel_sms&amp;tab=messages&amp;page=';
	echo '<ul class="pagination pagination-search pull-right">';
		$undoUrl = '#';
		$undoClass = 'disabled';
		if($nowPage > 1){
			$undoUrl = $pageUrl.($nowPage-1);
			$undoClass = '';
		}
		echo '<li class="'.$undoClass.'"><a href="'.$undoUrl.'"><i class="fa fa-angle-left"></i></a></li>';
	for($j = 1;$j <= $sayfasayisi;$j++){
		$active = ($nowPage == $j) ? ' class="active"' : '';
		echo '<li'.$active.'><a href="'.$pageUrl.$j.'">'.$j.'</a></li>';
	}
echo '
	<li><a href="#"><i class="fa fa-angle-right"></i></a></li>
</ul>';
}
		echo '</div>';

	}
	elseif($tab=="sendbulk")
	{
		$settings = $class->getSettings();

		if(!empty($_POST['client'])){
			$client = $_POST['client'];

			foreach($client AS $cl){
				$userinf = explode("_",$cl);
				$userid = $userinf[0];
				$gsmnumber = $userinf[1];

				$class->setGsmnumber($gsmnumber);
				$class->setMessage($_POST['message']);
				$class->setUserid($userid);

				$result = $class->send();
				if($result == false){
					echo $class->getErrors();
				}else{
					echo $LANG['smssent'].' '.$gsmnumber;
				}

				if($_POST["debug"] == "ON"){
					$debug = 1;
				}
			}
		}

		$result = Capsule::select("SELECT `a`.`id`,`a`.`firstname`, `a`.`lastname`, `b`.`value` as `gsmnumber`
		FROM `tblclients` as `a`
		JOIN `tblcustomfieldsvalues` as `b` ON `b`.`relid` = `a`.`id`
		JOIN `tblcustomfieldsvalues` as `c` ON `c`.`relid` = `a`.`id`
		WHERE `b`.`fieldid` = '".$settings['gsmnumberfield']."'
		AND `c`.`fieldid` = '".$settings['wantsmsfield']."'
		AND `c`.`value` = 'on' order by `a`.`firstname`");
		$clients = '';
		foreach ($result AS $data) {
			$clients .= '<option value="'.$data->id.'_'.$data->gsmnumber.'">'.$data->firstname.' '.$data->lastname.' (#'.$data->id.')</option>';
		}
		echo '
		<script>
		jQuery.fn.filterByText = function(textbox, selectSingleMatch) {
		  return this.each(function() {
			var select = this;
			var options = [];
			$(select).find("option").each(function() {
			  options.push({value: $(this).val(), text: $(this).text()});
			});
			$(select).data("options", options);
			$(textbox).bind("change keyup", function() {
			  var options = $(select).empty().scrollTop(0).data("options");
			  var search = $.trim($(this).val());
			  var regex = new RegExp(search,"gi");

			  $.each(options, function(i) {
				var option = options[i];
				if(option.text.match(regex) !== null) {
				  $(select).append(
					 $("<option>").text(option.text).val(option.value)
				  );
				}
			  });
			  if (selectSingleMatch === true && 
				  $(select).children().length === 1) {
				$(select).children().get(0).selected = true;
			  }
			});
		  });
		};
		$(function() {
		  $("#clientdrop").filterByText($("#textbox"), true);
		});  
		</script>';
		echo '<form action="" method="POST" enctype="multipart/form-data">
		<input type="hidden" name="action" value="save" />
			<div style="text-align: left;background-color: whiteSmoke;margin: 0px;padding: 10px;">
				<table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">
					<tbody>
						<tr>
							<td class="fieldlabel">'.$LANG['client'].'</td>
							<td class="fieldarea">
								<input class="form-control" id="textbox" type="text" placeholder="Filtrelemek İçin İsim Yazınız"><br>
								<select class="form-control" name="client[]" multiple id="clientdrop" required>
									<option value="" disabled>'.$LANG['selectclient'].'</option>
									' . $clients . '
								</select>
							</td>
						</tr>
						<tr>
							<td class="fieldlabel">'.$LANG['message'].'</td>
							<td class="fieldarea">
							   <textarea class="form-control" cols="70" rows="20" name="message" style="padding:5px" required></textarea>
							</td>
						</tr>
						<tr>
							<td class="fieldlabel">'.$LANG['debug'].'</td>
							<td class="fieldarea"><label for="debug"><input type="checkbox" id="debug" name="debug" value="ON" /> Log bilgilerini yazdırmak için işaretleyiniz.</label></td>
						</tr>
					</tbody>
				</table>
			</div>
			<p align="center"><input type="submit" value="'.$LANG['send'].'" class="button" /></p>
		</form>';

		if(isset($debug)){
			echo $class->getLogs();
		}
	}
	elseif($tab == "update"){
		$currentversion = file_get_contents("https://raw.github.com/AktuelSistem/WHMCS-SmsModule/master/version.txt");
		echo '<div style="text-align: left;background-color: whiteSmoke;margin: 0px;padding: 10px;">';
		if($version != $currentversion){
			echo $LANG['newversion'];
		}else{
			echo $LANG['uptodate'].'<br><br>';
		}
		echo '</div>';
	}

	$credit =  $class->getBalance();
	if($credit){
		echo '
			<div style="text-align: left;background-color: whiteSmoke;margin: 0px;padding: 10px;">
			<b>'.$LANG['credit'].':</b> '.$credit.'
			</div>';
	}

	echo $LANG['lisans'];
}
