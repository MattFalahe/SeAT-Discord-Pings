(function ($) {
    'use strict';

    var config = window.PING_REQUESTS;
    var cards = {};
    var FREE_TEXT = 'text';
    var LOOKUP_HINTS = {
        location: 'Type 3 letters of a system, then pick a spot or write your own',
        character: 'Type 3 letters of one of your characters'
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
            $target.append(field(line.text, line.key, answers[line.key], false, line.lookup));
        });
    }

    function field(label, key, value, multiline, lookup) {
        var $input = multiline ? $('<textarea rows="4">') : $('<input type="text">');

        $input.addClass('form-control').attr('data-key', key).attr('maxlength', 1000).val(value || '');

        if (lookup) {
            $input.attr({ 'data-lookup': lookup, autocomplete: 'off', placeholder: LOOKUP_HINTS[lookup] });
        }

        return $('<div class="form-group">')
            .toggleClass('pr-suggests', !!lookup)
            .append($('<label>').text(label))
            .append($input);
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

    /*
     * Suggestions on the boxes whose section names point at a place or a
     * character. They only ever offer text, so whatever somebody types for
     * themselves stays as they typed it.
     */
    var MIN_TYPED = 3;
    var MAX_SYSTEMS = 12;

    var systems = null;
    var systemsByName = {};
    var systemsRequest = null;
    var spotRequests = {};

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

    function systemsMatching(text) {
        var needle = text.trim().toLowerCase();
        var starting = [];
        var containing = [];

        systems.forEach(function (system) {
            var at = system.lower.indexOf(needle);

            if (at === 0) {
                starting.push(system);
            } else if (at > 0) {
                containing.push(system);
            }
        });

        return { starting: starting.length, list: starting.concat(containing).slice(0, MAX_SYSTEMS) };
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
        var found = systemsMatching(text);

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
    $('#prSendFields, #prPresetFields')
        .on('focusin', '[data-lookup="location"]', function () {
            loadSystems();
        })
        .on('focusout', '[data-lookup]', closeMenu)
        .on('input', '[data-lookup]', function () {
            if ($(this).attr('data-lookup') === 'location') {
                suggestLocation($(this));
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
    });
})(jQuery);
