"use client";

import { useAuth } from "hooks/auth";
import { usePathname } from "next/navigation";
import Link from "next/link";
import { useEffect, useState } from "react";
import Image from "next/image";

export const FooterSimple = function ({ logo }) {
	const pathName = usePathname();

	return (
		<div className="footer_partial">
			<footer className="sizing_wrapper color_white bg_color_black row align-items-center mx-n3 py-4 container_humanbit_1">
				<div className="footer_wrapper row justify-content-between align-items-center col-7 col-sm-5 col-md-12 container_max_width_1">
					<div className="box_1 col-12 col-md-4 mb-3 mb-md-0 px-3">
						<figure className="logo_wrapper col-10 col-sm-12 col-md-8 col-lg-7 col-xl-6 col-xxl-4">
							{/* <img className="logo_primary img-fluid" src="images/logos/logo_humanbit_dark.svg" alt="Logo" /> */}
							<Image
								className="logo_primary img-fluid"
								src={logo.src}
								alt="Logo"
								width={logo.width}
								height={logo.height}
							/>
						</figure>
					</div>
				</div>
				<div className="box_2 copyright_container color_white col-12 col-md-4 row my-3 my-md-0 px-3">
					<div className="inner_wrapper row col-12 col-md-12 col-lg-8 col-xl-7 col-xxl-5">
						<p className="footer_text mb-2">Humanbit S.r.l</p>

						<p className="footer_text">P.IVA 12251470964</p>
					</div>
				</div>
				<div className="box_3 color_white col-12 col-md-4 footer menu_footer_2 row px-3">
					<div className="spacing_wrapper ms-md-auto w-auto">
						<a className="service_link color_white d-block mb-2" href="mailto:info@humanbit.it">
							<i className="footer_icon fal fa-envelope align-middle me-2" />
							<p className="footer_text d-inline-block">info@humanbit.com</p>
						</a>

						<div className="service_wrapper d-flex flex-wrap color_gray_footer my-0 ps-0">
							{/* <xsl:choose>
                                <xsl:when test="lan='it'">
                                    <a className="service_link text-uppercase p-0" href="" target="_blank">Privacy</a>
                                </xsl:when>
                                <xsl:otherwise>
                                    <a className="service_link text-uppercase p-0" href="" target="_blank">Privacy</a>
                                </xsl:otherwise>
                            </xsl:choose> */}
							<Link
								href={{
									pathname: `${pathName}/prova`,
								}}
								className="menu_link circle_1 text-uppercase color_white">
								{true === true ? "IT" : "EN"}
							</Link>

							<p className="copyright_text">© 2022 Humanbit</p>
						</div>
					</div>
				</div>
			</footer>
		</div>
	);
};
