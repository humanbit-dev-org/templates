"use client"; // marks module for full browser execution
//
// import { UpdateInfoComponent } from "@/components/<filename>"; // File import statement

import { useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import * as constants from "@/config/constants";
import { fetchCsrf } from "@/hooks/fetchCsrf";
import { useClient } from "@/providers/Client";
import { useEventMessage } from "@/providers/EventMessage";
import { useTranslate } from "@/providers/Translate";
// import "./UpdateInfo.scss";

// ===============================================
// ## ############################################
// ===============================================

export function UpdateInfoComponent({ user }) {
	const csr = useClient();

	const lang = useTranslate()["lang"];
	const translates = useTranslate()["translates"]; // E.g., {translates?.[csr.page]?.["<code>"]?.[lang] ?? "Translate fallback"}

	const searchParams = useSearchParams();
	const { showToast } = useEventMessage();

	const [isSubmitting, setIsSubmitting] = useState(false);
	const [username, setUsername] = useState(user?.username);
	const [name, setName] = useState(user?.name);
	const [surname, setSurname] = useState(user?.surname);
	const [address, setAddress] = useState(user?.address);
	const [phone, setPhone] = useState(user?.phone);
	const [userLang, setUserLang] = useState(user?.lang);
	const [sendEmailNotifications, setSendEmailNotifications] = useState(user?.send_email_notifications ?? true);
	const [sendPushNotifications, setSendPushNotifications] = useState(user?.send_push_notifications ?? true);
	const [errors, setErrors] = useState([]);
	const [warning, setWarning] = useState(null);

	useEffect(() => {
		// Show the modal if query parameters are present
		if (searchParams.get("email") && searchParams.get("invite")) {
			setEmail(searchParams.get("email"));
			setInvite(searchParams.get("invite"));
			myModal.show();
		}
	}, [searchParams]);

	useEffect(() => {
		// Initialize Bootstrap tooltips
		if (typeof window !== "undefined" && window.bootstrap) {
			const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
			tooltipTriggerList.forEach((tooltipTriggerEl) => {
				new window.bootstrap.Tooltip(tooltipTriggerEl);
			});
		}
	}, []);

	const submitForm = async (event) => {
		event.preventDefault();

		setIsSubmitting(true);

		setErrors([]);

		const xsrfToken = await fetchCsrf();
		const fetchPath = `${constants.BACKEND_URL_CLIENT}/update-profile`;

		const updateProfileRequest = new Request(fetchPath, {
			method: "POST",
			credentials: "include",
			body: JSON.stringify({
				username: username,
				name: name,
				surname: surname,
				address: address,
				phone: phone,
				lang: userLang,
				send_email_notifications: sendEmailNotifications,
				send_push_notifications: sendPushNotifications,
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
			const updateProfileResponse = await fetch(updateProfileRequest);
			const responseData = await updateProfileResponse.json();
			if (!updateProfileResponse.ok) {
				setErrors(responseData.errors);
				showToast(responseData.message, "error");
			} else {
				showToast(responseData.message, "success");
			}
		} catch (error) {
			throw error;
		} finally {
			setIsSubmitting(false);
		}
	};

	return (
		<div className="update_info_component">
			<form className="block_cont accordion accordion-flush color_white mt-4 mt-lg-0 pe-lg-1" onSubmit={submitForm}>
				{warning && <fieldset className="mb-4 text-red-600">{warning}</fieldset>}

				<fieldset className="block_wrap accordion-item bg_color_gd border border-top-lg-0 border_color_third obj_radius user-select-none">
					<div className="group_cont accordion-header">
						<h3
							className="el_btn accordion-button kodchasan h6 fw-600 text-uppercase bg_color_transparent px-4 px-md-5 py-3 py-md-4 collapsed"
							type="button"
							data-bs-toggle="collapse"
							data-bs-target="#updateInfo">
							{translates?.[csr.page]?.["edit_personal_info"]?.[lang] ?? "Translate fallback"}
						</h3>
					</div>
				</fieldset>

				<div className="block_wrap border-0 collapse" id="updateInfo">
					<div className="group_cont accordion-body bg_color_gd obj_radius row align-items-start mt-4 px-4 px-md-5 py-5">
						<fieldset className="group_wrap row mx-n2 mb-2">
							<h6 className="text-uppercase text-black mb-3 px-2">
								{translates?.[csr.page]?.["your_data"]?.[lang] ?? "Translate fallback"}
							</h6>

							{/* Name */}
							<div className="input_wrapper_spacing col-12 col-xxl-6 mb-3">
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
							<div className="input_wrapper_spacing col-12 col-xxl-6 mb-3">
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
							<div className="input_wrapper_spacing col-12 col-xxl-6 mb-3">
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

							{/* Address */}
							<div className="input_wrapper_spacing col-12 col-xxl-6 mb-3">
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
							<div className="input_wrapper_spacing col-12 col-xxl-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="registerPhone"
										type="text"
										value={phone}
										onChange={(event) => setPhone(event.target.value)}
										required
										autoFocus
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
						</fieldset>

						<fieldset className="group_wrap row mx-n2 mb-2">
							<h6 className="text-uppercase text-black d-flex align-items-center gap-2 mb-3 px-2">
								{translates?.["all"]?.["notifications"]?.[lang] ?? "Translate fallback"}
								{/* Info icon with tooltip */}
								<i
									className="fas fa-info-circle text-primary"
									data-bs-toggle="tooltip"
									data-bs-placement="top"
									data-bs-title={
										translates?.["all"]?.["notifications"]?.[`text_${lang}`] ?? "Translate fallback"
									}
									style={{ cursor: "help", fontSize: "0.875rem" }}></i>
							</h6>

							{/* Language */}
							<div className="input_wrapper_spacing col-12 col-xxl-6 mb-3">
								<div className="form-floating">
									<select
										className="form-select"
										id="registerLang"
										value={userLang}
										onChange={(event) => setUserLang(event.target.value)}
										required>
										<option value="en">English</option>
										<option value="it">Italian</option>
									</select>

									<label className="label" htmlFor="registerLang">
										{translates?.[csr.page]?.["notifications_lang"]?.[lang] ?? "Translate fallback"}
									</label>

									<p>{errors?.lang}</p>
								</div>
							</div>
						</fieldset>

						<fieldset className="group_wrap row mx-n2 mb-2">
							{/* Send Email Notifications */}
							<div className="input_wrapper_spacing col-12 col-lg-6 mb-3">
								<div className="form-check">
									<input
										className="form-check-input"
										type="checkbox"
										id="registerSendEmailNotifications"
										checked={sendEmailNotifications}
										onChange={(event) => setSendEmailNotifications(event.target.checked)}
									/>

									<label className="form-check-label text-black" htmlFor="registerSendEmailNotifications">
										{translates?.[csr.page]?.["allow_email_notifications"]?.[lang] ??
											"Translate fallback"}
									</label>
								</div>
							</div>

							{/* Send Push Notifications */}
							<div className="input_wrapper_spacing col-12 col-lg-6 mb-3">
								<div className="form-check">
									<input
										className="form-check-input"
										type="checkbox"
										id="registerSendPushNotifications"
										checked={sendPushNotifications}
										onChange={(event) => setSendPushNotifications(event.target.checked)}
									/>

									<label className="form-check-label text-black" htmlFor="registerSendPushNotifications">
										{translates?.[csr.page]?.["allow_push_notifications"]?.[lang] ??
											"Translate fallback"}
									</label>
								</div>
							</div>
						</fieldset>

						<fieldset className="group_wrap justify-content-center mx-n2 mt-auto">
							{/* Submit */}
							<div className="input_wrapper_spacing d-flex flex-wrap justify-content-end">
								<button
									className={`el_btn btn_bg_first ${isSubmitting ? "pe-none opacity-50" : ""}`}
									type="submit"
									disabled={isSubmitting}>
									{translates?.["all"]?.["send"]?.[lang] ?? "Translate fallback"}
								</button>
							</div>
						</fieldset>
					</div>
				</div>
			</form>
		</div>
	);
}
