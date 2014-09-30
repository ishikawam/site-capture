<?php
/**
 * Phantomをキューから順次処理
 *
 */

ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL & ~E_NOTICE);

$path = ''; // mac
if (exec('uname') == 'Linux') {
    $path = '/home/m_ishikawa/.nvm/v0.10.22/bin/';
}

// lock
$output = array();
$count = 0;
exec('ps x | grep "batch_phantom.php"', $output);
foreach ($output as $val) {
    if (preg_match('|php cli/batch_phantom.php|', $val)) {
        $count ++;
    }
}
if ($count > 3) { // phantomは同時起動3つくらい？
    var_dump($output);
    exit;
}

$return = array();

$engine = 'phantom';

$command = 'phantomjs cli/render_phantom.js';
//$command = $path . 'casperjs --engine=phantomjs cli/render_casper.js phantom';

// DB
try {
    $pdo = new PDO('mysql:host=localhost; dbname=capture', 'capture', '');
} catch(PDOException $e) {
    var_dump($e->getMessage());
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

        echo("$url ($width*$height) \n");

        $file = 'render/' . $engine . '/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url) . '.png';

        $str = $command . ' ' . $url . ' ' . $width . ' ' . $height . ' \'' . $ua . '\'';
        $str .= ' 2>&1'; // エラーも渡す
        $output = array();
        exec($str, $output); // 取得処理

        echo(implode("\n", $output) . "\n\n");

        $flag = false;
        foreach ($output as $line) {
            $output2 = array();
            if (preg_match('/Phantom([^:]*):(.*)/', $line, $output2)) {
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
                    break;
                } else if ($output2[1] == 'Error') {
                    echo("> !!!Error!!!\n");
                    $pdo->exec('update queue_phantom SET status = \'error\' where id = ' . $val['id']);
                    $flag = true;
                    break;
                }
            }
        }
        if (!$flag) {
            // Phantomが反応してない？
            echo("> !!!Fatal Error!!!\n");
            $pdo->exec('update queue_phantom SET status = \'error\' where id = ' . $val['id']);
            $flag = true;
        }
    }
}
