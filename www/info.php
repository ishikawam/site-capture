<?php
/**
 * サーバ、システム等情報を返す。Mac Yosemite前提。
 *
 * - return
 * json
 */

include(__DIR__ . '/../inc/common.php');  // load timezone

header('Content-Type: application/json; charset=utf-8');
$json = file_get_contents(__DIR__ . '/../tmp/info');
$data = json_decode($json);

if (strtotime($data->updated_at) < (time() - 60*5)) {
    // 5分以上更新がなかったら500エラー。でも内容も返す。
    header('HTTP', true, 500);
    // exit;
}

echo file_get_contents(__DIR__ . '/../tmp/info');
