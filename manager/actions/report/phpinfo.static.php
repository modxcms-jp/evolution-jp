<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('logs')) {
    exit;
}
?>
    <style type="text/css">
        .phpinfoReport {
            font-family: inherit;
            padding: 20px;
            overflow-x: auto;
        }

        .phpinfoReport pre {
            margin: 0px;
            font-family: monospace;
        }

        .phpinfoReport a:link {
            color: #000099;
            text-decoration: none;
            background-color: #f7f7f7;
        }

        .phpinfoReport a:hover {
            text-decoration: underline;
        }

        .phpinfoReport table {
            margin-top: 20px;
            border-collapse: collapse;
            background-color: #ffffff;
        }

        .phpinfoReport .center {
            text-align: center;
        }

        .phpinfoReport .center table {
            margin-left: auto;
            margin-right: auto;
            text-align: left;
        }

        .phpinfoReport .center th {
            text-align: center !important;
        }

        .phpinfoReport td,
        .phpinfoReport th {
            border: 1px solid #999999;
            vertical-align: baseline;
            padding: 4px;
        }

        .phpinfoReport h1 {
            text-align: left;
            margin: 10px auto;
        }

        .phpinfoReport h2 {
            text-align: left;
            margin: 10px auto;
        }

        .phpinfoReport .p {
            text-align: left;
        }

        .phpinfoReport h1.p {
            background: transparent;
        }

        .phpinfoReport .e {
            width: 150px;
            background-color: #eeeeee;
            color: #333333;
        }

        .phpinfoReport .h {
            background-color: #bcbcd6;
            font-weight: bold;
            color: #333333;
        }

        .phpinfoReport .h h1 {
            width: 90%;
            font-size: 20px;
        }

        .phpinfoReport .v {
            width: 400px;
            color: #333333;
        }

        .phpinfoReport .vr {
            background-color: #cccccc;
            text-align: right;
            color: #333333;
        }

        .phpinfoReport img {
            float: right;
            border: 0px;
        }

        .phpinfoReport hr {
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
                        src="<?= style("icons_cancel") ?>"/> <?= lang('cancel') ?></a>
            </li>
        </ul>
    </div>

<?php
ob_start();
phpinfo();
echo '<div class="phpinfoReport">';
echo str_replace(
    [
        '<div class="center">',
    'width="600"',
    'src,input'
    ],
    [
        '<div>',
    'width="90%"',
    'src, input'
    ],
    preg_replace(
        '@.*<body>(.+)</body>.*@s',
        '$1',
        ob_get_clean()
    )
);
echo '</div>';
