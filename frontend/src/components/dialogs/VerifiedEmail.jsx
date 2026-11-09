"use client"; // marks module for full browser execution
//
// import { <Filename><Suffix> } from "@/<alias>/<Filename>"; // File import statement

import { useClient } from "@/providers/Client";
import { useTranslate } from "@/providers/Translate";

// ===============================================
// ## ############################################
// ===============================================

export function VerifiedEmailComponent() {
	const lang = useTranslate()["lang"];
	const translates = useTranslate()["translates"];
	const csr = useClient();
	const isLoggedIn = csr.isLoggedIn;
	const verified = csr.queryParams.verified;

	return (
		<div className="verified_email_component">
			{verified && verified == 1 && isLoggedIn ? (
				<p className="font-bold mt-5">
					{translates?.[csr.page]?.["email_verified"]?.[lang] ?? "Translate fallback"}
				</p>
			) : null}
		</div>
	);
}
