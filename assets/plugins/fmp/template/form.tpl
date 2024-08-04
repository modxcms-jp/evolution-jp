<label id="FMP-email_label">[+account_email+]:
    <input id="FMP-email" type="text"/>
</label>
<button
        id="FMP-email_button"
        type="button"
        onclick="window.location='index.php?action=send_email&email='+document.getElementById('FMP-email').value;"
>[+send+]
</button>
