<?php

namespace App\Services\Restricted;

use App\Models\User;
use Illuminate\Http\Request;

class RestrictedToolAccessService
{
	public function config(string $tool): array
	{
		$toolConfig = config("restricted_tools.tools.$tool");

		if (!is_array($toolConfig)) {
			return [
				"checks" => ["auth"],
				"roles" => ["developer"],
				"operator" => "or",
				"enabled_environments" => null,
			];
		}

		$checks = $toolConfig["checks"] ?? [];
		if (!is_array($checks)) {
			$checks = [];
		}

		$normalizedChecks = array_values(array_unique(array_map(static fn($check) => strtolower((string) $check), $checks)));

		$enabledEnvironments = $toolConfig["enabled_environments"] ?? null;

		// Normalize operator: only 'and' is explicit, everything else defaults to 'or'
		$operator = strtolower((string) ($toolConfig["operator"] ?? "or"));
		if (!in_array($operator, ["or", "and"], true)) {
			$operator = "or";
		}

		return [
			"checks" => $normalizedChecks,
			"roles" => is_array($toolConfig["roles"] ?? null) ? $toolConfig["roles"] : [],
			"enabled_environments" => is_array($enabledEnvironments) ? $enabledEnvironments : null,
			"operator" => $operator,
		];
	}

	public function isEnabledInCurrentEnvironment(string $tool): bool
	{
		$config = $this->config($tool);
		$enabled = $config["enabled_environments"];

		if ($enabled === null) {
			return true;
		}

		return in_array((string) config("app.env"), $enabled, true);
	}

	public function canAccess(string $tool, ?User $user, Request $request): bool
	{
		if (!$this->isEnabledInCurrentEnvironment($tool)) {
			return false;
		}

		$config = $this->config($tool);
		$checks = $config["checks"];
		$roles = $config["roles"];
		$operator = $config["operator"];

		// No checks configured: IP-only restriction.
		// The tool is active by its presence in restricted_tools config.
		// IP_BLOCK_ENABLE is the master switch — when off, no IP restriction applies.
		// ALLOWED_IPS is the sole source of truth — empty list means no one passes.
		if (empty($checks)) {
			if (!$this->ipBlockEnabled()) {
				return true;
			}

			return $this->isIpAllowed($this->getClientIp($request));
		}

		$isAuthenticated = $user !== null;
		$hasRole = $this->userHasAllowedRole($user, $roles);
		$requiresRole = !empty($roles);

		// IP check is active only if the IP_BLOCK_ENABLE master switch is on
		// and cors.allowed_ips is non-empty
		$allowedIps = config("cors.allowed_ips", []);
		$ipCheckActive = in_array("ip", $checks, true) && $this->ipBlockEnabled() && !empty($allowedIps);
		$hasAllowedIp = $ipCheckActive && $this->isIpAllowed($this->getClientIp($request));

		$canByIp = $ipCheckActive && $hasAllowedIp;

		$canByAuth = in_array("auth", $checks, true) && $isAuthenticated && (!$requiresRole || $hasRole);

		// 'and': all active checks must pass simultaneously.
		// If IP check is configured but disabled (IP_BLOCK_ENABLE off
		// or ALLOWED_IPS empty), auth alone is sufficient.
		if ($operator === "and") {
			$needsIp = $ipCheckActive;
			$needsAuth = in_array("auth", $checks, true);

			return (!$needsIp || $canByIp) && (!$needsAuth || $canByAuth);
		}

		// 'or' (default): any passing check grants access.
		// If IP check is configured but disabled (IP_BLOCK_ENABLE off
		// or ALLOWED_IPS empty), fall back to auth check only.
		if (in_array("ip", $checks, true) && !$ipCheckActive) {
			return $canByAuth;
		}

		return $canByIp || $canByAuth;
	}

	public function userHasAllowedRole(?User $user, array $allowedRoles): bool
	{
		if (!$user || !$user->backpack_role_id) {
			return false;
		}

		$roleName = strtolower($user->backpackRole?->name ?? "");
		$normalized = array_map(fn($role) => strtolower((string) $role), $allowedRoles);

		return in_array($roleName, $normalized, true);
	}

	public function ipBlockEnabled(): bool
	{
		return (bool) config("cors.ip_block_enable", false);
	}

	public function isIpAllowed(?string $ip): bool
	{
		$allowedIps = config("cors.allowed_ips", []);

		if (empty($allowedIps) || !$ip) {
			return false;
		}

		foreach ($allowedIps as $allowed) {
			if ($ip === $allowed) {
				return true;
			}

			if (str_contains($allowed, "/") && $this->ipInCidr($ip, $allowed)) {
				return true;
			}

			if (str_contains($allowed, "*")) {
				// \d{1,3} limits each octet to max 3 digits (valid IPv4 range)
				$pattern = "/^" . str_replace([".", "*"], ["\.", "\d{1,3}"], $allowed) . '$/';
				if (preg_match($pattern, $ip)) {
					return true;
				}
			}
		}

		return false;
	}

	public function ipInCidr(string $ip, string $cidr): bool
	{
		[$subnet, $bits] = explode("/", $cidr);

		if (
			!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ||
			!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
		) {
			return false;
		}

		$ipLong = ip2long($ip);
		$subnetLong = ip2long($subnet);
		$maskLong = -1 << 32 - (int) $bits;

		return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
	}

	public function getClientIp(Request $request): ?string
	{
		// trustProxies (bootstrap/app.php) already resolves X-Forwarded-For
		// from trusted proxies only. Reading the header manually would allow
		// any client to spoof a whitelisted IP.
		return $request->ip();
	}
}
