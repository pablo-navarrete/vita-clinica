<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\WebsitePage;
use App\Models\WebsiteBanner;
use App\Models\WebsitePageSection;
use App\Models\WebsiteFooter;
use App\Models\WebsiteSocialSetting;
use App\Models\WebsiteTopbar;
use App\Models\Specialty;
use App\Models\WebsiteMaintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WebsitePageController extends Controller
{
    public function edit(string $customer, int $id): View
    {
        $page = WebsitePage::query()
            ->where('customer_id', app('customer')->id)
            ->with('sections')
            ->findOrFail($id);

        return view('customer.website-pages.edit', compact('page'));
    }

    public function updatePage(Request $request, string $customer, int $id): RedirectResponse|JsonResponse
    {
        $page = $this->findPage($id);
        $pageHasOwnBanner = $page->slug !== 'inicio';
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'banner' => [$pageHasOwnBanner ? 'nullable' : 'prohibited', 'image', 'max:10240'],
            'banner_alt' => [$pageHasOwnBanner ? 'nullable' : 'prohibited', 'string', 'max:160'],
            'remove_banner' => [$pageHasOwnBanner ? 'nullable' : 'prohibited', 'boolean'],
        ]);

        if ($request->boolean('remove_banner') && $page->banner_path) {
            Storage::disk('public')->delete($page->banner_path);
            $data['banner_path'] = null;
        }

        if ($request->hasFile('banner')) {
            if ($page->banner_path) {
                Storage::disk('public')->delete($page->banner_path);
            }
            $data['banner_path'] = $request->file('banner')->store(
                'website-pages/customer_' . app('customer')->id . '/banners',
                'public'
            );
        }

        unset($data['banner'], $data['remove_banner']);
        $page->update($data);
        foreach ($page->menuItems as $menuItem) {
            $menuItem->update(['name' => $page->title]);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'title' => $page->title]);
        }

        return $this->backToEditor($page->id)->with('success', 'Banner de la página actualizado.');
    }

    public function storeSection(Request $request, string $customer, int $id): RedirectResponse|JsonResponse
    {
        $page = $this->findPage($id);
        $data = $this->validatedSection($request);

        if (!$request->filled('title') && !$request->filled('body') && !$request->hasFile('image')) {
            throw ValidationException::withMessages([
                'body' => 'Agrega un título, un texto o una imagen para crear la sección.',
            ]);
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store(
                'website-pages/customer_' . app('customer')->id,
                'public'
            );
        }

        $data['body'] = $this->sanitizeRichText($data['body'] ?? '');
        $data = $this->extractLayoutImage($data);
        $section = $page->sections()->create($data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'section' => $section]);
        }

        return $this->backToEditor($page->id)->with('success', 'Sección agregada a la página.');
    }

    public function storeContentImage(Request $request, string $customer, int $id): JsonResponse
    {
        $this->findPage($id);
        $data = $request->validate(['image' => ['required', 'image', 'max:10240']]);
        $path = $data['image']->store(
            'website-pages/customer_' . app('customer')->id . '/content',
            'public'
        );

        return response()->json(['url' => '/storage/' . $path]);
    }

    public function updateSection(Request $request, string $customer, int $id, int $sectionId): RedirectResponse|JsonResponse
    {
        $page = $this->findPage($id);
        $section = $page->sections()->findOrFail($sectionId);
        $data = $this->validatedSection($request);
        $data['body'] = $this->sanitizeRichText($data['body'] ?? '');

        $willKeepImage = $section->image_path
            && !$request->boolean('remove_image')
            && !$request->hasFile('image');
        if (blank($data['title'] ?? null) && blank($data['body'] ?? null)
            && !$willKeepImage && !$request->hasFile('image')) {
            throw ValidationException::withMessages([
                'body' => 'La sección debe conservar un título, texto o imagen.',
            ]);
        }

        if ($request->boolean('remove_image') && $section->image_path) {
            $embeddedImageUrl = '/storage/' . $section->image_path;
            if (!str_contains($data['body'], $embeddedImageUrl)) {
                Storage::disk('public')->delete($section->image_path);
            }
            $data['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            if ($section->image_path) {
                Storage::disk('public')->delete($section->image_path);
            }

            $data['image_path'] = $request->file('image')->store(
                'website-pages/customer_' . app('customer')->id,
                'public'
            );
        }

        $data = $this->extractLayoutImage($data);

        unset($data['remove_image']);
        $section->update($data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return $this->backToEditor($page->id)->with('success', 'Sección actualizada.');
    }

    public function destroySection(string $customer, int $id, int $sectionId): RedirectResponse|JsonResponse
    {
        $page = $this->findPage($id);
        $section = $page->sections()->findOrFail($sectionId);

        if ($section->image_path) {
            Storage::disk('public')->delete($section->image_path);
        }

        $section->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return $this->backToEditor($page->id)->with('success', 'Sección eliminada.');
    }

    public function show(string $customer, string $slug): View|Response
    {
        $customerId = app('customer')->id;
        $maintenance = WebsiteMaintenance::query()->firstOrNew(['customer_id' => $customerId]);
        $canManageWebsite = auth()->check() && !auth()->user()->roles()->where('roles.name', 'Doctor')->exists();
        if ($maintenance->enabled && (request()->boolean('public_view') || !$canManageWebsite)) {
            return response()->view('customer.web.maintenance', compact('maintenance'), 503);
        }
        $page = WebsitePage::query()
            ->where('customer_id', $customerId)
            ->where('slug', $slug)
            ->where('status', true)
            ->with('sections')
            ->firstOrFail();

        $publicMenus = $this->publicMenus($customerId);
        $publicBanners = WebsiteBanner::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $publicSpecialties = Specialty::query()->where('customer_id', $customerId)->where('status', true)->orderBy('name')->get();
        $websiteFooter = WebsiteFooter::where('customer_id', $customerId)->first();
        $websiteSocialSettings = WebsiteSocialSetting::where('customer_id', $customerId)->first();
        $websiteTopbar = WebsiteTopbar::firstOrNew(
            ['customer_id' => $customerId],
            [
                'enabled' => true,
                'show_social_links' => (bool) ($websiteSocialSettings?->show_in_topbar ?? false),
                'phone' => $websiteFooter?->phone,
                'email' => $websiteFooter?->email,
            ]
        );

        return view('customer.web.page', [
            'page' => $page,
            'publicMenus' => $publicMenus,
            'publicBanners' => $publicBanners,
            'publicSpecialties' => $publicSpecialties,
            'hasConfiguredMenus' => $publicMenus->isNotEmpty(),
            'websiteFooter' => $websiteFooter,
            'websiteSocialSettings' => $websiteSocialSettings,
            'websiteTopbar' => $websiteTopbar,
        ]);
    }

    private function findPage(int $id): WebsitePage
    {
        return WebsitePage::query()
            ->where('customer_id', app('customer')->id)
            ->findOrFail($id);
    }

    private function validatedSection(Request $request): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:50000'],
            'image' => ['nullable', 'image', 'max:5120'],
            'image_alt' => ['nullable', 'string', 'max:160'],
            'image_position' => ['required', 'in:inline,top,left,right'],
            'position' => ['required', 'integer', 'min:0', 'max:999'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        unset($data['image']);

        return $data;
    }

    private function extractLayoutImage(array $data): array
    {
        if (($data['image_position'] ?? 'inline') === 'inline') {
            return $data;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>' . ($data['body'] ?? '') . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $document->getElementsByTagName('div')->item(0);
        $image = $document->getElementsByTagName('img')->item(0);
        if (!$root || !$image) return $data;

        $srcPath = parse_url($image->getAttribute('src'), PHP_URL_PATH);
        $expectedPrefix = '/storage/website-pages/customer_' . app('customer')->id . '/';
        if (!is_string($srcPath) || !str_starts_with($srcPath, $expectedPrefix)) return $data;

        $data['image_path'] = substr($srcPath, strlen('/storage/'));
        $data['image_alt'] = $image->getAttribute('alt') ?: ($data['image_alt'] ?? null);
        $image->parentNode?->removeChild($image);

        foreach (iterator_to_array($root->getElementsByTagName('p')) as $paragraph) {
            if (trim($paragraph->textContent) === '' && !$paragraph->getElementsByTagName('img')->length) {
                $paragraph->parentNode?->removeChild($paragraph);
            }
        }

        $body = '';
        foreach ($root->childNodes as $child) $body .= $document->saveHTML($child);
        $data['body'] = $this->sanitizeRichText($body);

        return $data;
    }

    private function sanitizeRichText(string $html): string
    {
        $allowedTags = ['div', 'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'span', 'font', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'pre', 'code', 'a', 'img'];
        $document = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $clean = function (\DOMNode $node) use (&$clean, $allowedTags): void {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child instanceof \DOMElement) {
                    $tag = strtolower($child->tagName);
                    if (!in_array($tag, $allowedTags, true)) {
                        $child->parentNode?->replaceChild($node->ownerDocument->createTextNode($child->textContent), $child);
                        continue;
                    }

                    $legacyColor = $tag === 'font' ? $child->getAttribute('color') : null;
                    $legacyFace = $tag === 'font' ? $child->getAttribute('face') : null;
                    $legacySize = $tag === 'font' ? $child->getAttribute('size') : null;
                    $safeHref = $tag === 'a' ? $child->getAttribute('href') : null;
                    $safeSrc = $tag === 'img' ? $child->getAttribute('src') : null;
                    $safeAlt = $tag === 'img' ? $child->getAttribute('alt') : null;
                    $safeWidth = $tag === 'img' ? $child->getAttribute('width') : null;
                    $safeHeight = $tag === 'img' ? $child->getAttribute('height') : null;
                    $safeClasses = array_intersect(preg_split('/\\s+/', trim($child->getAttribute('class'))) ?: [], [
                        'website-card-grid', 'website-card', 'website-card-circle', 'website-card-icon',
                        'website-card-image', 'website-card-category', 'website-card-button',
                        'fa-solid', 'fa-stethoscope', 'fa-heart', 'fa-user-doctor', 'fa-hand-holding-heart',
                        'fa-house-medical', 'fa-brain', 'fa-star', 'fa-shield-heart', 'fa-calendar-check',
                        'fa-comments', 'fa-spa', 'fa-tooth',
                        'fa-hospital', 'fa-user-nurse', 'fa-kit-medical', 'fa-pills', 'fa-syringe', 'fa-dna',
                        'fa-eye', 'fa-ear-listen', 'fa-baby', 'fa-person', 'fa-people-group', 'fa-wheelchair',
                        'fa-notes-medical', 'fa-file-medical', 'fa-prescription', 'fa-handshake', 'fa-leaf',
                        'fa-lightbulb', 'fa-award', 'fa-clock', 'fa-location-dot', 'fa-phone', 'fa-envelope',
                        'fa-circle-check', 'fa-heart-pulse', 'fa-briefcase-medical', 'fa-lungs', 'fa-bone',
                        'fa-hospital-user', 'fa-person-pregnant', 'fa-child', 'fa-user-group', 'fa-house-medical-circle-check',
                        'fa-file-prescription', 'fa-capsules', 'fa-vial', 'fa-microscope', 'fa-virus', 'fa-laptop-medical',
                        'fa-truck-medical', 'fa-bandage', 'fa-crutch', 'fa-wheelchair-move', 'fa-hand-holding-medical',
                        'fa-hand-holding-droplet', 'fa-seedling', 'fa-sun', 'fa-moon', 'fa-calendar-days', 'fa-map-location-dot',
                        'fa-phone-volume', 'fa-envelope-open-text', 'fa-comment-medical', 'fa-video', 'fa-user-shield',
                        'fa-shield-halved', 'fa-medal', 'fa-thumbs-up', 'fa-circle-plus', 'fa-circle-info', 'fa-circle-question',
                        'fa-check', 'fa-check-double', 'fa-clipboard-check', 'fa-list-check', 'fa-heart-circle-check',
                        'fa-person-circle-check', 'fa-people-roof', 'fa-hands-holding-circle', 'fa-handshake-angle',
                        'fa-book-medical', 'fa-book-open',
                    ]);
                    $style = [];
                    foreach (explode(';', $child->getAttribute('style')) as $declaration) {
                        if (!str_contains($declaration, ':')) continue;
                        [$property, $value] = array_map('trim', explode(':', $declaration, 2));
                        if ($property === 'color' && preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,20})$/i', $value)) $style[] = "color: {$value}";
                        if ($property === 'font-size' && preg_match('/^[0-9.]+(px|pt|em|rem|%)$/i', $value)) $style[] = "font-size: {$value}";
                        if ($property === 'font-family' && preg_match('/^[a-z ,\"\'-]{1,80}$/i', $value)) $style[] = "font-family: {$value}";
                        if ($property === 'text-align' && in_array($value, ['left', 'center', 'right', 'justify'], true)) $style[] = "text-align: {$value}";
                        if ($property === 'float' && in_array($value, ['left', 'right', 'none'], true)) $style[] = "float: {$value}";
                        if (in_array($property, ['width', 'height'], true) && preg_match('/^[0-9.]+(px|%)$/i', $value)) $style[] = "{$property}: {$value}";
                        if ($property === 'vertical-align' && in_array($value, ['top', 'middle', 'bottom'], true)) $style[] = "vertical-align: {$value}";
                        if ($property === 'display' && in_array($value, ['block', 'inline-block', 'flex'], true)) $style[] = "display: {$value}";
                        if ($property === 'justify-content' && in_array($value, ['flex-start', 'center', 'flex-end', 'space-between'], true)) $style[] = "justify-content: {$value}";
                        if ($property === 'align-items' && in_array($value, ['flex-start', 'center', 'flex-end', 'stretch'], true)) $style[] = "align-items: {$value}";
                        if ($property === 'gap' && preg_match('/^[0-9.]+(px|rem|em|%)$/i', $value)) $style[] = "gap: {$value}";
                    }
                    while ($child->attributes->length) $child->removeAttributeNode($child->attributes->item(0));
                    if ($safeClasses) $child->setAttribute('class', implode(' ', $safeClasses));
                    if ($style) $child->setAttribute('style', implode('; ', $style));
                    if ($tag === 'font' && preg_match('/^#[0-9a-f]{3,8}$/i', (string) $legacyColor)) {
                        $style[] = 'color: ' . $legacyColor;
                    }
                    if ($tag === 'font' && preg_match('/^[a-z ,"\'-]{1,80}$/i', (string) $legacyFace)) $style[] = 'font-family: ' . $legacyFace;
                    if ($tag === 'font' && preg_match('/^[1-7]$/', (string) $legacySize)) $style[] = 'font-size: ' . [1 => '10px', 2 => '13px', 3 => '16px', 4 => '18px', 5 => '24px', 6 => '32px', 7 => '48px'][(int) $legacySize];
                    if ($tag === 'font' && $style) $child->setAttribute('style', implode('; ', array_unique($style)));
                    if ($tag === 'a' && $safeHref) {
                        $scheme = strtolower((string) parse_url($safeHref, PHP_URL_SCHEME));
                        if (in_array($scheme, ['', 'http', 'https', 'mailto'], true)) $child->setAttribute('href', $safeHref);
                    }
                    $safeImagePrefix = '/storage/website-pages/customer_' . app('customer')->id . '/';
                    if ($tag === 'img' && str_starts_with((string) parse_url((string) $safeSrc, PHP_URL_PATH), $safeImagePrefix)) {
                        $child->setAttribute('src', (string) parse_url((string) $safeSrc, PHP_URL_PATH));
                        if ($safeAlt !== null) $child->setAttribute('alt', $safeAlt);
                        if (preg_match('/^\d{1,4}$/', (string) $safeWidth)) $child->setAttribute('width', $safeWidth);
                        if (preg_match('/^\d{1,4}$/', (string) $safeHeight)) $child->setAttribute('height', $safeHeight);
                    } elseif ($tag === 'img') {
                        $child->parentNode?->removeChild($child);
                        continue;
                    }
                }
                $clean($child);
            }
        };

        $root = $document->getElementsByTagName('div')->item(0);
        if (!$root) return e(strip_tags($html));
        $clean($root);
        $result = '';
        foreach ($root->childNodes as $child) $result .= $document->saveHTML($child);
        return $result;
    }

    private function publicMenus(int $customerId)
    {
        return \App\Models\Menu::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->orderByRaw('CASE WHEN position < 1 THEN 1 ELSE 0 END')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    private function backToEditor(int $id): RedirectResponse
    {
        return redirect()->route('customers.website-pages.edit', [
            'customer' => request()->route('customer'),
            'id' => $id,
        ]);
    }
}
