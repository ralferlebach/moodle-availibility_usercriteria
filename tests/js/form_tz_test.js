/**
 * Tests for the time zone conversion of the restrict access editor.
 *
 * Runs in plain Node, no Moodle needed:  node tests/js/form_tz_test.js
 * The editor module is loaded with stubs for the YUI and Moodle globals it needs.
 */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '..', '..', 'yui', 'src', 'form', 'js', 'form.js'), 'utf8');
const M = {core_availability: {plugin: {}}};
const Y = {Object: (proto) => Object.assign({}, proto), Escape: {html: (s) => s}};
new Function('M', 'Y', source)(M, Y);
const form = M.availability_usercriteria.form;

/**
 * Convert with a time zone and return an ISO string in UTC.
 *
 * @param {String} tz Time zone.
 * @param {String} local Local date and time, as a datetime-local input would hold it.
 * @return {String} Instant as ISO string.
 */
function utc(tz, local) {
    form.config = {tz: tz};
    return new Date(form.fromInput(local) * 1000).toISOString().slice(0, 16);
}

let count = 0;
/**
 * Run one check.
 *
 * @param {String} name Name.
 * @param {Function} fn Check.
 */
function check(name, fn) {
    fn();
    count++;
    console.log('ok - ' + name);
}

check('Europe/Berlin winter time is UTC+1', () => assert.equal(utc('Europe/Berlin', '2026-01-15T12:00'), '2026-01-15T11:00'));
check('Europe/Berlin summer time is UTC+2', () => assert.equal(utc('Europe/Berlin', '2026-07-15T12:00'), '2026-07-15T10:00'));
check('a date after the switch uses summer time even when the editor is used in winter', () => {
    // 2026-03-29 02:00 CET jumps to 03:00 CEST. 12:00 that day is summer time (UTC+2).
    assert.equal(utc('Europe/Berlin', '2026-03-29T12:00'), '2026-03-29T10:00');
    assert.equal(utc('Europe/Berlin', '2026-03-28T12:00'), '2026-03-28T11:00');
});
check('a date after the autumn switch uses winter time', () => {
    assert.equal(utc('Europe/Berlin', '2026-10-25T12:00'), '2026-10-25T11:00');
    assert.equal(utc('Europe/Berlin', '2026-10-24T12:00'), '2026-10-24T10:00');
});
check('a local time that does not exist is read as the instant after the gap', () => {
    // 2026-03-29 02:30 does not exist in Berlin; the clock goes from 02:00 to 03:00.
    assert.equal(utc('Europe/Berlin', '2026-03-29T02:30'), '2026-03-29T01:30');
});
check('a local time that happens twice is read as its first occurrence', () => {
    // 2026-10-25 02:30 happens twice in Berlin: 00:30 UTC (summer time) and 01:30 UTC (winter time).
    assert.equal(utc('Europe/Berlin', '2026-10-25T02:30'), '2026-10-25T00:30');
});
check('the boundaries of the switch are exact', () => {
    assert.equal(utc('Europe/Berlin', '2026-03-29T01:59'), '2026-03-29T00:59');
    assert.equal(utc('Europe/Berlin', '2026-03-29T03:00'), '2026-03-29T01:00');
    assert.equal(utc('Europe/Berlin', '2026-10-25T03:00'), '2026-10-25T02:00');
});
check('a zone without summer time has a fixed offset', () => {
    assert.equal(utc('Asia/Kolkata', '2026-07-15T12:00'), '2026-07-15T06:30');
    assert.equal(utc('Asia/Kolkata', '2026-01-15T12:00'), '2026-01-15T06:30');
    assert.equal(utc('UTC', '2026-07-15T12:00'), '2026-07-15T12:00');
});
check('a zone with a different summer time calendar (southern hemisphere)', () => {
    assert.equal(utc('Australia/Sydney', '2026-01-15T12:00'), '2026-01-15T01:00');
    assert.equal(utc('Australia/Sydney', '2026-07-15T12:00'), '2026-07-15T02:00');
});
check('a zone with a negative offset', () => {
    assert.equal(utc('America/New_York', '2026-07-15T12:00'), '2026-07-15T16:00');
    assert.equal(utc('America/New_York', '2026-01-15T12:00'), '2026-01-15T17:00');
});
check('round trip: stored timestamp to input and back is stable on every minute around both switches', () => {
    form.config = {tz: 'Europe/Berlin'};
    ['2026-03-29', '2026-10-25'].forEach((day) => {
        const start = Date.parse(day + 'T00:00:00Z') / 1000 - 3600 * 6;
        for (let ts = start; ts < start + 3600 * 36; ts += 60) {
            const back = form.fromInput(form.toInput(ts));
            // An ambiguous hour maps to its first occurrence, so the round trip may only move earlier by one hour.
            assert.ok(back === ts || back === ts - 3600, day + ' ' + ts + ' -> ' + back);
        }
    });
});
check('round trip is exact outside the ambiguous hour', () => {
    form.config = {tz: 'Europe/Berlin'};
    [1767268800, 1784102400, 1789999980].forEach((ts) => assert.equal(form.fromInput(form.toInput(ts)), ts));
});
check('invalid text is returned unchanged so the form can report it', () => {
    form.config = {tz: 'Europe/Berlin'};
    assert.equal(form.fromInput('abc'), 'abc');
});
console.log(count + ' checks passed');
