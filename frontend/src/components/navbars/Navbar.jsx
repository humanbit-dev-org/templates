"use client";

import Image from "next/image";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import * as constants from "@/config/constants";
import { usePathname } from "next/navigation";
import { useTranslate } from "@/providers/Translate"; // Provides translation context and hook access for `lang` and `translates`
import { fetchCsrf } from "@/hooks/fetchCsrf";

import "./Navbar.scss";

// UTILITY IMPORTS:
import { useClient } from "@/providers/Client"; // Provide client-only values to the current component {CSR}

export const NavbarComponent = function ({ ...props }) {
	const [loading, setLoading] = useState(true);
	const [isOpen, setIsOpen] = useState(false);
	const [isScrolled, setIsScrolled] = useState(false);

	const csr = useClient();
	const collapseRef = useRef(null);
	const pathName = usePathname();
	const user = csr.user;
	const isLoggedIn = csr.isLoggedIn;
	const lang = useTranslate()["lang"];
	const changeLang = lang === "it" ? "en" : "it";
	const translates = useTranslate()["translates"];

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
	// useEffect(() => {
	// 	const handleScroll = () => {
	// 		if (window.scrollY > 50) {
	// 			setIsScrolled(true);
	// 		} else {
	// 			setIsScrolled(false);
	// 		}
	// 	};

	// 	window.addEventListener("scroll", handleScroll);

	// 	return () => {
	// 		window.removeEventListener("scroll", handleScroll);
	// 	};
	// }, []);

	const handleToggle = () => {
		setIsOpen(!isOpen);
	};

	// Function to properly close the navbar by handling both Bootstrap and React state
	const closeNavbar = () => {
		const collapseElement = collapseRef.current;
		if (collapseElement) {
			const bsCollapse = new bootstrap.Collapse(collapseElement, {
				toggle: false,
			});
			bsCollapse.hide();
		}
		setIsOpen(false);
	};

	const logout = async (event) => {
		event.preventDefault();

		const xsrfToken = await fetchCsrf();

		const fetchPath = constants.BACKEND_URL_CLIENT + "/logout";

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
		<div className={`navbar_component ${isScrolled ? "navbar-scrolled" : ""}`}>
			<nav className={`nav_slide_top rounded-3 navbar ${isScrolled ? "scrolled" : ""}`}>
				<div className="navbar_container_full cont_space_1 color_white container-fluid row justify-content-between align-items-center py-0">
					<div className="menu_navbar cont_mw_1 row justify-content-between align-items-center">
						<div className="collapse_nav navbar-nav row flex-row align-items-end col-12 py-4">
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
									{isLoggedIn ? (
										<>
											<Link
												className="btn_bg_first d-none d-lg-inline-block me-4 w-fit-content"
												href={`/${lang}/profile`}
												onClick={closeNavbar}>
												{translates?.["all"]?.["profile"]?.[lang] ?? "Translate fallback"}
											</Link>

											<span className="el_cont d-inline-block d-lg-none me-4">
												<i className="el_icon color_first fa-regular fa-user" />
											</span>
										</>
									) : (
										<>
											<button
												className="btn_bg_first d-none d-lg-inline-block me-4 w-fit-content"
												data-bs-toggle="modal"
												data-bs-target="#signInModal">
												{translates?.["all"]?.["sign_in"]?.[lang] ?? "Translate fallback"}
											</button>

											<span className="el_cont d-inline-block d-lg-none me-4">
												<i className="el_icon color_first fa-regular fa-user" />
											</span>
										</>
									)}

									<Link
										href={{
											pathname: csr.pathname?.startsWith(`/${lang}`)
												? csr.pathname.replace(`/${lang}`, `/${changeLang}`)
												: `/${changeLang}`,
										}}
										className="small color_first bg_color_white text-uppercase rounded-circle me-4 w-auto">
										{changeLang}
									</Link>

									<button
										onClick={handleToggle}
										className="btn_toggler_open navbar-toggler border-0 align-middle p-0 h-100 collapsed"
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

						<div
							className="menu_collapse bg_color_second semicerchio navbar-collapse collapse"
							id="navbarBasicContent"
							ref={collapseRef}>
							<div className="collapse_wrapper h-auto min-vw-100 mw-100">
								<div className="collapse_contents row cont_space_1 py-0 w-100">
									<div className="basic container_sizing row cont_mw_1 pt-8">
										<div className="collapse_nav navbar-nav col-12 col-lg-6 py-4 pe-lg-4">
											<Link
												className="nav-link"
												onClick={closeNavbar}
												href={csr.page === "home" ? `#what_is_alto` : `/${lang}/home/#what_is_alto`}>
												{translates?.["all"]?.["what_is_alto"]?.[lang] ?? "Translate fallback"}
											</Link>

											<Link
												className="nav-link"
												href={csr.page === "home" ? `#how_it_works` : `/${lang}/home/#how_it_works`}
												onClick={closeNavbar}>
												{translates?.["all"]?.["how_it_works"]?.[lang] ?? "Translate fallback"}
											</Link>

											<Link
												className="nav-link"
												href={csr.page === "home" ? `#about_us` : `/${lang}/home/#about_us`}
												onClick={closeNavbar}>
												{translates?.["all"]?.["about_us"]?.[lang] ?? "Translate fallback"}
											</Link>

											<Link
												className="nav-link"
												href={
													csr.page === "home" ? `#our_ecommerce` : `/${lang}/home/#our_ecommerce`
												}
												onClick={closeNavbar}>
												{translates?.["all"]?.["our_ecommerce"]?.[lang] ?? "Translate fallback"}
											</Link>

											<Link
												className="nav-link"
												href={csr.page === "home" ? `#open_groups` : `/${lang}/home/#open_groups`}
												onClick={closeNavbar}>
												{translates?.["all"]?.["open_groups"]?.[lang] ?? "Translate fallback"}
											</Link>

											<Link
												className="nav-link"
												href={csr.page === "home" ? `#faq` : `/${lang}/home/#faq`}
												onClick={closeNavbar}>
												{translates?.["all"]?.["faq"]?.[lang] ?? "Translate fallback"}
											</Link>

											<div className="d-flex col-6 pt-5">
												<img src="/images/other/logo_orange.png" alt="Logo" />
											</div>
										</div>

										<div className="collapse_nav navbar-nav col-12 col-lg-6 py-4 pe-lg-4">
											{/* <Link className="nav-link nav-link_dx" href={`/${lang}`} onClick={closeNavbar}>
												Istruzioni
											</Link> */}

											<Link
												className="nav-link nav-link_dx"
												href={`/${lang}/groups-list`}
												onClick={closeNavbar}>
												{translates?.["all"]?.["all_groups"]?.[lang] ?? "Translate fallback"}
											</Link>

											<Link
												className="nav-link nav-link_dx mb-3"
												href={`/${lang}/ecommerce-list`}
												onClick={closeNavbar}>
												{translates?.["all"]?.["all_ecommerces"]?.[lang] ?? "Translate fallback"}
											</Link>

											{isLoggedIn ? (
												<Link
													className="btn_bg_sixth border-0 my-2 w-fit-content"
													href={`/${lang}/profile`}
													onClick={closeNavbar}>
													{translates?.["all"]?.["profile"]?.[lang] ?? "Translate fallback"}
												</Link>
											) : (
												<button
													className="btn_bg_sixth border-0 my-2 w-fit-content"
													data-bs-toggle="modal"
													data-bs-target="#signInModal">
													{translates?.["all"]?.["sign_in"]?.[lang] ?? "Translate fallback"}
												</button>
											)}
											{isLoggedIn && (
												<button
													className="btn_bg_third border-0 my-2 w-fit-content"
													onClick={logout}>
													{translates?.["all"]?.["logout"]?.[lang] ?? "Translate fallback"}
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
