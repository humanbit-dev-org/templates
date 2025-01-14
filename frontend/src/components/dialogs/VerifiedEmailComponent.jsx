"use client";

import { useSearchParams } from "next/navigation";
import { useLocale } from "components/utilities/AuthHelper";

export function VerifiedEmailComponent() {
	const { lang, dict } = useLocale();
	const searchParams = useSearchParams();
	const verified = searchParams.get("verified");

	return (
		<div className="components_partial">
			<div className="container_humanbit_1 py-5">
				<div className="container_max_width_1">
					<main>
						{verified && verified == 1 ? (
							<div className="text-center">
								<p className="font-bold mt-5">{dict.email_verified}</p>
							</div>
						) : null}
					</main>
				</div>
			</div>
		</div>
	);
}
