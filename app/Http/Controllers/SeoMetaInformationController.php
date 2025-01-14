<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SeoMetaInformation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class SeoMetaInformationController extends Controller
{
	public function index($lang, Request $request)
	{
		Session::put("locale", $lang);
		$lang = in_array($lang, ["en", "it"]) ? $lang : "en";

		$seo = SeoMetaInformation::select($lang, "code", "image_path")->get();
		return response()->json($seo);
	}
}
