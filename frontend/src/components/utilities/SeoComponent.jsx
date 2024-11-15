// import SeoComponent from "components/utilities/SeoComponent"; // to include it in another file
// export default function SeoComponent() {
//   return (
//     // <xsl:choose>
//     //   <xsl:when test="($url-lan = 'en')">
//     //     <xsl:choose>
//     //       <!-- static pages meta -->

//     //       <!-- index -->
//     //       <xsl:when test="($current-page = 'index')">
//     //         <title>The oldest library in the world | Biblioteca Capitolare di Verona</title>
//     //         <meta name="description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//     //         <meta property="og:url" content="{$current-url}/" />
//     //         <meta property="og:type" content="article" />
//     //         <meta property="og:title" content="The oldest library in the world | The Capitulary Library of Verona" />
//     //         <meta property="og:description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//     //         <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//     //       </xsl:when>

//     //       <!-- exhibitions -->
//     //       <xsl:when test="($current-page = 'exhibitions')">
//     //         <title>Exhibitions Archives | Biblioteca Capitolare di Verona</title>
//     //         <meta name="description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//     //         <meta property="og:url" content="{$current-url}/" />
//     //         <meta property="og:type" content="article" />
//     //         <meta property="og:title" content="Exhibitions Archives | The Capitulary Library of Verona" />
//     //         <meta property="og:description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//     //         <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//     //       </xsl:when>

//     //       <!-- places -->
//     //       <xsl:when test="($current-page = 'places')">
//     //         <title>The Places | Biblioteca Capitolare di Verona</title>
//     //         <meta name="description" content="The buildings that house the Capitulary Library bear the signs of Verona's millenary history" />
//     //         <meta property="og:url" content="{$current-url}/" />
//     //         <meta property="og:type" content="article" />
//     //         <meta property="og:title" content="The Places | The Capitulary Library of Verona" />
//     //         <meta property="og:description" content="The buildings that house the Capitulary Library bear the signs of Verona's millenary history" />
//     //         <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//     //       </xsl:when>

//     //       <!-- tours -->
//     //       <xsl:when test="($current-page = 'tours')">
//     //         <title>Tours | Biblioteca Capitolare di Verona</title>
//     //         <meta name="description" content="Even our most requested event cannot be missed in 2023: the Walks with the Prefect return with three new dates in the months of January and February" />
//     //         <meta property="og:url" content="{$current-url}/" />
//     //         <meta property="og:type" content="article" />
//     //         <meta property="og:title" content="Tours | The Capitulary Library of Verona" />
//     //         <meta property="og:description" content="Even our most requested event cannot be missed in 2023: the Walks with the Prefect return with three new dates in the months of January and February" />
//     //         <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//     //       </xsl:when>

//     //       <!-- ######################### -->

//     //       <!-- dynamic pages meta -->

//     //       <!-- exhibition, place, institutional -->
//     //       <xsl:when test="($current-page = 'exhibition') or ($current-page = 'place') or ($current-page = 'institutional')">
//     //         <xsl:choose>
//     //           <xsl:when test="$title != ''">
//     //             <title><xsl:value-of select="/data/*/entry/*[local-name()=$titlelan]" /> | Biblioteca Capitolare di Verona</title>
//     //             <meta name="description" content="{/data/*/entry/*[local-name()=$abslan]}" />
//     //             <meta property="og:url" content="{$current-url}/" />
//     //             <meta property="og:type" content="article" />
//     //             <meta property="og:title" content="{/data/*/entry/*[local-name()=$titlelan]}" />
//     //             <meta property="og:description" content="{/data/*/entry/*[local-name()=$abslan]}" />
//     //             <meta property="og:image" content="{$root}/image/1/1200/0{/data/*/entry/image/@path}/{/data/*/entry/image/filename}" />
//     //           </xsl:when>

//     //           <xsl:otherwise>
//     //             <title>The oldest library in the world | The Capitulary Library of Verona</title>
//     //             <meta name="description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)1969)" />
//     //             <meta property="og:url" content="{$current-url}/" />
//     //             <meta property="og:type" content="article" />
//     //             <meta property="og:title" content="The oldest library in the world | The Capitulary Library of Verona" />
//     //             <meta property="og:description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//     //             <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//     //           </xsl:otherwise>
//     //         </xsl:choose>
//     //       </xsl:when>

