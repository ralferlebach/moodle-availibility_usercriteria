/**
 * JavaScript for editing user criteria conditions.
 *
 * @module moodle-availability_usercriteria-form
 */
M.availability_usercriteria = M.availability_usercriteria || {};

/**
 * @class M.availability_usercriteria.form
 * @extends M.core_availability.plugin
 */
M.availability_usercriteria.form = Y.Object(M.core_availability.plugin);

/**
 * Configuration from the server: features, operators, options, labels.
 *
 * @property config
 * @type Object
 */
M.availability_usercriteria.form.config = null;

/**
 * Initialises this plugin.
 *
 * @method initInner
 * @param {Object} config Configuration object built by the PHP frontend class.
 */
M.availability_usercriteria.form.initInner = function(config) {
    this.config = config;
};

/**
 * Escapes text for use in HTML.
 *
 * @method esc
 * @param {String} text Text.
 * @return {String} Escaped text.
 */
M.availability_usercriteria.form.esc = function(text) {
    return Y.Escape.html('' + text);
};

/**
 * Finds a feature definition.
 *
 * @method findFeature
 * @param {String} key Feature key.
 * @return {Object|null} Definition or null.
 */
M.availability_usercriteria.form.findFeature = function(key) {
    for (var i = 0; i < this.config.features.length; i++) {
        if (this.config.features[i].key === key) {
            return this.config.features[i];
        }
    }
    return null;
};

/**
 * Finds an operator definition offered for a feature.
 *
 * @method findOperator
 * @param {Object} feature Feature definition.
 * @param {String} op Operator key.
 * @return {Object|null} Definition or null.
 */
M.availability_usercriteria.form.findOperator = function(feature, op) {
    var list = this.operatorList(feature);
    for (var i = 0; i < list.length; i++) {
        if (list[i].op === op) {
            return list[i];
        }
    }
    return null;
};

/**
 * Whether an operator compares with the other participants.
 *
 * @method isRelative
 * @param {String} op Operator key.
 * @return {Boolean} True for a relative operator.
 */
M.availability_usercriteria.form.isRelative = function(op) {
    var list = this.config.operators.relative || [];
    for (var i = 0; i < list.length; i++) {
        if (list[i].op === op) {
            return true;
        }
    }
    return false;
};

/**
 * The operators offered for a feature: those of its type, plus the relative operators for the features that
 * can be compared with the other participants.
 *
 * @method operatorList
 * @param {Object} feature Feature definition.
 * @return {Array} Operator definitions.
 */
M.availability_usercriteria.form.operatorList = function(feature) {
    var list = (this.config.operators[feature.type] || []).slice();
    if (feature.relative) {
        list = list.concat(this.config.operators.relative || []);
    }
    return list;
};

/**
 * The wall clock time that a time zone shows at an instant, as if that wall clock time were UTC.
 *
 * @method wallTime
 * @param {Number} ms Instant in milliseconds since the epoch.
 * @param {String} tz Time zone name.
 * @return {Number} Milliseconds of the wall clock time, read as UTC.
 */
M.availability_usercriteria.form.wallTime = function(ms, tz) {
    var parts = new Intl.DateTimeFormat('en-US', {
        timeZone: tz, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
    }).formatToParts(new Date(ms - (((ms % 1000) + 1000) % 1000)));
    var v = {};
    for (var i = 0; i < parts.length; i++) {
        if (parts[i].type !== 'literal') {
            v[parts[i].type] = parseInt(parts[i].value, 10);
        }
    }
    return Date.UTC(v.year, v.month - 1, v.day, v.hour, v.minute, v.second);
};

/**
 * Offset of a time zone from UTC at an instant.
 *
 * @method offsetAt
 * @param {Number} ms Instant in milliseconds.
 * @param {String} tz Time zone name.
 * @return {Number} Offset in milliseconds.
 */
M.availability_usercriteria.form.offsetAt = function(ms, tz) {
    return this.wallTime(ms, tz) - (ms - (((ms % 1000) + 1000) % 1000));
};

/**
 * Converts a local date and time to an instant, with the rules of the time zone on that very date.
 *
 * A local time that happens twice (clocks go back) is read as its first occurrence. A local time that does not
 * exist (clocks go forward) is read as the instant after the gap, so 02:30 becomes 03:30 summer time.
 *
 * @method localToInstant
 * @param {Number} wall The local date and time, written as milliseconds of the same time in UTC.
 * @param {String} tz Time zone name.
 * @return {Number} Instant in milliseconds.
 */
