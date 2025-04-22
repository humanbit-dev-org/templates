// Client-Side Rendering
"use client"; // marks module for full browser rendering

// File import statements:
// import { NavSideBurgerComponent } from "@/navbars/NavSideBurger";

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
import { usePathInfoCSR } from "@/hooks/pathInfoCSR";

// 4. Relative internal (same directory)
import "./NavSideBurger.scss";

// ===============================================
// ## ############################################
// ===============================================

const BASE_URL = process.env.NEXT_PUBLIC_BACKEND_URL_CLIENT;
const MEDIA_PATH = `${BASE_URL}/storage/uploads`;

function menuLinks(page, pageIndex, menu) {
	switch (page.name) {
		case "la-nostra-storia":
			return (
				<div key={pageIndex} className="obj_cont col-12 col-xl-6 mb-5 mb-xl-6">
					<a href="/la-nostra-storia">
						<h6 className="el_title small fw-400 color_white mb-4 mb-xl-5">{page.title}</h6>
					</a>

					<div className="obj_wrap row align-content-start position-relative">
						<div className="navbar-nav smaller fw-600 col-auto">
							{menu.chapters.map((chapter, chapterIndex) => (
								<a
									key={chapterIndex}
									className="nav-link pt-0 pb-1"
									href={`/la-nostra-storia#content${chapterIndex}`}>
									<span className="el_nav-link color_second">
										{chapter.start_year} - {chapter.end_year}
									</span>

									<span className="el_nav-link color_white d-inline-block d-xl-none pt-0 ps-3 ps-xl-0 pb-1">
										{chapter.title}

										{/* <figure className="obj_card ms-6 position-absolute top-0 start-100"> */}
										<figure className="obj_card d-none d-xl-inline-block position-absolute top-0 start-75">
											<img
												className="el_img obj_hover position-relative"
												src={
													chapter.media[0]?.image_path
														? `${mediaPath}/${chapter.media[0]?.image_path}`
														: `/images/other/la-federazione-industriale-lombarda.png`
												}
												alt={chapter.media[0]?.caption ? chapter.media[0].caption : "Chapter"}
											/>

											<figcaption className="el_txt bg_color_gd storia small fw-500 pt-3 pb-2 ps-3 position-absolute end-0 bottom-0 start-0">
												{chapter.start_year} - {chapter.end_year}
											</figcaption>
										</figure>
									</span>
								</a>
							))}
						</div>

						<div className="navbar-nav smaller fw-400 color_white d-none d-xl-block col-auto justify-content-around ps-4">
							{menu.chapters.map((chapter, chapterIndex) => (
								<a
									key={chapterIndex}
									className="nav-link pt-0 pb-1"
									href={`/la-nostra-storia#content${chapterIndex}`}>
									{chapter.title}
									{/* <figure className="obj_card ms-6 position-absolute top-0 start-100"> */}
									<figure className="obj_card d-none d-xl-inline-block position-absolute top-0 start-75">
										<img
											className="el_img obj_hover position-relative"
											src={
												chapter?.media[0]?.image_path
													? `${mediaPath}/${chapter.media[0]?.image_path}`
													: `/images/other/la-federazione-industriale-lombarda.png`
											}
											alt={chapter?.media[0]?.caption ? chapter.media[0].caption : "Chapter"}
										/>

										<figcaption className="el_txt bg_color_gd storia small fw-500 pt-3 pb-2 ps-3 position-absolute end-0 bottom-0 start-0">
											{chapter.start_year} - {chapter.end_year}
										</figcaption>
									</figure>
								</a>
							))}
						</div>
					</div>
				</div>
			);
		case "la-voce-dei-presidenti":
		case "riflessioni-su-milano":
			const dataName = page.name == "la-voce-dei-presidenti" ? "presidenti" : "riflessioni";
			const arrayContent = page.name == "la-voce-dei-presidenti" ? menu.presidents : menu.thoughts;
			return (
				<div
					key={pageIndex}
					className="accordion accordion-flush col-12 col-xl-6 mb-5 mb-xl-6"
					id={`${dataName}${pageIndex}`}>
					<div className="obj_cont accordion-item bg_color_first border-bottom-0">
						<div className="accordion-header mb-4 mb-xl-5" id={`flush-heading${dataName}${pageIndex}`}>
							<a
								className="accordion_button py-0"
								// key={pageIndex}
								href={
									page.name == "la-voce-dei-presidenti"
										? `/la-voce-dei-presidenti`
										: `/riflessioni-su-milano`
								}>
								{/* className="accordion_button py-0 collapsed"
								type="button"
								data-bs-toggle="collapse"
								data-bs-target={`#flush-collapse${dataName}${pageIndex}`}
								aria-expanded="false"
								aria-controls={`#flush-collapse${dataName}${pageIndex}`}> */}
								<h6 className="el_title small fw-400 color_white">{page.title}</h6>
							</a>
						</div>

						<div
							id={`flush-collapse${dataName}${pageIndex}`}
							// className="accordion-collapse border-top-0 position-relative collapse"
							className="accordion-collapse border-top-0 position-relative"
							aria-labelledby={`flush-heading${dataName}${pageIndex}`}
							data-bs-parent={`#${dataName}${pageIndex}`}>
							<div className="accordion-body row align-content-start py-0">
								<div className="navbar-nav smaller fw-400 color_white col-auto">
									{arrayContent.map((content, contentIndex) => (
										<a
											key={contentIndex}
											className="nav-link pt-0 pb-2"
											href={
												page.name == "la-voce-dei-presidenti"
													? `/la-voce-dei-presidenti#content${contentIndex}`
													: `/riflessioni-su-milano#content${contentIndex}`
											}>
											<span className="el_txt fw-700">
												{content.name ? content.name : content.author_name}{" "}
												{content.surname ? content.surname : content.author_surname} {" - "}
											</span>
											<span className="el_txt fw-400">
												{content.paragraphs[0]?.title}{" "}
												{page.name === "la-voce-dei-presidenti"
													? `(${content.start_year} - ${content.end_year})`
													: null}
											</span>

											{/* <figure className="obj_card ms-6 position-absolute top-0 start-100"> */}
											<figure className="obj_card d-none d-xl-inline-block position-absolute top-0 start-75">
												<img
													className="el_img obj_hover position-relative"
													src={
														content?.image_path
															? `${mediaPath}/${content.image_path}`
															: content?.media?.[0]?.image_path
																? `${mediaPath}/${content.media[0].image_path}`
																: `/images/other/piero_bassetti.png`
													}
													alt={
														content?.image_path
															? `${content.name} ${content.surname}`
															: content?.media?.[0]?.caption
																? content.media[0].caption
																: "President"
													}
												/>

												<figcaption
													className={`el_txt bg_color_gd ${dataName} small fw-500 pt-3 pb-2 ps-3 position-absolute end-0 bottom-0 start-0`}>
													{content.name ? content.name : content.author_name}{" "}
													{content.surname ? content.surname : content.author_surname}
												</figcaption>
											</figure>
										</a>
									))}
								</div>
							</div>
						</div>
					</div>
				</div>
			);
		case "crediti":
			return (
				<a key={pageIndex} className="obj_cont d-block mt-3" href={`/${page.name}`}>
					<p className="el_txt manrope p color_white">Crediti</p>
				</a>
			);
		default:
			return (
				<a key={pageIndex} className="obj_cont d-inline-block col-12 col-xl-6 mb-5 mb-xl-6" href={`/${page.name}`}>
					<h6 className="el_title small fw-400 color_white">{page.title}</h6>
				</a>
			);
	}
}

