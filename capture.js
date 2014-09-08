(function(){
    var lock = [];
    var cap = function(id, w, h, ua){
        if (lock[id]) {
            return;
        }
        lock[id] = true;
        $(id).append('<div class="loading"><img src="img/gif-load.gif"></div>');
        url = $('#url').val();
        console.log([url, id, w, h, ua]);
        $.ajax({
            type: 'POST',
            url: '/capture.php',
            data: {
                url: url,
                w: w,
                h: h,
                ua: ua
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
                console.log('finish');
                lock[id] = false;
            }
        });
    };

    var config = {
        // @todo; 定数系はcapture.phpと共通で使いたい
        pc: {
            width: 1920,
            height: 1080,
            ua: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.94 Safari/537.36'
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

    $('#explain').html(
        '<p>pc: ' + config.pc.width + 'x' + config.pc.height + ' ' + config.pc.ua + '</p>' +
        '<p>tablet: ' + config.tablet.width + 'x' + config.tablet.height + ' ' + config.tablet.ua + '</p>' +
        '<p>mobile: ' + config.mobile.width + 'x' + config.mobile.height + ' ' + config.mobile.ua + '</p>'
    );

    var load = function(){
        cap('#imagePc', config.pc.width, config.pc.height, config.pc.ua);
        cap('#imageTablet', config.tablet.width, config.tablet.height, config.tablet.ua);
        cap('#imageMobile', config.mobile.width, config.mobile.height, config.mobile.ua);
    }

    $('#captureBtn').click(function(){
        load();
    });
    $('#url').keypress(function(e){
        if ((e.which && e.which == 13) || (e.keyCode && e.keyCode == 13)) {
            load();
        }
    });
})();
