<?php

// for debug
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);
//ini_set('error_reporting', E_ALL & ~E_NOTICE);

// setting
date_default_timezone_set('Asia/Tokyo');

class Common
{
    public $config = array(
        'path' => array(
            // casperjs, phantomjs, slimerjs
            '__DIR__/../node_modules/.bin',
            // node
            '/Users/tp-dayama/.nvm/v0.10.33/bin',
        ),

        'database' => array(
            // @todo;
        ),

        'slimerjs' => array( // @todo; use
            'display_num' => ':14.0', // @todo; 可変
        ),

        'device' => array(
            // @todo; 定数系はcapture.phpと共通で使いたい
            'pc' => array(
                'width' => 1920,
                'height' => 1080,
                // mac safari 1024x768
                'ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.78.2 (KHTML, like Gecko) Version/7.0.6 Safari/537.78.2',
                // mac chrome 1024x768
//                'ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.94 Safari/537.36'
            ),
            'tablet' => array(
                'width' => 768,
                'height' => 1024,
                'ua' => 'Mozilla/5.0 (iPad; CPU OS 4_3_5 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8L1 Safari/6533.18.5',
            ),
            'mobile' => array(
                'width' => 320,
                'height' => 568,
                'ua' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 7_0 like Mac OS X; en-us) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11A465 Safari/9537.53',
            ),
        ),

        'engines' => array(
/*
            array(
                'title' => 'Linux:Phantomjs',
                'name' => 'phantom',
                'type' => 'phantom',
                'server' => 'http://capture.osae.me'
            ),
*/
            array(
                'title' => 'Mac:Phantomjs',
                'name' => 'macphantom',
                'type' => 'phantom',
                'server' => 'http://homej.didit.jp'
            ),
/*
            array(
                'title' => 'Linux:Slimerjs (via Casperjs)',
                'name' => 'linuxslimer',
                'type' => 'slimer',
                'server' => 'http://capture.osae.me'
            ),
*/
            array(
                'title' => 'Mac:Slimerjs (via Casperjs)',
                'name' => 'macslimer',
                'type' => 'slimer',
                'server' => 'http://homej.didit.jp'
            ),
        ),
    );

    public function __construct()
    {
        foreach ($this->config['path'] as &$path) {
            $path = str_replace('__DIR__', __DIR__, $path);
        }
        $this->config['path'] = implode(':', $this->config['path']);

        // 無駄だけど、phpとjsで設定ファイルを共通化するためにアクセスのたび保存。。。いい方法はないものか
        // 内部用
        file_put_contents(__DIR__ . '/config.json', json_encode($this->config));
        // 公開用
        file_put_contents(__DIR__ . '/../www/config.json', json_encode($this->config));
    }
}