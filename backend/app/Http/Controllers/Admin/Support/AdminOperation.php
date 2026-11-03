<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Models\Delibera;
use App\Models\Tiraggio;
use App\Models\Trattenuta;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminOperation extends Controller
{
    /**
     * Search users by USER_ID
     */
    public function searchUsers(Request $request)
    {
        try {
            $query = $request->get('q', '');
            $user_id = $request->get('user_id', '');
            $limit = $request->get('limit', 10);

            // If searching by specific USER_ID
            if ($user_id) {
                $user = User::where('user_id', $user_id)
                    ->with([
                        'filiale',
                        'tiraggi' => function ($query) {
                            $query->orderBy('created_at', 'desc');
                        },
                    ])
                    ->first();

                if ($user) {
                    return response()->json([
                        'user' => $user,
                    ]);
                } else {
                    return response()->json(['error' => 'Utente non trovato'], 404);
                }
            }

            // If searching by query string
            if (strlen($query) < 2) {
                return response()->json(['users' => []]);
            }

            $users = User::where('user_id', 'LIKE', "%{$query}%")
                ->orWhere('ragione_sociale', 'LIKE', "%{$query}%")
                ->select('user_id', 'ragione_sociale', 'email')
                ->limit($limit)
                ->get();

            return response()->json(['users' => $users->toArray()]);
        } catch (\Exception $e) {
            Log::error('Search users error: '.$e->getMessage());

            return response()->json(['error' => 'Errore interno del server'], 500);
        }
    }

    /**
     * Get user details with related data
     */
    public function getUserDetails(int $id): JsonResponse
    {
        $user = User::with(['filiale', 'tiraggi', 'delibere', 'storici'])->findOrFail($id);

        return response()->json([
            'user' => $user,
            'tiraggi' => $user->tiraggi,
            'delibere' => $user->delibere,
            // Chronological ascending load + last 12 calendar months (most recent 12 records)
            'storici' => $user->storici->slice(-12)->values(),
        ]);
    }

    /**
     * Toggle user suspended status
     */
    public function toggleUserSuspended(Request $request, int $id): JsonResponse
    {
        // Gestisci l'override del metodo PATCH
        if ($request->hasHeader('X-HTTP-Method-Override')) {
            $request->setMethod('PATCH');
        }

        $validator = Validator::make($request->all(), [
            'sospeso' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $user = User::findOrFail($id);
        $user->update([
            'sospeso' => $request->get('sospeso'),
        ]);

        return response()->json([
            'message' => 'User status updated successfully',
            'sospeso' => $user->sospeso,
        ]);
    }

    /**
     * Update tiraggio status
     */
    public function updateTiraggioStatus(Request $request, int $id): JsonResponse
    {
        Log::info('updateTiraggioStatus chiamato', [
            'id' => $id,
            'method' => $request->method(),
            'data' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        // Gestisci l'override del metodo PATCH
        if ($request->hasHeader('X-HTTP-Method-Override')) {
            $request->setMethod('PATCH');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:'.implode(',', \App\Models\Tiraggio::STATUSES),
        ]);

        if ($validator->fails()) {
            Log::error('Validazione fallita', $validator->errors()->toArray());
            throw new ValidationException($validator);
        }

        $tiraggio = Tiraggio::findOrFail($id);
        $tiraggio->update([
            'status' => $request->get('status'),
        ]);

        Log::info('Tiraggio aggiornato', [
            'id' => $id,
            'new_status' => $tiraggio->status,
        ]);

        return response()->json([
            'message' => 'Tiraggio status updated successfully',
            'status' => $tiraggio->status,
        ]);
    }

    /**
     * Update delibera status and max_atp
     */
    public function updateDeliberaStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|string|in:new,open,close',
            'max_atp_consigliato' => 'sometimes|nullable|numeric|min:0',
            'max_atp_effettivo' => 'sometimes|nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $delibera = Delibera::findOrFail($id);

        $updateData = [];
        if ($request->has('status')) {
            // Normalize legacy 'closed' to 'close' if ever received
            $incoming = strtolower($request->get('status'));
            $updateData['status'] = $incoming === 'closed' ? 'close' : $incoming;
        }
        if ($request->has('max_atp_consigliato')) {
            $updateData['max_atp_consigliato'] = $request->get('max_atp_consigliato');
        }
        if ($request->has('max_atp_effettivo')) {
            $updateData['max_atp_effettivo'] = $request->get('max_atp_effettivo');
        }

        $delibera->update($updateData);

        return response()->json([
            'message' => 'Delibera updated successfully',
            'status' => $delibera->status,
            'max_atp_consigliato' => $delibera->max_atp_consigliato,
            'max_atp_effettivo' => $delibera->max_atp_effettivo,
        ]);
    }

    /**
     * Search tiraggi by user ID
     */
    public function searchTiraggi(Request $request): JsonResponse
    {
        try {
            $user_id = $request->get('user_id'); // user_id now contains USER_ID (primary key)

            if (! $user_id) {
                return response()->json(['error' => 'User ID mancante'], 400);
            }

            $tiraggi = Tiraggio::where('user_id', $user_id)->orderBy('created_at', 'desc')->get();

            return response()->json(['tiraggi' => $tiraggi]);
        } catch (\Exception $e) {
            Log::error('Search tiraggi error: '.$e->getMessage());

            return response()->json(['error' => 'Errore interno del server'], 500);
        }
    }

    /**
     * Search delibere by user ID
     */
    public function searchDelibere(Request $request): JsonResponse
    {
        try {
            $user_id = $request->get('user_id'); // user_id now contains USER_ID (primary key)

            if (! $user_id) {
                return response()->json(['error' => 'User ID mancante'], 400);
            }

            $delibere = Delibera::where('user_id', $user_id)->orderBy('created_at', 'desc')->get();

            return response()->json(['delibere' => $delibere]);
        } catch (\Exception $e) {
            Log::error('Search delibere error: '.$e->getMessage());

            return response()->json(['error' => 'Errore interno del server'], 500);
        }
    }

    public function getRecentAcceptedTiraggi(): JsonResponse
    {
        try {
            // Get tiraggi with status 'accepted' of any date with trattenuta >= 4/6
            $tiraggi = Tiraggio::where('status', 'accepted')
                ->whereRaw('trattenuta_totale >= (importo_deliberato * 4 / 6)') // Trattenuta totale >= 4/6 dell'importo deliberato
                ->with([
                    'user' => function ($query) {
                        $query->select('user_id', 'ragione_sociale');
                    },
                ])
                ->orderBy('data_delibera', 'desc')
                ->limit(10) // Limit to 10 most recent
                ->get();

            return response()->json(['tiraggi' => $tiraggi]);
        } catch (\Exception $e) {
            Log::error('Get recent accepted tiraggi error: '.$e->getMessage());

            return response()->json(['error' => 'Errore interno del server'], 500);
        }
    }
}
