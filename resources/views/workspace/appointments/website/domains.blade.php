@extends('layouts.appointments')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Domains</h2>
                <p class="text-sm text-slate-500">البحث، الشراء، الربط، والتحقق من حالة DNS/SSL.</p>
            </div>
            <a href="{{ route('workspace.appointments.website.customize', $website) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Back to Customize</a>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Search Domain</h3>
            <form method="POST" action="{{ route('workspace.appointments.website.domains.search', $website) }}" class="mt-4 flex flex-col gap-3 md:flex-row">
                @csrf
                <input type="text" name="query" value="{{ old('query', $lastSearchedQuery) }}" placeholder="aida-clinic" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Search</button>
            </form>

            @if($searchResults)
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-right font-semibold text-slate-600">Domain</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-600">Available</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-600">Premium</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-600">Reg. Price</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-600">Renewal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($searchResults as $row)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-slate-800">{{ $row['domain'] }}</td>
                                    <td class="px-3 py-2">{{ ($row['available'] ?? false) ? 'Yes' : 'No' }}</td>
                                    <td class="px-3 py-2">{{ ($row['is_premium'] ?? false) ? 'Yes' : 'No' }}</td>
                                    <td class="px-3 py-2">{{ $row['registration_price'] !== null ? number_format((float) $row['registration_price'], 2) : '-' }}</td>
                                    <td class="px-3 py-2">{{ $row['renewal_price'] !== null ? number_format((float) $row['renewal_price'], 2) : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Purchase Domain (Namecheap Sandbox)</h3>
            <form method="POST" action="{{ route('workspace.appointments.website.domains.purchase', $website) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Domain</label>
                    <input type="text" name="domain" value="{{ old('domain') }}" required class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Years</label>
                    <input type="number" min="1" max="10" name="years" value="{{ old('years', 1) }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">First Name</label>
                    <input type="text" name="contact[first_name]" value="{{ old('contact.first_name') }}" required class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Last Name</label>
                    <input type="text" name="contact[last_name]" value="{{ old('contact.last_name') }}" required class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Email</label>
                    <input type="email" name="contact[email]" value="{{ old('contact.email') }}" required class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Phone (+NNN.NNNNNNNNNN)</label>
                    <input type="text" name="contact[phone]" value="{{ old('contact.phone') }}" required class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Country (2-letter)</label>
                    <input type="text" name="contact[country]" value="{{ old('contact.country', 'US') }}" required class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">City</label>
                    <input type="text" name="contact[city]" value="{{ old('contact.city') }}" required class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">State / Province</label>
                    <input type="text" name="contact[state_province]" value="{{ old('contact.state_province') }}" required class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Postal Code</label>
                    <input type="text" name="contact[postal_code]" value="{{ old('contact.postal_code') }}" required class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Address Line 1</label>
                    <input type="text" name="contact[address1]" value="{{ old('contact.address1') }}" required class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Address Line 2</label>
                    <input type="text" name="contact[address2]" value="{{ old('contact.address2') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Start Purchase</button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Connected Domains</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Domain</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Type</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Status</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">DNS</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">SSL</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Expires</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Auto Renew</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Provider</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($domains as $domain)
                        <tr>
                            <td class="px-3 py-2 font-medium text-slate-800">
                                {{ $domain->domain }}
                                @if($domain->is_primary)
                                    <span class="mr-2 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Primary</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $domain->type }}</td>
                            <td class="px-3 py-2">{{ $domain->status }}</td>
                            <td class="px-3 py-2">{{ $domain->dns_status }}</td>
                            <td class="px-3 py-2">
                                {{ $domain->ssl_status }}
                                @if($domain->ssl_expires_at)
                                    <div class="text-[10px] text-slate-400">SSL exp {{ $domain->ssl_expires_at->format('Y-m-d') }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $domain->expires_at?->format('Y-m-d') ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $domain->auto_renew ? 'On' : 'Off' }}</td>
                            <td class="px-3 py-2 text-[11px] text-slate-500">
                                {{ $domain->provider }}
                                @if($domain->provider_domain_id)
                                    <div>ID: {{ $domain->provider_domain_id }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-1">
                                    <form method="POST" action="{{ route('workspace.appointments.website.domains.set-primary', [$website, $domain]) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-slate-300 px-2 py-1 text-[11px] font-semibold text-slate-700">Primary</button>
                                    </form>
                                    <form method="POST" action="{{ route('workspace.appointments.website.domains.verify', [$website, $domain]) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-slate-300 px-2 py-1 text-[11px] font-semibold text-slate-700">Verify</button>
                                    </form>
                                    <form method="POST" action="{{ route('workspace.appointments.website.domains.renew', [$website, $domain]) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-slate-300 px-2 py-1 text-[11px] font-semibold text-slate-700">Renew</button>
                                    </form>
                                    <form method="POST" action="{{ route('workspace.appointments.website.domains.sync', [$website, $domain]) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-slate-300 px-2 py-1 text-[11px] font-semibold text-slate-700">Sync</button>
                                    </form>
                                    @if($domain->type !== 'platform_subdomain')
                                        <form method="POST" action="{{ route('workspace.appointments.website.domains.auto-renew', [$website, $domain]) }}">
                                            @csrf
                                            <input type="hidden" name="auto_renew" value="{{ $domain->auto_renew ? 0 : 1 }}">
                                            <button type="submit" class="rounded-lg border border-slate-300 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                                {{ $domain->auto_renew ? 'Disable Auto Renew' : 'Enable Auto Renew' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('workspace.appointments.website.domains.remove', [$website, $domain]) }}" onsubmit="return confirm('Disconnect this domain?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-rose-200 px-2 py-1 text-[11px] font-semibold text-rose-700">Disconnect</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">No connected domains yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
