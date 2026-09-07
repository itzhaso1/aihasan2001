<?php

use App\Jobs\Stories\ExpireStoriesJob;
use App\Jobs\SyncDomainStatusJob;
use App\Models\Website\WebsiteDomain;
use App\Services\Appointments\AppointmentReminderService;
use App\Services\Domain\DomainService;
use App\Services\Finance\BillingScheduleService;
use App\Services\Finance\InvoiceReminderService;
use App\Services\Finance\InvoiceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('appointments:reminders:prepare', function (AppointmentReminderService $service) {
    $created = $service->scheduleUpcomingReminders(14);
    $this->info("تم تجهيز {$created} تذكير للمواعيد القادمة.");
})->purpose('Prepare appointment reminders for upcoming bookings');

Artisan::command('appointments:reminders:dispatch', function (AppointmentReminderService $service) {
    $processed = $service->dispatchDueReminders(200);
    $this->info("تمت معالجة {$processed} تذكير مستحق.");
})->purpose('Dispatch due appointment reminders');

Artisan::command('finance:invoices:refresh-payment-status', function (InvoiceService $service) {
    $updated = $service->refreshIssuedPaymentStatuses();
    $this->info("تم تحديث {$updated} فاتورة لحالة الدفع الفعلية.");
})->purpose('Recalculate issued invoices payment status and overdue state');

Artisan::command('finance:invoices:send-reminders', function (InvoiceReminderService $service) {
    $counts = $service->dispatchDueReminders();
    $this->info(sprintf(
        'تذكيرات الفواتير: قادمة %d، مستحقة اليوم %d، متأخرة %d.',
        $counts['upcoming'],
        $counts['due'],
        $counts['overdue']
    ));
})->purpose('Send upcoming, due-today, and overdue finance invoice reminders');

Artisan::command('finance:billing-schedules:generate', function (BillingScheduleService $service) {
    $generated = $service->generateDueInvoices();
    $this->info("تم توليد {$generated} فاتورة من جداول الفوترة المستحقة.");
})->purpose('Generate due finance invoices from activated billing schedules');

Artisan::command('finance:invoices:status-audit', function () {
    $rows = DB::table('finance_invoices')
        ->selectRaw('status, COUNT(*) as total')
        ->groupBy('status')
        ->orderBy('status')
        ->get();

    if ($rows->isEmpty()) {
        $this->warn('لا توجد فواتير حالية لتحليل الحالات.');

        return;
    }

    $this->line('Legacy status distribution:');
    foreach ($rows as $row) {
        $this->line(sprintf('- %s: %d', $row->status, $row->total));
    }
})->purpose('Show current finance_invoices legacy status counts before migration');

Artisan::command('finance:invoices:integrity-report', function () {
    $invoiceCount = DB::table('finance_invoices')->count();
    $paymentCount = DB::table('finance_invoice_payments')->count();
    $total = (float) DB::table('finance_invoices')->sum('total');
    $paid = (float) DB::table('finance_invoices')->sum('amount_paid');
    $due = (float) DB::table('finance_invoices')->sum('amount_due');

    $this->line('Invoice integrity metrics:');
    $this->line(sprintf('- invoices_count: %d', $invoiceCount));
    $this->line(sprintf('- payments_count: %d', $paymentCount));
    $this->line(sprintf('- totals_sum: %.2f', $total));
    $this->line(sprintf('- amount_paid_sum: %.2f', $paid));
    $this->line(sprintf('- amount_due_sum: %.2f', $due));
})->purpose('Output baseline invoice counters and totals for migration integrity checks');

Schedule::command('appointments:reminders:prepare')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('appointments:reminders:dispatch')->everyMinute()->withoutOverlapping(4);
Schedule::command('finance:invoices:refresh-payment-status')->hourly()->withoutOverlapping(50);
Schedule::command('finance:invoices:send-reminders')->dailyAt('08:00')->withoutOverlapping(120);
Schedule::command('finance:billing-schedules:generate')->dailyAt('01:15')->withoutOverlapping(120);
Schedule::job(new ExpireStoriesJob)->hourly()->withoutOverlapping(50);

Artisan::command('domains:sync-status', function () {
    $count = 0;
    WebsiteDomain::withoutGlobalScopes()
        ->whereIn('status', ['registered', 'dns_pending', 'dns_configured', 'verifying', 'verified', 'ssl_pending', 'active', 'recovery_required'])
        ->chunkById(100, function ($domains) use (&$count): void {
            foreach ($domains as $domain) {
                SyncDomainStatusJob::dispatch($domain->id);
                $count++;
            }
        });

    $this->info("Queued {$count} domains for status sync.");
})->purpose('Queue provider status sync for website domains');

Artisan::command('domains:auto-renew', function (DomainService $domainService) {
    $count = $domainService->processDueAutoRenewals();
    $this->info("Queued {$count} auto-renewal jobs.");
})->purpose('Queue auto renewals for domains nearing expiration');

Artisan::command('domains:expiration-reminders', function (DomainService $domainService) {
    $count = $domainService->processExpirationReminders();
    $this->info("Sent {$count} domain expiration reminders.");
})->purpose('Send domain expiration reminders without duplicates');

Artisan::command('domains:ssl-maintain', function (DomainService $domainService) {
    $count = $domainService->processSslMaintenance();
    $this->info("Processed SSL maintenance for {$count} domains.");
})->purpose('Provision/renew/sync SSL certificates for website domains');

Schedule::command('domains:sync-status')->dailyAt('02:10')->withoutOverlapping(120);
Schedule::command('domains:expiration-reminders')->dailyAt('08:15')->withoutOverlapping(60);
Schedule::command('domains:auto-renew')->dailyAt('03:20')->withoutOverlapping(120);
Schedule::command('domains:ssl-maintain')->dailyAt('04:05')->withoutOverlapping(120);
