/**
 * Casper render URL to file
 *
 * 成功すれば保存、失敗すれば何もしない。
 * 保存場所は sha1(user_agent)の先頭16文字+'_'+width+'_'+height+'/'+sha1(url)の先頭2文字+'/'+ sha1(url) .png
 * @todo; netsniff.js のHARを活用したい
 * @todo; リダイレクトされたらsuccessなのに保存されないぽい
 * @todo; やっぱり並列処理考えないと、現状はプロセス別に走ってるのに待っちゃう。。なんでかわからない。
 * @todo; casperjs使いたい。
 */

var system = require('system');
var fs = require('fs');
var sha1 = fs.read('../www/bower_components/cryptojslib/rollups/sha1.js');
eval(sha1); //よくないね
var viewport_zoom = fs.read('../www/bower_components/viewport-zoom/ViewportZoom.js');
eval(viewport_zoom); //よくないね

var urls = null;
if (system.args.length > 4) {
    // 引数 3, engine (phantomjs or slimerjs) 4,url 5,width(デフォルト1024) 6,height(デフォルトwidth*3/4) 7, useragent(デフォルトMac)
    urls = Array.prototype.slice.call(system.args, 1);
} else {
    console.log('CasperError: invalid');
}

var engine = urls[3];
var url = urls[4];
var width = urls[5] ? parseInt(urls[5]) : 1024;
var height = urls[6] ? parseInt(urls[6]) : Math.round(width*3/4); // =768
var user_agent = urls[7] ? urls[7] : 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.78.2 (KHTML, like Gecko) Version/7.0.6 Safari/537.78.2'; // mac safari 1024x768
//    var user_agent = urls[7] ? urls[7] : 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.94 Safari/537.36'; // mac chrome 1024x768

var casper = require('casper').create({
    viewportSize: {
        width: width,
        height: height,
        verbose: true,
        logLevel: 'debug',
    },
    timeout: 60 * 1000
});

casper.start();

casper.wait(3000); // 3秒のDelay必要

if (user_agent) {
    casper.userAgent(user_agent);
}

//console.log([url, width, height, user_agent]);

// 保存ファイル名 sha1のprefix 2文字をディレクトリにして(gitと同じ)想定256*10000=200万サイトくらいかな
var dir = CryptoJS.SHA1(user_agent).toString().substr(0, 16) + '_' + width + '_' + height;
var url_sha1 = CryptoJS.SHA1(url).toString();
var file = 'render/' + engine + '/' + dir + '/' + url_sha1.substr(0, 2) + '/' + url_sha1 + '.png';
//var file_har = 'har/'+ dir + '/' + url_sha1.substr(0, 2) + '/' + url_sha1;
//var file_content = 'content/'+ dir + '/' + url_sha1.substr(0, 2) + '/' + url_sha1 + '.html';

//casper.open(url).then(function() {
casper.open(url).viewport(width, height).then(function() {
    this.echo(this.getTitle());

    status_code = this.status().currentHTTPStatus;

    if (status_code === null) {
        // エラーを画像保存しないように。Not Found扱い
        this.echo('CasperError: null');
        return;
    }

    // 301, 302, 200, が成功っぽい。他が来た時は？とりあえずログに残す
    this.echo('CasperOk: ' + status_code);

    var meta_viewport = this.page.evaluate(function() {
        // phantomjs: 透過の場合用 背景を白に
        document.body.bgColor = 'white';

        var meta = document.getElementsByName('viewport').item(0);
        if (meta) {
            return meta.content;
        }
        return '';
    });

    if (user_agent.match(/iPhone/) || user_agent.match(/iPad/)) {
        console.log('meta_viewport: ' + meta_viewport);

        var zoomFactor = ViewportZoom.get(width, meta_viewport);

        console.log('zoom: ' + zoomFactor);

        this.zoom(zoomFactor);
    }

    this.capture(file, {
        // トリミング
        top: 0,
        left: 0,
        width: width,
        height: height
    });
});

casper.run();
