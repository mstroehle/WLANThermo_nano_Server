<?php
 /*************************************************** 
    Copyright (C) 2018  Florian Riedl
    ***************************
		@author Florian Riedl
		@version 0.3, 29/12/18
	***************************
	This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
    HISTORY: Please refer Github History
    
 ****************************************************/
//-----------------------------------------------------------------------------
// error reporting
 error_reporting(E_ALL); 
//-----------------------------------------------------------------------------
// start runtome counter
 $time_start = microtime(true);
//-----------------------------------------------------------------------------
// include Logging libary 
$logfile = '_api.log'; // global var for logger class filename
$logpath = '../logs/';  // global var for logger class filepath
require_once("../include/logger.php"); // logger class
//-----------------------------------------------------------------------------
// include database config
require_once("../config.inc.php"); // 
//-----------------------------------------------------------------------------
// test JSON
$test = '{
	"device": {
		"device": "nano",
		"serial": "84d1ac",
		"hw_version": "1",
		"sw_version": "v0.9.7"
	},
	"update": {
		"available": true
	},
	"cloud": {
		"task": "save",
		"api_token": "blablabla",
		"data":[] 
	},	
	"notification": {
		"task": "alert",
		"channel": 1,
		"message": "up",
		"lang": "de",
		"services": [{
			"service": "telegram",
			"key1": "xxx",
			"key2": "xxx"
		},{
			"service": "pushover",
			"key1": "xxx",
			"key2": "xxx"
		},{
			"service": "mail",
			"adress": "xxx"
		}]
	},
	"history": {
		"task": "save",
		"api_token": "blablabla",
		"data":[]
	},
	"url":{
		"api": {
			"host": "api.wlanthermo.de",
			"page": "/index.php"
		},
		"firmware": {
			"host": "update.wlanthermo.de",
			"page": "/getFirmware.php"
		},
		"spiffs": {
			"host": "update.wlanthermo.de",
			"page": "/getSpiffs.php"
		},
		"cloud": {
			"host": "cloud.wlanthermo.de",
			"page": "/saveData.php"
		},
		"notification": {
			"host": "message.wlanthermo.de",
			"page": "/message.php"
		}
	}
}';

//-----------------------------------------------------------------------------
// read post data
$json = file_get_contents('php://input');
// define json array
$JsonArr = array();
// decode post data to json
$JsonArr = json_decode( $json, true );
// check json error
if ($JsonArr === null && json_last_error() !== JSON_ERROR_NONE) {
    SimpleLogger::error("JSON invalide\n");
	SimpleLogger::debug("".$json."\n");
	die(false);
}
//-----------------------------------------------------------------------------
// main 

if(checkDeviceJson($JsonArr)){
	// Connecting to database
	try {
		$dbh = new PDO(sprintf('mysql:host=%s;dbname=%s', $db_server, $db_name), $db_user, $db_pass);
		$dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
		$dbh->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
	} catch (PDOException $e) {
		SimpleLogger::error("Database - An error has occurred\n");
		SimpleLogger::log(SimpleLogger::DEBUG, $e->getMessage() . "\n");
		die('false');
	}
	
	foreach($JsonArr as $key => $value){
		switch ($key) {
			case 'update':	
				$JsonArr = createUpdateJson($dbh,$JsonArr);
				break;
			case 'cloud':
				$JsonArr = createCloudJson($dbh,$JsonArr);
				break;
			case 'history':
				$JsonArr = createHistoryJson($dbh,$JsonArr);
				break;
			case 'notification':
				$JsonArr = createNotificationJson($JsonArr);
				break;
			case 'alexa':
				$JsonArr = createAlexaJson($dbh,$JsonArr);
				break;
		}
	}
	$JsonArr['runtime'] = (microtime(true) - $time_start);
	$json = json_encode($JsonArr, JSON_UNESCAPED_SLASHES);	
	header('Access-Control-Allow-Origin: *'); 
	//header('Content-type: text/html; charset=utf-8');
	header('Content-Type: application/json');
	//header("Transfer-encoding: chunked");

	header("Content-Length: ".strlen($json));
	//	flush();
	//	ob_flush();
	//dump_chunk($json);
	echo $json;	
}else{
	SimpleLogger::error("(checkDeviceJson) JSON device bad\n");
	SimpleLogger::debug("".$json."\n");
	die(false);
}

