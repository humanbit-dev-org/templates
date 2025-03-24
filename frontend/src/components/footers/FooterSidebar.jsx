// Client-Side Rendering
"use client"; // marks module for full browser rendering

// File import statements:
// import { FooterComponent } from "@/components/footers/FooterSidebar";

// 1. Core imports (React & Next.js)
import { useEffect, useState } from "react";

// 2. External imports (third-party libraries)
// import axios from "axios";
// import clsx from "clsx";
// import useSWR from "swr";
// import { AnimatePresence, motion } from "framer-motion";
// import { signIn, signOut, useSession } from "next-auth/react";

// 3. Absolute internal (`@/` alias)
import { NavLogoTopComponent } from "@/navbars/NavLogoTop";

// 4. Relative internal (same directory)
import "./FooterSidebar.scss";

// ===============================================
// ## ############################################
// ===============================================

export function FooterComponent() {
	const [windowWidth, setWindowWidth] = useState(0);

	useEffect(() => {
		// Function to update width
		const handleResize = () => setWindowWidth(window.innerWidth);

		// Set initial width
		handleResize();

		// Listen for window resize
		window.addEventListener("resize", handleResize);

		// Cleanup listener on unmount
		return () => window.removeEventListener("resize", handleResize);
	}, []);

	return (
		<>
			{windowWidth < 1199.98 ? (
				<div className="footer_sidebar_component bg_color_first">
					<div className="cont_space_1">
						<div className="cont_mw_1">
							<NavLogoTopComponent />
						</div>
					</div>
				</div>
			) : (
				<div className="footer_sidebar_component">
					<div className="block_cont d-flex flex-wrap vh-100 position-relative">
						<figure className="group_cont obj_1 w-auto h-100 position-absolute top-0 end-0">
							<img className="el_img h-100 mw-100" src="/images/other/250204_INSIEME.png" alt="" />
						</figure>

						<div
							className="group_cont row align-content-start mt-auto ms-auto px-3 full_height"
							style={{ minWidth: "calc(118.85px + 2rem)", maxWidth: "calc(138.81px + 2rem)" }}>
							<div className="group_wrap mb_auto">
								<p className="el_txt small fw-600 mb-2">INSIEME</p>

								<p className="el_txt smaller fw-400 mb-5">
									Assolombarda, <br />
									La nostra storia
								</p>
							</div>

							<div className="group_wrap">
								<button className="el_btn btn_bg_first w-100" onClick={() => (location.href = "/")}>
									Home
								</button>
							</div>
						</div>
					</div>
				</div>
			)}
		</>
	);
}
