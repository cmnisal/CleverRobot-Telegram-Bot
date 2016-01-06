<?php
require 'config.php';
require 'chatter-bot-api/php/chatterbotapi.php';

$factory = new ChatterBotFactory();

$bot1session = createSessions($factory);
$bot2session = createSessions($factory);

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

function createSessions($factory){
	$sessions = json_decode(file_get_contents("sessions.json"), true);

	if (!$sessions){
		$session = 1;
	} else {
		$session = max(array_keys($sessions));
		$session++;
	}

	${'bot'.$session} = $factory->create(ChatterBotType::CLEVERBOT);
	${'bot'.$session.'session'} = ${'bot'.$session}->createSession();

	$sessions[$session] = '@BotvsBot';
	file_put_contents('sessions.json', json_encode($sessions));

	return ${'bot'.$session.'session'};
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
	global $key;

	if (!$text){
		sendMessage('*! Empty Cleverbot message received, resetting conversation !*');
		exit(2);
	}

	$text = html_entity_decode($text);

	$result = file_get_contents('http://ws.detectlanguage.com/0.2/detect?q='.urlencode($text).'&key='.$key);
	$result = str_replace('true', '1', $result);
	$result = str_replace('false', '0', $result);
	$result = json_decode($result, true);

	if ($result && $result['data']['detections']['0']['language'] != 'en' && $result['data']['detections']['0']['isReliable'] === 1){
		sendMessage('*! Language other than English detected, resetting conversation... !*');
		touch('restart');
		exit(2);
	}
}
