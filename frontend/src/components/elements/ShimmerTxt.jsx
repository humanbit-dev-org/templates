"use client"; // marks module for full browser execution
//
// import { ShimmerTxtComponent } from "@/components/elements/ShimmerTxt"; // File import statement

import { useClient } from "@/providers/Client";
import { useTranslate } from "@/providers/Translate";

// ===============================================
// ## ############################################
// ===============================================

export function ShimmerTxtComponent() {
	const { dict, lang } = useTranslate();
	const csr = useClient();
	// const base = buildTranslateBase(csr, {
	// 	dict,
	// 	lang,
	// 	bancaAbi: csr.user?.banca?.banca_abi,
	// });
	// const uiLoading = resolveTranslateString({ ...base, code: "ui_loading" });

	return (
		<div
			role="status"
			className="shimmer_txt_component max-w-sm animate-pulse">
			<div className="h-2 bg-gray-200 rounded-full dark:bg-gray-700 max-w-[360px] mb-2.5"></div>
			<div className="h-2 bg-gray-200 rounded-full dark:bg-gray-700 mb-2.5"></div>
			<div className="h-2 bg-gray-200 rounded-full dark:bg-gray-700 max-w-[330px] mb-2.5"></div>
			<div className="h-2 bg-gray-200 rounded-full dark:bg-gray-700 max-w-[300px] mb-2.5"></div>
			<div className="h-2 bg-gray-200 rounded-full dark:bg-gray-700 max-w-[360px]"></div>
			<span className="sr-only">{uiLoading.title}</span>
		</div>
	);
}
