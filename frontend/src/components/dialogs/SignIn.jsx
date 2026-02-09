"use client";

import { useState } from "react";
import * as constants from "@/config/constants";
import { useTranslate } from "@/providers/Translate"; // Provides translation context and hook access for `lang` and `translates`
import { fetchCsrf } from "@/hooks/fetchCsrf"; // Fetch CSRF Token

export function SignInComponent({ preventClose = false }) {
	const [errors, setErrors] = useState([]);
	const [isSubmitting, setIsSubmitting] = useState(false);
	const [password, setPassword] = useState("");
	const [email, setEmail] = useState("");
	const lang = useTranslate()["lang"];
	const translates = useTranslate()["translates"];

	const submitForm = async (event) => {
		event.preventDefault();

		setIsSubmitting(true);

		//const xsrfToken = await fetchCsrf(); // CSRF Token

		setErrors([]);

		const fetchPath = `${constants.BACKEND_URL_CLIENT}/api/auth`; // API Token Authentication
		//const fetchPath = `${constants.BACKEND_URL_CLIENT}/login`; // Session Cookie Authentication

		const authRequest = new Request(fetchPath, {
			method: "POST",
			// credentials: "include", // Session Cookie Authentication
			body: JSON.stringify({
				email: email,
				password: password,
			}),
			headers: {
				"Accept": "application/json",
				"Content-Type": "application/json",
				//"Referer": constants.APP_URL, // Session Cookie Authentication
				//"X-XSRF-TOKEN": xsrfToken, // CSRF Token (Session Cookie Authentication)
				"X-Locale": lang,
			},
		});

		try {
			const authResponse = await fetch(authRequest);
			if (!authResponse.ok) {
				const errorData = await authResponse.json();
				setErrors(errorData.error);
				setIsSubmitting(false);
			} else {
				const responseData = await authResponse.json();
				document.cookie = `apiToken=${responseData.apiToken}; Path=/; Max-Age=${60 * 60 * 24 * 30}; SameSite=Lax`;
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
							<div className="input_wrap_space mb-3">
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
							<div className="input_wrap_space mb-3">
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
							<div className="input_wrap_space d-flex flex-wrap justify-content-between mb-3">
								<button
									className={`btn_bg_second ${isSubmitting ? "pe-none opacity-50" : ""}`}
									type="submit"
									disabled={isSubmitting}>
									{translates?.["all"]?.["send"]?.[lang] ?? "Translate fallback"}
								</button>
							</div>

							<div className="input_wrap_space d-flex flex-wrap justify-content-between">
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
