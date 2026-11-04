<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class)->in('Feature', 'Unit');

/**
 * Registro dei file creati da mtlsFixtureFile().
 *
 * Con un path lo aggiunge; senza argomenti svuota il registro e restituisce
 * quello che conteneva, cosi' l'afterEach dei test puo' cancellarli.
 *
 * @return list<string>
 */
function mtlsFixtureFiles(?string $add = null): array
{
    static $paths = [];

    if ($add !== null) {
        $paths[] = $add;

        return $paths;
    }

    $drained = $paths;
    $paths = [];

    return $drained;
}

/** File temporaneo registrato per la pulizia a fine test. */
function mtlsFixtureFile(string $prefix, string $content): string
{
    $path = tempnam(sys_get_temp_dir(), $prefix);
    file_put_contents($path, $content);
    mtlsFixtureFiles($path);

    return $path;
}

/**
 * Cert e key mTLS su disco, come li vuole services.mtls_tenant.
 *
 * Materiale volutamente non-PEM (cert base64 grezzo, key inline): serve a far
 * passare la configurazione da PemMaterialNormalizer, che e' il punto del test.
 *
 * Chi lo usa deve svuotare mtlsFixtureFiles() in un afterEach: prima questi due
 * file venivano creati con tempnam e mai rimossi, e il /tmp del container si
 * riempiva un run dopo l'altro.
 */
function useMtlsTenantFixtures(): void
{
    config([
        'services.mtls_tenant.cert' => mtlsFixtureFile(
            'mtls-cert-',
            'MIIDVDCCAjygAwIBAgIUdGVzdC1jZXJ0LWJvZHktZm9yLXVuaXQtdGVzdHM',
        ),
        'services.mtls_tenant.key' => mtlsFixtureFile(
            'mtls-key-',
            '-----BEGIN PRIVATE KEY----- MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC7VJTUt9Us8cKB -----END PRIVATE KEY-----',
        ),
    ]);
}

/** Test RSA keypair + self-signed certificate (acts as test IdP in SAML tests). */
function makeTestIdpCert(): array
{
    $pkey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($pkey, $privatePem);
    $csr = openssl_csr_new(['commonName' => 'test-idp'], $pkey, ['digest_alg' => 'sha256']);
    $x509 = openssl_csr_sign($csr, null, $pkey, 365, ['digest_alg' => 'sha256']);
    openssl_x509_export($x509, $certPem);

    return [$privatePem, $certPem];
}

/** Signed SAMLResponse (enveloped, RSA-SHA256, exc-c14n) with the given key. */
function signedSamlResponse(string $privatePem, string $userId = '73801-2755069', string $jwt = 'jwt-test'): string
{
    $xml = <<<XML
    <samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_resp1" Version="2.0">
      <saml:Assertion ID="_assert1" Version="2.0">
        <saml:AttributeStatement>
          <saml:Attribute Name="access-token"><saml:AttributeValue>{$jwt}</saml:AttributeValue></saml:Attribute>
          <saml:Attribute Name="user_ids-aziende"><saml:AttributeValue>{$userId}</saml:AttributeValue></saml:Attribute>
        </saml:AttributeStatement>
      </saml:Assertion>
    </samlp:Response>
    XML;

    $doc = new DOMDocument;
    $doc->loadXML($xml);

    $dsig = new XMLSecurityDSig;
    $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
    $dsig->addReference(
        $doc->documentElement,
        XMLSecurityDSig::SHA256,
        ['http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N],
        ['id_name' => 'ID'],
    );
    $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
    $key->loadKey($privatePem, false);
    $dsig->sign($key);
    $dsig->appendSignature($doc->documentElement);

    return $doc->saveXML();
}
