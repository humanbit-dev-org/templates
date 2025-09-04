"use client";

import { useState } from "react";
import * as constants from "@/config/constants";
import { fetchCsrf } from "@/hooks/fetchCsrf";
import { useTranslate } from "@/providers/Translate"; // Provides translation context and hook access for `lang` and `translates`

export function SignInComponent({ preventClose = false }) {
	const [errors, setErrors] = useState([]);
	const [isSubmitting, setIsSubmitting] = useState(false);
	const [password, setPassword] = useState("");
	const [shouldRemember, setShouldRemember] = useState(false);
	const [email, setEmail] = useState("");
	const lang = useTranslate()["lang"];
	const translates = useTranslate()["translates"];

	const submitForm = async (event) => {
		event.preventDefault();

		setIsSubmitting(true);

		setErrors([]);

		const xsrfToken = await fetchCsrf();

		const fetchPath = `${constants.BACKEND_URL_CLIENT}/login`;

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
				"Referer": constants.APP_URL,
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
	};

	return (
		<>
			<div
				className="modal_full modal fade"
				id="signInModal"
				tabIndex="-1"
				aria-labelledby="signInModalLabel"
				aria-hidden="true"
				{...(preventClose && {
					"data-bs-backdrop": "static",
					"data-bs-keyboard": "false",
				})}>
				<div className="modal-dialog modal-dialog-centered">
					<form className="modal-content color_first p-3 p-md-5" onSubmit={submitForm}>
						<div className="modal-header mb-5">
							<h5 className="modal-title big" id="signInModalLabel">
								{translates?.["all"]?.["sign_in"]?.[lang] ?? "Translate fallback"}
							</h5>

							<button
								type="button"
								className={`btn-close w-auto h-auto ${isSubmitting ? "pe-none opacity-50" : ""} `}
								data-bs-dismiss="modal"
								aria-label="Close">
								<i className="fa-regular fa-rectangle-xmark fa-2xl color_first" />
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
										placeholder={
											translates?.["all"]?.["email"]?.[`text_${lang}`] ?? "Translate fallback"
										}
									/>

									<label className="label" htmlFor="signInEmail">
										{translates?.["all"]?.["email"]?.[lang] ?? "Translate fallback"}
									</label>

									<p>{errors?.email}</p>

									{/* <InputError messages={errors?.email} className="mt-2" /> */}
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
										placeholder={
											translates?.["all"]?.["password"]?.[`text_${lang}`] ?? "Translate fallback"
										}
									/>

									<label className="label" htmlFor="signInPassword">
										{translates?.["all"]?.["password"]?.[lang] ?? "Translate fallback"}
									</label>

									{/* <InputError messages={errors?.password} className="mt-2" /> */}

									<p>{errors?.password}</p>
								</div>
							</div>

							{/* Remember me */}
							<div className="input_wrapper_spacing d-flex flex-wrap justify-content-between mb-3">
								<div className="form-check">
									<label className="form-check-label color_first" htmlFor="signInRememberMe">
										{translates?.["all"]?.["remember_me"]?.[lang] ?? "Translate fallback"}
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
									className={`btn_bg_second ${isSubmitting ? "pe-none opacity-50" : ""}`}
									type="submit"
									disabled={isSubmitting}>
									{translates?.["all"]?.["send"]?.[lang] ?? "Translate fallback"}
								</button>
							</div>

							<div className="input_wrapper_spacing d-flex flex-wrap justify-content-between">
								<button
									className={`btn_bg_third flex-fill ${isSubmitting ? "pe-none opacity-50" : ""}`}
									type="submit"
									disabled={isSubmitting}>
									<span className="el_txt pe-2">Facebook</span>
									<i className="fa-brands fa-facebook-f" />
								</button>

								<button
									className={`btn_bg_third flex-fill ${isSubmitting ? "pe-none opacity-50" : ""}`}
									type="submit"
									disabled={isSubmitting}>
									<span className="el_txt pe-2">Apple</span>
									<i className="fa-brands fa-apple" />
								</button>

								<button
									className={`btn_bg_third flex-fill ${isSubmitting ? "pe-none opacity-50" : ""}`}
									type="submit"
									disabled={isSubmitting}>
									<span className="el_txt pe-2">Google</span>
									<i className="fa-brands fa-google" />
								</button>
							</div>
						</fieldset>

						<div className="modal-footer justify-content-center mx-n4">
							<a
								className={`el_wrap px-4 ${isSubmitting ? "pe-none opacity-50" : ""}`}
								type="button"
								data-bs-toggle="modal"
								href="#forgotPasswordModal">
								<span className="fx_fill service pb-2">
									{translates?.["all"]?.["forgot_your_password"]?.[lang] ?? "Translate fallback"}
								</span>
							</a>

							<a
								className={`el_wrap px-4 ${isSubmitting ? "pe-none opacity-50" : ""}`}
								type="button"
								data-bs-toggle="modal"
								href="#registerModal">
								<span className="fx_fill main pb-2">
									{translates?.["all"]?.["dont_have_an_account"]?.[lang] ?? "Translate fallback"}
								</span>
							</a>
						</div>
					</form>
				</div>
			</div>
		</>
	);
}
