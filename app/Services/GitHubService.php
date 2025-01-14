<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GitHubService
{
	protected $apiUrl = "https://api.github.com";
	protected $token;

	public function __construct()
	{
		$this->token = config("services.github.token");
	}

	public function getCommits($repository, $limit)
	{
		$response = Http::withToken($this->token)->get("{$this->apiUrl}/repos/{$repository}/commits", [
			"per_page" => $limit,
		]);

		if ($response->status() == 401) {
			return null;
		}
		return $response->json() ?? [];
	}

	public function getIssues($repository, $limit)
	{
		$response = Http::withToken($this->token)->get("{$this->apiUrl}/repos/{$repository}/issues", [
			"per_page" => $limit,
			"state" => "all",
		]);

		if ($response->status() == 401) {
			return null;
		}

		return $response->json() ?? [];
	}

	public function getWiki($wikiUrl, $localPath, $pageName = null)
	{
		if (!is_dir($localPath)) {
			exec("git clone {$wikiUrl} {$localPath}");
		}

		if ($pageName != null) {
			$filePath = "{$localPath}/{$pageName}.md";
			$fileContent = file_get_contents($filePath);
			return $fileContent;
		}
		return "";
	}
}
