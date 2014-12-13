<?php
/**
 * Web Page Capture API
 *
 * - request
 * [詳細指定モード：制限したい@todo;]
 * url	url
 * w	width
 * h	height
 * ua	user agent
 * z	zoom
 * s	resize (imagemagick)
 * [定義指定モード] @todo; 未実装
 * d	定義したデバイス名 (ex. pc, mobile, tablet)
 * [共通]
 * e	pahtom or slimer
 * t	json, redirect or image
 * f	force
 *
 * @todo; まだApacheもPHPもデフォルト。チューニングしなきゃ。
 * @todo; HARとか活用したい
 * @todo; トップページ以外も取れる仕様に。？制限もうけたり
 * @todo; エラー画像、Not Found画像、Now Printing画像、とか用意したい。
 */

include(__DIR__ . '/../inc/common.php');
$common = new Common;

$path = $common->config['path'];

$return = array();

$url = !empty($_REQUEST['url']) ? trim($_REQUEST['url']) : '';
$width = !empty($_REQUEST['w']) ? (int)trim($_REQUEST['w']) : 1024;
$height = !empty($_REQUEST['h']) ? (int)trim($_REQUEST['h']) : round($width*3/4);
$ua = !empty($_REQUEST['ua']) ? trim($_REQUEST['ua']) : 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.94 Safari/537.36';
$zoom = !empty($_REQUEST['z']) ? (int)trim($_REQUEST['z']) : 100;
$resize = !empty($_REQUEST['s']) ? (int)trim($_REQUEST['s']) : 100;
$engine = !empty($_REQUEST['e']) ? trim($_REQUEST['e']) : 'phantom'; // phantom, slimer
$type = !empty($_REQUEST['t']) ? trim($_REQUEST['t']) : 'json'; // json, redirect, image
$force = !empty($_REQUEST['f']) ? true : false; // 取得

$force = false; // 今は制限 @todo;

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
        'zoom' => $zoom,
        'resize' => $resize,
        'engine' => $engine,
        'type' => $type,
        'force' => $force,
    ),
);

// キャッシュを確認する。まずDB確認しないのはDBアクセス抑制のため
$file = 'render/' . $engine . '/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '_' . $zoom . '_' . $resize . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url) . '.png';
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
        $pdo = new PDO($common->config['database']['db'], $common->config['database']['user'], $common->config['database']['password']);
    } catch(PDOException $e) {
        $common->logger("DB Error \n" . print_r($e->getMessage(), true), 'capture.php_log');
        exit;
    }

    // キューに突っ込む
    $stmt_find = $pdo->prepare('select * from queue_' . $engine .  ' where url=:url and width=:width and height=:height and user_agent=:user_agent and zoom=:zoom and resize=:resize order by id DESC limit 1');
    $stmt_find->execute(array(
            'url' => $url,
            'width' => $width,
            'height' => $height,
            'user_agent' => $ua,
            'zoom' => $zoom,
            'resize' => $resize,
        ));
//    $res = $stmt_find->fetchAll(PDO::FETCH_ASSOC);
    $res = $stmt_find->fetch();
    if (!$res || $force) {
        $stmt_insert = $pdo->prepare('insert into queue_' . $engine . ' set url=:url, width=:width, height=:height, user_agent=:user_agent, zoom=:zoom, resize=:resize, ip=:ip');
        $stmt_insert->execute(array(
                'url' => $url,
                'width' => $width,
                'height' => $height,
                'user_agent' => $ua,
                'zoom' => $zoom,
                'resize' => $resize,
                'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'],
            ));
        $status = 'wait';
//        usleep(1000); // すぐDB読んでも反映されないので

        // 取得スクリプトを起こす。batch_phantom.php or batch_slimer.php
        exec('php ' . __DIR__ . '/../cli/batch_' . $engine . '.php > ' . __DIR__ . '/../log/exec_batch_log &');
//        exec('php ' . __DIR__ . '/../cli/batch_' . $engine . '.php > /dev/null &');

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
