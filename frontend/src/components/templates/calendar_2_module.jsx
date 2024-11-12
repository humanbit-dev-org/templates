<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template name="calendar_2_module">
		<div class="calendar_2_module">
			<div class="calendar_List first-letter list-group row mb-6">
				<xsl:call-template name="calendar_month">
					<xsl:with-param name="index" select="1"/>
					<xsl:with-param name="month" select="$this-month"/>
					<xsl:with-param name="year" select="$this-year"/>
				</xsl:call-template>
			</div>
		</div>
	</xsl:template>

	<xsl:template name="horiz_calendar_module">
		
		<xsl:param name="from_today"/>

		<div class="calendar_2_module">
			<div class="calendar_button_container button_container d-flex flex-wrap align-items-center justify-content-between my-4 py-2">
				<button class="btn_chevron bg_color_first border-0 px-0">
					<i class="nav_calendar prev_cal chevron_calendar h1 color_white text-center border border-2 border_color_white rounded-circle fa-light fa-chevron-left m-0" data-url-calendar="{$root}/action/?action=calendar" data-url-month="{$root}/action/?action=month" data-month="#month_button" data-elem="#calendar_div" data-button="#btn_step_1"/>
				</button>
				<button id="month_button" data-year="{$this-year}" data-value="{$this-month}" class="btn_calendar small  text-uppercase btn_bg_first mx-md-4">
					<xsl:call-template name="GetMonthName">
						<xsl:with-param name="month" select="number($this-month)"/>
					</xsl:call-template>
					<xsl:text> </xsl:text><xsl:value-of select="$this-year"/>
				</button>
				<button class="btn_chevron bg_color_first border-0 px-0">
					<i class="nav_calendar next_cal chevron_calendar h1 color_white text-center border border-2 border_color_white rounded-circle fa-light fa-chevron-right m-0" data-url-calendar="{$root}/action/?action=calendar" data-url-month="{$root}/action/?action=month" data-month="#month_button" data-elem="#calendar_div" data-button="#btn_step_1"/>
				</button>
			</div>
			<div class="middle_container row justify-content-center align-self-center mx-auto mb-5">
				<ul class="day_of_week m-0 mb-3 p-0 pb-1 w-md-auto">
					<li class="li_date gotham h5  color_white opacity-75"><xsl:value-of select="/data/translate/entry[code='mon']/*[local-name() = $lanextended]"/></li>
					<li class="li_date gotham h5  color_white opacity-75"><xsl:value-of select="/data/translate/entry[code='tue']/*[local-name() = $lanextended]"/></li>
					<li class="li_date gotham h5  color_white opacity-75"><xsl:value-of select="/data/translate/entry[code='wed']/*[local-name() = $lanextended]"/></li>
					<li class="li_date gotham h5  color_white opacity-75"><xsl:value-of select="/data/translate/entry[code='thu']/*[local-name() = $lanextended]"/></li>
					<li class="li_date gotham h5  color_white opacity-75"><xsl:value-of select="/data/translate/entry[code='fri']/*[local-name() = $lanextended]"/></li>
					<li class="li_date gotham h5  color_white opacity-75"><xsl:value-of select="/data/translate/entry[code='sat']/*[local-name() = $lanextended]"/></li>
					<li class="li_date gotham h5  color_white opacity-75"><xsl:value-of select="/data/translate/entry[code='sun']/*[local-name() = $lanextended]"/></li>
				</ul>

				<div id="calendar_div" class="date_grid w-md-auto">
					<xsl:call-template name="calendar">
						<xsl:with-param name="from_today" select="$from_today"/>
						<xsl:with-param name="month" select="number($this-month)"/>
						<xsl:with-param name="year" select="number($this-year)"/>
					</xsl:call-template>
				</div>

			</div>
		</div>

	</xsl:template>

</xsl:stylesheet>
