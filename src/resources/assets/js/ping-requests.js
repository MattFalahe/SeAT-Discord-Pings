(function ($) {
    'use strict';

    var config = window.PING_REQUESTS;
    var cards = {};
    var FREE_TEXT = 'text';

    config.cards.forEach(function (card) {
        cards[card.id] = card;
    });

    function presetOf(card, presetId) {
        return card.presets.filter(function (preset) {
            return String(preset.id) === String(presetId);
        })[0] || null;
    }

    function questionsOf(card) {
        return card.lines.filter(function (line) {
            return line.kind === 'question';
        });
    }

    function buildFields($target, card, answers) {
        $target.empty();

        if (card.freeText) {
            $target.append(field('Your message', FREE_TEXT, answers[FREE_TEXT], true));
            return;
        }

        questionsOf(card).forEach(function (line) {
            $target.append(field(line.text, line.key, answers[line.key], false));
        });
    }

    function field(label, key, value, multiline) {
        var $input = multiline ? $('<textarea rows="4">') : $('<input type="text">');

        return $('<div class="form-group">')
            .append($('<label>').text(label))
            .append($input.addClass('form-control').attr('data-key', key).attr('maxlength', 1000).val(value || ''));
    }

    function readFields($target) {
        var answers = {};

        $target.find('[data-key]').each(function () {
            answers[$(this).attr('data-key')] = $(this).val();
        });

        return answers;
    }

    // The same rule the server applies, so what the box shows is what goes out.
    function compose(card, answers) {
        var out = [];

        card.lines.forEach(function (line) {
            if (line.kind === 'text') {
                out.push(line.text);
                return;
            }

            var answer = (answers[line.key] || '').replace(/\s*[\r\n]+\s*/g, ' ').trim();
            if (answer !== '') {
                out.push(line.text + ' ' + answer);
            }
        });

        if (card.freeText && (answers[FREE_TEXT] || '').trim() !== '') {
            out.push(answers[FREE_TEXT].trim());
        }

        return out.join('\n').trim();
    }

    function setStatus($status, ok, message) {
        $status.removeClass('pr-ok pr-bad').addClass(ok ? 'pr-ok' : 'pr-bad').text(message);
    }

    function cardStatus(buttonId) {
        return $('[data-card="' + buttonId + '"] .pr-status');
    }

    function post(url, data) {
        return $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            data: $.extend({ _token: config.csrf }, data)
        });
    }

    function press(buttonId, data) {
        return post(config.pressUrl.replace('__ID__', buttonId), data);
    }

    function failureOf(xhr) {
        if (xhr.responseJSON && xhr.responseJSON.message) {
            return xhr.responseJSON.message;
        }

        return 'Not delivered. The page could not reach SeAT, so press again or tell someone directly.';
    }

    function setBusy($buttons, busy) {
        $buttons.prop('disabled', busy);
    }

    /* A cooldown only ever applies to request buttons. */
    function buttonsOf(buttonId) {
        return $('[data-card="' + buttonId + '"] .js-pr-press');
    }

    function describe(seconds) {
        if (seconds < 60) {
            return seconds + (seconds === 1 ? ' second' : ' seconds');
        }

        var minutes = Math.ceil(seconds / 60);

        return minutes + (minutes === 1 ? ' minute' : ' minutes');
    }

    function startCooldown(card, seconds, before) {
        var $buttons = buttonsOf(card.id);
        var $status = cardStatus(card.id);
        var left = seconds;

        function waiting() {
            return (before ? before + ' ' : '') + 'You can press this again in ' + describe(left) + '.';
        }

        if (left <= 0) {
            return;
        }

        clearInterval(card.timer);
        setBusy($buttons, true);
        $status.removeClass('pr-ok pr-bad').toggleClass('pr-ok', !!before).text(waiting());

        card.timer = setInterval(function () {
            left -= 1;

            if (left <= 0) {
                clearInterval(card.timer);
                setBusy($buttons, false);
                $status.removeClass('pr-ok pr-bad').text('');
                return;
            }

            $status.text(waiting());
        }, 1000);
    }

    function pressEmergency(card, $button, presetId) {
        var $status = cardStatus(card.id);

        // Busy only while this one post is in flight, so a double click can't
        // send it twice. The next press after that goes straight out.
        setBusy($button, true);
        $status.removeClass('pr-ok pr-bad').text('Sending...');

        press(card.id, presetId ? { preset_id: presetId } : {})
            .done(function (result) {
                setStatus($status, result.ok, result.message);
            })
            .fail(function (xhr) {
                setStatus($status, false, failureOf(xhr));
            })
            .always(function () {
                setBusy($button, false);
            });
    }

    var sending = null;

    function openSendBox(card, presetId) {
        var preset = presetId ? presetOf(card, presetId) : null;

        sending = { card: card, preset: preset };

        $('#prSendTitle').text(card.name);
        $('#prSendStatus').removeClass('pr-ok pr-bad').text('');
        $('#prSendConfirm').prop('disabled', false);
        buildFields($('#prSendFields'), card, preset ? preset.answers : {});
        refreshPreview();

        $('#prSendModal').appendTo('body').modal('show');
    }

    function refreshPreview() {
        $('#prSendPreview').text(compose(sending.card, readFields($('#prSendFields'))) || sending.card.name);
    }

    $(document).on('input', '#prSendFields [data-key]', refreshPreview);

    $(document).on('click', '#prSendConfirm', function () {
        var $confirm = $(this);
        var card = sending.card;
        var data = { answers: readFields($('#prSendFields')) };

        if (sending.preset) {
            data.preset_id = sending.preset.id;
        }

        setBusy($confirm, true);

        press(card.id, data)
            .done(function (result) {
                if (result.ok) {
                    $('#prSendModal').modal('hide');

                    if (result.retry_in) {
                        startCooldown(card, result.retry_in, result.message);
                    } else {
                        setStatus(cardStatus(card.id), true, result.message);
                    }

                    return;
                }

                // Left open with everything still in it, ready to try again.
                setStatus($('#prSendStatus'), false, result.message);
                setBusy($confirm, false);

                if (result.status === 'blocked' && result.retry_in) {
                    startCooldown(card, result.retry_in);
                }
            })
            .fail(function (xhr) {
                setStatus($('#prSendStatus'), false, failureOf(xhr));
                setBusy($confirm, false);
            });
    });

    $(document).on('click', '.js-pr-press', function () {
        var $button = $(this);
        var card = cards[$button.attr('data-button-id')];
        var presetId = $button.attr('data-preset-id');

        if (card.emergency) {
            pressEmergency(card, $button, presetId);
            return;
        }

        openSendBox(card, presetId);
    });

    var editing = null;

    function openPresetBox(card, preset) {
        editing = { card: card, preset: preset };

        $('#prPresetTitle').text((preset ? 'Edit saved form: ' : 'Save a form: ') + card.name);
        $('#prPresetName').val(preset ? preset.name : '');
        $('#prPresetStatus').removeClass('pr-ok pr-bad').text('');
        buildFields($('#prPresetFields'), card, preset ? preset.answers : {});

        $('#prPresetModal').appendTo('body').modal('show');
    }

    $(document).on('click', '.js-pr-add-preset', function () {
        openPresetBox(cards[$(this).attr('data-button-id')], null);
    });

    $(document).on('click', '.js-pr-edit-preset', function () {
        var card = cards[$(this).attr('data-button-id')];

        openPresetBox(card, presetOf(card, $(this).attr('data-preset-id')));
    });

    $(document).on('click', '#prPresetSave', function () {
        var $save = $(this);
        var data = { name: $('#prPresetName').val(), answers: readFields($('#prPresetFields')) };
        var url = editing.preset
            ? config.presetUrl.replace('__ID__', editing.preset.id)
            : config.storePresetUrl.replace('__ID__', editing.card.id);

        if (editing.preset) {
            data._method = 'PUT';
        }

        setBusy($save, true);

        post(url, data)
            .done(function () {
                location.reload();
            })
            .fail(function (xhr) {
                setStatus($('#prPresetStatus'), false, xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Could not save that form.');
                setBusy($save, false);
            });
    });

    $(document).on('click', '.js-pr-delete-preset', function () {
        if (!confirm('Delete this saved form?')) {
            return;
        }

        post(config.presetUrl.replace('__ID__', $(this).attr('data-preset-id')), { _method: 'DELETE' })
            .done(function () {
                location.reload();
            })
            .fail(function () {
                alert('Could not delete that form.');
            });
    });

    $(function () {
        config.cards.forEach(function (card) {
            if (!card.emergency && card.readyIn > 0) {
                startCooldown(cards[card.id], card.readyIn);
            }
        });
    });
})(jQuery);
