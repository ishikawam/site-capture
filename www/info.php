<?php
/**
 * サーバ、システム等情報を返す。Mac Yosemite前提。
 *
 * - return
 * json
 */

include(__DIR__ . '/../inc/common.php');
$common = new Common;

$return = [];

// DB
try {
    $pdo = new PDO($common->config['database']['db'], $common->config['database']['user'], $common->config['database']['password']);
} catch(PDOException $e) {
    $common->logger("DB Error \n" . print_r($e->getMessage(), true), 'info.php_log');
    exit;
}

// queue phantom, slimer
foreach(['phantom', 'slimer'] as $engine) {
    $stmt_find = $pdo->query('select status,width,height,user_agent,zoom,resize,priority,ip,count(*) as count from queue_' . $engine . ' group by status,width,height,user_agent,zoom,resize,priority,ip;');
    while ($val = $stmt_find->fetch(PDO::FETCH_ASSOC)) {
        $status = $val['status']; // '' の場合も
        unset($val['status']);
        $val['width'] = (int)$val['width'];
        $val['height'] = (int)$val['height'];
        $val['zoom'] = (int)$val['zoom'];
        $val['resize'] = (int)$val['resize'];
        $val['priority'] = (int)$val['priority'];
        $val['count'] = (int)$val['count'];
        $return['queue'][$engine][$status][] = $val;
    }
}

// df
exec('df -m', $df);
/* sample
Filesystem    1M-blocks  Used Available Capacity  iused    ifree %iused  Mounted on
/dev/disk1       114588 39968     74369    35% 10295857 19038686   35%   /
*/
foreach($df as $val) {
    $val = preg_split('/ +/', $val);
    if (end($val) == 'on') {
        array_pop($val);
        $keys = $val;
    } else if (end($val) == '/') {
        foreach($keys as $index => $key) { // この時点で$keysが取れている前提
            $return['df'][$key] = @$val[$index];
        }
        break;
    }
}

// ps head 5
foreach(['r'=>'cpu', 'm'=>'mem'] as $option => $type) {
    $ps = [];
    exec('ps auxwww -' . $option . ' | head -6', $ps);
    /* sample
       USER              PID  %CPU %MEM      VSZ    RSS   TT  STAT STARTED      TIME COMMAND
       root              184   7.6  0.0  2472680   3868   ??  Ss    1:47PM   4:35.99 sysmond
    */
    foreach($ps as $index => $val) {
        $output = [];
        preg_match('/^([^ ]+) +([^ ]+) +([^ ]+) +([^ ]+) +([^ ]+) +([^ ]+) +([^ ]+) +([^ ]+) +([^ ]+) +([^ ]+) +(.*)$/', $val, $output);
        unset($output[0]);

        if ($output[1] == 'USER') {
            $keys = $output;
        } else {
            foreach($keys as $index2 => $key) { // この時点で$keysが取れている前提
                $return['ps'][$type][$index-1][$key] = @$output[$index2];
            }
        }
    }
}

// uptime
$uptime = exec('uptime');
$outpput = [];
preg_match('/ ([0-9\.]+) ([0-9\.]+) ([0-9\.]+)$/', $uptime, $output);
array_shift($output);
$return['la'] = array_map(function($c){return (float)$c;},$output);

// log
exec('cd ' . __DIR__ . '/../log/; wc -l *', $wc);
foreach($wc as $val) {
    $outpput = [];
    preg_match('/([0-9]+)[ 	]+([^ ]+log)$/', $val, $output);
    if ($output) {
        $return['log'][$output[2]]['wc'] = (int)$output[1];
        $return['log'][$output[2]]['size'] = floor(filesize(__DIR__ . '/../log/' . $output[2]) / 1024); // KB
        $return['log'][$output[2]]['elapsed'] = time() - filemtime(__DIR__ . '/../log/' . $output[2]); // 経過時間
    }
}


// output
header('Content-Type: application/json; charset=utf-8');
echo json_encode($return);
