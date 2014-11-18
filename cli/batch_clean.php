<?php
/**
 * DB キューのerror, none, busy, を定期的に掃除する
 *
 */

include(__DIR__ . '/../inc/common.php');
$common = new Common;

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
    $pdo = new PDO('mysql:host=localhost; dbname=capture; unix_socket=/tmp/mysql.sock', 'capture', '');
    $pdo = new PDO('mysql:host=localhost; dbname=capture', 'capture', '');
} catch(PDOException $e) {
    echo "error " . __LINE__ . "\n";
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
