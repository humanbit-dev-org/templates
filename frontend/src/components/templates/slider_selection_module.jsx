<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template name="slider_selection_module_image_1">

		<xsl:param name="node"/>

		<div class="slider_selection_module">
			<div class="slider_img position-relative mb-0 mb-lg-0">
				<!-- define on the html data-slide attributes of div class=slider-for-home how many are visible in the slider, for each of three media (sm, md, lg) -->
				<div class="wrapper-arrows position-relative slider-for-home" data-slide-sm="1" data-slide-md="2" data-slide-lg="3">
					<div class="slider slider-nav bottom-0 pb-5">
						<xsl:for-each select="/data/*[local-name() = concat($node, '-', 'list')]/entry">

							<xsl:variable name="id" select="@id"/>
							<xsl:variable name="title" select="*[local-name() = $titlelan]" />
							<xsl:variable name="titleHandle" select="title-italian/@handle" />
							<xsl:variable name="subtitle" select="*[local-name() = $subtitlelan]" />
							<xsl:variable name="abstract" select="*[local-name() = $abslan]" />
							<xsl:variable name="body" select="*[local-name() = $bodylan]" />
							<xsl:variable name="date" select="date" />
							<xsl:variable name="page" select="$node" />
							<xsl:variable name="category" select="category/item" />
							<xsl:variable name="image" select="/data/*[local-name() = concat($node, '-', 'list-media')]/entry[*[local-name() = $node]/item/@id = $id]/image/filename" />
							<xsl:variable name="imagetitle" select="/data/*[local-name() = concat($node, '-', 'list-media')]/entry[*[local-name() = $node]/item/@id = $id]/*[local-name() = $namelan]" />

							<a class="link_product pe-sm-2 pe-lg-3 pb-4 pb-lg-0" href="{$root}/{$page}/{$id}">
								<xsl:if test="$titleHandle">
									<xsl:attribute name="href"><xsl:value-of select="$root"/>/<xsl:value-of select="$page"/>/<xsl:value-of select="$id"/>/<xsl:value-of select="$titleHandle"/></xsl:attribute>
								</xsl:if>
								<div class="container_product pb-0">
									<div class="link_product_container">
										<div class="container_img_product_slider mb-4" title="{$imagetitle}" style="background-image: url('{$workspace}/uploads/{$image}');">

											<div class="info_sport border_color_white px-3 py-2">
												<span title="{nome}">INFO</span>
											</div>
										</div>
									</div>
									<div class="hero_text col-auto text-start">
										<h4 class="nomeproductTestata color_third text-start text-uppercase h6 mb-2 border_color_third pb-2 mb-2">
											<xsl:value-of select="$title"/>
										</h4>
										<xsl:if test="$abstract">
											<div class="caratteristiche col-12">
												<p class="small color_black mb-1">
													<xsl:copy-of select="*[local-name()= $abslan]"/>
												</p>
											</div>
										</xsl:if>
										
									</div>
								</div>
							</a>
						</xsl:for-each>
					</div>
					<i class="slickdsi-prev fa-solid fa-chevron-left align-middle"></i>
					<i class="slickdsi-next fa-solid fa-chevron-right align-middle"></i>
				</div>

			</div>
		</div>
	</xsl:template>

	<!-- slide selection module image 2 -->

	<xsl:template name="slider_selection_module_image_2">

		<xsl:param name="node"/>

		<div class="slider_selection_module">
			<div class="slider_img_2 position-relative mb-6 viewport">
				<!-- define on the html data-slide attributes of div class=slider-for-home how many are visible in the slider, for each of three media (sm, md, lg) -->
				<div class="wrapper-arrows position-relative slider-for-home" data-slide-sm="1" data-slide-md="2" data-slide-lg="3">
					<div class="slider slider-nav bottom-0 pb-5">
						<xsl:for-each select="/data/*[local-name() = concat($node, '-', 'list')]/entry">

							<xsl:variable name="id" select="@id"/>
							<xsl:variable name="title" select="*[local-name() = $titlelan]" />
							<xsl:variable name="titleHandle" select="title-italian/@handle" />
							<xsl:variable name="subtitle" select="*[local-name() = $subtitlelan]" />
							<xsl:variable name="abstract" select="*[local-name() = $abslan]" />
							<xsl:variable name="body" select="*[local-name() = $bodylan]" />
							<xsl:variable name="date" select="date" />
							<xsl:variable name="page" select="$node" />
							<xsl:variable name="category" select="category/item" />
							<xsl:variable name="image" select="/data/*[local-name() = concat($node, '-', 'list-media')]/entry[*[local-name() = $node]/item/@id = $id]/image/filename" />
							<xsl:variable name="imagetitle" select="/data/*[local-name() = concat($node, '-', 'list-media')]/entry[*[local-name() = $node]/item/@id = $id]/*[local-name() = $namelan]" />

							<a class="link_product pe-sm-2 pe-lg-3" href="{$root}/{$page}/{$id}">
								<xsl:if test="$titleHandle">
									<xsl:attribute name="href"><xsl:value-of select="$root"/>/<xsl:value-of select="$page"/>/<xsl:value-of select="$id"/>/<xsl:value-of select="$titleHandle"/></xsl:attribute>
								</xsl:if>
								<div class="container_product pb-3">
									<div class="link_product_container">
										<div class="container_img_product_slider" title="{$imagetitle}" style="background-image: url('{$workspace}/uploads/{$image}');"></div>
									</div>
									<div class="hero_text col-auto text-start ps-3 pe-3">
										<h4 class="nomeproductTestata text-start h6 mb-2 color_white">
											<xsl:value-of select="$title"/>
										</h4>
										<xsl:if test="$abstract">
											<p class="p_small mt-0">
												<xsl:copy-of select="$abstract"/>
											</p>
										</xsl:if>
									</div>
								</div>
							</a>
						</xsl:for-each>
					</div>
					<i class="slickdsi-prev fa-solid fa-chevron-left align-middle"></i>
					<i class="slickdsi-next fa-solid fa-chevron-right align-middle"></i>
				</div>

			</div>
		</div>
	</xsl:template>


	<!-- Slider selection module no image -->

	<xsl:template name="slider_selection_module_noimage">
		
		<xsl:param name="node"/>

		<div class="slider_selection_module">
			<div class="slider_no_img position-relative">
				<!-- define on the html data-slide attributes of div class=slider-for-home how many are visible in the slider, for each of three media (sm, md, lg) -->
				<div class="wrapper-arrows position-relative slider-for-home" data-slide-sm="1" data-slide-md="2" data-slide-lg="3">
					<div class="slider slider-nav bottom-0">
						<xsl:for-each select="/data/*[local-name() = concat($node, '-', 'list')]/entry">

							<xsl:variable name="id" select="@id"/>
							<xsl:variable name="title" select="*[local-name() = $titlelan]" />
							<xsl:variable name="titleHandle" select="title-italian/@handle" />
							<xsl:variable name="subtitle" select="*[local-name() = $subtitlelan]" />
							<xsl:variable name="abstract" select="*[local-name() = $abslan]" />
							<xsl:variable name="body" select="*[local-name() = $bodylan]" />
							<xsl:variable name="date" select="date" />
							<xsl:variable name="page" select="$node" />
							<xsl:variable name="category" select="category/item" />

							<a class="slider_no_img_card row py-4 me-sm-2 viewport me-lg-3" href="{$root}/{$page}/{$id}">
								<xsl:if test="$titleHandle">
									<xsl:attribute name="href"><xsl:value-of select="$root"/>/<xsl:value-of select="$page"/>/<xsl:value-of select="$id"/>/<xsl:value-of select="$titleHandle"/></xsl:attribute>
								</xsl:if>
								<div class="container_product pb-3">
									<div class="hero_text col-auto text-start px-3">
										<div class="nomeproductTestata col-12 row align-items-end justify-content-between border-color-white pb-3 mb-5">
											<h4 class="text-start col-9 h6 color_white">
												<xsl:value-of select="$title"/>
											</h4>
										</div>
										<xsl:if test="$abstract">
											<p class="small text_product mt-0 mb-4"><xsl:copy-of select="$abstract"/></p>
										</xsl:if>	
									</div>
								</div>
								<div class="info_buy_home_courses_container info-sport px-3" style="">
									<div class="buy_home_courses color_third border_color_white d-inline-block py-2 px-3" style="">
										<span class="p-0 pe-2" style="">INFO E ACQUISTA</span><i class="courses_icon btn_white fa-light fa-arrow-right-long" style=""></i>
									</div>
								</div>
							</a>
						</xsl:for-each>
					</div>
					<i class="slickdsi-prev fa-solid fa-chevron-left align-middle"></i>
					<i class="slickdsi-next fa-solid fa-chevron-right align-middle"></i>
				</div>
			</div>
		</div>
	</xsl:template>



</xsl:stylesheet>
