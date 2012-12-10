<!-- welcome -->
<div style="margin: 20px 12px;">
	<div class="tab-pane" id="welcomePane" style="border:0">
    <script type="text/javascript">
        tpPane = new WebFXTabPane(document.getElementById( "welcomePane" ),false);
    </script>

		<!-- home tab -->
		<div class="tab-page" id="tabhome" style="padding-left:0; padding-right:0;">
[+OnManagerWelcomePrerender+]
			<h2 class="tab">メイン</h2>
			<script type="text/javascript">tpPane.addTabPage( document.getElementById( "tabhome" ) );</script>
			<div class="sectionHeader">[+welcome_title+]</div>
			<div class="sectionBody">
                <h3 style="margin:0;font-weight:normal;margin:7px;">[+site_name+]</h3>
                <table border="0" cellpadding="5">
                  <tr>
                    <td width="100" align="right">
                        <img src='media/style/[+theme+]/images/misc/logo.png' alt='[+logo_slogan+]' />
                        <br /><br />
                    </td>
                    <td valign="top">
                        [+OnManagerWelcomeHome+]
                        [+NewDocIcon+]
                        [+ResourcesIcon+]
                        [+SettingsIcon+]
                        [+BackupIcon+]
                        [+HelpIcon+]
                        <br style="clear:both" /><!--+Modules+--><br style="clear:both" />
                        [+MessageInfo+]
                    </td>
                  </tr>
                </table>
			</div>
		</div>
		<!-- system check -->
		<div class="tab-page" id="tabcheck" style="display:[+config_display+]; padding-left:0; padding-right:0;">
			<h2 class="tab" style="display:[+config_display+]"><strong class="alert">[+settings_config+]</strong></h2>
			<script type="text/javascript"> if('[+config_display+]'=='block') tpPane.addTabPage( document.getElementById( "tabcheck" ) );</script>
			<div class="sectionHeader">[+configcheck_title+]</div>
			<div class="sectionBody">
				<img src="media/style/[+theme+]/images/icons/error.png" />
				[+config_check_results+]
			</div>
		</div>
[+OnManagerWelcomeRender+]
	</div>
</div>