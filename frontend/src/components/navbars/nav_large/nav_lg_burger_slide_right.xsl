<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template name="nav_lg_burger_slide_right">

		<div class="nav_partial">

			<nav class="nav_lg_burger_slide_right navbar scrollbar_spacing">

				<div class="navbar_container_full container_humanbit_1 color_white container-fluid row justify-content-between align-items-center py-0">

					<!-- Navbar. <<IN>> -->

					<!-- Navbar toggler. -->
					<!-- Add or remove the class `.d-none` at the end of this level depending on the desired model. -->
					<div class="menu_navbar container_max_width_1 row justify-content-between align-items-center">
						<div class="toggle_wrapper box_left col-2 padding_humanbit_1_right d-block d-lg-none">
							<!-- Change `data-bs-target` and `aria-controls` values to the `id` of the intended collapse. -->
							<button class="btn_toggler_open navbar-toggler border-0 align-middle p-0 w-auto collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation" id="navTogglerBasic">
								<span class="span_toggler color_white" />
								<span class="span_toggler color_white" />
								<span class="span_toggler color_white" />
							</button>
						</div>

						<!-- Toggler and logo. -->
						<!-- Avoid changing the columns! -->
						<a class="menu_box d-block box_left color_white row justify-content-start align-items-center col-auto col-6 col-lg-3" href="{$root}">


							<figure class="toggle_wrapper col-12 me-4 me-xl-5">
								<img class="w-75" src="{$workspace}/static/images/logos/Bollcrem_logo_header.jpg">

								</img>
							</figure>


							<!-- Uncomment block for extra slot version. -->
							<!-- <a class="menu_box box_center color_white row justify-content-center align-items-center col-auto" href="{$root}/{$lanurl}">
								<figure class="figure_logo">
									<img class="img_logo img-fluid" src="{$workspace}/static/images/logos/logo-humanbit-white.svg" alt="Logo" />
								</figure>
							</a> -->
						</a>


						<xsl:choose>
							<!-- test page -->
							<xsl:when test="$current-page='landing'">

							</xsl:when>
							<!-- online page -->
							<xsl:otherwise>
								<!-- Comment block for extra slot version. -->
								<!-- Avoid changing the columns! -->
								<ul class="menu_box box_center color_white d-none d-lg-flex row justify-content-center align-items-center col-auto col-md-4 me-auto mx-sm-auto mb-0">
									<li class="col-auto text-uppercase"> <a class="color_white" href="{$root}/index/#chi_siamo">

										chi siamo </a> <span class="px-2 color_first">|</span> </li>
									<li class="col-auto text-uppercase"> <a class="color_white" href="{$root}/index/#suggest_section"> consigli </a><span class="px-2 color_first">|</span>
									</li>
									<li class="col-auto text-uppercase"> <a class="color_white" href="{$root}/shop">
										<xsl:if test="($current-page = 'shop')">
											<xsl:attribute name="class">color_first</xsl:attribute>
										</xsl:if>
										shop </a><span class="px-2 color_first">|</span>
									</li>
									<li class="col-auto text-uppercase"> <a class="color_white" href="{$root}/contact">
										<xsl:if test="($current-page = 'contact')">
											<xsl:attribute name="class">color_first</xsl:attribute>
										</xsl:if>
										Contatti  </a>
									</li>
								</ul>

								<!-- <button class="navbar-toggler col-2 py-0 padding_humanbit_1_right d-flex align-items-top justify-content-end d-block d-lg-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
									<span class="navbar-toggler-icon" style="float:right;"></span>
								</button> -->
							</xsl:otherwise>
						</xsl:choose>



						<!-- Uncomment block for extra slot version. -->
						<!-- Avoid changing the columns! -->
						<!-- <div class="menu_box box_center nav_extra_wrapper text-center col-12 col-lg-auto mx-auto order-1 order-lg-0 mt-4 mt-lg-0">
							<p class="nav_extra_content h3 fw_300">Primiero San Martino di Castrozza</p>
						</div> -->

						<!-- Buttons row. -->
						<!-- Avoid changing the columns! -->
						<div class="menu_box box_right text-uppercase color_white row justify-content-end align-items-center col-4 col-lg-3 order-0 order-lg-1">


							<xsl:choose>
								<!-- landing -->
								<xsl:when test="$current-page='landing'">

									<a class="menu_link_sponsor d-xl-block ps-4 col-auto text-end" href="mailto:bollcrem@bollcrem.it">
										<i class="full_cart coll_white fa-thin fa-circle-envelope fa-2xl color_first text-end"></i>

										<!-- <figure class="figure_logo figure_logo_sponsor">
											<img class="img_logo img_logo_sponsor img-fluid" src="{$workspace}/static/images/logos/logo_humanbit.png" />
										</figure> -->

									</a>
								</xsl:when>
								<!-- site -->
								<xsl:otherwise>
									<xsl:if test="$current-page != 'checkout'">
										<div class="color_black text-end w-auto">
											<xsl:choose>
												<xsl:when test="$member-id != ''">
													<a class="menu_link circle_1 text-uppercase color_white menu_steps circle_cart collapsed" type="button" id="optionCounter" data-bs-toggle="collapse" data-bs-target="#menuOptions" aria-controls="menuOptions" aria-expanded="false" aria-label="Toggle cart" data-empty=".empty_cart" data-full=".full_cart">
														<i class="full_cart color_white fa-solid fa-bag-shopping text-start d-none" />
														<i class="empty_cart color_white fa-regular fa-bag-shopping text-start" />

													</a>
													<!-- empty cart -->
													<!-- <a class="menu_link menu_steps me-1 collapsed" type="button" id="optionCounter" data-bs-toggle="collapse" data-bs-target="#menuOptions" aria-controls="menuOptions" aria-expanded="false" aria-label="Toggle cart">
														<i class="empty_cart coll_white fa-regular fa-bag-shopping text-start" />
													</a> -->

													<!-- full cart -->
													<!-- <a class="menu_link menu_steps ms-1 collapsed" type="button" id="optionCounter" data-bs-toggle="collapse" data-bs-target="#menuOptions" aria-controls="menuOptions" aria-expanded="false" aria-label="Toggle cart">
														<i class="full_cart coll_white fa-solid fa-bag-shopping color_second text-start" />
													</a> -->
												</xsl:when>
												<xsl:otherwise>
													<a class="menu_link circle_1 text-uppercase color_white circle_cart collapsed" type="button" data-bs-toggle="modal" data-bs-target="#loginModal">
														<i class="full_cart color_white fa-solid fa-bag-shopping text-start d-none" />
														<i class="empty_cart color_white fa-regular fa-bag-shopping text-start" />
													</a>
													<!-- <i class="full_cart coll_white fa-solid fa-bag-shopping color_second text-start" /> -->
												</xsl:otherwise>
											</xsl:choose>

										</div>
									</xsl:if>

									<!-- Uncomment div if the cart collapse is being used. -->
									<!-- <button class="menu_link circle_1 menu_steps color_white me-2 me-md-3 collapsed" type="button" id="optionCounter" data-bs-toggle="collapse" data-bs-target="#menuOptions" aria-controls="menuOptions" aria-expanded="false" aria-label="Toggle cart">0</button> -->



									<xsl:choose>
										<xsl:when test="$member-id != ''">
											<a class="menu_link circle_1 ms-2 ms-md-3" href="{$root}/profile/{$lanurl}">
												<i class="icon_profile color_white fa-solid fa-user" />
											</a>
										</xsl:when>
										<xsl:otherwise>
											<button class="login_modal circle_1 ms-2 ms-md-3" data-bs-toggle="modal" data-bs-target="#loginModal">
												<i class="icon_profile color_white fa-regular fa-user" />
											</button>
										</xsl:otherwise>
									</xsl:choose>

									<xsl:choose>
										<xsl:when test="$lan = 'it'">
											<a class="menu_link circle_1 text-uppercase color_white ms-2 ms-md-3" href="{$current-url}/?lan=en">
												EN
											</a>
										</xsl:when>
										<xsl:otherwise>
											<a class="menu_link circle_1 text-uppercase color_white ms-2 ms-md-3" href="{$current-url}/?lan=it">
												IT
											</a>
										</xsl:otherwise>
									</xsl:choose>
								</xsl:otherwise>
							</xsl:choose>



						</div>
					</div>

					<!-- Navbar extra large. -->
					<!-- Add or remove the class `.d-none` at the end of this level depending on the desired model. -->
					<div class="menu_navbar container_max_width_1 row justify-content-between align-items-center d-none">
						<!-- Toggler and logo. -->
						<!-- Avoid changing the columns! -->
						<div class="menu_box box_left color_white row justify-content-start align-items-center col-auto col-lg-3 col-xl-2">
							<div class="toggle_wrapper d-block d-xl-none col-1 me-4 me-xl-5">
								<!-- Change `data-bs-target` and `aria-controls` values to the `id` of the intended collapse. -->
								<button class="btn_toggler_open navbar-toggler border-0 align-middle p-0 w-auto collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSectionsContent" aria-controls="navbarSectionsContent" aria-expanded="false" aria-label="Toggle navigation" id="navTogglerSections">
									<span class="span_toggler color_white" />
									<span class="span_toggler color_white" />
									<span class="span_toggler color_white" />
								</button>
							</div>

							<a class="menu_box box_center color_white row justify-content-center align-items-center col-auto" href="{$root}/{$lanurl}">
								<figure class="figure_logo">
									<img class="img_logo img-fluid" src="{$workspace}/static/images/logos/logo-humanbit-white.svg" alt="Logo" />
								</figure>
							</a>
						</div>

						<!-- Menu items. -->
						<!-- Avoid changing the columns! -->
						<div class="menu_box box_center d-none d-xl-flex row justify-content-start align-self-end col-12 col-xl-8 order-1 order-xl-0 mt-4 mt-xl-0">

							<ul class="menu_items navbar-nav flex-row flex-wrap col-auto">
								<li class="landmark text-center d-flex align-items-end">
									<a class="menu_item color_white px-1 opacity-100-hover" href="{$root}/lista-corsi/corsi" title="Courses">Courses</a>

									<span class="vbar px-1 px-xxl-3">|</span>
								</li>

								<!-- Collapse Impianti trigger. -->
								<li class="landmark text-center d-flex align-items-end li_menu_installations">
									<a class="menu_item color_white px-1 opacity-100-hover" href="#collapseInstallations" role="button" title="Installations" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapseInstallations">Installations</a>

									<span class="vbar px-1 px-xxl-3">|</span>
								</li>

								<!-- Collapse Sport trigger. -->
								<li class="landmark text-center d-flex align-items-end li_menu_sport">
									<a class="menu_item color_white px-1 opacity-100-hover" href="#collapseSport" role="button" title="Sport" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapseSport">Sport</a>

									<span class="vbar px-1 px-xxl-3">|</span>
								</li>

								<!-- Collapse Acquista trigger. -->
								<li class="landmark text-center d-flex align-items-end gray li_menu_buy">
									<a class="menu_item color_third px-1 opacity-100-hover" href="#collapseBuy" role="button" title="Ticket office" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapseBuy">Purchase</a>

									<span class="vbar px-1 px-xxl-3">|</span>
								</li>

								<li class="landmark text-center d-flex align-items-end">
									<a class="menu_item color_third px-1 opacity-100-hover" href="{$root}/istituzionali/orari-norme/" title="Timetables and info">Info</a>

									<span class="vbar px-1 px-xxl-3">|</span>
								</li>

								<li class="landmark text-center d-flex align-items-end">
									<a class="menu_item color_eighth px-1 opacity-100-hover" href="{$root}/lista-eventi" title="Events">Events</a>

									<span class="vbar px-1 px-xxl-3">|</span>
								</li>

								<li class="landmark text-center d-flex align-items-end gray li_menu_cerca">
									<a class="menu_item color_eighth px-1 opacity-100-hover" href="{$root}/lista-news" title="News">News</a>
								</li>
							</ul>

						</div>

						<!-- Buttons row. -->
						<!-- Avoid changing the columns! -->
						<div class="menu_box box_right text-uppercase color_white row justify-content-end align-items-center col-auto col-lg-3 col-xl-2 order-0 order-xl-1 ms-auto">
							<!-- Uncomment div if the cart collapse is being used. -->
							<!-- <button class="menu_link circle_1 menu_steps color_white me-2 me-md-3 collapsed" type="button" id="optionCounter" data-bs-toggle="collapse" data-bs-target="#menuOptions" aria-controls="menuOptions" aria-expanded="false" aria-label="Toggle cart">0</button> -->

							<xsl:choose>
								<xsl:when test="$lan = 'it'">
									<a class="menu_link circle_1 text-uppercase color_white" href="{$current-url}/?lan=en">
										EN
									</a>
								</xsl:when>
								<xsl:otherwise>
									<a class="menu_link circle_1 text-uppercase color_white" href="{$current-url}/?lan=it">
										IT
									</a>
								</xsl:otherwise>
							</xsl:choose>

							<xsl:choose>
								<xsl:when test="$member-id != ''">
									<a class="menu_link circle_1 ms-2 ms-md-3" href="{$root}/profile/{$lanurl}">
										<i class="icon_profile color_white fa-solid fa-user" />
									</a>
								</xsl:when>
								<xsl:otherwise>
									<button class="login_modal circle_1 ms-2 ms-md-3" data-bs-toggle="modal" data-bs-target="#loginModal">
										<i class="icon_profile color_white fa-regular fa-user" />
									</button>
								</xsl:otherwise>
							</xsl:choose>
						</div>
					</div>

					<!-- Navbar. <<OUT>> -->


					<!-- Collapse. <<IN>> -->

					<!-- Collapse navbar background. -->
					<!-- `.col-*` classes should match the ones on the `.collapse_contents` div in use for animation effect. -->
					<div class="cont_ghost_bg nav_ghost_bg col-12 position-fixed top-0 start-0" />

					<!-- Collapse (basic). -->
					<xsl:choose>
						<!-- test page -->
						<xsl:when test="$current-page='landing'">

						</xsl:when>
						<!-- online page -->
						<xsl:otherwise>
							<!-- If inner `width` is changed with `.col-*` classes, simply remove `background-color` from this level`. -->
							<div class="menu_collapse navbar-collapse collapse" id="navbarSupportedContent">

								<!-- Sets dimension and compensates for the hidden scrollbar with padding. -->
								<div class="collapse_wrapper scrollbar_spacing h-auto min-vw-100 mw-100">

									<!-- Centers content vertically and keeps dimensions set on higher levels. -->
									<!--
										To change overall `width`:
										1. Replace `.w-100` with `.col-*` classes.
										2. Add `.p*-*-0` according to the chosen breakpoint.
										3. Set the block's top level parent's `background-color` to `transparent`.
										4. Assign `background-color` to the following level.
									-->
									<div class="collapse_contents bg_color_first row align-items-center container_humanbit_1 py-0 w-100">

										<!-- Controls `width`, fixes `max-width` (as `mw-100` breaks layout) and positions content horizontally. -->
										<div class="basic container_sizing container_max_width_1 row justify-content-between align-items-start">

											<!-- Edit only from this point onwards. -->

											<!-- voci menu -->
											<!-- <div class="menu_items collapse navbar-collapse col-12 row align-items-end justify-content-between py-lg-4" id="navbarSupportedContent"> -->
											<div class="menu_items collapse_nav navbar-nav row flex-row col-12 col-lg-6 py-4 pe-lg-4">
												<ul class="menu_items_internal navbar-nav text-uppercase col-12 col-lg-8 py-5 py-lg-0 mt-0">
													<li class="landmark mb-3">
														<a class="trigger_collapse_menu p color_white" href="{$root}/index/#chi_siamo">
															<!-- <xsl:if test="($current-page = 'consigli')">
																<xsl:attribute name="class">color_first</xsl:attribute>
															</xsl:if> -->
															Chi Siamo
														</a>
													</li>

													<!-- collapse impianti trigger -->
													<li class="landmark li-menu-impianti mb-3">
														<a class="trigger_collapse_menu p color_white" href="{$root}/index/#suggest_section">
															<!-- <xsl:if test="($current-page = 'consigli')">
																<xsl:attribute name="class">color_first</xsl:attribute>
															</xsl:if> -->
															Consigli
														</a>
													</li>

													<!-- collapse sport trigger -->
													<li class="landmark li-menu-sport mb-3">
														<a class="trigger_collapse_menu p color_white" href="{$root}/shop">
															<xsl:if test="($current-page = 'shop')">
																<xsl:attribute name="class">trigger_collapse_menu p color_black</xsl:attribute>
															</xsl:if>
															Shop
														</a>
													</li>
													<li class="col-auto">
														<a class="trigger_collapse_menu p color_white" href="{$root}/contact">
															<xsl:if test="($current-page = 'contact')">
																<xsl:attribute name="class">trigger_collapse_menu p color_black</xsl:attribute>
															</xsl:if>
															Contatti
														</a>
													</li>
												</ul>

												<!-- logo nav internal -->
												<a class="logo_nav_internal desk d-none d-lg-block col-2" href="{$root}">
													<figure class="col-12 text-end">
														<img class="nav_logo hor_line_img w-75 mb-0" src="{$workspace}/static/images/logos/milanosport_LOGO_Orizzontale.svg">
															<xsl:if test="$current-page='home'"><xsl:attribute name="class">hor_line_img w-75 mb-0 opacity_0</xsl:attribute></xsl:if>
														</img>
													</figure>
												</a>
											</div>

											<!-- <div class="collapse_nav navbar-nav row flex-row col-12 col-lg-6 py-4 pe-lg-4">
												<a class="collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 show-search" href="{$root}/{$lanurl}">
													<xsl:if test="($current-page = 'index')">
														<xsl:attribute name="class">collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 active_link</xsl:attribute>
													</xsl:if>

													Home
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>

												<a class="collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0" href="{$root}/calendar/{$lanurl}">
													<xsl:if test="$current-page = 'events'">
														<xsl:attribute name="class">collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 active_link</xsl:attribute>
													</xsl:if>

													Eventi
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>

												<a class="collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0" href="{$root}/space/{$lanurl}">
													<xsl:if test="$current-page = 'plant'">
														<xsl:attribute name="class">collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 active_link</xsl:attribute>
													</xsl:if>

													L'Impianto
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>

												<a class="collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0" href="{$root}/institutional/274/{$lanurl}">
													<xsl:if test="$current-page = 'institutional'">
														<xsl:attribute name="class">collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 active_link</xsl:attribute>
													</xsl:if>

													Chi Siamo
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>

												<a class="collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0" href="{$root}/contact/{$lanurl}">
													<xsl:if test="$current-page = 'contacts'">
														<xsl:attribute name="class">collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 active_link</xsl:attribute>
													</xsl:if>

													Contatti
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>

												<a class="collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0" href="{$root}/profile/{$lanurl}">
													<xsl:if test="$current-page = 'profile'">
														<xsl:attribute name="class">collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 active_link</xsl:attribute>
													</xsl:if>

													Area Riservata
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>
											</div>

											<div class="collapse_nav navbar-nav row flex-row col-12 col-lg-6 py-4 ps-lg-4">
												<a class="collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 show-search" href="{$root}/{$lanurl}">
													<xsl:if test="($current-page = 'index')">
														<xsl:attribute name="class">collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 active_link</xsl:attribute>
													</xsl:if>

													Home
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>

												<a class="collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0" href="{$root}/calendar/{$lanurl}">
													<xsl:if test="$current-page = 'events'">
														<xsl:attribute name="class">collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 active_link</xsl:attribute>
													</xsl:if>

													Eventi
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>

												<a class="collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0" href="{$root}/space/{$lanurl}">
													<xsl:if test="$current-page = 'events'">
														<xsl:attribute name="class">collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 active_link</xsl:attribute>
													</xsl:if>

													L'Impianto
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>

												<a class="collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0" href="{$root}/institutional/274/{$lanurl}">
													<xsl:if test="$current-page = 'events'">
														<xsl:attribute name="class">collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 active_link</xsl:attribute>
													</xsl:if>

													Chi Siamo
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>

												<a class="collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0" href="{$root}/contact/{$lanurl}">
													<xsl:if test="$current-page = 'events'">
														<xsl:attribute name="class">collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 active_link</xsl:attribute>
													</xsl:if>

													Contatti
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>

												<a class="collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0" href="{$root}/{$lanurl}">
													<xsl:if test="$current-page = 'events'">
														<xsl:attribute name="class">collapse_link nav-link h2 text-uppercase border-bottom border-2 py-2 pe-0 active_link</xsl:attribute>
													</xsl:if>

													Area Riservata
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>
											</div> -->

											<!-- Edit only from this point backwards. -->

										</div>

									</div>

								</div>

							</div>
						</xsl:otherwise>
					</xsl:choose>

					<!-- Collapse (sections). -->
					<!-- If inner `width` is changed with `.col-*` classes, simply remove `background-color` from this level`. -->
					<div class="menu_collapse navbar-collapse collapse" id="navbarSectionsContent">

						<!-- Sets dimension and compensates for the hidden scrollbar with padding. -->
						<div class="collapse_wrapper scrollbar_spacing h-auto min-vw-100 mw-100">

							<!-- Centers content vertically and keeps dimensions set on higher levels. -->
							<!--
								To change overall `width`:
								1. Replace `.w-100` with `.col-*` classes.
								2. Add `.p*-*-0` according to the chosen breakpoint.
								3. Set the block's top level parent's `background-color` to `transparent`.
								4. Assign `background-color` to the following level.
							-->
							<div class="collapse_contents bg_color_second row align-items-center container_humanbit_1 col-12 col-lg-9 pe-lg-0">

								<!-- Controls `width`, fixes `max-width` (as `mw-100` breaks layout) and positions content horizontally. -->
								<div class="sections container_sizing container_max_width_1 row justify-content-between align-items-start">

									<!-- Edit only from this point onwards. -->

									<div class="collapse_nav navbar-nav row flex-row justify-content-between mb-4">
										<div class="wrapper_outer row align-items-center col-12 pt-5 pb-4 border-bottom">
											<div class="wrapper_inner text-sm-end text-md-start align-self-start col-12 col-md-6 my-3">
												<h3 class="collapse_title h5">
													Ti stai trasferendo in una nuova città?
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</h3>

												<p class="collapse_body p fw_200 about_us mt-3">
													Affidati a noi, troveremo insieme la casa perfetta per te.
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</p>
											</div>

											<div class="wrapper_inner d-flex flex-wrap justify-content-start justify-content-sm-end align-items-center col-12 col-md-6 col-lg-5 ps-md-4 ps-lg-3">
												<a class="collapse_link h2 text-uppercase btn_bg_third btn_reverse my-3 my-md-2 w-auto" href="{$root}/landing-user/?lan={$lan}">
													Come funziona?
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>

												<a class="collapse_link h2 text-uppercase btn_color_white my-3 my-md-2 ms-3 w-auto" href="{$root}/landing-user/?lan={$lan}">
													FAQ
												</a>
											</div>
										</div>

										<div class="wrapper_outer col-12 col-md-8 navbar-nav row flex-row border-end-md border-bottom mb-md-4 py-4 pe-md-4">
											<div class="wrapper_inner align-self-start col-12 mb-4">
												<h3 class="collapse_title h5">
													Sei un'azienda e vuoi organizzare il tuo campus?
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</h3>
											</div>

											<a class="collapse_link h2 text-uppercase btn_color_white border border-2 me-3 mb-2 w-auto" href="{$root}/institutional/41439/partner/{$lanurl}">
												Chi lavora con noi
												<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
											</a>

											<a class="collapse_link h2 text-uppercase btn_color_white border border-2 mb-2 w-auto" href="{$root}/landing-campus/{$lanurl}">
												Il campus
												<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
											</a>
										</div>

										<div class="wrapper_outer navbar-nav row flex-row col-12 col-md-4 mb-4 py-4 justify-content-md-center border-bottom">
											<div class="wrapper_inner align-self-center col-12 mb-4">
												<h3 class="collapse_title h5 text-sm-end text-md-center">
													O un'agenzia?
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</h3>
											</div>

											<a class="collapse_link h2 text-uppercase btn_color_white align-self-end border border-2 mb-2 ms-sm-auto ms-md-0 w-auto" href="{$root}/institutional/41439/partner/{$lanurl}">
												Collaboriamo!
												<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
											</a>
										</div>

										<div class="wrapper_outer row align-items-center col-12 border-bottom mb-4 pb-4">
											<div class="wrapper_inner align-self-start col-12 col-md-6 my-3">
												<h3 class="collapse_title h5 mb-2">
													Sei un proprietario di appartamento?
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</h3>

												<p class="collapse_body p fw_200 about_us mt-3">
													Con noi puoi affittare il tuo appartamento in modo semplice, veloce e sicuro
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</p>
											</div>

											<div class="wrapper_inner d-flex flex-wrap col-12 col-md-5 align-items-center justify-content-md-center my-3 ps-md-4 ps-lg-3">
												<a class="collapse_link h2 text-uppercase btn_bg_third btn_reverse w-auto" href="{$root}/landing-landlord/?lan={$lan}">Ce ne occupiamo noi!
													<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
												</a>
											</div>
										</div>

										<div class="collapse_nav navbar-nav d-flex flex-wrap flex-row justify-content-sm-center justify-content-md-start pt-2">
											<a class="about_us collapse_link h2 text-uppercase btn_reset me-3 mb-3 w-auto" href="{$root}/institutional/41515/about/{$lanurl}">
												<xsl:if test="($current-page = 'index')">
													<xsl:attribute name="class">collapse_link h2 text-uppercase btn_color_white border border-2 border_color_white me-3 mb-3 active_link</xsl:attribute>
												</xsl:if>

												About us
												<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
											</a>

											<a class="privacy_and_terms collapse_link h2 text-uppercase btn_reset me-3 mb-3 w-auto" href="{$root}/institutional/3606/terms-conditions/{$lanurl}">
												<xsl:if test="$current-page = 'events'">
													<xsl:attribute name="class">collapse_link h2 text-uppercase btn_color_white border border-2 border_color_white me-3 mb-3 active_link</xsl:attribute>
												</xsl:if>

												Terms and Conditions
												<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
											</a>

											<a class="privacy_and_terms collapse_link h2 text-uppercase btn_reset mb-3 w-auto" href="{$root}/institutional/3607/privacy/{$lanurl}">
												<xsl:if test="$current-page = 'events'">
													<xsl:attribute name="class">collapse_link h2 text-uppercase btn_color_white border border-2 border_color_white active_link</xsl:attribute>
												</xsl:if>

												Privacy Policy
												<xsl:value-of select="/data/translate/entry[code='example']/*[local-name() = $lanextended]" />
											</a>
										</div>
									</div>

									<!-- Edit only from this point backwards. -->

									<!-- Footer -->
									<xsl:call-template name="footer_nav" />

								</div>

							</div>

						</div>

					</div>

					<!-- Collapse. <<OUT>> -->


					<!-- Cart (ecommerce). <<IN>> -->

					<!-- Cart navbar background. -->
					<!-- Uncomment div if the cart toggle is being used (necessary for the JS animation). -->
					<!-- <div class="cont_ghost_bg coll_ghost_bg position-fixed top-0 end-0" /> -->

					<!-- Cart. -->
					<!-- Delete block if element is not needed on current project for safety and performance reasons. -->
					<div class="menu_options bg_color_white border border-end-md-0 border_color_second navbar-collapse h-100 collapse" id="menuOptions" dir="rtl">

						<div class="options_wrapper scrollbar_spacing overflow-y-auto" dir="ltr">
							<!-- Close button. -->
							<button id="closeCart" class="btn_toggler navbar-toggler p bg_color_black position-absolute border-0 px-3 pt-2 pb-2" type="button" data-bs-toggle="collapse" data-bs-target="#menuOptions" aria-controls="menuOptions" aria-expanded="false" aria-label="Close" data-ref=".btn_add_soluzione">
								<i class="icon_toggler color_white align-bottom fa-solid fa-xmark" />
							</button>

							<div class="option_items container_humanbit_1">

								<div class="position_wrapper position-relative py-5">

									<div class="container_choices row justify-content-between align-items-center mt-3 mb-4">
										<xsl:choose>
											<xsl:when test="$member-id != ''">
												<a class="btn_book_option p text-uppercase fw-500 btn_bg_first d-block col-12 mb-4 mx-auto mt-2 px-2 px-md-5 py-3 option_unsaved btn_proceed_checkout" href="{$root}/checkout/{$testurl}">
													<xsl:value-of select="/data/translate/entry[code = 'proceed_order']/*[local-name() = $lanextended]" />
												</a>
											</xsl:when>
											<xsl:otherwise>
												<button class="btn_book_option p login_modal smaller text-uppercase fw-500 btn_bg_third d-block col-12 mb-4 mx-auto mt-2 px-2 px-md-5 py-3 option_unsaved" data-bs-toggle="modal" data-bs-target="#loginModal" data-login-form="#formNew">
													<xsl:value-of select="/data/translate/entry[code = 'proceed_order']/*[local-name() = $lanextended]" />
												</button>
											</xsl:otherwise>
										</xsl:choose>
									</div>


									<h4 class="option_title small fw-700 color_first border-bottom border-2 border_color_grayer mb-4 pb-3 title_checkout">
										<span class="small d-block fw_400 color_black mb-2"><xsl:value-of select="/data/translate/entry[code = 'or']/*[local-name() = $lanextended]" /></span>
										<xsl:value-of select="/data/translate/entry[code = 'change_your_opt']/*[local-name() = $lanextended]" />
									</h4>
									<h4 class="option_title mid_small fw-700 color_second border-bottom border-2 border_color_grayer mb-4 pb-3 title_checkout d-none">
										<span class="d-block smaller fw_400 color_black"><xsl:value-of select="/data/translate/entry[code = 'prima_procedere']/*[local-name() = $lanextended]" /></span>
										<xsl:value-of select="/data/translate/entry[code = 'seleziona_taglie']/*[local-name() = $lanextended]"/>
									</h4>

									<xsl:if test="$current-page != 'checkout'">

										<div class="body_wrapper color_first row justify-content-between mb-3 pb-4 cart_container">
											<xsl:choose>
												<xsl:when test="count(/data/member-order-open-detail/entry) &gt; 0">
													<xsl:for-each select="/data/member-order-open-detail/entry">
														<xsl:variable name="idSolution" select="solution/item/@id"/>
														<xsl:variable name="countSize" select="count(/data/member-order-open-detail-soluzione/entry[@id = $idSolution]/size/item)"/>
														<xsl:variable name="priceSolution" select="/data/member-order-open-detail-soluzione/entry[@id = $idSolution]/price"/>
														<xsl:variable name="typeSolution" select="/data/member-order-open-detail-soluzione/entry[@id = $idSolution]/type/@handle"/>
														<xsl:variable name="idOrderDetail" select="@id"/>
														<xsl:variable name="additionalNotes" select="additional-notes"/>
														<xsl:variable name="additionalNotesHandle" select="additional-notes/@handle"/>
														<xsl:variable name="salesMethodSolution" select="/data/member-order-open-detail-soluzione/entry[@id = $idSolution]/sales-method/item" />
														<xsl:variable name="amountSoluzioneSelected">
															<xsl:choose>
																<xsl:when test="$countSize &gt; 0">
																	<xsl:value-of select="sum(/data/member-order-open-detail-soluzione-disponibilita/entry[solution/item/@id = $idSolution][size = $additionalNotes]/amount)"/>
																</xsl:when>
																<xsl:otherwise>
																	<xsl:value-of select="sum(/data/member-order-open-detail-soluzione-disponibilita/entry[solution/item/@id = $idSolution]/amount)"/>
																</xsl:otherwise>
															</xsl:choose>
														</xsl:variable>

														<div id="cart_product_{$idSolution}" class="cart_product mt-4 pb-3 border-bottom border_color_second" data-ref="{$idSolution}">
															<div class="higher_row justify-content-between mb-3 clearfix">
																<a class="option_title text-uppercase smaller color_black fw-500 pe-5 pe-lg-1 pe-xl-0" href="{$root}/{/data/member-order-open-detail-soluzione/entry[@id = $idSolution]/name-italian/@handle}"><xsl:value-of select="/data/member-order-open-detail-soluzione/entry[@id = $idSolution]/*[local-name() = $namelan]"/></a>

																<button class="btn_toggler trash_option smaller lh-1 bg_color_grayer border-0 rounded-circle p-2 opacity-75-hover btn_delete_soluzione float-end" type="button" data-form="#form_delete_soluzione" data-cart-container=".cart_container" data-cart-order="#cart_order_{$idSolution}" data-order-class=".order_detail" data-input-order="id" data-soluzione="{$idSolution}" data-soluzione-class=".btn_add_soluzione"  data-url="{$root}/action/?order-detail=yes">
																	<i class="icon_toggler smaller color_white fa-regular fa-trash align-middle"/>
																</button>
															</div>

															<div id="cart_order_{$idSolution}">
																<div id="{$idOrderDetail}" class="order_detail lower_row text-center justify-content-between row mb-3">
																	<xsl:if test="/data/member-order-open-detail-soluzione/entry[@id = $idSolution][size]">
																		<div class="option_box row justify-content-center align-items-start align-content-start col-2">
																			<p class="smaller mb-2 color_gray_darker">Size</p>
																			<select class="smaller center py-1 w-100 change_size color_second border_color_second" data-form="#form_edit_notes" data-input-notes="fields[additional-notes]" data-input-amount=".change_amount" data-order="{$idOrderDetail}" data-input-order="id" data-url="{$root}/action/?order-detail=yes">
																				<option class="selected" value="{$additionalNotes}" data-amount="{$amountSoluzioneSelected}"><xsl:value-of select="$additionalNotes"/><xsl:if test="($amountSoluzioneSelected &lt;= 5) and ($amountSoluzioneSelected &gt; 0)"> (<xsl:value-of select="$amountSoluzioneSelected"/> disponibili)</xsl:if></option>
																				<xsl:for-each select="/data/member-order-open-detail-soluzione/entry[@id = $idSolution]/size/item[(@handle != $additionalNotesHandle) or (not($additionalNotesHandle))]">
																					<xsl:variable name="size" select="."/>
																					<xsl:variable name="amountSoluzione" select="sum(/data/member-order-open-detail-soluzione-disponibilita/entry[solution/item/@id = $idSolution][size = $size]/amount)"/>
																					<option class="unselected" value="{$size}" data-amount="{$amountSoluzione}">
																						<xsl:if test="$amountSoluzione = 0">
																							<xsl:attribute name="disabled">disabled</xsl:attribute>
																							<xsl:attribute name="style">color:lightgray;</xsl:attribute>
																						</xsl:if>
																						<xsl:value-of select="$size"/><xsl:if test="($amountSoluzione &lt;= 5) and ($amountSoluzione &gt; 0)"><span style="font-size:2px;"> (<xsl:value-of select="$amountSoluzione"/> disponibili)</span></xsl:if>
																					</option>
																				</xsl:for-each>
																			</select>
																		</div>
																	</xsl:if>
																	<div class="option_box row justify-content-center align-items-start align-content-start col-2">
																		<p class="smaller mb-2 color_gray_darker">
																			<xsl:value-of select="/data/translate/entry[code = 'qty']/*[local-name() = $lanextended]"/>
																		</p>
																		<input class="form-control smaller text-center py-1 w-100 change_amount color_white" type="number" min="1" max="{$amountSoluzioneSelected}" value="{amount}" data-form="#form_edit_soluzione" data-cart-container=".cart_container" data-price="{$priceSolution}" data-input-amount="fields[amount]" data-input-price="fields[price]" data-input-price-tot="fields[price-tot]" data-order="{$idOrderDetail}" data-input-order="id" data-price-checkout="#price_checkout" data-price-tot-checkout="#price_tot_checkout" data-url="{$root}/action/?order-detail=yes">
																			<xsl:if test="$salesMethodSolution = 'Abbonamento'">
																				<xsl:attribute name="style">pointer-events:none;opacity:0.5;</xsl:attribute>
																			</xsl:if>
																		</input>
																	</div>


																	<div class="option_box col-3">
																		<p class="smaller mb-2 color_gray_darker">
																			<xsl:value-of select="/data/translate/entry[code = 'price']/*[local-name() = $lanextended]"/>
																		</p>

																		<span class="smaller color_gray_darker">€<xsl:value-of select="price"/></span>
																	</div>



																	<div class="option_box col-3">
																		<p class="smaller mb-2 fw-700 color_first">
																			<xsl:value-of select="/data/translate/entry[code = 'total']/*[local-name() = $lanextended]"/>
																		</p>

																		<span class="smaller color_first">€<xsl:value-of select="price-tot"/></span>
																	</div>
																</div>
															</div>

															<xsl:if test="/data/member-order-open-detail-soluzione/entry[@id = $idSolution][size]">
																<button class="add_address_button smaller text-uppercase btn_bg_white border border_color_second rounded-0 my-3 btn_label_select_address btn_color_second me-2 py-1 btn_add_size btn_add_soluzione d-none" style="font-size: 10px;" type="button" data-form="#form_add_soluzione" data-cart-container=".cart_container" data-price="{$priceSolution}" data-input-price="fields[price]" data-input-price-tot="fields[price-tot]" data-input-amount="fields[amount]" data-soluzione="{$idSolution}" data-input-soluzione="fields[solution]" data-url="{$root}/action/?order-detail=yes&amp;lan={$lanextended}">Aggiungi una taglia</button>
																<button class="add_address_button smaller text-uppercase btn_bg_white border border_color_second rounded-0 my-3 btn_label_select_address btn_color_second py-1 btn_remove_size d-none" style="font-size: 10px;" type="button" data-form="#form_delete_soluzione" data-cart-container=".cart_container" data-cart-order="#cart_order_{$idSolution}" data-order-class=".order_detail" data-input-order="id" data-soluzione="{$idSolution}" data-soluzione-class=".btn_add_soluzione" data-last="{/data/member-order-open-detail/entry[solution/item/@id = $idSolution]/entry[position() = last()]/@id}" data-url="{$root}/action/?order-detail=yes">Rimuovi una taglia</button>
															</xsl:if>

														</div>

													</xsl:for-each>
												</xsl:when>
												<xsl:otherwise>
													<p class="option_title smaller" href="" style="color:black;"><xsl:value-of select="/data/translate/entry[code = 'empty_cart']/*[local-name() = $lanextended]" /></p>
												</xsl:otherwise>
											</xsl:choose>
											<p class="final_price smaller fw_500 color_first pt-3 text-end"> <span class="pe-2">Totale carrello:</span>€ <xsl:value-of select="format-number(sum(/data/member-order-open-detail/entry/price-tot), '0.00')"/></p>
										</div>
									</xsl:if>
									<xsl:choose>
										<xsl:when test="$member-id != ''">
											<a class="btn_book_option p text-uppercase fw-500 btn_bg_first d-block col-12 mb-4 mx-auto mt-2 px-2 px-md-5 py-3 option_unsaved btn_proceed_checkout mb-8" href="{$root}/checkout/{$testurl}">
												<xsl:value-of select="/data/translate/entry[code = 'proceed_order']/*[local-name() = $lanextended]" />
											</a>
										</xsl:when>
										<xsl:otherwise>
											<!-- <button class="btn_book_option p login_modal smaller text-uppercase fw-500 btn_bg_third d-block col-12 mb-4 mx-auto mt-2 px-2 px-md-5 py-3 option_unsaved" data-bs-toggle="modal" data-bs-target="#loginModal" data-login-form="#formNew">
												<xsl:value-of select="/data/translate/entry[code = 'proceed_order']/*[local-name() = $lanextended]" />
											</button> -->
										</xsl:otherwise>
									</xsl:choose>
								</div>

								<!-- <xsl:choose>
									<xsl:when test="$member-id != ''">
										<a class="btn_book_option p text-uppercase fw-500 btn_bg_first d-block col-12 mb-4 mx-auto mt-2 px-2 px-md-5 py-3 option_unsaved btn_proceed_checkout" href="{$root}/checkout/{$testurl}">
											<xsl:value-of select="/data/translate/entry[code = 'proceed_order']/*[local-name() = $lanextended]" />
										</a>
									</xsl:when>
									<xsl:otherwise>
										<button class="btn_book_option p login_modal smaller text-uppercase fw-500 btn_bg_third d-block col-12 mb-4 mx-auto mt-2 px-2 px-md-5 py-3 option_unsaved" data-bs-toggle="modal" data-bs-target="#loginModal" data-login-form="#formNew">
											<xsl:value-of select="/data/translate/entry[code = 'proceed_order']/*[local-name() = $lanextended]" />
										</button>
									</xsl:otherwise>
								</xsl:choose> -->

							</div>

						</div>

					</div>

					<!-- Cart (ecommerce). <<OUT>> -->

				</div>

			</nav>

		</div>

	</xsl:template>

</xsl:stylesheet>
