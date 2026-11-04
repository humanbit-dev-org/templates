/**
 * Converts Italian decimal strings from the API (e.g. "10,50") to the dot format
 * required by HTML <input type="number"> and Laravel numeric validation.
 */
export function normalizeDecimalForInput(value) {
	const raw = String(value ?? "");
	if (!raw) {
		return "";
	}

	let normalized = raw.trim().replace(/\s/g, "").replace(/'/g, "");
	if (normalized.includes(".") && normalized.includes(",")) {
		normalized = normalized.replace(/\./g, "").replace(",", ".");
	} else if (normalized.includes(",")) {
		normalized = normalized.replace(",", ".");
	}

	return normalized;
}

const itGroupedAmountFormatter = new Intl.NumberFormat("it-IT", {
	minimumGroupingDigits: 1,
	useGrouping: true,
	maximumFractionDigits: 2,
});

/** Parses API/plain amounts, including Italian thousands (e.g. "1.000" -> 1000). */
export function parseAmountValue(value) {
	if (value === null || value === undefined || value === "") {
		return NaN;
	}

	if (typeof value === "number") {
		return value;
	}

	const raw = String(value).trim().replace(/\s/g, "");
	if (!raw) {
		return NaN;
	}

	// Italian thousands only: 1.000, 50.000, 1.250.000
	if (/^-?\d{1,3}(\.\d{3})+$/.test(raw)) {
		return Number(raw.replace(/\./g, ""));
	}

	return Number(normalizeDecimalForInput(raw));
}

/** Grouped amount without currency symbol (e.g. 1000 -> "1.000"). */
export function formatGroupedAmountIt(value) {
	if (value === null || value === undefined || value === "") {
		return "";
	}

	const numericValue = parseAmountValue(value);
	if (Number.isNaN(numericValue)) {
		return String(value);
	}

	return itGroupedAmountFormatter.format(numericValue);
}

/** Currency display with dot thousands from 1.000 upward (it-IT). */
export function formatCurrencyIt(value) {
	if (value === null || value === undefined || value === "") {
		return "";
	}

	const numericValue = parseAmountValue(value);
	if (Number.isNaN(numericValue)) {
		return String(value);
	}

	return `\u20AC\u202F${itGroupedAmountFormatter.format(numericValue)}`;
}

/** Percentage stat label (keeps BE comma decimals, adds % if missing). */
export function formatPercentageStat(value) {
	if (value === null || value === undefined || value === "") {
		return "";
	}

	const raw = String(value).trim();
	if (!raw) {
		return "";
	}

	if (raw.endsWith("%")) {
		return raw;
	}

	if (raw.includes(",")) {
		return `${raw}%`;
	}

	// Dot-decimal rates ("5.500") are decimals, not Italian thousands: show the
	// comma. Grouped values like "1.250.000" fail the single-dot regex and keep
	// the thousands interpretation below.
	if (/^-?\d+\.\d+$/.test(raw)) {
		return `${raw.replace(".", ",")}%`;
	}

	const numericValue = parseAmountValue(value);
	if (Number.isNaN(numericValue)) {
		return raw;
	}

	return `${numericValue.toLocaleString("it-IT", {
		minimumGroupingDigits: 1,
		useGrouping: true,
		minimumFractionDigits: 0,
		maximumFractionDigits: 3,
	})}%`;
}

/**
 * Normalises a rate for Laravel `decimal:2,3` validation.
 * Converts Italian comma format, then pads/truncates to exactly 3 decimal places.
 */
export function formatDecimalForPatch(value, fractionDigits = 3) {
	const normalized = normalizeDecimalForInput(value);
	if (normalized === "") {
		return "";
	}
	const numericValue = Number(normalized);
	if (Number.isNaN(numericValue)) {
		return normalized; // let backend reject invalid input
	}
	return numericValue.toFixed(fractionDigits);
}

/** Formats read-only stat fields (`statFormat`: currency | grouped | percentage). */
export function formatStatValue(value, statFormat) {
	if (value === null || value === undefined || value === "") {
		return "";
	}

	switch (statFormat) {
		case "currency":
			return formatCurrencyIt(value);
		case "grouped":
			return formatGroupedAmountIt(value);
		case "percentage":
			return formatPercentageStat(value);
		default:
			return String(value);
	}
}
