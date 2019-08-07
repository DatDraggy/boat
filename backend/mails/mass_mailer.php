<?php
if (php_sapi_name() != "cli") {
  die('lol nah');
}
require_once('../config.php');
require_once('../funcs.php');

$dbConnection = buildDatabaseConnection($config);

try {
  $sql = 'SELECT email, nickname FROM users';
  $stmt = $dbConnection->prepare($sql);
  $stmt->execute();
  $rows = $stmt->fetchAll();
} catch (PDOException $e) {
  notifyOnException('Database Select', $config, $sql, $e);
}

foreach ($rows as $row) {
  $nickname = $row['nickname'];

  sendEmail($row['email'], 'Summerbo.at Party Information', "Ahoy $nickname!

Can you believe it? In less than a week we will be departing from the Estrel for our awesome party!
To get you into the mood, we have some information for you.

<h4>Weather</h4>
The weather forecast tells us that there might be a little rain scattered across the day, but the evening should be dry.
However, bringing an umbrella for the boarding area might be a smart idea.

<h4>VIP Upgrade</h4>
If you selected the VIP upgrade for your summerbo.at registration, please don't forget to bring the 15&euro; <b>in cash</b> to the badge pickup area.
We opted for the cash payment, because it makes it easier to donate the money to the EF charity. It will be much less paperwork this way.

<h4>Badge Pickup</h4>
On the day of our party the registration department will open the badge pickup table in the Estrel at around 12:00 until 17:00.
You can find us in room 'Paris', where EF also has their badge pickup tables. If you have the EF App installed, it's marked as '02' on the first map.
If the time of your arrival makes it impossible for you to pickup your badge, you can contact us and we'll take your badge over to the boarding area.

<h4>Fursuiter</h4>
If you are a fursuiter, the following couple of things might interest you.
We would like to remind you to please not bring your big fursuit cases onto the boat unless absolutely necessary. It's only a boat after all, so if everyone brings their case, we won't have any place to party!
There will also be a fully fledged fursuit lounge on board with drinks and fans to cool you down if you need a break. Alternatively, visit the upper deck and let the breeze of the ocean cool you down. ;-)
Another cool thing: Fursuit events! We will be hosting events specifically for suiters, so you can look forward to that as well.
Please also note that the ceiling inside the boat is not very high, so please take care if you're a tall suiter.

<h4>After-Show-Party</h4>
Yes! There will be an After-Show-Party, or rather an After-Party-Party. However, it's going to be a special one!
Just like on the upper deck of our boat, we will host a <i>silent</i> party!
That means you will receive headphones and listen to the music on those.
If you're in a fursuit, make sure to bring 3.5mm in-ear pods so that you can connect those with a receiver.

If you have any questions, please send us a message. Reply to this e-mail or contact us via Telegram at <a href=\"https://t.me/summerboat\">https://t.me/summerboat</a>.
", true, false);
  sleep(10);
}