//     //       <xsl:otherwise>
//     //         <title>The oldest library in the world | The Capitulary Library of Verona</title>
//     //         <meta name="description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//     //         <meta property="og:url" content="{$current-url}/" />
//     //         <meta property="og:type" content="article" />
//     //         <meta property="og:title" content="The oldest library in the world | The Capitulary Library of Verona" />
//     //         <meta property="og:description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//     //         <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//     //       </xsl:otherwise>
//     //     </xsl:choose>
//     //   </xsl:when>

//     //   <xsl:otherwise>
//     //     <xsl:choose>
//     //       <!-- static pages meta -->

//     //       <!-- index -->
//     //       <xsl:when test="($current-page = 'index')">
//     //         <title>La biblioteca più antica al mondo in attività | Biblioteca Capitolare di Verona</title>
//     //         <meta name="description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
//     //         <meta property="og:url" content="{$current-url}/" />
//     //         <meta property="og:type" content="article" />
//     //         <meta property="og:title" content="La biblioteca più antica del mondo | Biblioteca Capitolare di Verona" />
//     //         <meta property="og:description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
//     //         <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//     //       </xsl:when>

//     //       <!-- exhibitions -->
//     //       <xsl:when test="($current-page = 'exhibitions')">
//     //         <title>Esposizioni Archivi | Biblioteca Capitolare di Verona</title>
//     //         <meta name="description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
//     //         <meta property="og:url" content="{$current-url}/" />
//     //         <meta property="og:type" content="article" />
//     //         <meta property="og:title" content="Esposizioni Archivi | Biblioteca Capitolare di Verona" />
//     //         <meta property="og:description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
//     //         <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//     //       </xsl:when>

//     //       <!-- places -->
//     //       <xsl:when test="($current-page = 'places')">
//     //         <title>I Luoghi | Biblioteca Capitolare di Verona</title>
//     //         <meta name="description" content="Gli edifici che ospitano la Biblioteca Capitolare portano i segni della storia millenaria di Verona" />
//     //         <meta property="og:url" content="{$current-url}/" />
//     //         <meta property="og:type" content="article" />
//     //         <meta property="og:title" content="I Luoghi | Biblioteca Capitolare di Verona" />
//     //         <meta property="og:description" content="Gli edifici che ospitano la Biblioteca Capitolare portano i segni della storia millenaria di Verona" />
//     //         <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//     //       </xsl:when>

//     //       <!-- tours -->
//     //       <xsl:when test="($current-page = 'tours')">
//     //         <title>Prenota la tua visita | Biblioteca Capitolare di Verona</title>
//     //         <meta name="description" content="Anche il nostro evento più richiesto non può mancare nel 2023: le Passeggiate con il Prefetto ritornano con tre nuove date nei mesi di gennaio e febbraio" />
//     //         <meta property="og:url" content="{$current-url}/" />
//     //         <meta property="og:type" content="article" />
//     //         <meta property="og:title" content="Passeggiate | Biblioteca Capitolare di Verona" />
//     //         <meta property="og:description" content="Anche il nostro evento più richiesto non può mancare nel 2023: le Passeggiate con il Prefetto ritornano con tre nuove date nei mesi di gennaio e febbraio" />
//     //         <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//     //       </xsl:when>
//     //       <xsl:when test="($current-page = 'event')">
//     //         <title>Organizza il tuo evento | Biblioteca Capitolare di Verona</title>
//     //         <meta name="description" content="Organizza il tuo evento privato negli splendidi spazi delle Fondazione" />
//     //         <meta property="og:url" content="{$current-url}/" />
//     //         <meta property="og:type" content="article" />
//     //         <meta property="og:title" content="Organizza il tuo evento | Biblioteca Capitolare di Verona" />
//     //         <meta property="og:description" content="Organizza il tuo evento privato negli splendidi spazi delle Fondazione" />
//     //         <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//     //       </xsl:when>

//     //       <!-- ######################### -->

//     //       <!-- dynamic pages meta -->

