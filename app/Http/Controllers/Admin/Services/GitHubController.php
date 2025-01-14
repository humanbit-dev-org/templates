<?php

namespace App\Http\Controllers\Admin\Services;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class GitHubController extends Controller
{
	public function index($page = null)
	{
		$gitHubService = new \App\Services\GitHubService();
		$repository = "humanbit-dev-org/templates";
		$wikiRepoUrl = "https://github.com/{$repository}.wiki.git";
		$localPath = storage_path("app/public/github/wiki");
		if ($page) {
			$wiki = $gitHubService->getWiki($wikiRepoUrl, $localPath, $page);
		} else {
			$wiki = $gitHubService->getWiki($wikiRepoUrl, $localPath);
		}
		return view("admin.github")->with("wiki", $wiki);
	}
}