export function useToggleOverflow(isCollapsed) {
	useEffect(() => {
		if (typeof window !== "undefined") {
			document.body.classList.toggle("overflow-hidden", !isCollapsed);
		}
	}, [isCollapsed]);
}

export function NavSideBurgerComponent({ menu }) {
	// Get structured path info from the current URL
	const { pathname, page, id, slug } = usePathInfoCSR();

	const [isCollapsed, setIsCollapsed] = useState(true);
	useToggleOverflow(isCollapsed);

	return (
		<div className="nav_side_burger_component">
			<div className="block_cont position-sticky top-0">
				<nav className="navbar">
					<div className="block_wrap bg_color_white row w-100 vh-xl-100">
						<div className="group_cont mt-auto cont_space_1 px-xl-3 full_height">
							<div className="group_wrap d-flex flex-wrap align-items-end align-items-xl-start justify-content-between justify-content-xl-center pt-xl-2 pb-4 pb-xl-0">
								<a className="obj_link small color_first lh-1 d-inline-block d-xl-none mb-n2" href="/">
									<span className="el_txt">INSIEME</span>
								</a>

								<button
									// className="toggler_obj collapsed"
									className={`toggler_obj ${isCollapsed ? "collapsed" : ""}`}
									type="button"
									data-bs-toggle="collapse"
									data-bs-target="#navbarToggler"
									aria-controls="navbarToggler"
									aria-expanded="false"
									aria-label="Toggle navigation"
									onClick={() => setIsCollapsed(!isCollapsed)}>
									<span className="el_obj"></span>
									<span className="el_obj"></span>
									<span className="el_obj"></span>
								</button>
							</div>

							<div
								className="navbar-collapse bg_color_first position-absolute top-100 top-xl-0 end-0 end-xl-100 collapse"
								id="navbarToggler">
								<div className={`group_wrap vh-100 overflow-y-auto ${page === "home" ? "obj_home" : ""}`}>
									<div className="cont_space_1 pb-10 pb-xl-6">
										<div className="cont_mw_1">
											<div className="obj_cont_wrap mb-6">
												<NavLogoTopComponent />
											</div>

											{menu.pages.map((page, pageIndex) => menuLinks(page, pageIndex, menu))}
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</nav>
			</div>
		</div>
	);
}
