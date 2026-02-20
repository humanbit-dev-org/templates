// Client-Side Rendering
// "use client"; // marks module for full browser rendering

// File import statements:
// import { IconInfoComponent } from "@/components/elements/IconInfo";

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
import "./IconInfo.scss";

// ===============================================
// ## ############################################
// ===============================================

export function IconInfoComponent() {
	return (
		<svg
			className="icon_info_component"
			width="34"
			height="34"
			viewBox="0 0 34 34"
			fill="none"
			xmlns="http://www.w3.org/2000/svg">
			<circle className="icon_background bg_color_section" cx="17" cy="17" r="17" />

			<path
				className="icon_path color_section"
				d="M16.9488 12.1894C15.9249 12.1894 15.2082 11.5902 15.2082 10.6237C15.2082 9.71521 15.9249 9 16.9488 9C17.9727 9 18.7099 9.5799 18.7099 10.5464C18.7099 11.4742 17.9727 12.1894 16.9488 12.1894ZM14 24V23.5168C15.2901 23.3621 15.5358 23.1495 15.5358 22.1637V16.384C15.5358 15.3595 15.1468 14.7023 14 14.5477V14.0644L18.0341 13.4652H18.5666V22.1637C18.5666 23.1495 18.9147 23.3621 20 23.5168V24H14Z"
			/>
		</svg>
	);
}
