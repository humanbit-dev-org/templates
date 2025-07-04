"use client";

import * as constants from "@/config/constants"; // Global constants shared across the app

export async function fetchCsrf() {
	try {
		await fetch(`${constants.BACKEND_URL_CLIENT}/sanctum/csrf-cookie`, {
			method: "GET",
			credentials: "include",
		});
		const xsrfToken = document.cookie
			.split("; ")
			.find((row) => row.startsWith("XSRF-TOKEN"))
			?.split("=")[1];
		return decodeURIComponent(xsrfToken);
	} catch (error) {
		console.error("Error fetching csrf:", error);
		return null;
	}
}