M.availability_usercriteria.form.localToInstant = function(wall, tz) {
    var day = 86400000;
    var before = this.offsetAt(wall - day, tz);
    var after = this.offsetAt(wall + day, tz);
    var valid = [];
    [before, after].forEach(function(offset) {
        var candidate = wall - offset;
        if (M.availability_usercriteria.form.offsetAt(candidate, tz) === offset) {
            valid.push(candidate);
        }
    });
    if (valid.length) {
        return Math.min.apply(null, valid);
    }
    return wall - before;
};

/**
 * Converts a stored UTC timestamp to the value of a datetime-local input in the editing person's time zone.
 *
 * @method toInput
 * @param {Number} ts Timestamp in seconds.
 * @return {String} Value for the input.
 */
M.availability_usercriteria.form.toInput = function(ts) {
    return new Date(this.wallTime(ts * 1000, this.config.tz)).toISOString().slice(0, 16);
};

/**
 * Converts the value of a datetime-local input, read in the editing person's time zone, to a UTC timestamp.
 *
 * @method fromInput
 * @param {String} text Input value.
 * @return {Number|String} Timestamp, or the text if invalid.
 */
M.availability_usercriteria.form.fromInput = function(text) {
    var match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(text);
    if (!match) {
        return text;
    }
    var wall = Date.UTC(parseInt(match[1], 10), parseInt(match[2], 10) - 1, parseInt(match[3], 10),
            parseInt(match[4], 10), parseInt(match[5], 10));
    return Math.round(this.localToInstant(wall, this.config.tz) / 1000);
};

/**
 * Parses a decimal number written with a dot or a comma.
 *
 * @method parseNumber
 * @param {String} text Input text.
 * @return {Number|String} The number, or the text if it is not a number.
 */
M.availability_usercriteria.form.parseNumber = function(text) {
    text = ('' + text).replace(/^\s+|\s+$/g, '');
    if (!(/^-?[0-9]+([.,][0-9]+)?$/.test(text))) {
        return text;
    }
    return parseFloat(text.replace(',', '.'));
};

/**
 * Builds the node for one condition.
 *
 * @method getNode
 * @param {Object} json Stored structure, or an empty object for a new condition.
 * @return {Y.Node} Node.
 */
M.availability_usercriteria.form.getNode = function(json) {
    var self = this;
    var labels = this.config.labels;
    var html = '<span class="availability-group mb-2 me-2"><label><span class="accesshide">' +
            this.esc(labels.feature) + '</span><select name="feature" class="form-select custom-select">' +
            '<option value="">' + this.esc(labels.choose) + '</option>';
    var grouped = {};
    var i;
    for (i = 0; i < this.config.features.length; i++) {
        var feature = this.config.features[i];
        grouped[feature.category] = grouped[feature.category] || [];
        grouped[feature.category].push(feature);
    }
    for (var category in this.config.categories) {
        if (!grouped[category]) {
            continue;
        }
        html += '<optgroup label="' + this.esc(this.config.categories[category]) + '">';
        for (i = 0; i < grouped[category].length; i++) {
            html += '<option value="' + this.esc(grouped[category][i].key) + '">' +
                    this.esc(grouped[category][i].label) + '</option>';
        }
        html += '</optgroup>';
    }
    html += '</select></label></span>' +
            '<span class="availability-group mb-2 me-2 uc-operator"></span>' +
            '<span class="availability-group mb-2 me-2 uc-values"></span>' +
            '<span class="uc-hint small text-muted d-block w-100"></span>';
    var node = Y.Node.create('<div class="d-inline-block d-flex flex-wrap align-items-center">' + html + '</div>');

    if (json.feature !== undefined && node.one('select[name=feature] > option[value="' + json.feature + '"], ' +
            'select[name=feature] optgroup > option[value="' + json.feature + '"]')) {
        node.one('select[name=feature]').set('value', json.feature);
    }
    this.renderOperator(node, json.op);
    this.renderValues(node, json);

    node.one('select[name=feature]').on('change', function() {
        self.renderOperator(node, undefined);
        self.renderValues(node, {});
        M.core_availability.form.update();
    });
    node.one('.uc-operator').delegate('change', function() {
        self.renderValues(node, {});
        M.core_availability.form.update();
    }, 'select[name=op]');
    node.one('.uc-values').delegate('change', function() {
        M.core_availability.form.update();
    }, 'select, input');
    node.one('.uc-values').delegate('valuechange', function() {
        M.core_availability.form.update();
    }, 'input');
    return node;
};

