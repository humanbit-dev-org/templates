"use client";

import logo from "/public/images/logos/logo_humanbit_white.png";
import { FooterSimple } from "@/footers/FooterSimple";
import { NavSlideTop } from "@/navbars/NavSlideTop";
import { createContext, useContext, useState } from "react";

// Creiamo il contesto UserContext
const UserContext = createContext(null);
const LocaleContext = createContext(null);

export const AuthProvider = function ({ children, lang, dict, user }) {
	return (
		<>
			<LocaleContext.Provider value={{ lang, dict }}>
				<UserContext.Provider value={{ user }}>
					{/* <NavSlideTop logo={logo} /> */}

					<div className="cont_structure container-fluid">
						{children}

						{/* Modals */}
						{/* <xsl:call-template name="login_modal" />
                        <xsl:call-template name="forgot_pw" />
                        <xsl:call-template name="register_modal" />
                        <xsl:call-template name="change_pw_modal" />
                        <xsl:call-template name="positiveMessagebBefAct" />
                        <xsl:call-template name="service_messages" />
                        <xsl:call-template name="newsletter_b2c_modal" /> */}
					</div>

					{/* Footer template */}
					{/* <FooterComponent /> */}
					{/* <FooterSimple logo={logo} /> */}
				</UserContext.Provider>
			</LocaleContext.Provider>
		</>
	);
};

export const useUser = () => useContext(UserContext);
export const useLocale = () => useContext(LocaleContext);
