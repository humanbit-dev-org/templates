<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template name="card_horiz_module">

		<xsl:param name="id"/>
		<xsl:param name="title"/>
		<xsl:param name="titleHandle"/>
		<xsl:param name="subtitle"/>
		<xsl:param name="abstract"/>
		<xsl:param name="body"/>
		<xsl:param name="date"/>
		<xsl:param name="author"/>
		<xsl:param name="page"/>
		<xsl:param name="category"/>
		<xsl:param name="categoryHandle"/>
		<xsl:param name="image"/>
		<xsl:param name="imagetitle"/>

		<div class="card_horiz_module">
			<div class="card border-0">
				<div class="row border-bottom pt-3 pb-4">
					<div class="col-12 col-md-8 pe-md-3">
						<div class="card-body p-0 pe-lg-5">
							<small class="small d-inline-block pb-1">
								<span class="roboto color_third text-uppercase"><xsl:value-of select="*[local-name() = $subtitlelan]"/></span>
								<xsl:text> </xsl:text>
								<span class="roboto color_second">
									<xsl:call-template name="day"><xsl:with-param name="date" select="$date"/></xsl:call-template>-<xsl:call-template name="month"><xsl:with-param name="date" select="$date"/></xsl:call-template>-<xsl:call-template name="year"><xsl:with-param name="date" select="$date"/></xsl:call-template>
								</span>
							</small>

							<h3 class="card-title h5 color_second m-0 my-3"><xsl:value-of select="$title"/></h3>
							<xsl:if test="$abstract">
								<p class="card-text color_second m-0 mb-2">
									<xsl:copy-of select="$abstract"/>
								</p>
							</xsl:if>

							<a class="card_horiz_btn d-inline-block roboto color_third text-end text-uppercase border border_color_third mt-4 py-3 px-4" href="{$root}/{$page}/{$id}">
								<xsl:if test="$titleHandle != ''">
									<xsl:attribute name="href"><xsl:value-of select="$root"/>/<xsl:value-of select="$page"/>/<xsl:value-of select="$id"/>/<xsl:value-of select="$titleHandle"/></xsl:attribute>
								</xsl:if>
								<span class="small d-block mb-2"><xsl:value-of select="/data/translate/entry[code = 'send']/*[local-name() = $lanextended]"/></span>
								<i class="fa-light fa-arrow-right-long d-block h3 text-end"></i>
							</a>
						</div>
					</div>

					<div class="col-md-4 d-flex align-items-start justify-content-center text-center mt-5 mt-lg-0" title="{$imagetitle}" style="background-image: url('{$workspace}/uploads/{$image}'); background-size: cover; background-repeat: no-repeat; background-position: center center; height: 250px;">
						<div class="small roboto text-uppercase color_third bg_color_first row col-5 p-2">
							<span class="">
								<xsl:call-template name="day">
									<xsl:with-param name="date" select="$date"/>
								</xsl:call-template>
							</span>
							<span class="">
								<xsl:call-template name="month_label">
									<xsl:with-param name="date" select="$date"/>
								</xsl:call-template>
							</span>
							<span class="">
								<xsl:call-template name="year">
									<xsl:with-param name="date" select="$date"/>
								</xsl:call-template>
							</span>
						</div>
						<!-- <img src="https://picsum.photos/300/200?random=2" class="img-fluid w-100 mw-100 h-auto" alt="..." /> -->
					</div>
				</div>
			</div>

		</div>

	</xsl:template>

</xsl:stylesheet>
