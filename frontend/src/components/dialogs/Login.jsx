"use client";

import { useEffect } from "react";
import { SignInComponent } from "./SignIn";

export default function LoginModal({ isLoggedIn, lang }) {
	useEffect(() => {
		if (!isLoggedIn) {
			const modalElement = document.getElementById("signInModal");
			if (modalElement) {
				const myModal = new window.bootstrap.Modal(modalElement);
				myModal.show();
			}
		}
	}, [isLoggedIn]);

	return <SignInComponent lang={lang} preventClose={true} />;
}
