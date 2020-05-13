<?php
// Include the JQuery call
$e->output( '
    <!-- ManagerManager Plugin :: '.$mm_version.' -->
    <!-- This document is using template: '. $mm_current_page['template'] .' -->
    <!-- You are logged into the following role: '. $mm_current_page['role'] .' -->
            
    <script type="text/javascript" charset="'.$modx->config['modx_charset'].'">
    var mm_lastTab = "tabGeneral";
    var mm_sync_field_count = 0;
    var synch_field = new Array();
    
$j(function(){
        // Lets handle errors nicely...
    try {
    ');
        
    // Get the JS for the changes & display the status
    $e->output($this->make_changes($config_chunk));
        
        // Close it off
        $e->output( '
            // Misc tidying up
            
            // General tab table container is too narrow for receiving TVs -- make it a bit wider
            $j("div#tabGeneral table").prop("width", "100%");
            
            // if template variables containers are empty, remove their section
        if ($j("div.tmplvars :input").length == 0){
                $j("div.tmplvars").hide();    // Still contains an empty table and some dividers
                $j("div.tmplvars").prev("div").hide();    // Still contains an empty table and some dividers
            }
            
            // If template category is empty, hide the optgroup
        $j("#field_template optgroup").each( function(){
                var $this = $j(this),
                visibleOptions = 0;
                $this.find("option").each( function() {
                    if ($j(this).css("display") != "none")     visibleOptions++ ;
                });
                if (visibleOptions == 0) $this.hide();
            });
            
    }catch(e){
            // If theres an error, fail nicely
            alert("ManagerManager: An error has occurred: " + e.name + " - " + e.message);
    }finally{
            // Whatever happens, hide the loading mask
            $j("#loadingmask").hide();
        }
    });
    </script>
    <!-- ManagerManager Plugin :: End -->
');
