<?php
require_once(__DIR__ . '/../backend/config.php');
header('Cache-Control: max-age=0');
if (stripos($_SERVER['REQUEST_URI'], $config['secretPath']) === false) {
  die('Get Lost.');
}
require_once(__DIR__ . '/../backend/funcs.php');

$response = file_get_contents('php://input');
$data = json_decode($response, true);
$dump = print_r($data, true);

if (isset($data['callback_query'])) {
  $chatId = $data['callback_query']['message']['chat']['id'];
  $queryId = $data['callback_query']['id'];
  $chatType = $data['callback_query']['message']['chat']['type'];
  $callbackData = $data['callback_query']['data'];
  $senderUserId = $data['callback_query']['from']['id'];
  if ($chatId == '-1001203230309' || $chatId == '-1001182844773') {
    list($targetUserId, $status) = explode('|', $callbackData);

    if ($targetUserId == $senderUserId) {
      unrestrictUser($chatId, $senderUserId, $data['callback_query']['message']['message_id'], $data['callback_query']['message']['text']);
      userClickedButton((string)$chatId, $senderUserId);
      answerCallbackQuery($queryId, 'Accepted.');
      die();
    }
    answerCallbackQuery($queryId);
  } else {
    if (!in_array($chatId, $config['telegramAdmins'])) {
      die();
    }
    if (stripos($callbackData, '|') !== false) {
      list($targetUserId, $status, $confirm, $time) = explode('|', $callbackData);
      $dbConnection = buildDatabaseConnection($config);
      if ($status === 'approve') {
        if (approveRegistration($targetUserId, $senderUserId)) {
          answerCallbackQuery($queryId, 'Registration has been approved.');
          list($email, $nickname, $regnumber) = getRegDetails($targetUserId, 'email, nickname, id');
          recalculateTopay($targetUserId);
          $topay = getBalanceDetails($targetUserId, 'topay - paid as topay')['topay'];
          sendEmail($email, 'Registration Approved', "Dear $nickname,

Your registration was approved by our registration team.

You are now able to login and manage your details on <a href=\"https://summerbo.at\">summerbo.at</a>

Welcome aboard! Get ready to party with us on the Spree just before Eurofurence . Your ticket price is $topay EUR - but wait, you don't have to pay yet. Starting on the 25th of March 2019 you will find your Ticket directly in your <a href=\"https://reg.eurofurence.org\">Eurofurence Registration</a> and can pay it in the same way you pay your EF Reg.

Please make sure to provide your correct EF Regnumber BEFORE the 25th of March via <a href=\"https://summerbo.at/user/details\">summerbo.at</a>

You will then also receive an email from Eurofurence regarding the payment of your ticket.

Also, because this is our first time organizing this event, we would like to receive some feedback about the registration system. Please take a minute to fill this out <a href=\"https://docs.google.com/forms/d/1B3TLU4COyluSKT1eiIKaQetSTklzokEjMI_enMFp64o\">survey</a>.

If you have any questions, please send us a message. Reply to this e-mail or contact us via Telegram at <a href=\"https://t.me/summerboat\">https://t.me/summerboat</a>.
", true);
        } else {
          answerCallbackQuery($queryId, 'Already approved, rejected or error.');
        }
      } else if ($status === 'reject') {
        if ($confirm == 1 && $time + 10 >= time()) {
          if (rejectRegistration($targetUserId)) {
            answerCallbackQuery($queryId, 'Registration has been rejected.');
          } else {
            answerCallbackQuery($queryId, 'Already rejected or error');
          }
        } else {
          $replyMarkup = array(
            'inline_keyboard' => array(
              array(
                array(
                  'text' => 'Yes',
                  'callback_data' => $targetUserId . '|reject|1|' . time()
                ),
                array(
                  'text' => 'No',
                  'callback_data' => 'no'
                )
              )
            )
          );
          answerCallbackQuery($queryId);
          sendMessage($chatId, "Are you sure you want to cancel the registration for $targetUserId?", json_encode($replyMarkup));
        }
      } else if ($status === 'handled') {
        answerCallbackQuery($queryId);
        if (isset($data['callback_query']['from']['username'])) {
          $handleName = $data['callback_query']['from']['username'];
        } else {
          $handleName = $data['callback_query']['from']['first_name'];
          if (isset($data['callback_query']['from']['last_name'])) {
            $handleName .= ' ' . $data['callback_query']['from']['last_name'];
          }
        }
        sendStaffNotification(0, 'Application from ' . $confirm . ' was handled by ' . $handleName . '.');
      }
    } else {
      answerCallbackQuery($queryId);
    }
    die();
  }
}
if (!isset($data['message'])) {
  die();
}
$chatId = $data['message']['chat']['id'];
$chatType = $data['message']['chat']['type'];
$messageId = $data['message']['message_id'];
$senderUserId = preg_replace("/[^0-9]/", "", $data['message']['from']['id']);
$senderUsername = NULL;
if (isset($data['message']['from']['username'])) {
  $senderUsername = $data['message']['from']['username'];
}
$senderName = $data['message']['from']['first_name'];
if (isset($data['message']['from']['last_name'])) {
  $senderName .= ' ' . $data['message']['from']['last_name'];
}
if (isset($data['message']['text'])) {
  $text = $data['message']['text'];
} else if (isset($data['message']['caption'])) {
  $text = $data['message']['caption'];
}

mail($config['mail'], 'Summerboat Dump', $dump);
if ($chatId == '-1001203230309' || $chatId == '-1001182844773') {
  if (isset($data['message']['new_chat_participant']) && $data['message']['new_chat_participant']['is_bot'] != 1) {
    $userId = $data['message']['new_chat_participant']['id'];
    //restrictChatMember($chatId, $userId, 3600);


    ignore_user_abort(true);
    set_time_limit(0);

    ob_start();
    $untilTimestamp = time() + 3600;
    $returndata = array(
      'method' => 'restrictChatMember',
      'chat_id' => $chatId,
      'user_id' => $userId,
      'until_date' => $untilTimestamp,
      'can_send_messages' => false,
      'can_send_media_messages' => false,
      'can_send_other_messages' => false,
      'can_add_web_page_previews' => false
    );
    echo json_encode($returndata);
    header('Content-type: application/json');
    header('Connection: close');
    header('Content-Length: '.ob_get_length());
    ob_end_flush();
    ob_flush();
    flush();


    addUserToNewUsers((string)$chatId, $userId);
    $name = $data['message']['new_chat_participant']['first_name'];
    if (isset($data['message']['new_chat_participant']['last_name'])) {
      $name .= ' ' . $data['message']['new_chat_participant']['last_name'];
    }
    $rules = "Welcome to the Summerbo.at Group, <a href=\"tg://user?id=$userId\">$name</a>!
Follow the /rules and enjoy your stay~";
    $replyMarkup = array(
      'inline_keyboard' => array(
        array(
          array(
            "text" => "Press if you're not a bot!",
            "callback_data" => $userId . '|bot'
          )
        )
      )
    );
    $message = sendMessage($chatId, $rules, json_encode($replyMarkup));
    addMessageToHistory($chatId, $data['message']['new_chat_participant']['id'], $messageId, time());
    addMessageToHistory($chatId, $data['message']['new_chat_participant']['id'], $message['message_id']);
    if ($name == 'Bot Notification' || $name == 'Information Agent') {
      kickUser($chatId, $userId, '0');
      deleteMessages($chatId, $userId);
    }
    die();
  } else {
    if (isset($text)) {
      if (substr($text, '0', '1') == '/') {
        $messageArr = explode(' ', $text);
        $command = explode('@', $messageArr[0])[0];
        $command = strtolower($command);
        switch (true) {
          case ($command === '/rules'):
            sendMessage($chatId, '1. Apply common sense
2. Don\'t spam. Neither stickers nor GIFs nor memes nor pictures.
3. Keep it English, other languages are not allowed
4. Keep it PG-13
5. No hate-speech, harassment, illegal stuff or insults.
6. No talk about Piracy (Pirates in general are allowed)
7. Keep your swim vest near you at all times
8. Thank the captain and listen to the boat crew');
            break;
          case ($command === '/venue'):
            sendVenue($chatId, 52.473208, 13.458217, 'Estrel Sommergarten', 'Ziegrastraße 44, 12057 Berlin');
            break;
          case ($command === '/badge'):
            sendMessage($chatId, 'On the day of the party you can pick up the badge inside the Estrel Hotel during the afternoon or in the evening in the Biergarten near the boat. Please make sure you bring your ID or Passport with you. The badge is your entrance to the party so please do not lose it. There will be no tickets sold on the day itself.');
            break;
        }
      } else {
        //addUserToNewUsers((string)$chatId, $senderUserId);
        //if (json_decode(file_get_contents('users.json'), true)[$chatId][$senderUserId] < time() + 1800){
        if (isNewUser((string)$chatId, $senderUserId)) {
          $status = 1;
          if (!hasUserClickedButton((string)$chatId, $senderUserId)) {
            deleteMessage($chatId, $messageId);
            kickUser($chatId, $senderUserId, 0);
            $status .= 2;
          } else if (!empty($data['message']['entities'])) {
            foreach ($data['message']['entities'] as $entity) {
              if ($entity['type'] == 'url') {
                deleteMessage($chatId, $messageId);
                $status.= 3;
                if (isNewUsersFirstMessage((string)$chatId, $senderUserId)) {
                  $status .=4;
                  kickUser($chatId, $senderUserId, 0);
                }
                break;
              }
            }
          } else if (!empty($data['message']['caption_entities'])) {
            foreach ($data['message']['caption_entities'] as $entity) {
              if ($entity['type'] == 'url') {
                $status .= 5;
                deleteMessage($chatId, $messageId);
                if (isNewUsersFirstMessage((string)$chatId, $senderUserId)) {
                  $status .= 6;
                  kickUser($chatId, $senderUserId, 0);
                }
                break;
              }
            }
          } else if (stripos($text, 'http') !== FALSE || stripos($text, 'https') !== FALSE) {
            deleteMessage($chatId, $messageId);
            $status .= 7;
            if (isNewUsersFirstMessage((string)$chatId, $senderUserId)) {
              $status .= 8;kickUser($chatId, $senderUserId, 0);
            }
          }
          isNewUsersFirstMessage((string)$chatId, $senderUserId);
          mail($config['mail'], 'Summerboat Dump', $status . $dump);
        }
      }
      die();
    }

  }
}

if (isset($text)) {
  if (substr($text, '0', '1') == '/') {
    $messageArr = explode(' ', $text);
    $command = explode('@', $messageArr[0])[0];
    if ($messageArr[0] == '/start' && isset($messageArr[1])) {
      $command = '/' . $messageArr[1];
    }
  } else {
    die();
  }

  $command = strtolower($command);
  switch (true) {
    case ($command === '/start'):
      sendMessage($chatId, 'Hello! I\'m the Summerbo.at Bot.
To get a command overview, send /help.');
      break;
    case ($command === '/help'):
      sendMessage($chatId, 'Applying for Volunteer: /apply
Location: /venue
Badge pickup: /badge');
      break;
    case ($command === '/id'):
      sendMessage($chatId, $chatId . ' ' . $senderUserId);
      break;
    case ($command === '/apply'):
      if (!empty($messageArr[1]) && $messageArr[0] !== '/start') {
        $dbConnection = buildDatabaseConnection($config);
        $application = explode(' ', $text, 2)[1];
        $saveName = $senderName;
        if ($senderUsername !== NULL) {
          $saveName = $senderUsername;
        }
        if (saveApplication($chatId, $saveName, $application)) {
          $replyMarkup = array(
            'inline_keyboard' => array(
              array(
                array(
                  'text' => 'Handled',
                  'callback_data' => $chatId . '|handled|' . $saveName . '|0'
                )
              )
            )
          );
          sendStaffNotification($chatId, "<b>New application from </b><a href=\"tg://user?id=$chatId\">$saveName</a>:
$application", $replyMarkup);
          sendMessage($chatId, 'Thank you! Your application will be reviewed soon.');
          mail('team@summerbo.at', 'New Application!', "By: $saveName
Message: $application");
        } else {
          sendMessage($chatId, 'Sorry, something went wrong. Perhaps you already applied?');
        }
      } else {
        sendMessage($chatId, '<b>How to apply as a volunteer:</b>
Write <code>/apply</code> with a little bit about yourself and experiences behind it.
Example: <code>/apply Hello, I\'m Dragon!</code>');
      }
      die();
      break;
    case ($command === '/reg' && isTelegramAdmin($chatId)):
      if (isset($messageArr[1])) {
        if ($messageArr[1] === 'status') {
          if (isset($messageArr[2])) {
            $dbConnection = buildDatabaseConnection($config);
            $details = getRegDetails($messageArr[2], 'id, nickname, status, approvedate');
            $approvedate = date('Y-m-d', $details['approvedate']);
            sendMessage($chatId, "
Regnumber: {$details['id']}
Nickname: {$details['nickname']}
Status: {$details['status']}
Approved: $approvedate");
          } else {
            sendMessage($chatId, 'Please supply a regnumber.');
          }
        }
      }
      break;
    case ($command === '/blacklist' && isTelegramAdmin($chatId)):
      //ToDo: TBD
      break;
    case ($command == '/getunconfirmed' && isTelegramAdmin($chatId)):
      $dbConnection = buildDatabaseConnection($config);
      requestUnapproved($chatId);
      break;
    case ($command === '/payment' && isTelegramAdmin($chatId)):
      if (isset($messageArr[1])) {
        $dbConnection = buildDatabaseConnection($config);
        if ($messageArr[1] === 'status') {
          if (isset($messageArr[2])) {
            $details = getPaymentDetails($messageArr[2], 'users.id, approvedate, amount, topay');
            if ($details === false) {
              sendMessage($chatId, 'No Payments');
            } else {
              foreach ($details as $detail) {
                $payByDate = date('Y-m-d', strtotime('+2 weeks', $details['approvedate']));
                sendMessage($chatId, "
Regnumber: {$detail['id']}
Until: $payByDate
Paid: {$detail['amount']}
To pay: {$detail['topay']}");
              }
            }
          } else {
            sendMessage($chatId, 'Please supply a regnumber.');
          }
        } else if (is_numeric($messageArr[1])) {
          if (isset($messageArr[2])) {
            $status = (approvePayment($messageArr[2], $senderUserId, $messageArr[1]) ? 'yes' : 'no');
            sendMessage($chatId, 'Updated. Payment completed: ' . $status);
          } else {
            sendMessage($chatId, 'Please supply a regnumber.');
          }
        } else {
          sendMessage($chatId, 'The given amount is not numeric.');
        }
      } else {
        sendMessage($chatId, 'Usage:
<code>/payment</code> <b>amount</b> <b>regnumber</b>');
      }
      break;
    case ($command === '/venue'):
      sendVenue($chatId, 52.473208, 13.458217, 'Estrel Sommergarten', 'Ziegrastraße 44, 12057 Berlin');
      break;
    case ($command === '/badge'):
      sendMessage($chatId, 'On the day of the party you can pick up the badge inside the Estrel Hotel during the afternoon or in the evening in the Biergarten near the boat. Please make sure you bring your ID or Passport with you. The badge is your entrance to the party so please do not lose it. There will be no tickets sold on the day itself.');
      break;
    default:
      sendMessage($chatId, 'Hello! I\'m the Summerbo.at Bot.
To get a command overview, send /help.');
      break;
  }
}
