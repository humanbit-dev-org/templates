"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import * as constants from "@/config/constants";
import { fetchCsrf } from "@/hooks/fetchCsrf";
import { useClient } from "@/providers/Client"; // Provide client-only values to the current component {CSR}
import { useTranslate } from "@/providers/Translate"; // Provides translation context and hook access for `lang` and `translates`

export function EmailVerificationComponent() {
	const lang = useTranslate()["lang"];
	const translates = useTranslate()["translates"];
	const [status, setStatus] = useState(null);
	const [message, setMessage] = useState(null);

	const router = useRouter();
	const csr = useClient();
	const user = csr.user;
	const request = csr.queryParams.request;
	const isLoggedIn = csr.isLoggedIn;

	// Handle redirect for already verified status from API
	useEffect(() => {
		if (!user?.notVerified && isLoggedIn) {
			router.push(`/${lang}/profile`);
		}
	}, [user?.notVerified, lang, router, isLoggedIn]);

	const resendEmailVerification = async () => {
		setStatus(null);
		setMessage(null);

		if (!request || request === "undefined") {
			setStatus("error");
			setMessage(translates?.[csr.page]?.["no_user_id"]?.[lang] ?? "Translate fallback");
			return;
		}

		const fetchPath = `${constants.BACKEND_URL_CLIENT}/email/verification-notification/${request}`;
		const xsrfToken = await fetchCsrf();

		const emailVerificationRequest = new Request(fetchPath, {
			method: "POST",
			credentials: "include",
			headers: {
				"Accept": "application/json",
				"Referer": constants.APP_URL,
				"X-Requested-With": "XMLHttpRequest",
				"Content-Type": "application/json",
				"X-XSRF-TOKEN": xsrfToken,
				"locale": lang,
			},
		});

		try {
			const emailVerificationResponse = await fetch(emailVerificationRequest);

			if (emailVerificationResponse.status === 429) {
				// Rate limit exceeded
				setStatus("rate-limited");
				setMessage(translates?.[csr.page]?.["rate_limit_reached"]?.[lang] ?? "Translate fallback");
				return;
			}

			const responseData = await emailVerificationResponse.json();
			console.log("Response data:", responseData);

			setStatus(responseData.status);
			setMessage(responseData.message);

			if (!emailVerificationResponse.ok) {
				console.error("Error response:", responseData);
			}
		} catch (error) {
			console.error("Error resending verification email:", error);
			setStatus("error");
			setMessage("There was an error sending the verification email. Please try again.");
		}
	};

	return (
		<div className="verify_email_page">
			{user?.notVerified && !isLoggedIn && (
				<div className="mb-4 text-sm text-gray-600">
					{translates?.[csr.page]?.["email_verification"]?.[`text_${lang}`] ?? "Translate fallback"}
				</div>
			)}

			{user?.notVerified && isLoggedIn && (
				<div className="mb-4 text-sm text-gray-600">
					{translates?.[csr.page]?.["email_verification_logged_in"]?.[`text_${lang}`] ?? "Translate fallback"}
				</div>
			)}

			{isLoggedIn && !user?.notVerified && (
				<div className="mb-4 font-medium text-sm text-blue-600">
					{translates?.[csr.page]?.["redirecting_profile"]?.[lang] ?? "Translate fallback"}
				</div>
			)}

			{status === "verification-link-sent" && (
				<div className="mb-4 font-medium text-sm text-green-600">
					{message ??
						translates?.[csr.page]?.["new_verification_link_sent"]?.[`text_${lang}`] ??
						"Translate fallback"}
				</div>
			)}

			{status === "already-verified" && (
				<div className="mb-4 font-medium text-sm text-blue-600">{message ?? "Email already verified"}</div>
			)}

			{status === "rate-limited" && (
				<div className="mb-4 font-medium text-sm text-orange-600">
					{message ?? "Please wait 30 minutes before trying again."}
				</div>
			)}

			{status === "error" && (
				<div className="mb-4 font-medium text-sm text-red-600">
					{message ?? "There was an error sending the verification email. Please try again."}
				</div>
			)}

			<div className="mt-4 flex items-center justify-between">
				<button
					onClick={resendEmailVerification}
					className="btn_bg_sixth border-0 my-2 w-fit-content"
					disabled={
						status === "verification-link-sent" || status === "rate-limited" || status === "already-verified"
					}>
					{translates?.[csr.page]?.["resend_verification_email"]?.[lang] ?? "Translate fallback"}
				</button>
			</div>
		</div>
	);
}
