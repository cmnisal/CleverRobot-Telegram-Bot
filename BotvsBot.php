<?php
require 'config.php';
require 'chatter-bot-api/php/chatterbotapi.php';

$getUpdates = $website.'getUpdates';

$factory = new ChatterBotFactory();

$bot1 = $factory->create(ChatterBotType::CLEVERBOT);
$bot1session = $bot1->createSession();

$bot2 = $factory->create(ChatterBotType::CLEVERBOT);
$bot2session = $bot2->createSession();

if (!file_exists('restart')){
	file_put_contents('sessions.json', json_encode(array('2' => '@BotvsBot')));
} else {
	unlink('restart');
}

$text = 'Hi.';

while (true){
	usleep(1000000);
	sendMessage('_Bot 1_: '.$text);
	checkIfEnglish($text);
	$text = $bot2session->think($text);
	sendMessage('_Bot 2_: '.$text);
	checkIfEnglish($text);
	$text = $bot1session->think($text);
}

function sendMessage($text){
	global $website;

	$text = html_entity_decode($text);

	file_get_contents($website.'sendMessage?chat_id=@BotvsBot&text='.urlencode($text).'&parse_mode=Markdown');
	$text = str_replace('_', '', $text);
	$text = str_replace('*', '', $text);
	echo $text."\n";
}

function checkIfEnglish($text){
	if (!$text){
		sendMessage('*! Empty Cleverbot message received, resetting conversation !*');
		exit(2);
	}

	$text = html_entity_decode($text);

	$result = file_get_contents('http://ws.detectlanguage.com/0.2/detect?q='.urlencode($text).'&key=54fe0674c4102e18522daba929bff621');
	$result = str_replace('true', '1', $result);
	$result = str_replace('false', '0', $result);
	$result = json_decode($result, true);

	if ($result && $result['data']['detections']['0']['language'] != 'en' && $result['data']['detections']['0']['isReliable'] === 1){
		// 		print_r($result);
		sendMessage('*! Language other than English detected, resetting conversation... !*');
		touch('restart');
		exit(2);
	}
}
