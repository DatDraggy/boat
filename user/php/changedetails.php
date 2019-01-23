<?php
require_once('../../backend/config.php');
require_once('../../backend/funcs.php');
header('Cache-Control: max-age=0');
session_start();
if (empty($_SESSION['userId']) || empty($_POST['nickname']) || empty($_POST['email']) || empty($_POST['password'])) {
  $status = 'Missing details. Check Nickname, Email and old Password.';
  session_start();
  $_SESSION['status'] = $status;
  session_commit();
  header('Location: ../details');
  die($status);
}

$dbConnection = buildDatabaseConnection($config);
$userId = $_SESSION['userId'];
////////////////////////
// Check Reg Validity //
if (!checkRegValid($userId)) {
  //If invalid, kill that bih
  if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
  }
  session_destroy();
  header('Location: ../login.html');
  die();
}
// Check Reg Validity //
////////////////////////
session_commit();
$nicknamePost = $_POST['nickname'];
if (preg_match('/[^\w-. ~]/', $nicknamePost) === 1) {
  $status = 'Illegal character in Nickname';
  session_start();
  $_SESSION['status'] = $status;
  session_commit();
  header('Location: ../details');
  die($status);
} else {
  $nickname = $nicknamePost;
}
$newEmailPost = strtolower($_POST['email']);
if (filter_var($newEmailPost, FILTER_VALIDATE_EMAIL)) {
  $newEmail = $newEmailPost;
} else {
  $status = 'Invalid Email Format';
  session_start();
  $_SESSION['status'] = $status;
  session_commit();
  header('Location: ../details');
  die($status);
}

/////////////////////
// Password Verify //
$hash = checkPassword($userId, $_POST['password']);

if ($hash === false) {
  $status = 'Incorrect Password';
  session_start();
  $_SESSION['status'] = $status;
  session_commit();
  header('Location: ../details');
  die($status);
}
// Password Verify //
/////////////////////

/////////////////////
// Password Change //
if (!empty($_POST['passwordNew'])) {
  $passwordNew = $_POST['passwordNew'];
  if (empty($_POST['passwordNewVerify']) || $passwordNew !== $_POST['passwordNewVerify']) {
    $status = 'Passwords do not match';
    session_start();
    $_SESSION['status'] = $status;
    session_commit();
    header('Location: ../details');
    die($status);
  }
  $valid = validatePassword($passwordNew);
  if ($valid !== true) {
    $status = $valid;
    session_start();
    $_SESSION['status'] = $status;
    session_commit();
    header('Location: ../details');
    die($status);
  }
  $hash = hashPassword($passwordNew);
}
// Sponsor Upgrade //
/////////////////////

if (empty($_POST['fursuiter'])) {
  $fursuiter = false;
} else {
  $fursuiter = true;
}
if (empty($_POST['publiclist'])) {
  $list = false;
} else {
  $list = true;
}

/////////////////////
// Sponsor Upgrade //
if (empty($_POST['sponsor'])) {
  $sponsorNew = false;
} else {
  $sponsorNew = true;
}
try {
  $sql = "SELECT id_internal, email, sponsor FROM users WHERE id = $userId";
  $stmt = $dbConnection->prepare('SELECT id_internal, email, sponsor FROM users WHERE id = :userId');
  $stmt->bindParam(':userId', $userId);
  $stmt->execute();
  $row = $stmt->fetch();
} catch (PDOException $e) {
  notifyOnException('Database Select', $config, $sql, $e);
}
// Sponsor Upgrade //
/////////////////////

$sponsorOld = $row['sponsor'];
$oldEmail = $row['email'];
$userIdInternal = $row['id_internal'];

//////////////////
// Email Change //
$confirmationLink = false;
if ($oldEmail !== $newEmail) {
  try {
    $sql = "SELECT email FROM users WHERE email = '$newEmail' OR email_new = '$newEmail'";
    $stmt = $dbConnection->prepare('SELECT email FROM users WHERE email = :newEmail OR email_new = :newEmail2');
    $stmt->bindParam(':newEmail', $newEmail);
    $stmt->bindParam(':newEmail2', $newEmail);
    $stmt->execute();
    $stmt->fetchAll();
  } catch (PDOException $e) {
    notifyOnException('Database Select', $config, $sql, $e);
  }
  if ($stmt->rowCount() > 0) {
    $status = 'This email address is already taken.';
    session_start();
    $_SESSION['status'] = $status;
    session_commit();
    header('Location: ../details');
    die($status);
  }
  else {
    try {
      $sql = "UPDATE users SET email_new = $newEmail WHERE id = $userId";
      $stmt = $dbConnection->prepare('UPDATE users SET email_new = :email WHERE id = :userId');
      $stmt->bindParam(':email', $newEmail);
      $stmt->bindParam(':userId', $userId);
      $stmt->execute();
    } catch (PDOException $e) {
      notifyOnException('Database Update', $config, $sql, $e);
    }
    if ($stmt->rowCount() === 1) {
      $confirmationLink = requestEmailConfirm($userIdInternal, 'email');
    }
  }
}
// Email Change //
//////////////////

/////////////////////////////////
// Update Nickname and Fursuit //
try {
  $sql = "UPDATE users SET nickname = $nickname, fursuiter = $fursuiter, list = :list, hash = $hash WHERE id = $userId";
  $stmt = $dbConnection->prepare('UPDATE users SET nickname = :nickname, fursuiter = :fursuiter, list = :list, hash = :hash WHERE id = :userId');
  $stmt->bindParam(':nickname', $nickname);
  $stmt->bindParam(':fursuiter', $fursuiter);
  $stmt->bindParam(':list', $list);
  $stmt->bindParam(':hash', $hash);
  $stmt->bindParam(':userId', $userId);
  $stmt->execute();
} catch (PDOException $e) {
  notifyOnException('Database Select', $config, $sql, $e);
}
// Update Nickname and Fursuit //
/////////////////////////////////

$status = 'Details changed successfully.';
session_start();
$_SESSION['statusSuccess'] = $status;
session_commit();
header('Location: ../details');

if ($sponsorNew === true && $sponsorOld == 0) {
  upgradeToSponsor($userId);
  sendStaffNotification($userId, "<a href=\"https://summerbo.at/admin/view?type=reg&id=$userId\">Attendee $userId</a> upgraded to sponsor.");
}

if ($confirmationLink !== false) {
  sendEmail($oldEmail, 'Email Change Confirmation', "Dear $nickname, 

You requested to change your email to $newEmail. Please follow this link to confirm: <a href=\"$confirmationLink\">$confirmationLink</a>

If you have any questions, please send us a message. Reply to this e-mail or contact us via Telegram at <a href=\"https://t.me/summerboat\">https://t.me/summerboat</a>.

Your Boat Party Crew
");
}