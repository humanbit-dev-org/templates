// `src/app/layout` -> ENTRY POINT

import { Inter, Roboto_Mono } from "next/font/google"; // Google Fonts

const inter = Inter({
    variable: "--font-inter",
    weight: ["100", "200", "300", "400", "500", "600", "700", "800", "900"],
    subsets: ["latin"],
    display: "swap",
});

const roboto_mono = Roboto_Mono({
    variable: "--font-roboto-mono",
    weight: ["100", "200", "300", "400", "500", "600", "700"],
    subsets: ["latin"],
    display: "swap",
});

// External stylesheets // TODO: Find out what this is
// import "https://cdn.jsdelivr.net/gh/lipis/flag-icons@6.6.6/css/flag-icons.min.css";

// FAVICON | METADATA | STYLESHEETS
import HeadComponent from "components/utilities/HeadComponent";

// Metadata Object (introduced in version `13.2`)
// https://nextjs.org/docs/app/api-reference/functions/generate-metadata
export const metadata = {
    ...HeadComponent(),
};

export default function RootLayout({ children }) {
    return (
        <html lang="it" className={`${inter.variable} ${roboto_mono.variable}`}>
            <body className="bg_color_white fx_load">
                {/* <div className="preloader" id="preloader">
          <div className="loader">
            <hr className="hr_preloader" />
          </div>
        </div> */}

                <div
                    className="container_humanbit_overflow scrollbar_spacing"
                    id="page"
                >
                    {/* <button id="add-webapp" class="btnBgP text-center font-weight-bold mb-4 py-1 px-3 d-lg-none" style="top: 15%; position: fixed;">Scarica l'App!</button> */}

                    {/* Navbar template */}
                    {/* <NavbarComponent /> */}

                    <div className="container_humanbit_structure container-fluid">
                        {children} {/* page structure */}
                        {/* Modals */}
                        {/* <xsl:call-template name="login_modal" />
            <xsl:call-template name="forgot_pw" />
            <xsl:call-template name="register_modal" />
            <xsl:call-template name="change_pw_modal" />
            <xsl:call-template name="positiveMessagebBefAct" />
            <xsl:call-template name="service_messages" />
            <xsl:call-template name="newsletter_b2c_modal" /> */}
                    </div>

                    {/* Footer template */}
                    {/* <FooterComponent /> */}
                </div>
            </body>
        </html>
    );
}
