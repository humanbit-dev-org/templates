<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminSupportRequest;
use App\Notifications\AdminSupportRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SupportController extends Controller
{
	/**
	 * Send a support request email from the authenticated admin user.
	 *
	 * The per-hour limit is enforced by the "admin-support" rate limiter on
	 * the route. On top of it, an identical request (same user, category and
	 * message) sent again within the duplicate window is rejected, so a
	 * double submit never produces two emails.
	 */
	public function store(StoreAdminSupportRequest $request): JsonResponse
	{
		$user = backpack_user();
		$validated = $request->validated();

		// Cache::add() only writes when the key is missing, which makes the
		// check-and-mark step atomic: of two concurrent identical requests,
		// only the first one gets through.
		$duplicateKey =
			"admin-support:dup:" .
			$user->getAuthIdentifier() .
			":" .
			sha1($validated["category"] . "\n" . $validated["message"]);

		if (!Cache::add($duplicateKey, true, (int) config("backpack.support.duplicate_window_seconds", 60))) {
			return response()->json(
				[
					"message" => __("backpack::support.duplicate"),
				],
				409
			);
		}

		try {
			Notification::route("mail", config("backpack.support.recipient"))->notify(
				new AdminSupportRequestNotification(
					$user,
					$validated["category"],
					$validated["message"],
					(string) ($validated["page_url"] ?? $request->headers->get("referer", url()->current())),
					$this->screenshotsPayload($request->file("screenshots", []))
				)
			);
		} catch (Throwable $e) {
			// The email was not sent: release the duplicate marker so the user
			// can retry right away with the same content.
			Cache::forget($duplicateKey);

			throw $e;
		}

		return response()->json([
			"message" => __("backpack::support.sent"),
		]);
	}

	/**
	 * Read the uploaded screenshots into plain arrays (name, mime, binary
	 * content) so the notification does not depend on the temporary upload
	 * files still existing when the email is built.
	 *
	 * @param  array<int, UploadedFile>  $files
	 * @return array<int, array{name: string, mime: string, data: string}>
	 */
	protected function screenshotsPayload(array $files): array
	{
		return array_values(
			array_map(
				static fn(UploadedFile $file, int $index): array => [
					// Clipboard pastes arrive as "image.png": prefix an index so the
					// attachment names stay unique and in the order they were added.
					"name" => sprintf("screenshot-%d-%s", $index + 1, $file->getClientOriginalName() ?: "image.png"),
					"mime" => (string) $file->getMimeType(),
					"data" => (string) $file->get(),
				],
				$files,
				array_keys($files)
			)
		);
	}
}
