"use client"; // marks module for full browser execution
//
// import { <Filename><Suffix> } from "@/<alias>/<Filename>"; // File import statement

import { useEffect } from "react";

import { SignInComponent } from "@/components/dialogs/SignIn";

// ===============================================
// ## ############################################
// ===============================================

export function LoginModal({ isLoggedIn, lang }) {
	useEffect(() => {
		if (!isLoggedIn) {
			const modalElement = document.getElementById("signInModal");
			if (modalElement) {
				const myModal = new window.bootstrap.Modal(modalElement);
				myModal.show();
			}
		}
	}, [isLoggedIn]);

	return (
		<SignInComponent
			lang={lang}
			preventClose={true}
		/>
	);
}
