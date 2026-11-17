import test from 'node:test';
import assert from 'node:assert/strict';
import { signalState } from '../../assets/js/todo-signal.js';

test('samo budući rok: tačka, bez broja', () => {
    assert.deepEqual(signalState({ count: 0, open: 2 }), { count: 0, showBadge: false, showDot: true });
});
test('zakasnelo ili danas: crveni broj, bez tačke', () => {
    assert.deepEqual(signalState({ count: 3, open: 5 }), { count: 3, showBadge: true, showDot: false });
});
test('prazan Podsetnik: ništa', () => {
    assert.deepEqual(signalState({ count: 0, open: 0 }), { count: 0, showBadge: false, showDot: false });
});
test('bez polja open tačka se ne dira (ne sme da se sakrije)', () => {
    assert.deepEqual(signalState({ count: 0 }), { count: 0, showBadge: false, showDot: null });
});
test('bez ikakvih podataka ništa se ne dira', () => {
    assert.deepEqual(signalState({}), { count: null, showBadge: null, showDot: null });
    assert.deepEqual(signalState(undefined), { count: null, showBadge: null, showDot: null });
});