/**
 * Fills the operator list for the selected feature.
 *
 * @method renderOperator
 * @param {Y.Node} node Condition node.
 * @param {String} selected Operator to select.
 */
M.availability_usercriteria.form.renderOperator = function(node, selected) {
    var feature = this.findFeature(node.one('select[name=feature]').get('value'));
    var holder = node.one('.uc-operator');
    if (!feature) {
        holder.setHTML('');
        return;
    }
    var html = '<label><span class="accesshide">' + this.esc(this.config.labels.operator) +
            '</span><select name="op" class="form-select custom-select">';
    var list = this.operatorList(feature);
    for (var i = 0; i < list.length; i++) {
        html += '<option value="' + this.esc(list[i].op) + '">' + this.esc(list[i].label) + '</option>';
    }
    html += '</select></label>';
    holder.setHTML(html);
    if (selected !== undefined && holder.one('select[name=op] > option[value="' + selected + '"]')) {
        holder.one('select[name=op]').set('value', selected);
    }
};

/**
 * Fills the operand inputs for the selected feature and operator.
 *
 * @method renderValues
 * @param {Y.Node} node Condition node.
 * @param {Object} json Stored structure to take initial values from.
 */
M.availability_usercriteria.form.renderValues = function(node, json) {
    var feature = this.findFeature(node.one('select[name=feature]').get('value'));
    var holder = node.one('.uc-values');
    var hint = node.one('.uc-hint');
    holder.setHTML('');
    hint.setHTML('');
    if (!feature) {
        return;
    }
    var opNode = node.one('select[name=op]');
    var operator = opNode ? this.findOperator(feature, opNode.get('value')) : null;
    var scope = feature.scope === 'course' ? this.config.labels.courseinfo : this.config.labels.siteinfo;
    var hintText = scope + (this.config.hints[feature.key] ? ' ' + this.config.hints[feature.key] : '');
    if (operator && (operator.kind === 'count' || operator.kind === 'percent' || this.isRelative(operator.op))) {
        hintText += ' ' + this.config.labels.relativeinfo;
    }
    hint.setHTML(this.esc(hintText));
    if (!operator || operator.kind === 'none') {
        return;
    }
    var labels = this.config.labels;
    var html = '';
    var isTime = feature.type === 'time' && operator.kind !== 'days';
    if (operator.kind === 'list') {
        var options = this.config.options[feature.key] || [];
        html = '<label><span class="accesshide">' + this.esc(labels.value) +
                '</span><select name="val" multiple="multiple" class="form-select custom-select" size="' +
                Math.min(Math.max(options.length, 2), 6) + '">';
        for (var i = 0; i < options.length; i++) {
            html += '<option value="' + this.esc(options[i].id) + '">' + this.esc(options[i].name) + '</option>';
        }
        html += '</select></label>';
    } else {
        var inputType = isTime ? 'datetime-local' : 'text';
        var extra = isTime ? '' : ' inputmode="' + (feature.type === 'number' || operator.kind === 'days' ?
                'decimal' : 'text') + '"';
        var unit = {days: labels.days, count: labels.count, percent: labels.percent}[operator.kind];
        html = '<label><span class="accesshide">' + this.esc(operator.kind === 'days' ? labels.days : labels.value) +
                '</span><input type="' + inputType + '" name="val" class="form-control"' + extra + '/></label>';
        if (unit) {
            html += ' ' + this.esc(unit);
        }
        if (operator.kind === 'pair') {
            html += ' <label><span class="accesshide">' + this.esc(labels.value2) + '</span><input type="' + inputType +
                    '" name="val2" class="form-control"' + extra + '/></label>' +
                    ' <label><span class="accesshide">' + this.esc(labels.bounds) +
                    '</span><select name="inc" class="form-select custom-select">';
            for (var b = 0; b < this.config.bounds.length; b++) {
                html += '<option value="' + this.esc(this.config.bounds[b].id) + '">' +
                        this.esc(this.config.bounds[b].name) + '</option>';
            }
            html += '</select></label>';
        }
    }
    holder.setHTML(html);
    this.applyValues(holder, feature, operator, json);
};

/**
 * Sets the operand inputs from a stored structure.
 *
 * @method applyValues
 * @param {Y.Node} holder Operand container.
 * @param {Object} feature Feature definition.
 * @param {Object} operator Operator definition.
 * @param {Object} json Stored structure.
 */
