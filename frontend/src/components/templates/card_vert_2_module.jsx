<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template name="card_vert_2_module">

		<xsl:param name="id" />
		<xsl:param name="title" />
		<xsl:param name="titleHandle" />
		<xsl:param name="subtitle" />
		<xsl:param name="abstract" />
		<xsl:param name="body" />
		<xsl:param name="date" />
		<xsl:param name="author" />
		<xsl:param name="page" />
		<xsl:param name="category" />
		<xsl:param name="categoryHandle" />
		<xsl:param name="image" />
		<xsl:param name="imagetitle" />

		<article class="card_vert_2_module col-12 col-md-6 col-lg-6 col-xl-4 px-md-4 mb-5">

			<a class="a_card d-block col-12 border-top border-2 border_color_black pt-2" href="{$root}/{$page}/{$id}">
				<xsl:if test="$titleHandle != ''">
					<xsl:attribute name="href"><xsl:value-of select="$root" />/<xsl:value-of select="$page" />/<xsl:value-of select="$id" />/<xsl:value-of select="$titleHandle" /></xsl:attribute>
				</xsl:if>
				<div class="card_row_top text-break d-flex flex-wrap mb-5">

					<div class="square_data fw-700 color_first small mw-100">
						<time class="date_card_event_home day month year" datetime="{$date}"><xsl:call-template name="day"><xsl:with-param name="date" select="$date" /></xsl:call-template>-<xsl:call-template name="month"><xsl:with-param name="date" select="$date" /></xsl:call-template>-<xsl:call-template name="year"><xsl:with-param name="date" select="$date" /></xsl:call-template></time>
					</div>

					<xsl:if test="$category">
						<p class="small fw-400 m-0 ms-auto">
							<xsl:value-of select="$category" />
						</p>
					</xsl:if>

				</div>

				<h4 class="title_event_card d-inline h2 fw-700 m-0">
					<xsl:value-of select="title" />
					asdasasdasd
				</h4>
				<p class="btn_white_blue go_event p fw-500 color_first m-0 my-3">
					<xsl:if test="$abstract">
						<xsl:copy-of select="$abstract" />
					</xsl:if>
				</p>

				<xsl:if test="$author">
					<p class="small fw-700 m-0"><xsl:value-of select="author" /></p>
				</xsl:if>
			</a>

		</article>
	</xsl:template>

</xsl:stylesheet>
