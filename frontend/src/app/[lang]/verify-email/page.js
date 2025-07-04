import { EmailVerificationComponent } from "@/components/blocks/EmailVerificationComponent";

export default async function VerifyEmailPage({ params }) {
	const { lang } = await params;

	return (
		<div className="cont_space_1 py-5">
			<div className="cont_mw_1">
				<main>
					<h1 className="h2 fw-600 mb-4">Email Verification</h1>
					<EmailVerificationComponent lang={lang} />
				</main>
			</div>
		</div>
	);
}
