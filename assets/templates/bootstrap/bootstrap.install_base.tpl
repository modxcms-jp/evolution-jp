/**
 * Bootstrap
 *
 * 学習用途向きのシンプルなテンプレート
 *
 * @category	template
 * @version 	0.9
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@lock_template 0
 * @internal 	@modx_category Demo Content
 * @internal    @installset sample
 */
<!DOCTYPE html>
<html lang="ja">
<head>
<base href="[(site_url)]">
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>[*pagetitle*] - [(site_name)]</title>
<link href="https://fonts.googleapis.com/css?family=Open+Sans" rel="stylesheet" type="text/css">
<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet">
<style>
html {font-size:16px;}
body {
    padding-top: 50px;
    margin-top: 0px;
    font-family: Roboto,"Droid Sans","Open Sans",Arial,Helvetica,Meiryo,"Hiragino Kaku Gothic ProN",sans-serif;
    font-size:16px;
}
html {
  position: relative;
  min-height: 100%;
}
body {
  margin-bottom: 60px;
}
nav {
  opacity:0.9;
  box-shadow:0 5px 5px -8px rgba(0, 0, 0, 0.1);
}
ul.breadcrumb {}
.footer {
  position: absolute;
  bottom: 0;
  width: 100%;
  /* Set the fixed height of the footer here */
  height: 65px;
  background-color: #d5d5d5;
}
.footer > .container {
  padding-right: 15px;
  padding-left: 15px;
}
</style>
</head>
<body>
<nav class="navbar navbar-default navbar-fixed-top">
    <div class="container">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#gnavi">
          <span class="sr-only">メニュー</span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand" href="[(site_url)]">[(site_name)]</a>
      </div>
      <div id="gnavi" class="collapse navbar-collapse">
          [[Wayfinder?
            &startId  = 0
            &level    = 1
            &outerClass = 'nav navbar-nav navbar-right'
          ]]
      </div>
    </div>
</nav>
<!--@IF:[*id:is('[(site_start)]')*]>
<div class="jumbotron">
  <div class="container">
    <h1>[(site_name)]</h1>
    <p>[(site_slogan)]</p>
    <a class="btn btn-primary btn-lg" href="[~8~]" role="button">Learn more &raquo;</a>
  </div>
</div>
<@ELSE>
<div class="container">
  <div class="page-header">
    [[TopicPath?theme=list&tplOuter='<ul class="breadcrumb">[+topics+]</ul>']]
    <h1>[*longtitle:ifempty('[*pagetitle*]')*]</h1>
  </div>
</div>
<@ENDIF-->

<div class="container" style="padding-bottom:3em;">
    <div class="row">
    <!--@IF:[[Wayfinder?level=1&startId=parent]]>
        <div class="col-sm-9">
        [*description:tpl('<p class="lead">[+value+]</p>')*]
        [*content*]
        </div>
        <div class="col-sm-3">[[Wayfinder?level=1&startId=parent&outerClass='nav nav-pills nav-stacked']]</div>
    <@ELSE>
        <div class="col-lg-12">
        [*description:tpl('<p class="lead">[+value+]</p>')*]
        [*content*]
        </div>
    <@ENDIF-->
    </div>
</div>
<div class="footer">
  <div class="container">
    <p class="text-center" style="margin: 20px 0;">(c)[[$_SERVER['REQUEST_TIME']:dateformat('Y')]] [(site_name)]</p>
  </div>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
</body>
</html>