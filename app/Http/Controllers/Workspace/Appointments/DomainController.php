<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Models\Website\Website;
use App\Models\Website\WebsiteDomain;
use App\Services\Domain\DomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class DomainController extends AppointmentsBaseController
{
    public function __construct(
        private readonly DomainService $domainService,
    ) {}

    public function index(Request $request, Website $website): View
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureSameWorkspace($website);
        $this->authorize('manageDomains', $website);

        $website->load([
            'domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('id'),
            'domains.contacts',
            'domains.operations' => fn ($query) => $query->latest('id')->limit(20),
        ]);

        return view('workspace.appointments.website.domains', [
            'website' => $website,
            'domains' => $website->domains,
            'searchResults' => session('domain_search_results', []),
            'lastSearchedQuery' => session('domain_search_query'),
        ]);
    }

    public function search(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureSameWorkspace($website);
        $this->authorize('manageDomains', $website);

        $validated = $request->validate([
            'query' => ['required', 'string', 'max:120'],
            'extensions' => ['nullable', 'array'],
            'extensions.*' => ['string', 'max:20'],
        ]);

        try {
            $results = $this->domainService->searchDomains(
                query: (string) $validated['query'],
                extensions: $validated['extensions'] ?? null
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('workspace.appointments.website.domains', $website)
            ->with('domain_search_results', $results)
            ->with('domain_search_query', $validated['query']);
    }

    public function purchase(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureSameWorkspace($website);
        $this->authorize('manageDomains', $website);

        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'years' => ['nullable', 'integer', 'min:1', 'max:10'],
            'contact.first_name' => ['required', 'string', 'max:255'],
            'contact.last_name' => ['required', 'string', 'max:255'],
            'contact.organization_name' => ['nullable', 'string', 'max:255'],
            'contact.job_title' => ['nullable', 'string', 'max:255'],
            'contact.address1' => ['required', 'string', 'max:255'],
            'contact.address2' => ['nullable', 'string', 'max:255'],
            'contact.city' => ['required', 'string', 'max:80'],
            'contact.state_province' => ['required', 'string', 'max:80'],
            'contact.postal_code' => ['required', 'string', 'max:40'],
            'contact.country' => ['required', 'string', 'size:2'],
            'contact.phone' => ['required', 'string', 'max:50'],
            'contact.email' => ['required', 'email', 'max:255'],
        ]);

        $contact = $validated['contact'];
        $contacts = [
            'registrant' => $contact,
            'admin' => $contact,
            'tech' => $contact,
            'aux_billing' => $contact,
        ];

        try {
            $domain = $this->domainService->purchaseDomain(
                website: $website,
                domain: (string) $validated['domain'],
                years: (int) ($validated['years'] ?? 1),
                contacts: $contacts,
                actorUserId: (int) $request->user()?->id,
            );
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('workspace.appointments.website.domains', $website)
            ->with('success', 'تم بدء شراء الدومين. سيتم تحديث الحالة بعد معالجة المهام الخلفية.')
            ->with('highlight_domain_id', $domain->id);
    }

    public function setPrimary(Request $request, Website $website, WebsiteDomain $domain): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureDomainBelongsToWebsite($website, $domain);
        $this->authorize('setPrimary', $domain);

        $this->domainService->setPrimaryDomain($domain);

        return back()->with('success', 'تم تعيين الدومين الأساسي للموقع.');
    }

    public function verify(Request $request, Website $website, WebsiteDomain $domain): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureDomainBelongsToWebsite($website, $domain);
        $this->authorize('update', $domain);

        try {
            $this->domainService->verifyDomain($domain);
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تنفيذ التحقق من الدومين.');
    }

    public function renew(Request $request, Website $website, WebsiteDomain $domain): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureDomainBelongsToWebsite($website, $domain);
        $this->authorize('renew', $domain);

        $validated = $request->validate([
            'years' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $this->domainService->renewDomain($domain, (int) ($validated['years'] ?? 1));

        return back()->with('success', 'تم إرسال طلب تجديد الدومين.');
    }

    public function sync(Request $request, Website $website, WebsiteDomain $domain): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureDomainBelongsToWebsite($website, $domain);
        $this->authorize('update', $domain);

        $this->domainService->syncDomainStatus($domain);

        return back()->with('success', 'تم إرسال طلب مزامنة حالة الدومين.');
    }

    public function toggleAutoRenew(Request $request, Website $website, WebsiteDomain $domain): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureDomainBelongsToWebsite($website, $domain);
        $this->authorize('update', $domain);

        $validated = $request->validate([
            'auto_renew' => ['required', 'boolean'],
        ]);

        $this->domainService->setAutoRenew($domain, (bool) $validated['auto_renew']);

        return back()->with('success', $validated['auto_renew'] ? 'تم تفعيل التجديد التلقائي.' : 'تم إيقاف التجديد التلقائي.');
    }

    public function remove(Request $request, Website $website, WebsiteDomain $domain): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureDomainBelongsToWebsite($website, $domain);
        $this->authorize('delete', $domain);

        if ($domain->type === 'platform_subdomain') {
            return back()->with('error', 'لا يمكن حذف Platform subdomain الافتراضي.');
        }

        $this->domainService->cancelDomain($domain);

        return back()->with('success', 'تم إلغاء ربط الدومين من الموقع.');
    }

    private function ensureSameWorkspace(Website $website): void
    {
        abort_unless((int) $website->workspace_id === (int) $this->currentWorkspace()->id, 404);
    }

    private function ensureDomainBelongsToWebsite(Website $website, WebsiteDomain $domain): void
    {
        $this->ensureSameWorkspace($website);
        abort_unless((int) $domain->website_id === (int) $website->id, 404);
    }
}
