<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SAML IdP — trust anchor
    |--------------------------------------------------------------------------
    | PUBLIC certificate of the IdP, used to verify the signature of every
    | SAMLResponse (SamlSignatureVerifier). It is a PINNED trust anchor —
    | public, not a secret — and must NEVER be read from the document's own
    | <X509Certificate>, which the sender controls.
    | Set per environment with SAML_IDP_CERTIFICATE (PEM or bare base64).
    | While empty, signature verification fails closed: no real SAML login.
    */
    'idp_certificate' => env('SAML_IDP_CERTIFICATE', ''),

    /*
    | Entity ids used by the Auth Simulation page to build its SAMLResponse:
    | the issuer of the fake IdP and the audience (this service provider).
    */
    'idp_entity_id' => env('SAML_IDP_ENTITY_ID', 'https://idp.example.test/saml'),

    'sp_entity_id' => env('SAML_SP_ENTITY_ID', env('APP_URL', 'http://localhost')),

    /*
    | Name of the SAML attribute carrying the username (users primary key) of
    | the user to log in.
    */
    'username_attribute' => env('SAML_USERNAME_ATTRIBUTE', 'username'),
];
