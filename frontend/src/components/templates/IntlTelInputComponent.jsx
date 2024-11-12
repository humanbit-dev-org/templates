// /src/components/modules/IntlTelInputComponent.jsx
//
// `intl-tel-input` (International Telephone Input),
// https://github.com/jackocnr/intl-tel-input/tree/master
"use client"; // Enable client-side features

import { useState, useEffect } from "react"; // Import necessary modules from React
import dynamic from "next/dynamic"; // Import dynamic from Next.js for dynamic imports
import "intl-tel-input/styles"; // Import styles for the `intl-tel-input` component

// Dynamically import `intl-tel-input` with SSR disabled
const IntlTelInput = dynamic(() => import("intl-tel-input/reactWithUtils"), {
  ssr: false, // Disable Server-Side Rendering
  loading: () => <span>Loading...</span>, // Display loading message while component is being loaded
});

// Error messages for invalid phone number input
const errorMap = [
  "Invalid number",
  "Invalid country code",
  "Too short",
  "Too long",
  "Invalid number",
];

export const IntlTelInputComponent = function() {
  // State variables
  const [isValid, setIsValid] = useState(null);
  const [number, setNumber] = useState(null);
  const [errorCode, setErrorCode] = useState(null);
  const [notice, setNotice] = useState(null);
  const [initialCountry, setInitialCountry] = useState("it");

  // useEffect(() => {
  //   // Fetch data when the component mounts
  //   fetch("/api/member?memberId=123")
  //     .then(response => response.json())
  //     .then(data => {
  //       setInitialCountry(data.country);
  //     });
  // }, []); // Empty dependency array means this effect runs once on mount

  const handleSubmit = function() {
    // Handle `<form>` pulled from submission and set the notice message based on `<input>` validity
    if (isValid) {
      setNotice(`Valid number: ${number}`);
    } else {
      const errorMessage = errorMap[errorCode || 0] || "Invalid number";
      setNotice(`Error: ${errorMessage}`);
    }
  };

  return (
    <div>
      <form>
        {/* <div className="form-floating"> */}
          <IntlTelInput
            onChangeNumber={setNumber} // Update number state when phone number changes
            onChangeValidity={setIsValid} // Update isValid state when input validity changes
            onChangeErrorCode={setErrorCode} // Update errorCode state when error code changes
            initOptions={{
              initialCountry: initialCountry, // Set the initial country for the input field
              // utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@23.7.3/build/js/utils.js",
            }}
            inputProps={{
              className: "form-control", // Add custom class name to the input element
              placeholder: "Enter your phone number", // Placeholder text for the input element
            }}
          />
        {/* </div> */}

        <button className="button" type="button" onClick={handleSubmit}>
          Validate
        </button>

        {
          notice && <div className="notice">{notice}</div>
        } {/* Display notice message if exists */}
      </form>
    </div>
  );
};

// export default IntlTelInputComponent;
