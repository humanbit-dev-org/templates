"use client"; // marks module for full browser execution
//
// import { ToastsComponent } from "@/components/<filename>"; // File import statement

// 1. Core imports (React & Next.js)
// import Link from "next/link"; // Client-side routing with automatic pre-fetching {CSR}
import React, { useEffect, useState } from "react"; // React hooks to manage state, context, and side effects {CSR}
import { useEventMessage } from "@/providers/EventMessage";
// 	createContext, // Create a global Context {CSR}
// 	useCallback, // Memoize a callback to avoid re-creating it on re-renders {CSR}
// 	useContext, // Consume the nearest <Provider>'s Context value {CSR}
// 	useEffect, // Run side effects AFTER screen update (non-blocking; e.g., data fetch, event listener) {CSR}
// 	useImperativeHandle, // [NICHE] Expose custom methods to parent refs instead of the DOM node (e.g., `focus()`, `scrollToBottom()`) {CSR}
// 	useLayoutEffect, // [RARE] Run side effects BEFORE screen update (blocking; e.g., layout reads/writes) {CSR}
// 	useMemo, // Memoize a value to avoid re-computing it on re-renders {CSR}
// 	useReducer, // Manage complex state logic with a reducer function {CSR}
// 	useRef, // Create a mutable ref that persists across renders {CSR}
// 	useState, // Manage local component state {CSR}
// } from "react";

// 2. External imports (third-party libraries)
// import axios from "axios"; // Promise-based HTTP client for data fetching (API requests) {CSR|SSR}
// import clsx from "clsx"; // Conditional CSS class name joiner {CSR|SSR}
// import useSWR from "swr"; // Client-side data fetching with automatic revalidation {CSR}
// import { AnimatePresence, motion } from "framer-motion"; // Declarative client-side animations and transitions {CSR}
// import { Bar, BarChart } from "recharts"; // Base components for rendering bar charts {CSR}
// import { Canvas } from "@react-three/fiber"; // Render 3D scenes with Three.js using JSX (client-only) {CSR}
// import { gsap } from "gsap"; // High-performance JS animation engine (scroll triggers, timelines, sequences) {CSR}
// import { HoverCard, Modal, Tabs } from "aceternity-ui"; // Prebuilt animated UI components (built on Framer Motion) {CSR}
// import { signIn, signOut, useSession } from "next-auth/react"; // Client-side user auth helpers {CSR}

// 3. Absolute internal (`@/` alias)
// IMPORT SYNTAX:
// import DefaultExportModule from "@/<path>/DefaultExport"; // {CSR|SSR}
// import { NamedExportModule } from "@/<path>/NamedExport"; // {CSR|SSR}
//
// UTILITY IMPORTS:
// import { getServer } from "@/lib/server"; // Provide server-only values to the current component {SSR}
// import { useClient } from "@/providers/Client"; // Provide client-only values to the current component {CSR}
// import { useTranslate } from "@/providers/Translate"; // Provides translation context and hook access for `lang` and `translates`
//
// FUTURE REFERENCE IMPORTS:
// import { Alert, Dialog, Input } from "@/components/ui"; // Accessible component primitives (Radix-based, styled with Tailwind) {CSR}
// import { ChartContainer, ChartTooltipContent } from "@/components/ui/chart"; // Styled chart wrapper and tooltip content (ShadCN + Recharts) {CSR}

// 4. Relative internal (same directory)
import "./Toasts.scss";

// ===============================================
// ## ############################################
// ===============================================

