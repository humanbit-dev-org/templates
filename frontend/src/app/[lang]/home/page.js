// Server-Side Rendering (React generates HTML before hydration)

// File import statements:
// import HomePage from "@/page/home";

// 1. Core imports (React & Next.js)
// import React, { createContext, useCallback, useContext, useEffect, useMemo, useReducer, useRef, useState } from "react";

// 2. External imports (third-party libraries)
// import axios from "axios";
// import clsx from "clsx";
// import useSWR from "swr";
// import { AnimatePresence, motion } from "framer-motion";
// import { signIn, signOut, useSession } from "next-auth/react";

// 3. Absolute internal (`@/` alias)
import { ContentHomeComponent } from "@/components/blocks/Contents";
import { ContentProvider } from "@/providers/Contents";
import { FooterComponent } from "@/components/footers/FooterSidebar";
import { getDictionary } from "@/app/dictionaries";
import { HeroHomeComponent } from "@/components/blocks/Heroes";
import { NavLogoTopComponent } from "@/navbars/NavLogoTop";
import { TimelineStoriaComponent } from "@/components/blocks/Timelines";
import { TranslateProvider } from "@/providers/Translates";

// 4. Relative internal (same directory)
import "./page.scss";

// ===============================================
// ## ############################################
// ===============================================

const baseUrl = process.env.NEXT_PUBLIC_ASSETS_URL;

export default async function HomePage({ params }) {
	const { lang } = await params;

	const translates = await getDictionary(lang);

	// Riflessioni su Milano
	const apiUrlThoughts = `${baseUrl}/api/${lang}/home/thoughts`;
	const responseThoughts = await fetch(apiUrlThoughts, {
		method: "GET",
		credentials: "include",
		headers: {
			"Content-Type": "application/json",
			"locale": lang,
		},
	});
	const thoughts = await responseThoughts.json();

	// La Nostra Storia
	const apiUrlChapters = `${baseUrl}/api/${lang}/chapters-links`;
	const responseChapters = await fetch(apiUrlChapters, {
		method: "GET",
		credentials: "include",
		headers: {
			"Content-Type": "application/json",
			"locale": lang,
		},
	});
	const chapters_links = await responseChapters.json();

	const heroResponse = await fetch(`${baseUrl}/api/home/hero`, {
		method: "GET",
		credentials: "include",
		headers: {
			"Content-Type": "application/json",
		},
	});

	const heroData = await heroResponse.json();

	return (
		<TranslateProvider lang={lang} translates={translates}>
			<div className="home_page">
				<div className="space_wrap mb-0">
					<div className="cont_space_1 bg_color_first position-xl-sticky top-xl-0" style={{ zIndex: "1" }}>
						<div className="cont_mw_1">
							<NavLogoTopComponent />
						</div>
					</div>

					<ContentProvider contents={chapters_links}>
						<TimelineStoriaComponent />
					</ContentProvider>

					<HeroHomeComponent heroData={heroData} />

					<div className="col-11 col-xl-6">
						<div className="cont_space_1">
							<div className="cont_mw_2">
								<a
									className="el_btn btn_bg_second btn_hover mb-8 position-fixed bottom-0"
									href="/la-nostra-storia"
									style={{ zIndex: "9" }}>
									{translates?.["home"]?.["button_la_nostra_storia"]?.[lang] ??
										translates?.["home"]?.["button_la_nostra_storia"]?.[`text_${lang}`] ??
										"Esplora la nostra storia"}
								</a>
							</div>
						</div>
					</div>

					<div className="row">
						<div className="block_cont obj_grid col-12 col-xl-5" />

						<div className="block_cont color_white bg_color_first col-12 col-xl-7 full_height">
							<div className="cont_space_1 py-10">
								<div className="cont_mw_1">
									<p className="el_txt lora p mb-4">
										{translates?.["home"]?.["intro_home_1"]?.[`text_${lang}`] ??
											"La storia di Assolombarda è un racconto di visione, impegno e trasformazione. Questo libro celebra oltre un secolo di vita dell’Associazione, un viaggio che ha accompagnato Milano, la Lombardia e l’Italia lungo i momenti più intensi e significativi dello sviluppo economico, sociale e culturale."}
									</p>

									<p className="el_txt lora p mb-4">
										{translates?.["home"]?.["intro_home_2"]?.[`text_${lang}`] ??
											"Attraverso le parole dei nostri presidenti e le riflessioni sulla città di Milano da parte di alcune delle menti più significative del nostro tempo, emergono i tratti distintivi di un’Associazione che ha saputo evolversi interpretando gli aspetti principali del cambiamento. Dall’inaugurazione del Palazzo via Pantano 9 firmato Gio Ponti, fino ai giorni nostri, l’Associazione si è raccontata attraverso azioni, eventi e una comunicazione capace di mettere in connessione imprese, istituzioni e comunità."}
									</p>

									<p className="el_txt lora p mb-4">
										{translates?.["home"]?.["intro_home_3"]?.[`text_${lang}`] ??
											"Come Direttore di Fondazione Assolombarda ho fortemente sostenuto la nascita di questo prodotto editoriale perché ripercorrere la storia di Assolombarda significa consolidare un’identità condivisa, un atto di consapevolezza che lega il passato alle azioni presenti e future. Perché la storia di Assolombarda è, prima di tutto, la storia di chi crede nella forza delle idee, nell’intraprendenza, nella responsabilità del mettersi in gioco e nel contributo che ognuno può offrire per costruire una società migliore, inclusiva e sostenibile."}
									</p>

									<p className="el_txt lora p mt-5">
										{translates?.["home"]?.["intro_home_author"]?.[`text_${lang}`] ??
											"Alessandro Scarabelli, Direttore Generale Assolombarda e Fondazione Assolombarda"}
									</p>
								</div>
							</div>
						</div>
					</div>

					<ContentProvider contents={thoughts}>
						<ContentHomeComponent />
					</ContentProvider>

					<div className="sect_cont d-xl-none">
						<FooterComponent />
					</div>
				</div>
			</div>
		</TranslateProvider>
	);
}
