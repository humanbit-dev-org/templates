"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useState, useEffect } from "react";
import * as constants from "@/config/constants"; // Global constants shared across the app
import { useClient } from "@/providers/Client"; // Provide client-only values to the current component {CSR}
import { useTranslate } from "@/providers/Translate"; // Provides translation context and hook access for `lang` and `translates`
import { fetchCsrf } from "@/hooks/fetchCsrf";

export function RegisterComponent({}) {
	const [isSubmitting, setIsSubmitting] = useState(false);
	const [username, setUsername] = useState("");
	const [name, setName] = useState("");
	const [surname, setSurname] = useState("");
	const [email, setEmail] = useState("");
	const [invite, setInvite] = useState("no");
	const [address, setAddress] = useState("");
	const [phone, setPhone] = useState("");
	const [password, setPassword] = useState("");
	const [passwordConfirmation, setPasswordConfirmation] = useState("");
	const [errors, setErrors] = useState([]);
	const [warning, setWarning] = useState(null);
	const lang = useTranslate()["lang"];
	const translates = useTranslate()["translates"];

	const searchParams = useSearchParams();

	useEffect(() => {
		const modalElement = document.getElementById("registerModal");
		if (modalElement && window.bootstrap) {
			const myModal = new window.bootstrap.Modal(modalElement);

			// Show the modal if query parameters are present
			if (searchParams.get("email") && searchParams.get("invite")) {
				setEmail(searchParams.get("email"));
				setInvite(searchParams.get("invite"));
				myModal.show();
			}
		}
	}, [searchParams]);

	const submitForm = async (event) => {
		event.preventDefault();
		setIsSubmitting(true);

		setErrors([]);

		const xsrfToken = await fetchCsrf();
		const fetchPath = `${constants.BACKEND_URL_CLIENT}/register`;

		const registerRequest = new Request(fetchPath, {
			method: "POST",
			credentials: "include",
			body: JSON.stringify({
				username: username,
				name: name,
				surname: surname,
				email: email,
				invite: invite,
				address: address,
				phone: phone,
				lang: lang,
				password: password,
				password_confirmation: passwordConfirmation,
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
			const registerResponse = await fetch(registerRequest);
			const registerResponseData = await registerResponse.json();
			if (!registerResponse.ok) {
				setErrors(registerResponseData.errors);
				setIsSubmitting(false);
			} else {
				window.location.href = `/${lang}/verify-email/?request=${registerResponseData.id}`;
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
				id="registerModal"
				tabIndex="-1"
				aria-labelledby="registerModalLabel"
				aria-hidden="true">
				<div className="modal-dialog modal-dialog-centered">
					<form className="modal-content color_first border border_color_third p-3 p-md-5" onSubmit={submitForm}>
						{warning && <div className="mb-4 text-red-600">{warning}</div>}

						<div className="modal-header mb-5">
							<h5 className="modal-title big" id="registerModalLabel">
								{translates?.["all"]?.["sign_up"]?.[lang] ?? "Translate fallback"}
							</h5>

							<button
								type="button"
								className={`btn-close w-auto h-auto ${isSubmitting ? "pe-none opacity-50" : ""}`}
								data-bs-dismiss="modal"
								aria-label="Close">
								<i className="fa-regular fa-rectangle-xmark fa-2xl color_first" />
							</button>
						</div>

						<fieldset className="modal-body row mx-n2 mb-5">
							{/* Name */}
							<div className="input_wrap_space col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="registerName"
										type="text"
										value={name}
										onChange={(event) => setName(event.target.value)}
										required
										autoFocus
										placeholder={translates?.["all"]?.["name"]?.[`text_${lang}`] ?? "Translate fallback"}
									/>

									<label className="label" htmlFor="registerName">
										{translates?.["all"]?.["name"]?.[lang] ?? "Translate fallback"}
									</label>

									<p>{errors?.name}</p>

									{/* <InputError messages={errors?.name} className="mt-2" /> */}
								</div>
							</div>

							{/* Surname */}
							<div className="input_wrap_space col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="registerSurname"
										type="text"
										value={surname}
										onChange={(event) => setSurname(event.target.value)}
										required
										autoFocus
										placeholder={
											translates?.["all"]?.["surname"]?.[`text_${lang}`] ?? "Translate fallback"
										}
									/>

									<label className="label" htmlFor="registerSurname">
										{translates?.["all"]?.["surname"]?.[lang] ?? "Translate fallback"}
									</label>

									<p>{errors?.surname}</p>

									{/* <InputError messages={errors?.surname} className="mt-2" /> */}
								</div>
							</div>

							{/* Username */}
							<div className="input_wrap_space col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="registerUsername"
										type="text"
										value={username}
										onChange={(event) => setUsername(event.target.value)}
										required
										autoFocus
										placeholder={
											translates?.["all"]?.["username"]?.[`text_${lang}`] ?? "Translate fallback"
										}
									/>

									<label className="label" htmlFor="registerUsername">
										{translates?.["all"]?.["username"]?.[lang] ?? "Translate fallback"}
									</label>

									<p>{errors?.username}</p>

									{/* <InputError messages={errors?.name} className="mt-2" /> */}
								</div>
							</div>

							{/* Email */}
							<div className="input_wrap_space col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className={
											searchParams.get("invite") ? "form-control pe-none opacity-75" : "form-control"
										}
										readOnly={searchParams.get("invite") ? true : false}
										id="registerEmail"
										type="email"
										value={email}
										onChange={(event) => setEmail(event.target.value)}
										required
										placeholder={
											translates?.[`all`]?.["email"]?.[`text_${lang}`] ?? "Translate fallback"
										}
									/>

									<label className="label" htmlFor="registerEmail">
										{translates?.[`all`]?.["email"]?.[lang] ?? "Translate fallback"}
									</label>

									<p>{errors?.email}</p>

									{/* <InputError messages={errors?.email} className="mt-2" /> */}
								</div>
							</div>

							{/* Address */}
							<div className="input_wrap_space col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="registerAddress"
										type="text"
										value={address}
										onChange={(event) => setAddress(event.target.value)}
										required
										autoFocus
										placeholder={
											translates?.["all"]?.["address"]?.[`text_${lang}`] ?? "Translate fallback"
										}
									/>

									<label className="label" htmlFor="registerAddress">
										{translates?.["all"]?.["address"]?.[lang] ?? "Translate fallback"}
									</label>

									<p>{errors?.address}</p>

									{/* <InputError messages={errors?.address} className="mt-2" /> */}
								</div>
							</div>

							{/* Phone */}

							<div className="input_wrap_space col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="registerPhone"
										type="text"
										value={phone}
										onChange={(event) => setPhone(event.target.value)}
										required
										placeholder={
											translates?.["all"]?.["phone"]?.[`text_${lang}`] ?? "Translate fallback"
										}
									/>

									<label className="label" htmlFor="registerPhone">
										{translates?.["all"]?.["phone"]?.[lang] ?? "Translate fallback"}
									</label>

									<p>{errors?.phone}</p>

									{/* <InputError messages={errors?.phone} className="mt-2" /> */}
								</div>
							</div>

							{/* Password */}
							<div className="input_wrap_space col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="registerPassword"
										type="password"
										value={password}
										onChange={(event) => setPassword(event.target.value)}
										required
										autoComplete="new-password"
										placeholder={
											translates?.["all"]?.["password"]?.[`text_${lang}`] ?? "Translate fallback"
										}
									/>

									<label className="label" htmlFor="registerPassword">
										{translates?.["all"]?.["password"]?.[lang] ?? "Translate fallback"}
									</label>

									<p>{errors?.password}</p>

									{/* <InputError messages={errors?.password} className="mt-2" /> */}
								</div>
							</div>

							{/* Confirm password */}
							<div className="input_wrap_space col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="registerPasswordConfirmation"
										type="password"
										value={passwordConfirmation}
										onChange={(event) => setPasswordConfirmation(event.target.value)}
										required
										placeholder={
											translates?.["all"]?.["confirm_password"]?.[`text_${lang}`] ??
											"Translate fallback"
										}
									/>

									<label className="label" htmlFor="registerPasswordConfirmation">
										{translates?.["all"]?.["confirm_password"]?.[lang] ?? "Translate fallback"}
									</label>

									<p>{errors?.password_confirmation}</p>

									{/* <InputError messages={errors?.password_confirmation} className="mt-2" /> */}
								</div>
							</div>

							{/* Submit */}
							<div className="input_wrap_space d-flex flex-wrap justify-content-end">
								<button
									className={`btn_bg_second ${isSubmitting ? "pe-none opacity-50" : ""}`}
									type="submit"
									disabled={isSubmitting}>
									{translates?.["all"]?.["send"]?.[lang] ?? "Translate fallback"}
								</button>
							</div>
						</fieldset>

						<div className="modal-footer justify-content-center mx-n4">
							<a
								className={`el_wrap px-4 ${isSubmitting ? "pe-none opacity-50" : ""}`}
								type="button"
								data-bs-toggle="modal"
								href="#forgotPasswordModal">
								<div className="fx_fill service pb-2">
									{translates?.["all"]?.["forgot_your_password"]?.[lang] ?? "Translate fallback"}
								</div>
							</a>

							<a
								className={`el_wrap px-4 ${isSubmitting ? "pe-none opacity-50" : ""}`}
								type="button"
								data-bs-toggle="modal"
								href="#signInModal">
								<div className="fx_fill main pb-2">
									{translates?.["all"]?.["already_registered"]?.[lang] ?? "Translate fallback"}
								</div>
							</a>
						</div>
					</form>
				</div>
			</div>
		</>
	);
}
