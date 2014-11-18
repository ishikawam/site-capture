/**
 * capture.js
 *
 */
(function(){

    var config;
    var engines;
    $.ajaxSetup({ async: false }); // 同期
    $.getJSON('config.json', function(data) {
        config = data.device;
        engines = data.engines;
    });
    $.ajaxSetup({ async: true });

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

        start_time = new Date/1000;
        if (!cont) {
            $(id).append('<div class="loading"><img src="img/gif-load-blue.gif"></div>');
            url = $('#url').val();
        }
//        console.log([url, id, w, h, ua]);
        $.ajax({
            type: 'POST',
            url: engine['server'] + '/capture.php',
            data: {
                url: url,
                w: w,
                h: h,
                ua: ua,
                e: engine['type'] // phantom or slimer
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
                    console.log(['waiting...' + count[id], url, w, h, ua, engine, id]);
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

        for (var key in engines) {
            for (var target in config) {
                if ($('#url').val()) {
                    cap('#image_' + engines[key]['name'] + '_' + target, config[target].width, config[target].height, config[target].ua, engines[key]);
                } else {
                    $('#image_' + engines[key]['name'] + '_' + target).html('');
                }
            }
        }
    };

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
