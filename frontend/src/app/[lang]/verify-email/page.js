"use client";

// import Button from '@/components/Button'
import { useAuth } from "@/hooks/auth";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { useLocale, useUser } from "@/hooks/auth";

const BASE_URL = process.env.NEXT_PUBLIC_BACKEND_URL_CLIENT;

async function fetchCsrf() {
	try {
		const fetchPath = BASE_URL + "/sanctum/csrf-cookie";

		await fetch(fetchPath, {
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
	}
}

const Page = () => {
	const router = useRouter();
	const { user } = useUser();
	const isLoggedIn = user != undefined ? true : false;

	// const { logout, resendEmailVerification } = useAuth({
	//     middleware: 'auth',
	//     redirectIfAuthenticated: '/dashboard',
	// })

	if (!isLoggedIn || user.user.email_verified_at != null) {
		router.push("/404");
	}

	const [status, setStatus] = useState(null);

	const resendEmailVerification = async ({ setStatus }) => {
		setStatus(null);

		const fetchPath = BASE_URL + "/email/verification-notification";
		const xsrfToken = await fetchCsrf();

		const emailVerificationRequest = new Request(fetchPath, {
			method: "POST",
			credentials: "include",
			headers: {
				"Accept": "application/json",
				"X-Requested-With": "XMLHttpRequest",
				"Content-Type": "application/json",
				"X-XSRF-TOKEN": xsrfToken,
			},
		});

		try {
			const emailVerificationResponse = await fetch(emailVerificationRequest);
			const responseData = await emailVerificationResponse.json();
			if (!emailVerificationResponse.ok) {
				setStatus(null);
			} else {
				console.log(responseData);
				setStatus(responseData.status);
			}
		} catch (error) {
			throw error;
		}
	};

	return (
		<>
			<div className="mb-4 text-sm text-gray-600">
				Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we
				just emailed to you? If you didn't receive the email, we will gladly send you another.
			</div>

			{status === "verification-link-sent" && (
				<div className="mb-4 font-medium text-sm text-green-600">
					A new verification link has been sent to the email address you provided during registration.
				</div>
			)}

			<div className="mt-4 flex items-center justify-between">
				<button onClick={() => resendEmailVerification({ setStatus })}>Resend Verification Email</button>

				{/* <button
                    type="button"
                    className="underline text-sm text-gray-600 hover:text-gray-900"
                    onClick={logout}>
                    Logout
                </button> */}
			</div>
		</>
	);
};

export default Page;
