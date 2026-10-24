<?php

namespace Crater\Http\Controllers\V1\Site;

use Crater\Enums\Permission;
use Crater\Http\Controllers\Controller;
use Crater\Models\SchoolLevel;
use Crater\Services\Access\AccessManager;
use Crater\Services\Audit\Auditor;
use Crater\Services\Site\SiteContent;
use Crater\Support\PublicSite;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Configuracion > Sitio publico. La administracion total edita la
 * institucion, la foto principal y los tres niveles; la direccion de cada
 * nivel edita solo la pagina de su nivel.
 */
class SiteSettingsController extends Controller
{
    const LEVEL_CODES = ['primario' => 'primary', 'secundario' => 'secondary', 'terciario' => 'tertiary'];

    protected SiteContent $content;
    protected AccessManager $access;

    public function __construct(SiteContent $content, AccessManager $access)
    {
        $this->middleware('tenant');
        $this->content = $content;
        $this->access = $access;
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $saved = $this->content->all(TenantContext::companyId());
        $images = $this->content->images(TenantContext::companyId());
        $editable = array_values(array_filter(array_keys(self::LEVEL_CODES), fn ($slug) => $this->canEditLevel($user, $slug)));
        abort_unless($editable || $this->access->isTotalAdmin($user), 403);
        $pick = fn (string $prefix, array $fields) => collect($fields)->mapWithKeys(fn ($f) => [$f => $saved[$prefix.$f] ?? ''])->all();
        $defaults = PublicSite::levelDefaults();

        return response()->json([
            'can_edit_institution' => $this->access->isTotalAdmin($user),
            'institution' => $pick('institution.', SiteContent::INSTITUTION_FIELDS),
            'institution_defaults' => PublicSite::institutionDefaults(),
            'hero_image' => $images['hero'] ?? null,
            'levels' => collect($editable)->mapWithKeys(fn ($slug) => [$slug => [
                'name' => $defaults[$slug]['name'],
                'values' => $pick('level.'.$slug.'.', SiteContent::LEVEL_FIELDS),
                'defaults' => ['tagline' => $defaults[$slug]['tagline'], 'summary' => $defaults[$slug]['summary'],
                    'highlights' => $defaults[$slug]['highlights']],
                'image' => $images['level.'.$slug] ?? null,
            ]])->all(),
            'limits' => ['max_image_mb' => SiteContent::MAX_IMAGE_BYTES / 1048576],
        ]);
    }

    public function saveInstitution(Request $request)
    {
        abort_unless($this->access->isTotalAdmin($request->user()), 403);
        $data = $request->validate(array_merge($this->rules(SiteContent::INSTITUTION_FIELDS), [
            'email' => ['nullable', 'email', 'max:150'], 'about' => ['nullable', 'string', 'max:1200'],
        ]));
        $this->content->save(TenantContext::companyId(), 'institution.', SiteContent::INSTITUTION_FIELDS, $data, $request->user()->id);
        $this->audit($request, 'site.institution_updated', null, array_keys($data));

        return $this->show($request);
    }

    public function saveLevel(Request $request, string $slug)
    {
        abort_unless($this->canEditLevel($request->user(), $slug), 403);
        $data = $request->validate(array_merge($this->rules(SiteContent::LEVEL_FIELDS), [
            'email' => ['nullable', 'email', 'max:150'], 'summary' => ['nullable', 'string', 'max:600'],
            'meeting_link' => ['nullable', 'url', 'starts_with:https://', 'max:255'],
        ]));
        $this->content->save(TenantContext::companyId(), 'level.'.$slug.'.', SiteContent::LEVEL_FIELDS, $data, $request->user()->id);
        $this->audit($request, 'site.level_updated', $slug, array_keys($data));

        return $this->show($request);
    }

    public function uploadImage(Request $request, string $key)
    {
        $this->authorizeImage($request, $key);
        $data = $request->validate(['image' => ['required', 'file', 'max:4096'], 'alt' => ['nullable', 'string', 'max:255']]);
        $file = $data['image'];
        $this->content->putImage(TenantContext::companyId(), $key, $file->getRealPath(), (string) $file->getMimeType(),
            $data['alt'] ?? null, $request->user()->id);
        $this->audit($request, 'site.image_updated', $key, []);

        return $this->show($request);
    }

    public function deleteImage(Request $request, string $key)
    {
        $this->authorizeImage($request, $key);
        $this->content->deleteImage(TenantContext::companyId(), $key);
        $this->audit($request, 'site.image_deleted', $key, []);

        return $this->show($request);
    }

    /** Foto publica del sitio, servida desde la base con cache del navegador. */
    public function image(Request $request, string $key)
    {
        $image = DB::table('site_images')->where('company_id', SiteContent::companyId())->where('key', $key)->first();
        abort_unless($image, 404);
        $etag = '"'.md5($image->updated_at.$image->size).'"';
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response(base64_decode($image->data), 200, [
            'Content-Type' => $image->mime, 'Cache-Control' => 'public, max-age=604800', 'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function canEditLevel($user, string $slug): bool
    {
        if (! isset(self::LEVEL_CODES[$slug])) {
            return false;
        }
        if ($this->access->isTotalAdmin($user)) {
            return true;
        }
        $level = SchoolLevel::where('company_id', TenantContext::companyId())->where('code', self::LEVEL_CODES[$slug])->value('id');

        return $level && $this->access->allows($user, Permission::SITE_LEVEL_MANAGE, (int) $level);
    }

    protected function authorizeImage(Request $request, string $key): void
    {
        if ($key === 'hero') {
            abort_unless($this->access->isTotalAdmin($request->user()), 403);

            return;
        }
        abort_unless(preg_match('/^level\.(primario|secundario|terciario)$/', $key, $m) && $this->canEditLevel($request->user(), $m[1]), 403);
    }

    protected function rules(array $fields): array
    {
        return collect($fields)->mapWithKeys(fn ($f) => [$f => ['nullable', 'string', 'max:255']])->all();
    }

    protected function audit(Request $request, string $action, ?string $target, array $fields): void
    {
        app(Auditor::class)->record($action, $request->user(), ['type' => 'site', 'id' => null],
            [], array_filter(['target' => $target, 'fields' => $fields]));
    }
}
