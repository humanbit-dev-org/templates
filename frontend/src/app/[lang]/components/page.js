// import ComponentsPage from "page/components"; // to include it in another file
//
"use client";

// import Header from "@/components/templates/react_test_module";
import { IntlTelInputComponent } from "@/components/blocks/IntlTelInputComponent";

export default function ComponentsPage() {
	return (
		<div className="components_partial">
			<div className="container_humanbit_1 py-5">
				<div className="container_max_width_1">
					<main>
						<IntlTelInputComponent />
					</main>
				</div>
			</div>
		</div>
	);
}
