<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function __invoke(Request $request): Response
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\User|null $user */
        $user = Auth::user();

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        if ($user !== null) {
            $this->auditLogger->logFromRequest(
                $request,
                action: 'auth.logout',
                resourceType: 'user',
                resourceId: $user->public_id,
            );
        }

        return response()->noContent();
    }
}
