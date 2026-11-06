/**
 * Removal of the apiToken cookie, in one place.
 *
 * The backend writes it with the SESSION_DOMAIN attribute (see the cookie built
 * in SamlController), and a cookie set for a domain cannot be removed by a write
 * that omits it: the browser keeps them apart, so the plain
 *
 *   document.cookie = "apiToken=; Path=/; Max-Age=0";
 *
 * that every logout used to run created an already-expired host-only cookie and
 * left the real one untouched. The session survived signing out — from the
 * session-timeout dialog, the navbar, both sidebars, the profile dialog, the
 * password reset and the 401 handler, which all carried their own copy of that
 * line.
 *
 * The domain is not exposed to the browser anywhere, so instead of guessing one
 * the cookie is expired for the current host and for each of its parents. A
 * write aimed at a domain holding no such cookie does nothing, and the browser
 * refuses the ones that would reach a public suffix.
 */
export function clearApiTokenCookie() {
	if (typeof document === "undefined") {
		return;
	}

	const base = "apiToken=; Path=/; Max-Age=0; SameSite=Lax";

	// Host-only: how SimulationControls and the OIDC flow write it.
	document.cookie = base;

	const labels = (window.location.hostname || "").split(".");

	for (let i = 0; i < labels.length - 1; i++) {
		const domain = labels.slice(i).join(".");

		document.cookie = `${base}; Domain=${domain}`;
		document.cookie = `${base}; Domain=.${domain}`;
	}
}
