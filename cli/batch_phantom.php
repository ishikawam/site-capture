<?php
/**
 * Phantomをキューから順次処理
 *
 */

include(__DIR__ . '/../inc/common.php');
$common = new Common;

chdir(__DIR__ . '/../www/');
$path = $common->config['path'];

usleep(mt_rand(0,1000000)); // 同時起動をずらす

// lock
$output = array();
$count = 0;
exec('ps x | grep "batch_phantom.php"', $output);
foreach ($output as $val) {
    if (preg_match('|php cli/batch_phantom.php|', $val)) {
        $count ++;
    }
}
if ($count > 2) { // phantomは同時起動3つくらい？
    var_dump($output);
    exit;
}

$return = array();

$engine = 'phantom';

$command = 'PATH=$PATH:' . $path . ' phantomjs ' . __DIR__ .'/render_phantom.js';
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
    $stmt_find = $pdo->query('select * from queue_phantom where status = \'\'');
    $res = $stmt_find->fetchAll(PDO::FETCH_ASSOC);
//    var_dump($res);
    if (!$res) {
        // 終わった
        exit;
    }

    $queue = array();
    $queue []= array_pop($res);
    if ($res) {
        $queue []= array_shift($res);
    }

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

        echo("$url ($width*$height) \n");

        $file = __DIR__ . '/../www/render/' . $engine . '/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '_' . $zoom . '_' . $resize . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url) . '.png';

        $str = $command . ' ' . $url . ' ' . $width . ' ' . $height . ' \'' . $ua . '\' ' . $zoom . ' ' . $resize;
        $str .= ' 2>&1'; // エラーも渡す
        $output = array();
        exec($str, $output); // 取得処理

        $common->logger("\n" . $str . "\n" . implode("\n", $output), 'batch_phantom_log');

        $flag = false;
        foreach ($output as $line) {
            $output2 = array();
            if (preg_match('/^Phantom([^:]*):(.*)/', $line, $output2)) {
                if ($output2[1] == 'Status' && trim($output2[2]) == '404') {
                    // phantomはnot found画像を記録しちゃう？？？わからん
                    $pdo->exec('update queue_phantom SET status = \'none\' where id = ' . $val['id']);
                    $flag = true;
                    break;
                } else if ($output2[1] == 'Ok') {
                    $stmt_delete = $pdo->prepare('delete from queue_phantom where id=:id');
                    $stmt_delete->execute(array(
                            'id' => $val['id'],
                        ));
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
        if (!$flag) {
            // Phantomが反応してない？
            $common->logger('> !!!Fatal Error!!!', 'batch_phantom_log');
            $pdo->exec('update queue_phantom SET status = \'error\' where id = ' . $val['id']);
            $flag = true;
        }

        // 縮小
        if ($resize != 100) {
            $resize_width = $width * $resize * 0.01;
            $image = new Imagick($file);
            $image->thumbnailImage($resize_width, 0);
            $image->writeImage($file);
            $image->destroy();
        }
    }
}
