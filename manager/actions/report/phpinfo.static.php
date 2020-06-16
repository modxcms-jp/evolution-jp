<?php
if (!isset($modx) || !$modx->isLoggedin()) {
    exit;
}
if (!$modx->hasPermission('logs')) {
    exit;
}
?>
    <style type="text/css">
        body {
            padding: 20px;
        }

        pre {
            margin: 0px;
            font-family: monospace;
        }

        a:link {
            color: #000099;
            text-decoration: none;
            background-color: #f7f7f7;
        }

        a:hover {
            text-decoration: underline;
        }

        table {
            margin-top: 20px;
            border-collapse: collapse;
            background-color: #ffffff;
        }

        .center {
            text-align: center;
        }

        .center table {
            margin-left: auto;
            margin-right: auto;
            text-align: left;
        }

        .center th {
            text-align: center !important;
        }

        td, th {
            border: 1px solid #999999;
            vertical-align: baseline;
            padding: 4px;
        }

        h1 {
            text-align: left;
            margin: 10px auto;
        }

        h2 {
            text-align: left;
            margin: 10px auto;
        }

        .p {
            text-align: left;
        }

        .e {
            width: 150px;
            background-color: #eeeeee;
            color: #333333;
        }

        .h {
            background-color: #bcbcd6;
            font-weight: bold;
            color: #333333;
        }

        .h h1 {
            width: 90%;
            font-size: 20px;
        }

        .v {
            width: 400px;
            color: #333333;
        }

        .vr {
            background-color: #cccccc;
            text-align: right;
            color: #333333;
        }

        img {
            float: right;
            border: 0px;
        }

        hr {
            background-color: #cccccc;
            border: 0px;
            height: 1px;
            color: #333333;
        }
    </style>

    <div id="actions">
        <ul class="actionButtons">
            <li
                id="Button5"
                class="mutate"
            >
                <a
                    href="#"
                    onclick="documentDirty=false;document.location.href='index.php?a=53';"
                ><img
                    alt="icons_cancel"
                    src="<?php echo style("icons_cancel") ?>"/> <?php echo lang('cancel') ?></a>
            </li>
        </ul>
    </div>

<?php
ob_start();
phpinfo();
ob_end_clean();
echo str_replace(
    array(
        '<div class="center">'
        , 'width="600"'
        , 'src,input'
    )
    , array(
        '<div>'
        , 'width="90%"'
        , 'src, input'
    )
    , preg_replace(
            '%^.*<body>(.*)</body>.*$%ms'
            , '$1'
            , ob_get_contents()
    )
);
