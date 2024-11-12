<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:param name="url-email" />
	<xsl:param name="url-tab" />
	<xsl:param name="member-id" />
	<xsl:param name="url-lan" />
	<xsl:param name="url-category" />
	<xsl:param name="url-activation" />
	<xsl:param name="url-profile-login" />
	<xsl:param name="page-types" />
	<xsl:param name="url-reg" />
	<xsl:param name="cookie-pass" />
	<xsl:param name="url-change" />
	<xsl:param name="url-code" />
	<xsl:param name="url-test" />
	<xsl:param name="url-year" />
	<xsl:param name="url-event" />
	<xsl:param name="url-prov" />
	<xsl:param name="url-region" />
	<xsl:param name="url-success" />
	<xsl:param name="url-successnews" />
	<xsl:param name="member-role" />
	<xsl:param name="url-emailchange" />
	<xsl:param name="url-recovery" />
	<xsl:param name="url-booked" />
	<xsl:param name="title" />
	<xsl:param name="id" />
	<xsl:variable name="lan">
		<xsl:choose>
			<xsl:when test="$url-lan = 'en'">en</xsl:when>
			<xsl:otherwise>it</xsl:otherwise>
		</xsl:choose>
	</xsl:variable>
	<xsl:variable name="lanurl">
		<xsl:choose>
			<xsl:when test="$url-lan = 'en'">?lan=en</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>
	<xsl:variable name="lanexp">
		<xsl:choose>
			<xsl:when test="$url-lan = 'en'">english</xsl:when>
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
	<xsl:variable name="namegrouplan">name-sezione-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="subtitlelan">subtitle-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="bodytitlelan">body-title-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="bodylan">body-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="asidetitlelan">aside-title-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="asidebodylan">aside-body-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="distrettititlelan">distretti-title-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="distrettibodylan">distretti-body-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="desclan">description-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="abslan">abstract-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="questionlan">question-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="answerlan">answer-<xsl:value-of select="$lanexp" /></xsl:variable>
	<xsl:variable name="is-logged-in" select="/data/events/login-info/@logged-in" />
	<xsl:variable name="root">https://renudo.it</xsl:variable>

	<!-- calendar row (test page)-->
	<xsl:template name="calendar_row_module">

		<div class="calendar_row_module">
			<div class="years row col-12 mb-3">
				<a href="#" class="calendar_Item list-group-item list-group-item-action fw-500 col-4 calendar_year_btn" data-year="{$this-year - 1}" data-month-class=".searchMonth" data-elem="#calendarEvents"><xsl:value-of select="$this-year - 1" /></a>

				<a href="#" class="calendar_Item list-group-item list-group-item-action fw-500 col-4 calendar_year_btn year_active" data-year="{$this-year}" data-month-class=".searchMonth" data-elem="#calendarEvents"><xsl:value-of select="$this-year" /></a>

				<a href="#" class="calendar_Item list-group-item list-group-item-action fw-500 col-4 calendar_year_btn" data-year="{$this-year + 1}" data-month-class=".searchMonth" data-elem="#calendarEvents"><xsl:value-of select="$this-year + 1" /></a>
			</div>

			<div class="calendar_List first-letter list-group row col-12 mb-6">

				<xsl:call-template name="calendar_row_month">
					<xsl:with-param name="index" select="1" />
					<xsl:with-param name="month" select="01" />
				</xsl:call-template>

			</div>

			<!-- #################### -->

			<div class="calendar_Filters roboto d-flex flex-wrap mt-n4" id="category_section">
				<h6 class="p fw-500 text-uppercase color_second mt-4 mb-4 w-100">
					<!-- categorie (tt) -->
					<xsl:value-of select="/data/translate/entry[code='categories']/*[local-name() = $lanextended]" />
				</h6>

				<xsl:for-each select="/data/event-type/entry">
					<div class="calendarFilter_Wrapper w-auto w-lg-100">
						<!-- <a class="searchType calendar_Filter small btn_bg_white rounded-pill d-block text-center text-uppercase me-2 me-lg-0 mb-3 py-2 px-4" href="#" role="button" data-value="{@id}" data-keyword="type" data-elem="#calendarEvents"><xsl:value-of select="*[local-name() = $namelan]" /></a> -->
						<!-- <a class="searchType calendar_Filter small btn_bg_white d-block text-center text-uppercase me-2 me-lg-0 mb-3 py-2 px-4" href="#" role="button" data-value="{@id}" data-keyword="type" data-elem="#calendarEvents"><xsl:value-of select="*[local-name() = $namelan]" /></a> -->
						<a class="searchType calendar_Filter evt_link color_black text-lowercase btn_bg_white d-block align-self-end mb-2 w-auto" href="#event_list_content" role="button" data-value="{@id}" data-keyword="type" data-elem="#calendarEvents">
							<span class="span_link mid_small mb-2 pe-2 fw-300 roboto_condensed">
								<xsl:value-of select="*[local-name() = $namelan]" />

								<!-- APPROFONDISCI -->
								<!-- <xsl:value-of select="/data/translate/entry[code = 'read_more']/*[local-name() = $lanextended]" /> -->
							</span>

							<!-- <i class="icon_link fa-light fa-arrow-right-long h4 fw-200 align-middle" /> -->
						</a>
					</div>
				</xsl:for-each>

				<h6 class="p fw-500 text-uppercase color_second mt-4 mb-4 w-100">
					<!-- cerca evento  -->
					<xsl:value-of select="/data/translate/entry[code='evt_search']/*[local-name() = $lanextended]" />
				</h6>

				<form class="form_contacts_allianz form_contacts_container col-12" method="post" action="{$current-url}/">
					<input name="xsrf" type="hidden" value="{$cookie-xsrf-token}" />
					<div class="form_inner_row row">
						<div class="form_contacts_wrapper form-floating col-12 mb-4">
							<input id="titleCalendar" type="text" class="form-control small noto w-100" placeholder="{/data/translate/entry[code='name']/*[local-name() = $lanextended]}" />
							<label class="small" for="titleCalendar">
								<!-- Nome (tt) -->
								<xsl:value-of select="/data/translate/entry[code='name']/*[local-name() = $lanextended]" />
							</label>
						</div>
					</div>

					<!-- <a class="calendar_Filter small btn_bg_white rounded-pill text-center text-uppercase mb-3 py-2 px-4" id="searchCalendarName" role="button" data-url="{$root}/action/?lan={$lan}&amp;search-calendar=yes" data-title="#titleCalendar" data-elem="#calendarEvents"> -->
					<a class="calendar_Filter small btn_bg_white text-center text-uppercase mb-3 py-2 px-4" id="searchCalendarName" role="button" data-url="{$root}/action/?lan={$lan}&amp;search-calendar=yes" data-title="#titleCalendar" data-elem="#calendarEvents">
						<!-- vedi tutti (tt) -->
						<!-- <xsl:value-of select="/data/translate/entry[code='search']/*[local-name() = $lanextended]" /> -->
						<i class="fal fa-search"></i>
					</a>
				</form>
			</div>
		</div>

	</xsl:template>



	<xsl:template name="calendar_row_month">
		<xsl:param name="index" />
		<xsl:param name="month" />

		<xsl:call-template name="monthLabel_row">
			<xsl:with-param name="month" select="$month" />
		</xsl:call-template>

		<xsl:if test="$index &lt; 12">
			<xsl:call-template name="calendar_row_month">
				<xsl:with-param name="index" select="$index + 1" />
				<xsl:with-param name="month" select="$month + 1" />
			</xsl:call-template>
		</xsl:if>
	</xsl:template>

	<xsl:template name="monthLabel_row">
		<xsl:param name="month" />

		<xsl:choose>
			<xsl:when test="($month = '1') or ($month = '01')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 searchMonthTest" data-start-date="-01-01" data-elem="#calendarEvents">
					<xsl:if test="$this-month = 01">
						<xsl:attribute name="class">searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 month_active</xsl:attribute>
					</xsl:if>
					<span class="calendar_Month fw-300">
						<!-- gennaio (tt) -->
						<xsl:value-of select="/data/translate/entry[code='jan']/*[local-name() = $lanextended]" />
					</span>
					<!-- <span class="calendar_FullYear roboto ms-auto me-1"><xsl:value-of select="$year" /></span> -->
				</a>
			</xsl:when>
			<xsl:when test="($month = '2') or ($month = '02')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 searchMonthTest" data-start-date="-02-01" data-elem="#calendarEvents">
					<xsl:if test="$this-month = 02">
						<xsl:attribute name="class">searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 month_active</xsl:attribute>
					</xsl:if>
					<span class="calendar_Month fw-300">
						<!-- febbraio (tt) -->
						<xsl:value-of select="/data/translate/entry[code='feb']/*[local-name() = $lanextended]" />
					</span>
					<!-- <span class="calendar_FullYear roboto ms-auto me-1"><xsl:value-of select="$year" /></span> -->
				</a>
			</xsl:when>
			<xsl:when test="($month = '3') or ($month = '03')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 searchMonthTest" data-start-date="-03-01" data-elem="#calendarEvents">
					<xsl:if test="$this-month = 03">
						<xsl:attribute name="class">searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 month_active</xsl:attribute>
					</xsl:if>
					<span class="calendar_Month fw-300">
						<!-- marzo (tt) -->
						<xsl:value-of select="/data/translate/entry[code='mar']/*[local-name() = $lanextended]" />
					</span>
					<!-- <span class="calendar_FullYear roboto ms-auto me-1"><xsl:value-of select="$year" /></span> -->
				</a>
			</xsl:when>
			<xsl:when test="($month = '4') or ($month = '04')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 searchMonthTest" data-start-date="-04-01" data-elem="#calendarEvents">
					<xsl:if test="$this-month = 04">
						<xsl:attribute name="class">searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 month_active</xsl:attribute>
					</xsl:if>
					<span class="calendar_Month fw-300">
						<!-- aprile (tt) -->
						<xsl:value-of select="/data/translate/entry[code='apr']/*[local-name() = $lanextended]" />
					</span>
					<!-- <span class="calendar_FullYear roboto ms-auto me-1"><xsl:value-of select="$year" /></span> -->
				</a>
			</xsl:when>
			<xsl:when test="($month = '5') or ($month = '05')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 searchMonthTest" data-start-date="-05-01" data-elem="#calendarEvents">
					<xsl:if test="$this-month = 05">
						<xsl:attribute name="class">searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 month_active</xsl:attribute>
					</xsl:if>
					<span class="calendar_Month fw-300">

						<xsl:value-of select="/data/translate/entry[code='may']/*[local-name() = $lanextended]" />
					</span>
					<!-- <span class="calendar_FullYear roboto ms-auto me-1"><xsl:value-of select="$year" /></span> -->
				</a>
			</xsl:when>
			<xsl:when test="($month = '6') or ($month = '06')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 searchMonthTest" data-start-date="-06-01" data-elem="#calendarEvents">
					<xsl:if test="$this-month = 06">
						<xsl:attribute name="class">searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 month_active</xsl:attribute>
					</xsl:if>
					<span class="calendar_Month fw-300">

						<xsl:value-of select="/data/translate/entry[code='jun']/*[local-name() = $lanextended]" />
					</span>
					<!-- <span class="calendar_FullYear roboto ms-auto me-1"><xsl:value-of select="$year" /></span> -->
				</a>
			</xsl:when>
			<xsl:when test="($month = '7') or ($month = '07')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 searchMonthTest" data-start-date="-07-01" data-elem="#calendarEvents">
					<xsl:if test="$this-month = 07">
						<xsl:attribute name="class">searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 month_active</xsl:attribute>
					</xsl:if>
					<span class="calendar_Month fw-300">

						<xsl:value-of select="/data/translate/entry[code='jul']/*[local-name() = $lanextended]" />
					</span>
					<!-- <span class="calendar_FullYear roboto ms-auto me-1"><xsl:value-of select="$year" /></span> -->
				</a>
			</xsl:when>
			<xsl:when test="($month = '8') or ($month = '08')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 searchMonthTest" data-start-date="-08-01" data-elem="#calendarEvents">
					<xsl:if test="$this-month = 08">
						<xsl:attribute name="class">searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 month_active</xsl:attribute>
					</xsl:if>
					<span class="calendar_Month fw-300">

						<xsl:value-of select="/data/translate/entry[code='aug']/*[local-name() = $lanextended]" />
					</span>
					<!-- <span class="calendar_FullYear roboto ms-auto me-1"><xsl:value-of select="$year" /></span> -->
				</a>
			</xsl:when>
			<xsl:when test="($month = '9') or ($month = '09')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 searchMonthTest" data-start-date="-09-01" data-elem="#calendarEvents">
					<xsl:if test="$this-month = 09">
						<xsl:attribute name="class">searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 month_active</xsl:attribute>
					</xsl:if>
					<span class="calendar_Month fw-300">

						<xsl:value-of select="/data/translate/entry[code='sep']/*[local-name() = $lanextended]" />
					</span>
					<!-- <span class="calendar_FullYear roboto ms-auto me-1"><xsl:value-of select="$year" /></span> -->
				</a>
			</xsl:when>
			<xsl:when test="$month = '10'">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 searchMonthTest" data-start-date="-10-01" data-elem="#calendarEvents">
					<xsl:if test="$this-month = 10">
						<xsl:attribute name="class">searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 month_active</xsl:attribute>
					</xsl:if>
					<span class="calendar_Month fw-300">

						<xsl:value-of select="/data/translate/entry[code='oct']/*[local-name() = $lanextended]" />
					</span>
					<!-- <span class="calendar_FullYear roboto ms-auto me-1"><xsl:value-of select="$year" /></span> -->
				</a>
			</xsl:when>
			<xsl:when test="$month = '11'">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 searchMonthTest" data-start-date="-11-01" data-elem="#calendarEvents">
					<xsl:if test="$this-month = 11">
						<xsl:attribute name="class">searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 month_active</xsl:attribute>
					</xsl:if>
					<span class="calendar_Month fw-300">

						<xsl:value-of select="/data/translate/entry[code='nov']/*[local-name() = $lanextended]" />
					</span>
					<!-- <span class="calendar_FullYear roboto ms-auto me-1"><xsl:value-of select="$year" /></span> -->
				</a>
			</xsl:when>
			<xsl:otherwise>
				<a class="searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 searchMonthTest" data-start-date="-12-01" data-elem="#calendarEvents">
					<xsl:if test="$this-month = 12">
						<xsl:attribute name="class">searchMonth calendar_Item list-group-item list-group-item-action col-4 mb-2 month_active</xsl:attribute>
					</xsl:if>
					<span class="calendar_Month fw-300">

						<xsl:value-of select="/data/translate/entry[code='dec']/*[local-name() = $lanextended]" />
					</span>
					<!-- <span class="calendar_FullYear roboto ms-auto me-1"><xsl:value-of select="$year" /></span> -->
				</a>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>



	<!-- calendar list -->
	<xsl:template name="calendar_module">

		<div class="calendar_module">
			<div class="calendar_List first-letter list-group row mb-6">
				<xsl:call-template name="calendar_month">
					<xsl:with-param name="index" select="1" />
					<xsl:with-param name="year">
						<xsl:choose>
							<xsl:when test="$this-month = 01">
								<xsl:value-of select="$this-year - 1" />
							</xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="$this-year" />
							</xsl:otherwise>
						</xsl:choose>
					</xsl:with-param>
					<xsl:with-param name="month">
						<xsl:choose>
							<xsl:when test="$this-month = 01">
								<xsl:value-of select="12" />
							</xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="$this-month - 1" />
							</xsl:otherwise>
						</xsl:choose>
					</xsl:with-param>
				</xsl:call-template>
			</div>

			<!-- #################### -->

			<div class="calendar_Filters roboto d-flex flex-wrap mt-n4" id="category_section">
				<h6 class="p fw-500 text-uppercase color_second mt-4 mb-4 w-100">
					<!-- categorie (tt) -->
					<xsl:value-of select="/data/translate/entry[code='categories']/*[local-name() = $lanextended]" />
				</h6>

				<xsl:for-each select="/data/event-type/entry">
					<div class="calendarFilter_Wrapper w-auto w-lg-100">
						<!-- <a class="searchType calendar_Filter small btn_bg_white rounded-pill d-block text-center text-uppercase me-2 me-lg-0 mb-3 py-2 px-4" href="#" role="button" data-value="{@id}" data-keyword="type" data-elem="#calendarEvents"><xsl:value-of select="*[local-name() = $namelan]" /></a> -->
						<!-- <a class="searchType calendar_Filter small btn_bg_white d-block text-center text-uppercase me-2 me-lg-0 mb-3 py-2 px-4" href="#" role="button" data-value="{@id}" data-keyword="type" data-elem="#calendarEvents"><xsl:value-of select="*[local-name() = $namelan]" /></a> -->
						<a class="searchType calendar_Filter evt_link color_black text-lowercase btn_bg_white d-block align-self-end mb-2 w-auto" href="#" role="button" data-value="{@id}" data-keyword="type" data-elem="#calendarEvents">
							<span class="span_link mid_small mb-2 pe-2 fw-300 roboto_condensed">
								<xsl:value-of select="*[local-name() = $namelan]" />

								<!-- APPROFONDISCI -->
								<!-- <xsl:value-of select="/data/translate/entry[code = 'read_more']/*[local-name() = $lanextended]" /> -->
							</span>

							<!-- <i class="icon_link fa-light fa-arrow-right-long h4 fw-200 align-middle" /> -->
						</a>

						<a class="searchType calendar_Filter evt_link color_black text-lowercase btn_bg_white d-block align-self-end mb-2 w-auto" href="#" role="button" data-value="{@id}" data-keyword="type" data-elem="#calendarEvents">
							<span class="span_link mid_small mb-2 pe-2 fw-300 roboto_condensed">
								CATEGORIA 1
							</span>

							<!-- <i class="icon_link fa-light fa-arrow-right-long h4 fw-200 align-middle" /> -->
						</a>

						<a class="searchType calendar_Filter evt_link color_black text-lowercase btn_bg_white d-block align-self-end mb-2 w-auto" href="#" role="button" data-value="{@id}" data-keyword="type" data-elem="#calendarEvents">
							<span class="span_link mid_small mb-2 pe-2 fw-300 roboto_condensed">
								LOREM IPSUM
							</span>

							<!-- <i class="icon_link fa-light fa-arrow-right-long h4 fw-200 align-middle" /> -->
						</a>

						<a class="searchType calendar_Filter evt_link color_black text-lowercase btn_bg_white d-block align-self-end mb-2 w-auto" href="#" role="button" data-value="{@id}" data-keyword="type" data-elem="#calendarEvents">
							<span class="span_link mid_small mb-2 pe-2 fw-300 roboto_condensed">
								TEST
							</span>

							<!-- <i class="icon_link fa-light fa-arrow-right-long h4 fw-200 align-middle" /> -->
						</a>
					</div>
				</xsl:for-each>

				<h6 class="p fw-500 text-uppercase color_second mt-4 mb-4 w-100">
					<!-- cerca evento  -->
					<xsl:value-of select="/data/translate/entry[code='evt_search']/*[local-name() = $lanextended]" />
				</h6>

				<form class="form_contacts_allianz form_contacts_container col-12" method="post" action="{$current-url}/">
					<input name="xsrf" type="hidden" value="{$cookie-xsrf-token}" />
					<div class="form_inner_row row">
						<div class="form_contacts_wrapper form-floating col-12 mb-4">
							<input id="titleCalendar" type="text" class="form-control small noto w-100" placeholder="{/data/translate/entry[code='name']/*[local-name() = $lanextended]}" />
							<label class="small" for="titleCalendar">
								<!-- Nome (tt) -->
								<xsl:value-of select="/data/translate/entry[code='name']/*[local-name() = $lanextended]" />
							</label>
						</div>
					</div>

					<!-- <a class="calendar_Filter small btn_bg_white rounded-pill text-center text-uppercase mb-3 py-2 px-4" id="searchCalendarName" role="button" data-url="{$root}/action/?lan={$lan}&amp;search-calendar=yes" data-title="#titleCalendar" data-elem="#calendarEvents"> -->
					<a class="calendar_Filter small btn_bg_white text-center text-uppercase mb-3 py-2 px-4" id="searchCalendarName" role="button" data-url="{$root}/action/?lan={$lan}&amp;search-calendar=yes" data-title="#titleCalendar" data-elem="#calendarEvents">
						<!-- vedi tutti (tt) -->
						<!-- <xsl:value-of select="/data/translate/entry[code='search']/*[local-name() = $lanextended]" /> -->
						<i class="fal fa-search"></i>
					</a>
				</form>
			</div>
		</div>

	</xsl:template>


	<!-- years -->
	<xsl:template name="calendar_month">
		<xsl:param name="index" />
		<xsl:param name="month" />
		<xsl:param name="year" />

		<xsl:call-template name="monthLabel">
			<xsl:with-param name="month" select="$month" />
			<xsl:with-param name="year" select="$year" />
		</xsl:call-template>

		<xsl:if test="$index &lt; 4">
			<xsl:call-template name="calendar_month">
				<xsl:with-param name="index" select="$index + 1" />
				<xsl:with-param name="year">
					<xsl:choose>
						<xsl:when test="$month = 12">
							<xsl:value-of select="$year + 1" />
						</xsl:when>
						<xsl:otherwise>
							<xsl:value-of select="$year" />
						</xsl:otherwise>
					</xsl:choose>
				</xsl:with-param>
				<xsl:with-param name="month">
					<xsl:choose>
						<xsl:when test="$month = 12">
							<xsl:value-of select="01" />
						</xsl:when>
						<xsl:otherwise>
							<xsl:value-of select="$month + 1" />
						</xsl:otherwise>
					</xsl:choose>
				</xsl:with-param>
			</xsl:call-template>
		</xsl:if>
	</xsl:template>


	<xsl:template name="monthLabel">
		<xsl:param name="month" />
		<xsl:param name="year" />

		<xsl:choose>
			<xsl:when test="($month = '1') or ($month = '01')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action d-flex mb-4" data-start-date="{$year}-01-01" data-end-date="{$year}-01-31" data-elem="#calendarEvents">
					<span class="calendar_Month fw-300">
						<!-- gennaio (tt) -->
						<xsl:value-of select="/data/translate/entry[code='january']/*[local-name() = $lanextended]" />
					</span>

					<span class="calendar_FullYear roboto ms-auto me-1">
						<xsl:value-of select="$year" />
					</span>
				</a>
			</xsl:when>
			<xsl:when test="($month = '2') or ($month = '02')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action d-flex mb-4" data-start-date="{$year}-02-01" data-end-date="{$year}-02-29" data-elem="#calendarEvents">
					<span class="calendar_Month fw-300">
						<!-- febbraio (tt) -->
						<xsl:value-of select="/data/translate/entry[code='february']/*[local-name() = $lanextended]" />
					</span>

					<span class="calendar_FullYear roboto ms-auto me-1">
						<xsl:value-of select="$year" />
					</span>
				</a>
			</xsl:when>
			<xsl:when test="($month = '3') or ($month = '03')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action d-flex mb-4" data-start-date="{$year}-03-01" data-end-date="{$year}-03-31" data-elem="#calendarEvents">
					<span class="calendar_Month fw-300">
						<!-- marzo (tt) -->
						<xsl:value-of select="/data/translate/entry[code='march']/*[local-name() = $lanextended]" />
					</span>

					<span class="calendar_FullYear roboto ms-auto me-1">
						<xsl:value-of select="$year" />
					</span>
				</a>
			</xsl:when>
			<xsl:when test="($month = '4') or ($month = '04')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action d-flex mb-4" data-start-date="{$year}-04-01" data-end-date="{$year}-04-30" data-elem="#calendarEvents">
					<span class="calendar_Month fw-300">
						<!-- aprile (tt) -->
						<xsl:value-of select="/data/translate/entry[code='april']/*[local-name() = $lanextended]" />
					</span>

					<span class="calendar_FullYear roboto ms-auto me-1">
						<xsl:value-of select="$year" />
					</span>
				</a>
			</xsl:when>
			<xsl:when test="($month = '5') or ($month = '05')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action d-flex mb-4" data-start-date="{$year}-05-01" data-end-date="{$year}-05-31" data-elem="#calendarEvents">
					<span class="calendar_Month fw-300">
						<!-- maggio -->
						<xsl:value-of select="/data/translate/entry[code='may']/*[local-name() = $lanextended]" />
					</span>

					<span class="calendar_FullYear roboto ms-auto me-1">
						<xsl:value-of select="$year" />
					</span>
				</a>
			</xsl:when>
			<xsl:when test="($month = '6') or ($month = '06')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action d-flex mb-4" data-start-date="{$year}-06-01" data-end-date="{$year}-06-30" data-elem="#calendarEvents">
					<span class="calendar_Month fw-300">
						<!-- giugno -->
						<xsl:value-of select="/data/translate/entry[code='june']/*[local-name() = $lanextended]" />
					</span>

					<span class="calendar_FullYear roboto ms-auto me-1">
						<xsl:value-of select="$year" />
					</span>
				</a>
			</xsl:when>
			<xsl:when test="($month = '7') or ($month = '07')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action d-flex mb-4" data-start-date="{$year}-07-01" data-end-date="{$year}-07-31" data-elem="#calendarEvents">
					<span class="calendar_Month fw-300">
						<!-- luglio -->
						<xsl:value-of select="/data/translate/entry[code='july']/*[local-name() = $lanextended]" />
					</span>

					<span class="calendar_FullYear roboto ms-auto me-1">
						<xsl:value-of select="$year" />
					</span>
				</a>
			</xsl:when>
			<xsl:when test="($month = '8') or ($month = '08')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action d-flex mb-4" data-start-date="{$year}-08-01" data-end-date="{$year}-08-31" data-elem="#calendarEvents">
					<span class="calendar_Month fw-300">
						<!-- agosto -->
						<xsl:value-of select="/data/translate/entry[code='august']/*[local-name() = $lanextended]" />
					</span>

					<span class="calendar_FullYear roboto ms-auto me-1">
						<xsl:value-of select="$year" />
					</span>
				</a>
			</xsl:when>
			<xsl:when test="($month = '9') or ($month = '09')">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action d-flex mb-4" data-start-date="{$year}-09-01" data-end-date="{$year}-09-30" data-elem="#calendarEvents">
					<span class="calendar_Month fw-300">
						<!-- settembre -->
						<xsl:value-of select="/data/translate/entry[code='september']/*[local-name() = $lanextended]" />
					</span>

					<span class="calendar_FullYear roboto ms-auto me-1">
						<xsl:value-of select="$year" />
					</span>
				</a>
			</xsl:when>
			<xsl:when test="$month = '10'">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action d-flex mb-4" data-start-date="{$year}-10-01" data-end-date="{$year}-10-31" data-elem="#calendarEvents">
					<span class="calendar_Month fw-300">
						<!-- ottobre -->
						<xsl:value-of select="/data/translate/entry[code='october']/*[local-name() = $lanextended]" />
					</span>

					<span class="calendar_FullYear roboto ms-auto me-1">
						<xsl:value-of select="$year" />
					</span>
				</a>
			</xsl:when>
			<xsl:when test="$month = '11'">
				<a class="searchMonth calendar_Item list-group-item list-group-item-action d-flex mb-4" data-start-date="{$year}-11-01" data-end-date="{$year}-11-30" data-elem="#calendarEvents">
					<span class="calendar_Month fw-300">
						<!-- novembre -->
						<xsl:value-of select="/data/translate/entry[code='november']/*[local-name() = $lanextended]" />
					</span>

					<span class="calendar_FullYear roboto ms-auto me-1">
						<xsl:value-of select="$year" />
					</span>
				</a>
			</xsl:when>
			<xsl:otherwise>
				<a class="searchMonth calendar_Item list-group-item list-group-item-action d-flex mb-4" data-start-date="{$year}-12-01" data-end-date="{$year}-12-31" data-elem="#calendarEvents">
					<span class="calendar_Month fw-300">
						<!-- dicembre -->
						<xsl:value-of select="/data/translate/entry[code='december']/*[local-name() = $lanextended]" />
					</span>

					<span class="calendar_FullYear roboto ms-auto me-1">
						<xsl:value-of select="$year" />
					</span>
				</a>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

</xsl:stylesheet>
