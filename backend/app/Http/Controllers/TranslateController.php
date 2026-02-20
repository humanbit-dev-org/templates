<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Translate;
use Illuminate\Http\Request;

class TranslateController extends Controller
{
	public function index($page)
	{
		$pageId = Page::where("name", $page)->first()->id;
		$translates = Translate::where("page_id", $pageId)->get();
		return response()->json($translates);
	}
}
