/**
 * ditto_news
 * 
 * 新着記事一覧のテンプレート(Ditto)
 * 
 * @category	chunk
 * @version 	1.0
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal 	@modx_category Demo Content
 * @internal    @overwrite false
 * @internal    @installset base, sample
 */
<tr>
<td class="date">[+date+]</td>
<td><span class="keyword">[+キーワード+]</span>
<a href="[~[+id+]~]">[+title+]</a></td>
</tr>
