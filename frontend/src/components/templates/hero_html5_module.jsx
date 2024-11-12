<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	
	<xsl:template name="hero_html5_module">

		<xsl:param name="title"/>
		<xsl:param name="subtitle"/>
		<xsl:param name="image"/>
		<xsl:param name="mp4"/>
		<xsl:param name="webm"/>
		<xsl:param name="ogv"/>
		<xsl:param name="gotoelem"/>

		<div class="hero_html5_module">
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
					<div class="box_testo_hero col-12 d-flex h-100 align-items-center px-2 px-md-5">
						<div class="text_container px-2">
							<h3 class="h4_home px-2 px-md-5 pt-3 pt-md-0 text-warning" style="font-size: 1.5rem;opacity: 0.6;"><xsl:value-of select="/data/translate/entry[code = 'holiday']/*[local-name() = $lan]"/></h3>
							<h3 class="h3_home px-2 px-md-5 pt-3 pt-md-0 text-danger" style="font-weight:900"><xsl:value-of select="/data/translate/entry[code = 'holiday2']/*[local-name() = $lan]"/></h3>
							<h1 class="h1_home px-2 px-md-5 pt-3 pt-md-0">
								<xsl:value-of select="$title"/>
							</h1>
							<xsl:if test="$subtitle">
								<h2 class="h2_home px-2 px-md-5 mb-4">
									<xsl:copy-of select="$subtitle"/>
								</h2>
							</xsl:if>
							<xsl:if test="$gotoelem">
								<a class="chi_siamo px-2 px-md-5" href="{$gotoelem}">
									<xsl:value-of select="/data/translate/entry[code = 'whoam']/*[local-name() = $lan]"/>
									<i class="fa fa-chevron-down ml-4" aria-hidden="true"></i>
								</a>
							</xsl:if>
						</div>
					</div>
				</div>
			</div>
		</div>
	</xsl:template>

</xsl:stylesheet>
