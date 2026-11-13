/*
 * The Send and Schedule broadcast forms. One place fills them in, whether
 * from Settings' defaults, a template, a recent ping or the Send page's
 * Schedule button, and one place reads them back, so every box goes along
 * every time. Also the suggestions as an FC types.
 */
(function ($) {
    'use strict';

    var config = window.BROADCAST_FORM || {};
    var $form = $();
    var multiple = false;
    var webhookRequired = false;

    // Set while a fill is going on, so a box's own reaction to a change
    // doesn't stop to ask the FC about values they didn't type.
    var filling = false;

    function field(name) {
        return $form.find('[name="' + name + '"]');
    }

    function has(values, key) {
        return Object.prototype.hasOwnProperty.call(values, key) && values[key] !== null && values[key] !== undefined;
    }

    /** Picks the option when the select has it, and leaves the select as it was when it doesn't. */
    function choose($select, value) {
        var wanted = String(value);
        var found = $select.find('option').filter(function () {
            return this.value === wanted;
        }).length > 0;

        if (found) {
            $select.val(wanted);
        }

        return found;
    }

    /** A doctrine picked from SeAT Fitting goes out as a link, and the box wants its name. */
    function doctrineName(values) {
        var name = String(has(values, 'doctrine_name') ? values.doctrine_name : (values.doctrine || '')).trim();
        var link = /^\[(.+)\]\((https?:\/\/[^)\s]+)\)$/.exec(name);

        return link ? link[1] : name;
    }

    function setMultiple(on) {
        if (!$('#multipleWebhooksDiv').length || on === multiple) {
            return;
        }

        multiple = on;
        $('#singleWebhookDiv').toggle(!on);
        $('#multipleWebhooksDiv').toggle(on);

        // Only the list in use is sent. The other keeps what was picked in it, for switching back.
        $('#webhookSelect').prop('disabled', on).prop('required', !on && webhookRequired);
        $('#multipleWebhooksDiv').find('[name="webhook_ids[]"]').prop('disabled', !on);

        $('#multipleToggle').html(on
            ? '<i class="fas fa-bullhorn"></i> Single Webhook'
            : '<i class="fas fa-copy"></i> Multiple Webhooks');

        // Send posts each mode to its own route. A template saves both the same way.
        if (config.singleAction && config.multipleAction) {
            $form.attr('action', on ? config.multipleAction : config.singleAction);
        }
    }

    function fillWebhooks(values) {
        var ids = $.isArray(values.webhook_ids) ? values.webhook_ids.map(String) : [];

        if (ids.length > 1 && $('#multipleWebhooksDiv').length) {
            setMultiple(true);
            $form.find('[name="webhook_ids[]"]').each(function () {
                this.checked = ids.indexOf(this.value) !== -1;
            });

            return;
        }

        // A form with one webhook, like Schedule, takes the first of several.
        var id = ids.length > 0 ? ids[0] : (has(values, 'webhook_id') ? values.webhook_id : null);

        if (id === null) {
            return;
        }

        setMultiple(false);

        // The change hands over the webhook's own colour, unless the values bring one below.
        if (choose(field('webhook_id'), id)) {
            field('webhook_id').trigger('change');
        }
    }

    function fillDoctrine(values) {
        var $select = $('#doctrineSelect');
        var name = doctrineName(values);

        if (!$select.length) {
            $form.find('input[name="doctrine"]').val(name);
            return;
        }

        var id = has(values, 'doctrine_id') ? String(values.doctrine_id) : '';
        var picked = id !== '' && id !== 'custom' && choose($select, id);

        if (!picked && name !== '') {
            $select.find('option').each(function () {
                if (this.value !== '' && this.value !== 'custom' && $(this).text().trim() === name) {
                    $select.val(this.value);
                    picked = true;
                    return false;
                }
            });
        }

        if (picked) {
            $select.trigger('change');
        } else if (name !== '') {
            $select.val('custom').trigger('change');
            $('#doctrineManual').val(name);
        } else {
            $select.val('').trigger('change');
        }
    }

    /**
     * Fills in the boxes the values name and leaves the rest as they are.
     * Never the request a broadcast answers: only the server puts that on a page.
     */
    function fill(values) {
        values = values || {};
        filling = true;

        try {
            fillWebhooks(values);

            if (has(values, 'embed_type')) {
                choose(field('embed_type'), values.embed_type);
            }

            if (has(values, 'message')) {
                field('message').val(values.message).trigger('input');
            }

            if (has(values, 'mention_type') && choose(field('mention_type'), values.mention_type)) {
                field('mention_type').trigger('change');
            }

            // Templates have always kept the role as mention_role_id.
            var role = has(values, 'role_mention') ? values.role_mention : values.mention_role_id;
            if (role !== undefined && role !== null) {
                choose(field('role_mention'), role);
            }

            if (has(values, 'custom_mention')) {
                field('custom_mention').val(values.custom_mention);
            }

            // Comms before the channel, so the channel's warning reads both as they end up.
            if (has(values, 'comms')) {
                field('comms').val(values.comms).trigger('input');
            }

            if (has(values, 'channel_link') && choose(field('channel_link'), values.channel_link)) {
                field('channel_link').trigger('change');
            }

            if (has(values, 'embed_color') && /^#[0-9A-Fa-f]{6}$/.test(values.embed_color)) {
                $('#embedColor').val(values.embed_color);
                $('#colorPicker').val(values.embed_color);
            }

            ['fc_name', 'formup_location'].forEach(function (name) {
                if (has(values, name)) {
                    field(name).val(values[name]);
                }
            });

            if (has(values, 'pap_type')) {
                var $pap = field('pap_type');

                // One switched off since keeps its name, so the broadcast still says what it was.
                if (!choose($pap, values.pap_type) && values.pap_type !== '') {
                    $pap.append($('<option>').val(values.pap_type).text(values.pap_type)).val(values.pap_type);
                }
            }

            if (has(values, 'doctrine') || has(values, 'doctrine_id') || has(values, 'doctrine_name')) {
                fillDoctrine(values);
            }
        } finally {
            filling = false;
        }
    }

    /** Every box as it stands, in the shape fill() takes back. */
    function read() {
        var values = {
            webhook_id: multiple ? '' : (field('webhook_id').val() || ''),
            embed_type: field('embed_type').val() || '',
            message: field('message').val() || '',
            mention_type: field('mention_type').val() || 'none',
            role_mention: field('role_mention').val() || '',
            custom_mention: field('custom_mention').val() || '',
            channel_link: field('channel_link').val() || '',
            embed_color: $('#embedColor').val() || '',
            fc_name: field('fc_name').val() || '',
            formup_location: field('formup_location').val() || '',
            pap_type: field('pap_type').val() || '',
            comms: field('comms').val() || ''
        };

        if (multiple) {
            values.webhook_ids = $form.find('[name="webhook_ids[]"]:checked').map(function () {
                return this.value;
            }).get();
        }

        var $select = $('#doctrineSelect');

        if ($select.length) {
            var id = $select.val() || '';

            values.doctrine_id = id === 'custom' ? '' : id;
            values.doctrine = id === 'custom'
                ? ($('#doctrineManual').val() || '')
                : (id ? $select.find('option:selected').text().trim() : '');
        } else {
            values.doctrine = $form.find('input[name="doctrine"]').val() || '';
        }

        return values;
    }

    /*
     * Suggestions. They only ever offer text, so whatever an FC types for
     * themselves stays as they typed it.
     */
    var MIN_TYPED = 3;
    var MAX_SHOWN = 12;

    var systems = null;
    var systemsByName = {};
    var systemsRequest = null;
    var spotRequests = {};

    var $menu = $('<div class="bf-suggest-menu">');
    var $suggesting = null;
    var picking = false;

    // Fetched once and matched here, so nothing waits on the network between key presses.
    function loadSystems() {
        if (!systemsRequest && config.systemsUrl) {
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

        return systemsRequest || $.Deferred().reject().promise();
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
                $menu.append($('<div class="bf-suggest-group">').text(group.label));
            }

            group.items.forEach(function (item) {
                $menu.append($('<div class="bf-suggest-item">').text(item.text).data('item', item));
            });
        });

        $menu.find('.bf-suggest-item').first().addClass('bf-active');

        // Straight after the box, so it opens under it rather than under the help text below.
        var $group = $input.closest('.input-group');
        $menu.insertAfter($group.length ? $group : $input).scrollTop(0);
        $suggesting = $input;
    }

    function pickSuggestion($item) {
        var item = $item.data('item');
        var $input = $suggesting;

        if (!item || !$input) {
            return;
        }

        closeMenu();
        picking = true;
        $input.val(item.value).trigger('input');
        picking = false;

        // A system leads on to the spots in it.
        if (item.leadsOn) {
            suggestLocation($input);
        }
    }

    function moveSuggestion(step) {
        var $items = $menu.find('.bf-suggest-item');
        var next = ($items.index($items.filter('.bf-active')) + step + $items.length) % $items.length;

        $items.removeClass('bf-active').eq(next).addClass('bf-active')[0].scrollIntoView({ block: 'nearest' });
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

    /* The same for plain lists of names. An empty box gets them all. */
    function fromList(list, text) {
        var needle = text.trim().toLowerCase();
        var starting = [];
        var containing = [];

        (list || []).forEach(function (value) {
            var at = value.toLowerCase().indexOf(needle);

            if (at === 0) {
                starting.push(value);
            } else if (at > 0) {
                containing.push(value);
            }
        });

        return starting.concat(containing).slice(0, MAX_SHOWN).map(function (value) {
            return { text: value, value: value };
        });
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

    function typedBefore(kind) {
        return (config.typedBefore || {})[kind] || [];
    }

    function suggestLocation($input) {
        var text = $input.val().replace(/^\s+/, '');
        var before = { label: 'Used before', items: fromList(typedBefore('formup_location'), text) };

        if (!systems) {
            openMenu($input, [before]);
            loadSystems().done(function () {
                if (document.activeElement === $input[0]) {
                    suggestLocation($input);
                }
            });

            return;
        }

        var system = systemTyped(text);
        var found = matching(systems, text);

        // Some names are how another system's name starts, so a bare name
        // only counts once nothing else starts that way or a space follows it.
        if (system && (text.length > system.name.length || found.starting === 1)) {
            spotsIn(system).done(function (groups) {
                var now = $input.val().replace(/^\s+/, '');

                // The answer can land after the box has moved on to something else.
                if (document.activeElement === $input[0] && systemTyped(now) === system) {
                    openMenu($input, [before].concat(narrowSpots(groups, system, now.slice(system.name.length))));
                }
            });

            return;
        }

        var groups = [before];

        if (text.trim().length >= MIN_TYPED) {
            groups.push({
                label: 'Systems',
                items: found.list.map(function (match) {
                    return { text: match.name, value: match.name + ' ', leadsOn: true };
                })
            });
        }

        openMenu($input, groups);
    }

    function suggest($input) {
        var kind = $input.attr('data-suggest');

        if (kind === 'location') {
            suggestLocation($input);
        } else if (kind === 'character') {
            openMenu($input, [{ label: '', items: fromList(config.characters, $input.val()) }]);
        } else {
            openMenu($input, [{ label: 'Used before', items: fromList(typedBefore(kind), $input.val()) }]);
        }
    }

    function bindSuggestions() {
        $form.find('[data-suggest]').closest('.form-group').addClass('bf-suggests');

        $menu.on('mousedown', function (event) {
            // Keeps the focus in the box, or the blur would shut this before the pick lands.
            event.preventDefault();
        }).on('mousedown', '.bf-suggest-item', function () {
            pickSuggestion($(this));
        });

        $form
            .on('focusin', '[data-suggest]', function () {
                if ($(this).attr('data-suggest') === 'location') {
                    loadSystems();
                }

                // An empty box offers its list straight away. One with something in it waits for typing.
                if ($(this).val().trim() === '') {
                    suggest($(this));
                }
            })
            .on('focusout', '[data-suggest]', closeMenu)
            .on('input', '[data-suggest]', function () {
                if (!filling && !picking && document.activeElement === this) {
                    suggest($(this));
                }
            })
            .on('keydown', '[data-suggest]', function (event) {
                if (!$suggesting) {
                    return;
                }

                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    moveSuggestion(event.key === 'ArrowDown' ? 1 : -1);
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    pickSuggestion($menu.find('.bf-active'));
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    closeMenu();
                }
            });
    }

    /* What every box does by itself, the same on both pages. */
    function bindBoxes() {
        field('message').on('input', function () {
            $('#charCount').text($(this).val().length);
        }).trigger('input');

        $('#mentionType').on('change', function () {
            var value = $(this).val();

            $('#roleMentionDiv').toggle(value === 'role');
            $('#customMentionDiv').toggle(value === 'custom');
        });

        $form.on('click', '.staging-select', function (event) {
            event.preventDefault();
            $('#formupLocation').val($(this).data('location'));
        });

        var commsHelp = '<i class="fas fa-info-circle"></i> For voice comms or custom channels. ' +
            '<strong class="text-warning">Leave empty if Discord Channel is selected above</strong>.';

        $('#channelSelect').on('change', function () {
            var $comms = $('#commsField');

            if ($(this).val()) {
                $comms.attr('placeholder', 'Leave empty - Discord channel already selected');
                $comms.parent().find('.text-warning').addClass('text-danger font-weight-bold');

                if (!filling && $comms.val() && window.confirm('You have selected a Discord channel. Clear the Comms field to avoid duplication?')) {
                    $comms.val('');
                }
            } else {
                $comms.attr('placeholder', 'e.g., Mumble Channel 3 or TeamSpeak Room 5');
                $comms.parent().find('.text-warning').removeClass('text-danger font-weight-bold');
            }
        });

        $('#commsField').on('input', function () {
            var $help = $(this).parent().find('small');

            if ($('#channelSelect').val() && $(this).val()) {
                $help.html('<i class="fas fa-exclamation-triangle text-warning"></i> ' +
                    '<strong class="text-danger">Warning: Discord channel already selected. This may create duplicate entries!</strong>');
            } else if (!$('#channelSelect').val()) {
                $help.html(commsHelp);
            }
        });

        $('#colorPicker').on('input change', function () {
            $('#embedColor').val($(this).val());
        });

        $('#embedColor').on('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test($(this).val())) {
                $('#colorPicker').val($(this).val());
            }
        });

        $('#webhookSelect').on('change', function () {
            var color = $(this).find(':selected').data('color');

            if (color) {
                $('#embedColor').val(color);
                $('#colorPicker').val(color);
            }
        });

        $('#multipleToggle').on('click', function () {
            setMultiple(!multiple);
        });

        // The name moves between the list and the box, so only the one in use is sent.
        $('#doctrineSelect').on('change', function () {
            var manual = $(this).val() === 'custom';

            $('#doctrineManual').toggle(manual).prop('name', manual ? 'doctrine' : '');
            $(this).prop('name', manual ? '' : 'doctrine_id');

            if (!manual) {
                $('#doctrineManual').val('');
            }
        }).trigger('change');
    }

    window.BroadcastForm = {
        init: function (selector, values) {
            $form = $(selector);
            webhookRequired = $('#webhookSelect').prop('required') === true;
            bindBoxes();
            bindSuggestions();
            fill(values || {});
        },
        fill: fill,
        read: read
    };
})(jQuery);
