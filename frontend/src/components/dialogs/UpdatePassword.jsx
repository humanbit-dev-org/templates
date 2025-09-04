"use client"; // marks module for full browser execution
//
// import { UpdatePasswordComponent } from "@/components/<filename>"; // File import statement

import { useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { fetchCsrf } from "@/hooks/fetchCsrf";
import { useTranslate } from "@/providers/Translate";
import { useEventMessage } from "@/providers/EventMessage";
import * as constants from "@/config/constants";
import { useClient } from "@/providers/Client";
// import "./UpdatePasswordComponent.scss";

// ===============================================
// ## ############################################
// ===============================================

export function UpdatePasswordComponent({ user }) {
	const csr = useClient();

	const lang = useTranslate()["lang"];
	const translates = useTranslate()["translates"]; // E.g., {translates?.[csr.page]?.["<code>"]?.[lang] ?? "Translate fallback"}

	const searchParams = useSearchParams();
	const router = useRouter();
	const { showToast } = useEventMessage();

	const [isSubmitting, setIsSubmitting] = useState(false);
	const [currentPassword, setCurrentPassword] = useState("");
	const [newPassword, setNewPassword] = useState("");
	const [newPasswordConfirmation, setNewPasswordConfirmation] = useState("");
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

	const submitForm = async (event) => {
		event.preventDefault();
		setIsSubmitting(true);

		setErrors([]);

		const xsrfToken = await fetchCsrf();
		const fetchPath = `${constants.BACKEND_URL_CLIENT}/update-password`;

		const updateProfileRequest = new Request(fetchPath, {
			method: "POST",
			credentials: "include",
			body: JSON.stringify({
				current_password: currentPassword,
				new_password: newPassword,
				new_password_confirmation: newPasswordConfirmation,
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
				setIsSubmitting(false);
			} else {
				showToast(responseData.message, "success");
				router.push(`/${lang}/profile?password-changed=yes`);
			}
		} catch (error) {
			setIsSubmitting(false);
			throw error;
		}
	};

	return (
		<div className="update_password_component">
			<form className="block_cont accordion accordion-flush color_white mt-4 mt-lg-0 ps-lg-1" onSubmit={submitForm}>
				{warning && <fieldset className="mb-4 text-red-600">{warning}</fieldset>}

				<fieldset className="block_wrap accordion-item bg_color_gd border border-top-lg-0 border_color_third obj_radius user-select-none">
					<div className="group_cont accordion-header">
						<h3
							className="el_btn accordion-button kodchasan h6 fw-600 text-uppercase bg_color_transparent px-4 px-md-5 py-3 py-md-4 collapsed"
							type="button"
							data-bs-toggle="collapse"
							data-bs-target="#updatePassword">
							{translates?.[csr.page]?.["change_password"]?.[lang] ?? "Translate fallback"}
						</h3>
					</div>
				</fieldset>

				<div className="block_wrap border-0 collapse" id="updatePassword">
					<div className="group_cont accordion-body bg_color_gd obj_radius row align-items-start mt-4 px-4 px-md-5 py-5">
						<fieldset className="group_wrap row mx-n2 mb-2">
							<h6 className="text-uppercase text-black mb-3 px-2">
								Change your password
								{/* {translates?.[csr.page]?.["your_data"]?.[lang] ?? "Translate fallback"} */}
							</h6>

							{/* Current password */}
							<div className="input_wrapper_spacing col-12 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="currentPassword"
										type="password"
										value={currentPassword}
										onChange={(event) => setCurrentPassword(event.target.value)}
										required
										autoFocus
										placeholder={
											translates?.[csr.page]?.["current_password"]?.[`text_${lang}`] ??
											"Translate fallback"
										}
									/>

									<label className="label" htmlFor="currentPassword">
										{translates?.[csr.page]?.["current_password"]?.[lang] ?? "Translate fallback"}
									</label>

									<p>{errors?.current_password}</p>

									{/* <InputError messages={errors?.name} className="mt-2" /> */}
								</div>
							</div>

							{/* New password */}
							<div className="input_wrapper_spacing col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="newPassword"
										type="password"
										value={newPassword}
										onChange={(event) => setNewPassword(event.target.value)}
										required
										autoFocus
										placeholder={
											translates?.[csr.page]?.["new_password"]?.[`text_${lang}`] ??
											"Translate fallback"
										}
									/>

									<label className="label" htmlFor="newPassword">
										{translates?.[csr.page]?.["new_password"]?.[lang] ?? "Translate fallback"}
									</label>

									<p>{errors?.new_password}</p>

									{/* <InputError messages={errors?.surname} className="mt-2" /> */}
								</div>
							</div>

							{/* New password confirmation */}
							<div className="input_wrapper_spacing col-12 col-lg-6 mb-3">
								<div className="form-floating">
									<input
										className="form-control"
										id="newPasswordConfirmation"
										type="password"
										value={newPasswordConfirmation}
										onChange={(event) => setNewPasswordConfirmation(event.target.value)}
										required
										autoFocus
										placeholder={
											translates?.[csr.page]?.["new_password_confirmation"]?.[`text_${lang}`] ??
											"Translate fallback"
										}
									/>

									<label className="label" htmlFor="newPasswordConfirmation">
										{translates?.[csr.page]?.["new_password_confirmation"]?.[lang] ??
											"Translate fallback"}
									</label>

									<p>{errors?.new_password_confirmation}</p>

									{/* <InputError messages={errors?.name} className="mt-2" /> */}
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
