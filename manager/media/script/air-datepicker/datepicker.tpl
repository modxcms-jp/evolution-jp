<link href="media/script/air-datepicker/css/datepicker.min.css" rel="stylesheet" type="text/css">
<script type="text/javascript" src="media/script/air-datepicker/datepicker.min.js"></script>
<script src="media/script/air-datepicker/i18n/datepicker.ja.js"></script>
<script type="text/javascript">

var start = new Date();
start.setHours(0);
start.setMinutes(0);

var options = {
    language      : 'ja',
    timepicker    : true,
    todayButton   : new Date(),
    keyboardNav   : false,
    startDate     : start,
    autoClose     : true,
    toggleSelected: false,
    clearButton   : true,
    minutesStep   : 5,
    dateFormat    : '[(datetime_format:strtolower)]',
    onSelect      : function (fd, d, picker) {
        documentDirty = true;
    },
    navTitles: {
       days: 'yyyy/mm'
    }
};

var pub_date   = jQuery('#pub_date');
var unpub_date = jQuery('#unpub_date');

pub_date.datepicker(options);
if(pub_date.val())
    pub_date.data('datepicker').selectDate(new Date(pub_date.val()));

unpub_date.datepicker(options);
if(unpub_date.val())
    unpub_date.data('datepicker').selectDate(new Date(unpub_date.val()));

</script>