//     //       <!-- exhibition, place, institutional -->
//     //       <xsl:when test="($current-page = 'exhibition') or ($current-page = 'place') or ($current-page = 'institutional')">
//     //         <xsl:choose>
//     //           <xsl:when test="$title != ''">
//     //             <title><xsl:value-of select="/data/*/entry/*[local-name()=$titlelan]" /> | Biblioteca Capitolare di Verona</title>
//     //             <meta name="description" content="{/data/*/entry/*[local-name()=$abslan]}" />
//     //             <meta property="og:url" content="{$current-url}/" />
//     //             <meta property="og:type" content="article" />
//     //             <meta property="og:title" content="{/data/*/entry/*[local-name()=$titlelan]}" />
//     //             <meta property="og:description" content="{/data/*/entry/*[local-name()=$abslan]}" />
//     //             <meta property="og:image" content="{$root}/image/1/1200/0{/data/*/entry/image/@path}/{/data/*/entry/image/filename}" />
//     //           </xsl:when>

//     //           <xsl:otherwise>
//     //             <title>La biblioteca più antica del mondo | Biblioteca Capitolare di Verona</title>
//     //             <meta name="description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
//     //             <meta property="og:url" content="{$current-url}/" />
//     //             <meta property="og:type" content="article" />
//     //             <meta property="og:title" content="La biblioteca più antica del mondo | Biblioteca Capitolare di Verona" />
//     //             <meta property="og:description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
//     //             <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//     //           </xsl:otherwise>
//     //         </xsl:choose>
//     //       </xsl:when>

//     //       <xsl:otherwise>
//     //         <title>La biblioteca più antica al mondo in attività | Biblioteca Capitolare di Verona</title>
//     //         <meta name="description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
//     //         <meta property="og:url" content="{$current-url}/" />
//     //         <meta property="og:type" content="article" />
//     //         <meta property="og:title" content="La biblioteca più antica al mondo in attività | Biblioteca Capitolare di Verona" />
//     //         <meta property="og:description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
//     //         <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//     //       </xsl:otherwise>
//     //     </xsl:choose>
//     //   </xsl:otherwise>
//     // </xsl:choose>
//   );
// };





// import SeoComponent from "components/utilities/SeoComponent"; // to include it in another file
// export default function SeoComponent({ currentPage, urlLan, currentUrl, title, description, imagePath, imageFilename }) {
//   let metadata = {};

//   return (
//     if (urlLan === 'it') {
//       switch (currentPage) {
//         // STATIC PAGES

//         // INDEX
//         case "index":
//           metadata = {
//             title: "La biblioteca più antica al mondo in attività | Biblioteca Capitolare di Verona",
//             description: "La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)",
//             og: {
//               url: currentUrl,
//               type: "article",
//               title: "La biblioteca più antica al mondo in attività | Biblioteca Capitolare di Verona",
//               description: "La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)",
//               image: "/images/logos/230306_LaCapitolare_Logo_step2.svg",
//             },
//           };
//           break;

//         // COMPONENTS
//         case "components":
//           metadata = {
//             title: "Esposizioni Archivi | Biblioteca Capitolare di Verona",
//             description: "La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)",
//             og: {
//               url: currentUrl,
//               type: "article",
//               title: "Esposizioni Archivi | Biblioteca Capitolare di Verona",
//               description: "La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)",
//               image: "/static/images/logos/230306_LaCapitolare_Logo_step2.svg",
//             },
//           };
//           break;

//           // <!-- ######################### -->

//           // <!-- dynamic pages meta -->

//           // <!-- exhibition, place, institutional -->
//           // <xsl:when test="($current-page = 'exhibition') or ($current-page = 'place') or ($current-page = 'institutional')">
//           //   <xsl:choose>
//           //     <xsl:when test="$title != ''">
//           //       <title><xsl:value-of select="/data/*/entry/*[local-name()=$titlelan]" /> | Biblioteca Capitolare di Verona</title>
//           //       <meta name="description" content="{/data/*/entry/*[local-name()=$abslan]}" />
//           //       <meta property="og:url" content="{$current-url}/" />
//           //       <meta property="og:type" content="article" />
//           //       <meta property="og:title" content="{/data/*/entry/*[local-name()=$titlelan]}" />
//           //       <meta property="og:description" content="{/data/*/entry/*[local-name()=$abslan]}" />
//           //       <meta property="og:image" content="{$root}/image/1/1200/0{/data/*/entry/image/@path}/{/data/*/entry/image/filename}" />
//           //     </xsl:when>

