<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Specialty;
use App\Models\WebsiteBlogPost;
use App\Models\WebsiteBlogSetting;
use App\Models\WebsiteBanner;
use App\Models\WebsiteFooter;
use App\Models\WebsiteMaintenance;
use App\Models\WebsiteNavigationButton;
use App\Models\WebsiteSocialSetting;
use App\Models\WebsiteTopbar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebsiteBlogController extends Controller
{
    public function index(): View
    {
        $customerId = (int) app('customer')->id;
        $setting = WebsiteBlogSetting::firstOrCreate(['customer_id' => $customerId]);
        $posts = WebsiteBlogPost::where('customer_id', $customerId)
            ->orderByDesc('created_at')->get();

        return view('customer.website-blog.index', compact('setting', 'posts'));
    }

    public function create(): View
    {
        return view('customer.website-blog.form', ['post' => new WebsiteBlogPost()]);
    }

    public function store(Request $request, string $customer): RedirectResponse
    {
        $data = $this->validatedData($request);
        $customerId = (int) app('customer')->id;
        $data['customer_id'] = $customerId;
        $data['user_id'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['title'], $customerId);
        $data['status'] = $request->boolean('status');
        $data['published_at'] = $data['status'] ? now() : null;
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store("website-blog/customer_{$customerId}", 'public');
        }

        unset($data['image']);
        WebsiteBlogPost::create($data);

        return redirect()->route('customers.website-blog.index', ['customer' => $customer])
            ->with('success', 'Artículo creado correctamente.');
    }

    public function edit(string $customer, int $id): View
    {
        return view('customer.website-blog.form', ['post' => $this->findPost($id)]);
    }

    public function preview(string $customer, int $id): View
    {
        $post = $this->findPost($id);
        $contentHtml = $this->sanitizeBlogContent((string) $post->content);

        return view('customer.web.blog.show', array_merge(
            $this->publicViewData(),
            compact('post', 'contentHtml'),
            ['previewMode' => true]
        ));
    }

    public function update(Request $request, string $customer, int $id): RedirectResponse
    {
        $post = $this->findPost($id);
        $data = $this->validatedData($request);
        $data['slug'] = $this->uniqueSlug($data['title'], (int) app('customer')->id, $post->id);
        $data['status'] = $request->boolean('status');
        $data['published_at'] = $data['status'] ? ($post->published_at ?? now()) : null;

        if ($request->hasFile('image')) {
            $newImage = $request->file('image')->store('website-blog/customer_' . app('customer')->id, 'public');
            if ($post->image_path) Storage::disk('public')->delete($post->image_path);
            $data['image_path'] = $newImage;
        }

        unset($data['image']);
        $post->update($data);

        return redirect()->route('customers.website-blog.index', ['customer' => $customer])
            ->with('success', 'Artículo actualizado correctamente.');
    }

    public function updateSettings(Request $request, string $customer): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'title' => ['required', 'string', 'max:80'],
            'banner' => ['nullable', 'image', 'max:10240'],
            'remove_banner' => ['nullable', 'boolean'],
        ]);
        $customerId = (int) app('customer')->id;
        $setting = WebsiteBlogSetting::firstOrNew(['customer_id' => $customerId]);
        if ($request->boolean('remove_banner') && ! $request->hasFile('banner') && $setting->banner_path) {
            Storage::disk('public')->delete($setting->banner_path);
            $setting->banner_path = null;
        }
        if ($request->hasFile('banner')) {
            $newBanner = $request->file('banner')->store("website-blog/customer_{$customerId}/banner", 'public');
            if ($setting->banner_path) Storage::disk('public')->delete($setting->banner_path);
            $setting->banner_path = $newBanner;
        }
        $setting->fill(
            [
                'enabled' => (bool) $data['enabled'],
                'title' => trim($data['title']),
                'banner_path' => $setting->banner_path,
            ]
        )->save();

        return redirect()->route('customers.website-blog.index', ['customer' => $customer])
            ->with('success', $data['enabled'] ? 'El blog ya se muestra en la web pública.' : 'El blog se ocultó de la web pública.');
    }

    public function toggleStatus(Request $request, string $customer, int $id): RedirectResponse|JsonResponse
    {
        $post = $this->findPost($id);
        $published = ! $post->status;
        $post->update(['status' => $published, 'published_at' => $published ? now() : null]);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $published,
                'published_at' => $post->published_at?->format('d/m/Y') ?? $post->created_at->format('d/m/Y'),
                'message' => $published ? 'Articulo publicado.' : 'Articulo guardado como borrador.',
            ]);
        }

        return back()->with('success', $published ? 'Artículo publicado.' : 'Artículo guardado como borrador.');
    }

    public function destroy(string $customer, int $id): RedirectResponse
    {
        $post = $this->findPost($id);
        if ($post->image_path) Storage::disk('public')->delete($post->image_path);
        $post->delete();

        return redirect()->route('customers.website-blog.index', ['customer' => $customer])
            ->with('success', 'Artículo eliminado.');
    }

    public function publicIndex(string $customer): View|Response
    {
        $this->ensurePublicBlogEnabled();
        $search = trim((string) request()->query('search', ''));
        $posts = WebsiteBlogPost::where('customer_id', app('customer')->id)
            ->where('status', true)->whereNotNull('published_at')->where('published_at', '<=', now())
            ->when($search !== '', function ($query) use ($search) {
                $term = '%' . $search . '%';
                $query->where(function ($query) use ($term) {
                    $query->where('title', 'like', $term)
                        ->orWhere('excerpt', 'like', $term)
                        ->orWhere('content', 'like', $term);
                });
            })
            ->orderByDesc('published_at')->paginate(9)->withQueryString();

        return view('customer.web.blog.index', array_merge($this->publicViewData(), compact('posts', 'search')));
    }

    public function publicShow(string $customer, string $slug): View|Response
    {
        $this->ensurePublicBlogEnabled();
        $post = WebsiteBlogPost::where('customer_id', app('customer')->id)
            ->where('slug', $slug)->where('status', true)
            ->whereNotNull('published_at')->where('published_at', '<=', now())->firstOrFail();
        $contentHtml = $this->sanitizeBlogContent((string) $post->content);

        return view('customer.web.blog.show', array_merge($this->publicViewData(), compact('post', 'contentHtml'), ['previewMode' => false]));
    }

    private function ensurePublicBlogEnabled(): void
    {
        $customerId = (int) app('customer')->id;
        abort_unless(WebsiteBlogSetting::where('customer_id', $customerId)->where('enabled', true)->exists(), 404);

        $maintenance = WebsiteMaintenance::firstOrNew(['customer_id' => $customerId]);
        $user = auth()->user();
        $hasAdminAccess = $user && ($user->roles()->where('roles.customer_id', $customerId)->where('roles.name', 'Administrador')->exists()
            || $user->roles()->whereNull('roles.customer_id')->where('roles.name', 'Super Administrador')->exists());
        $isDoctorOnly = $user && $user->roles()->where('roles.customer_id', $customerId)->where('roles.name', 'Doctor')->exists() && ! $hasAdminAccess;
        $canManageWebsite = (bool) $user && ! $isDoctorOnly;
        if ($maintenance->enabled && (request()->boolean('public_view') || ! $canManageWebsite)) {
            abort(response()->view('customer.web.maintenance', compact('maintenance'), 503));
        }
    }

    private function publicViewData(): array
    {
        $customerId = (int) app('customer')->id;
        $menus = Menu::with('websitePage')->where('customer_id', $customerId)->where('status', true)
            ->orderByRaw('CASE WHEN position < 1 THEN 1 ELSE 0 END')->orderBy('position')->orderBy('id')->get();
        $footer = WebsiteFooter::where('customer_id', $customerId)->first();
        $socials = WebsiteSocialSetting::where('customer_id', $customerId)->first();
        $topbar = WebsiteTopbar::firstOrNew(['customer_id' => $customerId], [
            'enabled' => true,
            'show_social_links' => (bool) ($socials?->show_in_topbar ?? false),
            'phone' => $footer?->phone,
            'email' => $footer?->email,
        ]);

        return [
            'blogSetting' => WebsiteBlogSetting::firstOrNew(['customer_id' => $customerId]),
            'publicMenus' => $menus,
            'hasConfiguredMenus' => $menus->isNotEmpty(),
            'websiteNavigationButton' => WebsiteNavigationButton::where('customer_id', $customerId)->first(),
            'publicBanners' => WebsiteBanner::where('customer_id', $customerId)->where('status', true)->orderBy('position')->orderBy('id')->get(),
            'publicSpecialties' => Specialty::where('customer_id', $customerId)->where('status', true)->orderBy('name')->get(),
            'websiteFooter' => $footer,
            'websiteSocialSettings' => $socials,
            'websiteTopbar' => $topbar,
        ];
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:100000'],
            'image' => ['nullable', 'image', 'max:5120'],
            'status' => ['nullable', 'boolean'],
        ]);

        $data['content'] = $this->sanitizeBlogContent($data['content']);

        return $data;
    }

    private function sanitizeBlogContent(string $html): string
    {
        $allowedTags = [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup',
            'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'pre', 'code',
            'a', 'span', 'div', 'img', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        ];
        $customerId = (int) app('customer')->id;
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="blog-content-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $clean = function (\DOMNode $node) use (&$clean, $allowedTags, $customerId): void {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }

                $tag = strtolower($child->tagName);
                if (!in_array($tag, $allowedTags, true)) {
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math'], true)) {
                        $child->parentNode?->removeChild($child);
                    } else {
                        $child->parentNode?->replaceChild($node->ownerDocument->createTextNode($child->textContent), $child);
                    }
                    continue;
                }

                $href = $tag === 'a' ? trim($child->getAttribute('href')) : '';
                $src = $tag === 'img' ? trim($child->getAttribute('src')) : '';
                $alt = $tag === 'img' ? $child->getAttribute('alt') : '';
                $style = $child->getAttribute('style');
                $safeStyles = [];
                foreach (explode(';', $style) as $declaration) {
                    if (!str_contains($declaration, ':')) continue;
                    [$property, $value] = array_map('trim', explode(':', $declaration, 2));
                    $property = strtolower($property);
                    if ($property === 'text-align' && in_array(strtolower($value), ['left', 'center', 'right', 'justify'], true)) $safeStyles[] = "text-align: {$value}";
                    if ($property === 'color' && preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,20})$/i', $value)) $safeStyles[] = "color: {$value}";
                    if ($property === 'background-color' && preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,20})$/i', $value)) $safeStyles[] = "background-color: {$value}";
                    if ($property === 'font-size' && preg_match('/^[0-9.]+(px|pt|em|rem|%)$/i', $value)) $safeStyles[] = "font-size: {$value}";
                    if ($property === 'font-family' && preg_match('/^[a-z ,"\'-]{1,80}$/i', $value)) $safeStyles[] = "font-family: {$value}";
                    if (in_array($property, ['width', 'height'], true) && preg_match('/^[0-9.]+(px|%)$/i', $value)) $safeStyles[] = "{$property}: {$value}";
                }

                while ($child->attributes->length) $child->removeAttributeNode($child->attributes->item(0));
                if ($safeStyles) $child->setAttribute('style', implode('; ', $safeStyles));

                if ($tag === 'a' && $href !== '') {
                    $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
                    if (in_array($scheme, ['', 'http', 'https', 'mailto'], true)) {
                        $child->setAttribute('href', $href);
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }
                }

                if ($tag === 'img') {
                    $path = (string) parse_url($src, PHP_URL_PATH);
                    $safeStoragePath = '/storage/website-blog/customer_' . $customerId . '/';
                    $safeDataImage = preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=]+$#i', $src);
                    if ($safeDataImage || str_starts_with($path, $safeStoragePath)) {
                        $child->setAttribute('src', $safeDataImage ? $src : $path);
                        if ($alt !== '') $child->setAttribute('alt', $alt);
                    } else {
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

        $content = '';
        foreach ($root->childNodes as $child) $content .= $document->saveHTML($child);

        return $content;
    }

    private function uniqueSlug(string $title, int $customerId, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'articulo';
        $slug = $base;
        $suffix = 2;
        while (WebsiteBlogPost::where('customer_id', $customerId)->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $suffix++;
        }
        return $slug;
    }

    private function findPost(int $id): WebsiteBlogPost
    {
        return WebsiteBlogPost::where('customer_id', app('customer')->id)->findOrFail($id);
    }
}
