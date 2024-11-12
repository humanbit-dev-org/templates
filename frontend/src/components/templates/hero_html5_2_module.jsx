<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template name="hero_html5_2_module">

		<xsl:param name="title"/>
		<xsl:param name="subtitle"/>
		<xsl:param name="logo"/>
		<xsl:param name="image"/>
		<xsl:param name="mp4"/>
		<xsl:param name="webm"/>
		<xsl:param name="ogv"/>
		<xsl:param name="gotoelem"/>

		<div class="hero_html5_2_module">
			<div class="hero_rmg d-flex align-items-center justify-content-start">
				<div class="hero_rmg_div w-100">
					<video id="bg-video" class="video-js vjs-default-skin"
						 autoplay="autoplay" muted="muted" loop="loop" playsinline="playsinline" style="position:absolute;top:0;left:0;overflow:hidden;z-index:-998;">
						<source src='{$workspace}/uploads/{$mp4}' type='video/mp4'/>
						<source src='{$workspace}/uploads/{$webm}' type='video/webm'/>
						<source src="{$workspace}/uploads/{$ogv}" type="video/ogg"/>
						<p class='vjs-no-js'>
						  To view this video please enable JavaScript, and consider upgrading to a web browser that
						  <a href='https://videojs.com/html5-video-support/' target='_blank'>supports HTML5 video</a>
						</p>
					</video>
					<div class="box_testo_hero container_humanbit_1 col-12 d-flex align-items-end pb-10 pb-xl-12">
						<div class="container_text_hero text-center text-lg-start container_max_width_2 row col-12">
							<figure class="container_illu_home col-8 col-lg-3 pe-lg-4 mx-auto mx-lg-0 mb-md-5 mb-lg-0">
								<img class="w-100" src="{$workspace}/uploads/{$logo}"/>
							</figure>

							<div class="container_hero_right col-12 col-lg-9">
								<div class="h1_container row col-12 align-items-end justify-content-between border_color_white pb-3">
									<h1 class="h1_home col-12 col-lg-7 pt-3 pt-md-0 text-uppercase position mb-2 mb-lg-0"><xsl:value-of select="$title"/></h1>
									<div class="subscribe_home color_first bg_color_white border_color_white d-inline-block mx-auto py-2 px-3" style="">
										<a href="#" class="p-0 pe-2 color_first never_text d-inline" style="">
											<xsl:if test="$gotoelem">
												<xsl:attribute name="href">#<xsl:value-of select="$gotoelem"/></xsl:attribute>
											</xsl:if>
											<span class="h6 italic">Call to action!</span>
										</a>
										<i class="courses_icon btn_white color_first fa-light fa-arrow-right-long" style=""></i>
									</div>

									<xsl:if test="$gotoelem">
									<a class="subscribe_text subscription_circle d-flex flex-wrap justify-content-center align-items-center mt-4" href="{$gotoelem}">
										<div class="subscription_inner_circle color_white text-center">
											<i class="fa-light fa-citrus-slice" />
											<p class="subscription_txt h6 fw_700"><xsl:value-of select="/data/translate/entry[code='subscribe']/*[local-name() = $lanextended]" />Subscribe</p>
										</div>
									</a>
									</xsl:if>
								</div>
								<xsl:if test="$subtitle">
									<h2 class="h2_home lh-1 mb-4 text-uppercase pt-3">
										<xsl:value-of select="$subtitle"/>
									</h2>
								</xsl:if>
								<xsl:if test="$gotoelem">
									<a class="scroll-to" href="{$gotoelem}">
										<xsl:value-of select="/data/translate/entry[code = 'whoam']/*[local-name() = $lan]"/>
										<i class="fa fa-chevron-down ml-4" aria-hidden="true"></i>
									</a>
								</xsl:if>
							</div>

						</div>
					</div>
				</div>
			</div>
		</div>
	</xsl:template>





</xsl:stylesheet>
