<?php

namespace App\Console\Commands;

use App\Mail\DigestMail;
use App\Models\Company;
use App\Notifications\BuildDigest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('ksef:send-digest')]
#[Description('E-mail each company with a notification address what needs attention: offline deadlines, new purchases, rejections')]
class SendDigestCommand extends Command
{
    public function handle(BuildDigest $build): int
    {
        $sent = 0;

        foreach (Company::query()->whereNotNull('notify_email')->get() as $company) {
            $digest = $build->for($company);
            if ($digest === null) {
                continue;
            }

            try {
                Mail::to((string) $company->notify_email)->send(new DigestMail($company, $digest));
            } catch (\Throwable $e) {
                // A broken mail server must not stop the others, and the news stays news: the date does not move.
                $this->error("{$company->name}: {$e->getMessage()}");

                continue;
            }

            $company->forceFill(['last_digest_at' => now()])->save();
            $sent++;
        }

        $this->info("Sent {$sent} summary(ies).");

        return self::SUCCESS;
    }
}
