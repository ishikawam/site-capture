/**
 * @todo;
 * 各種を並列で取ってきて欲しいのにできてない
 */
(function(){
    var lock = [];
    var cap = function(id, w, h, ua, engine){
        if (lock[id]) {
            return;
        }
        start_time = new Date/1000;
        lock[id] = true;
        $(id).append('<div class="loading"><img src="img/gif-load-blue.gif"></div>');
        var url = $('#url').val();
        console.log([url, id, w, h, ua]);
        $.ajax({
            type: 'POST',
            url: '/capture.php',
            data: {
                url: url,
                w: w,
                h: h,
                ua: ua,
                e: engine // phantom, casper_phantom or casper_slimer
            },
            timeout: 60*1000,
            success: function(res){
                console.log(this.data);
                console.log(res);
                if (res.status === 'error') {
                    console.log(['error', res.result, res.command]);
                    $(id).html(''); // @todo; errorぽい画面出したい。テレビのノイズぽいの
                    return;
                }
                var img = $('<a target="_blank">').attr('href', res.cacheUrl); // @todo; lightbox
                img.append($('<img class="window">').attr('src', res.cacheUrl));
                $(id).html(img);
            },
            error: function(XMLHttpRequest, textStatus, errorThrown){
                console.log(['error', XMLHttpRequest, textStatus, errorThrown]);
                $(id).html(''); // @todo; errorぽい画面出したい。テレビのノイズぽいの
            },
            complete: function(data){
                console.log('finish ' + ((new Date/1000) - start_time));
                lock[id] = false;
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

    var engine = ['phantom', 'casper_phantom', 'casper_slimer'];

    var str = '';
    for (var target in config) {
        str += '<p>' + target + ': ' + config[target].width + 'x' + config[target].height + ' ' + config[target].ua + '</p>';
    }
    $('#explain').html(str);

    var load = function(){
        var url = $('#url').val();
        location.hash = '#' + url;
        for (var key in engine) {
            for (var target in config) {
                cap('#image_' + engine[key] + '_' + target, config[target].width, config[target].height, config[target].ua, engine[key]);
            }
        }
    }

    $('#captureBtn').click(function(){
        load();
    });
    $('#url').keypress(function(e){
        if ((e.which && e.which == 13) || (e.keyCode && e.keyCode == 13)) {
            load();
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
})();
