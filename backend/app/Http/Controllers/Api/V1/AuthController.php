<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Presenters\MePresenter;
use App\Models\Device;
use App\Models\User;
use App\Services\AccountConflict;
use App\Services\AccountService;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Services\Auth\InvalidGoogleToken;
use App\Services\FeatureSwitches;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly MePresenter $me,
    ) {}

    /**
     * No account needed: every install gets an anonymous account and a token.
     */
    public function device(Request $request): JsonResponse
    {
        $data = $request->validate([
            'install_id' => ['required', 'uuid'],
            'platform' => ['required', 'in:android,ios'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        [$user, $created] = $this->accounts->registerDevice($data['install_id'], $data['platform'], $data['app_version'] ?? null);

        $tokenName = $this->tokenName($data['install_id']);
        $user->tokens()->where('name', $tokenName)->delete();
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'created' => $created,
            'user' => $this->me->present($user),
        ], $created ? 201 : 200);
    }

    /**
     * Optional Google sign-in: keeps progress across phones and is needed for Premium sync.
     */
    public function google(Request $request, GoogleIdTokenVerifier $verifier, FeatureSwitches $switches): JsonResponse
    {
        if (! $switches->enabled('google_sign_in')) {
            return response()->json(['code' => 'feature_disabled', 'message' => 'Google sign-in is turned off.'], 403);
        }

        $data = $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            'install_id' => ['required', 'uuid'],
        ]);

        /** @var User $current */
        $current = $request->user();

        try {
            $identity = $verifier->verify($data['id_token']);
            $user = $this->accounts->signInWithGoogle($current, $identity);
        } catch (InvalidGoogleToken) {
            return response()->json(['code' => 'invalid_google_token', 'message' => 'Google sign-in failed. Please try again.'], 422);
        } catch (AccountConflict $e) {
            return response()->json(['code' => 'account_conflict', 'message' => $e->getMessage()], 409);
        }

        $this->deleteCurrentToken($request);

        $tokenName = $this->tokenName($data['install_id']);
        $user->tokens()->where('name', $tokenName)->delete();
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'merged' => $user->id !== $current->id,
            'user' => $this->me->present($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->deleteCurrentToken($request);

        return response()->json(['ok' => true]);
    }

    private function tokenName(string $installId): string
    {
        return 'device:'.substr(Device::hashInstallId($installId), 0, 24);
    }

    private function deleteCurrentToken(Request $request): void
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
