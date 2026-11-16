/**
 * Calendar dates from the server come as "YYYY-MM-DD". Read them as local
 * midnight, so the day does not shift in time zones west of UTC.
 */
export function parseDate(date: string): Date {
    return new Date(`${date}T00:00:00`);
}

export function addDays(date: Date, days: number): Date {
    const result = new Date(date);
    result.setDate(result.getDate() + days);

    return result;
}

export function isSameDay(a: Date, b: Date): boolean {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

/** "Nov 14" */
export function formatShortDate(date: string | Date): string {
    const value = typeof date === 'string' ? parseDate(date) : date;

    return value.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

/** "Nov 16 – 22", or "Nov 30 – Dec 6" when the week spans two months. */
export function formatWeekRange(start: string, end: string): string {
    const from = parseDate(start);
    const to = parseDate(end);
    const sameMonth = from.getMonth() === to.getMonth();
    const endLabel = sameMonth ? String(to.getDate()) : formatShortDate(to);

    return `${formatShortDate(from)} – ${endLabel}`;
}

/** "This week", "Next week", or "Week of Nov 30" for a Monday. */
export function weekName(monday: string): string {
    const today = new Date();
    const thisMonday = addDays(today, -((today.getDay() + 6) % 7));
    const value = parseDate(monday);

    if (isSameDay(value, thisMonday)) {
        return 'This week';
    }

    if (isSameDay(value, addDays(thisMonday, 7))) {
        return 'Next week';
    }

    return `Week of ${formatShortDate(value)}`;
}

/** "YYYY-MM-DD" in local time, the format the server expects. */
export function toDateString(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}
