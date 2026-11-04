<?php

return [
	/*
    |--------------------------------------------------------------------------
    | SAML IdP — trust anchor
    |--------------------------------------------------------------------------
    | Certificato PUBBLICO dell'IdP (Banco Desio) con cui si verifica la firma
    | della SAMLResponse su POST /api/auth/saml. È un trust anchor PINNATO: pubblico
    | (non un segreto), quindi vive in config e non su Secrets Manager. Non va MAI
    | letto dall'<X509Certificate> del documento — quello e' controllato dall'attaccante.
    | Overridabile per ambiente con SAML_IDP_CERTIFICATE (PEM o base64).
    */
	"idp_certificate" => env(
		"SAML_IDP_CERTIFICATE",
		<<<'PEM'
-----BEGIN CERTIFICATE-----
MIIFqDCCA5CgAwIBAgIJAI0QzRUi6L7fMA0GCSqGSIb3DQEBCwUAMGkxFjAUBgNV
BAMMDWJhbmNvZGVzaW8uaXQxDDAKBgNVBAsMA1NTTzEkMCIGA1UECgwbQmFuY28g
RGVzaW8gZSBkZWxsYSBCcmlhbnphMQ4wDAYDVQQHDAVEZXNpbzELMAkGA1UEBhMC
SVQwHhcNMjQwMjAyMTIwNjIyWhcNMzQwMTMwMTIwNjIyWjBpMRYwFAYDVQQDDA1i
YW5jb2Rlc2lvLml0MQwwCgYDVQQLDANTU08xJDAiBgNVBAoMG0JhbmNvIERlc2lv
IGUgZGVsbGEgQnJpYW56YTEOMAwGA1UEBwwFRGVzaW8xCzAJBgNVBAYTAklUMIIC
IjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA2LeoRI2u9L32v8CZhlvEMO2S
zEazMjFaSIHrRJjz8zCFvYcbnuN2Sb87QYt4YmVGSvUjtaTqR1F/ZeUslbq1c0Ad
lS3hGGKH5fuhTbxtInlRbqIjih352DJHsBF7vLnCQHO8POiL2aPGk+tOk2gJt8u5
pRPuxeFnyKYmYP64iIOICRmLi81J097EjtnTWweiO/REhDi4z6HSBF1GouHAZdVA
ZW6GTdQF4sThA9dbC2PD+s1NvGrpWrmcQVHD20a4VTfNzwPUlOWYERL4oL+hzeEE
nF3Y7vD5fPHx8kj1TTn0R/ROGUxeqAFVu+LlkQAwonlP5kIsLx94OfaYj/lzr6xT
P6EsnJMneIz1vJtw/Utk7OFCDmW26yJ5jz30n4zloeOoRSNQ68M8xdkG1pamT4gb
yzbGQTWxU8SKapxr2Y5/+CMOVv+5kvo+Y5HUumM5bywEDKBArNVcbIWp3DCng4Y+
mlP736pHQ1PZsutk/VpavHi2V++J4hIC4gdOaK0Jb75GmbKReYKpB4dxBt2GRshV
6+iZWt1QMiV49pMjS33LJBxyqL3InW5ZyCzNRWjkz8llZBVuiY0XiCdZFYyAHeFk
OIyWVwazSBBcI7ckc47V/LuRCp+vFYCjvwShocVLNgVJu6AJxqHjm58oM0TIKMOu
JaJvuIiTyenwsz3euV0CAwEAAaNTMFEwHQYDVR0OBBYEFLTZXv6dv69yIsb6z2oZ
uxKNSFXLMB8GA1UdIwQYMBaAFLTZXv6dv69yIsb6z2oZuxKNSFXLMA8GA1UdEwEB
/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggIBAGNITvmu3cmBfcp7KTfTrCX7MOWL
LoFklvrtzPxrxnsVtzEeHvKQvOwG5kgryKi4lnDn/QmgGN71uC9tZlP23kFTjqvR
3IyEkHLnL7fpOthJ6b69XPbw9/9z28bl7G2IWk9U+TgLG509svpXg0+iK5CM/4CT
+8Sv1QUxBBeoJHs1oI8CSaAmbV7GJm8r0pNwCnUNmkGeLgBC6cDXzo2rddTGczFz
SgEA2DypGrSWOXTjuM7Tdz6ioHxmHSfCd+usHPK0vBPh80v9CM1EoHVNNxOMESJV
b3dzsbgwYlGVFh3T1rObW96EwB+cTgd96ZCsGjs5NTaFLPGjgrb1IV+0D2ODghqD
wNQx4mtFfuBZG6g4do1mSO8YYa9Nh1avKBj8zp3azdvXcTJVO+bu4KFYSsJ+3dSa
5GEJNtb+SHRUfLpRSmbpesLLtds/LHd54eASh6/8nA8kR6g8fsQg23N6OBYDVB5G
OXIzzxXjQGnJ5gBzHtSxDoJH+MEOGhbQ73ezOio4cZEG0Zp8z6C6HzUfRjy2i/BP
V7c/9PWwcuOUiimvfKK6PzXqM3OhlTx7ovSCR+3m7OmnmGU0rOqID13tZhoC6Tka
abneOliAgN5w3XPY+8ySu394ajiPva2KNYTnxizVW6X+4yiJo5ePs6CBeCaaurXT
3/L0Umz4FWwo4WUd
-----END CERTIFICATE-----
PEM
	),
];
