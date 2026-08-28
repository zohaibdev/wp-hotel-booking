(function ($) {
    'use strict';

    $(function () {
        var $tabs = $('.hb-settings-tabs .nav-tab');
        var $panels = $('.hb-settings-panel');
        var $activeInput = $('#hb_active_tab');

        if (!$tabs.length) {
            return;
        }

        function activate(slug) {
            var $target = $('#hb-tab-' + slug);
            if (!$target.length) {
                return;
            }

            $tabs.removeClass('nav-tab-active');
            $tabs.filter('[data-tab="' + slug + '"]').addClass('nav-tab-active');

            $panels.hide();
            $target.show();

            $activeInput.val(slug);

            if (window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', slug);
                window.history.replaceState({}, '', url.toString());
            }
        }

        $tabs.on('click', function (e) {
            e.preventDefault();
            activate($(this).data('tab'));
        });

        // Restore tab from URL on load (e.g. after a save redirect)
        var params = new URLSearchParams(window.location.search);
        var initial = params.get('tab');
        if (initial) {
            activate(initial);
        }
    });
})(jQuery);
