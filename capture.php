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

$path = '/home/m_ishikawa/.nvm/v0.10.22/bin/';

$return = array();

$url = trim($_REQUEST['url']);
$width = trim($_REQUEST['w']) ? trim($_REQUEST['w']) : 1024;
$height = trim($_REQUEST['h']) ? trim($_REQUEST['h']) : round($width*3/4);
$ua = trim($_REQUEST['ua']) ? trim($_REQUEST['ua']) : 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.94 Safari/537.36';
$engine = trim($_REQUEST['e']) ? trim($_REQUEST['e']) : 'phantom'; // phantom, casper_phantom or casper_slimer
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
        'force' => $force,
        'image' => $image,
    ),
);

$engines = array(
    'phantom' => array(
        'command' => 'phantomjs cli/render_phantom.js',
    ),
    'casper_phantom' => array(
        'command' => $path . 'casperjs --engine=phantomjs cli/render_casper.js phantom',
    ),
/*
    'casper_slimer' => array(
        'command' => 'sh cli/render_casper.sh', // デスクトップ落ちたら番号かわる。。。
//        'command' => 'xvfb-run ' . $path . 'casperjs --engine=slimerjs cli/render_casper.js slimer', // xvfb-runだと遅い。$DISPLAY使ったほうがいいなあ。GNOMEの。

//        'command' => 'DISPLAY=:10.0 ' . $path . 'casperjs --engine=slimerjs cli/render_casper.js slimer', // デスクトップ落ちたら番号かわる。。。
    ),
*/
);

// DB
try {
    $pdo = new PDO('mysql:host=localhost; dbname=capture', 'capture', '');
} catch(PDOException $e) {
    var_dump($e->getMessage());
    exit;
}

// キャッシュ
$file = 'render/' . $engine . '/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url) . '.png';
$return['cacheUrl'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $file;
if ($engine == 'phantom') {
    // phantomjsの場合だけhar, content を取得して保存
    $file_har = 'har/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url);
    $file_content = 'content/' . substr(sha1($ua), 0, 16) . '_' . $width . '_' . $height . '/' . substr(sha1($url), 0, 2) . '/' . sha1($url) . '.html';
    $return['harUrl'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $file_har;
    $return['contentUrl'] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $file_content;
}

if (!$force && file_exists($file)) {
    $return['status'] = 'cache';
} else {
    if ($engine == 'casper_slimer') {

        // キューに突っ込む
        $stmt_find = $pdo->prepare('select * from queue_slimer where url=:url and width=:width and height=:height and user_agent=:user_agent limit 1');
        $stmt_find->execute(array(
                'url' => $url,
                'width' => $width,
                'height' => $height,
                'user_agent' => $ua,
            ));
        $res = $stmt_find->fetch();
        if (!$res) {
            $stmt_insert = $pdo->prepare('insert into queue_slimer set url=:url, width=:width, height=:height, user_agent=:user_agent, ip=:ip');
            $stmt_insert->execute(array(
                    'url' => $url,
                    'width' => $width,
                    'height' => $height,
                    'user_agent' => $ua,
                    'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'],
                ));
        }

        // 取得スクリプトを起こす
        usleep(1000); // すぐDB読んでも反映されないので
        exec('php cli/batch_slimer.php &');

        // 取れるまで待つ
        $start_time = time();
        while (true) {
            $stmt_find->execute(array(
                    'url' => $url,
                    'width' => $width,
                    'height' => $height,
                    'user_agent' => $ua,
                ));
            $res = $stmt_find->fetch();
            if (!$res) {
                // 完了
                if (!file_exists($file)) {
                    // 取得できなかった
                    $return['status'] = 'error';
                    $return['cacheUrl'] = 'dummy'; // @todo; エラー画像返したい
                } else {
                    // 取得できた
                    $return['status'] = 'ok';
                }
                break;
            }
            if (time() - $start_time > 60) {
                $return['status'] = 'error';
                $return['cacheUrl'] = 'dummy'; // @todo; エラー画像返したい
                break;
            }
        }

        // 取得スクリプトを起こす
        exec('php cli/batch_slimer.php &');

    } else {
        // phantom
        $str = $engines[$engine]['command'] . ' ' . $url . ' ' . $width . ' ' . $height . ' \'' . $ua . '\'';
        $str .= ' 2>&1'; // エラーも渡す
        $output = array();
        $result = exec($str, $output); // 取得処理

        if ($engine == 'phantom' && !preg_match('/^Rendered /', $result)) {
            $return['status'] = 'error';
            $return['cacheUrl'] = 'dummy'; // @todo; エラー画像返したい
            $return['result'] = $result;
            $return['output'] = $output;
            $return['command'] = $str;
        } else if ($engine == 'casper_phantom' && preg_match('/^Error$/', $result)) {
            $return['status'] = 'error';
            $return['cacheUrl'] = 'dummy'; // @todo; エラー画像返したい
            $return['result'] = $result;
            $return['output'] = $output;
            $return['command'] = $str;
        } else {
            $return['status'] = 'ok';
            $return['result'] = $result;
            $return['output'] = $output;
            $return['command'] = $str;
        }
    }
}

if ($image) {
    // 画像自体を返す
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
