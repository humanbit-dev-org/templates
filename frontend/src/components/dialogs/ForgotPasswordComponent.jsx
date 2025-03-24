"use client";

import { useState } from "react";
import AuthSessionStatus from "@/hooks/AuthSessionStatus";
import { useAuth } from "@/hooks/auth";

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

export function ForgotPasswordComponent({ lang }) {
	// const { forgotPassword } = useAuth({
	// 	middleware: 'guest',
	// })

	const [isSubmitting, setIsSubmitting] = useState(false);
	const [email, setEmail] = useState("");
	const [errors, setErrors] = useState([]);
	const [status, setStatus] = useState(null);

	const submitForm = async (event) => {
		event.preventDefault();

		setIsSubmitting(true);

		setErrors([]);
		setStatus(null);

		const xsrfToken = await fetchCsrf();

		const fetchPath = baseUrl + "/forgot-password";

		const forgotPasswordRequest = new Request(fetchPath, {
			method: "POST",
			credentials: "include",
			body: JSON.stringify({
				email: email,
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
			const forgotPasswordResponse = await fetch(forgotPasswordRequest);
			const responseData = await forgotPasswordResponse.json();
			if (!forgotPasswordResponse.ok) {
				setStatus(null);
				setErrors(responseData.errors);
			} else {
				console.log(responseData);
				setStatus(responseData.status);
			}
		} catch (error) {
			throw error;
		} finally {
			setIsSubmitting(false);
		}
	};

	return (
		<>
			<div
				className="modal_full modal fade"
				id="forgotPasswordModal"
				tabIndex="-1"
				aria-labelledby="forgotPasswordModalLabel"
				aria-hidden="true">
				<div className="modal-dialog modal-dialog-centered">
					<form className="modal-content color_white border border_color_third p-3 p-md-5" onSubmit={submitForm}>
						<div className="modal-header mb-5">
							<h5 className="modal-title big" id="forgotPasswordModalLabel">
								Forgot your password?
							</h5>

							<button
								type="button"
								className={`btn-close w-auto h-auto ${isSubmitting ? "pe-none opacity-50" : ""}`}
								data-bs-dismiss="modal"
								aria-label="Close">
								<i className="fa-regular fa-rectangle-xmark fa-2xl color_white" />
							</button>
						</div>

						{/* Session status */}
						<AuthSessionStatus className="mb-4" status={status} />

						<fieldset className="modal-body mx-n2 mb-5">
							<div className="px-2">
								<p className="small mb-2">No problem!</p>

								<p className="small mb-4">
									Just let us know your email address and we will email you a password reset link that will
									allow you to choose a new one.
								</p>
							</div>

							{/* Email */}
							<div className="input_wrapper_spacing mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="ForgotPasswordEmail"
										type="email"
										name="email"
										value={email}
										onChange={(event) => setEmail(event.target.value)}
										required
										autoFocus
										placeholder="Enter email for password reset link"
									/>

									<label className="label" htmlFor="ForgotPasswordEmail">
										Email
									</label>
									<p>{errors.email}</p>
									{/* <InputError messages={errors.email} className="mt-2" /> */}
								</div>
							</div>

							{/* Submit */}
							<div className="input_wrapper_spacing d-flex flex-wrap justify-content-end">
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
								href="#signInModal">
								<div className="fx_fill service pb-2">Already registered?</div>
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
