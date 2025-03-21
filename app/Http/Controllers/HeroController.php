<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Media;
use Illuminate\Http\Request;

class HeroController extends Controller
{
	public function index($page)
	{
		$page = Page::where("name", $page)->first();
		$hero = Media::where("page_id", $page->id)->first();
		$heroData = [
			"title" => $page->title,
			"description" => $page->description,
			"image_path" => $hero->image_path ?? null,
			"caption" => $hero->caption ?? null,
		];
		return response()->json($heroData);
	}
}
