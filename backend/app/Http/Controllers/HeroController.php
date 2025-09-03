<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Media;
use Illuminate\Http\Request;

class HeroController extends Controller
{
	public function index($page)
	{
		$pageRecord = Page::where("name", $page)->first();

		if (!$pageRecord) {
			return response()->json([], 404);
		}

		$hero = Media::where("page_id", $pageRecord->id)->first();

		$heroData = [
			"title" => $pageRecord->title,
			"description" => $pageRecord->description,
			"image_path" => $hero->image_path ?? null,
			"caption" => $hero->caption ?? null,
		];

		return response()->json($heroData);
	}
}
