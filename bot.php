<?php
require 'config.php';
require 'chatter-bot-api/php/chatterbotapi.php';

$factory = new ChatterBotFactory();

$result = json_decode(file_get_contents("php://input"), true);
processMessage($result);

function processMessage($result){
	global $website, $factory;

	$messageElements = getMessageElements($result);

	if ($messageElements['chatType'] != 'private'){
		return false;
	}

	if (file_exists('shutdown')){
		sendMessage('*! The bot is currently shutting down for maintenance, try again later !* ', $messageElements['chatId']);
		return false;
	}

	$sessions = json_decode(file_get_contents("sessions.json"), true);

	switch(strtolower($messageElements['text'])){
		case '/start':
			sendMessage("Welcome to CleverRobot! A Telegram bot powered by Cleverbot.\n\nSee two Cleverbots talking to eachother!\n@BotvsBot\n\nSend only one message at a time if you want to have a proper conversation!\n_Say something nice to Cleverbot!_", $messageElements['chatId']);
			break;
		case '/restart':
			if($messageElements['userId'] == '125874268'){
				if (isset($sessions[2])){
					sendMessage('Restarting Bot vs. Bot...', $messageElements['chatId']);
					sendMessage('*! Restarting Bot vs. Bot... !*');

					exec('screen -X -S BotvsBot kill');
					touch('restart');
				} else {
					sendMessage('Bot vs. Bot is not running. Starting it...', $messageElements['chatId']);
					sendMessage('*! Starting Bot vs. Bot... !*');
				}

				exec('./screen.sh');

				sendMessage('Bot vs. Bot has been (re)started', $messageElements['chatId']);
			}
			break;
		case '/reset':
			$session = array_search($messageElements['chatId'], $sessions);

			if (!$session){
				sendMessage('_You don\'t have an active session, say something nice to Cleverbot!_', $messageElements['chatId']);
			} else {
				unset($sessions[$session]);
				file_put_contents('sessions.json', json_encode($sessions));
				unlink('sessions/'.$session.'.json');
				sendMessage('*! Your conversation with Cleverbot has been reset !*', $messageElements['chatId']);
			}
			break;
		case '/resetall':
			if ($messageElements['userId'] == '125874268'){
				if (!$sessions){
					sendMessage('*! No running sessions !*', $messageElements['chatId']);
				} else {
					sendMessage('Resetting all conversations...', $messageElements['chatId']);

					foreach ($sessions as $session){
						sendMessage('*! Resetting all conversations... !*', $session);

						$session = array_search($session, $sessions);

						if ($session === 2){
							exec('screen -X -S BotvsBot kill');
							touch('restart');
							exec('./screen.sh');
						} else {
							$sessions = json_decode(file_get_contents("sessions.json"), true);

							unlink('sessions/'.$session.'.json');
							unset($sessions[$session]);
							file_put_contents('sessions.json', json_encode($sessions));
						}

						usleep(100000);
					}

					sendMessage('All conversations have been reset.', $messageElements['chatId']);
				}
			}
			break;
		case '/shutdown':
			if ($messageElements['userId'] == '125874268'){
				touch('shutdown');

				if (!$sessions){
					sendMessage('*! No running sessions !*', $messageElements['chatId']);
				} else {
					sendMessage('Shutting down bot...', $messageElements['chatId']);

					foreach ($sessions as $session){
						sendMessage('*! The bot is shutting down for maintenance, resetting all conversations... !*', $session);

						if ($session = '@BotvsBot'){
							exec('screen -X -S BotvsBot kill');
						}

						usleep(100000);
					}

					foreach (glob('sessions/*') as $file){
						unlink($file);
					}

					file_put_contents('sessions.json', array());

					sendMessage('The bot has been shut down.', $messageElements['chatId']);
				}

				unlink('shutdown');
				exit();
			}
			break;
		default:
			file_get_contents($website.'sendChatAction?chat_id='.$messageElements['chatId'].'&action=typing');

			$sessions = json_decode(file_get_contents('sessions.json'), true);

			if (!$sessions){
				$session = null;
			} else {
				$session = array_search($messageElements['chatId'], $sessions);
			}

			if (!$session){
				if (!$sessions){
					$session = 3;
				} else{
					$session = max(array_keys($sessions));
					$session++;
				}

				${'bot'.$session} = $factory->create(ChatterBotType::CLEVERBOT);
				${'bot'.$session.'session'} = ${'bot'.$session}->createSession();

				file_put_contents('sessions/'.$session.'.json', serialize(${'bot'.$session.'session'}));

				$sessions[$session] = $messageElements['chatId'];

				file_put_contents('sessions.json', json_encode($sessions));
			} else {
				${'bot'.$session.'session'} = unserialize(file_get_contents('sessions/'.$session.'.json'));
			}

			$response = ${'bot'.$session.'session'}->think($messageElements['text']);
			sendMessage($response, $messageElements['chatId']);
			break;
	}
}

function getMessageElements($array){
	$messageElements = array(
			'chatId' => '',
			'text' => '',
			'messageId' => '',
			'userId' => '',
			'chatType' => '',
			'firstName' => '',
			'lastName' => '',
			'userName' => ''
	);

	if ($array != null){
		if (isset($array['message']['chat']['id'])){
			$messageElements['chatId'] = $array['message']['chat']['id'];
		}

		if (isset($array['message']['text'])){
			$messageElements['text'] = str_replace('@ZermeloBot', '', $array['message']['text']);
		}

		if (isset($array['message']['message_id'])){
			$messageElements['messageId'] = $array['message']['message_id'];
		}

		if (isset($array['message']['from']['id'])){
			$messageElements['userId'] = $array['message']['from']['id'];
		}

		if (isset($array['message']['chat']['type'])){
			$messageElements['chatType'] = $array['message']['chat']['type'];
		}

		if (isset($array['message']['from']['first_name'])){
			$messageElements['firstName'] = $array['message']['from']['first_name'];
		}

		if (isset($array['message']['from']['last_name'])){
			$messageElements['lastName'] = ' '.$array['message']['from']['last_name'];
		}

		if (isset($array['message']['from']['username'])){
			$messageElements['userName'] = ', @'.$array['message']['from']['username'];
		}

		return $messageElements;
	}
}

function sendMessage($text, $chatId){
	global $website;

	$text = html_entity_decode($text);

	if (!$chatId){
		file_get_contents($website.'sendMessage?chat_id=@BotvsBot&text='.urlencode($text).'&parse_mode=Markdown');
		$text = str_replace('_', '', $text);
		$text = str_replace('*', '', $text);
	} else {
		file_get_contents($website.'sendMessage?chat_id='.$chatId.'&text='.urlencode($text).'&parse_mode=Markdown');
	}
}
