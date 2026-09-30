# Renee AgriSuite Private Worker Infrastructure

Renee AgriSuite runs several scheduled workers outside the public web root.

The production private root is normally:

    $HOME/renee-private

The portability installer derives `$HOME` from the target hosting account.
It does not assume a fixed cPanel home path.


## Credential worker

Canonical repository source:

    scripts/run_v310_account_credential_outbox.php
    scripts/run_v310_credential_worker_production.sh
    scripts/v310_private_cli_bridge.php

Installed under:

    $HOME/renee-private/v310-credential-worker/


## Subscription lifecycle worker

Application/business authority remains:

    scripts/run_v320_subscription_lifecycle.php

Private production authority wrapper:

    deployment/private-workers/v320-subscription-lifecycle/
        run_v320_subscription_lifecycle.php

Private overlap-prevention launcher:

    deployment/private-workers/v320-subscription-lifecycle/
        run_v320_subscription_lifecycle_production.sh

Installed under:

    $HOME/renee-private/v320-subscription-lifecycle/


## Subscription renewal reminder worker

Application/business authority remains:

    scripts/run_v230_subscription_renewal_reminders.php

Private production authority wrapper:

    deployment/private-workers/v320-subscription-reminder/
        run_v320_subscription_renewal_reminders.php

Private overlap-prevention launcher:

    deployment/private-workers/v320-subscription-reminder/
        run_v320_subscription_renewal_reminders_production.sh

Installed under:

    $HOME/renee-private/v320-subscription-reminder/


## Runtime files that are NOT source

Do not commit:

    production-launcher.lock
    lifecycle-worker.lock
    reminder-worker.lock
    worker.log
    other runtime logs

These are generated automatically on the server.


## Secrets

No DB password, SMTP password, billing provider secret, token or
production credential belongs in this directory in GitHub.

Production authority comes from the target server environment.
