import test from 'node:test';
import assert from 'node:assert/strict';
import { timeOptions, timeSelectHtml, timeStep } from '../../assets/js/time-select.js';

test('korak 30 daje 48 vremena od 00:00 do 23:30, 24h bez AM/PM', () => {
    const t = timeOptions(30);
    assert.equal(t.length, 48);
    assert.equal(t[0], '00:00');
    assert.equal(t[1], '00:30');
    assert.equal(t[47], '23:30');
    assert.ok(t.every((v) => /^\d\d:\d\d$/.test(v)));
});
test('korak 15 daje 96 vremena', () => assert.equal(timeOptions(15).length, 96));
test('vrednost van koraka se umeće sortirano i ne zaokružuje', () => {
    const t = timeOptions(30, '14:15');
    assert.equal(t.length, 49);
    assert.deepEqual(t.slice(28, 31), ['14:00', '14:15', '14:30']);
});
test('neispravna vrednost se ne umeće', () => {
    assert.equal(timeOptions(30, '25:99').length, 48);
    assert.equal(timeOptions(30, '').length, 48);
});
test('select: prazna opcija, izabrana vrednost, klasa form-select', () => {
    const html = timeSelectHtml({ id: 'x', value: '14:30', emptyLabel: 'Bez vremena' });
    assert.match(html, /class="form-select"/);
    assert.match(html, /<option value="">Bez vremena<\/option>/);
    assert.match(html, /<option value="14:30" selected>14:30<\/option>/);
    assert.equal((html.match(/<option/g) || []).length, 49);
});
test('select bez prazne opcije i sa vrednošću van koraka', () => {
    const html = timeSelectHtml({ id: 'x', value: '14:47' });
    assert.equal((html.match(/<option/g) || []).length, 49);
    assert.match(html, /<option value="14:47" selected>14:47<\/option>/);
    assert.doesNotMatch(html, /value=""/);
});
test('timeStep čita meta, a rezerva je 30', () => {
    assert.equal(timeStep({ querySelector: () => ({ content: '15' }) }), 15);
    assert.equal(timeStep({ querySelector: () => null }), 30);
    assert.equal(timeStep({ querySelector: () => ({ content: 'abc' }) }), 30);
});