//-----------------------------------------------------------------------------
// WLANThermo API functions

function checkDeviceJson($JsonArr){
	if (isset($JsonArr['device']['device']) AND !empty($JsonArr['device']['device']) AND isset($JsonArr['device']['serial']) AND !empty($JsonArr['device']['serial']) AND isset($JsonArr['device']['hw_version']) AND !empty($JsonArr['device']['hw_version']) AND isset($JsonArr['device']['sw_version']) AND !empty($JsonArr['device']['sw_version'])){
		return true;
	}else{
		return false;
	}
}

function dump_chunk($chunk)
{
    echo sprintf("%x\r\n", strlen($chunk));
    echo $chunk;
    echo "\r\n";
}

function createUpdateJson($dbh,$JsonArr){
	if(checkDeviceDatabase($dbh,$JsonArr)){
		if (isset($JsonArr['update']['version'])){
			$newVersion = checkVersion($dbh,$JsonArr);
		}else{
			$newVersion = checkNewUpdate($dbh,$JsonArr);
		}
		
		if ($newVersion != 'false'){
			$JsonArr['update']['available'] = 'true';
			$JsonArr['update']['version'] = $newVersion;
			$JsonArr['update']['firmware']['url'] = 'http://update.wlanthermo.de/getFirmware.php?device='.$JsonArr['device']['device'].'&serial='.$JsonArr['device']['serial'].'&version='.$JsonArr['update']['version'].'';
			$JsonArr['update']['spiffs']['url'] = 'http://update.wlanthermo.de/getSpiffs.php?device='.$JsonArr['device']['device'].'&serial='.$JsonArr['device']['serial'].'&version='.$JsonArr['update']['version'].'';
			return $JsonArr;
		}else{
			$JsonArr['update']['available'] = 'false';
			return $JsonArr;
		}
	}else{
		SimpleLogger::error("An error has occurred - (createUpdateJson)\n");
		die(false);		
	}	
}

function createCloudJson($dbh,$JsonArr){
	if(checkCloudJson){
		switch ($JsonArr['cloud']['task']) {
			case 'save':
				if (insertCloudData($dbh,$JsonArr)){
					$JsonArr['cloud']['task'] = 'true';
					unset($JsonArr['cloud']['data']);
				}else{
					$JsonArr['cloud']['task'] = 'false';
					unset($JsonArr['cloud']['data']);
				}
				break;
			case 'read':
				// todo
				break;		
		}
	}else{
		$JsonArr['cloud']['task'] = 'false';	
		SimpleLogger::debug("Json false - ".json_encode($JsonArr['cloud'], JSON_UNESCAPED_SLASHES)."(createCloudJson)\n");
	}
	return $JsonArr;
}

function createHistoryJson($dbh,$JsonArr){
	if (isset($JsonArr['history']['task']) AND !empty($JsonArr['history']['task'])){
		switch ($JsonArr['history']['task']) {
			case 'save':
				if (isset($JsonArr['history']['api_token']) AND !empty($JsonArr['history']['api_token'])){			
					if (insertHistoryData($dbh,$JsonArr)){
						$JsonArr['history']['task'] = 'true';
						unset($JsonArr['history']['data']);
					}else{
						$JsonArr['history']['task'] = 'false';
						unset($JsonArr['history']['data']);
					}
				}else{
					$JsonArr['history']['task'] = 'false';	
					SimpleLogger::debug("Json false - ".json_encode($JsonArr['history'], JSON_UNESCAPED_SLASHES)."(createHistoryJson)\n");
				}
				break;
			case 'read':
				// todo
				break;		
		}
	}else{
		$JsonArr['history']['task'] = 'false';	
		SimpleLogger::debug("Json false - ".json_encode($JsonArr['history'], JSON_UNESCAPED_SLASHES)."(createHistoryJson)\n");
	}
	return $JsonArr;
}

