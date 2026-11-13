/*
 * A formatting toolbar for a broadcast's message box: Discord's own markdown,
 * and Discord timestamps, which every reader sees in their own time zone and
 * language. Any textarea with data-message-tools gets one.
 */
(function ($) {
    'use strict';

    var MARKS = [
        { title: 'Bold', icon: 'fa-bold', wrap: '**' },
        { title: 'Italic', icon: 'fa-italic', wrap: '*' },
        { title: 'Underline', icon: 'fa-underline', wrap: '__' },
        { title: 'Strikethrough', icon: 'fa-strikethrough', wrap: '~~' },
        { title: 'Spoiler, hidden until clicked', icon: 'fa-eye-slash', wrap: '||' },
        { title: 'Code', icon: 'fa-code', wrap: '`' },
        { title: 'Code block', icon: 'fa-file-code', block: true },
        { title: 'Quote', icon: 'fa-quote-right', prefix: '> ' },
        { title: 'List', icon: 'fa-list-ul', prefix: '- ' },
        { title: 'Link', icon: 'fa-link', link: true }
    ];

    // Discord's styles, with how each one reads on the screen of whoever opens it.
    var STYLES = [
        { code: 't', label: 'Time', format: { hour: '2-digit', minute: '2-digit' } },
        { code: 'T', label: 'Time with seconds', format: { hour: '2-digit', minute: '2-digit', second: '2-digit' } },
        { code: 'd', label: 'Date', format: { day: '2-digit', month: '2-digit', year: 'numeric' } },
        { code: 'D', label: 'Long date', format: { day: 'numeric', month: 'long', year: 'numeric' } },
        { code: 'f', label: 'Date and time', format: { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' } },
        { code: 'F', label: 'Day, date and time', format: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' } },
        { code: 'R', label: 'Relative', relative: true }
    ];

    var QUICK = [['Now', 0], ['+15 min', 15], ['+30 min', 30], ['+1 hour', 60], ['+2 hours', 120], ['+1 day', 1440]];

    function pad(number) {
        return String(number).padStart(2, '0');
    }

    function styleOf(code) {
        return STYLES.filter(function (style) {
            return style.code === code;
        })[0] || STYLES[4];
    }

    function relative(seconds) {
        var diff = seconds - Math.round(Date.now() / 1000);
        var units = [['year', 31536000], ['month', 2592000], ['day', 86400], ['hour', 3600], ['minute', 60], ['second', 1]];

        for (var i = 0; i < units.length; i++) {
            if (Math.abs(diff) >= units[i][1] || units[i][0] === 'second') {
                var count = Math.round(diff / units[i][1]);

                try {
                    return new Intl.RelativeTimeFormat(undefined, { numeric: 'always' }).format(count, units[i][0]);
                } catch (e) {
                    return (count < 0 ? -count + ' ' + units[i][0] + 's ago' : 'in ' + count + ' ' + units[i][0] + 's');
                }
            }
        }

        return '';
    }

    /** What Discord shows for a timestamp, as near as a browser can tell. */
    function stampText(seconds, code) {
        var style = styleOf(code || 'f');

        if (style.relative) {
            return relative(seconds);
        }

        try {
            return new Intl.DateTimeFormat(undefined, style.format).format(new Date(seconds * 1000));
        } catch (e) {
            return new Date(seconds * 1000).toString();
        }
    }

    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /**
     * The message the way Discord will show it, near enough for a preview:
     * timestamps in the reader's time and the common marks. Escaped first,
     * so nothing typed can become page markup.
     */
    function render(text) {
        var blocks = [];
        var html = escapeHtml(text).replace(/```(?:[a-z0-9]*\n)?([\s\S]*?)```/gi, function (whole, code) {
            blocks.push('<pre class="mt-render-block">' + code + '</pre>');
            return '\u0000' + (blocks.length - 1) + '\u0000';
        });

        html = html
            .replace(/&lt;t:(-?\d{1,13})(?::([tTdDfFR]))?&gt;/g, function (whole, seconds, code) {
                return '<span class="mt-render-stamp">' + escapeHtml(stampText(parseInt(seconds, 10), code)) + '</span>';
            })
            .replace(/`([^`\n]+)`/g, '<code>$1</code>')
            .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
            .replace(/__([^_\n]+)__/g, '<u>$1</u>')
            .replace(/\*([^*\n]+)\*/g, '<em>$1</em>')
            .replace(/~~([^~\n]+)~~/g, '<s>$1</s>')
            .replace(/\|\|([^|\n]+)\|\|/g, '<span class="mt-render-spoiler">$1</span>')
            .replace(/^&gt; (.*)$/gm, '<span class="mt-render-quote">$1</span>');

        return html.replace(/\u0000(\d+)\u0000/g, function (whole, index) {
            return blocks[parseInt(index, 10)];
        });
    }

    /**
     * Puts text in place of the range. Through the browser's own editing where
     * it can, so Ctrl+Z still takes it back out.
     */
    function replaceRange(textarea, start, end, text) {
        textarea.focus();
        textarea.setSelectionRange(start, end);

        var done = false;

        try {
            done = document.execCommand('insertText', false, text);
        } catch (e) {
            done = false;
        }

        if (!done || textarea.value.slice(start, start + text.length) !== text) {
            textarea.value = textarea.value.slice(0, start) + text + textarea.value.slice(end);
        }

        $(textarea).trigger('input');
    }

    function wrap(textarea, before, after) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        var selected = value.slice(start, end);

        // Pressed again on text that already has the marks, they come off.
        if (value.slice(start - before.length, start) === before && value.slice(end, end + after.length) === after) {
            replaceRange(textarea, start - before.length, end + after.length, selected);
            textarea.setSelectionRange(start - before.length, end - before.length);
            return;
        }

        replaceRange(textarea, start, end, before + selected + after);
        textarea.setSelectionRange(start + before.length, start + before.length + selected.length);
    }

    /** Every line the selection touches, whole. */
    function prefixLines(textarea, prefix) {
        var value = textarea.value;
        var start = value.lastIndexOf('\n', textarea.selectionStart - 1) + 1;
        var end = textarea.selectionEnd;

        if (end > textarea.selectionStart && value.charAt(end - 1) === '\n') {
            end--;
        }

        var lineEnd = value.indexOf('\n', end);
        end = lineEnd === -1 ? value.length : lineEnd;

        var lines = value.slice(start, end).split('\n');
        var all = lines.every(function (line) {
            return line.indexOf(prefix) === 0;
        });
        var text = lines.map(function (line) {
            return all ? line.slice(prefix.length) : prefix + line;
        }).join('\n');

        replaceRange(textarea, start, end, text);
        textarea.setSelectionRange(start, start + text.length);
    }

    function codeBlock(textarea) {
        var start = textarea.selectionStart;
        var selected = textarea.value.slice(start, textarea.selectionEnd);

        replaceRange(textarea, start, textarea.selectionEnd, '```\n' + selected + '\n```');
        textarea.setSelectionRange(start + 4, start + 4 + selected.length);
    }

    function link(textarea) {
        var start = textarea.selectionStart;
        var selected = textarea.value.slice(start, textarea.selectionEnd);

        if (/^https?:\/\/\S+$/.test(selected)) {
            replaceRange(textarea, start, textarea.selectionEnd, '[text](' + selected + ')');
            textarea.setSelectionRange(start + 1, start + 5);
            return;
        }

        var text = selected || 'text';

        replaceRange(textarea, start, textarea.selectionEnd, '[' + text + '](https://)');
        textarea.setSelectionRange(start + text.length + 3, start + text.length + 11);
    }

    function insertAtCaret(textarea, text) {
        var start = textarea.selectionStart;

        replaceRange(textarea, start, textarea.selectionEnd, text);
        textarea.setSelectionRange(start + text.length, start + text.length);
    }

    /* The time picker. EVE time unless the FC says otherwise, since that's what a fleet runs on. */
    function timePanel(textarea) {
        var mode = 'eve';
        var seconds = Math.floor(Date.now() / 60000) * 60;

        var $panel = $('<div class="mt-time-panel" hidden>');
        var $when = $('<input type="datetime-local" class="form-control form-control-sm mt-time-when" aria-label="Date and time">');
        var $modes = $('<div class="btn-group btn-group-sm mt-time-mode" role="group" aria-label="Time zone of the box">');
        var $list = $('<div class="mt-time-list">');

        $modes.append(
            $('<button type="button" class="btn btn-pings-secondary active" data-mode="eve">').text('EVE / UTC'),
            $('<button type="button" class="btn btn-pings-secondary" data-mode="local">').text('My local time')
        );

        var $quick = $('<div class="mt-time-quick">');
        QUICK.forEach(function (quick) {
            $quick.append($('<button type="button" class="btn btn-sm btn-pings-secondary">').text(quick[0]).attr('data-minutes', quick[1]));
        });

        $panel.append(
            $('<div class="mt-time-head">').append(
                $('<strong>').text('Discord time'),
                $('<span class="mt-time-note">').text('Everyone reading it sees it in their own time.')
            ),
            $('<div class="mt-time-inputs">').append($when, $modes),
            $quick,
            $list
        );

        function boxValue() {
            var date = new Date(seconds * 1000);

            return mode === 'eve'
                ? date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-' + pad(date.getUTCDate()) + 'T' + pad(date.getUTCHours()) + ':' + pad(date.getUTCMinutes())
                : date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
        }

        function fromBox(value) {
            var match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value || '');

            if (!match) {
                return null;
            }

            var parts = match.slice(1).map(Number);
            var date = mode === 'eve'
                ? new Date(Date.UTC(parts[0], parts[1] - 1, parts[2], parts[3], parts[4]))
                : new Date(parts[0], parts[1] - 1, parts[2], parts[3], parts[4]);

            return isNaN(date.getTime()) ? null : Math.floor(date.getTime() / 1000);
        }

        function eveText() {
            var date = new Date(seconds * 1000);

            return date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-' + pad(date.getUTCDate()) + ' ' + pad(date.getUTCHours()) + ':' + pad(date.getUTCMinutes()) + ' EVE';
        }

        function row(label, code, preview) {
            return $('<div class="mt-time-row">').append(
                $('<span class="mt-time-label">').text(label),
                $('<code class="mt-time-code">').text(code),
                $('<span class="mt-time-preview">').text(preview),
                $('<button type="button" class="btn btn-sm btn-pings-primary mt-time-insert">').text('Insert').attr('data-insert', code)
            );
        }

        function refresh() {
            $list.empty();

            STYLES.forEach(function (style) {
                var code = '<t:' + seconds + ':' + style.code + '>';

                $list.append(row(style.label, code, stampText(seconds, style.code)));
            });

            var both = '<t:' + seconds + ':t> (<t:' + seconds + ':R>)';

            $list.append(row('Time and relative', both, stampText(seconds, 't') + ' (' + stampText(seconds, 'R') + ')'));
            $list.append(row('EVE time as text', eveText(), eveText()));
        }

        $when.val(boxValue()).on('input change', function () {
            var picked = fromBox($(this).val());

            if (picked !== null) {
                seconds = picked;
                refresh();
            }
        });

        $modes.on('click', '[data-mode]', function () {
            mode = $(this).attr('data-mode');
            $modes.find('[data-mode]').removeClass('active');
            $(this).addClass('active');
            $when.val(boxValue());
        });

        $quick.on('click', '[data-minutes]', function () {
            seconds = Math.floor(Date.now() / 60000) * 60 + parseInt($(this).attr('data-minutes'), 10) * 60;
            $when.val(boxValue());
            refresh();
        });

        $list.on('click', '.mt-time-insert', function () {
            insertAtCaret(textarea, $(this).attr('data-insert'));
        });

        refresh();

        return $panel;
    }

    function attach(textarea) {
        var $textarea = $(textarea);

        if ($textarea.data('mtAttached')) {
            return;
        }

        $textarea.data('mtAttached', true);

        var $toolbar = $('<div class="mt-toolbar" role="toolbar" aria-label="Formatting">');
        var $panel = timePanel(textarea);

        MARKS.forEach(function (mark) {
            var $button = $('<button type="button" class="btn btn-sm btn-pings-secondary">')
                .attr({ title: mark.title, 'aria-label': mark.title })
                .append($('<i class="fas">').addClass(mark.icon));

            $button.on('click', function () {
                if (mark.wrap) {
                    wrap(textarea, mark.wrap, mark.wrap);
                } else if (mark.prefix) {
                    prefixLines(textarea, mark.prefix);
                } else if (mark.block) {
                    codeBlock(textarea);
                } else if (mark.link) {
                    link(textarea);
                }
            });

            $toolbar.append($button);
        });

        var $timeButton = $('<button type="button" class="btn btn-sm btn-pings-secondary mt-time-toggle" aria-expanded="false">')
            .append($('<i class="fas fa-clock">'), document.createTextNode(' Discord time'));

        $timeButton.on('click', function () {
            var open = $panel.prop('hidden');

            $panel.prop('hidden', !open);
            $timeButton.toggleClass('active', open).attr('aria-expanded', open ? 'true' : 'false');
        });

        $toolbar.append($('<span class="mt-divider" aria-hidden="true">'), $timeButton);
        $textarea.before($toolbar, $panel);
    }

    window.MessageTools = {
        attach: attach,
        render: render,
        stampText: stampText
    };

    $(function () {
        $('textarea[data-message-tools]').each(function () {
            attach(this);
        });
    });
})(jQuery);
