(function ($) {
    'use strict';

    var config = window.PING_REQUESTS;
    var cards = {};
    var FREE_TEXT = 'text';
    var LOOKUP_HINTS = {
        location: 'Type 3 letters of a system, then pick a spot or write your own',
        character: 'Type 3 letters of one of your characters',
        ship: 'Type 3 letters of a ship'
    };

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
            return line.key !== null;
        });
    }

    /*
     * Which boxes a form gets: 'all' in the confirm box, and 'saved' on a
     * saved form, where only answers that can be kept belong.
     */
    function buildFields($target, card, answers, which) {
        $target.empty();

        if (card.freeText) {
            $target.append(field({ text: 'Your message', key: FREE_TEXT, box: 'long' }, answers[FREE_TEXT]));
            return;
        }

        questionsOf(card).forEach(function (line) {
            if (which === 'all' || !line.live) {
                $target.append(field(line, answers[line.key]));
            }
        });
    }

    function field(line, value) {
        var $group = $('<div class="form-group">').append($('<label>').text(line.text));

        if (line.box === 'yesno') {
            return $group.append(yesNo(line.key, value));
        }

        var $input = line.box === 'long' ? $('<textarea rows="4">') : $('<input type="text">');

        $input.addClass('form-control').attr('data-key', line.key).attr('maxlength', 1000).val(value || '');

        if (LOOKUP_HINTS[line.box]) {
            $input.attr({ 'data-lookup': line.box, autocomplete: 'off', placeholder: LOOKUP_HINTS[line.box] });
            $group.addClass('pr-suggests');
        }

        return $group.append($input);
    }

    /* Two buttons and not a dropdown, so answering takes one click. Clicking the lit one again clears it. */
    function yesNo(key, value) {
        var $answer = $('<input type="hidden">').attr('data-key', key).val(value === 'Yes' || value === 'No' ? value : '');
        var $buttons = $('<div class="btn-group pr-yesno" role="group">');

        ['Yes', 'No'].forEach(function (choice) {
            $buttons.append($('<button type="button" class="btn btn-sm">').attr('data-answer', choice).text(choice));
        });

        showYesNo($buttons, $answer.val());

        return $('<div>').append($buttons, $answer);
    }

    function showYesNo($buttons, choice) {
        $buttons.find('.btn').each(function () {
            var on = $(this).attr('data-answer') === choice;

            $(this).toggleClass('btn-pings-primary', on).toggleClass('btn-pings-secondary', !on).attr('aria-pressed', on ? 'true' : 'false');
        });
    }

    $(document).on('click', '.pr-yesno .btn', function () {
        var $answer = $(this).closest('.form-group').find('[data-key]');
        var choice = $answer.val() === $(this).attr('data-answer') ? '' : $(this).attr('data-answer');

        $answer.val(choice).trigger('pr-picked');
        showYesNo($(this).parent(), choice);
    });

    function readFields($target) {
        var answers = {};

        $target.find('[data-key]').each(function () {
            answers[$(this).attr('data-key')] = $(this).val();
        });

        return answers;
    }

    // The same rules the server applies, so what the box shows is what goes out.
    function answerOf(line, answer) {
        answer = typeof answer === 'string' ? answer : '';

        if (line.box === 'yesno') {
            return answer === 'Yes' || answer === 'No' ? answer : '';
        }

        if (line.box === 'long') {
            return answer.replace(/\r\n?/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
        }

        return answer.replace(/\s*[\r\n]+\s*/g, ' ').trim();
    }

    function compose(card, answers) {
        var out = [];

        card.lines.forEach(function (line) {
            if (line.key === null) {
                out.push(line.text);
                return;
            }

            var answer = answerOf(line, answers[line.key]);
            if (answer !== '') {
                out.push((/[:?]$/.test(line.text) ? line.text : line.text + ':') + (answer.indexOf('\n') !== -1 ? '\n' : ' ') + answer);
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

                if (result.ok && result.active) {
                    showActive(result.active, true);
                }
            })
            .fail(function (xhr) {
                setStatus($status, false, failureOf(xhr));
            })
            .always(function () {
                setBusy($button, false);
            });
    }

    /*
     * Emergencies that can still be added to, each with the questions it has
     * left. The server keeps them until they close, so a reload brings them
     * back. Every update carries all the answers, so whichever lands last has
     * the lot.
     */
    var actives = [];
    var ticker = null;

    function clock(seconds) {
        var rest = seconds % 60;

        return Math.floor(seconds / 60) + ':' + (rest < 10 ? '0' : '') + rest;
    }

    function showActive(entry, fresh) {
        var $fields = $('<div class="pr-active-fields">');
        var $left = $('<span class="pr-active-left">');
        var $close = $('<button type="button" class="btn btn-sm btn-pings-secondary pr-active-close js-pr-active-close">')
            .append($('<i class="fas fa-times">'), ' Close');
        var $item = $('<div class="pr-active-item">').append(
            $('<div class="pr-active-head">').append(
                $('<span class="pr-active-name">').append(
                    $('<i class="fas fa-exclamation-triangle">'), ' ', $('<strong>').text(entry.button),
                    entry.savedForm ? $('<span class="text-muted pr-active-form">').text(entry.savedForm) : null
                ),
                $close
            ),
            $('<div class="pr-active-when">').append(
                $('<span class="text-muted">').append('Sent ', $('<span class="eve-time" data-show-local>').attr('data-eve-time', entry.sentAt).text(entry.sentAt.substr(11, 5) + ' EVE')),
                $left
            ),
            $('<div class="pr-active-handled" hidden>'),
            $fields,
            $('<button type="button" class="btn btn-sm btn-pings-secondary js-pr-details-send">').append($('<i class="fas fa-sync-alt">'), ' Update message'),
            $('<div class="pr-status pr-details-status" role="status">')
        );
        var details = {
            token: entry.token,
            historyId: entry.historyId ? String(entry.historyId) : null,
            $item: $item,
            $fields: $fields,
            $left: $left,
            $close: $close,
            // From how long the server said was left, so a clock that's off on this computer doesn't matter.
            deadline: Date.now() + entry.closesIn * 1000,
            sent: null,
            busy: false,
            again: false,
            closing: false,
            closed: false
        };

        if (entry.freeText) {
            $fields.append(field({ text: 'Your message', key: FREE_TEXT, box: 'long' }, entry.answers[FREE_TEXT]));
        } else {
            entry.lines.forEach(function (line) {
                $fields.append(field(line, entry.answers[line.key]));
            });
        }

        // What the server already has counts as sent, so nothing goes out until something changes.
        details.sent = JSON.stringify(readFields($fields));

        $item.data('details', details);
        $('#prActiveList').prepend($('<div class="col-xl-4 col-lg-6 col-12 mb-3 pr-active-col">').append($item));
        $('#prActive').removeClass('d-none');
        actives.push(details);

        if (details.historyId) {
            showHandled(details.historyId, entry.answeredBy || []);
        }

        if (window.EveTime) {
            window.EveTime.convert($item[0]);
        }

        countDown();
        ticker = ticker || setInterval(countDown, 1000);
        watchAnswers();

        if (fresh) {
            var top = $('#prActive')[0].getBoundingClientRect().top;

            if (top < 0 || top > window.innerHeight - 120) {
                $('#prActive')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    }

    /*
     * Which FCs have answered a request, so the member knows it's being
     * handled. A request counts as answered once an FC's broadcast for it
     * has gone out.
     */
    var ANSWERS_EVERY = 30000;
    var answersTimer = null;

    function handledBadge(answers) {
        return $('<span class="badge badge-info pr-handled-badge" title="An FC has sent a broadcast for this request">').append(
            $('<i class="fas fa-user-check">'),
            document.createTextNode(' Handled by ' + answers.map(function (answer) {
                return answer.name;
            }).join(', '))
        );
    }

    function showHandled(historyId, answers) {
        var id = String(historyId);

        // The table keeps the rows of its other pages out of the document, so they're found through it.
        var $table = $('#prRecentTable');
        var $rows = $.fn.dataTable && $.fn.dataTable.isDataTable($table[0]) ? $($table.DataTable().rows().nodes()) : $table.find('tbody tr');

        $rows.find('.pr-handled').filter(function () {
            return $(this).attr('data-history-id') === id;
        }).each(function () {
            $(this).empty().append(answers.length > 0 ? handledBadge(answers) : null);
        });

        actives.forEach(function (details) {
            if (details.historyId !== id) {
                return;
            }

            var $handled = details.$item.find('.pr-active-handled').empty().prop('hidden', answers.length === 0);

            answers.forEach(function (answer, index) {
                $handled.append(
                    index === 0 ? $('<i class="fas fa-user-check">') : ', ',
                    index === 0 ? ' Being handled by ' : '',
                    $('<strong>').text(answer.name),
                    ' (', $('<span class="eve-time" data-show-local>').attr('data-eve-time', answer.at).text(answer.at.substr(11, 5) + ' EVE'), ')'
                );
            });

            if (window.EveTime) {
                window.EveTime.convert($handled[0]);
            }
        });
    }

    function checkAnswers() {
        if (document.hidden) {
            return;
        }

        $.getJSON(config.answersUrl).done(function (reply) {
            var answers = reply && reply.answers ? reply.answers : {};

            Object.keys(answers).forEach(function (id) {
                showHandled(id, answers[id]);
            });
        });
    }

    // Every half minute while a request could still be answered, and straight away when the tab comes back.
    function watchAnswers() {
        if (answersTimer || !config.answersUrl) {
            return;
        }

        answersTimer = setInterval(checkAnswers, ANSWERS_EVERY);
        document.addEventListener('visibilitychange', checkAnswers);
    }

    function countDown() {
        var now = Date.now();

        actives.forEach(function (details) {
            if (details.closed) {
                return;
            }

            var left = Math.ceil((details.deadline - now) / 1000);

            if (left <= 0) {
                closeHere(details, 'Closed. Ten minutes have passed, so nothing more can be added to this message. Press the button again if you need to.', false);
                return;
            }

            details.$left.text('Closes in ' + clock(left));
        });
    }

    function lockFields(details, locked) {
        details.$item.find('input, textarea, .pr-yesno .btn, .js-pr-details-send').prop('disabled', locked);
    }

    /* Left on show, faded, so the member can still read what they wrote. */
    function closeHere(details, message, failed) {
        var $status = details.$item.find('.pr-details-status');

        details.closed = true;
        details.$item.addClass('pr-closed');
        lockFields(details, true);
        details.$left.text('Closed');
        details.$close.empty().append($('<i class="fas fa-times">'), ' Dismiss');

        if (failed) {
            setStatus($status, false, message);
        } else {
            $status.removeClass('pr-ok pr-bad').text(message);
        }
    }

    function removeActive(details) {
        actives = actives.filter(function (other) {
            return other !== details;
        });

        details.$item.closest('.pr-active-col').remove();

        if (actives.length === 0) {
            $('#prActive').addClass('d-none');
            clearInterval(ticker);
            ticker = null;
        }
    }

    function sendDetails(details, asked) {
        if (!details || details.closed || details.closing) {
            return;
        }

        var answers = readFields(details.$fields);
        var state = JSON.stringify(answers);
        var $status = details.$item.find('.pr-details-status');

        if (state === details.sent) {
            if (asked && !details.busy) {
                setStatus($status, true, 'Your message on Discord already says this.');
            }
            return;
        }

        // One at a time, and whatever changed in the meantime goes straight after.
        if (details.busy) {
            details.again = true;
            return;
        }

        details.busy = true;
        details.again = false;
        $status.removeClass('pr-ok pr-bad').text('Updating...');

        post(config.detailsUrl.replace('__TOKEN__', details.token), { answers: answers })
            .done(function (result) {
                setStatus($status, result.ok, result.message);

                if (result.ok) {
                    details.sent = state;
                } else if (result.status === 'expired' || result.status === 'not_configured' || result.status === 'rate_limited') {
                    closeHere(details, result.message, true);
                }
            })
            .fail(function (xhr) {
                setStatus($status, false, xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Not updated. The page could not reach SeAT, so try again or tell someone directly.');
            })
            .always(function () {
                details.busy = false;

                if (details.closing) {
                    sendClose(details);
                } else if (details.again) {
                    sendDetails(details);
                }
            });
    }

    function sendClose(details) {
        var $status = details.$item.find('.pr-details-status');

        $status.removeClass('pr-ok pr-bad').text('Closing...');

        post(config.closeUrl.replace('__TOKEN__', details.token))
            .done(function (result) {
                if (result.ok && result.status === 'closed') {
                    removeActive(details);
                } else if (result.ok) {
                    closeHere(details, result.message, true);
                    details.$close.prop('disabled', false);
                } else {
                    stillOpen(details, result.message);
                }
            })
            .fail(function (xhr) {
                stillOpen(details, xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Not closed. The page could not reach SeAT, so try again.');
            });
    }

    function stillOpen(details, message) {
        details.closing = false;
        details.$close.prop('disabled', false);

        if (!details.closed) {
            lockFields(details, false);
        }

        setStatus(details.$item.find('.pr-details-status'), false, message);
    }

    $(document).on('change pr-picked', '.pr-active-item [data-key]', function () {
        sendDetails($(this).closest('.pr-active-item').data('details'));
    });

    $(document).on('click', '.js-pr-details-send', function () {
        sendDetails($(this).closest('.pr-active-item').data('details'), true);
    });

    $(document).on('click', '.js-pr-active-close', function () {
        var details = $(this).closest('.pr-active-item').data('details');

        if (details.closed) {
            removeActive(details);
            return;
        }

        if (details.closing || !confirm('Close this request? Your message stays on Discord, but nothing more can be added to it.')) {
            return;
        }

        // Nothing typed from here on would get there, so it can't be typed.
        details.closing = true;
        details.$close.prop('disabled', true);
        lockFields(details, true);
        details.$item.find('.pr-details-status').removeClass('pr-ok pr-bad').text('Closing...');

        // An update already on its way goes first. Sent together, whichever
        // edit landed last would win on Discord, and that could undo the close.
        if (!details.busy) {
            sendClose(details);
        }
    });

    var sending = null;

    function openSendBox(card, presetId) {
        var preset = presetId ? presetOf(card, presetId) : null;

        sending = { card: card, preset: preset };

        $('#prSendTitle').text(card.name);
        $('#prSendStatus').removeClass('pr-ok pr-bad').text('');
        $('#prSendConfirm').prop('disabled', false);
        buildFields($('#prSendFields'), card, preset ? preset.answers : {}, 'all');
        refreshPreview();

        $('#prSendModal').appendTo('body').modal('show');
    }

    function refreshPreview() {
        $('#prSendPreview').text(compose(sending.card, readFields($('#prSendFields'))) || sending.card.name);
    }

    $(document).on('input pr-picked', '#prSendFields [data-key]', refreshPreview);

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
        buildFields($('#prPresetFields'), card, preset ? preset.answers : {}, 'saved');

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

    /*
     * Suggestions on the boxes set up for a place, a ship or one of the
     * member's characters. They only ever offer text, so whatever somebody
     * types for themselves stays as they typed it.
     */
    var MIN_TYPED = 3;
    var MAX_SHOWN = 12;

    var systems = null;
    var systemsByName = {};
    var systemsRequest = null;
    var spotRequests = {};
    var ships = null;
    var shipsRequest = null;

    var $menu = $('<div class="pr-suggest-menu">');
    var $suggesting = null;

    // Fetched once and matched here, so nothing waits on the network between key presses.
    function loadSystems() {
        if (!systemsRequest) {
            systemsRequest = $.getJSON(config.systemsUrl)
                .done(function (rows) {
                    systems = rows.map(function (row) {
                        var system = { id: row[0], name: row[1], lower: row[1].toLowerCase() };

                        systemsByName[system.lower] = system;

                        return system;
                    });
                })
                .fail(function () {
                    systemsRequest = null;
                });
        }

        return systemsRequest;
    }

    function loadShips() {
        if (!shipsRequest) {
            shipsRequest = $.getJSON(config.shipsUrl)
                .done(function (names) {
                    ships = names.map(function (name) {
                        return { name: name, lower: name.toLowerCase() };
                    });
                })
                .fail(function () {
                    shipsRequest = null;
                });
        }

        return shipsRequest;
    }

    function spotsIn(system) {
        if (!spotRequests[system.id]) {
            spotRequests[system.id] = $.getJSON(config.locationsUrl.replace('__ID__', system.id))
                .fail(function () {
                    delete spotRequests[system.id];
                });
        }

        return spotRequests[system.id];
    }

    function closeMenu() {
        // Detached and not removed, because a form being rebuilt would take its handlers with it.
        $menu.detach().empty();
        $suggesting = null;
    }

    function openMenu($input, groups) {
        var items = groups.reduce(function (all, group) {
            return all.concat(group.items);
        }, []);

        // Nothing to add when the only suggestion is what's already in the box.
        if (items.length === 0 || (items.length === 1 && items[0].value.trim().toLowerCase() === $input.val().trim().toLowerCase())) {
            closeMenu();
            return;
        }

        $menu.empty();

        groups.forEach(function (group) {
            if (group.label && group.items.length > 0) {
                $menu.append($('<div class="pr-suggest-group">').text(group.label));
            }

            group.items.forEach(function (item) {
                $menu.append($('<div class="pr-suggest-item">').text(item.text).data('item', item));
            });
        });

        $menu.find('.pr-suggest-item').first().addClass('pr-active');
        $menu.appendTo($input.closest('.form-group')).scrollTop(0);
        $suggesting = $input;
    }

    function pickSuggestion($item) {
        var item = $item.data('item');
        var $input = $suggesting;

        closeMenu();
        $input.val(item.value).trigger('pr-picked');

        if (item.leadsOn) {
            suggestLocation($input);
        }
    }

    function moveSuggestion(step) {
        var $items = $menu.find('.pr-suggest-item');
        var next = ($items.index($items.filter('.pr-active')) + step + $items.length) % $items.length;

        $items.removeClass('pr-active').eq(next).addClass('pr-active')[0].scrollIntoView({ block: 'nearest' });
    }

    // Longest name first, for the few systems with a space in their name.
    function systemTyped(text) {
        var lower = text.toLowerCase();

        for (var end = lower.length; end > 0; end--) {
            var next = lower.charAt(end);

            if ((next === '' || next === ' ') && systemsByName[lower.slice(0, end)]) {
                return systemsByName[lower.slice(0, end)];
            }
        }

        return null;
    }

    /* Names that start with what was typed come first, then names that only contain it. */
    function matching(list, text) {
        var needle = text.trim().toLowerCase();
        var starting = [];
        var containing = [];

        list.forEach(function (entry) {
            var at = entry.lower.indexOf(needle);

            if (at === 0) {
                starting.push(entry);
            } else if (at > 0) {
                containing.push(entry);
            }
        });

        return { starting: starting.length, list: starting.concat(containing).slice(0, MAX_SHOWN) };
    }

    function squeezed(text) {
        return text.toLowerCase().replace(/[\s-]+/g, '');
    }

    /* Whatever follows the system's name narrows the list, and what starts that way comes first. */
    function narrowSpots(groups, system, typed) {
        var needle = squeezed(typed);
        var prefix = squeezed(system.name);

        return groups.map(function (group) {
            var starting = [];
            var containing = [];

            group.options.forEach(function (option) {
                var tail = squeezed(option);

                tail = tail.indexOf(prefix) === 0 ? tail.slice(prefix.length) : tail;

                if (tail.indexOf(needle) === 0) {
                    starting.push(option);
                } else if (tail.indexOf(needle) > 0) {
                    containing.push(option);
                }
            });

            return {
                label: group.label,
                items: starting.concat(containing).map(function (option) {
                    return { text: option, value: option };
                })
            };
        });
    }

    function suggestLocation($input) {
        if (!systems) {
            loadSystems().done(function () {
                if (document.activeElement === $input[0]) {
                    suggestLocation($input);
                }
            });

            return;
        }

        var text = $input.val().replace(/^\s+/, '');
        var system = systemTyped(text);
        var found = matching(systems, text);

        // Some names are how another system's name starts, so a bare name
        // only counts once nothing else starts that way or a space follows it.
        if (system && (text.length > system.name.length || found.starting === 1)) {
            spotsIn(system).done(function (groups) {
                var now = $input.val().replace(/^\s+/, '');

                // The answer can land after the box has moved on to something else.
                if (document.activeElement === $input[0] && systemTyped(now) === system) {
                    openMenu($input, narrowSpots(groups, system, now.slice(system.name.length)));
                }
            });

            return;
        }

        if (text.trim().length < MIN_TYPED) {
            closeMenu();
            return;
        }

        openMenu($input, [{
            items: found.list.map(function (match) {
                return { text: match.name, value: match.name + ' ', leadsOn: true };
            })
        }]);
    }

    function suggestShip($input) {
        if (!ships) {
            loadShips().done(function () {
                if (document.activeElement === $input[0]) {
                    suggestShip($input);
                }
            });

            return;
        }

        if ($input.val().trim().length < MIN_TYPED) {
            closeMenu();
            return;
        }

        openMenu($input, [{
            items: matching(ships, $input.val()).list.map(function (ship) {
                return { text: ship.name, value: ship.name };
            })
        }]);
    }

    function suggestCharacter($input) {
        var needle = $input.val().trim().toLowerCase();

        if (needle.length < MIN_TYPED) {
            closeMenu();
            return;
        }

        openMenu($input, [{
            items: config.characters.filter(function (name) {
                return name.toLowerCase().indexOf(needle) !== -1;
            }).map(function (name) {
                return { text: name, value: name };
            })
        }]);
    }

    $menu.on('mousedown', function (event) {
        // Keeps the focus in the box, or the blur would shut this before the pick lands.
        event.preventDefault();
    }).on('mousedown', '.pr-suggest-item', function () {
        pickSuggestion($(this));
    });

    // On the forms and not the document, because the Escape below has to get in before the modal's own.
    $('#prSendFields, #prPresetFields, #prActiveList')
        .on('focusin', '[data-lookup="location"]', function () {
            loadSystems();
        })
        .on('focusin', '[data-lookup="ship"]', function () {
            loadShips();
        })
        .on('focusout', '[data-lookup]', closeMenu)
        .on('input', '[data-lookup]', function () {
            var lookup = $(this).attr('data-lookup');

            if (lookup === 'location') {
                suggestLocation($(this));
            } else if (lookup === 'ship') {
                suggestShip($(this));
            } else {
                suggestCharacter($(this));
            }
        })
        .on('keydown', '[data-lookup]', function (event) {
            if (!$suggesting) {
                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                moveSuggestion(event.key === 'ArrowDown' ? 1 : -1);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                pickSuggestion($menu.find('.pr-active'));
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                closeMenu();
            }
        });

    $(function () {
        config.cards.forEach(function (card) {
            if (!card.emergency && card.readyIn > 0) {
                startCooldown(cards[card.id], card.readyIn);
            }
        });

        // They come newest first and each one goes on top, so the oldest goes in first.
        config.active.slice().reverse().forEach(function (entry) {
            showActive(entry, false);
        });

        if (config.watchAnswers) {
            watchAnswers();
        }
    });
})(jQuery);