function checkCloudJson($dbh,$JsonArr){
	if (isset($JsonArr['cloud']['task']) AND !empty($JsonArr['cloud']['task']) AND isset($JsonArr['cloud']['api_token']) AND !empty($JsonArr['cloud']['api_token'])){
		return true;
	}else{
		return false;
	}
}
	
function checkNotificationJson($dbh,$JsonArr){
	if (isset($JsonArr['notification']['task']) AND !empty($JsonArr['notification']['task'])){
		return true;
	}else{
		return false;
	}
}
		
function insertCloudData($dbh,$JsonArr){	
	if (isset($JsonArr['cloud']['data']) AND !empty($JsonArr['cloud']['data'])){
		try {
			$sql = "INSERT INTO `cloud` (`serial`, `api_token`, `data`) VALUES (:serial, :api_token, :data)";
			$statement = $dbh->prepare($sql);
			$statement->bindValue(':serial', $JsonArr['device']['serial']);
			$statement->bindValue(':api_token', $JsonArr['cloud']['api_token']);
			foreach($JsonArr['cloud']['data'] as $key => $data){			
				$statement->bindValue(':data', json_encode($data, JSON_UNESCAPED_SLASHES));
				$statement->execute();
			}		
			return true;
		} catch (PDOException $e) {
			SimpleLogger::error("An error has occurred - (insertCloudData)\n");
			SimpleLogger::log(SimpleLogger::DEBUG, $e->getMessage() . "\n");
			return false;
		}
	}else{
		return false;
	}
}

function insertHistoryData($dbh,$JsonArr){	
		try {
		$sql = "SELECT data FROM `cloud` WHERE api_token= :api_token AND `time` > TIMESTAMP(DATE_SUB(NOW(), INTERVAL :history_time hour)) order by `id` asc";
		$statement = $dbh->prepare($sql);
		$statement->bindValue(':api_token', $api_token);
		$statement->bindValue(':history_time', $api_time);
		$statement->execute();
		$statement->setFetchMode(PDO::FETCH_ASSOC);
		$data = array();
		if ($statement->rowCount() > 0) {
			foreach($statement as $daten) {
				$obj = json_decode( $daten['data'], true );
				if ($obj === null && json_last_error() !== JSON_ERROR_NONE) {
					//ToDo Error Hadling
				}else{
					$arr = array(); 
					$arr['system']['time'] = $obj['system']['time'];
					$arr['system']['soc'] = $obj['system']['soc'];
					foreach ( $obj['channel'] as $key => $value )
					{
						$arr['channel'][$key]['temp'] = $value['temp'];
					}
					if(isAssoc($obj['pitmaster'])){
						foreach ($obj['pitmaster'] as $key => $value)
						{
							$arr['pitmaster'][$key]['value'] = $value['value'];
							$arr['pitmaster'][$key]['set'] = $value['set'];
							$arr['pitmaster'][$key]['typ'] = $value['typ'];
						}					
					}else{
						$arr['pitmaster'][0]['value'] = $obj['pitmaster']['value'];
						$arr['pitmaster'][0]['set'] = $obj['pitmaster']['set'];
						$arr['pitmaster'][0]['typ'] = $obj['pitmaster']['typ'];						
					}
					array_push($data, $arr);
				}
			}
			$sql = "INSERT INTO `history` (`data`) VALUES (:data)";
			$statement = $dbh->prepare($sql);			
			$statement->bindValue(':data', json_encode($data, JSON_UNESCAPED_SLASHES));
			$statement->execute();
				
				//return(json_encode($data));
		} else {
			return false;
		}

		
		$sql = "INSERT INTO `cloud` (`serial`, `api_token`, `data`) VALUES (:serial, :api_token, :data)";
		$statement = $dbh->prepare($sql);
		$statement->bindValue(':serial', $JsonArr['device']['serial']);
		$statement->bindValue(':api_token', $JsonArr['cloud']['api_token']);
		foreach($JsonArr['cloud']['data'] as $key => $data){			
			$statement->bindValue(':data', json_encode($data, JSON_UNESCAPED_SLASHES));
			$statement->execute();
		}	
		
		return true;
	} catch (PDOException $e) {
		SimpleLogger::error("An error has occurred - (insertHistoryData)\n");
		SimpleLogger::log(SimpleLogger::DEBUG, $e->getMessage() . "\n");
		return false;
	}
}

