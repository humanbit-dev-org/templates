// Client-Side Rendering
// "use client"; // marks module for full browser rendering

// File import statements:
// import { NavLogoTopComponent } from "@/navbars/NavLogoTop";

// 1. Core imports (React & Next.js)
// import React, { createContext, useCallback, useContext, useEffect, useMemo, useReducer, useRef, useState } from "react";

// 2. External imports (third-party libraries)
// import axios from "axios";
// import clsx from "clsx";
// import useSWR from "swr";
// import { AnimatePresence, motion } from "framer-motion";
// import { signIn, signOut, useSession } from "next-auth/react";

// 3. Absolute internal (`@/` alias)
// import DefaultExportModule from "@/<path>/DefaultExports";
// import { NamedExportModule } from "@/<path>/NamedExports";

// 4. Relative internal (same directory)
import "./NavLogoTop.scss";

// ===============================================
// ## ############################################
// ===============================================

export function NavLogoTopComponent({ menu }) {
	const baseUrl = process.env.NEXT_PUBLIC_ADMIN_URL;
	const mediaPath = `${baseUrl}/storage/uploads`;

	return (
		<div className="nav_logo_top_component">
			<div className="obj_cont obj_1 d-flex flex-wrap justify-content-between align-items-center">
				<a className="obj_link" href="https://www.fondazioneassolombarda.it/" target="_blank">
					<figure className="el_logo">
						<img className="el_img w-100" src="/images/logos/fondazione-assolombarda.png" alt="" />
					</figure>
				</a>

				<a className="el_link" href="https://www.assolombarda.it/" target="_blank">
					<figure className="el_logo">
						<img className="el_img w-100" src="/images/logos/Asso_standard_P_1.png" alt="" />
					</figure>
				</a>
			</div>
		</div>
	);
}
