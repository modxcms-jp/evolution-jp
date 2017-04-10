<link href="media/script/air-datepicker/css/datepicker.min.css" rel="stylesheet" type="text/css">
<script type="text/javascript" src="media/script/air-datepicker/datepicker.min.js"></script>
<script src="media/script/air-datepicker/i18n/datepicker.ja.js"></script>
<script type="text/javascript">

jQuery(function(){
    var ua = navigator.userAgent.toLowerCase();
    var isIE11 = (ua.indexOf('trident/7') > -1);
    var start = new Date();
    start.setHours(0);
    start.setMinutes(0);
    
    var options = {
        language      : '[(lang_code)]',
        todayButton   : isIE11 ? false : new Date(),
        keyboardNav   : false,
        startDate     : start,
        autoClose     : true,
        toggleSelected: false,
        clearButton   : isIE11 ? false : true,
        minutesStep   : 5,
        dateFormat    : '[(datetime_format:strtolower)]',
        onSelect      : function (fd, d, picker) {
            documentDirty = true;
        },
        navTitles: {
           days: 'yyyy/mm'
        }
    };
    
    jQuery('input.DatePicker').datepicker(options);
    jQuery('input.DatePicker').each(function(i, elm){
        var v=jQuery(elm).val();
        if(v) {
            jQuery(elm).data('datepicker').selectDate(new Date(v));
        }
        documentDirty = false;
    });
    jQuery('input.ddAddButton').on('click',function(){
        jQuery('input.DatePicker').datepicker(options);
        jQuery('input.DatePicker').each(function(i, elm){
            var v=jQuery(elm).val();
            if(v) {
                jQuery(elm).data('datepicker').selectDate(new Date(v));
            }
            documentDirty = false;
        });
    });
});

</script>
