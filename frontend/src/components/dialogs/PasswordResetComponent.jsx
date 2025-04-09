"use client";

import AuthSessionStatus from "@/components/elements/AuthSessionStatus";
import { useAuth } from "@/hooks/auth";
import { useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";

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

export function PasswordResetComponent({ lang }) {
	const searchParams = useSearchParams();
	const router = useRouter();

	const [isSubmitting, setIsSubmitting] = useState(false);
	const [email, setEmail] = useState("");
	const [token, setToken] = useState("");
	const [password, setPassword] = useState("");
	const [passwordConfirmation, setPasswordConfirmation] = useState("");
	const [errors, setErrors] = useState([]);
	const [status, setStatus] = useState(null);

	useEffect(() => {
		const modalElement = document.getElementById("passwordResetModal");
		if (modalElement && window.bootstrap) {
			const myModal = new window.bootstrap.Modal(modalElement);

			// Show the modal if query parameters are present
			if (searchParams.get("email") && searchParams.get("token")) {
				setEmail(searchParams.get("email"));
				setToken(searchParams.get("token"));
				myModal.show();
			}
		}
	}, [searchParams]); // Rerun effect when searchParams changes

	const submitForm = async (event) => {
		event.preventDefault();
		setIsSubmitting(true);
		setErrors([]);
		setStatus(null);

		const xsrfToken = await fetchCsrf();

		const fetchPath = BASE_URL + "/reset-password";

		const resetPasswordRequest = new Request(fetchPath, {
			method: "POST",
			credentials: "include",
			body: JSON.stringify({
				email: email,
				password: password,
				password_confirmation: passwordConfirmation,
				token: token,
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
			const resetPasswordResponse = await fetch(resetPasswordRequest);
			const responseData = await resetPasswordResponse.json();
			if (!resetPasswordResponse.ok) {
				setStatus(responseData.status);
				setErrors(responseData.errors);
				setIsSubmitting(false);
			} else {
				router.push("/?reset=" + btoa(responseData.status)), setStatus(responseData.status);
				const fetchPath = BASE_URL + "/login";

				const loginRequest = new Request(fetchPath, {
					method: "POST",
					credentials: "include",
					body: JSON.stringify({
						email: email,
						password: password,
					}),
					headers: {
						"Accept": "application/json",
						"X-Requested-With": "XMLHttpRequest",
						"Content-Type": "application/json",
						"X-XSRF-TOKEN": xsrfToken,
					},
				});

				try {
					const loginResponse = await fetch(loginRequest);
					if (!loginResponse.ok) {
						const errorData = loginResponse.json();
						setErrors(errorData.errors);
						setIsSubmitting(false);
					} else {
						window.location.href = "/";
					}
				} catch (error) {
					setIsSubmitting(false);
					throw error;
				}
			}
		} catch (error) {
			setIsSubmitting(false);
			throw error;
		}

		// resetPassword({
		//     email,
		//     password,
		//     password_confirmation: passwordConfirmation,
		//     setErrors,
		//     setStatus,
		// })
	};

	return (
		<>
			<div
				className="modal_full modal fade"
				id="passwordResetModal"
				tabIndex="-1"
				aria-labelledby="passwordResetModalLabel"
				data-bs-backdrop="static"
				data-bs-keyboard="false"
				aria-hidden="true">
				<div className="modal-dialog modal-dialog-centered">
					<form className="modal-content color_white border border_color_third p-3 p-md-5" onSubmit={submitForm}>
						<div className="modal-header mb-5">
							<h5 className="modal-title big" id="registerModalLabel">
								Password Reset
							</h5>
						</div>

						<AuthSessionStatus className="mb-4" status={status} />

						<fieldset className="modal-body row mx-n2 mb-5">
							{/* Email Address */}
							<input
								id="email"
								type="hidden"
								value={email ? email : ""}
								className="form-control"
								onChange={(event) => setEmail(event.target.value)}
								required
								autoFocus
							/>
							{/* <InputError messages={errors.email} className="mt-2" /> */}

							{/* Password */}
							<div className="input_wrapper_spacing col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										id="password"
										type="password"
										value={password ? password : ""}
										className="form-control"
										onChange={(event) => setPassword(event.target.value)}
										required
									/>

									<label className="label" htmlFor="password">
										Password
									</label>

									<p>{errors?.email}</p>
									<p>{errors?.password}</p>
									{/* <InputError
                                        messages={errors.password}
                                        className="mt-2"
                                    /> */}
								</div>
							</div>

							{/* Confirm Password */}
							<div className="input_wrapper_spacing col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										id="passwordConfirmation"
										type="password"
										value={passwordConfirmation ? passwordConfirmation : ""}
										className="form-control"
										onChange={(event) => setPasswordConfirmation(event.target.value)}
										required
									/>
									<label className="label" htmlFor="passwordConfirmation">
										Confirm Password
									</label>

									<p>{errors?.password_confirmation}</p>
									{/* <InputError
                                        messages={errors.password_confirmation}
                                        className="mt-2"
                                    /> */}
								</div>
							</div>

							<div className="input_wrapper_spacing d-flex flex-wrap justify-content-end">
								<button
									className={`btn_bg_first ${isSubmitting ? "pe-none opacity-50" : ""}`}
									type="submit"
									disabled={isSubmitting}>
									Reset Password
								</button>
							</div>
						</fieldset>
					</form>
				</div>
			</div>
		</>
	);
}
