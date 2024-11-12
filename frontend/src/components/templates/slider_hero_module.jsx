<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template name="slider_hero_module">

		<div class="slider_hero_module">
			<div class="slider_img position-relative mb-0 mb-lg-0">
				<!-- define on the html data-slide attributes of div class=slider-for-home how many are visible in the slider, for each of three media (sm, md, lg) -->
				<div class="wrapper-arrows position-relative slider-for-home" data-slide-sm="1" data-slide-md="1" data-slide-lg="1">
					<div class="slider bottom-0 pb-5">
						<xsl:for-each select="/data/hero-html5/entry">
							<a class="link_product pe-sm-2 pe-lg-3 pb-4 pb-lg-0" href="{link}">
								<div class="container_product pb-0">
									<div class="link_product_container">
										<div class="container_img_product_slider mb-4" title="{*[local-name() = $titlelan]}" style="background-image: url('{$workspace}/uploads/{image/filename}');">
											<div class="info_sport border_color_white px-3 py-2">
												<span title="{nome}">INFO</span>
											</div>
										</div>
									</div>
									<div class="hero_text col-auto text-start">
										<h4 class="nomeproductTestata color_third text-start text-uppercase h6 mb-2 border_color_third pb-2 mb-2"><xsl:value-of select="*[local-name()= $titlelan]"/></h4>
										<div class="caratteristiche col-12">
											<p class="small color_black mb-1"><xsl:value-of select="*[local-name()= $abslan]"/></p>
										</div>
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
