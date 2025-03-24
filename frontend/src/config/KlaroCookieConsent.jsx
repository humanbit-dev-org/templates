"use client";

import { useEffect } from "react";

export const KlaroCookieConsent = ({ config }) => {
	useEffect(() => {
		if (typeof window !== "undefined" && typeof self !== "undefined") {
			const Klaro = require("klaro");
			Klaro.setup(config); // If Klaro is available globally as window.Klaro
		} else {
			console.error("Klaro is not available");
		}
	}, [config]);

	return null;
};
