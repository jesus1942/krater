<?php

use Crater\Http\Middleware\ReportTenant;
use Crater\Models\User;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    TenantContext::clear();

    Schema::dropIfExists('school_levels');
    Schema::dropIfExists('companies');

    Schema::create('companies', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->string('unique_hash')->nullable();
        $table->timestamps();
    });

    Schema::create('school_levels', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('code')->nullable();
        $table->string('name')->nullable();
        $table->boolean('enabled')->default(true);
        $table->timestamps();
    });

    DB::table('companies')->insert([
        ['id' => 1, 'name' => 'Empresa A', 'unique_hash' => 'company-a'],
        ['id' => 2, 'name' => 'Empresa B', 'unique_hash' => 'company-b'],
    ]);

    DB::table('school_levels')->insert([
        ['id' => 10, 'company_id' => 1, 'code' => 'primary', 'name' => 'Primaria', 'enabled' => true],
        ['id' => 20, 'company_id' => 2, 'code' => 'secondary', 'name' => 'Secundaria', 'enabled' => true],
    ]);
});

afterEach(function () {
    TenantContext::clear();
    Schema::dropIfExists('school_levels');
    Schema::dropIfExists('companies');
});

function reportRequest(User $user, string $hash, int $levelId): Request
{
    $request = Request::create('/reports/test/'.$hash.'?school_level_id='.$levelId, 'GET');
    $request->setUserResolver(fn () => $user);
    $request->setRouteResolver(function () use ($hash) {
        return new class($hash) {
            private string $hash;
            public function __construct(string $hash) { $this->hash = $hash; }
            public function parameter($key, $default = null) { return $key === 'hash' ? $this->hash : $default; }
        };
    });

    return $request;
}

test('report web routes require authentication and report tenant middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return $route->uri() === 'reports/sales/customers/{hash}';
    });

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    expect($middleware)->toContain('redirect-if-unauthenticated');
    expect($middleware)->toContain('report-tenant');
});

test('total admin may report another company when level belongs to it', function () {
    $user = new User();
    $user->id = 50;
    $user->company_id = 1;

    $access = new class extends AccessManager {
        public function isTotalAdmin(User $user): bool { return true; }
        public function allows(User $user, string $permission, ?int $schoolLevelId = null, ?array $scope = null): bool { return true; }
    };

    $middleware = new ReportTenant($access);
    $response = $middleware->handle(reportRequest($user, 'company-b', 20), function () {
        return response()->json([
            'company' => TenantContext::companyId(),
            'level' => TenantContext::schoolLevelId(),
        ]);
    });

    expect($response->status())->toBe(200);
    $payload = json_decode($response->getContent(), true);
    expect($payload['company'])->toBe(2);
    expect($payload['level'])->toBe(20);
    expect(TenantContext::companyId())->toBeNull();
});

test('normal user cannot report another company', function () {
    $user = new User();
    $user->id = 51;
    $user->company_id = 1;

    $access = new class extends AccessManager {
        public function isTotalAdmin(User $user): bool { return false; }
        public function allows(User $user, string $permission, ?int $schoolLevelId = null, ?array $scope = null): bool { return true; }
    };

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->expectExceptionMessage('');
    (new ReportTenant($access))->handle(reportRequest($user, 'company-b', 20), fn () => response('ok'));
});

test('report requires an explicit level belonging to selected company', function () {
    $user = new User();
    $user->id = 52;
    $user->company_id = 1;

    $access = new class extends AccessManager {
        public function isTotalAdmin(User $user): bool { return true; }
        public function allows(User $user, string $permission, ?int $schoolLevelId = null, ?array $scope = null): bool { return true; }
    };

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    (new ReportTenant($access))->handle(reportRequest($user, 'company-a', 20), fn () => response('ok'));
});
