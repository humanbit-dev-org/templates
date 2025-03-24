"use client";

import { useAuth } from "hooks/auth";
import { use, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import AuthSessionStatus from "hooks/AuthSessionStatus";

const baseUrl = process.env.NEXT_PUBLIC_ADMIN_URL;

async function fetchCsrf() {
	try {
		const fetchPath = baseUrl + "/sanctum/csrf-cookie";
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

export function SignInComponent({ lang }) {
	const [errors, setErrors] = useState([]);
	const router = useRouter();

	useEffect(() => {
		if (typeof window !== "undefined") {
			if (router.reset?.length > 0 && errors.length === 0) {
				setStatus(atob(router.reset));
			} else {
				setStatus(null);
			}
		}
	});

	// const redirectIfAuthenticated = "/";

	// const { login } = useAuth({
	// 	middleware: "guest",
	// });

	const [isSubmitting, setIsSubmitting] = useState(false);
	const [password, setPassword] = useState("");
	const [shouldRemember, setShouldRemember] = useState(false);
	const [email, setEmail] = useState("");
	const [status, setStatus] = useState(null);

	const submitForm = async (event) => {
		event.preventDefault();

		setIsSubmitting(true);

		setErrors([]);
		setStatus(null);

		const xsrfToken = await fetchCsrf();

		const fetchPath = baseUrl + "/login";

		const loginRequest = new Request(fetchPath, {
			method: "POST",
			credentials: "include",
			body: JSON.stringify({
				email: email,
				password: password,
				remember: shouldRemember,
			}),
			headers: {
				"Accept": "application/json",
				"X-Requested-With": "XMLHttpRequest",
				"Content-Type": "application/json",
				"X-XSRF-TOKEN": xsrfToken,
				"locale": lang,
			},
		});

		try {
			const loginResponse = await fetch(loginRequest);
			if (!loginResponse.ok) {
				const errorData = await loginResponse.json();
				setErrors(errorData.errors);
				setIsSubmitting(false);
			} else {
				window.location.reload();
			}
		} catch (error) {
			setIsSubmitting(false);
			throw error;
		}

		// login({
		// 	email,
		// 	password,
		// 	remember: shouldRemember,
		// 	setErrors,
		// 	setStatus,
		// });
	};

	return (
		<>
			<div
				className="modal_full modal fade"
				id="signInModal"
				tabIndex="-1"
				aria-labelledby="signInModalLabel"
				aria-hidden="true">
				<div className="modal-dialog modal-dialog-centered">
					<form className="modal-content color_white border border_color_third p-3 p-md-5" onSubmit={submitForm}>
						<div className="modal-header mb-5">
							<h5 className="modal-title big" id="signInModalLabel">
								Sign in
							</h5>

							{/* Session status */}
							<AuthSessionStatus className="mb-4" status={status} />

							<button
								type="button"
								className={`btn-close w-auto h-auto ${isSubmitting ? "pe-none opacity-50" : ""} `}
								data-bs-dismiss="modal"
								aria-label="Close">
								<i className="fa-regular fa-rectangle-xmark fa-2xl color_white" />
							</button>
						</div>

						<fieldset className="modal-body mx-n2 mb-5">
							{/* Email */}
							<div className="input_wrapper_spacing mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="signInEmail"
										type="email"
										value={email}
										onChange={(event) => setEmail(event.target.value)}
										required
										autoFocus
										placeholder="Enter your email"
									/>

									<label className="label" htmlFor="signInEmail">
										Email
									</label>
									<p>{errors.email}</p>
									{/* <InputError messages={errors.email} className="mt-2" /> */}
								</div>
							</div>

							{/* Password */}
							<div className="input_wrapper_spacing mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="signInPassword"
										type="password"
										value={password}
										onChange={(event) => setPassword(event.target.value)}
										required
										autoComplete="current-password"
										placeholder="Enter your password"
									/>

									<label className="label" htmlFor="signInPassword">
										Password
									</label>

									{/* <InputError messages={errors.password} className="mt-2" /> */}
									<p>{errors.password}</p>
								</div>
							</div>

							{/* Remember me */}
							<div className="input_wrapper_spacing d-flex flex-wrap justify-content-between">
								<div className="form-check">
									<label className="form-check-label" htmlFor="signInRememberMe">
										Remember me
									</label>

									<input
										className="form-check-input"
										id="signInRememberMe"
										type="checkbox"
										name="remember"
										onChange={(event) => setShouldRemember(event.target.checked)}
									/>
								</div>

								<button
									className={`btn_bg_first ${isSubmitting ? "pe-none opacity-50" : ""}`}
									type="submit"
									disabled={isSubmitting}>
									Submit
								</button>
							</div>
						</fieldset>

						<div className="modal-footer justify-content-center mx-n4">
							<a
								className={`el_wrap px-4 ${isSubmitting ? "pe-none opacity-50" : ""}`}
								type="button"
								data-bs-toggle="modal"
								href="#forgotPasswordModal">
								<div className="fx_fill service pb-2">Forgot your password?</div>
							</a>

							<a
								className={`el_wrap px-4 ${isSubmitting ? "pe-none opacity-50" : ""}`}
								type="button"
								data-bs-toggle="modal"
								href="#registerModal">
								<div className="fx_fill main pb-2">Don't have an account?</div>
							</a>
						</div>
					</form>
				</div>
			</div>
		</>
	);
}
