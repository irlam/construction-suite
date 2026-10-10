# Deliveries site calendar integration

Deliveries now publishes real site references and requires an explicit site for shared-installation summaries. Map Rochdale Road (McGoff Construction) to Deliveries site `1`; do not use `__all__`. Dashboard record links target `/schedule.php?site=1` and the source application independently checks site membership and company ownership.

This changes site reporting and calendar links only. It does not mark the shared module as a verified tenant instance or broaden Suite roles. Company users must have their own Deliveries account and an assigned site. The current shared API key is an administrative reporting connection; use a company-bound server key/configuration for a dedicated company connection. Suite SSO needs a separately verified adapter and explicit identity/company mapping before enabling tenant readiness.
