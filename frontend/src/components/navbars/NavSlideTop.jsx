"use client";

import Image from "next/image";
import Link from "next/link";
import { motion } from "motion/react";
import { useEffect, useRef, useState } from "react";
import { useLocale, useUser } from "@/hooks/auth";
import { usePathname } from "next/navigation";

const BASE_URL = process.env.NEXT_PUBLIC_BACKEND_URL_CLIENT;

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

export const NavSlideTopComponent = function ({ ...props }) {
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

		const fetchPath = BASE_URL + "/logout";

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
			<nav className={`nav_slide_top navbar scrollbar_spacing ${isScrolled ? "scrolled" : ""}`}>
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

							<div className="nav_menu col-4 align-content-center d-none d-lg-flex flex-wrap justify-content-between">
								{/* Link della navbar */}
								<Link
									className="collapse_link col-auto show-search"
									href={{
										pathname: `/${lang}`,
									}}>
									<p className="nav-link small text-capitalize d-inline-block py-2 px-0">Home</p>
								</Link>

								<Link
									className="collapse_link col-auto"
									href={{
										pathname: `/${lang}/groups-list`,
									}}>
									<p className="nav-link small text-capitalize d-inline-block py-2 px-0">Gruppi</p>
								</Link>

								<Link
									className="collapse_link col-auto"
									href={{
										pathname: `/${lang}/ecommerce`,
									}}>
									<p className="nav-link small text-capitalize d-inline-block py-2 px-0">Ecommerce</p>
								</Link>

								<Link
									className="collapse_link col-auto"
									href={{
										pathname: `/${lang}/about`,
									}}>
									<p className="nav-link small text-capitalize d-inline-block py-2 px-0">Chi Siamo</p>
								</Link>

								<Link
									className="collapse_link col-auto"
									href={{
										pathname: `/${lang}/contact`,
									}}>
									<p className="nav-link small text-capitalize d-inline-block py-2 px-0">Contatti</p>
								</Link>
							</div>
							<div className="menu_box box_right flex-row flex-nowrap gap-3 d-none d-lg-flex text-uppercase color_white d-flex flex-wrap justify-content-end align-items-center col-auto order-0 order-lg-1 ms-auto">
								{isLoggedIn ? (
									<Link
										className="btn_bg_first"
										href={{
											pathname: `/${lang}/profile`,
										}}>
										Profile
									</Link>
								) : (
									<motion.button
										className="btn_bg_first"
										type="button"
										data-bs-toggle="modal"
										data-bs-target="#signInModal"
										whileHover={{ scale: 1.1 }}
										whileTap={{ scale: 0.95 }}>
										{dict.sign_in}
									</motion.button>
								)}

								{isLoggedIn ? (
									<motion.button
										className="btn_bg_second"
										type="button"
										onClick={logout}
										whileHover={{ scale: 1.1 }}
										whileTap={{ scale: 0.95 }}>
										Logout
									</motion.button>
								) : (
									<motion.button
										className="btn_bg_second"
										type="button"
										data-bs-toggle="modal"
										data-bs-target="#registerModal"
										whileHover={{ scale: 1.1 }}
										whileTap={{ scale: 0.95 }}>
										{dict.sign_up}
									</motion.button>
								)}
							</div>

							{/* toggler */}
							<div className="menu_box box_left d-lg-none color_white row justify-content-start align-items-center col-auto col-lg-3 ms-auto">
								<div className="toggle_wrapper col-1 me-4 me-xl-5">
									<button
										onClick={handleToggle}
										className="btn_toggler_open navbar-toggler border-0 align-middle p-0 w-auto collapsed"
										type="button"
										data-bs-toggle="collapse"
										data-bs-target="#navbarBasicContent"
										aria-controls="navbarBasicContent"
										aria-expanded="false"
										aria-label="Toggle navigation"
										id="navTogglerBasic">
										<span className="span_toggler color_white" />
										<span className="span_toggler color_white" />
										<span className="span_toggler color_white" />
									</button>
								</div>
							</div>
							{/* fine toggler */}
						</div>
						{/* Voci del menu per mobile */}
						<div
							className="menu_collapse  bg_color_second navbar-collapse collapse d-lg-none"
							id="navbarBasicContent"
							ref={collapseRef}>
							<div className="collapse_wrapper scrollbar_spacing h-auto min-vw-100 mw-100">
								<div className="collapse_contents row container_humanbit_1 py-0 w-100">
									<div className="basic container_sizing container_max_width_1 row justify-content-between align-items-start">
										<div className="collapse_nav  navbar-nav row flex-row col-12 col-lg-6 py-4 pe-lg-4">
											<Link className="nav-link" href={`/${lang}`}>
												Home
											</Link>

											<Link className="nav-link" href={`/${lang}/groups-list`}>
												Gruppi
											</Link>

											<Link className="nav-link" href={`/${lang}/ecommerce`}>
												Ecommerce
											</Link>

											<Link className="nav-link" href={`/${lang}/about`}>
												Chi Siamo
											</Link>

											<Link className="nav-link" href={`/${lang}/contact`}>
												Contatti
											</Link>

											{isLoggedIn ? (
												<Link className="nav-link my-2" href={`/${lang}/profile`}>
													Profile
												</Link>
											) : (
												<motion.button
													className="btn_bg_first nav-link border-0 my-2"
													data-bs-toggle="modal"
													data-bs-target="#signInModal"
													whileHover={{ scale: 1.1 }}
													whileTap={{ scale: 0.95 }}>
													{dict.sign_in}
												</motion.button>
											)}
											{isLoggedIn ? (
												<motion.button
													className="my-2 btn_bg_second nav-link border-0"
													onClick={logout}
													whileHover={{ scale: 1.1 }}
													whileTap={{ scale: 0.95 }}>
													Logout
												</motion.button>
											) : (
												<motion.button
													className="my-2 btn_bg_second nav-link border-0"
													data-bs-toggle="modal"
													data-bs-target="#registerModal"
													whileHover={{ scale: 1.1 }}
													whileTap={{ scale: 0.95 }}>
													{dict.sign_up}
												</motion.button>
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
