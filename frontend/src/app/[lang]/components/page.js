// Server-Side Rendering (React generates HTML before hydration)

// File import statements:
// import ComponentsPage from "@/page/components";

// import Header from "@/components/templates/react_test_module";
import { IntlTelInputComponent } from "@/components/blocks/IntlTelInput";

// 4. Relative internal (same directory)
import "./page.scss";

// ===============================================
// ## ############################################
// ===============================================

// Get the base URL for assets from environment variables (publicly exposed)
const BASE_URL = process.env.NEXT_PUBLIC_BACKEND_URL_SERVER;

export default function ComponentsPage() {
	return (
		<div className="components_page">
			<div className="page_cont bg_color_first">
				<section className="cont_space_1">
					<div className="cont_mw_1">
						<main>
							<IntlTelInputComponent />

							<div className="block_wrap vert_charts text-center d-flex flex-wrap justify-content-center align-items-center mb-5 w-100">
								<div className="vert_chart mx-2 left" />

								<div className="vert_chart mx-2 right fx" />
								<div className="vert_chart mx-2 right fx cascade" />
								<div className="vert_chart mx-2 right fx delay" />
								<div className="vert_chart mx-2 right fx delay" />
								<div className="vert_chart mx-2 right fx cascade delay" />
								<div className="vert_chart mx-2 right fx cascade" />

								<div className="vert_chart mx-2 right fx wait" />
								<div className="vert_chart mx-2 right fx cascade wait" />
								<div className="vert_chart mx-2 right fx delay wait" />
								<div className="vert_chart mx-2 right fx delay wait" />
								<div className="vert_chart mx-2 right fx cascade delay wait" />
								<div className="vert_chart mx-2 right fx cascade wait" />
							</div>
						</main>
					</div>
				</section>
			</div>
		</div>
	);
}
