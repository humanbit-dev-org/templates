<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template name="txt_img_row_module">

		<xsl:param name="id"/>
		<xsl:param name="title"/>
		<xsl:param name="titleHandle"/>
		<xsl:param name="subtitle"/>
		<xsl:param name="abstract"/>
		<xsl:param name="body"/>
		<xsl:param name="date"/>
		<xsl:param name="author"/>
		<xsl:param name="page"/>
		<xsl:param name="pageList"/>
		<xsl:param name="category"/>
		<xsl:param name="categoryHandle"/>
		<xsl:param name="image"/>
		<xsl:param name="imagetitle"/>
		<xsl:param name="mod" />

		<div class="txt_img_row_module">

			<div class="space_home_row col-12 row align-items-center mb-5">
				<xsl:choose>
					<xsl:when test="(position() mod $mod) != 0">
						<div class="space_home_element space_home_text col-12 col-lg-5 ">
							<h5 class="h4 text-uppercase m-0 mb-2 mb-lg-3">
								<xsl:value-of select="$title"/>
							</h5>
							<xsl:if test="$subtitle">
								<h6 class="p_bold m-0 mb-3"><xsl:copy-of select="$subtitle"/></h6>
							</xsl:if>
							<xsl:if test="$abstract">
								<p class="m-0 mb-4 mb-lg-6"><xsl:copy-of select="$abstract"/></p>
							</xsl:if>
							<a class="txt_img_row_btn d-inline-block roboto color_third text-end text-uppercase border border_color_third mt-4 py-3 px-5" href="{$root}/{$page}/{$id}">
								<xsl:if test="$titleHandle != ''">
									<xsl:attribute name="href"><xsl:value-of select="$root"/>/<xsl:value-of select="$page"/>/<xsl:value-of select="$id"/>/<xsl:value-of select="$titleHandle"/></xsl:attribute>
								</xsl:if>
								<span class="small d-block mb-2"><xsl:value-of select="/data/translate/entry[code = 'send']/*[local-name() = $lanextended]"/></span>
								<i class="fa-light fa-arrow-right-long d-block h3 text-end"></i>
							</a>
						</div>

						<div class="space_home_element space_home_img col-12 col-lg-7">
							<figure class="col-12 m-0 mb-3 mb-lg-0">
								<img class="w-100" title="{$imagetitle}" alt="{$imagetitle}" src="{$workspace}/uploads/{$image}"></img>
							</figure>
						</div>
					</xsl:when>
					<xsl:otherwise>
						<div class="space_home_element space_home_img col-12 col-lg-7">
							<figure class="col-12 m-0 mb-3 mb-lg-0">
								<img class="w-100" title="{$imagetitle}" alt="{$imagetitle}" src="{$workspace}/uploads/{$image}"></img>
							</figure>
						</div>

						<div class="space_home_element space_home_text col-12 col-lg-5 ">
							<h5 class="h4 text-uppercase m-0 mb-2 mb-lg-3">
								<xsl:value-of select="$title"/>
							</h5>
							<xsl:if test="$subtitle">
								<h6 class="p_bold m-0 mb-3"><xsl:copy-of select="$subtitle"/></h6>
							</xsl:if>
							<xsl:if test="$abstract">
								<p class="m-0 mb-4 mb-lg-6"><xsl:copy-of select="$abstract"/></p>
							</xsl:if>
							<a class="txt_img_row_btn d-inline-block roboto color_third text-end text-uppercase border border_color_third mt-4 py-3 px-5" href="#">
								<span class="small d-block mb-2"><xsl:value-of select="/data/translate/entry[code = 'send']/*[local-name() = $lanextended]"/></span>
								<i class="fa-light fa-arrow-right-long d-block h3 text-end"></i>
							</a>
						</div>
					</xsl:otherwise>
				</xsl:choose>
			</div>

		</div>

	</xsl:template>

</xsl:stylesheet>
