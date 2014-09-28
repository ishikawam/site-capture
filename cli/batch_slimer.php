<?php
/**
 * Slimerをキューから順次処理
 *
 */

ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL & ~E_NOTICE);

// lock
$output = array();
$count = 0;
exec('ps x | grep "batch_slimer.php"', $output);
foreach ($output as $val) {
    if (preg_match('|php cli/batch_slimer.php|', $val)) {
        $count ++;
    }
}
if ($count > 1) {
    exit;
}

$path = '/home/m_ishikawa/.nvm/v0.10.22/bin/';

$engine = 'casper_slimer';
$return = array();

$display = ':14.0';

$command = 'DISPLAY=' . $display . ' ' . $path . 'casperjs --engine=slimerjs cli/render_casper.js slimer';
//$command = 'xvfb-run ' . $path . 'casperjs --engine=slimerjs cli/render_casper.js slimer'; // xvfb-runだと遅い。$DISPLAY使ったほうがいいなあ。GNOMEの。

// DB
try {
    $pdo = new PDO('mysql:host=localhost; dbname=capture', 'capture', '');
} catch(PDOException $e) {
    var_dump($e->getMessage());
    exit;
}

// 最初と最後だけ、を繰り返す。＞毎度取り直すのは更新された時のため＞新しいクエリ優先
while (true) {
    $stmt_find = $pdo->query('select * from queue_slimer');
    $res = $stmt_find->fetchAll(PDO::FETCH_ASSOC);
    var_dump($res);
    if (!$res) {
        // 終わった！けどねばる
        exit;
    }

    $queue = array();
    $queue []= array_pop($res);
    if ($res) {
        $queue []= array_shift($res);
    }

    foreach ($queue as $val) {
        $url = $val['url'];
        $width = $val['width'];
        $height = $val['height'];
        $ua = $val['user_agent'];

        $file = 'render/' . $engine . '/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url) . '.png';

        // slimer
        $str = $command . ' ' . $url . ' ' . $width . ' ' . $height . ' \'' . $ua . '\'';
        $str .= ' 2>&1'; // エラーも渡す
        $output = array();
        exec($str, $output); // 取得処理

        echo(implode("\n", $output));

        $stmt_delete = $pdo->prepare('delete from queue_slimer where id=:id');
        $stmt_delete->execute(array(
                'id' => $val['id'],
            ));
    }
}
