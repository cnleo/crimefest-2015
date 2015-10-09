<?php header('Content-Type: text/plain; charset=utf-8'); date_default_timezone_set("UTC");

/**
 * U need a cron-job and a mail service on your Server (mostly include on share-host too)
 * To get mail notification about new/changes on challenges from http://www.overkillsoftware.com/games/roadtocrimefest/
 * It creates an json file (inclusive an archive) with all the information you can found on overkill crimefest page 
 * and build only a new json file if something has changed. 
 **/
 
/**
 * 
 * What it does:
 * 
 * parse the http://www.overkillsoftware.com/games/roadtocrimefest/ page
 * build Json file with all information we need (and few more)
 * (default: saves as current file named "crimefest.json" and as "archive/crimefest-[date]-utc.json")
 * check the new json with old json file
 * if current parsed json changed since last one, it will replace the crimefest.json and
 * inform you via email that something changed
 *
 **/

 // SET IT UP:
$emailNotifiation = false; //false will disable email notification

$yourMailAdress = 'your@domain.tld'; // not needed if notification set as false

// true means you get only mail notification on changes
// false means every time you request this file you get a mail notification, even of no changes.
$onlyOnChangesNotification = false;

// at the moment better not change this:
$pathToArchive = 'archive/'; //only folder with appendix a / (slash) //will fix that
// at the moment better not change this:
$pathToCrimefestJson = ''; // '' means same folder as this file here //will fix it later


// NO SET UP NEEDED:

$crimefestURL = 'http://www.overkillsoftware.com/games/roadtocrimefest/';
//$crimefestURL = 'http://localhost/crimefest-2015/';

$crimefestContent = file_get_contents($crimefestURL);

if ($crimefestContent !== false) {

	$crimefestContent = utf8_encode($crimefestContent);

	$crimefestContentPattern = '/\<div class=\"crimefestpathfloormarkers (?P<markerclassid>.*) (?P<markerstatus>.*)\" data-thismarker=\"(?P<markerid>[\w\d]*)\">?\s?((?P<markerinfo>.*)>)?<\/div>/i';
	$crimefestContentPattern2 = '/\<div class=\"crimefestpathfloormarkers (?P<markerclassid>.*) (?P<markerstatus>.*)\" data-thismarker=\"(?P<markerid>[\w\d]*)\">?\s?((?P<markerinfo>.*\s*.*)>)?<\/div>/i';
		
	//$summeArray = [];	
	if (preg_match_all($crimefestContentPattern,$crimefestContent,$crimefestContentMatches)) {
	
		if (preg_match_all($crimefestContentPattern2,$crimefestContent,$crimefestContentMatches2)) {
			for ($m2 = 0; $m2 < count($crimefestContentMatches2['markerid']); $m2++) {
				$summeIndex = false;
				for ($m1 = 0; $m1 < count($crimefestContentMatches['markerid']); $m1++) {
				
					if ($crimefestContentMatches2['markerid'][$m2] == $crimefestContentMatches['markerid'][$m1]) {
						$summeIndex = false;
						break 1;
					} else {
						$summeIndex = $m2;
					}
				
				}
				
				if ($summeIndex != false) {
					$crimefestContentMatches['markerclassid'][] = $crimefestContentMatches2['markerclassid'][$summeIndex];
					$crimefestContentMatches['markerstatus'][] = $crimefestContentMatches2['markerstatus'][$summeIndex];
					$crimefestContentMatches['markerid'][] = $crimefestContentMatches2['markerid'][$summeIndex];
					$crimefestContentMatches['markerinfo'][] = $crimefestContentMatches2['markerinfo'][$summeIndex];
				}
				
			}
		}
		
		for ($m = 0; $m < count($crimefestContentMatches['markerinfo']); $m++) {
			
			//first entry lost but doesn't matter - i think (data-thismarker=xx)
			$dataParts = explode(" data-", $crimefestContentMatches['markerinfo'][$m]);

			$dataObject = [];
			for ($r = 1; $r < count($dataParts) ; $r++) {
				$makeObject = explode("=", $dataParts[$r]);
				$dataObject[$makeObject[0]] = str_replace('"', "", $makeObject[1]);
			}
			
			$crimefestContentMatches['markerinfo'][$m] = $dataObject;
		}
		
		$niceArray = array(
			'markerclassid' => $crimefestContentMatches['markerclassid'],
			'markerstatus' => $crimefestContentMatches['markerstatus'],
			'markerid' => $crimefestContentMatches['markerid'],
			// markerinfo needs some object/array in array stuff for your pleasure ^DONE^
			'markerinfo' => $crimefestContentMatches['markerinfo']
		);
		
		$niceJson = json_encode($niceArray, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT | JSON_HEX_QUOT);
		$niceJson = utf8_encode($niceJson);
		//echo $niceJson;
		
		$time = date('Y-m-d\TH-i-s\Z', time());
		
		if (!is_dir($pathToArchive))
		{
			if (!is_writable(getcwd())) {
				//die('Sorry the current folder is not writeable pls try chmod 0770 and switch back to 0750 after the folder was created; or create the folder '.$pathToArchive.' with ftp access');
				die('Sorry the current folder is not writeable');
			}
			
			if(!mkdir($pathToArchive, 0750, true)) { // 0750,770
				die('Sorry mkdir failed');
			}
		}
		
		if (is_readable('crimefest.json')) {
		
			$oldFileBuffer =  file_get_contents("{$pathToCrimefestJson}crimefest.json");

		} else {
		
			file_put_contents($pathToCrimefestJson.'crimefest.json', $niceJson, LOCK_EX);
			
			file_put_contents("{$pathToArchive}crimefest-{$time}-UTC.json", $niceJson, LOCK_EX);
			
			$oldFileBuffer =  file_get_contents("{$pathToCrimefestJson}crimefest.json");
		}

		if ($oldFileBuffer == $niceJson) {
		
			//echo "same";
			
			if(!$onlyOnChangesNotification){
				// EMAIL STUFF NOW:
				if ($emailNotifiation) {
				
					$msg = "Crimefest: nothing happend http://yourpage.tld";
					$msg = wordwrap($msg, 70);
					$header = "From: challenge-parser <{$yourMailAdress}>" . "\r\n";
					$header .= 'Content-type: text/plain; charset=utf-8' . "\r\n";
					$header .= "Subject: Crimefest - nothing happend" . "\r\n";
					
					// Send
					mail($yourMailAdress, 'Crimefest nothing happend', $msg, $header);
				}
			}
		
		} else {
			
			//echo "diff";
			
			file_put_contents($pathToCrimefestJson.'crimefest.json', $niceJson, LOCK_EX);
			
			file_put_contents("{$pathToArchive}crimefest-{$time}-UTC.json", $niceJson, LOCK_EX);
			
			
			
			// EMAIL STUFF NOW:
			if ($emailNotifiation) {
			
				$msg = "Crimefest Challenges Changed http://yourpage.tld";
				$msg = wordwrap($msg, 70);
				$header = "From: challenge-parser <{$yourMailAdress}>" . "\r\n";
				$header .= 'Content-type: text/plain; charset=utf-8' . "\r\n";
				$header .= "Subject: Crimefest - something changed" . "\r\n";
				
				// Send
				mail($yourMailAdress, 'Crimefest some changes', $msg, $header);
			}
		}

	} else {
		echo "Nothing found!";
	}

}

echo "EOF";