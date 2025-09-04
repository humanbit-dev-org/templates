"use client"; // marks module for full browser execution

import Link from "next/link";
import { sanitize } from "isomorphic-dompurify";
import { useClient } from "@/providers/Client";
import { useTranslate } from "@/providers/Translate";

// Component to handle password changed confirmation message
export function PasswordChangedMsgComponent({ isLoggedIn }) {
	const csr = useClient();
	const passwordChanged = csr.queryParams["password-changed"];
	const lang = useTranslate()["lang"];
	const translates = useTranslate()["translates"];

	// Show message only when user is not logged in and password was changed
	if (!isLoggedIn && passwordChanged === "yes") {
		return (
			<div
				dangerouslySetInnerHTML={{
					__html: sanitize(translates?.[csr.page]?.["logged_out"]?.[lang] ?? "Translate fallback"),
				}}
			/>
		);
	}

	// Show default message when user is not logged in but no password change
	if (!isLoggedIn && passwordChanged !== "yes") {
		return (
			<div
				dangerouslySetInnerHTML={{
					__html: sanitize(translates?.[csr.page]?.["not_logged_in"]?.[lang] ?? "Translate fallback"),
				}}></div>
		);
	}

	// Return null if user is logged in
	return null;
}
