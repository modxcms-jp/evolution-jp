/**
 * Bootstrap
 *
 * 学習用途向きのシンプルなテンプレート
 *
 * @category    template
 * @version    1.0
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal    @lock_template 0
 * @internal    @modx_category Demo Content
 * @internal    @installset sample
 */
<!DOCTYPE html>
<html lang="ja">
<head>
    <base href="[(site_url)]">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>[*pagetitle*] - [(site_name)]</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html {
            font-size: 16px;
            position: relative;
            min-height: 100%;
        }

        body {
            padding-top: 56px;
            margin-bottom: 60px;
            font-family: Roboto, "Droid Sans", "Open Sans", Arial, Helvetica, Meiryo, "Hiragino Kaku Gothic ProN", sans-serif;
            font-size: 16px;
        }

        nav {
            opacity: 0.9;
            box-shadow: 0 5px 5px -8px rgba(0, 0, 0, 0.1);
        }

        .navbar-nav {
            gap: 20px;
        }

        .breadcrumb {
            background-color: #f8f9fa;
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .breadcrumb li {
            display: inline;
        }

        .breadcrumb li a {
            color: #007bff;
            text-decoration: none;
        }

        .breadcrumb li a:hover {
            text-decoration: underline;
        }

        .breadcrumb li + li::before {
            content: "\203A"; /* › */
            color: #6c757d;
            padding: 0 8px;
        }

        .jumbotron {
            padding: 2rem 1rem;
            margin-bottom: 2rem;
            background-color: #e9ecef;
            border-radius: .3rem;
        }

        .jumbotron h1 {
            font-size: 2.5rem;
            font-weight: bold;
        }

        .jumbotron p {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
        }


        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
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
<nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
    <div class="container">
        <a class="navbar-brand" href="[(site_url)]">[(site_name)]</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#gnavi" aria-controls="gnavi" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div id="gnavi" class="collapse navbar-collapse justify-content-end">
            [[Wayfinder?
            &startId=0
            &level=1
            &outerClass='navbar-nav'
            ]]
        </div>
    </div>
</nav>
<@IF:[*id:is('[(site_start)]')*]>
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
        [[TopicPath?theme=bootstrap]]
        <h1>[*longtitle:ifempty('[*pagetitle*]')*]</h1>
    </div>
</div>
<@ENDIF>
<div class="container" style="padding-bottom:3em;">
    <div class="row">
        <@IF:[[Wayfinder?level=1&startId=parent]]>
        <div class="col-sm-9">
            [*description:tpl('<p class="lead">[+value+]</p>')*]
            [*content*]
        </div>
        <div class="col-sm-3">[[Wayfinder?level=1&startId=parent&outerClass='nav nav-pills flex-column']]</div>
        <@ELSE>
        <div class="col-lg-12">
            [*description:tpl('<p class="lead">[+value+]</p>')*]
            [*content*]
        </div>
        <@ENDIF>
    </div>
</div>
<div class="footer">
    <div class="container">
        <p class="text-center" style="margin: 20px 0;">(c)[[$_SERVER['REQUEST_TIME']:dateformat('Y')]] [(site_name)]</p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
