import Link from "next/link";
import { cookies } from "next/headers";
import * as constants from "@/config/constants";
import { getDictionary } from "@/app/dictionaries";
import { getServer } from "@/lib/server";
import { PasswordChangedMsgComponent } from "@/components/alerts/registration/PasswordChangedMsg";
// import { sanitize } from "isomorphic-dompurify";
import { UpdateInfoComponent } from "@/components/dialogs/UpdateInfo";
import { UpdatePasswordComponent } from "@/components/dialogs/UpdatePassword";
import { VerifiedEmailComponent } from "@/components/dialogs/VerifiedEmail";

import "./page.scss";

export default async function Profile({ params }) {
	const { lang } = await params;
	const ssr = await getServer();
	const user = ssr.user;
	const translates = await getDictionary(lang);

	return (
		<div className="profile_page">
			{ssr.isLoggedIn && (
				<>
					<div className="cont_space_1 py-5 bg_color_gd">
						<div className="cont_mw_1">
							<div className="block_cont d-flex justify-content-between align-items-end">
								<h1 className="el_title h1">
									{translates?.[ssr.page]?.["profile"]?.[lang] ?? "Translate fallback"}
								</h1>

								{user.notVerified ? (
									<Link className="el_link fx_fill" href={`/${lang}/verify-email?request=${user.id}`}>
										{translates?.[ssr.page]?.["verify_account"]?.[lang] ?? "Translate fallback"}
									</Link>
								) : (
									<VerifiedEmailComponent />
								)}
							</div>
						</div>
					</div>
				</>
			)}

			<div className="cont_space_1 pb-5">
				<div className="cont_mw_1">
					<PasswordChangedMsgComponent isLoggedIn={ssr.isLoggedIn} />

					{ssr.isLoggedIn && (
						<>
							<div className="block_cont d-flex flex-wrap justify-content-between mb-6">
								<div className="block_wrap row col-12 col-lg-6">
									<UpdateInfoComponent user={user} />
								</div>

								<div className="block_wrap row col-12 col-lg-6">
									<UpdatePasswordComponent user={user} />
								</div>
							</div>
						</>
					)}
				</div>
			</div>
		</div>
	);
}
