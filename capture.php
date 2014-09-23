<?php
/**
 * Web Page Capture API
 *
 * @todo; デフォルト値がrender...js, capture.jsと冗長しているので一元化したい
 * @todo; まだApacheもPHPもデフォルト。チューニングしなきゃ。
 * @todo; リダイレクトされたら取れない？？？
 * @todo; HARとか活用したい
 * @todo; imagemagickとかでサイズ調整、トリミングしたい
 */

ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL & ~E_NOTICE);

$return = array();

$url = trim($_REQUEST['url']);
$width = trim($_REQUEST['w']) ? trim($_REQUEST['w']) : 1024;
$height = trim($_REQUEST['h']) ? trim($_REQUEST['h']) : round($width*3/4);
$ua = trim($_REQUEST['ua']) ? trim($_REQUEST['ua']) : 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.94 Safari/537.36';
$force = trim($_REQUEST['f']) == true; // 強制取得
$image = trim($_REQUEST['i']) == true; // falseでJSONで情報返す。 trueで画像自体を返す。＞@todo; もちょっと仕様固める

// validation
// http:// 省略の場合、付加
if (!preg_match('/^(https?|ftp):\/\//', $url)) {
    $url = 'http://' . $url;
}
// ドメイン部を抽出
if (!preg_match('/^(https?|ftp):\/\/(([a-zA-Z0-9][a-zA-Z0-9-]+\.)+[a-zA-Z]+)/', $url, $output)) {
    $return['status'] = 'error';
    $return['result'] = 'URL invalid.';
    header("Content-Type: application/json; charset=utf-8");
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
        'force' => $force,
        'image' => $image,
    ),
);

// キャッシュ
$file = 'render/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url) . '.png';
$file_har = 'har/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url);
$file_content = 'content/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url) . '.html';
$return['cacheUrl'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $file;
$return['harUrl'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $file_har;
$return['contentUrl'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $file_content;

if (!$force && file_exists($file)) {
    $return['status'] = 'cache';
} else {
    // casperjs use phantomjs
    $str = ('casperjs --engine=phantomjs cli/render_casper.js ' . $url . ' ' . $width . ' ' . $height . ' \'' . $ua . '\'');
    // casperjs use slimerjs
    $str = ('casperjs --engine=slimerjs cli/render_casper.js ' . $url . ' ' . $width . ' ' . $height . ' \'' . $ua . '\'');
    // phantomjs
    $str = ('phantomjs cli/render_phantom.js ' . $url . ' ' . $width . ' ' . $height . ' \'' . $ua . '\'');
// @todo;全然とちゅう
    $output = array();
    $result = exec($str, $output);
    if (!preg_match('/^Rendered /', $result)) {
        $return['status'] = 'error';
        $return['result'] = $result;
        $return['command'] = $str;
    } else {
        $return['status'] = 'ok';
        $return['result'] = $result;
        $return['command'] = $str;
    }
}

if ($image) {
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
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($return);
}
