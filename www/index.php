<?php
include(__DIR__ . '/../inc/common.php');
$common = new Common;
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="">
        <meta name="author" content="">

        <title>Capture test</title>

        <!-- Bootstrap core CSS -->
        <link href="bower_components/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">

        <link href="css/capture.css" rel="stylesheet">
    </head>
    <body>
        <div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
            <div class="container">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".navbar-collapse">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="/">Capture test</a>
                </div>
                <div class="collapse navbar-collapse">
                    <ul class="nav navbar-nav">
                    </ul>
                </div><!--/.nav-collapse -->
            </div>
        </div>

        <div class="container">
            <div class="starter-template">
                <h1>Capture test</h1>
                <div class="col col-xs-12 col-sm-10 col-md-10">
                    <input id="url" type="text" class="form-control" placeholder="url" autofocus>
                </div>
                <div class="col col-xs-12 col-sm-2 col-md-2">
                    <button id="captureBtn" class="btn btn-block">Capture!</button>
                </div>
            </div>
            <p class="feed">
              <span id="feed_url"></span>
              <span id="feed_pc"></span><span id="feed_tablet"></span><span id="feed_mobile"></span>
              <span id="content_pc"></span><span id="content_tablet"></span><span id="content_mobile"></span>
            </p>
<?php
foreach($common->config['engines'] as $val) {
?>
            <div class="starter-template sub">
                <p class="lead">
                    <?=$val['title']?>
                </p>
            </div>
            <div class="row center">
                <div class="imageWrap">
                    <div class="imageWrapPc">
                        <p>pc</p>
                        <div id="image_<?=$val['name']?>_pc" class="imagePc"></div>
                    </div>
                    <div class="imageWrapTablet">
                        <p>tablet</p>
                        <div id="image_<?=$val['name']?>_tablet" class="imageTablet"></div>
                    </div>
                    <div class="imageWrapMobile">
                        <p>mobile</p>
                        <div id="image_<?=$val['name']?>_mobile" class="imageMobile"></div>
                    </div>
                </div>
            </div>
<?php
}
?>
            <div id="explain" class="row">
            </div>
            <div class="row">
                <p class="author"><a href="http://about.me/M_Ishikawa">M_Ishikawa</a></p>
            </div>
        </div><!-- /.container -->

        <!-- Bootstrap core JavaScript
             ================================================== -->
        <!-- Placed at the end of the document so the pages load faster -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
        <script src="bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
        <script src="bower_components/jquery-hashchange/jquery.ba-hashchange.js"></script>

        <script src="capture.js"></script>
    </body>
</html>
