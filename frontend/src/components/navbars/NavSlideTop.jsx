"use client";

import Image from "next/image";
import Link from "next/link";
import { PlusComponent } from "components/templates/PlusComponent";
import { useEffect, useRef, useState } from "react";
import { useUser, useLocale } from "components/utilities/AuthHelper";
import { usePathname } from "next/navigation";

const BASE_URL = process.env.NEXT_PUBLIC_BACKEND_URL;

async function fetchCsrf() {
	try {
		const fetchPath = BASE_URL + "/sanctum/csrf-cookie";

		await fetch(fetchPath, {
			method: "GET",
			credentials: "include",
		});
		const xsrfToken = document.cookie
			.split("; ")
			.find((row) => row.startsWith("XSRF-TOKEN"))
			?.split("=")[1];
		return decodeURIComponent(xsrfToken);
	} catch (error) {
		console.error("Error fetching csrf:", error);
	}
}

export const NavSlideTop = function ({ ...props }) {
	const collapseRef = useRef(null);
	const [loading, setLoading] = useState(true);
	const pathName = usePathname();
	const [isOpen, setIsOpen] = useState(false);
	const [isScrolled, setIsScrolled] = useState(false);
	const { user } = useUser();
	const { lang, dict } = useLocale();
	const isLoggedIn = user != undefined ? true : false;

	useEffect(() => {
		if (isOpen) {
			const collapseElement = collapseRef.current;
			const bsCollapse = new bootstrap.Collapse(collapseElement, {
				toggle: false,
			});
			bsCollapse.hide();
			setIsOpen(false);
		}
	}, [pathName]);

	// Aggiungi l'effetto per rilevare lo scroll
	useEffect(() => {
		const handleScroll = () => {
			if (window.scrollY > 50) {
				setIsScrolled(true);
			} else {
				setIsScrolled(false);
			}
		};

		window.addEventListener("scroll", handleScroll);

		return () => {
			window.removeEventListener("scroll", handleScroll);
		};
	}, []);

	const handleToggle = () => {
		setIsOpen(!isOpen);
	};

	const logout = async (event) => {
		event.preventDefault();

		const xsrfToken = await fetchCsrf();

		const fetchPath = baseUrl + "/logout";

		const logoutRequest = new Request(fetchPath, {
			method: "POST",
			credentials: "include",
			headers: {
				"Accept": "application/json",
				"X-Requested-With": "XMLHttpRequest",
				"Content-Type": "application/json",
				"X-XSRF-TOKEN": xsrfToken,
			},
		});

		try {
			const logoutResponse = await fetch(logoutRequest);
			if (!logoutResponse.ok) {
				throw new Error("Logout failed");
			}
			window.location.href = "/";
		} catch (error) {
			console.error("Error logging out:", error);
		}
	};

	return (
		<div className={`nav_partial ${isScrolled ? "navbar-scrolled" : ""}`}>
			<nav className={`nav_slide_top rounded-3 navbar scrollbar_spacing ${isScrolled ? "scrolled" : ""}`}>
				<div className="navbar_container_full container_humanbit_1 color_white container-fluid row justify-content-between align-items-center py-0">
					<div className="menu_navbar container_max_width_1 row justify-content-between align-items-center">
						<div className="collapse_nav navbar-nav row flex-row col-12 py-4">
							{/* logo */}
							<Link
								className="menu_box box_center color_white small row align-items-center-md col-auto col-md-2"
								href={{
									pathname: `/${lang}`,
								}}>
								<figure className="figure_logo">
									<Image
										className="logo_primary img-fluid"
										src={props.logo.src}
										alt="Logo"
										width={props.logo.width}
										height={props.logo.height}
									/>
								</figure>
							</Link>
							{/* fine logo */}

							{/* toggler */}
							<div className="menu_box box_left color_white row justify-content-start align-items-center col-auto ms-auto">
								<div className="toggle_wrapper w-auto h-100">
									<button
										onClick={handleToggle}
										className="btn_toggler_open navbar-toggler border-0 align-middle p-0 w-auto h-100 collapsed"
										type="button"
										data-bs-toggle="collapse"
										data-bs-target="#navbarBasicContent"
										aria-controls="navbarBasicContent"
										aria-expanded="false"
										aria-label="Toggle navigation"
										id="navTogglerBasic">
										<span className="span_toggler" />
										<span className="span_toggler d-none" />
										<span className="span_toggler" />
									</button>
								</div>
							</div>
							{/* fine toggler */}
						</div>
						{/* Voci del menu per mobile */}
						{/* Half circle at the top center */}
						<PlusComponent />

						<div
							className="menu_collapse bg_color_second semicerchio navbar-collapse collapse"
							id="navbarBasicContent"
							ref={collapseRef}>
							<div className="collapse_wrapper scrollbar_spacing h-auto min-vw-100 mw-100">
								<div className="collapse_contents  row container_humanbit_1 py-0 w-100">
									<div className="basic container_sizing row container_max_width_1 pt-8">
										<div className="collapse_nav navbar-nav col-12 col-lg-6 py-4 pe-lg-4">
											<Link className="nav-link" href={`/${lang}`}>
												Cos'è All Together Pay
											</Link>

											<Link className="nav-link" href={`/${lang}/groups-list`}>
												Come funziona
											</Link>

											<Link className="nav-link" href={`/${lang}/ecommerce`}>
												Chi siamo
											</Link>

											<Link className="nav-link" href={`/${lang}/about`}>
												Lista e-commerce
											</Link>

											<Link className="nav-link" href={`/${lang}/contact`}>
												Gruppi aperti
											</Link>

											<Link className="nav-link" href={`/${lang}/contact`}>
												FAQ
											</Link>

											<div className="d-flex col-6 pt-5">
												<img src="/images/other/logo_orange.png" alt="Logo" />
											</div>
										</div>

										<div className="collapse_nav navbar-nav col-12 col-lg-6 py-4 pe-lg-4">
											<Link className="nav-link nav-link_dx" href={`/${lang}`}>
												Instruzioni
											</Link>

											<Link className="nav-link nav-link_dx" href={`/${lang}/groups-list`}>
												Tutti i gruppi
											</Link>

											<Link className="nav-link nav-link_dx mb-3" href={`/${lang}/ecommerce`}>
												Tutti gli e-commerce
											</Link>

											{isLoggedIn ? (
												<Link className="nav-link my-2" href={`/${lang}/profile`}>
													Profile
												</Link>
											) : (
												<button
													className="btn_bg_sixth border-0 my-2 w-fit-content"
													data-bs-toggle="modal"
													data-bs-target="#signInModal">
													{dict.sign_in}
												</button>
											)}
											{isLoggedIn ? (
												<button
													className="btn_bg_sixth border-0 my-2 w-fit-content"
													onClick={logout}>
													Logout
												</button>
											) : (
												<button
													className="btn_bg_third border-0 my-2 w-fit-content"
													data-bs-toggle="modal"
													data-bs-target="#registerModal">
													{dict.sign_up}
												</button>
											)}
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</nav>
		</div>
	);
};

<style jsx>
	{`.semicerchio
		background-color: #ffffff !important;
		clip-path: ellipse(50% 50% at 50% 0) !important;
	`}
</style>;
