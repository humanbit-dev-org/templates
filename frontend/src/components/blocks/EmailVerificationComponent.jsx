"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { useClient } from "@/providers/Client"; // Provide client-only values to the current component {CSR}
import { fetchCsrf } from "@/hooks/fetchCsrf";
import * as constants from "@/config/constants";

export function EmailVerificationComponent({ lang }) {
	const [status, setStatus] = useState(null);

	const router = useRouter();
	const csr = useClient();
	const user = csr.user;
	const request = csr.queryParams.request;
	const isLoggedIn = csr.isLoggedIn;

	// Check if user is logged in and email is verified
	useEffect(() => {
		if (isLoggedIn) {
			// User is already verified, redirect to profile or home
			router.push(`/${lang}/profile`);
		}
	}, [isLoggedIn, user, lang, router]);

	// Handle redirect for already verified status from API
	useEffect(() => {
		if (status === "already-verified" && isLoggedIn) {
			router.push(`/${lang}/profile`);
		}
	}, [status, lang, router, isLoggedIn]);

	const resendEmailVerification = async () => {
		setStatus(null);

		// Validate that we have a user ID
		if (!request || request === "undefined") {
			setStatus("error");
			console.error("No user ID available for verification");
			return;
		}

		const fetchPath = constants.BACKEND_URL_CLIENT + "/email/verification-notification/" + request;
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
			},
		});

		try {
			const emailVerificationResponse = await fetch(emailVerificationRequest);

			console.log("Response status:", emailVerificationResponse.status);

			if (emailVerificationResponse.status === 429) {
				// Rate limit exceeded
				setStatus("rate-limited");
				console.warn("Rate limit exceeded for email verification");
				return;
			}

			const responseData = await emailVerificationResponse.json();
			console.log("Response data:", responseData);

			if (!emailVerificationResponse.ok) {
				setStatus("error");
				console.error("Error response:", responseData);
			} else {
				setStatus(responseData.status);
			}
		} catch (error) {
			console.error("Error resending verification email:", error);
			setStatus("error");
		}
	};

	return (
		<div className="verify_email_page">
			{!isLoggedIn && (
				<div className="mb-4 text-sm text-gray-600">
					Thanks for signing up! Before getting started, could you verify your email address by clicking on the
					link we just emailed to you? If you didn't receive the email, we will gladly send you another.
				</div>
			)}

			{isLoggedIn && (
				<div className="mb-4 font-medium text-sm text-blue-600">
					You are already logged in. Redirecting to profile...
				</div>
			)}

			{status === "verification-link-sent" && (
				<div className="mb-4 font-medium text-sm text-green-600">
					A new verification link has been sent to the email address you provided during registration.
				</div>
			)}

			{status === "rate-limited" && (
				<div className="mb-4 font-medium text-sm text-orange-600">Please wait 30 minutes before trying again.</div>
			)}

			{status === "error" && (
				<div className="mb-4 font-medium text-sm text-red-600">
					There was an error sending the verification email. Please try again.
				</div>
			)}

			<div className="mt-4 flex items-center justify-between">
				<button
					onClick={resendEmailVerification}
					className="btn_bg_sixth border-0 my-2 w-fit-content"
					disabled={status === "verification-link-sent" || status === "rate-limited"}>
					{status === "verification-link-sent"
						? "Email Sent!"
						: status === "rate-limited"
							? "Rate Limit Reached"
							: "Resend Verification Email"}
				</button>
			</div>
		</div>
	);
}
