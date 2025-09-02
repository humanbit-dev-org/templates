"use client";

import { useClient } from "@/providers/Client"; // Provide client-only values to the current component {CSR}
import { useTranslate } from "@/providers/Translate"; // Provides translation context and hook access for `lang` and `translates`

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