//           //     <xsl:otherwise>
//           //       <title>La biblioteca più antica del mondo | Biblioteca Capitolare di Verona</title>
//           //       <meta name="description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
//           //       <meta property="og:url" content="{$current-url}/" />
//           //       <meta property="og:type" content="article" />
//           //       <meta property="og:title" content="La biblioteca più antica del mondo | Biblioteca Capitolare di Verona" />
//           //       <meta property="og:description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
//           //       <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//           //     </xsl:otherwise>
//           //   </xsl:choose>
//           // </xsl:when>

//           // <xsl:otherwise>
//           //   <title>La biblioteca più antica al mondo in attività | Biblioteca Capitolare di Verona</title>
//           //   <meta name="description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
//           //   <meta property="og:url" content="{$current-url}/" />
//           //   <meta property="og:type" content="article" />
//           //   <meta property="og:title" content="La biblioteca più antica al mondo in attività | Biblioteca Capitolare di Verona" />
//           //   <meta property="og:description" content="La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)" />
//           //   <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//           // </xsl:otherwise>
//       }
//       break;
//     } else {
//       // <xsl:otherwise>
//       //   <xsl:choose>
//       //     <!-- static pages meta -->

//       //     <!-- index -->
//       //     <xsl:when test="($current-page = 'index')">
//       //       <title>The oldest library in the world | Biblioteca Capitolare di Verona</title>
//       //       <meta name="description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//       //       <meta property="og:url" content="{$current-url}/" />
//       //       <meta property="og:type" content="article" />
//       //       <meta property="og:title" content="The oldest library in the world | The Capitulary Library of Verona" />
//       //       <meta property="og:description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//       //       <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//       //     </xsl:when>

//       //     <!-- exhibitions -->
//       //     <xsl:when test="($current-page = 'exhibitions')">
//       //       <title>Exhibitions Archives | Biblioteca Capitolare di Verona</title>
//       //       <meta name="description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//       //       <meta property="og:url" content="{$current-url}/" />
//       //       <meta property="og:type" content="article" />
//       //       <meta property="og:title" content="Exhibitions Archives | The Capitulary Library of Verona" />
//       //       <meta property="og:description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//       //       <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//       //     </xsl:when>

//       //     <!-- ######################### -->

//       //     <!-- dynamic pages meta -->

//       //     <!-- exhibition, place, institutional -->
//       //     <xsl:when test="($current-page = 'exhibition') or ($current-page = 'place') or ($current-page = 'institutional')">
//       //       <xsl:choose>
//       //         <xsl:when test="$title != ''">
//       //           <title><xsl:value-of select="/data/*/entry/*[local-name()=$titlelan]" /> | Biblioteca Capitolare di Verona</title>
//       //           <meta name="description" content="{/data/*/entry/*[local-name()=$abslan]}" />
//       //           <meta property="og:url" content="{$current-url}/" />
//       //           <meta property="og:type" content="article" />
//       //           <meta property="og:title" content="{/data/*/entry/*[local-name()=$titlelan]}" />
//       //           <meta property="og:description" content="{/data/*/entry/*[local-name()=$abslan]}" />
//       //           <meta property="og:image" content="{$root}/image/1/1200/0{/data/*/entry/image/@path}/{/data/*/entry/image/filename}" />
//       //         </xsl:when>

//       //         <xsl:otherwise>
//       //           <title>The oldest library in the world | The Capitulary Library of Verona</title>
//       //           <meta name="description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)1969)" />
//       //           <meta property="og:url" content="{$current-url}/" />
//       //           <meta property="og:type" content="article" />
//       //           <meta property="og:title" content="The oldest library in the world | The Capitulary Library of Verona" />
//       //           <meta property="og:description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//       //           <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//       //         </xsl:otherwise>
//       //       </xsl:choose>
//       //     </xsl:when>

