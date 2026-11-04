<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Media\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoredFileController extends Controller
{
	public function show(Request $request, string $path): StreamedResponse
	{
		// Reject traversal before touching any disk: the route matches '.*'.
		abort_if(str_contains($path, ".."), 404);

		foreach (MediaStorage::candidateDisks($path) as $diskName) {
			$resolved = $this->resolveFilePath($diskName, $path);
			if ($resolved !== null) {
				return Storage::disk($diskName)->response($resolved);
			}
		}

		abort(404);
	}

	/**
	 * Resolve a stored path to an actual file path on the given disk.
	 * If the path points to a directory (legacy records stored only the folder),
	 * returns the first file found inside it.
	 */
	private function resolveFilePath(string $diskName, string $path): ?string
	{
		$storage = Storage::disk($diskName);

		if ($storage->exists($path) && !$this->isDirectory($diskName, $path)) {
			return $path;
		}

		// Legacy: path is a directory — serve the first file inside
		$files = $storage->files($path);
		if (!empty($files)) {
			return $files[0];
		}

		return null;
	}

	private function isDirectory(string $diskName, string $path): bool
	{
		try {
			Storage::disk($diskName)->size($path);

			return false;
		} catch (\Throwable) {
			return true;
		}
	}
}