function checkDeviceDatabase($dbh,$JsonArr){
	try {
		$sql = "INSERT INTO `devices` (`device`,`serial`, `name`, `hardware_version`, `software_version`, `update_active`, `whitelist`) 
				VALUES (:device, :serial, :name, :hardware_version, :software_version, :update_active, :whitelist) 
				ON DUPLICATE KEY UPDATE device=VALUES(device), hardware_version=VALUES(hardware_version), software_version=VALUES(software_version)";
		$statement = $dbh->prepare($sql);
		$statement->bindValue(':device', $JsonArr['device']['device']);
		$statement->bindValue(':serial', $JsonArr['device']['serial']);
		$statement->bindValue(':name', '');
		$statement->bindValue(':hardware_version', $JsonArr['device']['hw_version']);
		$statement->bindValue(':software_version', $JsonArr['device']['sw_version']);
		$statement->bindValue(':update_active', '1');
		$statement->bindValue(':whitelist', '0');
		$inserted = $statement->execute();
		if($inserted){
			return true;
		}else{
			return false;
		}
		$statement = null;
	} catch (PDOException $e) {
		SimpleLogger::error("An error has occurred - (checkDeviceDatabase)\n");
		SimpleLogger::log(SimpleLogger::DEBUG, $e->getMessage() . "\n");
		die('false');
	}
}

function checkNewUpdate($dbh,$JsonArr){
	try {
		$sql = "select s1.software_version from sw_versions as s1, 
				(SELECT d.serial, max(s.software_id) as software_id FROM `devices` as d, sw_versions as s WHERE 
				d.device = s.device and d.update_active = 1 and (d.whitelist = 1 or s.prerelease = 0) and d.serial = :serial
				group by d.serial) as s2
				where 
				s1.software_id = s2.software_id";
		$statement = $dbh->prepare($sql);
		$statement->bindValue(':serial', $JsonArr['device']['serial']);
		$statement->execute();
		$statement->setFetchMode(PDO::FETCH_ASSOC);
		if ($statement->rowCount() > 0) {
		  $deviceInfo = $statement->fetch();
		  return compareVersion($deviceInfo['software_version'],$JsonArr['device']['sw_version']);
		} else {
		  return('false');
		}
	} catch (PDOException $e) {
		SimpleLogger::error("An error has occurred - (checkNewUpdate)\n");
		SimpleLogger::log(SimpleLogger::DEBUG, $e->getMessage() . "\n");
		return('false');
	}	
}

