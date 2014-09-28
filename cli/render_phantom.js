/**
 * Phantom render URL to file
 *  (ref. render_multi_url.js, netsniff.js)
 * 成功すれば保存、失敗すれば何もしない。
 * 保存場所は sha1(user_agent)の先頭16文字+'_'+width+'_'+height+'/'+sha1(url)の先頭2文字+'/'+ sha1(url) .png
 * @todo; netsniff.js のHARを活用したい
 * @todo; リダイレクトされたらsuccessなのに保存されないぽい
 * @todo; やっぱり並列処理考えないと、現状はプロセス別に走ってるのに待っちゃう。。なんでかわからない。
 * @todo; casperjs使いたい。
 */

var system = require('system');
var fs = require('fs');
phantom.injectJs('bower_components/cryptojslib/rollups/sha1.js');

var webpage = require('webpage');

if (!Date.prototype.toISOString) {
    Date.prototype.toISOString = function () {
        function pad(n) { return n < 10 ? '0' + n : n; }
        function ms(n) { return n < 10 ? '00'+ n : n < 100 ? '0' + n : n }
        return this.getFullYear() + '-' +
            pad(this.getMonth() + 1) + '-' +
            pad(this.getDate()) + 'T' +
            pad(this.getHours()) + ':' +
            pad(this.getMinutes()) + ':' +
            pad(this.getSeconds()) + '.' +
            ms(this.getMilliseconds()) + 'Z';
    }
}

function createHAR(address, page)
{
    var entries = [];

    page.resources.forEach(function (resource) {
        var request = resource.request,
            startReply = resource.startReply,
            endReply = resource.endReply;

        if (!request || !startReply || !endReply) {
            return;
        }

        // Exclude Data URI from HAR file because
        // they aren't included in specification
        if (request.url.match(/(^data:image\/.*)/i)) {
            return;
        }

        entries.push({
            startedDateTime: request.time.toISOString(),
            time: endReply.time - request.time,
            request: {
                method: request.method,
                url: request.url,
                httpVersion: "HTTP/1.1",
                cookies: [],
                headers: request.headers,
                queryString: [],
                headersSize: -1,
                bodySize: -1
            },
            response: {
                status: endReply.status,
                statusText: endReply.statusText,
                httpVersion: "HTTP/1.1",
                cookies: [],
                headers: endReply.headers,
                redirectURL: "",
                headersSize: -1,
                bodySize: startReply.bodySize,
                content: {
                    size: startReply.bodySize,
                    mimeType: endReply.contentType
                }
            },
            cache: {},
            timings: {
                blocked: 0,
                dns: -1,
                connect: -1,
                send: 0,
                wait: startReply.time - request.time,
                receive: endReply.time - startReply.time,
                ssl: -1
            },
            pageref: address
        });
    });

    return {
        log: {
            version: '1.2',
            creator: {
                name: "PhantomJS",
                version: phantom.version.major + '.' + phantom.version.minor +
                    '.' + phantom.version.patch
            },
            pages: [{
                startedDateTime: page.startTime.toISOString(),
                id: address,
                title: page.title,
                pageTimings: {
                    onLoad: page.endTime - page.startTime
                }
            }],
            entries: entries
        }
    };
}

/*
Render given url
@param array of URL to render
@param callbackPerUrl Function called after finishing each URL, including the last URL
@param callbackFinal Function called after finishing everything
*/
var RenderUrlsToFile = function(urls, callbackPerUrl, callbackFinal) {
    var page = webpage.create();
    var next = function(status, url, file) {
        page.close();
        callbackPerUrl(status, url, file);
        return callbackFinal();
    };
    var url = urls[0];
    var width = urls[1] ? urls[1] : 1024;
    var height = urls[2] ? urls[2] : Math.round(width*3/4);
    var user_agent = urls[3] ? urls[3] : 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.78.2 (KHTML, like Gecko) Version/7.0.6 Safari/537.78.2'; // mac safari 1024x768
//    var user_agent = urls[3] ? urls[3] : 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.94 Safari/537.36'; // mac chrome 1024x768

    page.viewportSize = {
        width: width,
        height: height
    };
    page.clipRect = {
        // トリミング
        top: 0,
        left: 0,
        width: width,
        height: height
    };
    page.settings.userAgent = user_agent;
    page.settings.resourceTimeout = 60 * 1000;
// iphone5 320x568
//    page.settings.userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 7_0 like Mac OS X; en-us) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11A465 Safari/9537.53';
// ipad 768x1024
//    page.settings.userAgent = 'Mozilla/5.0 (iPad; CPU OS 4_3_5 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8L1 Safari/6533.18.5';
// phantom default
//    page.settings.userAgent = 'Phantom.js bot';

    page.resources = [];
    page.onLoadStarted = function () {
        page.startTime = new Date();
    };
    page.onResourceRequested = function (req) {
        page.resources[req.id] = {
            request: req,
            startReply: null,
            endReply: null
        };
    };
    page.onResourceReceived = function (res) {
        if (res.stage === 'start') {
            page.resources[res.id].startReply = res;
        }
        if (res.stage === 'end') {
            page.resources[res.id].endReply = res;
        }
    };

    return page.open(url, function(status) {
        // 保存ファイル名 sha1のprefix 2文字をディレクトリにして(gitと同じ)想定256*10000=200万サイトくらいかな
        var dir = CryptoJS.SHA1(user_agent).toString().substr(0, 16) + '_' + width + '_' + height;
        var url_sha1 = CryptoJS.SHA1(url).toString();
        var file = 'render/phantom/'+ dir + '/' + url_sha1.substr(0, 2) + '/' + url_sha1 + '.png';
        var file_har = 'har/'+ dir + '/' + url_sha1.substr(0, 2) + '/' + url_sha1;
        var file_content = 'content/'+ dir + '/' + url_sha1.substr(0, 2) + '/' + url_sha1 + '.html';

        page.evaluate(function() {
            // 透過の場合用 背景を白に
            document.body.bgColor = 'white';
        });

        if (status === 'success') {
            return window.setTimeout((function() {
                page.render(file);
                page.endTime = new Date();
                page.title = page.evaluate(function () {
                    return document.title;
                });
                har = createHAR(url, page);
                fs.write(file_har, JSON.stringify(har, undefined, 4));

                fs.write(file_content, page.content);

                return next(status, url, file);
            }), 4000); // 開いて2秒後がだいたい整っていると判断。あと、これしないとJSでリダイレクトの場合何も取れない。@todo; あとそもそもメモリ足りてない
        } else {
            return next(status, url, file);
        }
    });
};

var arrayOfUrls = null;

if (system.args.length > 1) {
    // 引数 1,url 2,width(デフォルト1024) 3,height(デフォルトwidth*3/4) 4, useragent(デフォルトMac)
    arrayOfUrls = Array.prototype.slice.call(system.args, 1);
} else {
    console.log('Error');
    phantom.exit();
}

RenderUrlsToFile(arrayOfUrls, (function(status, url, file) {
    if (status !== 'success') {
        return console.log('Unable to render "' + url + '"');
    } else {
        return console.log('Rendered "' + url + '" at "' + file + '"');
    }
}), function() {
    return phantom.exit();
});
