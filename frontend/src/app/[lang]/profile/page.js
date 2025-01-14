import { VerifiedEmailComponent } from "components/dialogs/VerifiedEmailComponent";
import { cookies } from "next/headers";

const baseUrl = process.env.NEXT_PUBLIC_BACKEND_URL_SERVER;

async function fetchInvites() {
	try {
		const cookiesStore = await cookies();
		const laravelSession = cookiesStore.get("laravel_session")?.value;
		const invitesResponse = await fetch(`${baseUrl}/api/invites`, {
			method: "GET",
			credentials: "include",
			headers: {
				"Accept": "application/json",
				"Referer": process.env.APP_URL,
				"X-Requested-With": "XMLHttpRequest",
				"Content-Type": "application/json",
				"cookie": "laravel_session=" + laravelSession,
			},
		});
		return await invitesResponse.json();
	} catch (error) {
		console.error("Error fetching:", error);
	}
}

export default async function Profile({ params }) {
	const { lang } = await params;

	const invitesArray = await fetchInvites();

	console.log(invitesArray);

	return (
		<div>
			<VerifiedEmailComponent />

			<InvitesComponent invites={invitesArray} />
		</div>
	);
}
