(function () {
    'use strict';

    if (window.EvoShell) {
        return;
    }

    function navigate(url) {
        window.location.href = url || window.location.href;
    }

    window.EvoShell = {
        navigate: navigate,
        submit: function (form) {
            if (form && typeof form.submit === 'function') {
                form.submit();
            }
        },
        reloadTree: function () {
            navigate('index.php?a=1&f=tree');
        },
        reloadMenu: function () {
            navigate('index.php?a=1&f=menu');
        }
    };
}());
