<?php

declare(strict_types=1);

namespace BlessedZulu\NativePhpAdmob\Http\Controllers;

use BlessedZulu\NativePhpAdmob\Exceptions\UnknownSlotException;
use BlessedZulu\NativePhpAdmob\Facades\Admob;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Same-origin endpoint for Buff's banner-only AdMob coordinator. */
class AdmobCallController
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            return match ((string) $request->input('kind')) {
                'ad' => $this->handleAd($request),
                'lifecycle' => $this->handleLifecycle($request),
                'ump' => $this->handleUmp($request),
                'att' => $this->handleAtt($request),
                default => $this->error('unknown_kind'),
            };
        } catch (UnknownSlotException) {
            return $this->error('unknown_slot');
        }
    }

    private function handleLifecycle(Request $request): JsonResponse
    {
        if ($request->input('action') === 'enabled') {
            return response()->json(['enabled' => Admob::enabled()]);
        }

        if ($request->input('action') === 'initialize') {
            return $this->result(Admob::initialize());
        }

        if ($request->input('action') !== 'configurePolicy') {
            return $this->error('invalid_action');
        }

        $underAge = $request->input('under_age_of_consent');
        $nonPersonalized = $request->input('non_personalized');
        $maxContentRating = $request->input('max_content_rating');

        if (! is_bool($underAge) || ! is_bool($nonPersonalized) || $maxContentRating !== 'T') {
            return $this->error('invalid_policy');
        }

        return $this->result(Admob::configurePolicy($underAge, $nonPersonalized, $maxContentRating));
    }

    private function handleAd(Request $request): JsonResponse
    {
        $format = (string) $request->input('format');
        $slot = (string) $request->input('slot');

        if ($format !== 'banner' || $slot !== 'app_shell') {
            return $this->error('invalid_ad_request');
        }

        $banner = Admob::banner($slot);

        return match ((string) $request->input('action')) {
            'load' => $this->ok(fn () => $banner->load()),
            'show' => $this->ok(fn () => $banner->show($this->position($request), $this->offset($request))),
            'hide' => $this->ok(fn () => $banner->hide()),
            default => $this->error('invalid_action'),
        };
    }

    private function handleUmp(Request $request): JsonResponse
    {
        $ump = Admob::ump();

        return match ((string) $request->input('action')) {
            'requestInfo' => $this->result($ump->requestConsentInfo()),
            'showForm' => $this->result($ump->showFormIfRequired()),
            'canRequestAds' => response()->json(['can_request' => $ump->canRequestAds()]),
            'status' => response()->json(['status' => $ump->status()]),
            'privacyOptionsStatus' => response()->json(['status' => $ump->privacyOptionsStatus()]),
            'showPrivacyOptionsForm' => $this->result($ump->showPrivacyOptionsForm()),
            default => $this->error('invalid_action'),
        };
    }

    private function handleAtt(Request $request): JsonResponse
    {
        $att = Admob::att();

        return match ((string) $request->input('action')) {
            'request' => $this->result($att->requestAuthorization()),
            'status' => response()->json(['status' => $att->status()]),
            default => $this->error('invalid_action'),
        };
    }

    private function ok(Closure $fn): JsonResponse
    {
        $fn();

        return response()->json(['ok' => true]);
    }

    /** @param array{success: bool, data?: mixed, error?: ?string} $result */
    private function result(array $result): JsonResponse
    {
        $ok = ($result['success'] ?? false) === true;
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];

        return response()->json(array_merge($data, [
            'ok' => $ok,
            'error' => $result['error'] ?? null,
        ]), $ok ? 200 : 422);
    }

    private function error(string $code, int $status = 422): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $code], $status);
    }

    private function position(Request $request): string
    {
        return $request->input('position') === 'top' ? 'top' : 'bottom';
    }

    private function offset(Request $request): ?int
    {
        $offset = $request->input('offset');

        return is_numeric($offset) ? (int) $offset : null;
    }
}
