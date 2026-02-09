// Client-Side Rendering
// "use client"; // marks module for full browser rendering

// File import statements:
// import { ShimmerImgComponent } from "@/components/elements/ShimmerImg";

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
// import "./ShimmerImg.scss";

// ===============================================
// ## ############################################
// ===============================================

export function ShimmerImgComponent(w, h, c) {
	const lightenColor = (color) => {
		let r = parseInt(color.substring(1, 3), 16);
		let g = parseInt(color.substring(3, 5), 16);
		let b = parseInt(color.substring(5, 7), 16);

		r = Math.min(255, r + 40);
		g = Math.min(255, g + 40);
		b = Math.min(255, b + 40);

		return `#${r.toString(16).padStart(2, "0")}${g.toString(16).padStart(2, "0")}${b.toString(16).padStart(2, "0")}`;
	};

	const c2 = lightenColor(c);

	const shimmerSVG = `
    <svg class="shimmer_img_component" width="${w}" height="${h}" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
      <defs>
        <linearGradient id="g">
          <stop stop-color="${c}" offset="20%" />
          <stop stop-color="${c2}" offset="50%" />
          <stop stop-color="${c}" offset="70%" />
        </linearGradient>
      </defs>
      <rect width="${w}" height="${h}" fill="${c}" />
      <rect id="r" width="${w}" height="${h}" fill="url(#g)" />
      <animate xlink:href="#r" attributeName="x" from="-${w}" to="${w}" dur="1s" repeatCount="indefinite"  />
    </svg>`;

	return `data:image/svg+xml;base64,${typeof window === "undefined" ? Buffer.from(shimmerSVG).toString("base64") : window.btoa(shimmerSVG)}`;
}
