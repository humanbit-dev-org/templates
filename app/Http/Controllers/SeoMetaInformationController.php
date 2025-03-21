<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;
use App\Models\SeoMetaInformation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class SeoMetaInformationController extends Controller
{
	public function index($lang, $page = null)
	{
		// Store the language in the session
		Session::put("locale", $lang);

		// Ensure only allowed languages are used
		$lang = in_array($lang, ["en", "it"]) ? $lang : "en";

		// If $page is provided, try to get the page ID
		$page_id = null;
		if ($page) {
			$page_id = Page::where("name", $page)->first()->id;
		}

		// Fetch SEO data with proper column selection
		$query = SeoMetaInformation::select([$lang, "code", "image_path"]);

		if ($page_id) {
			$query->where("page_id", $page_id);
		} else {
			$query->where("page_id", null);
		}

		$seo = $query->get();

		return response()->json($seo);
	}
}
