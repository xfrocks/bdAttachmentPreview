//noinspection ThisExpressionReferencesGlobalObjectJS,JSUnusedLocalSymbols
/** @param {jQuery} $ jQuery Object */
!function ($, window, document, _undefined) {

    XenForo.bdAttachmentPreview_Previewer = function ($container) {
        this.__construct($container);
    };
    XenForo.bdAttachmentPreview_Previewer.prototype = {
        __construct: function ($container) {
            this.$container = $container;
            this.$header = $container.find('.header');
            this.$pageNav = $container.find('.PageNav');
            this.$data = $container.find('.data');
            this.dataSrc = this.$data.attr('src');

            this.binary = null;
            this.pages = [];

            jBinary.load(this.dataSrc, $.context(this, 'onBinaryLoad'));
        },

        onBinaryLoad: function (err, binary) {
            while (true) {
                this.readPng(binary, binary.tell());

                if (binary.tell() >= binary.view.byteLength) {
                    break;
                }
            }

            this.binary = binary;
            if (this.pages.length > 1) {
                for (var i = 0, l = this.pages.length; i < l; i++) {
                    var $a = $('<a />')
                        .attr('href', '#')
                        .text(i + 1)
                        .data('page', i)
                        .on('click', $.context(this, 'onPageClick'));

                    $a.addClass('page-' + i);
                    if (i == 0) {
                        $a.addClass('currentPage');
                    }

                    $a.xfInsert('appendTo', this.$pageNav, 'show');
                }

                this.$pageNav
                    .css('width', this.$data.width() + 'px')
                    .show();

                if (!this.$container.is('.embedded')) {
                    // only sticky in full preview
                    this.$header.sticky();
                }
            }
        },

        readPng: function (binary, offsetStart) {
            binary.seek(offsetStart);

            //console.log('readPng offset', binary.tell(), 'length', binary.view.byteLength);
            binary.read(['array', 'uint8', 8]);
            //console.log('offset (after magic)', binary.tell());

            while (true) {
                var chunk = binary.read({
                    length: 'uint32',
                    type: ['string', 4],
                    data: ['skip', 'length'],
                    crc: ['skip', 4]
                });
                //console.log('length', chunk.length, 'type', chunk.type, 'offset', binary.tell());
                if (chunk.type === 'IEND') {
                    break;
                }
            }

            var offsetEnd = binary.tell();

            this.pages.push({
                type: 'png',
                pageNo: this.pages.length,
                offsetStart: offsetStart,
                offsetEnd: offsetEnd
            });
        },

        onPageClick: function (e) {
            var page = $(e.target).data('page');
            if (typeof this.pages[page] != 'object') {
                return;
            }
            e.preventDefault();

            var pageInfo = this.pages[page];
            switch (pageInfo.type) {
                case 'png':
                    this.renderPng(pageInfo);
                    break;
            }

            this.$pageNav.find('.currentPage').removeClass('currentPage');
            this.$pageNav.find('.page-' + pageInfo.pageNo).addClass('currentPage');
        },

        renderPng: function (pageInfo) {
            var slice = this.binary.slice(pageInfo.offsetStart, pageInfo.offsetEnd, true);
            this.$data.attr('src', slice.toURI('image/png'));
            this.$data.focus();
        }
    };

    // *********************************************************************

    XenForo.register('.attachment-previewer', 'XenForo.bdAttachmentPreview_Previewer');

}(jQuery, this, document);