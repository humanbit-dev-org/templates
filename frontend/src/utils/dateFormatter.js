/**
 * Formats a date as a short locale date (e.g. DD/MM/YYYY in it).
 * Used for stat fields, tables, and compact read-only display.
 *
 * @param {string|Date|null|undefined} value
 * @param {string} lang - BCP 47 language tag (e.g. "it", "en")
 * @param {{ empty?: string }} [options]
 * @returns {string}
 */
export function formatDate(value, lang, { empty = "" } = {}) {
	if (value === null || value === undefined || value === "") {
		return empty;
	}

	const date = new Date(value);
	if (Number.isNaN(date.getTime())) {
		return String(value);
	}

	return date.toLocaleDateString(lang);
}

/**
 * Extracts YYYY-MM-DD from an ISO timestamp or date value.
 * Used for delibera created_at and tiraggio data_delibera normalization.
 *
 * @param {string|Date|null|undefined} value
 * @param {{ empty?: string }} [options]
 * @returns {string}
 */
export function toIsoDateKey(value, { empty = "" } = {}) {
	if (value === null || value === undefined || value === "") {
		return empty;
	}

	const date = new Date(value);
	if (Number.isNaN(date.getTime())) {
		return String(value);
	}

	const yyyy = date.getFullYear();
	const mm = String(date.getMonth() + 1).padStart(2, "0");
	const dd = String(date.getDate()).padStart(2, "0");

	return `${yyyy}-${mm}-${dd}`;
}

/**
 * Extracts YYYY-MM month key from a date or ISO timestamp.
 * Used for ATP month tabs and initial tab selection.
 *
 * @param {string|Date|null|undefined} value
 * @param {{ empty?: string }} [options]
 * @returns {string}
 */
export function toMonthKey(value, { empty = "" } = {}) {
	const isoDate = toIsoDateKey(value, { empty });
	if (!isoDate || isoDate.length < 7) {
		return empty;
	}

	return isoDate.slice(0, 7);
}

/**
 * Resolves the earliest month anchor for ATP tab navigation.
 * Compares open delibera created_at against tiraggio erogation dates.
 *
 * @param {string|Date|null|undefined} deliberaStartDate
 * @param {Array<{ data_delibera?: string|null }>|null|undefined} tiraggi
 * @returns {Date}
 */
export function resolveAtpTabRangeStart(deliberaStartDate, tiraggi) {
	const candidates = [];

	if (deliberaStartDate) {
		const deliberaDate = new Date(deliberaStartDate);
		if (!Number.isNaN(deliberaDate.getTime())) {
			candidates.push(deliberaDate);
		}
	}

	for (const tiraggio of tiraggi ?? []) {
		if (!tiraggio?.data_delibera) continue;
		const erogationDate = new Date(tiraggio.data_delibera);
		if (!Number.isNaN(erogationDate.getTime())) {
			candidates.push(erogationDate);
		}
	}

	if (!candidates.length) {
		return new Date();
	}

	return candidates.reduce((earliest, current) => (current < earliest ? current : earliest));
}

/**
 * Formats a month key (YYYY-MM) as long month + year (e.g. "luglio 2026" in it).
 * Used for ATP month tabs, chart titles, and print labels.
 *
 * @param {string|null|undefined} monthKey - Month in "YYYY-MM" format
 * @param {string} lang - BCP 47 language tag (e.g. "it", "en")
 * @param {{ empty?: string }} [options]
 * @returns {string}
 */
export function formatMonthLabel(monthKey, lang, { empty = "" } = {}) {
	if (monthKey === null || monthKey === undefined || monthKey === "") {
		return empty;
	}

	const [year, month] = monthKey.split("-").map(Number);
	if (!Number.isInteger(year) || !Number.isInteger(month) || month < 1 || month > 12) {
		return String(monthKey);
	}

	return new Date(year, month - 1, 1).toLocaleDateString(lang, {
		month: "long",
		year: "numeric",
	});
}

/**
 * Formats a date as day + long month + year (e.g. "15 giugno 2026" in it).
 * Used for detail pages and descriptive labels.
 *
 * @param {string|Date|null|undefined} value
 * @param {string} lang - BCP 47 language tag (e.g. "it", "en")
 * @param {{ empty?: string }} [options]
 * @returns {string}
 */
export function formatDateLong(value, lang, { empty = "" } = {}) {
	if (value === null || value === undefined || value === "") {
		return empty;
	}

	const date = new Date(value);
	if (Number.isNaN(date.getTime())) {
		return String(value);
	}

	return date.toLocaleDateString(lang, { day: "numeric", month: "long", year: "numeric" });
}
