<?php

use Crater\Http\Middleware\ValidateTenant;
use Crater\Models\User;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function () {
    TenantContext::clear();
});

test('total admin can select another company tenant', function () {
    $user = new User();
    $user->id = 99;
    $user->company_id = 1;

    $access = new class extends AccessManager {
        public function isTotalAdmin(User $user): bool
        {
            return true;
        }
    };

    $request = Request::create('/test', 'GET');
    $request->headers->set('company', '2');
    $request->setUserResolver(fn () => $user);

    $middleware = new ValidateTenant($access);
    $response = $middleware->handle($request, function () {
        return response()->json([
            'company_id' => TenantContext::companyId(),
        ]);
    });

    expect($response->status())->toBe(200);
    expect(json_decode($response->getContent(), true)['company_id'])->toBe(2);
    expect(TenantContext::companyId())->toBeNull();
});

test('non total admin cannot select another company tenant', function () {
    $user = new User();
    $user->id = 100;
    $user->company_id = 1;

    $access = new class extends AccessManager {
        public function isTotalAdmin(User $user): bool
        {
            return false;
        }
    };

    $request = Request::create('/test', 'GET');
    $request->headers->set('company', '2');
    $request->setUserResolver(fn () => $user);

    $middleware = new ValidateTenant($access);
    $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

    expect($response->status())->toBe(403);
    expect(TenantContext::companyId())->toBeNull();
});
