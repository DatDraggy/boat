<?php
if (php_sapi_name() != "cli") {
  die('lol nah');
}

require_once('../config.php');
require_once('../funcs.php');

$dbConnection = buildDatabaseConnection($config);

//remindDeleteReg();

mail($config['mail'], 'Enable Payment Check', 'Enable Cronjob daily');