function checkVersion($dbh,$JsonArr){
	try {
		$sql = "select s1.software_version from sw_versions as s1, 
				(SELECT d.serial, max(s.software_id) as software_id FROM `devices` as d, sw_versions as s WHERE 
				d.device = s.device and d.update_active = 1 and d.serial = :serial and s.software_version = :version
                group by d.serial) as s2
				where 
				s1.software_id = s2.software_id";
		$statement = $dbh->prepare($sql);
		$statement->bindValue(':serial', $JsonArr['device']['serial']);
		$statement->bindValue(':version', $JsonArr['update']['version']);
		$statement->execute();
		$statement->setFetchMode(PDO::FETCH_ASSOC);
		if ($statement->rowCount() > 0) {
		  $deviceInfo = $statement->fetch();
		  return($deviceInfo['software_version']);
		} else {
		  return('false');
		}
	} catch (PDOException $e) {
		SimpleLogger::error("An error has occurred - (checkVersion)\n");
		SimpleLogger::log(SimpleLogger::DEBUG, $e->getMessage() . "\n");
		return('false');
	}	
}
//-----------------------------------------------------------------------------
// compare version numbers
function compareVersion($dbVersion, $deviceVersion){
	if (version_compare($dbVersion, $deviceVersion, ">")) {
		return $dbVersion;
	}else{
		return('false');
	}
}

function createAlexaJson($dbh,$JsonArr){
	if(checkAlexaJson($JsonArr)){
		switch ($JsonArr['alexa']['task']) {
			case 'save':
				if (insertAlexaKey($dbh,$JsonArr)){
					$JsonArr['alexa']['task'] = 'true';
				}else{
					$JsonArr['alexa']['task'] = 'false';
				}
				break;
			case 'delete':
				$JsonArr['alexa']['token'] = NULL;
				if (insertAlexaKey($dbh,$JsonArr)){
					$JsonArr['alexa']['task'] = 'true';
				}else{
					$JsonArr['alexa']['task'] = 'false';
				}
				unset($JsonArr['alexa']['token']);
				break;				
		}
	}else{
		$JsonArr['alexa']['task'] = 'false';	
		SimpleLogger::debug("Json false - ".json_encode($JsonArr['alexa'], JSON_UNESCAPED_SLASHES)."(createAlexaJson)\n");
	}
	return $JsonArr;	
}

function checkAlexaJson($JsonArr){
	if (isset($JsonArr['alexa']['task']) AND !empty($JsonArr['alexa']['task'])){
		return true;
	}else{
		return false;
	}
}

function insertAlexaKey($dbh,$JsonArr){
	try {			
		$sql = "UPDATE `devices` 
				SET `amazon_token` = :token 
				WHERE `serial` = :serial";
		$statement = $dbh->prepare($sql);
		$statement->bindValue(':serial', $JsonArr['device']['serial']);
		$statement->bindValue(':token', $JsonArr['alexa']['token']);
		$statement->execute();			
		return true;
	} catch (PDOException $e) {
		SimpleLogger::error("An error has occurred - (insertAlexaKey)\n");
		SimpleLogger::log(SimpleLogger::DEBUG, $e->getMessage() . "\n");
		return false;
	}
}

function createNotificationJson($JsonArr){
	if(checkCloudJson){
		switch ($JsonArr['notification']['task']) {
			case 'alert':
				sendNotification($JsonArr);
				break;
		}
	}else{
		//$JsonArr['cloud']['task'] = 'false';	
		//SimpleLogger::debug("Json false - ".json_encode($JsonArr['cloud'], JSON_UNESCAPED_SLASHES)."(createUpdateJson)\n");
	}
	return $JsonArr;
}

function sendNotification($JsonArr){
	foreach($JsonArr['notification']['services'] as $key => $value){
		switch ($value['service']) {
			case 'telegram':	
				sendTelegram($JsonArr,$value);
				break;
			case 'pushover':
				sendPushover($JsonArr,$value);
				break;
			case 'mail':
				// ToDo
				break;
		}
	}
}

