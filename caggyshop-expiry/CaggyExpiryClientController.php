<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

/**
 * CaggyShop: kirim masa expired server ke halaman console (dibaca oleh glass.js tema).
 * GET /api/client/servers/{identifier}/caggy-expiry
 * Hanya untuk admin, pemilik server, atau subuser server tersebut.
 */
class CaggyExpiryClientController extends Controller
{
    public function show(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['expires_at' => null], 401);
        }

        $server = Server::query()
            ->where(strlen($identifier) === 8 ? 'uuidShort' : 'uuid', $identifier)
            ->first();

        $allowed = $server && (
            $user->root_admin
            || (int) $server->owner_id === (int) $user->id
            || $server->subusers()->where('user_id', $user->id)->exists()
        );
        if (!$allowed) {
            return response()->json(['expires_at' => null], 404);
        }

        $row = Schema::hasTable('server_expirations')
            ? DB::table('server_expirations')->where('server_id', $server->id)->first()
            : null;

        return response()->json([
            'expires_at' => ($row && $row->expires_at) ? Carbon::parse($row->expires_at, 'UTC')->toIso8601String() : null,
            'suspended' => (bool) ($row->suspended_by_expiry ?? false),
        ]);
    }
}