//       //     <xsl:otherwise>
//       //       <title>The oldest library in the world | The Capitulary Library of Verona</title>
//       //       <meta name="description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//       //       <meta property="og:url" content="{$current-url}/" />
//       //       <meta property="og:type" content="article" />
//       //       <meta property="og:title" content="The oldest library in the world | The Capitulary Library of Verona" />
//       //       <meta property="og:description" content="The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)" />
//       //       <meta property="og:image" content="{$workspace}/static/images/logos/230306_LaCapitolare_Logo_step2.svg" />
//       //     </xsl:otherwise>
//       //   </xsl:choose>
//       // </xsl:otherwise>
//     }
//   );
// };


// components/utilities/SeoComponent.js

export default function SeoComponent() {
  const currentPage = 'index'; // You can dynamically set this based on your application's logic
  const urlLan = 'it'; // You can dynamically set this based on your application's logic
  const currentUrl = 'https://yourwebsite.com';
  const title = 'Your dynamic title'; // Set this dynamically if needed
  const description = 'Your dynamic description'; // Set this dynamically if needed
  const imagePath = '/static/images/path/to/your/images';
  const imageFilename = 'your-image-file.jpg';

  // const urlLan = typeof window !== "undefined" ? document.documentElement.lang : "en";

  function generateMetadata() {
    let metadata = {};

    if (urlLan === 'en') {
      switch (currentPage) {
        case 'index':
          metadata = {
            title: 'The oldest library in the world | Biblioteca Capitolare di Verona',
            description: 'The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)',
            og: {
              url: `${currentUrl}/`,
              type: 'article',
              title: 'The oldest library in the world | The Capitulary Library of Verona',
              description: 'The Queen of the Ecclesiastical collections, so defined for the preciousness of her manuscripts, by the paleographer Elias Avery Lowe (1879-1969)',
              image: '/static/images/logos/230306_LaCapitolare_Logo_step2.svg',
            },
          };
          break;
        case 'article':
          metadata = {
            title: `${title} | Biblioteca Capitolare di Verona`,
            description: description,
            og: {
              url: `${currentUrl}/`,
              type: 'article',
              title: `${title} | Biblioteca Capitolare di Verona`,
              description: description,
              image: `${imagePath}/${imageFilename}`,
            },
          };
          break;
        default:
          metadata = {
            title: 'Default Title | Biblioteca Capitolare di Verona',
            description: 'Default description for the page.',
            og: {
              url: `${currentUrl}/`,
              type: 'article',
              title: 'Default Title | Biblioteca Capitolare di Verona',
              description: 'Default description for the page.',
              image: '/static/images/logos/230306_LaCapitolare_Logo_step2.svg',
            },
          };
      }
    // } else if (urlLan === 'it') {
    } else {
      switch (currentPage) {
        case 'index':
          metadata = {
            title: 'La biblioteca più antica al mondo in attività | Biblioteca Capitolare di Verona',
            description: 'La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)',
            og: {
              url: `${currentUrl}/`,
              type: 'article',
              title: 'La biblioteca più antica al mondo in attività | Biblioteca Capitolare di Verona',
              description: 'La Regina delle collezioni Ecclesiastiche, così definita per la preziosità dei suoi manoscritti, dal paleografo Elias Avery Lowe (1879-1969)',
              image: '/static/images/logos/230306_LaCapitolare_Logo_step2.svg',
            },
          };
          break;
        case 'article':
          metadata = {
            title: `${title} | Biblioteca Capitolare di Verona`,
            description: description,
            og: {
              url: `${currentUrl}/`,
              type: 'article',
              title: `${title} | Biblioteca Capitolare di Verona`,
              description: description,
              image: `${imagePath}/${imageFilename}`,
            },
          };
          break;
        default:
          metadata = {
            title: 'Default Title | Biblioteca Capitolare di Verona',
            description: 'Default description for the page.',
            og: {
              url: `${currentUrl}/`,
              type: 'article',
              title: 'Default Title | Biblioteca Capitolare di Verona',
              description: 'Default description for the page.',
              image: '/static/images/logos/230306_LaCapitolare_Logo_step2.svg',
            },
          };
      }
    }

    return metadata;
  }

  return generateMetadata();
};

// export default SeoComponent;
