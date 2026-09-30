/**
 * "for 2 dage siden" på dansk, regnet i browseren.
 *
 * Serveren sender ISO-tidspunkter og ikke færdig tekst: kortet kan stå åbent i
 * timevis, og et tidsstempel der er formateret ved svaret, står og lyver om at
 * noget skete "for et øjeblik siden" længe efter.
 */
const relative = new Intl.RelativeTimeFormat('da-DK', { numeric: 'auto' });

const steps = [
    ['minute', 60],
    ['hour', 60],
    ['day', 24],
    ['month', 30.4],
    ['year', 12],
];

export function timeAgo(iso) {
    const then = Date.parse(iso);

    if (!then) {
        return '';
    }

    let value = (then - Date.now()) / 1000;
    let unit = 'second';

    for (const [next, divisor] of steps) {
        if (Math.abs(value) < divisor) {
            break;
        }

        value /= divisor;
        unit = next;
    }

    return relative.format(Math.round(value), unit);
}
