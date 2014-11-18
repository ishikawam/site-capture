<?php
/**
 * Web Page Capture API
 *
 * @todo; デフォルト値がrender...js, capture.jsと冗長しているので一元化したい
 * @todo; まだApacheもPHPもデフォルト。チューニングしなきゃ。
 * @todo; リダイレクトされたら取れない？？？
 * @todo; HARとか活用したい
 * @todo; viewport対応したい
 * @todo; トップページ以外も取れる仕様に。？制限もうけたり
 * @todo; エラー画像、Not Found画像、Now Printing画像、とか用意したい。
 */

ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL & ~E_NOTICE);

//header('Access-Control-Allow-Origin: *'); //crossdomainを許容

$path = __DIR__ . '/../node_modules/.bin/';

$return = array();

$url = trim($_REQUEST['url']);
$width = trim($_REQUEST['w']) ? trim($_REQUEST['w']) : 1024;
$height = trim($_REQUEST['h']) ? trim($_REQUEST['h']) : round($width*3/4);
$ua = trim($_REQUEST['ua']) ? trim($_REQUEST['ua']) : 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.94 Safari/537.36';
$engine = trim($_REQUEST['e']) ? trim($_REQUEST['e']) : 'phantom'; // phantom, slimer
$type = trim($_REQUEST['t']) ? trim($_REQUEST['t']) : 'json'; // json, redirect, image
$force = trim($_REQUEST['f']) == true; // 取得

$force = false; // 今は制限

$device = 'pc';
if ($width / $height < 0.6) {
    $device = 'mobile';
} else if ($width / $height < 1) {
    $device = 'tablet';
}

// validation
// http:// 省略の場合、付加
if (!preg_match('/^(https?|ftp):\/\//', $url)) {
    $url = 'http://' . $url;
}
// ドメイン部を抽出
if (!preg_match('/^(https?|ftp):\/\/(([a-zA-Z0-9][a-zA-Z0-9-]+\.)+[a-zA-Z]+)/', $url, $output)) {
    $return['status'] = 'error';
    $return['imageUrl'] = 'http://' . $_SERVER['HTTP_HOST'] . '/img/error_' . $device . '.png';
    $return['result'] = 'URL invalid.';
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($return);
    exit;
}

$url = $output[0];
$return = array(
    'request' => array(
        'url' => $url,
        'width' => $width,
        'height' => $height,
        'userAgent' => $ua,
        'engine' => $engine,
        'type' => $type,
        'force' => $force,
    ),
);

// キャッシュを確認する。まずDB確認しないのはDBアクセス抑制のため
$file = 'render/' . $engine . '/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url) . '.png';
$imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $file;
$cacheUrl = $imageUrl;

if (!$force && file_exists($file)) {
    $status = 'cache';

    // har, content
    if ($engine == 'phantom') {
        $file_har = 'har/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url);
        $file_yslow = 'yslow/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url);
        $file_content = 'content/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url) . '.html';
        if (file_exists($file_har)) {
            $return['harUrl'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $file_har;
            $return['yslowUrl'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $file_yslow;
            if (!file_exists($file_yslow)) {
                // yslow生成
                if (!file_exists(dirname($file_yslow))) {
                    mkdir(dirname($file_yslow), 0755, true);
                }
                exec($path . 'yslow --info basic --format plain ' . $file_har . ' > ' . $file_yslow);
            }
        }
        if (file_exists($file_content)) {
            $return['contentUrl'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $file_content;
        }
    }
} else {
    // キャッシュがない or 強制

    // DB
    try {
        $pdo = new PDO('mysql:host=localhost; dbname=capture', 'capture', '');
    } catch(PDOException $e) {
        var_dump($e->getMessage());
        exit;
    }

    // キューに突っ込む
    $stmt_find = $pdo->prepare('select * from queue_' . $engine .  ' where url=:url and width=:width and height=:height and user_agent=:user_agent order by id DESC limit 1');
    $stmt_find->execute(array(
            'url' => $url,
            'width' => $width,
            'height' => $height,
            'user_agent' => $ua,
        ));
//    $res = $stmt_find->fetchAll(PDO::FETCH_ASSOC);
    $res = $stmt_find->fetch();
    if (!$res || $force) {
        $stmt_insert = $pdo->prepare('insert into queue_' . $engine . ' set url=:url, width=:width, height=:height, user_agent=:user_agent, ip=:ip');
        $stmt_insert->execute(array(
                'url' => $url,
                'width' => $width,
                'height' => $height,
                'user_agent' => $ua,
                'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'],
            ));
        $status = 'wait';
//        usleep(1000); // すぐDB読んでも反映されないので

        // 取得スクリプトを起こす。batch_phantom.php or batch_slimer.php
        exec('php ' . __DIR__ . '/../cli/batch_' . $engine . '.php > /dev/null &');

    } else {
        $status = $res['status'];
        if ($status == 'busy' || !$status) {
            $status = 'wait';
        }
    }

    switch ($status) {
        case 'error':
            $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/img/error_' . $device . '.png';
            break;
        case 'wait':
            $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/img/wait_' . $device . '.png';
            $return['cacheUrl'] = $cacheUrl; // waitの時だけimageUrl予測を
            break;
        case 'none':
            $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/img/none_' . $device . '.png';
            break;
    }
}

$return['status'] = $status;
$return['imageUrl'] = $imageUrl;

if ($type == 'image') {
    // 画像自体を返す @todo; キャッシュ前提
    header('Pragma: cache');
    header('Cache-Control: private, max-age=' . (60 * 60 * 24 * 7)); // 7日キャッシュ
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        header('HTTP/1.1 304 image not modified');
        exit;
    }
    header('Content-Type: image/png');
    header('Accept-Ranges: bytes');
    header('Last-Modified: ' . date('r', time()));
    ob_clean();
    flush();
    readfile($file);
} else if ($type == 'redirect') {
    header('Location: ' . $imageUrl);
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($return);
}
