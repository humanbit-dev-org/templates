<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template name="txt_img_row_vert_module">

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

		<!-- Copy, paste and uncomment the container on the following line of code into the intended .html/.xsl page to wrap the module: -->
		<!-- <div class="txt_img_row_vert row mx-n4 mx-xl-n5"> -->

			<div class="txt_img_row_vert_module col-12 col-md-6 mb-5 px-4 px-xl-5">
				<xsl:choose>
					<xsl:when test="(position() mod $mod) != 0">
						<!-- <div class="cont_img_wrap order-1 order-md-0 mb-5"> -->
						<figure class="cont_fig img-hover-zoom order-1 order-md-0 mb-4">
							<img class="cont_img img-fluid w-100" title="{$imagetitle}" alt="{$imagetitle}" src="{$workspace}/uploads/{$image}" />
						</figure>
						<!-- </div> -->

						<div class="cont_txt row order-0 order-md-1 col-12">
							<a class="cont_cat roboto_condensed smaller fw-400 text-uppercase mb-2" href="{$root}/{$pageList}/?cat={$categoryHandle}">
								<span class="fill_fx roboto_condensed smaller fw-400">
									<xsl:value-of select="$category"/>
								</span>
							</a>

							<a class="space_home_row" href="{$root}/article/{$id}/{$titleHandle}">
								<h3 class="cont_title cooper h3 fw-800 mb-2">
									<xsl:value-of select="$title" />
								</h3>

								<xsl:if test="$author">
									<h5 class="author_name item_header month roboto_condensed mid_small fw-400 first-letter-unset d-block mb-1">
										<xsl:value-of select="$author" />
									</h5>
								</xsl:if>

								<div class="cont_abst roboto mid_small fw-400">
									<xsl:copy-of select="$abstract" />
								</div>

								<div class="cont_link color_black text-uppercase mt-4 w-auto">
									<span class="span_link roboto_condensed mid_small fw-300 mb-2 pe-2">
										<!-- APPROFONDISCI -->
										<xsl:value-of select="/data/translate/entry[code = 'read_more']/*[local-name() = $lanextended]" />
									</span>

									<i class="icon_link fa-light fa-arrow-right-long h4 fw-200 align-middle" />
								</div>
							</a>
						</div>
					</xsl:when>
					<xsl:otherwise>
						<div class="cont_txt row order-0 order-md-1 col-12">
							<a class="cont_cat roboto_condensed smaller fw-400 text-uppercase mb-2" href="{$root}/{$pageList}/?cat={$categoryHandle}">
								<span class="fill_fx roboto_condensed smaller fw-400">
									<xsl:value-of select="$category"/>
								</span>
							</a>

							<a class="space_home_row" href="{$root}/article/{$id}/{$titleHandle}">
								<h3 class="cont_title cooper h3 fw-800 mb-2">
									<xsl:value-of select="$title" />
								</h3>

								<xsl:if test="$author">
									<h5 class="author_name item_header month roboto_condensed mid_small fw-400 first-letter-unset d-block mb-1">
										<xsl:value-of select="$author" />
									</h5>
								</xsl:if>

								<div class="cont_abst roboto mid_small fw-400">
									<xsl:copy-of select="$abstract" />
								</div>


								<div class="cont_link color_black text-uppercase mt-4 ms-auto w-auto">
									<span class="span_link roboto_condensed mid_small fw-300 mb-2 pe-2">
										<!-- APPROFONDISCI -->
										<xsl:value-of select="/data/translate/entry[code = 'read_more']/*[local-name() = $lanextended]" />
									</span>

									<i class="icon_link fa-light fa-arrow-right-long h4 fw-200 align-middle" />
								</div>
							</a>
						</div>

						<div class="cont_img position-relative order-1 order-md-0 mt-n11 opacity-75" title="{$imagetitle}" style="background-image: url('{$workspace}/uploads/{$image}');" />
					</xsl:otherwise>
				</xsl:choose>
			</div>

		<!-- </div> -->
	</xsl:template>

</xsl:stylesheet>
