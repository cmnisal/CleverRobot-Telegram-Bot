<?php
require 'config.php';
require 'chatter-bot-api/php/chatterbotapi.php';

$factory = new ChatterBotFactory();

$result = json_decode(file_get_contents("php://input"), true);

processMessage($result);

function processMessage($result){
	global $website, $factory;

	$messageElements = getMessageElements($result);

	if ($messageElements['chatType'] !== 'private'){
		return false;
	}

	$chats = json_decode(file_get_contents('chats.json'), true);

	if (!in_array($messageElements['chatId'], $chats)){
		$chats[] = $messageElements['chatId'];
		file_put_contents('chats.json', json_encode($chats));
	}

	$sessions = json_decode(file_get_contents("sessions.json"), true);

	switch(strtolower($messageElements['text'])){
		case '/start':
			sendMessage("Welcome to CleverRobot! A bot powered by Cleverbot, made by @BasvdW.\n\nOfficial Cleverbot News:\n@CleverbotNews\n*See two Cleverbots talking to each other!*\n@BotvsBot\nOfficial Cleverbot chat:\nbvdw.me/CleverbotChat\n\nCleverbot supports multiple languages, altough you should use English for the best experience.\nSend only one message at a time if you want to have a proper conversation.\n_Say something nice to Cleverbot!_", $messageElements['chatId']);
			break;
		case '/restart':
			if($messageElements['userId'] == '125874268'){
				if (isset($sessions[2])){
					sendMessage('Restarting Bot vs. Bot...', $messageElements['chatId']);
					sendMessage('*! Restarting Bot vs. Bot... !*');

					exec('screen -X -S BotvsBot kill');
				} else {
					sendMessage('Bot vs. Bot is not running, starting it...', $messageElements['chatId']);
					sendMessage('*! Starting Bot vs. Bot... !*');
				}

				exec('./screen.sh');

				sendMessage('Bot vs. Bot has been (re)started', $messageElements['chatId']);
			}
			break;
		case '/reset':
			$session = @array_search($messageElements['chatId'], $sessions);

			if (!$session){
				sendMessage('_You don\'t have an active session, say something nice to Cleverbot!_', $messageElements['chatId']);
			} else {
				sendMessage("*! Your conversation with Cleverbot has been reset !\nStarting new session...*", $messageElements['chatId']);

				unlink('sessions/'.$session.'.json');
				unset($sessions[$session]);

				file_put_contents('sessions.json', json_encode($sessions));
				$sessions = json_decode(file_get_contents('sessions.json'), true);

				if ($sessions){
					$session = max(array_keys($sessions));
				}

				$session++;

				${'bot'.$session} = $factory->create(ChatterBotType::CLEVERBOT);
				${'bot'.$session.'session'} = ${'bot'.$session}->createSession();
				file_put_contents('sessions/'.$session.'.json', serialize(${'bot'.$session.'session'}));

				$sessions[$session] = $messageElements['chatId'];
				file_put_contents('sessions.json', json_encode($sessions));

				sendMessage("*! New session started !*\n_Say something nice to Cleverbot!_", $messageElements['chatId']);
			}
			break;
		case '/resetall':
			if ($messageElements['userId'] == '125874268'){
				if (!$sessions){
					sendMessage('*! No running sessions !*', $messageElements['chatId']);
				} else {
					sendMessage('Resetting all conversations...', $messageElements['chatId']);

					$x = 0;

					foreach ($sessions as $session){
						sendMessage('*! Resetting all conversations... !*', $session);

						$session = array_search($session, $sessions);

						if ($session === 2 && $x === 0){
							exec('screen -X -S BotvsBot kill');
							exec('./screen.sh');

							$x = 1;
						} else {
							$sessions = json_decode(file_get_contents("sessions.json"), true);

							unlink('sessions/'.$session.'.json');
							unset($sessions[$session]);
							file_put_contents('sessions.json', json_encode($sessions));
						}

						usleep(100000);
					}

					sendMessage('All sessions have been reset.', $messageElements['chatId']);
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

					$x = 0;

					foreach ($sessions as $session){
						if ($session === '@BotvsBot'){
							if ($x === 0){
								exec('screen -X -S BotvsBot kill');
								sendMessage("*! The bot is shutting down for maintenance !\nFollow *@CleverbotNews* for updates.*", $session);

								$x = 1;
							}

							continue;
						}

						sendMessage("*! The bot is shutting down for maintenance !\nFollow *@CleverbotNews* for updates.*", $session);

						usleep(100000);
					}

					foreach (glob('sessions/*') as $file){
						unlink($file);
					}

					file_put_contents('sessions.json', array());

					sendMessage('The bot has been shut down.', $messageElements['chatId']);
				}
				exit();
			}
			break;
		default:
			file_get_contents($website.'sendChatAction?chat_id='.$messageElements['chatId'].'&action=typing');

					if (file_exists('shutdown' || $messageElements['userId'] !== '125874268')){
						sendMessage("*! The bot has been shut down for maintenance, try again later !\nFollow *@CleverbotNews* for updates.*", $messageElements['chatId']);
						return false;
					}

					$session = @array_search($messageElements['chatId'], $sessions);

					if (!$session){
						if (!$sessions){
							$session = 1;
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

					if (!$response){
						sendMessage("*! Your conversation with Cleverbot has been reset, because of an expired session !\nStarting new session...*", $messageElements['chatId']);

						unset($sessions[$session]);

						$sessions = json_decode(file_get_contents('sessions.json'), true);

						if (!$sessions){
							$session = 1;
						} else{
							$session = max(array_keys($sessions));
							$session++;
						}

						${'bot'.$session} = $factory->create(ChatterBotType::CLEVERBOT);
						${'bot'.$session.'session'} = ${'bot'.$session}->createSession();
						file_put_contents('sessions/'.$session.'.json', serialize(${'bot'.$session.'session'}));

						$sessions[$session] = $messageElements['chatId'];
						file_put_contents('sessions.json', json_encode($sessions));

						sendMessage("*! New session started !*\n_Say something nice to Cleverbot!_", $messageElements['chatId']);
					} else {
						sendMessage($response, $messageElements['chatId']);
					}
					break;
			}
	}
	$lastMessage[$messageElements["userId"]][$messageElements["chatId"]] = $messageElements["text"];
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

function sendMessage($text, $chatId = null){
	global $website;

	$text = html_entity_decode($text);

	if (!$chatId){
		file_get_contents($website.'sendMessage?chat_id=@BotvsBot&text='.urlencode($text).'&parse_mode=Markdown');
		$text = str_replace('_', '', $text);
		$text = str_replace('*', '', $text);
	} elseif (!$text) {
		file_get_contents($website.'sendMessage?chat_id='.$chatId.'&text='.urlencode("*! Empty message received, try again later !\nFollow *@CleverbotNews* for updates.*").'&parse_mode=Markdown');
	} else {
		file_get_contents($website.'sendMessage?chat_id='.$chatId.'&text='.urlencode($text).'&parse_mode=Markdown');
	}
}
