<?php
require 'config.php';
require 'chatter-bot-api/php/chatterbotapi.php';

$getUpdates = $website."getUpdates";

$factory = new ChatterBotFactory();

$bot1 = $factory->create(ChatterBotType::CLEVERBOT);
$bot1session = $bot1->createSession();

$bot2 = $factory->create(ChatterBotType::CLEVERBOT);
$bot2session = $bot2->createSession();

$text = 'Hi.';

while (true){
	$result = file_get_contents($getUpdates);
	
	$result = json_decode($result, true);
	$result = $result["result"];
	
	if (isset($result[0])){
		foreach ($result as $message){
			processMessage($result);
			
			$result = file_get_contents($getUpdates);
			
			$result = json_decode($result, true);
			$result = $result["result"];
		}
	}
	
	sendMessage($text, 1);
	checkIfEnglish($text);
	$text = $bot2session->think($text);
	sendMessage($text, 2);
	checkIfEnglish($text);
	$text = $bot1session->think($text);
}

function processMessage($result){
	global $getUpdates;
	
	$messageElements = getMessageElements($result);

	file_get_contents($getUpdates."?offset=".$messageElements["offset"]);

	switch(strtolower($messageElements["text"])){
		case "/restart":
			if($messageElements['userId'] == "125874268"){
				sendMessage("*! Resetting conversation... !*");
				exit(2);
			}
			break;
		case "/shutdown":
			if ($messageElements['userId'] == "125874268"){
				sendMessage("*! The bot has been shut down for maintenance. !*");
				exit();
			}
			break;
	}
}

function getMessageElements($array){
	$messageElements = array(
			"offset" => "",
			"text" => "",
			"userId" => ""
	);

	if ($array != null){
		$keys = array_keys($array);
		$key = array_shift($keys);
		if (isset($array[$key]["update_id"])){
			$messageElements["offset"] = $array[$key]["update_id"];
			$messageElements["offset"]++;
		}

		if (isset($array[$key]["message"]["text"])){
			$messageElements["text"] = str_replace("@ZermeloBot", "", $array[$key]["message"]["text"]);
		}

		if (isset($array[$key]["message"]["from"]["id"])){
			$messageElements["userId"] = $array[$key]["message"]["from"]["id"];
		}

		return $messageElements;
	}
}

function sendMessage($text, $bot = null){
	global $website;

	if ($bot === 1){
		file_get_contents($website.'sendMessage?chat_id=@BotvsBot&text='.urlencode('_Bot 1_: '.$text).'&parse_mode=Markdown');
		echo "Bot 1: ".$text."\n";
	} elseif ($bot === 2){
		file_get_contents($website.'sendMessage?chat_id=@BotvsBot&text='.urlencode('_Bot 2_: '.$text).'&parse_mode=Markdown');
		echo "Bot 2: ".$text."\n";
	} else {
		file_get_contents($website.'sendMessage?chat_id=@BotvsBot&text='.urlencode($text).'&parse_mode=Markdown');
		echo $text."\n";
	}
}

function checkIfEnglish($text){
	$result = file_get_contents('http://ws.detectlanguage.com/0.2/detect?q='.urlencode($text).'&key=54fe0674c4102e18522daba929bff621');
	$result = str_replace('true', '1', $result);
	$result = str_replace('false', '0', $result);
	$result = json_decode($result, true);
	
	if ($result && $result['data']['detections']['0']['language'] != 'en' && $result['data']['detections']['0']['isReliable'] === 1){
// 		print_r($result);
		sendMessage('*! Language other than English detected, resetting conversation... !*');
		exit(2);
	}
}
