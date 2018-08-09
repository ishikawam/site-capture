<?php
/**
 * Phantomをキューから順次処理
 *
 */

usleep(mt_rand(0,100000)); // 同時起動をずらす

// lock
$output = [];
//exec('ps x | grep "[0-9]:[0-9]\{2\}\.[0-9]\{2\} \(gtimeout [0-9]* \)*[0-9a-zA-Z_/\.-]*php .*cli/batch_phantom\.php"', $output);
exec('ps x | grep "[0-9]:[0-9]\{2\}\.[0-9]\{2\} [0-9a-zA-Z_/\.-]*php .*cli/batch_phantom\.php"', $output);
//exec('ps x | grep "php .*cli/batch_phantom.php"', $output);
if (count($output) > 8) { // phantomは同時起動3つくらい？8にした
    exit;
}

include(__DIR__ . '/../inc/common.php');
$common = new Common;

chdir(__DIR__ . '/../www/');
$path = $common->config['path'];

$return = [];

$engine = 'phantom';

$command = 'PATH=$PATH:' . $path . ' gtimeout 60 phantomjs ' . __DIR__ .'/render_phantom.js';
//$command = 'PATH=$PATH:' . $path . ' phantomjs ' . __DIR__ .'/render_phantom.js';
//$command = $path . 'casperjs --engine=phantomjs cli/render_casper.js phantom';

// DB
try {
    $pdo = new PDO($common->config['database']['db'], $common->config['database']['user'], $common->config['database']['password']);
} catch(PDOException $e) {
    $common->logger("DB Error \n" . print_r($e->getMessage(), true), 'batch_phantom_log');
    exit;
}

// 最初と最後だけ、を繰り返す。＞毎度取り直すのは更新された時のため＞新しいクエリ優先
for ($i = 0; $i < 1000; $i ++) {
    $stmt_find = $pdo->query('select * from queue_phantom where status = \'\' order by priority ASC, created_at DESC limit 1');
    $res = $stmt_find->fetchAll(PDO::FETCH_ASSOC);
    if (!$res) {
        // 終わった
        exit;
    }

    $queue = [];
    $queue []= array_pop($res); // 最新のみ

    // busyフラグを立てる
    foreach ($queue as $key => $val) {
        $res = $pdo->exec('update queue_phantom SET status = \'busy\' where id = ' . $val['id'] . ' and status = \'\'');
        if (!$res) {
            // この間に他のプロセスに取られた
            unset($queue[$key]);
        }
    }

//    var_dump($queue);

    foreach ($queue as $val) {
        $url = $val['url'];
        $width = $val['width'];
        $height = $val['height'];
        $ua = $val['user_agent'];
        $zoom = $val['zoom'];
        $resize = $val['resize'];
        $delay = $val['delay'];

        $common->logger("$url ($width*$height)", 'batch_phantom_log');

        $file = __DIR__ . '/../www/render/' . $engine . '/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '_' . $zoom . '_' . $resize . '_' . $delay . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url) . '.png';

        $str = $command . ' ' . $url . ' ' . $width . ' ' . $height . ' \'' . $ua . '\' ' . $zoom . ' ' . $resize . ' ' . $delay;
        $str .= ' 2>&1'; // エラーも渡す
        $output = [];
        exec($str, $output); // 取得処理

        $common->logger($str . "\n" . implode("\n", $output), 'batch_phantom_log');

        $flag = false;
        foreach ($output as $line) {
            $output2 = [];
            if (preg_match('/^Phantom([^:]*):(.*)/', $line, $output2)) {
                if ($output2[1] == 'Status' && trim($output2[2]) == '404') {
                    // phantomはnot found画像を記録しちゃう？？？わからん
                    $pdo->exec('update queue_phantom SET status = \'none\' where id = ' . $val['id']);
                    $flag = true;
                    break;
                } else if ($output2[1] == 'Ok') {
                    $stmt_delete = $pdo->prepare('delete from queue_phantom where id=:id');
                    $stmt_delete->execute([
                            'id' => $val['id'],
                        ]);
                    $flag = true;
                    // deleteなのでログを残す
                    $common->logger(implode("\t", $val), 'done_phantom_log');
                    break;
                } else if ($output2[1] == 'Error') {
                    $common->logger('> !!!Error!!!', 'batch_phantom_log');
                    $pdo->exec('update queue_phantom SET status = \'error\' where id = ' . $val['id']);
                    $flag = true;
                    break;
                }
            }
        }
        if (! $flag) {
            // Phantomが反応してない？
            $common->logger('> !!!Fatal Error!!!', 'batch_phantom_log');
            $pdo->exec('update queue_phantom SET status = \'error\' where id = ' . $val['id']);
            $flag = true;
        }

        // 縮小
        if ($resize != 100 && file_exists($file)) {
            $resize_width = $width * $resize * 0.01;
            $image = new Imagick($file);
            $image->thumbnailImage($resize_width, 0);
            $image->writeImage($file);
            $image->destroy();
        }
    }
}
