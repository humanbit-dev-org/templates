<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Metadata;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class MetadataController extends Controller
{
	/**
	 * @unauthenticated
	 */
	public function index($lang, $page)
	{
		// Store the language in the session
		Session::put("locale", $lang);

		// Ensure only allowed languages are used
		$lang = in_array($lang, ["en", "it"]) ? $lang : "en";

		// If $page is provided, try to get the page ID
		$page_id = Page::where("name", $page)->first()->id ?? Page::where("name", "home")->first()->id;

		$pageName = Page::where("id", $page_id)->value("name") ?? $page;

		$seo = Cache::remember("seo:{$lang}:{$pageName}", config("cache_ttl.seo"), static function () use ($lang, $page_id) {
			return Metadata::query()
				->select([$lang, "code", "image_path"])
				->where("page_id", $page_id)
				->get();
		});

		return response()->json($seo);
	}
}
