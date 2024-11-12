// import HeadComponent from "components/utilities/HeadComponent"; // to include it in another file
//
// "use client";

import FaviconComponent from "components/utilities/FaviconComponent";
import SeoComponent from "components/utilities/SeoComponent";
export * as StylesheetsImporter from "components/utilities/StylesheetsImporter";

export default function HeadComponent() {
  return {
    viewport: "height=device-height, width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no, target-densitydpi=device-dpi, shrink-to-fit=no",
    ...FaviconComponent(),
    ...SeoComponent(),
  };
};
