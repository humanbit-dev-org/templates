<?php

namespace App\Services\Auth;

use DOMDocument;
use DOMElement;
use DOMNode;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

/**
 * Verifica la firma XML-DSig di una SAMLResponse contro il certificato PINNATO in config
 * (config/saml.php), non contro l'<X509Certificate> del documento (controllato dall'attaccante).
 *
 * Ritorna i soli nodi effettivamente coperti dalla firma: gli attributi vanno letti da lì, mai
 * con XPath globale — e' la difesa contro il Signature Wrapping (XSW).
 *
 * Usa robrichards/xmlseclibs (>=3.1, con il fix XSW di CVE-2019-3465) per canonicalizzazione,
 * digest e verifica: crypto/C14N non si scrivono a mano.
 */
class SamlSignatureVerifier
{
	private const DS_NS = "http://www.w3.org/2000/09/xmldsig#";

	/**
	 * @return list<DOMNode> nodi firmati (da cui estrarre gli attributi)
	 *
	 * @throws RuntimeException se la firma e' assente, con algoritmo inatteso o non valida
	 */
	public function verifiedNodes(string $xml): array
	{
		$pem = $this->pinnedCertificatePem();
		if ($pem === null) {
			throw new RuntimeException("SAML IdP certificate not configured");
		}

		// Anti-XXE: niente DOCTYPE, niente rete. libxml non espande entita' esterne senza
		// LIBXML_NOENT (che NON impostiamo); il DOCTYPE lo rifiutiamo comunque a monte.
		if (stripos($xml, "<!DOCTYPE") !== false) {
			throw new RuntimeException("DOCTYPE not allowed");
		}

		$doc = new DOMDocument();
		if (!$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
			throw new RuntimeException("Invalid XML");
		}

		$dsig = new XMLSecurityDSig();
		$signature = $dsig->locateSignature($doc);
		if ($signature === null) {
			throw new RuntimeException("Signature not found");
		}

		// Pin dell'algoritmo di firma: solo RSA-SHA256, mai quello dichiarato liberamente altrove.
		if ($this->signatureMethodAlgorithm($signature) !== XMLSecurityKey::RSA_SHA256) {
			throw new RuntimeException("Unexpected signature algorithm");
		}

		$dsig->canonicalizeSignedInfo();

		// I riferimenti SAML puntano per attributo ID.
		$dsig->idKeys = ["ID"];

		// validateReference: verifica i digest e in caso di manomissione (o riferimenti
		// sospetti — protezione XSW della libreria, patched in >=3.0.4) lancia \Exception.
		// La normalizziamo a RuntimeException per un tipo di errore coerente.
		try {
			$referenceOk = $dsig->validateReference();
		} catch (\Exception $e) {
			throw new RuntimeException("Reference validation failed: " . $e->getMessage(), previous: $e);
		}
		if (!$referenceOk) {
			throw new RuntimeException("Reference digest mismatch");
		}

		// getValidatedNodes() ritorna un array (i soli nodi coperti dalla firma).
		$validated = $dsig->getValidatedNodes();
		if (!is_array($validated) || $validated === []) {
			throw new RuntimeException("No signed nodes");
		}

		// Verifica della firma con la chiave PINNATA (il cert del documento e' ignorato).
		$key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ["type" => "public"]);
		$key->loadKey($pem, false, true);
		if ($dsig->verify($key) !== 1) {
			throw new RuntimeException("Signature verification failed");
		}

		return array_values($validated);
	}

	private function signatureMethodAlgorithm(DOMElement $signature): string
	{
		$method = $signature->getElementsByTagNameNS(self::DS_NS, "SignatureMethod")->item(0);

		return $method instanceof DOMElement ? $method->getAttribute("Algorithm") : "";
	}

	private function pinnedCertificatePem(): ?string
	{
		$cert = trim((string) config("saml.idp_certificate"));
		if ($cert === "") {
			return null;
		}

		if (!str_contains($cert, "BEGIN CERTIFICATE")) {
			$cert = "-----BEGIN CERTIFICATE-----\n" . chunk_split($cert, 64, "\n") . "-----END CERTIFICATE-----";
		}

		return $cert;
	}
}
