// Pravilo signala Podsetnika na ikonici u headeru (SPEC 8p): jedino mesto koje odlučuje šta se vidi.
// Tačka = ima otvorenih stavki a nema zakasnelih ni današnjih; crveni broj = zakasnelo + današnje.
// Polje koje nedostaje (undefined) znači "ne znam", pa se taj deo signala ne dira (null u rezultatu).
export function signalState(data) {
    const count = Number.isInteger(data?.count) ? data.count : null;
    const open = Number.isInteger(data?.open) ? data.open : null;

    return {
        count,
        showBadge: count === null ? null : count > 0,
        showDot: count === null || open === null ? null : open > 0 && count === 0,
    };
}
