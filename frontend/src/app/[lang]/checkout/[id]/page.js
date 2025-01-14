import { cookies } from "next/headers";

export default async function CheckoutPage({ params }) {
	const { id } = await params;

	try {
		const host = process.env.NEXT_PUBLIC_BACKEND_URL_SERVER;
		const cookiesStore = await cookies();
		const laravelSession = cookiesStore.get("laravel_session")?.value;
		const checkoutResponse = await fetch(`${host}/api/checkout/${id}`, {
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
		const order = await checkoutResponse.json();
		console.log(order);

		return (
			<div className="py-12">
				<div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
					<div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
						<div className="p-6 bg-white border-b border-gray-200">
							<h1 className="text-3xl font-bold tracking-tight text-gray-900">Ordine: {order.data.name}</h1>
							<p className="mb-3">Descrizione: {order.data.description}</p>
							<h2 className="text-xl font-bold tracking-tight text-gray-900 mb-3">
								Stato ordine:{" "}
								<span
									className={
										order.data.status === "pending"
											? "fw-700 text-warning"
											: order.data.status === "rejected" || order.data.status === "expired"
												? "fw-700 text-danger"
												: "fw-700 text-success"
									}>
									{order.data.status === "pending"
										? order.data.status +
											" - scade in data: " +
											order.data.expire_date.split(" ")[0] +
											" alle ore " +
											order.data.expire_date.split(" ")[1]
										: order.data.status}
								</span>
								<p>
									{order.data.status === "expired" ? (
										<>
											Ordine scaduto in data {order.data.expire_date.split(" ")[0]} alle ore{" "}
											{order.data.expire_date.split(" ")[1]}
										</>
									) : order.data.status === "completed" ? (
										<>
											Ordine completato in data {order.data.complete_date.split(" ")[0]} alle ore{" "}
											{order.data.complete_date.split(" ")[1]}
										</>
									) : order.data.status === "pending" ? (
										<>
											Ordine in corso. Tutti i membri devono completare l'acquisto:
											<span className="fw-700">
												{order.data.details.filter((item) => item.status === "completed").length}/
												{order.data.details.length}
											</span>
										</>
									) : null}
								</p>
							</h2>

							<hr />
							<p className="mt-4">Dettagli ordine</p>
							<ul className="list-disc list-inside mb-3">
								<li>Importo totale: €{order.data.import}</li>
								<a href={order.data.ecommerce_url} target="_blank">
									{order.data.ecommerce_description}
								</a>
							</ul>

							{order.data.status != "rejected" ? (
								<>
									<hr />
									<p className="mt-4">Stato saldo</p>
									<ul className="list-disc list-inside mb-3">
										{order.data.details.map((detail, index) => (
											<li
												key={index}
												className={
													detail.status === "pending"
														? "fw-700 text-warning"
														: detail.status === "rejected"
															? "fw-700 text-danger"
															: "fw-700 text-success"
												}>
												{detail.user.name} {detail.user.surname} -{" "}
												{detail.status === "completed"
													? "€" +
														detail.import +
														" - " +
														detail.status +
														" in data " +
														detail.complete_date.split(" ")[0] +
														" alle ore " +
														detail.complete_date.split(" ")[1]
													: detail.status}
											</li>
										))}
									</ul>
								</>
							) : null}
						</div>
					</div>
				</div>
			</div>
		);
	} catch (error) {
		console.error("Error loading page data:", error);
		return <p>There was an error loading the page. Please try again later.</p>;
	}
}
