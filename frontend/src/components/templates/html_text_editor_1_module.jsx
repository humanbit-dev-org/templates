<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template name="html_text_editor_1_module">

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

			<div class="html_text_editor_1_module">
				<form class="text_editor_container col-12 col-lg-8 mx-auto py-8">
					<div class="d-block col tools mb-3">
						<!-- <button type="button" data-cmd="justifyLeft">
							<i class="fa-light fa-align-left" aria-hidden="true"></i>
						</button>
						<button type="button" data-cmd="justifyCenter">
							<i class="fa fa-align-Center" aria-hidden="true"></i>
						</button>
						<button type="button" data-cmd="justifyFull">
							<i class="fa-light fa-align-justify" aria-hidden="true"></i>
						</button>
						<button type="button" data-cmd="justifyRight">
							<i class="fa-light fa-align-right" aria-hidden="true"></i>
						</button> -->
						<button type="button" data-cmd="bold" name="active">
							<i class="fa-light fa-bold" aria-hidden="true"></i>
						</button>
						<button type="button" data-cmd="italic" name="active">
							<i class="fa-light fa-italic" aria-hidden="true" name="active"></i>
						</button>
						<button type="button" data-cmd="underline" name="active">
							<i class="fa-light fa-underline" aria-hidden="true" name="active"></i>
						</button>
						<button type="button" data-cmd="createLink" name="">
							<i class="fa-light fa-link" aria-hidden="true"></i>
						</button>

						<!-- comment if no image option wanted -->
						<!-- <button type="button" data-cmd="insertImage">
							<i class="fa fa-file-image" aria-hidden="true"></i>
						</button> -->
					</div>


					<!-- <iframe class="text_area" id="output" name="textField"></iframe> -->
					<textarea class="text_area" id="output" name="textField"></textarea>



				</form>
			</div>

		<!-- </div> -->
	</xsl:template>


	<xsl:template name="html_text_editor_2_module">

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

			<div class="html_text_editor_2_module">
				<section class="text_editor_container col-12 col-lg-8 mx-auto">
					<div class="row tools_container">
						<div class="col tools">
							<!-- <div class="first box">
								<input id="font-size" type="number" value="16" min="1" max="100" onchange="f1(this)"/>
							</div> -->

							<button type="button" onclick="f2(this)">
								<i class="fa-solid fa-bold"></i>
							</button>
							<button type="button" onclick="f3(this)">
								<i class="fa-solid fa-italic"></i>
							</button>
							<button type="button" onclick="f4(this)">
								<i class="fa-solid fa-underline"></i>
							</button>

							<!-- <div class="third box">
								<button type="button" onclick="f5(this)">
									<i class="fa-solid fa-align-left"></i>
								</button>
								<button type="button" onclick="f6(this)">
									<i class="fa-solid fa-align-center"></i>
								</button>
								<button type="button" onclick="f7(this)">
									<i class="fa-solid fa-align-right"></i>
								</button>
							</div> -->

							<div class="fourth box">
								<button type="button" data-cmd="createLink" onclick="f10()">
									<i class="fa-solid fa-link"></i>
								</button>
							</div>

							<!-- <div class="fifth box">
								<button type="button" onclick="f8(this)">aA</button>
								<button type="button" onclick="f9()">
									<i class="fa-solid fa-text-slash"></i>
								</button>
								<input type="color" onchange="f10(this)"/>
							</div> -->
						</div>
					</div>
					<br/>
					<div class="row">
						<div class="col">
							<textarea id="


								" class="textarea" placeholder="Your text here"></textarea>
						</div>
					</div>
				</section>
			</div>

		<!-- </div> -->
	</xsl:template>


	<xsl:template name="html_text_editor_3_module">

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

			<div class="html_text_editor_3_module">
				<section class="text_editor_container col-12 col-lg-8 mx-auto">
					<form>
						<div class="editor">
					  		<div class="toolbar">
								<button class="bold"><i class="fas fa-bold"></i></button>
								<button class="italic"><i class="fas fa-italic"></i></button>
								<button class="underline"><i class="fas fa-underline"></i></button>
								<button class="link"><i class="fas fa-link"></i></button>
							</div>
							<textarea class="text"></textarea>
						</div>
						<input type="submit" value="Submit"/>
					</form>
				</section>
			</div>

		<!-- </div> -->
	</xsl:template>


	<xsl:template name="html_text_editor_4_module">

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

			<div class="html_text_editor_3_module">
				<section id="#trumbowyg-demo" class="text_editor_container col-12 col-lg-8 mx-auto">

				</section>
			</div>

		<!-- </div> -->
	</xsl:template>

</xsl:stylesheet>
