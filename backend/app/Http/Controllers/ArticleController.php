<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
	public function index($lang)
	{
		$lang = in_array($lang, ["en", "it"]) ? $lang : "en";
		$query = Article::where("strillo", true)
			->where("published", true)
			->select("title_" . $lang, "subtitle_" . $lang, "body_" . $lang)
			->get();
		return response()->json($query);
	}
}
