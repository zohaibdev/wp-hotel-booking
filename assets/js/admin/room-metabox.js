(function ($) {
    'use strict';

    $(function () {
        var $gallery = $('#hb-gallery-images');

        // Sortable gallery
        if ($gallery.length && $.fn.sortable) {
            $gallery.sortable({
                items: '.hb-gallery-item',
                cursor: 'grabbing',
                opacity: 0.7,
                tolerance: 'pointer',
            });
        }

        // Add gallery images
        $('#hb-gallery-add').on('click', function (e) {
            e.preventDefault();

            var frame = wp.media({
                title: 'Select Gallery Images',
                button: { text: 'Add to Gallery' },
                multiple: true,
                library: { type: 'image' },
            });

            frame.on('select', function () {
                var attachments = frame.state().get('selection').toJSON();

                attachments.forEach(function (attachment) {
                    var thumb = attachment.sizes && attachment.sizes.thumbnail
                        ? attachment.sizes.thumbnail.url
                        : attachment.url;

                    var $item = $(
                        '<div class="hb-gallery-item" data-id="' + attachment.id + '">' +
                        '<img src="' + thumb + '" alt="">' +
                        '<button type="button" class="hb-gallery-remove" aria-label="Remove image">&times;</button>' +
                        '<input type="hidden" name="hb_gallery[]" value="' + attachment.id + '">' +
                        '</div>'
                    );

                    $gallery.append($item);
                });
            });

            frame.open();
        });

        // Remove gallery image
        $gallery.on('click', '.hb-gallery-remove', function (e) {
            e.preventDefault();
            $(this).closest('.hb-gallery-item').remove();
        });
    });
})(jQuery);
