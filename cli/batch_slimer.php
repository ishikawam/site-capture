<?php
/**
 * Slimerをキューから順次処理
 *
 */

ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL & ~E_NOTICE);

$path = ''; // mac
$display = ''; // mac
if (exec('uname') == 'Linux') {
    $path = '/home/m_ishikawa/.nvm/v0.10.22/bin/';
    $display_num = ':14.0'; // @todo; 可変
    $display = 'DISPLAY=' . $display_num . ' ';
}

/*
// lock
$output = array();
$count = 0;
exec('ps x | grep "batch_slimer.php"', $output);
foreach ($output as $val) {
    if (preg_match('|php cli/batch_slimer.php|', $val)) {
        $count ++;
    }
}
if ($count > 30) { // slimerは同時起動1つくらい。だけど、何故かうまくいかないので。。排他考えなきゃ
error_log('Over' . $count);
    var_dump($output);
    exit;
}
*/

// 排他にセマフォ使ってみる...やっぱやめた。ぜんぜんだめ。リリースされたら一気に走り過ぎる
$sid = sem_get(884);
$sem = sem_acquire($sid);
error_log('semafo!');
$return = array();

$engine = 'slimer';

$command = $display . $path . 'casperjs --engine=slimerjs cli/render_casper.js slimer';
//$command = 'xvfb-run ' . $path . 'casperjs --engine=slimerjs cli/render_casper.js slimer'; // xvfb-runだと遅い。$DISPLAY使ったほうがいいなあ。GNOMEの。

// DB
try {
    $pdo = new PDO('mysql:host=localhost; dbname=capture', 'capture', '');
} catch(PDOException $e) {
    var_dump($e->getMessage());
    exit;
}

// 最初と最後だけ、を繰り返す。＞毎度取り直すのは更新された時のため＞新しいクエリ優先
for ($i = 0; $i < 1000; $i ++) {
    $stmt_find = $pdo->query('select * from queue_slimer where status = \'\'');
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
        $res = $pdo->exec('update queue_slimer SET status = \'busy\' where id = ' . $val['id'] . ' and status = \'\'');
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

        echo('> ' . implode("\n", $output) . "\n");

        if (preg_match('/^CasperOk:/', $output[0])) {
            $stmt_delete = $pdo->prepare('delete from queue_slimer where id=:id');
            $stmt_delete->execute(array(
                    'id' => $val['id'],
                ));
        } else if (preg_match('/^CasperError: null/', $output[0])) {
            $pdo->exec('update queue_slimer SET status = \'none\' where id = ' . $val['id']);
        } else {
            echo("!!!Error!!!\n");
            // DISPLAYがおかしいとかの理由でslimerjsが機能していないかも
            $pdo->exec('update queue_slimer SET status = \'error\' where id = ' . $val['id']);
        }
    }
}

// セマフォを解放
sem_release($sem);
error_log('Done');