export const klaroConfig = {
	// With the 0.7.0 release we introduce a 'version' parameter that will make
	// it easier for us to keep configuration files backwards-compatible in the future.
	version: 1,

	// You can customize the ID of the DIV element that Klaro will create
	// when starting up. If undefined, Klaro will use 'klaro'.
	elementID: "klaro",

	// You can override CSS style variables here. For IE11, Klaro will
	// dynamically inject the variables into the CSS. If you still consider
	// supporting IE9-10 (which you probably shouldn't) you need to use Klaro
	// with an external stylesheet as the dynamic replacement won't work there.
	//styling: {
	//    theme: ['light', 'top', 'wide'],
	//},

	// Setting this to true will keep Klaro from automatically loading itself
	// when the page is being loaded.
	noAutoLoad: false,

	// Setting this to true will render the descriptions of the consent
	// modal and consent notice are HTML. Use with care.
	htmlTexts: true,

	// Setting 'embedded' to true will render the Klaro modal and notice without
	// the modal background, allowing you to e.g. embed them into a specific element
	// of your website, such as your privacy notice.
	embedded: false,

	// You can group services by their purpose in the modal. This is advisable
	// if you have a large number of services. Users can then enable or disable
	// entire groups of services instead of having to enable or disable every service.
	groupByPurpose: true,

	// How Klaro should store the user's preferences. It can be either 'cookie'
	// (the default) or 'localStorage'.
	storageMethod: "cookie",

	// You can customize the name of the cookie that Klaro uses for storing
	// user consent decisions. If undefined, Klaro will use 'klaro'.
	cookieName: "klaro",

	// You can also set a custom expiration time for the Klaro cookie.
	// By default, it will expire after 120 days.
	cookieExpiresAfterDays: 365,

	// You can change to cookie domain for the consent manager itself.
	// Use this if you want to get consent once for multiple matching domains.
	// If undefined, Klaro will use the current domain.
	//cookieDomain: '.github.com',

	// You can change to cookie path for the consent manager itself.
	// Use this to restrict the cookie visibility to a specific path.
	// If undefined, Klaro will use '/' as cookie path.
	//cookiePath: '/',

	// Defines the default state for services (true=enabled by default).
	default: false,

	// If "mustConsent" is set to true, Klaro will directly display the consent
	// manager modal and not allow the user to close it before having actively
	// consented or declines the use of third-party services.
	mustConsent: false,

	// Show "accept all" to accept all services instead of "ok" that only accepts
	// required and "default: true" services
	acceptAll: true,

	// replace "decline" with cookie manager modal
	hideDeclineAll: true,

	// hide "learnMore" link
	hideLearnMore: false,

	// show cookie notice as modal
	noticeAsModal: true,

	// You can also remove the 'Realized with Klaro!' text in the consent modal.
	// Please don't do this! We provide Klaro as a free open source tool.
	// Placing a link to our website helps us spread the word about it,
	// which ultimately enables us to make Klaro! better for everyone.
	// So please be fair and keep the link enabled. Thanks :)
	//disablePoweredBy: true,

	// you can specify an additional class (or classes) that will be added to the Klaro `div`
	//additionalClass: 'my-klaro',

	// You can define the UI language directly here. If undefined, Klaro will
	// use the value given in the global "lang" variable. If that does
	// not exist, it will use the value given in the "lang" attribute of your
	// HTML tag. If that also doesn't exist, it will use 'en'.
	//lang: 'en',

	// You can overwrite existing translations and add translations for your
	// service descriptions and purposes. See `src/translations/` for a full
	// list of translations that can be overwritten:
	// https://github.com/KIProtect/klaro/tree/master/src/translations

	// Example config that shows how to overwrite translations:
	// https://github.com/KIProtect/klaro/blob/master/src/configs/i18n.js
	translations: {
		// translationsed defined under the 'zz' language code act as default
		// translations.
		//zz: {
		//privacyPolicyUrl: 'istitutional/4743/privacy',
		//},
		it: {
			consentModal: {
				title: "Servizi che desideriamo utilizzare",
				description:
					"Qui puoi valutare e personalizzare i servizi che vorremmo utilizzare su questo sito web. Puoi abilitare o disabilitare i servizi come meglio credi.",
			},
			purposes: {
				marketing: {
					description: "",
				},
				functional: {
					title: "Funzionali",
					description:
						"Questi servizi sono essenziali per il corretto funzionamento di questo sito web. Non puoi disattivarli qui perché altrimenti il servizio non funzionerebbe correttamente.",
				},
			},
			privacyPolicy: {
				text: "Per saperne di più, leggi la nostra {privacyPolicy}.",
			},
			acceptSelected: "Accettare selezionati",
			ok: "Acconsento",
			privacyPolicyUrl: "institutional/3607/privacy/",
			service: {
				disableAll: {
					description: "Utilizza questo interruttore per attivare o disattivare tutti i servizi.",
				},
				purpose: "Scopo",
			},
			consentNotice: {
				description:
					'Utilizziamo cookie tecnici per far funzionare correttamente il sito web. Inoltre, previo Suo consenso, tale tecnologia è utilizzata per analizzare il nostro traffico e per inviare messaggi pubblicitari in linea con le preferenze manifestate nell’ambito della navigazione in rete e/o per effettuare analisi e monitoraggio dei comportamenti dei visitatori. <br/>Ulteriori informazioni sono disponibili qui <strong>{privacyPolicy}</strong>.<br/>Per impostare le Sue preferenze cliccare sul bottone <strong>"Gestisci preferenze"</strong>, dove può anche ottenere ulteriori informazioni sulle terze parti. Facendo click su <strong>“Acconsento”</strong>, accetta l’installazione di tutti i cookie e l’utilizzo degli altri strumenti di tracciamento. Chiudendo il banner rifiuta l’installazione di tutti i cookie, ad eccezione di quelli tecnici.',
				learnMore: "Gestisci preferenze",
			},
		},
		en: {
			purposes: {
				marketing: {
					description: "",
				},
			},
			service: {
				purpose: "Purpose",
			},
			privacyPolicyUrl: "institutional/3607/privacy/?lan=en",
			functional: {
				title: "Functional",
			},
			consentNotice: {
				description:
					'We use technical cookies to make the website work properly. Furthermore, subject to your consent, this technology is used to analyze our traffic and to send advertising messages in line with the preferences shown in the context of surfing the net and/or to perform analysis and monitoring of visitor behaviour. <br/>Further information is available here <strong>{privacyPolicy}</strong>.<br/>To set your preferences by clicking on the <strong>"Manage Preferences"</strong> button, where you can also obtain further information on third parties. By clicking on <strong>“I agree”</strong>, you accept the installation of all cookies and the use of other tracking tools. By closing the banner, you refuse the installation of all cookies, except for the technical ones.',
				learnMore: "Manage preferences",
			},
			consentModal: {
				description:
					"Here you can assess and customize the services that we would like to use on this website. Enable or disable services as you see fit.",
			},
		},
	},

	// This is a list of third-party services that Klaro will manage for you.
	services: [
		{
			name: "facebook",
			default: true,
			purposes: ["marketing"],
			// cookies: [
			//     ['prova', '/', 'localhost'],
			// ],
		},
		{
			name: "linkedin",
			default: true,
			purposes: ["marketing"],
		},
		{
			name: "googleAnalytics",
			default: true,
			title: "Google Analytics",
			purposes: ["analytics"],
		},
		{
			name: "functional",
			default: true,
			required: true,
			purposes: ["functional"],
		},
	],
};
