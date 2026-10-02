<?php

namespace App\Console\Commands;

use App\Mail\AnniversaryWishMail;
use App\Mail\BirthdayWishMail;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBirthdayAnniversaryWishes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:send-wishes 
                            {--dry-run : Execute simulation without actually dispatching emails}
                            {--force : Bypass already-wished checks and force re-send}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily automated cron to send celebratory Birthday and Anniversary greetings to leads with deduplication';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');
        $nowIst = now('Asia/Kolkata');
        $todayMonthDay = $nowIst->format('m-d');
        $currentYear = (int) $nowIst->year;

        $this->info("====================================================================");
        $this->info(" Running Daily Customer Greetings Automation [Date: " . $nowIst->toFormattedDateString() . " IST] ");
        $this->info(" Time: " . $nowIst->format('h:i:s A T') . " | Timezone: Asia/Kolkata (IST)");
        $this->info(" Mode: " . ($isDryRun ? "DRY-RUN SIMULATION (No emails sent)" : "LIVE DISPATCH") . ($isForce ? " [FORCE OVERRIDE]" : ""));
        $this->info("====================================================================\n");

        $stats = [
            'birthdays_found' => 0,
            'birthdays_sent' => 0,
            'birthdays_already_wished' => 0,
            'anniversaries_found' => 0,
            'anniversaries_sent' => 0,
            'anniversaries_already_wished' => 0,
            'skipped_duplicates' => 0,
            'errors' => 0,
        ];

        // ---------------------------------------------------------------------
        // 1. PROCESS BIRTHDAY WISHES
        // ---------------------------------------------------------------------
        $this->info("--- Checking Birthdays matching [MM-DD: {$todayMonthDay}] ---");

        $allMatchingBday = Lead::whereNotNull('birth_date')
            ->whereRaw("DATE_FORMAT(birth_date, '%m-%d') = ?", [$todayMonthDay])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        $alreadyWishedBday = $allMatchingBday->filter(function ($lead) use ($currentYear) {
            return $lead->last_birthday_wished_year == $currentYear;
        });
        $stats['birthdays_already_wished'] = $alreadyWishedBday->count();

        $birthdayLeads = $isForce
            ? $allMatchingBday
            : $allMatchingBday->filter(function ($lead) use ($currentYear) {
                return empty($lead->last_birthday_wished_year) || $lead->last_birthday_wished_year < $currentYear;
            });

        $stats['birthdays_found'] = $birthdayLeads->count();

        if ($stats['birthdays_already_wished'] > 0 && !$isForce) {
            $this->line("  ℹ️  {$stats['birthdays_already_wished']} lead(s) with birthday today were already sent wishes for {$currentYear} (use --force or force=true to override).");
            foreach ($alreadyWishedBday as $aw) {
                $this->line("     • {$aw->name} ({$aw->email}) - Already wished in {$currentYear}");
            }
        }

        $this->info("Found {$stats['birthdays_found']} eligible birthday lead record(s) to send.");

        if ($birthdayLeads->isNotEmpty()) {
            // Deduplicate by normalized email address
            $birthdayGroups = $birthdayLeads->groupBy(function ($lead) {
                return strtolower(trim($lead->email));
            });

            foreach ($birthdayGroups as $email => $leadsGroup) {
                $count = $leadsGroup->count();
                if ($count > 1) {
                    $stats['skipped_duplicates'] += ($count - 1);
                    $this->line("  [Deduplication] Multiple leads ({$count}) found for email: {$email}. Consolidating to 1 email send.");
                }

                // Pick primary lead record (latest created)
                $primaryLead = $leadsGroup->sortByDesc('id')->first();

                if ($isDryRun) {
                    $this->info("  [DRY-RUN] Would send Birthday Wish to: {$email} (Customer: {$primaryLead->name}, Lead #{$primaryLead->id})");
                    $stats['birthdays_sent']++;
                } else {
                    try {
                        // Send single email to customer
                        Mail::to($email)->send(new BirthdayWishMail($primaryLead));

                        // Mark all matching lead records as wished for current year
                        Lead::whereIn('id', $leadsGroup->pluck('id'))->update([
                            'last_birthday_wished_year' => $currentYear,
                        ]);

                        // Record timeline follow-up interaction for each lead
                        foreach ($leadsGroup as $lead) {
                            LeadFollowUp::create([
                                'lead_id' => $lead->id,
                                'user_id' => $lead->assigned_to ?? 1,
                                'follow_up_date' => $nowIst->toDateString(),
                                'follow_up_time' => $nowIst->format('h:i A'),
                                'type' => 'Email',
                                'notes' => "🎂 Happy Birthday greetings email automatically sent to {$email} by daily CRM scheduler at 11:00 AM IST.",
                                'status' => 'Completed',
                            ]);
                        }

                        $stats['birthdays_sent']++;
                        $this->info("  [SENT] Birthday Wish emailed to: {$email} (Customer: {$primaryLead->name})");
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        $this->error("  [ERROR] Failed to email {$email}: " . $e->getMessage());
                        Log::error("Failed to send birthday email to {$email}: " . $e->getMessage(), [
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            }
        } else {
            $this->line("No birthday greetings scheduled for today.\n");
        }

        // ---------------------------------------------------------------------
        // 2. PROCESS ANNIVERSARY WISHES
        // ---------------------------------------------------------------------
        $this->info("\n--- Checking Anniversaries matching [MM-DD: {$todayMonthDay}] ---");

        $allMatchingAnni = Lead::whereNotNull('anniversary_date')
            ->whereRaw("DATE_FORMAT(anniversary_date, '%m-%d') = ?", [$todayMonthDay])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        $alreadyWishedAnni = $allMatchingAnni->filter(function ($lead) use ($currentYear) {
            return $lead->last_anniversary_wished_year == $currentYear;
        });
        $stats['anniversaries_already_wished'] = $alreadyWishedAnni->count();

        $anniversaryLeads = $isForce
            ? $allMatchingAnni
            : $allMatchingAnni->filter(function ($lead) use ($currentYear) {
                return empty($lead->last_anniversary_wished_year) || $lead->last_anniversary_wished_year < $currentYear;
            });

        $stats['anniversaries_found'] = $anniversaryLeads->count();

        if ($stats['anniversaries_already_wished'] > 0 && !$isForce) {
            $this->line("  ℹ️  {$stats['anniversaries_already_wished']} lead(s) with anniversary today were already sent wishes for {$currentYear} (use --force or force=true to override).");
            foreach ($alreadyWishedAnni as $aw) {
                $this->line("     • {$aw->name} ({$aw->email}) - Already wished in {$currentYear}");
            }
        }

        $this->info("Found {$stats['anniversaries_found']} eligible anniversary lead record(s) to send.");

        if ($anniversaryLeads->isNotEmpty()) {
            // Deduplicate by normalized email address
            $anniversaryGroups = $anniversaryLeads->groupBy(function ($lead) {
                return strtolower(trim($lead->email));
            });

            foreach ($anniversaryGroups as $email => $leadsGroup) {
                $count = $leadsGroup->count();
                if ($count > 1) {
                    $stats['skipped_duplicates'] += ($count - 1);
                    $this->line("  [Deduplication] Multiple leads ({$count}) found for email: {$email}. Consolidating to 1 email send.");
                }

                $primaryLead = $leadsGroup->sortByDesc('id')->first();

                if ($isDryRun) {
                    $this->info("  [DRY-RUN] Would send Anniversary Wish to: {$email} (Customer: {$primaryLead->name}, Lead #{$primaryLead->id})");
                    $stats['anniversaries_sent']++;
                } else {
                    try {
                        Mail::to($email)->send(new AnniversaryWishMail($primaryLead));

                        Lead::whereIn('id', $leadsGroup->pluck('id'))->update([
                            'last_anniversary_wished_year' => $currentYear,
                        ]);

                        foreach ($leadsGroup as $lead) {
                            LeadFollowUp::create([
                                'lead_id' => $lead->id,
                                'user_id' => $lead->assigned_to ?? 1,
                                'follow_up_date' => $nowIst->toDateString(),
                                'follow_up_time' => $nowIst->format('h:i A'),
                                'type' => 'Email',
                                'notes' => "💐 Happy Anniversary greetings email automatically sent to {$email} by daily CRM scheduler at 11:00 AM IST.",
                                'status' => 'Completed',
                            ]);
                        }

                        $stats['anniversaries_sent']++;
                        $this->info("  [SENT] Anniversary Wish emailed to: {$email} (Customer: {$primaryLead->name})");
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        $this->error("  [ERROR] Failed to email {$email}: " . $e->getMessage());
                        Log::error("Failed to send anniversary email to {$email}: " . $e->getMessage(), [
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            }
        } else {
            $this->line("No anniversary greetings scheduled for today.\n");
        }

        $this->info("\n====================================================================");
        $this->info(" Summary: Birthdays Sent: {$stats['birthdays_sent']} | Anniversaries Sent: {$stats['anniversaries_sent']} | Duplicate Leads Merged: {$stats['skipped_duplicates']} | Errors: {$stats['errors']}");
        $this->info("====================================================================");

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
