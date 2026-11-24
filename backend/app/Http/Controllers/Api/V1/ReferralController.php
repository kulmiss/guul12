<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FeatureSwitches;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referrals,
        private readonly FeatureSwitches $switches,
    ) {}

    /** GET /v1/referral — my invite code, how many friends joined, and the rules. */
    public function show(Request $request): JsonResponse
    {
        if ($off = $this->off()) {
            return $off;
        }
        /** @var User $me */
        $me = $request->user();

        return response()->json(['referral' => $this->referrals->summary($me)]);
    }

    /** POST /v1/referral — enter the code of the friend who invited me. */
    public function store(Request $request): JsonResponse
    {
        if ($off = $this->off()) {
            return $off;
        }
        /** @var User $me */
        $me = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:8']]);
        $error = $this->referrals->enterCode($me, (string) $data['code']);
        if ($error !== null) {
            return response()->json(['code' => $error, 'message' => 'This code cannot be used.'], $error === 'not_found' ? 404 : 409);
        }

        return response()->json(['referral' => $this->referrals->summary($me)], 201);
    }

    private function off(): ?JsonResponse
    {
        return $this->switches->enabled('referral')
            ? null
            : response()->json(['code' => 'feature_disabled', 'message' => 'Invites are turned off.'], 403);
    }
}
