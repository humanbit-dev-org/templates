<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:param name="url-capitolo" />
	<xsl:param name="url-tipo" />
	<xsl:param name="url-sotto" />
	<xsl:param name="url-email2" />
	<xsl:param name="url-code" />
	<xsl:param name="url-change" />
	<xsl:param name="url-test" />
	<xsl:param name="url-page" />
	<xsl:param name="url-new" />
	<xsl:param name="url-richiesta" />
	<xsl:param name="categoria" />
	<xsl:param name="url-iddata" />

	<!-- When you call the template watch which parameter it expects (xsl: param subito sotto i template) and pass them onto `call-template`

		e.g.:

		template:
		<xsl:template name="module_1">
			<xsl:param name="nodecont" />
			<xsl:param name="nodemedia" />
		</xsl:template>

		call template:
		<xsl:call-template name="module_1">
			<xsl:with-param name="nodecont" select="'(name_data_source)'" />
			<xsl:with-param name="nodemedia" select="'(name_data_source)'" />
		</xsl:for-each> -->

	<!-- module 1 -->

	<xsl:template name="scripts_module">

		<script type="module">
			import * as script from "/workspace/static/js/entry_point.js"; // If using ES modules

			<!-- // >>> Load — START -->
			$(document).ready(function () {
				script.fxLoad();
				script.fxMove();
			});
			<!-- // <<<<< Load — END -->

			<!-- // >>> Scroll — START -->
			$(window).on("scroll", function () {
				script.fxMove();
			});
			<!-- // <<<<< Scroll — END -->
		</script>

		<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous" /> -->
		<!-- <script src="{$workspace}/static/bootstrap/dist/js/jquery-3.5.1.js" /> -->
		<!-- <script src="{$workspace}/static/bootstrap/dist/js/bootstrap.min.js" /> -->
		<!-- <script src="{$workspace}/static/bootstrap/dist/js/popper.min.js" /> -->
		<!-- <script src="{$workspace}/static/bootstrap/dist/js/mdb.min.js" /> -->
		<!-- <script src="{$workspace}/static/fonts/fontawesome-pro-6.0.0-beta2-web/js/all.min.js" /> -->
		<script src="{$workspace}/static/js/plugin.min.js" />
		<script src="{$workspace}/static/js/main.js?v1.07" />
		<script src="{$workspace}/static/js/main_humanbit.js?v1.08" />
		<!-- <script src="{$workspace}/static/js/html_text_editor_1.js" /> -->
		<!-- <script src="{$workspace}/static/js/html_text_editor_2.js" /> -->
		<!-- <script src="{$workspace}/static/js/html_text_editor_3.js" /> -->
		<!-- <script src="{$workspace}/static/trumbowyg/dist/trumbowyg.min.js" /> -->

		<script type="text/javascript" src="{$workspace}/static/wysihtml5/parser_rules/advanced.js" />
		<script type="text/javascript" src="{$workspace}/static/wysihtml5/dist/wysihtml5-0.4.0pre.js" />

		<script type="text/javascript">
			<!-- var editor = new wysihtml5.Editor("editor", { // id of textarea element
				toolbar:      "toolbar", // id of toolbar element
				parserRules:  wysihtml5ParserRules // defined in parser rules set
			}); -->

			// Assuming each pair of .editor and .toolbar is wrapped in a common container with class .editor-container
			var editorContainers = document.querySelectorAll('.editor-container');

			editorContainers.forEach(function(container) {
				var editorElement = container.querySelector('.editor');
				var toolbarElement = container.querySelector('.toolbar');

				if (editorElement &amp;&amp; toolbarElement) {
					var editor = new wysihtml5.Editor(editorElement, {
						toolbar: toolbarElement,
						parserRules: wysihtml5ParserRules // defined in file parser rules JavaScript
					});
				}
			});
		</script>

		<!-- <script type="text/javascript" src="https://nibirumail.com/docs/scripts/nibirumail.cookie.min.js" /> -->
		<script src="{$workspace}/static/js/slick_script.js"/>
		<script src="{$root}/service/intlTelInput/intlTelInput.min.js"/>
		<script src="{$root}/service/intlTelInput/utils.js"/>
		<script>
			$(document).ready(function (){
				var input = document.getElementsByClassName("prefix");
				if(input != null){
					let area_code = '<xsl:value-of select="/data/login-info/entry/area-code"/>';
					let area_code_country = '<xsl:value-of select="/data/login-info/entry/area-code-country"/>';
					input.forEach(function(this_input) {
						if(area_code_country != ''){
							window.intlTelInput(this_input, {
								initialCountry: area_code_country,
								separateDialCode: "true",
								preferredCountries: "",
								autoPlaceholder: "off",
								customContainer: "form-floating",
							});
						} else {
							window.intlTelInput(this_input, {
								initialCountry: "auto",
								geoIpLookup: function(success, failure) {
								$.get("https://ipinfo.io", function() {}, "jsonp").always(function(resp) {
									var countryCode = (resp &amp;&amp; resp.country) ? resp.country : "it";
									success(countryCode);
								});
								},
								separateDialCode: "true",
								preferredCountries: "",
								autoPlaceholder: "off",
								customContainer: "form-floating",
							});
						}
					});
					$('.iti__flag-container').attr('style', 'width:100%;');
					$('.iti').append('<label class="label_join small border-top-0 border-end-0 border-start-0" for="floatingCountry"><xsl:value-of select="/data/translate/entry[code = 'country_code']/*[local-name() = $lanextended]" /></label>');
				}
			});
		</script>
		<!-- <script src="{$workspace}/static/js/preloader.js" /> -->

		<!-- Linetobe -> ignorare -->
		<xsl:if test="($current-page = 'linetobe-test')">
			<script defer="defer" crossorigin="anonymous" src="https://www.linetobe.com/workspace/static/bootstrap/js/linetobe.js?idm=idm46723354096336" />
		</xsl:if>

		<!-- SPID -> scommentare se necessario implementare autenticazione tramite SPID -->
		<!-- <xsl:if test="($current-page = 'spid-test')">
			<script src="{$root}/service/spid/spid.min.js" />
			<script src="{$root}/service/spid/spid-request.js" />
			<link rel="stylesheet" href="{$root}/service/spid/spid.min.css" type="text/css" />
		</xsl:if> -->

		<!-- Documentazione Humanbit -> sono contenuti gli script relativi alla mappa, ai grafici dinamici, alla formattazione del codice e delle notifiche push. Copiare al bisogno, altrimenti commentare -->
		<xsl:if test="($current-page = 'humanbit-doc')">
			<script src="{$workspace}/static/js/echarts.min.js" />
			<script src="{$root}/documentation/js/echarts.js" />
			<script src="{$root}/documentation/js/codeStyler.js" />
			<script src="{$workspace}/static/js/push_notifications.js" />
		</xsl:if>

		<xsl:if test="$current-page = 'map'">
			<link href="{$root}/service/leaflet/leaflet.css" rel="stylesheet"/>
			<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw-src.css" integrity="sha512-vJfMKRRm4c4UupyPwGUZI8U651mSzbmmPgR3sdE3LcwBPsdGeARvUM5EcSTg34DK8YIRiIo+oJwNfZPMKEQyug==" crossorigin="anonymous" referrerpolicy="no-referrer" />
			<script src="{$root}/service/leaflet/leaflet.js"/>
			<script src="{$root}/service/leaflet/map.js"/>
			<script src='//api.tiles.mapbox.com/mapbox.js/plugins/leaflet-image/v0.0.4/leaflet-image.js'></script>
			<link rel="stylesheet" href="//code.jquery.com/ui/1.13.1/themes/base/jquery-ui.css"></link>
			<script src="https://code.jquery.com/ui/1.13.1/jquery-ui.js" />
			<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js" integrity="sha512-ozq8xQKq6urvuU6jNgkfqAmT7jKN2XumbrX1JiB3TnF7tI48DPI4Gy1GXKD/V3EExgAs1V+pRO7vwtS1LHg0Gw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
		</xsl:if>
		<!-- Medium editor -> scommentare quando serve abilitare l'editor dei test da frontend -->
		<!-- <xsl:if test="($current-page = 'post-editing')">
			<script src="{$workspace}/static/js/medium-editor.min.js" />
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
		</xsl:if> -->

		<!-- Slider Slick -->
		<xsl:if test="($current-page = 'humanbit-doc') or ($current-page = 'index') or ($current-page = 'modules')">
			<script>
				<!-- slider-for-home -->
				sliderForHome();
				<!--  -->
				sliderForLarge();
				<!--  -->
				sliderForDimo();
				<!--  -->
				sliderFor();
			</script>
		</xsl:if>

		<!-- Ecommerce functions -->
		<xsl:if test="$current-page = 'timeline'">
			<link rel="preconnect" href="https://fonts.googleapis.com"/>
			<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="crossorigin"/>
			<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
			<script src="{$workspace}/static/js/timeline.js?v1.0" />
			<script>checkHolidays('<xsl:value-of select="$this-month"/>', '.btn_date_day');</script>
			<xsl:if test="$url-notification = 'yes'">
				<script>addNotification('#timeline_notification', '<xsl:value-of select="translate($ds-timeline-last.member, ' ', '')"/>', '<xsl:value-of select="$root"/>/timeline');</script>
			</xsl:if>
			<script>init('<xsl:value-of select="$member-id"/>', '<xsl:value-of select="translate($ds-timeline-member-list, ' ', '')"/>', '<xsl:value-of select="$root"/>/timeline-action/?action=message&amp;member=', '#chat_');</script>
		</xsl:if>
		<xsl:if test="$member-id != ''">
			<script>checkElemsInContainerInputs('elem', '.cart_product', '.change_size');</script>
			<script>checkCollapse('#optionCounter', '.btn_add_soluzione');</script>
			<xsl:if test="$current-page != 'checkout'">
				<script>checkCart('#optionCounter', '.cart_container', '.cart_product');</script>
			</xsl:if>
			<!-- <xsl:if test="($current-page = 'material') and (/data/material-detail/entry[size])">
				<script>selectFirstVal('#sizeGadget', '#form_add_soluzione', 'fields[additional-notes]');</script>
			</xsl:if> -->
			<xsl:if test="$current-page = 'profile'">
				<script>checkAddress('.address_list');</script>
			</xsl:if>
			<xsl:if test="$current-page = 'confirm-order'">
				<script>addDisponibility('#solution_items');</script>
			</xsl:if>
			<xsl:if test="$current-page = 'checkout'">
				<xsl:if test="$url-step = 2">
					<script>updateSpedPrice('.delivery_price');</script>
				</xsl:if>
			<script>checkCartCheckout('.cart_container', '.cart_product');</script>
			<script>updateSelectionCheckout('.select_address');</script>
			<script>checkAddress('.address_list', '.btn_checkout');</script>
			<script>countOrder('<xsl:value-of select="count(/data/member-order-open-detail/entry)"/>', '<xsl:value-of select="sum(/data/member-order-open-detail/entry/price-tot)"/>');</script>
			</xsl:if>
			<!-- <xsl:if test="($current-page = 'confirm-order')">
				<script>submitForms('.confirm_order');</script>
				<script>fbq('track', 'Purchase', {value: 1.00, currency: 'USD'});</script>
			</xsl:if> -->
		</xsl:if>

		<!-- Klaro Cookie Script -->
		<script defer="defer" type="text/javascript" src="{$workspace}/static/js/klaro_config.js" />
		<script defer="defer" type="text/javascript" src="{$workspace}/static/js/klaro.js" />
		<script src="{$workspace}/static/js/web-app.js" />

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

		<!-- Ecommerce Forms -->
		<xsl:choose>

			<!-- Creazione ordine vuoto iniziale se non presente -->
			<xsl:when test="/data/member-order-open/error">
				<form id="member_start_order" method="post" action="{$current-url}/">
					<input name="xsrf" type="hidden" value="{$cookie-xsrf-token}" />
					<input name="fields[order-number]" type="hidden" value="{substring-after(generate-id(), 'idm')}"/>
					<input name="fields[member]" type="hidden" value="{$member-id}"/>
					<input name="fields[status]" type="hidden" value="open"/>
					<input name="fields[order-date]" type="hidden" value=""/>
					<input name="action[add-order]" type="hidden" value="submit"/>
				</form>
				<script>addOrder('#member_start_order');</script>
			</xsl:when>
			<xsl:otherwise>

				<!-- Aggiunta al carrello di un prodotto -->
				<form class="d-none" id="form_add_soluzione" method="post" action="{$current-url}/">
					<input name="xsrf" type="hidden" value="{$cookie-xsrf-token}" />
					<input name="fields[amount]" type="hidden" value="1"/>
					<input name="fields[price]" type="hidden" value=""/>
					<input name="fields[price-tot]" type="hidden" value=""/>
					<input name="fields[order]" type="hidden" value="{/data/member-order-open/entry/@id}"/>
					<input name="fields[solution]" type="hidden" value=""/>
					<input name="action[add-order-detail]" type="hidden" value="submit"/>
				</form>

				<!-- Cancellazione di un prodotto dal carrello -->
				<form class="d-none" id="form_delete_soluzione" method="post" action="{$current-url}/">
					<input name="xsrf" type="hidden" value="{$cookie-xsrf-token}" />
					<input name="id" type="hidden" value=""/>
					<input name="fields[deleted]" type="hidden" value="yes"/>
					<input name="action[add-order-detail]" type="hidden" value="submit"/>
				</form>

				<!-- Modifica di un prodotto nel carrello -->
				<form class="d-none" id="form_edit_soluzione" method="post" action="{$current-url}/">
					<input name="xsrf" type="hidden" value="{$cookie-xsrf-token}" />
					<input name="id" type="hidden" value=""/>
					<input name="fields[amount]" type="hidden" value=""/>
					<input name="fields[price]" type="hidden" value=""/>
					<input name="fields[price-tot]" type="hidden" value=""/>
					<input name="action[add-order-detail]" type="hidden" value="submit"/>
				</form>

				<!-- Form di modifica delle note aggiuntive di un prodotto nel carrello (taglie e altre info) -->
				<form class="d-none" id="form_edit_notes" method="post" action="{$current-url}/">
					<input name="xsrf" type="hidden" value="{$cookie-xsrf-token}" />
					<input name="id" type="hidden" value=""/>
					<input name="fields[additional-notes]" type="hidden" value=""/>
					<input name="action[add-order-detail]" type="hidden" value="submit"/>
				</form>
			</xsl:otherwise>
		</xsl:choose>
		<xsl:choose>

			<!-- Modal di successo recupero password -->
			<xsl:when test="/data/events/members-generate-recovery-code/@result = 'success'">
				<script>
					var myModal = new bootstrap.Modal(document.getElementById('forgotSuccessModal'));
					myModal.show();
				</script>
			</xsl:when>

			<!-- Modal di errore recupero password -->
			<xsl:when test="/data/events/members-generate-recovery-code/@result = 'error'">
				<script>
					var myModal = new bootstrap.Modal(document.getElementById('forgotErrorModal'));
					myModal.show();
				</script>
			</xsl:when>

			<!-- Modal di cambio password -->
			<xsl:when test="$url-emailchange != ''">
				<script>
					var myModal = new bootstrap.Modal(document.getElementById('pwChangeModal'));
					myModal.show();
				</script>
			</xsl:when>
		</xsl:choose>

		<!-- Modal di errore -->
		<xsl:if test="((/data/events/*/@result = 'error') or (/data/events/@result = 'error')) and (not(/data/events/members-generate-recovery-code))">
			<script>
				var myModal = new bootstrap.Modal(document.getElementById('msgErrorModal'))
				myModal.show();
			</script>
		</xsl:if>

		<!-- Modal di successo -->
		<xsl:if test="(($url-change = 'yes') or (/data/events/*[local-name() != 'member-login-info']/@result = 'success') or (/data/events/@result = 'success')) and (not(/data/events/members-generate-recovery-code))">
			<script>
				var myModal = new bootstrap.Modal(document.getElementById('msgSuccessModal'))
				myModal.show();
			</script>
		</xsl:if>

		<!-- Modal di conferma ordine -->
		<xsl:if test="(($url-confirm = '1') and ($current-page = 'profile'))">
			<script>
				var myModal = new bootstrap.Modal(document.getElementById('msgSuccessModal'))
				myModal.show();
			</script>
		</xsl:if>

	</xsl:template>

</xsl:stylesheet>
