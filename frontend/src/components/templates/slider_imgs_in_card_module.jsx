<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template name="slider_imgs_in_card_module">

		<xsl:param name="node"/>

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

			<div class="slider_imgs_in_card_module position-relative col-12 col-md-6 col-lg-4 link_product mb-4 pe-sm-2 pe-lg-4">
				<div class="slider_img mb-0 mb-lg-0 slider_hero_home">
					<xsl:for-each select="/data/*[local-name() = concat($node, '-', 'list-media')]/entry[*[local-name() = $node]/item/@id = $id]">

						<xsl:variable name="image" select="image/filename" />
						<xsl:variable name="imagetitle" select="*[local-name() = $namelan]" />

						<div class="container_img_product_slider slider_item bottom-0 mb-3" title="{$imagetitle}" style="background-image: url('{$workspace}/uploads/{$image}');"></div>
					</xsl:for-each>
				</div>
				<div class="hero_text col-auto text-start col-12">
					<h4 class="nomeproductTestata color_third text-start text-uppercase h6 mb-2 border_color_third pb-2 mb-2">
						<xsl:value-of select="$title"/>
					</h4>
					<xsl:if test="$abstract">
						<p class="small color_black mb-1 col-10">
							<xsl:value-of select="$abstract"/>
						</p>
					</xsl:if>
				</div>
				<div class="date_and_icon">
					<time class="date_card_event_home day small mt-0">
						<xsl:call-template name="day">
							<xsl:with-param name="date" select="$date"/>
						</xsl:call-template>
					</time><xsl:text>/</xsl:text>
					<time class="date_card_event_home month text-uppercase small mt-0">
						<xsl:call-template name="month">
							<xsl:with-param name="date" select="$date"/>
						</xsl:call-template>
					</time><xsl:text>/</xsl:text>
					<time class="date_card_event_home year small mt-0">
						<xsl:call-template name="year">
							<xsl:with-param name="date" select="$date"/>
						</xsl:call-template>
					</time>
					<a class="link_modal_event float-end" data-bs-toggle="modal" data-bs-target="#modal_event{$id}"><i class="fa-thin fa-arrows-maximize"></i></a>
				</div>
			</div>
		</xsl:for-each>

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

			<!-- modal img -->
			<div class="modal_event_news">

				<div class="modal modal_slider_image fade" id="modal_event{$id}">

					<div class="modal-dialog mt-0">

						<div class="modal-content">

							<div class="modal-body">
								<div class="modal-header mt-0 mb-2">
									<button type="button" class="btn-close float-end" data-bs-dismiss="modal" aria-label="Close">
										<i class="fa-light fa-xmark" />
									</button>
								</div>

								<div class="slider_img mb-0 mb-lg-0 slider_hero_home">
									<xsl:for-each select="/data/*[local-name() = concat($node, '-', 'list-media')]/entry[*[local-name() = $node]/item/@id = $id]">

										<xsl:variable name="image" select="image/filename" />
										<xsl:variable name="imagetitle" select="*[local-name() = $namelan]" />

										<div class="container_img_product_slider slider_item bottom-0 mb-3" title="{$imagetitle}" style="background-image: url('{$workspace}/uploads/{$image}');"></div>
									</xsl:for-each>
								</div>

								<div class="col-12 modal_title_container text-center">
									<h2 class="modal_event_title small d-inline pe-2">
										<xsl:value-of select="$title"/>
										<time class="d-inline-block small text-uppercase modal_event_time ps-3">
											<xsl:call-template name="day">
												<xsl:with-param name="date" select="$date"/>
											</xsl:call-template><xsl:text> </xsl:text>
											<xsl:call-template name="month_label">
												<xsl:with-param name="date" select="$date"/>
											</xsl:call-template><xsl:text> </xsl:text>
											<xsl:call-template name="year">
												<xsl:with-param name="date" select="$date"/>
											</xsl:call-template>
										</time>
									</h2>
								</div>
							</div>

						</div>

					</div>

				</div>

			</div>
		</xsl:for-each>
	</xsl:template>



</xsl:stylesheet>
