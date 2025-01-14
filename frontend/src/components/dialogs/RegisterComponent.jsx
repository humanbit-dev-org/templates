"use client";

import { useAuth } from "hooks/auth";
import { th } from "intl-tel-input/i18n";
import { useRouter, useSearchParams } from "next/navigation";
import { useState, useEffect } from "react";

const baseUrl = process.env.NEXT_PUBLIC_BACKEND_URL;

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

export function RegisterComponent({ lang }) {
	const searchParams = useSearchParams();
	const router = useRouter();

	const [isSubmitting, setIsSubmitting] = useState(false);
	const [username, setUsername] = useState("");
	const [name, setName] = useState("");
	const [surname, setSurname] = useState("");
	const [email, setEmail] = useState("");
	const [invite, setInvite] = useState("no");
	const [address, setAddress] = useState("");
	const [password, setPassword] = useState("");
	const [passwordConfirmation, setPasswordConfirmation] = useState("");
	const [errors, setErrors] = useState([]);
	const [warning, setWarning] = useState(null);

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
		const fetchPath = baseUrl + "/register";

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
			if (!registerResponse.ok) {
				const errorData = await registerResponse.json();
				setErrors(errorData.errors);
				setIsSubmitting(false);
			} else {
				window.location.href = "/verify-email";
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
					<form className="modal-content color_white border border_color_third p-3 p-md-5" onSubmit={submitForm}>
						{warning && <div className="mb-4 text-red-600">{warning}</div>}

						<div className="modal-header mb-5">
							<h5 className="modal-title big" id="registerModalLabel">
								Register
							</h5>

							<button
								type="button"
								className={`btn-close w-auto h-auto ${isSubmitting ? "pe-none opacity-50" : ""}`}
								data-bs-dismiss="modal"
								aria-label="Close">
								<i className="fa-regular fa-rectangle-xmark fa-2xl color_white" />
							</button>
						</div>

						<fieldset className="modal-body row mx-n2 mb-5">
							{/* Name */}
							<div className="input_wrapper_spacing col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="RegisterName"
										type="text"
										value={name}
										onChange={(event) => setName(event.target.value)}
										required
										autoFocus
										placeholder="Enter your name"
									/>

									<label className="label" htmlFor="RegisterName">
										Name
									</label>
									<p>{errors.name}</p>
									{/* <InputError messages={errors.name} className="mt-2" /> */}
								</div>
							</div>

							{/* Surname */}
							<div className="input_wrapper_spacing col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="RegisterSurname"
										type="text"
										value={surname}
										onChange={(event) => setSurname(event.target.value)}
										required
										autoFocus
										placeholder="Enter your surname"
									/>

									<label className="label" htmlFor="RegisterSurname">
										Surname
									</label>
									<p>{errors.surname}</p>
									{/* <InputError messages={errors.surname} className="mt-2" /> */}
								</div>
							</div>

							{/* Username */}
							<div className="input_wrapper_spacing col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="RegisterUsername"
										type="text"
										value={username}
										onChange={(event) => setUsername(event.target.value)}
										required
										autoFocus
										placeholder="Enter your username"
									/>

									<label className="label" htmlFor="RegisterUsername">
										Username
									</label>
									<p>{errors.username}</p>
									{/* <InputError messages={errors.name} className="mt-2" /> */}
								</div>
							</div>

							{/* Email */}
							<div className="input_wrapper_spacing col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className={
											searchParams.get("invite") ? "form-control pe-none opacity-75" : "form-control"
										}
										readOnly={searchParams.get("invite") ? true : false}
										id="RegisterEmail"
										type="email"
										value={email}
										onChange={(event) => setEmail(event.target.value)}
										required
										placeholder="Enter your email"
									/>

									<label className="label" htmlFor="RegisterEmail">
										Email
									</label>
									<p>{errors.email}</p>
									{/* <InputError messages={errors.email} className="mt-2" /> */}
								</div>
							</div>

							{/* Address */}
							<div className="input_wrapper_spacing col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="RegisterAddress"
										type="text"
										value={address}
										onChange={(event) => setAddress(event.target.value)}
										required
										autoFocus
										placeholder="Enter your address"
									/>

									<label className="label" htmlFor="RegisterAddress">
										Address
									</label>
									<p>{errors.address}</p>
									{/* <InputError messages={errors.address} className="mt-2" /> */}
								</div>
							</div>

							{/* Password */}
							<div className="input_wrapper_spacing col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="RegisterPassword"
										type="password"
										value={password}
										onChange={(event) => setPassword(event.target.value)}
										required
										autoComplete="new-password"
										placeholder="Enter your password"
									/>

									<label className="label" htmlFor="RegisterPassword">
										Password
									</label>
									<p>{errors.password}</p>
									{/* <InputError messages={errors.password} className="mt-2" /> */}
								</div>
							</div>

							{/* Confirm password */}
							<div className="input_wrapper_spacing col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="passwordConfirmation"
										type="password"
										value={passwordConfirmation}
										onChange={(event) => setPasswordConfirmation(event.target.value)}
										required
										placeholder="Retype your password"
									/>

									<label className="label" htmlFor="passwordConfirmation">
										Confirm password
									</label>
									<p>{errors.password_confirmation}</p>
									{/* <InputError messages={errors.password_confirmation} className="mt-2" /> */}
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
								href="#forgotPasswordModal">
								<div className="fx_fill service pb-2">Forgot your password?</div>
							</a>

							<a
								className={`el_wrap px-4 ${isSubmitting ? "pe-none opacity-50" : ""}`}
								type="button"
								data-bs-toggle="modal"
								href="#signInModal">
								<div className="fx_fill main pb-2">Already registered?</div>
							</a>
						</div>
					</form>
				</div>
			</div>
		</>
	);
}
