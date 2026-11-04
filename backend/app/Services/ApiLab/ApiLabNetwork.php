<?php

namespace App\Services\ApiLab;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ApiLabNetwork
{
	/**
	 * @return array<string, string|null>
	 */
	public function snapshot(Request $request): array
	{
		return [
			"client_ip" => $request->ip(),
			"docker_ip" => $this->containerIp(),
			"hostname" => gethostname() ?: null,
			"external_ip" => $this->externalIp(),
		];
	}

	protected function containerIp(): ?string
	{
		$hostname = gethostname();
		if (!$hostname) {
			return null;
		}

		$resolved = gethostbyname($hostname);

		return $resolved !== $hostname ? $resolved : null;
	}

	protected function externalIp(): ?string
	{
		return Cache::remember("api_lab:external_ip", 300, function () {
			try {
				$response = Http::timeout(2)->get("https://api.ipify.org?format=json");

				if ($response->successful()) {
					return $response->json("ip");
				}
			} catch (\Throwable) {
				//
			}

			return null;
		});
	}
}
