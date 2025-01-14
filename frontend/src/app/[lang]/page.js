const baseUrl = process.env.NEXT_PUBLIC_BACKEND_URL_SERVER;

async function fetchCustom(lang, endpoint) {
	try {
		const fetchPath = baseUrl + (lang != null ? `/api/${lang}/${endpoint}` : `/api/${endpoint}`);
		console.log(fetchPath);
		const fetchResponse = await fetch(fetchPath, {
			method: "GET",
			headers: {
				"Content-Type": "application/json",
			},
		});
		return await fetchResponse.json();
	} catch (error) {
		console.error("Error fetching:", error);
	}
}

export default async function HomePage({ params }) {
	const { lang } = await params;
	// console.log({ lang }.lang);

	return (
		<div className="home_partial">
			<div className="container_humanbit_1 py-5">
				<div className="container_max_width_1">{/* content */}</div>
			</div>
		</div>
	);
}