function getMsg($JsonArr){
	$de_alert_up = 'ACHTUNG! Kanal %s: Temperatur (%s°%s) ist zu hoch (%s°%s)';
	$de_alert_down = 'ACHTUNG! Kanal %s: Temperatur (%s°%s) ist zu tief (%s°%s)';
	$en_alert_up = 'ATTENTION! Channel %s: Temperature (%s°%s) is too high (%s°%s)';
	$en_alert_down = 'ATTENTION!  Channel %s: Temperature (%s°%s) is too low (%s°%s)';
	
	$de_alert_battery = 'Achtung: Die Batterieladung ist niedrig! Bitte ein Netzteil anschließen.';
	$en_alert_battery = 'Attention: Battery charge is low! Please connect a power adapter.';
	$de_alert_test = 'Testnachricht erfolgreich gesendet. Deine Einstellungen sind korrekt.';
	$en_alert_test = 'Message sent successfully. Your settings are correct.';
	
	
	switch ($JsonArr['notification']['lang']) {
		case de:
			if($JsonArr['notification']['message'] == 'up'){
			return sprintf($de_alert_up, $JsonArr['notification']['channel'],$JsonArr['notification']['temp'][0],$JsonArr['notification']['unit'],$JsonArr['notification']['temp'][1],$JsonArr['notification']['unit']);
			}else if($JsonArr['notification']['message'] === 'down'){
				return sprintf($de_alert_down, $JsonArr['notification']['channel'],$JsonArr['notification']['temp'][0],$JsonArr['notification']['unit'],$JsonArr['notification']['temp'][1],$JsonArr['notification']['unit']);
			}else if($JsonArr['notification']['message'] === 'battery'){
				return $de_alert_battery;	
			}else if($JsonArr['notification']['message'] === 'test'){
				return $de_alert_test;
			}
			break;
		case en:
			if($JsonArr['notification']['message'] == 'up'){
				return sprintf($en_alert_up, $JsonArr['notification']['channel'],$JsonArr['notification']['temp'][0],$JsonArr['notification']['unit'],$JsonArr['notification']['temp'][1],$JsonArr['notification']['unit']);
			}else if($JsonArr['notification']['message'] === 'down'){
				return sprintf($en_alert_down, $JsonArr['notification']['channel'],$JsonArr['notification']['temp'][0],$JsonArr['notification']['unit'],$JsonArr['notification']['temp'][1],$JsonArr['notification']['unit']);
			}else if($JsonArr['notification']['message'] === 'battery'){
				return $en_alert_battery;	
			}else if($JsonArr['notification']['message'] === 'test'){
				return $en_alert_test;
			}
		default:
			if($JsonArr['notification']['message'] == 'up'){
				return sprintf($en_alert_up, $JsonArr['notification']['channel'],$JsonArr['notification']['temp'][0],$JsonArr['notification']['unit'],$JsonArr['notification']['temp'][1],$JsonArr['notification']['unit']);
			}else if($JsonArr['notification']['message'] === 'down'){
				return sprintf($en_alert_down, $JsonArr['notification']['channel'],$JsonArr['notification']['temp'][0],$JsonArr['notification']['unit'],$JsonArr['notification']['temp'][1],$JsonArr['notification']['unit']);
			}else if($JsonArr['notification']['message'] === 'battery'){
				return $en_alert_battery;	
			}else if($JsonArr['notification']['message'] === 'test'){
				return $en_alert_test;
			}
	}
}

function sendTelegram($JsonArr,$services){	
	$url = 'https://api.telegram.org/bot' . $services['key1'] . '/sendMessage?text="'.getMsg($JsonArr).'"&chat_id='.$services['key2'].'';
	$result = json_decode(file_get_contents($url));
	if($result->ok === true){
		SimpleLogger::info("Message has been sent! \n");
	}else{
		SimpleLogger::error("Message could not be sent! \n");		
	}
}

function sendPushover($JsonArr,$services){
	curl_setopt_array($ch = curl_init(), array(
	  CURLOPT_URL => "https://api.pushover.net/1/messages.json",
	  CURLOPT_POSTFIELDS => array(
		"token" => $services['key1'],
		"user" => $services['key2'],
		"message" => getMsg($JsonArr),
	  ),
	  CURLOPT_SAFE_UPLOAD => true,
	  CURLOPT_RETURNTRANSFER => true,
	));
	curl_exec($ch);
	curl_close($ch);
}

?>