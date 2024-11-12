<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:import href="../utilities/navbar_modules.xsl" />
	<xsl:import href="../utilities/footer_modules.xsl" />
	<xsl:import href="../utilities/modules.xsl" />
	<xsl:import href="../utilities/calendar.xsl" />
	<xsl:import href="../utilities/registration.xsl" />
	<xsl:decimal-format name="euro" decimal-separator="," grouping-separator="." NaN=" " />
	<xsl:output method="html" doctype-system="about:legacy-compat" />
	<xsl:param name="url-email" />
	<xsl:param name="member-id" />
	<xsl:param name="url-lan" />
	<xsl:param name="url-category" />
	<xsl:param name="member-role" />
	<xsl:param name="url-test" />
	<xsl:param name="cookie-xsrf-token" />
	<xsl:param name="timestamp" />
	<xsl:param name="url-activation" />
	<xsl:param name="url-code" />
	<xsl:param name="url-step" />
	<xsl:param name="url-confirm" />
	<xsl:param name="url-login" />
	<xsl:param name="url-register" />
	<xsl:param name="url-logout" />
	<xsl:param name="url-error" />
	<xsl:param name="url-reg" />
	<xsl:param name="url-emailchange" />
	<xsl:param name="url-recovery" />
	<xsl:param name="title" />
	<xsl:param name="id" />
	<xsl:variable name="lan">
		<xsl:choose>
			<xsl:when test="$url-lan = 'en'">en</xsl:when>
			<xsl:when test="$url-lan = 'de'">de</xsl:when>
			<xsl:otherwise>it</xsl:otherwise>
		</xsl:choose>
	</xsl:variable>
	<xsl:variable name="lanurl">
		<xsl:choose>
			<xsl:when test="$url-lan = 'en'">?lan=en</xsl:when>
			<xsl:when test="$url-lan = 'de'">?lan=de</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>
	<xsl:variable name="lanexp">
		<xsl:choose>
			<xsl:when test="$url-lan = 'en'">english</xsl:when>
			<xsl:when test="$url-lan = 'de'">deutsche</xsl:when>
			<xsl:otherwise>italian</xsl:otherwise>
		</xsl:choose>
	</xsl:variable>
	<xsl:variable name="lanextended">
		<xsl:choose>
			<xsl:when test="$url-lan != ''">
				<xsl:value-of select="$url-lan" />
			</xsl:when>
			<xsl:otherwise>it</xsl:otherwise>
		</xsl:choose>
	</xsl:variable>
	<xsl:variable name="titlelan">title-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="metatitlelan">meta-title-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="metadesclan">meta-description-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="namelan">name-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="subtitlelan">subtitle-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="bodylan">body-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="textlan">text-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="desclan">description-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="abslan">abstract-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="audiolan">audio-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="ticketreducedlan">ticket-price-reduced-details-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="ticketfreelan">ticket-free-details-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="dateliberelan">date-libere-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="boxinfolan">box-info-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="is-logged-in" select="/data/events/login-info/@logged-in" />
	<xsl:template match="/">

	<xsl:comment><![CDATA[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]]]></xsl:comment>
	<xsl:comment><![CDATA[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]]]></xsl:comment>
	<xsl:comment><![CDATA[if IE 8]>         <html class="no-js lt-ie9"> <![endif]]]></xsl:comment>
	<xsl:comment><![CDATA[if gt IE 8]><html class="no-js"> <![endif]]]></xsl:comment>

	<html lang="{$lanextended}">
		<head>
			<!-- <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" /> -->
			<meta name="viewport" content="height=device-height, width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no, target-densitydpi=device-dpi, shrink-to-fit=no" />


			<!-- no index conditions and pages -->
			<!-- <xsl:if test="($url-test = 'yes') or ($current-page = '404') or ($current-page = 'tour') or ($url-confirmed = 'yes')"> -->
			<xsl:if test="($current-page = '404') or ($current-page = 'tour')">
				<meta name="robots" content="noindex" />
			</xsl:if>


			<xsl:choose>
				<xsl:when test="($url-lan = 'en')">

					<xsl:choose>
						<!-- static pages meta -->

						<!-- index -->
						<xsl:when test="($current-page = 'index')">
							<title>The oldest library in the world | Biblioteca Capitolare di Verona</title>
							<meta name="description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
							<meta property="og:url" content="{$current-url}/" />
							<meta property="og:type" content="article" />
							<meta property="og:title" content="The oldest library in the world | The Capitulary Library of Verona" />
							<meta property="og:description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
							<meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
						</xsl:when>

						<!-- exhibitions -->
						<xsl:when test="($current-page = 'exhibitions')">
							<title>Exhibitions Archives | Biblioteca Capitolare di Verona</title>
							<meta name="description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
							<meta property="og:url" content="{$current-url}/" />
							<meta property="og:type" content="article" />
							<meta property="og:title" content="Exhibitions Archives | The Capitulary Library of Verona" />
							<meta property="og:description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
							<meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
						</xsl:when>

						<!-- places -->
						<xsl:when test="($current-page = 'places')">
							<title>The Places | Biblioteca Capitolare di Verona</title>
							<meta name="description" content="The buildings that house the Capitulary Library bear the signs of Verona's millenary history" />
							<meta property="og:url" content="{$current-url}/" />
							<meta property="og:type" content="article" />
							<meta property="og:title" content="The Places | The Capitulary Library of Verona" />
							<meta property="og:description" content="The buildings that house the Capitulary Library bear the signs of Verona's millenary history" />
							<meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
						</xsl:when>

						<!-- tours -->
						<xsl:when test="($current-page = 'tours')">
							<title>Tours | Biblioteca Capitolare di Verona</title>
							<meta name="description" content="Even our most requested event cannot be missed in 2023: the Walks with the Prefect return with three new dates in the months of January and February" />
							<meta property="og:url" content="{$current-url}/" />
							<meta property="og:type" content="article" />
							<meta property="og:title" content="Tours | The Capitulary Library of Verona" />
							<meta property="og:description" content="Even our most requested event cannot be missed in 2023: the Walks with the Prefect return with three new dates in the months of January and February" />
							<meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
						</xsl:when>

						<!-- ######################### -->

						<!-- dynamic pages meta -->

						<!-- exhibition, place, institutional -->
						<xsl:when test="($current-page = 'exhibition') or ($current-page = 'place') or ($current-page = 'institutional')">
							<xsl:choose>
								<xsl:when test="$title != ''">
									<title><xsl:value-of select="/data/*/entry/*[local-name()=$titlelan]" /> | Biblioteca Capitolare di Verona</title>
									<meta name="description" content="{/data/*/entry/*[local-name()=$abslan]}" />
									<meta property="og:url" content="{$current-url}/" />
									<meta property="og:type" content="article" />
									<meta property="og:title" content="{/data/*/entry/*[local-name()=$titlelan]}" />
									<meta property="og:description" content="{/data/*/entry/*[local-name()=$abslan]}" />
									<meta property="og:image" content="{$root}/image/1/1200/0{/data/*/entry/image/@path}/{/data/*/entry/image/filename}" />
								</xsl:when>

								<xsl:otherwise>
									<title>The oldest library in the world | The Capitulary Library of Verona</title>
									<meta name="description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)1969)" />
									<meta property="og:url" content="{$current-url}/" />
									<meta property="og:type" content="article" />
									<meta property="og:title" content="The oldest library in the world | The Capitulary Library of Verona" />
									<meta property="og:description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
									<meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
								</xsl:otherwise>
							</xsl:choose>
						</xsl:when>

						<xsl:otherwise>
							<title>The oldest library in the world | The Capitulary Library of Verona</title>
							<meta name="description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
							<meta property="og:url" content="{$current-url}/" />
							<meta property="og:type" content="article" />
							<meta property="og:title" content="The oldest library in the world | The Capitulary Library of Verona" />
							<meta property="og:description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
							<meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
						</xsl:otherwise>
					</xsl:choose>
				</xsl:when>

				<xsl:otherwise>
					<xsl:choose>
						<!-- static pages meta -->

						<!-- index -->
						<xsl:when test="($current-page = 'index')">
							<title>La biblioteca più antica al mondo in attività | Biblioteca Capitolare di Verona</title>
							<meta name="description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
							<meta property="og:url" content="{$current-url}/" />
							<meta property="og:type" content="article" />
							<meta property="og:title" content="La biblioteca più antica del mondo | Biblioteca Capitolare di Verona" />
							<meta property="og:description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
							<meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
						</xsl:when>

						<!-- exhibitions -->
						<xsl:when test="($current-page = 'exhibitions')">
							<title>Esposizioni Archivi | Biblioteca Capitolare di Verona</title>
							<meta name="description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
							<meta property="og:url" content="{$current-url}/" />
							<meta property="og:type" content="article" />
							<meta property="og:title" content="Esposizioni Archivi | Biblioteca Capitolare di Verona" />
							<meta property="og:description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
							<meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
						</xsl:when>

						<!-- places -->
						<xsl:when test="($current-page = 'places')">
							<title>I Luoghi | Biblioteca Capitolare di Verona</title>
							<meta name="description" content="Gli edifici che ospitano la Biblioteca Capitolare portano i segni della storia millenaria di Verona" />
							<meta property="og:url" content="{$current-url}/" />
							<meta property="og:type" content="article" />
							<meta property="og:title" content="I Luoghi | Biblioteca Capitolare di Verona" />
							<meta property="og:description" content="Gli edifici che ospitano la Biblioteca Capitolare portano i segni della storia millenaria di Verona" />
							<meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
						</xsl:when>

						<!-- tours -->
						<xsl:when test="($current-page = 'tours')">
							<title>Prenota la tua visita | Biblioteca Capitolare di Verona</title>
							<meta name="description" content="Anche il nostro evento più richiesto non può mancare nel 2023: le Passeggiate con il Prefetto ritornano con tre nuove date nei mesi di gennaio e febbraio" />
							<meta property="og:url" content="{$current-url}/" />
							<meta property="og:type" content="article" />
							<meta property="og:title" content="Passeggiate | Biblioteca Capitolare di Verona" />
							<meta property="og:description" content="Anche il nostro evento più richiesto non può mancare nel 2023: le Passeggiate con il Prefetto ritornano con tre nuove date nei mesi di gennaio e febbraio" />
							<meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
						</xsl:when>
						<xsl:when test="($current-page = 'event')">
							<title>Organizza il tuo evento | Biblioteca Capitolare di Verona</title>
							<meta name="description" content="Organizza il tuo evento privato negli splendidi spazi delle Fondazione" />
							<meta property="og:url" content="{$current-url}/" />
							<meta property="og:type" content="article" />
							<meta property="og:title" content="Organizza il tuo evento | Biblioteca Capitolare di Verona" />
							<meta property="og:description" content="Organizza il tuo evento privato negli splendidi spazi delle Fondazione" />
							<meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
						</xsl:when>

						<!-- ######################### -->

						<!-- dynamic pages meta -->

						<!-- exhibition, place, institutional -->
						<xsl:when test="($current-page = 'exhibition') or ($current-page = 'place') or ($current-page = 'institutional')">
							<xsl:choose>
								<xsl:when test="$title != ''">
									<title><xsl:value-of select="/data/*/entry/*[local-name()=$titlelan]" /> | Biblioteca Capitolare di Verona</title>
									<meta name="description" content="{/data/*/entry/*[local-name()=$abslan]}" />
									<meta property="og:url" content="{$current-url}/" />
									<meta property="og:type" content="article" />
									<meta property="og:title" content="{/data/*/entry/*[local-name()=$titlelan]}" />
									<meta property="og:description" content="{/data/*/entry/*[local-name()=$abslan]}" />
									<meta property="og:image" content="{$root}/image/1/1200/0{/data/*/entry/image/@path}/{/data/*/entry/image/filename}" />
								</xsl:when>

								<xsl:otherwise>
									<title>La biblioteca più antica del mondo | Biblioteca Capitolare di Verona</title>
									<meta name="description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
									<meta property="og:url" content="{$current-url}/" />
									<meta property="og:type" content="article" />
									<meta property="og:title" content="La biblioteca più antica del mondo | Biblioteca Capitolare di Verona" />
									<meta property="og:description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
									<meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
								</xsl:otherwise>
							</xsl:choose>
						</xsl:when>

						<xsl:otherwise>
							<title>La biblioteca più antica al mondo in attività | Biblioteca Capitolare di Verona</title>
							<meta name="description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
							<meta property="og:url" content="{$current-url}/" />
							<meta property="og:type" content="article" />
							<meta property="og:title" content="La biblioteca più antica al mondo in attività | Biblioteca Capitolare di Verona" />
							<meta property="og:description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
							<meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
						</xsl:otherwise>
					</xsl:choose>
				</xsl:otherwise>
			</xsl:choose>


			<!-- favicon -->
			<link rel="apple-touch-icon" sizes="180x180" href="{$root}/favicon/apple-touch-icon.png?v=1.2" />
			<link rel="icon" type="image/png" sizes="32x32" href="{$root}/favicon/favicon-32x32.png?v=1.2" />
			<link rel="icon" type="image/png" sizes="16x16" href="{$root}/favicon/favicon-16x16.png?v=1.2" />
			<link rel="manifest" href="{$root}/favicon/site.webmanifest?v=1.2" />
			<link rel="shortcut icon" href="{$root}/favicon/favicon.ico?v=1.2" />
			<meta name="apple-mobile-web-app-title" content="Biblioteca Capitolare di Verona" />
			<meta name="application-name" content="Biblioteca Capitolare di Verona" />
			<meta name="msapplication-TileColor" content="#000000" />
			<meta name="msapplication-config" content="{$root}/favicon/browserconfig.xml?v=1.2" />
			<meta name="theme-color" content="#ffffff" />

			<!-- <link rel="apple-touch-icon" sizes="180x180" href="{$root}/favicon/apple-touch-icon.png" />
			<link rel="icon" type="image/png" sizes="32x32" href="{$root}/favicon/favicon-32x32.png" />
			<link rel="icon" type="image/png" sizes="16x16" href="{$root}/favicon/favicon-16x16.png" />
			<link rel="manifest" href="{$root}/favicon/site.webmanifest" />
			<link rel="mask-icon" href="{$root}/favicon/safari-pinned-tab.svg" color="#000000" />
			<link rel="shortcut icon" href="{$root}/favicon/favicon.ico" />
			<meta name="msapplication-TileColor" content="#000000" />
			<meta name="msapplication-config" content="{$root}/favicon/browserconfig.xml" /> -->
			<meta name="theme-color" content="#ffffff" />
			<!-- fine favicon -->

			<!-- BOOTSTRAP CDN -->
			<!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous" /> -->

			<!-- <link href="{$workspace}/static/css/bootstrap.min.css" rel="stylesheet" /> -->
			<!--link href="{$workspace}/static/css/style_default.css" rel="stylesheet"/-->
			<!--link href="{$workspace}/static/css/mdb.min.css" rel="stylesheet"/-->
			<!-- <link href="{$workspace}/static/css/slick.css" rel="stylesheet" /> -->

			<link href="{$workspace}/static/bessex/css/bessex_humanbit.css?v5.2.3" rel="stylesheet" />
			<link href="{$workspace}/static/css/plugin.min.css" rel="stylesheet" />



			<!-- <link href="{$workspace}/static/css/style_gabriel.css" rel="stylesheet" /> -->
			<!-- <link href="{$workspace}/static/css/style.css" rel="stylesheet" /> -->
			<!-- <link href="{$workspace}/static/css/style_gabriel_all.css" rel="stylesheet" /> -->
			<link href="{$workspace}/static/css/style.css?v1.1" rel="stylesheet" />
			<link href="{$workspace}/static/css/klaro.css" rel="stylesheet"/>

			<!-- google fonts (download and put them in utilities) -->
			<link rel="preconnect" href="https://fonts.googleapis.com" />
			<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="crossorigin" />
			<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Display:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600;1,700;1,800&amp;family=Roboto+Mono:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&amp;display=swap" rel="stylesheet" />


			<!-- <link href="{$workspace}/static/css/style.min.css" rel="stylesheet" /> -->


			<!--link href="{$workspace}/static/css/bootstrap-boost_humanbit-ponzano.css" rel="stylesheet"/-->

			<link href="{$workspace}/static/fonts/fontawesome-pro-6.0.0-beta2-web/css/all.min.css" rel="stylesheet" />

			<!-- Custom styles for this template -->
			<script>
			  (function (s, e, n, d, er) {
				s['Sender'] = er;
				s[er] = s[er] || function () {
				  (s[er].q = s[er].q || []).push(arguments)
				}, s[er].l = 1 * new Date();
				var a = e.createElement(n),
					m = e.getElementsByTagName(n)[0];
				a.async = 1;
				a.src = d;
				m.parentNode.insertBefore(a, m)
			  })(window, document, 'script', 'https://cdn.sender.net/accounts_resources/universal.js', 'sender');
			  sender('a46dd090664170')
			</script>
			<!-- Google tag (gtag.js) --> 
			<script async="async" src="https://www.googletagmanager.com/gtag/js?id=AW-16532604791"></script> 
			<script>
				window.dataLayer = window.dataLayer || []; 
				function gtag(){dataLayer.push(arguments);} 
				gtag('js', new Date()); 
				gtag('config', 'AW-16532604791'); 
			</script>
			


			<xsl:call-template name="head" />
		</head>

		<body class="bg_color_white">
			<xsl:if test="($current-page = 'space-info')"><xsl:attribute name="class">bg_color_black</xsl:attribute></xsl:if>
			<!-- <div class="preloader" id="preloader">
				<div class="loader">
					<hr class="hr_preloader" />
				</div>
			</div> -->

			<!-- Add the shimmering effect to your placeholders -->
			<!-- <xsl:call-template name="shimmering_module" /> -->

			<div class="container_humanbit_overflow scrollbar_spacing" id="page">

				<!-- <button id="add-webapp" class="btnBgP text-center font-weight-bold mb-4 py-1 px-3 d-lg-none" style="top: 15%; position: fixed;">Scarica l'App!</button> -->

				<!-- Navbar slide left -->
				<xsl:if test="($current-page != '404')">
					<xsl:call-template name="navbar_slide_right" />
				</xsl:if>

				<div class="container_humanbit_structure container-fluid">
					<xsl:call-template name="page_structure" />
					<xsl:call-template name="login_modal" />
					<xsl:call-template name="forgot_pw" />
					<xsl:call-template name="register_modal" />
					<xsl:call-template name="change_pw_modal" />
					<xsl:call-template name="positiveMessagebBefAct" />
					<xsl:call-template name="service_messages" />
					<xsl:call-template name="newsletter" />
					<!-- Credits -->
					<xsl:call-template name="modal_credits_module" />
				</div>

				<!-- Footer -->
				<xsl:if test="($current-page != '404')">
					<xsl:call-template name="footer_left_right" />
				</xsl:if>




			</div>

			<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script> -->

			<!-- <script src="{$workspace}/static/bootstrap/dist/js/jquery-3.5.1.js"></script> -->
			<!-- <script src="{$workspace}/static/bootstrap/dist/js/bootstrap.min.js"></script> -->
			<!-- <script src="{$workspace}/static/bootstrap/dist/js/popper.min.js"></script> -->
			<!-- <script src="{$workspace}/static/bootstrap/dist/js/mdb.min.js"></script> -->
			<!-- <script src="{$workspace}/static/fonts/fontawesome-pro-6.0.0-beta2-web/js/all.min.js"></script> -->
			<script src="{$workspace}/static/js/plugin.min.js"></script>
			<script src="{$workspace}/static/js/main.js?v1.07"></script>
			<script src="{$workspace}/static/js/main_humanbit.js?v1.06"></script>
			<!-- <script type="text/javascript" src="https://nibirumail.com/docs/scripts/nibirumail.cookie.min.js"></script> -->
			<script src="{$workspace}/static/js/slick_script.js" />
			<!-- <script src="{$workspace}/static/js/preloader.js" /> -->
			<!-- linetobe -->
			<xsl:if test="($current-page = 'linetobe-test')">
				<script defer="defer" crossorigin="anonymous" src="https://www.linetobe.com/workspace/static/bootstrap/js/linetobe.js?idm=idm46723354096336"></script>
			</xsl:if>
			<xsl:if test="($current-page = 'spid-test')">
				<script src="{$root}/service/spid/spid.min.js"></script>
				<script src="{$root}/service/spid/spid-request.js"></script>
				<link rel="stylesheet" href="{$root}/service/spid/spid.min.css" type="text/css" />
			</xsl:if>
			<xsl:if test="($current-page = 'humanbit-doc')">
				<script src="{$workspace}/static/js/echarts.min.js" />
				<script src="{$root}/documentation/js/echarts.js" />
				<script src="{$root}/documentation/js/codeStyler.js" />
				<script src="{$workspace}/static/js/push_notifications.js" />
			</xsl:if>
			<!-- medium editor -->
			<xsl:if test="($current-page = 'post-editing')">
				<script src="{$workspace}/static/js/medium-editor.min.js"></script>
				<link rel="stylesheet" href="{$workspace}/static/css/medium-editor.min.css" type="text/css" media="screen" charset="utf-8" />
				<script>
					$(document).ready(function () {
						var editor = new MediumEditor('.editable', {
							toolbar: {
								/* These are the default options for the toolbar,
								if nothing is passed this is what is used */
								allowMultiParagraphSelection: true,
								buttons: ['bold', 'italic', 'underline'],
								diffLeft: 0,
								diffTop: -10,
								firstButtonClass: 'medium-editor-button-first',
								lastButtonClass: 'medium-editor-button-last',
								relativeContainer: null,
								standardizeSelectionStart: false,
								static: false,
								/* options which only apply when static is true */
								align: 'left',
								sticky: false,
								updateOnEmptySelection: false
							}
						});
					});
				</script>
			</xsl:if>
			<!-- Meta Pixel Code -->

			<script>

			!function(f,b,e,v,n,t,s)
			{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
			n.callMethod.apply(n,arguments):n.queue.push(arguments)};
			if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
			n.queue=[];t=b.createElement(e);t.async=!0;
			t.src=v;s=b.getElementsByTagName(e)[0];
			s.parentNode.insertBefore(t,s)}(window, document,'script',
			'https://connect.facebook.net/en_US/fbevents.js');
			fbq('init', '704853421187414');
			fbq('track', 'PageView');

			</script>

			<noscript><img height="1" width="1" style="display:none"

			src="https://www.facebook.com/tr?id=704853421187414&amp;ev=PageView&amp;noscript=1"

			/></noscript>

			<!-- End Meta Pixel Code -->
			<script defer="defer" type="text/javascript" src="{$workspace}/static/js/klaro_config.js"></script>
			<script defer="defer" type="text/javascript" src="{$workspace}/static/js/klaro.js"></script>
			<script src="{$workspace}/static/js/web-app.js"></script>
			<!-- TIKTOK -->
			<script>
				!function (w, d, t) {
				w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i&lt;ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n&lt;ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&amp;lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};

				ttq.load('CEHF0IBC77U8PGLVR0D0');
				ttq.page();
				}(window, document, 'ttq');
			</script>
			<!-- slick slider -->
			<!-- <xsl:if test="$current-page = 'index'">
				<script>
					sliderForHome();
				</script>
			</xsl:if> -->
			<!-- mail forgot -->
			<xsl:choose>
				<xsl:when test="/data/member-order-open/error">
					<form id="member_start_order" method="post" action="{$current-url}/">
						<input name="xsrf" type="hidden" value="{$cookie-xsrf-token}" />
						<input name="fields[order-number]" type="hidden" value="{substring-after(generate-id(), 'idm')}" />
						<input name="fields[member]" type="hidden" value="{$member-id}" />
						<input name="fields[status]" type="hidden" value="open" />
						<input name="fields[order-date]" type="hidden" value="" />
						<input name="action[add-order]" type="hidden" value="submit" />
					</form>
					<script>addOrder('#member_start_order');</script>
				</xsl:when>
				<xsl:otherwise>
					<form class="d-none" id="form_add_soluzione" method="post" action="{$current-url}/">
						<input name="xsrf" type="hidden" value="{$cookie-xsrf-token}" />
						<input name="fields[amount]" type="hidden" value="1" />
						<input name="fields[price]" type="hidden" value="" />
						<input name="fields[price-tot]" type="hidden" value="" />
						<input name="fields[order]" type="hidden" value="{/data/member-order-open/entry/@id}" />
						<input name="fields[solution]" type="hidden" value="" />
						<input name="action[add-order-detail]" type="hidden" value="submit" />
					</form>
					<form class="d-none" id="form_delete_soluzione" method="post" action="{$current-url}/">
						<input name="xsrf" type="hidden" value="{$cookie-xsrf-token}" />
						<input name="id" type="hidden" value="" />
						<input name="fields[deleted]" type="hidden" value="yes" />
						<input name="action[add-order-detail]" type="hidden" value="submit" />
					</form>
					<form class="d-none" id="form_edit_soluzione" method="post" action="{$current-url}/">
						<input name="xsrf" type="hidden" value="{$cookie-xsrf-token}" />
						<input name="id" type="hidden" value="" />
						<input name="fields[amount]" type="hidden" value="" />
						<input name="fields[price]" type="hidden" value="" />
						<input name="fields[price-tot]" type="hidden" value="" />
						<input name="action[add-order-detail]" type="hidden" value="submit" />
					</form>
					<form class="d-none" id="form_edit_notes" method="post" action="{$current-url}/">
						<input name="xsrf" type="hidden" value="{$cookie-xsrf-token}" />
						<input name="id" type="hidden" value="" />
						<input name="fields[additional-notes]" type="hidden" value="" />
						<input name="action[add-order-detail]" type="hidden" value="submit" />
					</form>
				</xsl:otherwise>
			</xsl:choose>
			<xsl:choose>
				<xsl:when test="/data/events/members-generate-recovery-code/@result = 'success'">
					<script>
						var myModal = new bootstrap.Modal(document.getElementById('forgotSuccessModal'));
						myModal.show();
					</script>
				</xsl:when>
				<xsl:when test="/data/events/members-generate-recovery-code/@result = 'error'">
					<script>
						var myModal = new bootstrap.Modal(document.getElementById('forgotErrorModal'));
						myModal.show();
					</script>
				</xsl:when>
				<xsl:when test="$url-emailchange != ''">
					<script>
						var myModal = new bootstrap.Modal(document.getElementById('pwChangeModal'));
						myModal.show();
					</script>
				</xsl:when>
			</xsl:choose>
			<!-- event message -->
			<xsl:if test="((/data/events/*/@result = 'error') or (/data/events/@result = 'error')) and (not(/data/events/members-generate-recovery-code))">
				<script>
					var myModal = new bootstrap.Modal(document.getElementById('msgErrorModal'))
					myModal.show();
				</script>
			</xsl:if>
			<xsl:if test="(($url-change = 'yes') or (/data/events/*[local-name() != 'member-login-info']/@result = 'success') or (/data/events/@result = 'success')) and (not(/data/events/members-generate-recovery-code))">
				<script>
					var myModal = new bootstrap.Modal(document.getElementById('msgSuccessModal'))
					myModal.show();
				</script>
			</xsl:if>
			<xsl:if test="(($url-confirm = '1') and ($current-page = 'profile'))">
				<script>
					var myModal = new bootstrap.Modal(document.getElementById('msgSuccessModal'))
					myModal.show();
				</script>
			</xsl:if>
		</body>
	</html>

	</xsl:template>

</xsl:stylesheet>
