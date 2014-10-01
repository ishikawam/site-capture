<?php
/**
 * キューのerror, none, busy, を定期的に掃除する
 *
 */

ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL & ~E_NOTICE);

// lock
$output = array();
$count = 0;
exec('ps x | grep "batch_clean.php"', $output);
foreach ($output as $val) {
    if (preg_match('|php cli/batch_clean.php|', $val)) {
        $count ++;
    }
}
if ($count > 1) {
    var_dump($output);
    exit;
}

$return = array();

// DB
try {
    $pdo = new PDO('mysql:host=localhost; dbname=capture', 'capture', '');
} catch(PDOException $e) {
    var_dump($e->getMessage());
    exit;
}

$config = array(
    'phantom' => array(
        'busy' => '1 hour',
        'error' => '3 hour',
        'none' => '1 day',
    ),
    'slimer' => array(
        'busy' => '1 hour',
        'error' => '3 hour',
        'none' => '1 day',
    ),
);

// ログに残すのでselectしてから
foreach ($config as $engine => $val) {
    foreach ($val as $status => $interval) {
        $stmt = $pdo->query('select * from queue_' . $engine . ' where status = \'' . $status . '\' and subdate(now(),interval ' . $interval . ' ) >= created_at');
        $log = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($log as $line) {
            $stmt = $pdo->exec('delete from queue_' . $engine . ' where id = \'' . $line['id'] . '\'');
            file_put_contents('log/clean_log', implode("\t", $line) . "\n", FILE_APPEND);
        }
    }
}
