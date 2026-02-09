<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;
use App\Models\Metadata;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
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

		// Fetch SEO data with proper column selection
		$query = Metadata::select([$lang, "code", "image_path"]);
		$query->where("page_id", $page_id);

		$seo = $query->get();

		return response()->json($seo);
	}
}
