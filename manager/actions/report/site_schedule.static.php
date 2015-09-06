<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('view_schedule')) {
	$e->setError(3);
	$e->dumpError();
}
?>

<script type="text/javascript" src="media/script/tablesort.js"></script>
<h1><?php echo $_lang["site_schedule"]?></h1>

<div id="actions">
  <ul class="actionButtons">
      <li id="Button5"><a href="#" onclick="documentDirty=false;document.location.href='index.php?a=2';"><img alt="icons_cancel" src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang['cancel']?></a></li>
  </ul>
</div>

<div class="section">
<div class="sectionHeader"><?php echo $_lang["publish_events"]?></div>
<div class="sectionBody" id="lyr1">
<?php
$field = 'id, pagetitle, pub_date';
$where = 'pub_date > ' . $_SERVER['REQUEST_TIME'];
$orderby = 'pub_date ASC';
$rs = $modx->db->select($field,'[+prefix+]site_content',$where,$orderby);
$total = $modx->db->getRecordCount($rs);
if($total<1) {
	echo "<p>".$_lang["no_docs_pending_publishing"]."</p>";
} else {
?>
  <table border="0" cellpadding="2" cellspacing="0"  class="sortabletable sortable-onload-3 rowstyle-even" id="table-1" width="100%">
    <thead>
      <tr bgcolor="#CCCCCC">
        <th class="sortable"><b><?php echo $_lang['resource'];?></b></th>
        <th class="sortable"><b><?php echo $_lang['id'];?></b></th>
        <th class="sortable"><b><?php echo $_lang['publish_date'];?></b></th>
      </tr>
    </thead>
    <tbody>
<?php
	while ($row = $modx->db->getRow($rs)) {
?>
    <tr>
      <td><a href="index.php?a=3&id=<?php echo $row['id'] ;?>"><?php echo $row['pagetitle']?></a></td>
	  <td><?php echo $row['id'] ;?></td>
      <td><?php echo $modx->toDateFormat($row['pub_date']+$server_offset_time)?></td>
    </tr>
<?php
	}
?>
	</tbody>
</table>
<?php
}
?>
</div>
</div>

<div class="section">
<div class="sectionHeader"><?php echo $_lang["unpublish_events"];?></div>
<div class="sectionBody" id="lyr2"><?php
//$db->debug = true;
$field = 'id, pagetitle, unpub_date';
$where = 'unpub_date > ' . $_SERVER['REQUEST_TIME'];
$orderby = 'unpub_date ASC';
$rs = $modx->db->select($field,'[+prefix+]site_content',$where,$orderby);
$total = $modx->db->getRecordCount($rs);
if($total<1) {
	echo "<p>".$_lang["no_docs_pending_unpublishing"]."</p>";
} else {
?>
  <table border="0" cellpadding="2" cellspacing="0"  class="sortabletable sortable-onload-3 rowstyle-even" id="table-2" width="100%">
    <thead>
      <tr bgcolor="#CCCCCC">
        <th class="sortable"><b><?php echo $_lang['resource'];?></b></th>
        <th class="sortable"><b><?php echo $_lang['id'];?></b></th>
        <th class="sortable"><b><?php echo $_lang['unpublish_date'];?></b></th>
      </tr>
    </thead>
    <tbody>
<?php
	while ($row = $modx->db->getRow($rs)) {
?>
    <tr>
      <td><a href="index.php?a=3&id=<?php echo $row['id'] ;?>"><?php echo $row['pagetitle'] ;?></a></td>
	  <td><?php echo $row['id'] ;?></td>
      <td><?php echo $modx->toDateFormat($row['unpub_date']+$server_offset_time) ;?></td>
    </tr>
<?php
	}
?>
	</tbody>
</table>
<?php
}
?>
</div>
</div>

<div class="section">
<div class="sectionHeader">更新を予定している下書きリソースの一覧</div>
<div class="sectionBody" id="lyr2"><?php
//$db->debug = true;
$field = 'rv.*, sc.*, rv.pub_date AS pub_date';
$where = '0<rv.pub_date';
$orderby = 'rv.pub_date ASC';
$rs = $modx->db->select($field,'[+prefix+]site_revision rv INNER JOIN [+prefix+]site_content sc ON rv.elmid=sc.id',$where,$orderby);
$total = $modx->db->getRecordCount($rs);
if($total<1) {
	echo "<p>更新を予定している下書きリソースはありません。</p>";
} else {
?>
  <table border="0" cellpadding="2" cellspacing="0"  class="sortabletable sortable-onload-2 rowstyle-even" id="table-2" width="100%">
    <thead>
      <tr bgcolor="#CCCCCC">
        <th class="sortable"><b><?php echo $_lang['id'];?></b></th>
        <th class="sortable"><b><?php echo $_lang['resource'];?></b></th>
        <th class="sortable">更新予約日時</th>
        <th class="sortable">操作</th>
      </tr>
    </thead>
    <tbody>
<?php
	while ($row = $modx->db->getRow($rs)) {
        $editLink = 'index.php?a=131&id=' . $row['elmid'];
        $prevLink = $modx->makeUrl($row['elmid']).'?revision='.$row['version'];
?>
    <tr>
      <td><?php echo $row['elmid'] ;?></td>
      <td><a href="<?php echo $editLink;?>"><?php echo $row['pagetitle'] ;?></a></td>
      <td><?php echo $modx->toDateFormat($row['pub_date']+$server_offset_time) ;?></td>
      <td><a href="<?php echo $prevLink;?>" target="_blank">プレビュー</a></td>
    </tr>
<?php
	}
?>
	</tbody>
</table>
<?php
}
?>
</div>
</div>
