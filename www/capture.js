/**
 * capture.js
 *
 */
(function(){
    var lock = [];
    var count = [];
    var url;
    var cap = function(id, w, h, ua, engine, cont){
        if (lock[id]) {
            return;
        }
        if (count[id]) {
            count[id]++;
        } else {
            count[id] = 1;
        }

        var server = 'http://capture.osae.me';
        var engine_type = engine;
        if (engine.match(/^mac/)) {
            server = 'http://jitaku.osae.me:28080';
            engine_type = engine.replace(/^mac/, '');
        }

        start_time = new Date/1000;
        if (!cont) {
            $(id).append('<div class="loading"><img src="img/gif-load-blue.gif"></div>');
            url = $('#url').val();
        }
//        console.log([url, id, w, h, ua]);
        $.ajax({
            type: 'POST',
            url: server + '/capture.php',
            data: {
                url: url,
                w: w,
                h: h,
                ua: ua,
                e: engine_type // phantom or slimer
            },
            timeout: 60*1000,
            success: function(res){
//                console.log(this.data);
//                console.log(res);
                if (res.status === 'error') {
//                    console.log(['error', res.result, res.command]);
                    var device = getDevice(w, h);
                    var imageUrl = '/img/error_' + device + '.png';
                    var img = $('<img class="window">').attr('src', imageUrl);
                    $(id).html(img);
                    return;
                } else if (res.status === 'wait') {
//                    console.log(['waiting...', res.result, res.command]);
                    console.log(['waiting...' + count[id], url,w,h,ua,engine,id]);
                    if (count[id] < 60) {
                        setTimeout(function() {
                            cap(id, w, h, ua, engine, true);
                        }, 1000);
                    } else {
                        // timeout error
                        var device = getDevice(w, h);
                        var imageUrl = '/img/error_' + device + '.png';
                        var img = $('<img class="window">').attr('src', imageUrl);
                        $(id).html(img);
                    }
                    return;
                }
                if (res.status == 'ok' || res.status == 'cache') {
                    var img = $('<a target="_blank">').attr('href', res.imageUrl); // @todo; lightbox
                    img.append($('<img class="window">').attr('src', res.imageUrl));

                    if (res.yslowUrl) { // phantomに限る
                        $('#feed_url').html(' <a target="_blank" href="' + res.request.url + '">' + res.request.url + '</a> ');

                        var device = getDevice(w, h);
                        var yslow = $('<a class="toolhint" target="_blank">yslow(' + device + ')<span id="toolhint_' + device + '"></span></a>');

                        yslow.click({res:res, device:device}, function(event) {
                            // unbindしなくていいのかな
                            $.ajax({
                                type: 'GET',
                                url: event.data.res.yslowUrl,
                                timeout: 6*1000,
                                success: function(res){
                                    $('#toolhint_' + event.data.device).text(res);
                                    $('#toolhint_' + event.data.device).addClass('toolhint');
                                    console.log(res);
                                }
                            });
                        });
                        $('#feed_' + device).text(' , ').append(yslow);
                    }

                    if (res.contentUrl) { // phantomに限る
                        $('#content_' + device).html(' , <a href="' + res.contentUrl + '" target="_blank">content(' + device + ')</a>');
                    }

                } else {
                    var img = $('<img class="window">').attr('src', res.imageUrl);
                }
                $(id).html(img);
            },
            error: function(XMLHttpRequest, textStatus, errorThrown){
                console.log(['error', XMLHttpRequest, textStatus, errorThrown]);
                var device = getDevice(w, h);
                var imageUrl = '/img/error_' + device + '.png';
                var img = $('<img class="window">').attr('src', imageUrl);
                $(id).html(img);
            },
            complete: function(data){
//                console.log('finish ' + ((new Date/1000) - start_time));
                lock[id] = false; // ここのロック見直したい @todo;
            }
        });
    };

    var config = {
        // @todo; 定数系はcapture.phpと共通で使いたい
        pc: {
            width: 1920,
            height: 1080,
            ua: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.78.2 (KHTML, like Gecko) Version/7.0.6 Safari/537.78.2' // mac safari 1024x768
//            ua: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.94 Safari/537.36' // mac chrome 1024x768
        },
        tablet: {
            width: 768,
            height: 1024,
            ua: 'Mozilla/5.0 (iPad; CPU OS 4_3_5 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8L1 Safari/6533.18.5'
        },
        mobile: {
            width: 320,
            height: 568,
            ua: 'Mozilla/5.0 (iPhone; CPU iPhone OS 7_0 like Mac OS X; en-us) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11A465 Safari/9537.53'
        },
    };

    var engine = ['phantom', 'macphantom', 'slimer', 'macslimer'];

    var str = '';
    for (var target in config) {
        str += '<p>' + target + ': ' + config[target].width + 'x' + config[target].height + ' ' + config[target].ua + '</p>';
    }
    $('#explain').html(str);

    var load = function() {
        // リセット
        $('#feed_url').text('');
        $('#feed_pc').text('');
        $('#feed_tablet').text('');
        $('#feed_mobile').text('');
        $('#content_pc').text('');
        $('#content_tablet').text('');
        $('#content_mobile').text('');

        for (var key in engine) {
            for (var target in config) {
                if ($('#url').val()) {
                    cap('#image_' + engine[key] + '_' + target, config[target].width, config[target].height, config[target].ua, engine[key]);
                } else {
                    $('#image_' + engine[key] + '_' + target).html('');
                }
            }
        }
    }

    $('#captureBtn').click(function(){
        if (location.hash.replace(/^#/, '') == $('#url').val()) {
            // reload
            $(window).hashchange();
        } else {
            location.hash = '#' + $('#url').val();
        }
    });
    $('#url').keypress(function(e){
        if ((e.which && e.which == 13) || (e.keyCode && e.keyCode == 13)) {
            if (location.hash.replace(/^#/, '') == $('#url').val()) {
                // reload
                $(window).hashchange();
            } else {
                location.hash = '#' + $('#url').val();
            }
        }
    });

    $(window).hashchange(function(){
        var url = location.hash.replace(/^#/, '');
        $('#url').val(url);
        load();
    });

    if (location.hash) {
        $(window).hashchange();
    }

    var getDevice = function(w, h) {
        var device = 'pc';
        if (w / h < 0.6) {
            device = 'mobile';
        } else if (w / h < 1) {
            device = 'tablet';
        }
        return device;
    };
})();
