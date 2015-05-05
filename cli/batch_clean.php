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
    $pdo = new PDO($common->config['database']['db'], $common->config['database']['user'], $common->config['database']['password']);
} catch(PDOException $e) {
    $common->logger("DB Error \n" . print_r($e->getMessage(), true), 'batch_clean_log');
    exit;
}

// @todo; 本当は同じ結果が返ったら間隔を広げていきたい。error->1日、3日、7日、30日、とか
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
            $common->logger(implode("\t", $line), 'clean_log');
        }
    }
}
