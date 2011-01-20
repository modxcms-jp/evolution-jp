/**
 * xGray
 *
 * 「xGray」学習用途向きのシンプルなテンプレート
 *
 * @category	template
 * @version 	1.0
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@lock_template 0
 * @internal 	@modx_category Demo Content
 * @internal    @installset sample
 */
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <base href="[(site_url)]" />
  <title>[*pagetitle*]|[(site_name)]</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <link rel="stylesheet" type="text/css" href="assets/templates/xgray/style.css" />
  <link rel="stylesheet" type="text/css" href="assets/templates/xgray/content.css" />
</head>
<body>
<div class="wrap">
	<div>
	    <h1><img src="assets/templates/xgray/images/header_image.png" alt="[(site_name)]" /></h1>
	</div>
	<div class="navi">
	    [[Wayfinder?startId=0&hideSubMenus=true&level=1]]
	</div>
	<div class="content">
	    [[Breadcrumbs]]
	    <h2>[*pagetitle*]</h2>
	    [*content*]
	</div>
	<div class="footer">
	    (c)2011 [(site_name)]
	</div>
</div>
</body>
</html>
