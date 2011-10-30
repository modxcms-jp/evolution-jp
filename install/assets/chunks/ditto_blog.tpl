/**
 * ditto_blog
 * 
 * ブログ記事のテンプレート(Ditto)
 * 
 * @category	chunk
 * @version 	1.0
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal 	@modx_category Demo Content
 * @internal    @overwrite false
 * @internal    @installset base, sample
 */
<div class="ditto_summaryPost">
  <h3><a href="[~[+id+]~]" title="[+title+]">[+title+]</a></h3>
  <div class="ditto_info" ><strong>投稿者：[+author+]</strong> on [+date+]. <a  href="[~[+id+]~]#commentsAnchor">コメント数：
  ([!Jot?&docid=`[+id+]`&action=`count-comments`!])</a></div><div class="ditto_tags">キーワード：[+tagLinks+]</div>
  [+summary+]
  <p class="ditto_link">[+link+]</p>
</div>