M.availability_usercriteria.form.applyValues = function(holder, feature, operator, json) {
    if (json.op !== operator.op || json.val === undefined) {
        return;
    }
    var isTime = feature.type === 'time' && operator.kind !== 'days';
    if (operator.kind === 'list') {
        holder.one('select[name=val]').get('options').each(function(option) {
            option.set('selected', json.val.indexOf(option.get('value')) !== -1);
        });
        return;
    }
    holder.one('input[name=val]').set('value', isTime ? this.toInput(json.val) : '' + json.val);
    if (operator.kind === 'pair' && json.val2 !== undefined) {
        holder.one('input[name=val2]').set('value', isTime ? this.toInput(json.val2) : '' + json.val2);
        // Rules saved before the limits were selectable have none: both limits were included.
        holder.one('select[name=inc]').set('value', json.inc || 'both');
    }
};

/**
 * Reads the stored structure from the node.
 *
 * @method fillValue
 * @param {Object} value Object to fill.
 * @param {Y.Node} node Condition node.
 */
M.availability_usercriteria.form.fillValue = function(value, node) {
    value.v = 1;
    value.feature = node.one('select[name=feature]').get('value');
    var feature = this.findFeature(value.feature);
    var opNode = node.one('select[name=op]');
    value.op = opNode ? opNode.get('value') : '';
    var operator = feature ? this.findOperator(feature, value.op) : null;
    if (!operator || operator.kind === 'none') {
        return;
    }
    var holder = node.one('.uc-values');
    var isTime = feature.type === 'time' && operator.kind !== 'days';
    var self = this;
    var read = function(name) {
        var input = holder.one('input[name=' + name + ']');
        // The inputs are rebuilt when the feature or operator changes; until then there may be none.
        var text = input ? input.get('value') : '';
        if (isTime) {
            return self.fromInput(text);
        }
        if (feature.type === 'number' || operator.kind === 'days' || operator.kind === 'pair' ||
                operator.kind === 'count' || operator.kind === 'percent') {
            return self.parseNumber(text);
        }
        return text;
    };
    if (operator.kind === 'list') {
        value.val = [];
        var select = holder.one('select[name=val]');
        if (select) {
            select.get('options').each(function(option) {
                if (option.get('selected')) {
                    value.val.push(option.get('value'));
                }
            });
        }
        return;
    }
    value.val = read('val');
    if (operator.kind === 'pair') {
        value.val2 = read('val2');
        var inc = holder.one('select[name=inc]');
        if (inc && inc.get('value') !== 'both') {
            value.inc = inc.get('value');
        }
    }
};

/**
 * Reports configuration errors, so the form cannot be saved with an incomplete condition.
 *
 * @method fillErrors
 * @param {Array} errors Array to add error identifiers to.
 * @param {Y.Node} node Condition node.
 */
M.availability_usercriteria.form.fillErrors = function(errors, node) {
    var value = {};
    this.fillValue(value, node);
    var feature = this.findFeature(value.feature);
    if (!feature) {
        errors.push('availability_usercriteria:error_selectfeature');
        return;
    }
    var operator = this.findOperator(feature, value.op);
    if (!operator) {
        errors.push('availability_usercriteria:error_selectoperator');
        return;
    }
    if (operator.kind === 'none') {
        return;
    }
    if (operator.kind === 'list') {
        if (!value.val.length) {
            errors.push('availability_usercriteria:error_selectvalue');
        }
        return;
    }
    // Text operators need a non-empty string; every other single, pair or days operand must be a number.
    var textOperand = feature.type === 'text' && operator.kind === 'single';
    var bad = function(item) {
        if (textOperand) {
            return item === '';
        }
        return typeof item !== 'number';
    };
    if (bad(value.val) || (operator.kind === 'pair' && bad(value.val2))) {
        errors.push('availability_usercriteria:error_invalidvalue');
    } else if (operator.kind === 'pair' && value.val > value.val2) {
        errors.push('availability_usercriteria:error_backwardrange');
    } else if (operator.kind === 'days' && (value.val < 1 || value.val !== Math.floor(value.val))) {
        errors.push('availability_usercriteria:error_invaliddays');
    } else if (operator.kind === 'count' && (value.val < 1 || value.val !== Math.floor(value.val))) {
        errors.push('availability_usercriteria:error_invalidcount');
    } else if (operator.kind === 'percent' && (value.val <= 0 || value.val > 100)) {
        errors.push('availability_usercriteria:error_invalidpercent');
    }
};