export function ToastsComponent() {
	const { messageData, messageType, messageId } = useEventMessage();
	// const ssr = await getServer();
	// const csr = useClient();
	// const lang = useTranslate()["lang"];
	// const translates = useTranslate()["translates"]; // E.g., {translates?.[csr.page]?.["<code>"]?.[lang] ?? "Translate fallback"}

	useEffect(() => {
		if (messageData && messageType && messageId > 0) {
			// Use the original Bootstrap approach for showing toasts
			const toastElement = document.querySelector(`#toast-${messageType}`);

			if (toastElement && window.bootstrap) {
				// Hide any currently visible toasts first
				document.querySelectorAll(".toast-item").forEach((toast) => {
					const instance = window.bootstrap.Toast.getInstance(toast);

					if (instance) {
						instance.hide();
					}
				});

				// Wait a bit to ensure previous toast is completely hidden, then show new one
				setTimeout(() => {
					// Dispose of existing instance to reset state
					const existingInstance = window.bootstrap.Toast.getInstance(toastElement);

					if (existingInstance) {
						existingInstance.dispose();
					}

					// Create fresh instance and show
					const newInstance = new window.bootstrap.Toast(toastElement, {
						autohide: true,
						delay: 5000,
					});
					newInstance.show();
				}, 200); // Small delay to ensure clean state
			}
		}
	}, [messageData, messageType, messageId]); // Include messageId in dependency array

	// Function to get icon based on toast type
	const getToastIcon = (type) => {
		switch (type) {
			case "success":
				return (
					<div className="toast-icon toast-icon-success">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path
								d="M20 6L9 17L4 12"
								stroke="currentColor"
								strokeWidth="2.5"
								strokeLinecap="round"
								strokeLinejoin="round"
							/>
						</svg>
					</div>
				);
			case "error":
				return (
					<div className="toast-icon toast-icon-error">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path
								d="M18 6L6 18"
								stroke="currentColor"
								strokeWidth="2.5"
								strokeLinecap="round"
								strokeLinejoin="round"
							/>
							<path
								d="M6 6L18 18"
								stroke="currentColor"
								strokeWidth="2.5"
								strokeLinecap="round"
								strokeLinejoin="round"
							/>
						</svg>
					</div>
				);
			default:
				return null;
		}
	};

	// Function to get title based on toast type
	const getToastTitle = (type) => {
		switch (type) {
			case "success":
				return "Successo";
			case "error":
				return "Errore";
			default:
				return "Notifica";
		}
	};

	return (
		<div className="toasts_component">
			<div className="toasts-container">
				{/* Success Toast */}
				<div
					className="toast-item toast-success toast"
					id="toast-success"
					role="alert"
					aria-live="assertive"
					aria-atomic="true"
					data-bs-autohide="true"
					data-bs-delay="5000">
					<div className="toast-content">
						<div className="toast-header">
							{getToastIcon("success")}
							<span className="toast-title">{getToastTitle("success")}</span>

							<button
								className="toast-close"
								type="button"
								data-bs-dismiss="toast"
								aria-label="Chiudi notifica">
								<svg
									width="16"
									height="16"
									viewBox="0 0 24 24"
									fill="none"
									xmlns="http://www.w3.org/2000/svg">
									<path
										d="M18 6L6 18"
										stroke="currentColor"
										strokeWidth="2.5"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
									<path
										d="M6 6L18 18"
										stroke="currentColor"
										strokeWidth="2.5"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
								</svg>
							</button>
						</div>

						<div className="toast-body">{messageType === "success" ? messageData : ""}</div>
					</div>

					<div className="toast-progress">
						<div className="toast-progress-bar" />
					</div>
				</div>

				{/* Error Toast */}
				<div
					className="toast-item toast-error toast"
					id="toast-error"
					role="alert"
					aria-live="assertive"
					aria-atomic="true"
					data-bs-autohide="true"
					data-bs-delay="5000">
					<div className="toast-content">
						<div className="toast-header">
							{getToastIcon("error")}
							<span className="toast-title">{getToastTitle("error")}</span>

							<button
								className="toast-close"
								type="button"
								data-bs-dismiss="toast"
								aria-label="Chiudi notifica">
								<svg
									width="16"
									height="16"
									viewBox="0 0 24 24"
									fill="none"
									xmlns="http://www.w3.org/2000/svg">
									<path
										d="M18 6L6 18"
										stroke="currentColor"
										strokeWidth="2.5"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
									<path
										d="M6 6L18 18"
										stroke="currentColor"
										strokeWidth="2.5"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
								</svg>
							</button>
						</div>

						<div className="toast-body">{messageType === "error" ? messageData : ""}</div>
					</div>

					<div className="toast-progress">
						<div className="toast-progress-bar" />
					</div>
				</div>
			</div>
		</div>
	);
}
